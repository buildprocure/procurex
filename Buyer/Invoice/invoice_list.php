<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\Auth;
use App\Core\DB;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Auth::checkBuyer();

$conn = DB::getConnection();
$buyerCompanyId = $_SESSION['company_id'];

// Only invoices that have actually been sent - a DRAFT invoice is an
// internal supplier-side draft the buyer was never notified about.
$stmt = $conn->prepare("
    SELECT pi.id, pi.invoice_number, pi.status, pi.payment_status, pi.total_amount, pi.created_at, pi.sent_at,
           po.id as po_id, s.name as supplier_name
    FROM po_invoices pi
    JOIN purchase_orders po ON po.id = pi.purchase_order_id
    JOIN `user` u ON u.id = po.created_by
    JOIN companies s ON s.id = po.supplier_company_id
    WHERE u.company_id = ?
      AND pi.status = 'SENT'
    ORDER BY pi.sent_at DESC
");
$stmt->bind_param("i", $buyerCompanyId);
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

<?php require '../../header.php'; ?>
<div class="main-content">
    <div class="container mt-5">
        <h2>Invoices</h2>

        <?php if (empty($invoices)): ?>
            <div class="alert alert-secondary mt-4">
                No invoices received yet.
            </div>
        <?php else: ?>
        <table class="table table-hover mt-4">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>PO ID</th>
                    <th>Supplier</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Sent At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><?= htmlspecialchars($inv['invoice_number']) ?></td>
                    <td><?= (int) $inv['po_id'] ?></td>
                    <td><?= htmlspecialchars($inv['supplier_name']) ?></td>
                    <td>$<?= number_format($inv['total_amount'], 2) ?></td>
                    <td><span class="badge bg-success"><?= htmlspecialchars($inv['status']) ?></span></td>
                    <td>
                        <?php
                            $payStatus = $inv['payment_status'] ?? 'UNPAID';
                            $payBadge  = $payStatus === 'PAID' ? 'bg-success' : ($payStatus === 'PARTIALLY_PAID' ? 'bg-warning text-dark' : 'bg-secondary');
                        ?>
                        <span class="badge <?= $payBadge ?>"><?= htmlspecialchars(str_replace('_', ' ', $payStatus)) ?></span>
                    </td>
                    <td><?= $inv['sent_at'] ? date('d M Y', strtotime($inv['sent_at'])) : '-' ?></td>
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
