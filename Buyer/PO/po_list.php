<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use App\Core\DB;
use App\Core\Auth;

Auth::checkBuyer();
$conn = DB::getConnection();
//find all POs for this buyer
// get the buyer's company ID
$buyerCompanyId = $_SESSION['company_id'];
//list the POs where buyer_company_id matches and order by created_at desc, but I have created_by which is user_id, so I need to join with users table to filter by company_id
$stmt = $conn->prepare("
    SELECT po.id, po.rfq_id, c.name as supplier_name, po.total_amount, po.status, po.created_at
    FROM purchase_orders po
    JOIN companies c ON c.id = po.supplier_company_id
    WHERE po.created_by IN (
        SELECT id FROM user WHERE company_id = ?
    )
    ORDER BY po.created_at DESC
");
$stmt->bind_param("i", $buyerCompanyId);
$stmt->execute();
$po_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
                <h1 class="display-5">Purchase Orders</h1>
            </div>
        </div>

        <?php if (empty($po_list)): ?>
            <div class="alert alert-info text-center" role="alert">
                <h5>No Purchase Orders found</h5>
                <p>Start by creating a new RFQ and awarding a supplier.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>PO ID</th>
                            <th>RFQ ID</th>
                            <th>Supplier</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($po_list as $po): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($po['id']); ?></strong></td>
                                <!-- More columns here -->
                                 <td><?php echo htmlspecialchars($po['rfq_id']); ?></td>
                                <td><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                                <td><?php echo number_format($po['total_amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($po['status']); ?></td>
                                <td><?php echo date('d M Y', strtotime($po['created_at'])); ?></td>
                                <td>
                                    <a href="po_view.php?po_id=<?php echo $po['id']; ?>" class="btn btn-sm btn-info">View</a> 
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php require '../../footer.php'; ?>
</body>
</html>