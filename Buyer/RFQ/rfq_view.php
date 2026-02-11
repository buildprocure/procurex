<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\DB;
use App\Core\Auth;

session_start();
Auth::checkBuyer();

if (!isset($_GET['rfq_id'])) {
    die("RFQ ID missing");
}

$rfqId = (int)$_GET['rfq_id'];
$username = $_SESSION['username'];
$userId = $_SESSION['user_id'];
$conn = DB::getConnection();

// Fetch RFQ details
$stmt = $conn->prepare("
    SELECT r.id, r.boq_id, r.delivery_location, r.required_delivery_date, 
           r.quote_deadline, r.instructions, r.process_stage, r.created_user_id, r.created_at,
           b.version_no, p.project_name, p.location as project_location
    FROM rfqs r
    JOIN boqs b ON b.id = r.boq_id
    JOIN projects p ON p.id = b.project_id
    WHERE r.id = ?
");
$stmt->bind_param("i", $rfqId);
$stmt->execute();
$rfq = $stmt->get_result()->fetch_assoc();

if (!$rfq) {
    die("RFQ not found");
}

// Verify ownership
if ($rfq['created_user_id'] !== $userId) {
    die("Unauthorized access");
}

// Fetch RFQ items from BOQ
$stmt = $conn->prepare("
    SELECT id, material_name as material, specification, unit, quantity
    FROM rfq_items
    WHERE rfq_id = ?
    ORDER BY id ASC
");
$stmt->bind_param("i", $rfqId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);


?>
<!DOCTYPE html>
<html>
<head>
    <title>View RFQ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f5f7fb; }
        .card { border-radius: 12px; box-shadow: 0 6px 18px rgba(66,77,88,0.08); }
        .badge-status { font-size: 0.9rem; padding: 0.5rem 0.75rem; }
        .table-custom { margin-top: 1.5rem; }
        .table-custom th { background-color: #f8f9fa; font-weight: 600; border-top: 2px solid #dee2e6; }
        .table-custom td { vertical-align: middle; }
        .rfq-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; }
        .rfq-header h2 { margin: 0; font-weight: 700; }
        .rfq-header .meta { font-size: 0.95rem; opacity: 0.95; }
        .info-group { margin-bottom: 1.5rem; }
        .info-label { font-weight: 600; color: #495057; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-size: 1rem; color: #212529; margin-top: 0.25rem; }
        .btn-group-custom { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .quote-card { border-left: 4px solid #667eea; transition: all 0.3s ease; }
        .quote-card:hover { box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15); }
        .status-badge { font-weight: 600; }
    </style>
</head>
<body>

<?php require '../../header.php'; ?>

<div class="main-content container my-5">
    <!-- RFQ Header -->
    <div class="rfq-header">
        <div class="row align-items-center">
            <div class="col">
                <h2><i class="fas fa-file-invoice"></i> RFQ #<?= $rfqId ?></h2>
                <div class="meta mt-2">
                    <p class="mb-1"><strong>Project:</strong> <?= htmlspecialchars($rfq['project_name']) ?></p>
                    <p class="mb-0"><strong>Created on:</strong> <?= date('d M Y, H:i', strtotime($rfq['created_at'])) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- RFQ Details Card -->
            <div class="card p-4 mb-4">
                <h5 class="card-title mb-3"><i class="fas fa-info-circle"></i> RFQ Details</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-group">
                            <div class="info-label">Delivery Location</div>
                            <div class="info-value"><?= htmlspecialchars($rfq['delivery_location']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <div class="info-label">Project Location</div>
                            <div class="info-value"><?= htmlspecialchars($rfq['project_location']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <div class="info-label">Required Delivery Date</div>
                            <div class="info-value"><?= date('d M Y', strtotime($rfq['required_delivery_date'])) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-group">
                            <div class="info-label">Quote Deadline</div>
                            <div class="info-value"><?= date('d M Y', strtotime($rfq['quote_deadline'])) ?></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="info-group">
                            <div class="info-label">Instructions</div>
                            <div class="info-value"><?= htmlspecialchars($rfq['instructions'] ?? 'No instructions provided') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RFQ Items Table -->
            <div class="card p-4 mb-4">
                <h5 class="card-title mb-0"><i class="fas fa-list"></i> Items</h5>
                <table class="table table-hover table-custom">
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Est. Cost</th>
                            <th>Specifications</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($items) > 0): ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($item['material']) ?></strong></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td><?= htmlspecialchars($item['unit']) ?></td>
                                    <td> - </td>
                                    <td><small class="text-muted"><?= htmlspecialchars(substr($item['specification'] ?? '', 0, 40)) ?>...</small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">No items found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="card p-4 mb-4">
                <h6 class="card-title"><i class="fas fa-clock"></i> Timeline</h6>
                <div class="timeline mt-3">
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <span class="badge bg-primary">1</span>
                        </div>
                        <div class="flex-grow-1 ms-2">
                            <small class="text-muted">RFQ Created</small>
                            <p class="mb-0 small"><strong><?= date('d M Y', strtotime($rfq['created_at'])) ?></strong></p>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <div class="flex-shrink-0">
                            <span class="badge bg-warning">2</span>
                        </div>
                        <div class="flex-grow-1 ms-2">
                            <small class="text-muted">Quote Deadline</small>
                            <p class="mb-0 small"><strong><?= date('d M Y', strtotime($rfq['quote_deadline'])) ?></strong></p>
                        </div>
                    </div>
                    <div class="d-flex">
                        <div class="flex-shrink-0">
                            <span class="badge bg-success">3</span>
                        </div>
                        <div class="flex-grow-1 ms-2">
                            <small class="text-muted">Delivery Date</small>
                            <p class="mb-0 small"><strong><?= date('d M Y', strtotime($rfq['required_delivery_date'])) ?></strong></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <h6 class="card-title mb-3">
                    <i class="fas fa-stream"></i> RFQ Progress
                </h6>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>RFQ Created</span>
                        <span class="badge bg-success">Done</span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span>Item Grouping</span>
                        <?php if ($rfq['process_stage'] === 'CREATED'): ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php else: ?>
                            <span class="badge bg-success">Done</span>
                        <?php endif; ?>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span>Suppliers Assigned</span>
                        <span class="badge bg-secondary">Not Started</span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span>RFQ Sent to Suppliers</span>
                        <span class="badge bg-secondary">Not Sent</span>
                    </li>

                    <li class="list-group-item d-flex justify-content-between">
                        <span>Quotes Received</span>
                        <span class="badge bg-secondary">Waiting</span>
                    </li>
                </ul>

                <div class="alert alert-info mt-3 mb-0 small">
                    <i class="fas fa-info-circle"></i>
                    RFQ is created successfully. Next step is item grouping and supplier assignment.
                </div>

            </div>
        </div>
    </div>
</div>

<?php require '../../footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>