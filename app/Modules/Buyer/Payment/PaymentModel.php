<?php
namespace App\Modules\Buyer\Payment;

use App\Core\DB;

/**
 * Payment records against an invoice, backed by Stripe (Payment Intents -
 * cards and ACH bank debits through the same API). Rows are written twice:
 * once as PENDING the moment a PaymentIntent is created (so we never lose
 * track of an attempt), then flipped to SUCCEEDED/FAILED once Stripe
 * confirms the outcome (via the buyer's browser redirect and/or the
 * webhook - whichever arrives first; both paths are idempotent on
 * stripe_payment_intent_id).
 *
 * We never see or store a raw card number, CVC, or full bank account/
 * routing number - Stripe.js's Payment Element collects those directly
 * in the buyer's browser and hands Stripe a token. card_last4/card_expiry/
 * bank_last4 etc. are populated from what Stripe reports back after the
 * fact, purely for display in the payment history table.
 *
 * Deliberately keyed on invoice_id only, not purchase_order_id - a payment
 * settles an invoice, not a PO.
 *
 * Amount paid is always summed live from invoice_payments (status =
 * SUCCEEDED only - PENDING/FAILED attempts don't count) so it can never
 * drift. po_invoices.payment_status is a cached label kept in sync on
 * every write purely so list views can filter/badge without summing
 * every row.
 */
class PaymentModel
{
    private \mysqli $conn;

    public function __construct()
    {
        $this->conn = DB::getConnection();
    }

    public function getPayments(int $invoiceId): array
    {
        $stmt = $this->conn->prepare("
            SELECT id, amount, payment_date, payment_method, status,
                   bank_account_name, bank_last4,
                   card_holder_name, card_last4, card_expiry,
                   failure_message, notes, created_at
            FROM invoice_payments
            WHERE invoice_id = ?
            ORDER BY created_at DESC, id DESC
        ");
        $stmt->bind_param("i", $invoiceId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getTotalPaid(int $invoiceId): float
    {
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total_paid
            FROM invoice_payments
            WHERE invoice_id = ? AND status = 'SUCCEEDED'
        ");
        $stmt->bind_param("i", $invoiceId);
        $stmt->execute();
        return (float) ($stmt->get_result()->fetch_assoc()['total_paid'] ?? 0);
    }

    public function findByPaymentIntentId(string $paymentIntentId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM invoice_payments WHERE stripe_payment_intent_id = ?");
        $stmt->bind_param("s", $paymentIntentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    /**
     * Written the moment we create a Stripe PaymentIntent, before the
     * buyer has actually completed payment - lets us track and reconcile
     * abandoned/failed attempts, not just successful ones.
     */
    public function createPendingPayment(int $invoiceId, float $amount, string $paymentIntentId, ?string $notes, int $recordedBy): int
    {
        $stmt = $this->conn->prepare("
            INSERT INTO invoice_payments
                (invoice_id, amount, payment_date, payment_method, status, stripe_payment_intent_id, notes, recorded_by, created_at)
            VALUES (?, ?, CURDATE(), NULL, 'PENDING', ?, ?, ?, NOW())
        ");
        $stmt->bind_param("idssi", $invoiceId, $amount, $paymentIntentId, $notes, $recordedBy);
        $stmt->execute();

        return (int) $this->conn->insert_id;
    }

    /**
     * $details, when present, comes from Stripe's PaymentIntent ->
     * latest_charge -> payment_method_details: card gives brand/last4/exp,
     * us_bank_account gives bank_name/last4. Whichever isn't applicable is
     * simply absent.
     */
    public function markSucceeded(string $paymentIntentId, string $paymentMethodType, array $details): bool
    {
        $bankAccountName = $details['bank_name'] ?? null;
        $bankLast4       = $details['bank_last4'] ?? null;
        $cardHolderName  = $details['card_holder_name'] ?? null;
        $cardLast4       = $details['card_last4'] ?? null;
        $cardExpiry      = $details['card_expiry'] ?? null;

        $stmt = $this->conn->prepare("
            UPDATE invoice_payments
            SET status = 'SUCCEEDED',
                payment_method = ?,
                bank_account_name = ?, bank_last4 = ?,
                card_holder_name = ?, card_last4 = ?, card_expiry = ?,
                failure_message = NULL
            WHERE stripe_payment_intent_id = ? AND status != 'SUCCEEDED'
        ");
        $stmt->bind_param(
            "sssssss",
            $paymentMethodType,
            $bankAccountName,
            $bankLast4,
            $cardHolderName,
            $cardLast4,
            $cardExpiry,
            $paymentIntentId
        );
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }

    public function markFailed(string $paymentIntentId, ?string $failureMessage): void
    {
        $stmt = $this->conn->prepare("
            UPDATE invoice_payments
            SET status = 'FAILED', failure_message = ?
            WHERE stripe_payment_intent_id = ? AND status = 'PENDING'
        ");
        $stmt->bind_param("ss", $failureMessage, $paymentIntentId);
        $stmt->execute();
    }

    /**
     * Recompute payment_status from live totals and persist it on
     * po_invoices. $totalAmount is passed in rather than re-queried since
     * the caller already has the invoice detail loaded.
     */
    public function syncPaymentStatus(int $invoiceId, float $totalAmount): void
    {
        $totalPaid = $this->getTotalPaid($invoiceId);

        if ($totalPaid <= 0) {
            $status = 'UNPAID';
        } elseif ($totalPaid + 0.01 >= $totalAmount) {
            $status = 'PAID';
        } else {
            $status = 'PARTIALLY_PAID';
        }

        $stmt = $this->conn->prepare("UPDATE po_invoices SET payment_status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $invoiceId);
        $stmt->execute();
    }
}
