<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\DB;

session_start();
Auth::checkSupplier();

$conn = DB::getConnection();

$poId = (int)($_POST['po_id'] ?? 0);
$response = $_POST['response'] ?? '';
$note = $_POST['note'] ?? '';

$supplierId = $_SESSION['company_id'];

if (!in_array($response, ['ACCEPTED','REJECTED'])) {
    die("Invalid response");
}

/* Verify supplier owns PO */
$stmt = $conn->prepare("
SELECT id
FROM purchase_orders
WHERE id = ?
AND supplier_company_id = ?
");
$stmt->bind_param("ii",$poId,$supplierId);
$stmt->execute();

if (!$stmt->get_result()->fetch_assoc()) {
    die("Unauthorized");
}

/* Update PO */

$stmt = $conn->prepare("
UPDATE purchase_orders
SET 
supplier_response = ?,
supplier_response_at = NOW(),
supplier_note = ?
WHERE id = ?
");

$stmt->bind_param("ssi",$response,$note,$poId);
$stmt->execute();

header("Location: po_view.php?po_id=".$poId);
exit;