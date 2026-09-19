<?php
declare(strict_types=1);

namespace App\Modules\Buyer\Returns;

use App\Core\Auth;
use App\Core\Config;
use App\Modules\Buyer\Payment\PaymentModel;
use App\Modules\Supplier\Invoice\InvoiceModel;
use Exception;
use Stripe\StripeClient;
use Throwable;

/**
 * Buyer returns and the Stripe refunds that follow them.
 *
 * Flow: buyer requests (REQUESTED) -> supplier or Admin approves/rejects
 * -> on receipt of the goods, supplier or Admin confirms and the refund is
 * issued through Stripe (REFUNDING -> REFUNDED, or REFUND_FAILED, which
 * can be retried). The refund never exceeds what was actually paid, and
 * every status change is written to return_request_events.
 */
class ReturnController
{
    private ReturnModel $model;
    private InvoiceModel $invoiceModel;
    private PaymentModel $paymentModel;

    public function __construct()
    {
        $this->model = new ReturnModel();
        $this->invoiceModel = new InvoiceModel();
        $this->paymentModel = new PaymentModel();
    }

    private function stripeClient(): StripeClient
    {
        return new StripeClient(Config::require('STRIPE_SECRET_KEY'));
    }

    // ---------------------------------------------------------------
    // CSRF - these forms move money, so they are token-protected.
    // ---------------------------------------------------------------

