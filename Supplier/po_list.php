<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\DB;

session_start();
Auth::checkSupplier();

$conn = DB::getConnection();
$supplierId = $_SESSION['company_id'];
// Fetch POs assigned to this supplier Also I need to find buyer company name for each PO based on created_by field in purchase_orders table which is user_id of buyer
$stmt = $conn->prepare("
    SELECT po.*, c.name as buyer_name from purchase_orders po 
    JOIN user u ON u.id = po.created_by
    JOIN companies c ON c.id = u.company_id
    WHERE po.supplier_company_id = ?
");
$stmt->bind_param("i", $supplierId);
$stmt->execute();
$pos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>My RFQs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php require '../header.php'; ?>
<div class="main-content">
    <div class="container mt-5">
        <h2>Assigned Purchase Orders</h2>

        <table class="table table-hover mt-4">
            <thead>
                <tr>
                    <th>PO ID</th>
                    <th>RFQ ID</th>
                    <th>Buyer Name</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th> Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pos as $po): ?>
                <tr>
                    <td><?= $po['id'] ?></td>
                    <td><?= htmlspecialchars($po['rfq_id']) ?></td>
                    <td><?= htmlspecialchars(string: $po['buyer_name']) ?></td>
                    <td><?= $po['total_amount'] ?></td>
                    <td>
                        <span class="badge bg-<?= $po['status']=='QUOTED'?'success':'warning' ?>">
                            <?= $po['status'] ?>
                        </span>
                    </td>
                    <td><?= date('d M Y', strtotime($po['created_at'])) ?></td>
                    <td>
                        <a href="po_view.php?po_id=<?= $po['id'] ?>"
                        class="btn btn-sm btn-primary">
                        View
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>
</div>

</body>
</html>
