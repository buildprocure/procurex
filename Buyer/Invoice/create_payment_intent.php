<?php
/**
 * AJAX/JSON endpoint the invoice page calls to start a Stripe payment.
 * Returns a client_secret (and the publishable key) so the browser can
 * mount Stripe's Payment Element and confirm payment itself - the actual
 * card/bank details never pass through this server at all.
 */
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Modules\Buyer\Payment\PaymentController;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

$invoiceId = (int) ($input['invoice_id'] ?? 0);
$amount    = (float) ($input['amount'] ?? 0);
$notes     = isset($input['notes']) ? trim((string) $input['notes']) : null;
$notes     = ($notes === '') ? null : $notes;

try {
    if ($invoiceId <= 0) {
        throw new Exception('invoice_id is required.');
    }

    $controller = new PaymentController();
    $result = $controller->createIntent($invoiceId, $amount, $notes);

    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
