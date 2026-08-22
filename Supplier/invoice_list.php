<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\DB;

session_start();
Auth::checkSupplier();

$conn = DB::getConnection();
$supplierId = $_SESSION['company_id'];

$stmt = $conn->prepare("
    SELECT pi.id, pi.invoice_number, pi.status, pi.total_amount, pi.created_at,
           po.id as po_id, c.name as buyer_name
    FROM po_invoices pi
    JOIN purchase_orders po ON po.id = pi.purchase_order_id
    JOIN `user` u ON u.id = po.created_by
    JOIN companies c ON c.id = u.company_id
    WHERE po.supplier_company_id = ?
    ORDER BY pi.created_at DESC
");
$stmt->bind_param("i", $supplierId);
$stmt->execute();
$invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>
    <title>My Invoices</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php require '../header.php'; ?>
<div class="main-content">
    <div class="container mt-5">
        <h2>Invoices</h2>

        <?php if (empty($invoices)): ?>
            <div class="alert alert-secondary mt-4">
                No invoices yet. Generate one from an accepted, shipped purchase order.
            </div>
        <?php else: ?>
        <table class="table table-hover mt-4">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>PO ID</th>
                    <th>Buyer Name</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><?= htmlspecialchars($inv['invoice_number']) ?></td>
                    <td><?= (int) $inv['po_id'] ?></td>
                    <td><?= htmlspecialchars($inv['buyer_name']) ?></td>
                    <td>$<?= number_format($inv['total_amount'], 2) ?></td>
                    <td>
                        <span class="badge bg-<?= $inv['status'] === 'SENT' ? 'success' : 'secondary' ?>">
                            <?= htmlspecialchars($inv['status']) ?>
                        </span>
                    </td>
                    <td><?= date('d M Y', strtotime($inv['created_at'])) ?></td>
                    <td>
                        <a href="invoice_view.php?invoice_id=<?= (int) $inv['id'] ?>"
                           class="btn btn-sm btn-primary">
                            View
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
