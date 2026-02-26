<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\DB;

session_start();
Auth::checkSupplier();

$conn = DB::getConnection();
$supplierId = $_SESSION['company_id'];

$stmt = $conn->prepare("
    SELECT 
        rig.id as rfq_group_id,
        r.id as rfq_id,
        ig.group_name as group_name,
        r.quote_deadline,
        rgs.status
    FROM rfq_group_suppliers rgs
    JOIN rfq_item_groups rig ON rig.id = rgs.rfq_item_group_id
    JOIN item_groups ig ON ig.id = rig.item_group_id
    JOIN rfqs r ON r.id = rig.rfq_id
    WHERE rgs.supplier_company_id = ?
");
$stmt->bind_param("i", $supplierId);
$stmt->execute();
$rfqs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
        <h2>Assigned RFQs</h2>

        <table class="table table-hover mt-4">
            <thead>
                <tr>
                    <th>RFQ #</th>
                    <th>Group</th>
                    <th>Deadline</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rfqs as $rfq): ?>
                <tr>
                    <td><?= $rfq['rfq_id'] ?></td>
                    <td><?= htmlspecialchars($rfq['group_name']) ?></td>
                    <td><?= date('d M Y', strtotime($rfq['quote_deadline'])) ?></td>
                    <td>
                        <span class="badge bg-<?= $rfq['status']=='QUOTED'?'success':'warning' ?>">
                            <?= $rfq['status'] ?>
                        </span>
                    </td>
                    <td>
                        <a href="rfq_group_view.php?group_id=<?= $rfq['rfq_group_id'] ?>"
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
