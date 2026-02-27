<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\DB as Database;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Auth::checkSupplier();

$poId = (int)($_GET['po_id'] ?? 0);

$conn = Database::getConnection();

// Fetch PO Header
$stmt = $conn->prepare("
    SELECT po.*, c.name as buyer_name, s.name as supplier_name FROM purchase_orders po
    JOIN user u ON u.id = po.created_by
    JOIN companies c ON c.id = u.company_id
    JOIN companies s ON s.id = po.supplier_company_id
    WHERE po.id = ?
");
$stmt->bind_param("i", $poId);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();

if (!$po) {
    die("Purchase Order not found.");
}

// Fetch PO Items
$stmt = $conn->prepare("
    SELECT * FROM purchase_order_items
    WHERE purchase_order_id = ?
");
$stmt->bind_param("i", $poId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
echo "<pre>"; print_r($po); echo "</pre>";
exit;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Purchase Order #<?= $poId ?></title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { width:100%; border-collapse: collapse; margin-top:20px;}
        th, td { border:1px solid #ccc; padding:8px; text-align:left;}
        th { background:#f4f4f4; }
        .total { text-align:right; font-weight:bold; }
    </style>
</head>
<body>

<?php require '../header.php'; ?>
<div class="main-content">
<h2>Purchase Order #<?= $poId ?></h2>

<p>
<strong>Buyer:</strong> <?= ($po['buyer_name']) ?><br>
<strong>Status:</strong> <?= ($po['status']) ?><br>
<strong>Total Amount:</strong> $<?= number_format($po['total_amount'],2) ?><br>
<strong>Created At:</strong> <?= $po['created_at'] ?>
</p>

<table>
    <thead>
        <tr>
            <th>Material</th>
            <th>Specification</th>
            <th>Unit</th>
            <th>Qty</th>
            <th>Rate</th>
            <th>Line Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($items as $item): ?>
        <tr>
            <td><?= ($item['material_name']) ?></td>
            <td><?= ($item['specification']) ?></td>
            <td><?= ($item['unit']) ?></td>
            <td><?= $item['quantity'] ?></td>
            <td>$<?= number_format($item['unit_price'],2) ?></td>
            <td>$<?= number_format($item['line_total'],2) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
</body>
</html>