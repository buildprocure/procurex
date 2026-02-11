<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use App\Modules\Buyer\RFQ\RFQController;

//use DB.php to connect to database

$rfqController = new RFQController();
$rfqs = $rfqController->getRFQsByBuyer($_SESSION['user_id']);
// $buyer_id = $_SESSION['user_id'];
// $query = "SELECT * FROM rfqs WHERE created_user_id = ? ORDER BY created_at DESC";
// $stmt = $conn->prepare($query);
// $stmt->bind_param('i', $buyer_id);
// $stmt->execute();
// $result = $stmt->get_result();
// $rfqs = $result->fetch_all(MYSQLI_ASSOC);
// $stmt->close();
// $conn->close();
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
                <h1 class="display-5">Request for Quotations</h1>
            </div>
        </div>

        <?php if (empty($rfqs)): ?>
            <div class="alert alert-info text-center" role="alert">
                <h5>No RFQs found</h5>
                <p>Start by creating a new RFQ.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>RFQ ID</th>
                            <th>Project ID</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Closing Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rfqs as $rfq): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($rfq['id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($rfq['project_id']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $rfq['process_stage'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($rfq['process_stage'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($rfq['created_at'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($rfq['quote_deadline'])); ?></td>
                                <td>
                                    <a href="rfq_view.php?rfq_id=<?php echo $rfq['id']; ?>" class="btn btn-sm btn-info">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>