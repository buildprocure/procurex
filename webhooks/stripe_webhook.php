<?php
/**
 * Public Stripe webhook receiver - no session/Auth, Stripe's signature is
 * the only trust boundary here (verified inside PaymentController::
 * handleWebhook() via the STRIPE_WEBHOOK_SECRET configured in the Stripe
 * Dashboard for this exact endpoint URL).
 *
 * This is the reliable path for finalizing a payment: it's what catches
 * ACH debits settling days later, and buyers who close the tab before the
 * confirmFromReturn() redirect in Buyer/Invoice/invoice_view.php completes.
 * Both paths are idempotent on stripe_payment_intent_id, so it's safe for
 * this to also fire for a PaymentIntent that confirmFromReturn() already
 * finished handling.
 *
 * Only reachable once STRIPE_WEBHOOK_SECRET is set and this URL
 * (https://<your-domain>/webhooks/stripe_webhook.php) is registered as an
 * endpoint in the Stripe Dashboard (or via the Stripe CLI for local dev).
 */
require_once __DIR__ . '/../vendor/autoload.php';

use App\Modules\Buyer\Payment\PaymentController;

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $controller = new PaymentController();
    $controller->handleWebhook($payload, $sigHeader);
    http_response_code(200);
    echo 'ok';
} catch (Throwable $e) {
    http_response_code(400);
    echo 'error: ' . $e->getMessage();
}
