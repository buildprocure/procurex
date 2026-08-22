<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Modules\Buyer\RFQ\RFQController;
use App\Core\Auth;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

Auth::checkBuyer();

header('Content-Type: application/json');

/**
 * Expects JSON body:
 *   {
 *     "rfq_id": 14,
 *     "awards": [
 *       { "rfq_item_id": 5, "quote_id": 12, "quantity": 600 },
 *       { "rfq_item_id": 5, "quote_id": 15, "quantity": 400 }
 *     ]
 *   }
 *
 * All awards in one request are applied in a single transaction. Typical
 * usage from the comparison page is one item's worth of awards per
 * request (its split across however many suppliers the buyer chose),
 * but nothing here requires that - any set of (item, quote, quantity)
 * triples belonging to the same RFQ is valid.
 */
$raw     = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body.']);
    exit;
}

$rfqId  = (int) ($payload['rfq_id'] ?? 0);
$awards = $payload['awards'] ?? [];

if ($rfqId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'rfq_id is required.']);
    exit;
}

if (!is_array($awards) || empty($awards)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'At least one award is required.']);
    exit;
}

// Normalise and bound-check each entry before it reaches the model.
$clean = [];
foreach ($awards as $a) {
    $clean[] = [
        'rfq_item_id' => (int) ($a['rfq_item_id'] ?? 0),
        'quote_id'    => (int) ($a['quote_id'] ?? 0),
        'quantity'    => (float) ($a['quantity'] ?? 0),
    ];
}

try {
    $controller = new RFQController();
    $result = $controller->awardItems($rfqId, $clean, (int) ($_SESSION['user_id'] ?? 0));

    echo json_encode([
        'success' => true,
        'po_ids'  => $result['po_ids'],
        'logs'    => $result['logs'],
    ]);

} catch (Exception $e) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
