<?php
namespace App\Modules\Buyer\Payment;

use App\Core\Auth;
use App\Core\Config;
use App\Modules\Supplier\Invoice\InvoiceModel;
use Exception;
use Stripe\StripeClient;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class PaymentController
{
    private PaymentModel $model;
    private InvoiceModel $invoiceModel;

    public function __construct()
    {
        $this->model = new PaymentModel();
        $this->invoiceModel = new InvoiceModel();
    }

    private function stripeClient(): StripeClient
    {
        return new StripeClient(Config::require('STRIPE_SECRET_KEY'));
    }

    /**
     * Loads the invoice detail (PO + supplier + buyer context) and enforces
     * that the logged-in buyer's company actually owns it - reuses
     * InvoiceModel::getInvoiceDetail() rather than re-deriving PO context.
     */
    private function loadAuthorizedInvoice(int $invoiceId): array
    {
        $detail = $this->invoiceModel->getInvoiceDetail($invoiceId);
        if (!$detail) {
            throw new Exception('Invoice not found.');
        }

        $companyId = (int) ($_SESSION['company_id'] ?? 0);
        if ($companyId <= 0 || (int) $detail['po']['buyer_company_id'] !== $companyId) {
            throw new Exception('You do not have permission to manage payments for this invoice.');
        }

        return $detail;
    }

    /**
     * Create a Stripe PaymentIntent for (up to) the remaining balance and
     * record a PENDING row for it. Returns what the frontend needs to
     * mount Stripe's Payment Element and confirm payment client-side.
     * automatic_payment_methods lets Stripe itself decide which methods
     * to offer (card, US bank account/ACH, etc.) based on the Stripe
     * account's configuration - we don't hardcode the choice here.
     */
    public function createIntent(int $invoiceId, float $amount, ?string $notes): array
    {
        Auth::checkBuyer();

        $detail = $this->loadAuthorizedInvoice($invoiceId);
        $totalAmount = (float) $detail['invoice']['total_amount'];

        if ($amount <= 0) {
            throw new Exception('Payment amount must be greater than zero.');
        }

        $totalPaid = $this->model->getTotalPaid($invoiceId);
        $remaining = $totalAmount - $totalPaid;

        if ($remaining <= 0) {
            throw new Exception('This invoice is already fully paid.');
        }
        if ($amount > $remaining + 0.01) {
            throw new Exception('Payment amount exceeds the remaining balance of $' . number_format($remaining, 2) . '.');
        }

        $stripe = $this->stripeClient();
        $intent = $stripe->paymentIntents->create([
            'amount' => (int) round($amount * 100),
            'currency' => 'usd',
            'automatic_payment_methods' => ['enabled' => true],
            'description' => 'Invoice ' . $detail['invoice']['invoice_number'] . ' - PO #' . (int) $detail['po']['po_id'],
            'metadata' => [
                'invoice_id'       => (string) $invoiceId,
                'invoice_number'   => $detail['invoice']['invoice_number'],
                'po_id'            => (string) $detail['po']['po_id'],
                'buyer_company_id' => (string) $detail['po']['buyer_company_id'],
            ],
        ]);

        $this->model->createPendingPayment(
            $invoiceId,
            $amount,
            $intent->id,
            $notes,
            (int) ($_SESSION['user_id'] ?? 0)
        );

        return [
            'client_secret'   => $intent->client_secret,
            'publishable_key' => Config::require('STRIPE_PUBLISHABLE_KEY'),
        ];
    }

    /**
     * Called when the buyer's browser is redirected back from Stripe after
     * confirmPayment(). Retrieves the PaymentIntent's authoritative status
     * from Stripe (never trust query-string status alone) and applies it.
     * Safe to call even if the webhook already processed this same
     * PaymentIntent - both paths are idempotent on stripe_payment_intent_id.
     */
    public function confirmFromReturn(string $paymentIntentId): array
    {
        Auth::checkBuyer();

        $existing = $this->model->findByPaymentIntentId($paymentIntentId);
        if (!$existing) {
            throw new Exception('Payment not found.');
        }

        $this->loadAuthorizedInvoice((int) $existing['invoice_id']);

        if ($existing['status'] === 'SUCCEEDED') {
            return ['status' => 'SUCCEEDED'];
        }

        $stripe = $this->stripeClient();
        $intent = $stripe->paymentIntents->retrieve($paymentIntentId, [
            'expand' => ['latest_charge.payment_method_details'],
        ]);

        if ($intent->status === 'succeeded') {
            $this->applySuccess($intent, (int) $existing['invoice_id']);
            return ['status' => 'SUCCEEDED'];
        }

        if ($intent->status === 'processing') {
            // ACH bank debits commonly sit here for a few business days
            // before settling - leave the row PENDING; the webhook flips
            // it to SUCCEEDED/FAILED once Stripe knows the outcome.
            return ['status' => 'PROCESSING'];
        }

        if (in_array($intent->status, ['requires_payment_method', 'canceled'], true)) {
            $message = $intent->last_payment_error->message ?? 'Payment was not completed.';
            $this->model->markFailed($paymentIntentId, $message);
            return ['status' => 'FAILED', 'message' => $message];
        }

        return ['status' => $intent->status];
    }

    /**
     * Verifies and handles a Stripe webhook event. This is the reliable
     * path for finalizing a payment - confirmFromReturn() covers the
     * common case where the buyer's browser makes it back to our page,
     * but ACH settlement and abandoned redirects rely on this instead.
     */
    public function handleWebhook(string $payload, string $sigHeader): void
    {
        $webhookSecret = Config::require('STRIPE_WEBHOOK_SECRET');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (UnexpectedValueException | SignatureVerificationException $e) {
            throw new Exception('Invalid Stripe webhook payload or signature.');
        }

        if ($event->type === 'payment_intent.succeeded') {
            $intentId = $event->data->object->id;
            $existing = $this->model->findByPaymentIntentId($intentId);
            if (!$existing) {
                return;
            }

            $stripe = $this->stripeClient();
            $full = $stripe->paymentIntents->retrieve($intentId, [
                'expand' => ['latest_charge.payment_method_details'],
            ]);
            $this->applySuccess($full, (int) $existing['invoice_id']);
        } elseif ($event->type === 'payment_intent.payment_failed') {
            $intent = $event->data->object;
            $message = $intent->last_payment_error->message ?? 'Payment failed.';
            $this->model->markFailed($intent->id, $message);
        }
    }

    /**
     * Shared by confirmFromReturn() and handleWebhook() - pulls the
     * payment-method details Stripe reports back (card brand/last4/exp,
     * or bank name/last4) purely for display, and keeps po_invoices
     * .payment_status in sync once the row actually flips to SUCCEEDED.
     */
    private function applySuccess($intent, int $invoiceId): void
    {
        $charge = $intent->latest_charge ?? null;
        $pmDetails = is_object($charge) ? ($charge->payment_method_details ?? null) : null;
        $type = $pmDetails->type ?? 'card';

        $details = [];
        if ($type === 'card' && isset($pmDetails->card)) {
            $details['card_holder_name'] = $charge->billing_details->name ?? null;
            $details['card_last4']       = $pmDetails->card->last4 ?? null;
            if (!empty($pmDetails->card->exp_month) && !empty($pmDetails->card->exp_year)) {
                $details['card_expiry'] = sprintf('%02d/%04d', $pmDetails->card->exp_month, $pmDetails->card->exp_year);
            }
        } elseif ($type === 'us_bank_account' && isset($pmDetails->us_bank_account)) {
            $details['bank_name']  = $pmDetails->us_bank_account->bank_name ?? 'Bank Account';
            $details['bank_last4'] = $pmDetails->us_bank_account->last4 ?? null;
        }

        $methodEnum = $type === 'us_bank_account' ? 'BANK_TRANSFER' : 'CARD';
        $wasNewlyMarked = $this->model->markSucceeded($intent->id, $methodEnum, $details);

        if ($wasNewlyMarked) {
            $detail = $this->invoiceModel->getInvoiceDetail($invoiceId);
            if ($detail) {
                $this->model->syncPaymentStatus($invoiceId, (float) $detail['invoice']['total_amount']);
            }
        }
    }

    public function getPayments(int $invoiceId): array
    {
        return $this->model->getPayments($invoiceId);
    }

    public function getTotalPaid(int $invoiceId): float
    {
        return $this->model->getTotalPaid($invoiceId);
    }
}
