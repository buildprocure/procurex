<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\DB;
use App\Core\Auth;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Auth::checkBuyer();

if (!isset($_GET['po_id'])) {
    die("PO ID missing");
}
$conn = DB::getConnection();
$poId = (int)$_GET['po_id'];
/* -------------------------------------------------
   Fetch PO + Supplier Info
--------------------------------------------------*/
$stmt = $conn->prepare("
    SELECT po.id, po.rfq_id, c.name as supplier_name, po.total_amount, po.status, po.created_at
    FROM purchase_orders po
    JOIN companies c ON c.id = po.supplier_company_id
    WHERE po.id = ?
");
$stmt->bind_param("i", $poId);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();
if (!$po) {
    die("PO not found");
}

/* -------------------------------------------------
   Invoice, if one has been generated for this PO
--------------------------------------------------*/
$stmt = $conn->prepare("SELECT id, status FROM po_invoices WHERE purchase_order_id = ?");
$stmt->bind_param("i", $poId);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFQ List - ProcureX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php require '../../header.php'; ?>
    <div class="main-content">
        <div class="container-fluid mt-5">
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="display-5">Purchase Order Details</h1>
            </div>
        </div>

        <!-- PO details content here -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">PO #<?= htmlspecialchars($po['id']) ?></h5>
                <p><strong>RFQ ID:</strong> <?= htmlspecialchars($po['rfq_id']) ?></p>
                <p><strong>Supplier:</strong> <?= htmlspecialchars($po['supplier_name']) ?></p>
                <p><strong>Total Amount:</strong> $<?= number_format($po['total_amount'], 2) ?></p>
                <p><strong>Status:</strong> <?= htmlspecialchars($po['status']) ?></p>
                <p><strong>Created Date:</strong> <?= date('d M Y', strtotime($po['created_at'])) ?></p>

                <?php if ($invoice && $invoice['status'] === 'SENT'): ?>
                    <a href="../Invoice/invoice_view.php?invoice_id=<?= (int) $invoice['id'] ?>"
                       class="btn btn-outline-primary btn-sm">View Invoice</a>
                <?php elseif ($invoice): ?>
                    <span class="badge bg-secondary">Invoice generated, not yet sent</span>
                <?php endif; ?>
            </div>
        </div>

    </div>
</body>