    public static function csrfToken(): string
    {
        if (empty($_SESSION['return_csrf'])) {
            $_SESSION['return_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['return_csrf'];
    }

    public static function assertCsrf(?string $token): void
    {
        $expected = $_SESSION['return_csrf'] ?? '';
        if ($expected === '' || $token === null || !hash_equals($expected, $token)) {
            throw new Exception('Your session expired. Please reload the page and try again.');
        }
    }

    private static function role(): string
    {
        return strtolower((string) ($_SESSION['role'] ?? ''));
    }

    private static function companyId(): int
    {
        return (int) ($_SESSION['company_id'] ?? 0);
    }

    private static function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    // ---------------------------------------------------------------
    // Buyer: request a return
    // ---------------------------------------------------------------

    /** Data for the buyer's return request form. */
    public function getRequestFormData(int $invoiceId): array
    {
        Auth::checkBuyer();
        $detail = $this->loadBuyerInvoice($invoiceId);
        $poId = (int) $detail['po']['po_id'];

        $totalPaid = $this->paymentModel->getTotalPaid($invoiceId);
        $totalRefunded = $this->model->getTotalRefunded($invoiceId);

        return [
            'detail'        => $detail,
            'lines'         => $this->model->getPoLinesWithReturned($poId),
            'total_paid'    => $totalPaid,
            'refundable'    => ReturnRules::refundableAmount($totalPaid, $totalRefunded),
            'can_return'    => $detail['po']['po_status'] === 'DELIVERED' && $totalPaid > 0,
        ];
    }

    /**
     * @param array<int|string, mixed> $quantities purchase_order_items.id => quantity to return
     */
    public function requestReturn(int $invoiceId, array $quantities, string $reason): int
    {
        Auth::checkBuyer();
        $detail = $this->loadBuyerInvoice($invoiceId);
        $poId = (int) $detail['po']['po_id'];

        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw new Exception('Please give a reason for the return (up to 2000 characters).');
        }
        if ($detail['po']['po_status'] !== 'DELIVERED') {
            throw new Exception('Only delivered orders can be returned.');
        }

        $totalPaid = $this->paymentModel->getTotalPaid($invoiceId);
        if ($totalPaid <= 0) {
            throw new Exception('Only paid invoices can be returned and refunded.');
        }

        $this->model->begin();
        try {
            $poLines = [];
            foreach ($this->model->getPoLinesWithReturned($poId, true) as $line) {
                $poLines[(int) $line['id']] = $line;
            }

            $lines = [];
            $total = 0.0;
            foreach ($quantities as $itemId => $qty) {
                $qty = (float) $qty;
                if ($qty <= 0) {
                    continue;
                }
                $itemId = (int) $itemId;
                if (!isset($poLines[$itemId])) {
                    throw new Exception('One of the selected items is not on this order.');
                }
                $line = $poLines[$itemId];
                $max = ReturnRules::returnableQuantity((float) $line['quantity'], (float) $line['returned_quantity']);
                if ($qty > $max + 0.0005) {
                    throw new Exception('You can return at most ' . rtrim(rtrim(number_format($max, 3, '.', ''), '0'), '.')
                        . ' of "' . $line['material_name'] . '".');
                }
                $lineTotal = ReturnRules::lineTotal($qty, (float) $line['unit_price']);
                $lines[] = [
                    'po_item_id' => $itemId,
                    'quantity'   => round($qty, 3),
                    'unit_price' => (float) $line['unit_price'],
                    'line_total' => $lineTotal,
                ];
                $total += $lineTotal;
            }

            if (!$lines) {
                throw new Exception('Select at least one item and a quantity to return.');
            }

            $refundable = ReturnRules::refundableAmount($totalPaid, $this->model->getTotalRefunded($invoiceId));
            if (ReturnRules::toCents($total) > ReturnRules::toCents($refundable)) {
                throw new Exception('This return is worth $' . number_format($total, 2)
                    . ' but only $' . number_format($refundable, 2) . ' has been paid and not yet refunded on this invoice.');
            }

            $returnId = $this->model->createReturn(
                $invoiceId,
                $poId,
                (int) $detail['po']['buyer_company_id'],
                (int) $detail['po']['supplier_company_id'],
                $reason,
                ReturnRules::fromCents(ReturnRules::toCents($total)),
                self::userId(),
                $lines
            );
            $this->model->addEvent($returnId, 'REQUESTED', self::userId(), 'buyer', $reason);
            $this->model->commit();
        } catch (Throwable $e) {
            $this->model->rollback();
            throw $e;
        }

        $return = $this->model->getReturn($returnId);
        if ($return) {
            ReturnNotifier::requested($return);
        }

        return $returnId;
    }

    private function loadBuyerInvoice(int $invoiceId): array
    {
        $detail = $this->invoiceModel->getInvoiceDetail($invoiceId);
        if (!$detail) {
            throw new Exception('Invoice not found.');
        }
        $companyId = self::companyId();
        if ($companyId <= 0 || (int) $detail['po']['buyer_company_id'] !== $companyId) {
            throw new Exception('You do not have permission to return items on this invoice.');
        }
        return $detail;
    }

    // ---------------------------------------------------------------
    // Viewing and listing (any signed-in role, scoped to what it may see)
    // ---------------------------------------------------------------

    public function listForCurrentUser(): array
    {
        Auth::requireLogin();
        switch (self::role()) {
            case 'admin':
                return $this->model->listReturns(null, null);
            case 'supplier':
                return $this->model->listReturns(null, self::companyId());
            case 'buyer':
                return $this->model->listReturns(self::companyId(), null);
        }
        return [];
    }

    /** Return detail plus what the current user is allowed to do with it. */
    public function getForViewer(int $returnId): array
    {
        Auth::requireLogin();
        $return = $this->model->getReturn($returnId);
        if (!$return) {
            throw new Exception('Return not found.');
        }

        $role = self::role();
        $company = self::companyId();
        $isBuyer    = $role === 'buyer' && (int) $return['buyer_company_id'] === $company && $company > 0;
        $isSupplier = $role === 'supplier' && (int) $return['supplier_company_id'] === $company && $company > 0;
        $isAdmin    = $role === 'admin';

        if (!$isBuyer && !$isSupplier && !$isAdmin) {
            throw new Exception('You do not have permission to view this return.');
        }

        $status = $return['status'];
        $canManage = $isSupplier || $isAdmin;

        return [
            'return'      => $return,
            'events'      => $this->model->getEvents($returnId),
            'refunds'     => $this->model->getRefundsForReturn($returnId),
            'can_decide'  => $canManage && $status === ReturnRules::REQUESTED,
            'can_refund'  => $canManage && in_array($status, [ReturnRules::APPROVED, ReturnRules::REFUND_FAILED], true),
        ];
    }

    private function loadManageable(int $returnId): array
    {
        Auth::requireLogin();
        $return = $this->model->getReturn($returnId);
        if (!$return) {
            throw new Exception('Return not found.');
        }

        $role = self::role();
        $ownsAsSupplier = $role === 'supplier'
            && self::companyId() > 0
            && (int) $return['supplier_company_id'] === self::companyId();

        if ($role !== 'admin' && !$ownsAsSupplier) {
            throw new Exception('You do not have permission to manage this return.');
        }
        return $return;
    }

    // ---------------------------------------------------------------
    // Supplier / Admin: decide
    // ---------------------------------------------------------------

    public function approve(int $returnId, ?string $comment): void
    {
        $this->decide($returnId, true, $comment);
    }

    public function reject(int $returnId, ?string $comment): void
    {
        if ($comment === null || trim($comment) === '') {
            throw new Exception('Please give a reason for rejecting the return.');
        }
        $this->decide($returnId, false, $comment);
    }

    private function decide(int $returnId, bool $approve, ?string $comment): void
    {
        $return = $this->loadManageable($returnId);
        $to = $approve ? ReturnRules::APPROVED : ReturnRules::REJECTED;
        $comment = ($comment !== null && trim($comment) !== '') ? trim($comment) : null;

        if (!ReturnRules::canTransition($return['status'], $to)
            || !$this->model->transition($returnId, ReturnRules::REQUESTED, $to, self::userId(), $comment)) {
            throw new Exception('This return is no longer awaiting a decision.');
        }

        $this->model->addEvent($returnId, $to, self::userId(), self::role(), $comment);
        ReturnNotifier::decided($this->model->getReturn($returnId) ?? $return, $approve, $comment);
    }

    // ---------------------------------------------------------------
    // Supplier / Admin: goods received -> refund through Stripe
    // ---------------------------------------------------------------

    /** Confirms the goods came back and issues (or retries) the refund. Returns the resulting status. */
    public function confirmReceiptAndRefund(int $returnId): string
    {
        $return = $this->loadManageable($returnId);
        $from = $return['status'];

        if (!in_array($from, [ReturnRules::APPROVED, ReturnRules::REFUND_FAILED], true)) {
            throw new Exception('This return is not ready to be refunded.');
        }
        // Atomic claim: a double-click or a second approver loses here and
        // can never trigger a second refund run.
        if (!$this->model->transition($returnId, $from, ReturnRules::REFUNDING, self::userId(), null)) {
            throw new Exception('This refund is already being processed.');
        }
        $this->model->addEvent($returnId, 'REFUND_STARTED', self::userId(), self::role(), null);
        $this->model->setFailure($returnId, null);

        try {
            $this->issueRefunds($return);
        } catch (Throwable $e) {
            $this->model->setFailure($returnId, mb_substr($e->getMessage(), 0, 500));
        }

        return $this->finalizeReturn($returnId);
    }

    /** Creates and sends Stripe refunds for whatever part of the return is still unrefunded. */
    private function issueRefunds(array $return): void
    {
        $returnId = (int) $return['id'];
        $invoiceId = (int) $return['invoice_id'];

        $remainingCents = ReturnRules::toCents((float) $return['refund_total'])
            - ReturnRules::toCents($this->model->getRefundedForReturn($returnId));
        if ($remainingCents <= 0) {
            return;
        }
        $remaining = ReturnRules::fromCents($remainingCents);

        $refundable = ReturnRules::refundableAmount(
            $this->paymentModel->getTotalPaid($invoiceId),
            $this->model->getTotalRefunded($invoiceId)
        );
        if ($remainingCents > ReturnRules::toCents($refundable)) {
            throw new Exception('Refund of $' . number_format($remaining, 2)
                . ' exceeds the $' . number_format($refundable, 2) . ' still refundable on this invoice.');
        }

        $payments = $this->model->getRefundablePayments($invoiceId);
        $allocations = ReturnRules::allocateRefund($remaining, $payments);
        $paymentIntents = [];
        foreach ($payments as $p) {
            $paymentIntents[(int) $p['id']] = $p['stripe_payment_intent_id'];
        }

        $stripe = $this->stripeClient();
        foreach ($allocations as $allocation) {
            $rowId = $this->model->createRefundRow(
                $returnId,
                $invoiceId,
                $allocation['payment_id'],
                $allocation['amount'],
                self::userId() ?: null
            );

            try {
                $refund = $stripe->refunds->create([
                    'payment_intent' => $paymentIntents[$allocation['payment_id']],
                    'amount'         => ReturnRules::toCents($allocation['amount']),
                    'reason'         => 'requested_by_customer',
                    'metadata'       => [
                        'return_request_id' => (string) $returnId,
                        'invoice_id'        => (string) $invoiceId,
                        'refund_row_id'     => (string) $rowId,
                    ],
                ], ['idempotency_key' => "return-{$returnId}-refund-{$rowId}"]);
            } catch (Throwable $e) {
                $this->model->setRefundResult($rowId, 'FAILED', null, mb_substr($e->getMessage(), 0, 500));
                $this->syncInvoice($invoiceId);
                throw $e;
            }

            $status = strtolower((string) $refund->status);
            if ($status === 'succeeded') {
                $this->model->setRefundResult($rowId, 'SUCCEEDED', $refund->id, null);
            } elseif (in_array($status, ['failed', 'canceled'], true)) {
                $message = (string) ($refund->failure_reason ?? 'Stripe refund ' . $status);
                $this->model->setRefundResult($rowId, 'FAILED', $refund->id, $message);
                $this->syncInvoice($invoiceId);
                throw new Exception($message);
            } else {
                // pending / requires_action: settles later; the webhook finalises it.
                $this->model->setRefundResult($rowId, 'PENDING', $refund->id, null);
            }
            $this->syncInvoice($invoiceId);
        }
    }

    /**
     * Settle the return's status from its refund rows. Idempotent: it is
     * guarded by compare-and-set, so the webhook and the request that
     * started the refund can both call it safely.
     */
    private function finalizeReturn(int $returnId): string
    {
        $return = $this->model->getReturn($returnId);
        if (!$return) {
            throw new Exception('Return not found.');
        }
        if ($return['status'] !== ReturnRules::REFUNDING) {
            return $return['status'];
        }

        $counts = $this->model->getRefundCountsForReturn($returnId);
        if ($counts['PENDING'] > 0) {
            return ReturnRules::REFUNDING;
        }

        $refundedCents = ReturnRules::toCents($this->model->getRefundedForReturn($returnId));
        if ($refundedCents >= ReturnRules::toCents((float) $return['refund_total'])) {
            if ($this->model->transition($returnId, ReturnRules::REFUNDING, ReturnRules::REFUNDED)) {
                $this->model->addEvent($returnId, 'REFUNDED', null, null, null);
                ReturnNotifier::refunded($return);
            }
            return ReturnRules::REFUNDED;
        }

        $message = $return['failure_message'] ?: 'The refund did not complete.';
        if ($this->model->transition($returnId, ReturnRules::REFUNDING, ReturnRules::REFUND_FAILED)) {
            $this->model->addEvent($returnId, 'REFUND_FAILED', null, null, $message);
            ReturnNotifier::refundFailed($return, $message);
        }
        return ReturnRules::REFUND_FAILED;
    }

    private function syncInvoice(int $invoiceId): void
    {
        $this->model->syncInvoiceRefund($invoiceId, $this->paymentModel->getTotalPaid($invoiceId));
    }

    // ---------------------------------------------------------------
    // Stripe webhook (refund.updated / refund.failed)
    // ---------------------------------------------------------------

    public function applyRefundEvent(object $stripeRefund): void
    {
        $row = $this->model->findRefundByStripeId((string) $stripeRefund->id);
        if (!$row) {
            return;
        }

        $status = strtolower((string) $stripeRefund->status);
        if ($status === 'succeeded') {
            $changed = $this->model->finalizePendingRefund((int) $row['id'], 'SUCCEEDED', null);
        } elseif (in_array($status, ['failed', 'canceled'], true)) {
            $message = (string) ($stripeRefund->failure_reason ?? 'Stripe refund ' . $status);
            $changed = $this->model->finalizePendingRefund((int) $row['id'], 'FAILED', $message);
            if ($changed) {
                $this->model->setFailure((int) $row['return_request_id'], mb_substr($message, 0, 500));
            }
        } else {
            return;
        }

        if ($changed) {
            $this->syncInvoice((int) $row['invoice_id']);
            $this->finalizeReturn((int) $row['return_request_id']);
        }
    }
}
