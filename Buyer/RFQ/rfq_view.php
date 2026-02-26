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
           r.quote_deadline, r.instructions, r.status, r.created_user_id, r.created_at,
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

//status badge logic
switch ($rfq['status']) {
    case 'CREATED':
        $rfqCreatedBadge = 'Done';
        $rfqCreatedBadgeClass = 'bg-success';
        
        $itemGroupingBadge = 'Pending';
        $itemGroupingBadgeClass = 'badge bg-warning text-dark';

        $supplierAssignmentBadge = 'Not Started';
        $supplierAssignmentBadgeClass = 'badge bg-secondary';

        $rfqSentBadge = 'Not Sent';
        $rfqSentBadgeClass = 'badge bg-secondary';

        $quotesReceivedBadge = 'Not Started';
        $quotesReceivedBadgeClass = 'badge bg-secondary';

        $generalMessage = "RFQ is created successfully. Next step is item grouping and supplier assignment.";
        break;
    
    case 'GROUPED':
        $rfqCreatedBadge = 'Done';
        $rfqCreatedBadgeClass = 'bg-success';

        $itemGroupingBadge = 'Done';
        $itemGroupingBadgeClass = 'badge bg-success';

        $supplierAssignmentBadge = 'Pending';
        $supplierAssignmentBadgeClass = 'badge bg-warning text-dark';

        $rfqSentBadge = 'Not Sent';
        $rfqSentBadgeClass = 'badge bg-secondary';

        $quotesReceivedBadge = 'Not Started';
        $quotesReceivedBadgeClass = 'badge bg-secondary';

        $generalMessage = "Items are grouped. Next step is supplier assignment and RFQ sending.";
        break;

    case 'SUPPLIER_ASSIGNED':
        $rfqCreatedBadge = 'Done';
        $rfqCreatedBadgeClass = 'badge bg-success';

        $itemGroupingBadge = 'Done';
        $itemGroupingBadgeClass = 'badge bg-success';

        $supplierAssignmentBadge = 'Done';
        $supplierAssignmentBadgeClass = 'badge bg-success';

        $rfqSentBadge = 'Pending';
        $rfqSentBadgeClass = 'badge bg-warning text-dark';

        $quotesReceivedBadge = 'Not Started';
        $quotesReceivedBadgeClass = 'badge bg-secondary';

        $generalMessage = "Suppliers are assigned. Next step is sending RFQ to suppliers.";
        break;

    case 'RFQ_SENT':
        $rfqCreatedBadge = 'Done';
        $rfqCreatedBadgeClass = 'bg-success';
        
        $itemGroupingBadge = 'Done';
        $itemGroupingBadgeClass = 'badge bg-success';

        $supplierAssignmentBadge = 'Done';
        $supplierAssignmentBadgeClass = 'badge bg-success';

        $rfqSentBadge = 'Done';
        $rfqSentBadgeClass = 'badge bg-success';

        $quotesReceivedBadge = 'Pending';
        $quotesReceivedBadgeClass = 'badge bg-warning text-dark';

        $generalMessage = "RFQ is sent to suppliers. Waiting for quotes to be received.";
        break;
    
    case 'QUOTES_RECEIVED':
        $rfqCreatedBadge = 'Done';
        $rfqCreatedBadgeClass = 'bg-success';

        $itemGroupingBadge = 'Done';
        $itemGroupingBadgeClass = 'badge bg-success';

        $supplierAssignmentBadge = 'Done';
        $supplierAssignmentBadgeClass = 'badge bg-success';

        $rfqSentBadge = 'Done';
        $rfqSentBadgeClass = 'badge bg-success';

        $quotesReceivedBadge = 'Done';
        $quotesReceivedBadgeClass = 'badge bg-success';

        $generalMessage = "Quotes are received from suppliers. You can review and compare quotes.";
        break;

    default:

        $rfqCreatedBadge = 'Done';
        $rfqCreatedBadgeClass = 'badge bg-success';

        $itemGroupingBadge = 'Pending';
        $itemGroupingBadgeClass = 'badge bg-warning text-dark';

        $supplierAssignmentBadge = 'Not Started';
        $supplierAssignmentBadgeClass = 'badge bg-secondary';

        $rfqSentBadge = 'Not Sent';
        $rfqSentBadgeClass = 'badge bg-secondary';

        $quotesReceivedBadge = 'Not Started';
        $quotesReceivedBadgeClass = 'badge bg-secondary';

        $generalMessage = "RFQ is created successfully. Next step is item grouping and supplier assignment.";
        break;
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

// Fetch details for progress badges
// 1️⃣ Count grouped items
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_items, id as group_id, item_group_id
    FROM rfq_item_groups
    WHERE rfq_id = ? GROUP BY id
");
$stmt->bind_param("i", $rfqId);
$stmt->execute();
$groupedItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$totalItems = count($groupedItems);



// 2️⃣ Get details of assigned suppliers for each group
foreach ($groupedItems as $index => $group) {
    $stmt = $conn->prepare("
        SELECT * FROM rfq_group_suppliers
        WHERE rfq_item_group_id = ?
    ");
    $stmt->bind_param("i", $group['group_id']);
    $stmt->execute();
    $assignedSuppliers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $groupedItems[$index]['assigned_suppliers'] = $assignedSuppliers;
    //get the group code from item_groups table and add to the array for display
    $stmt = $conn->prepare("SELECT group_code FROM item_groups WHERE id = ?");
    $stmt->bind_param("i", $group['item_group_id']);
    $stmt->execute();
    $groupCode = $stmt->get_result()->fetch_assoc()['group_code'];
    $groupedItems[$index]['group_code'] = $groupCode;
}


// 3️⃣ Count sent RFQs for each group
foreach ($groupedItems as $index => $group) {

    $stmt = $conn->prepare("
        SELECT COUNT(*) as sent_count
        FROM rfq_group_suppliers
        WHERE rfq_item_group_id = ?
        AND status IN ('INVITED', 'QUOTED')
    ");
    $stmt->bind_param("i", $group['group_id']);
    $stmt->execute();
    $resultsentCount = $stmt->get_result()->fetch_assoc();  
    $sentCount = $resultsentCount['sent_count'];
    $groupedItems[$index]['sent_count'] = $sentCount;
}

// 4️⃣ Count received quotes
foreach ($groupedItems as $index => $group) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as received_count
        FROM rfq_group_quotes rgq        
        WHERE rgq.rfq_item_group_id = ?
        AND rgq.status = 'SUBMITTED'
    ");
    $stmt->bind_param("i", $group['group_id']);
    $stmt->execute();
    $receivedCount = $stmt->get_result()->fetch_assoc()['received_count'];
    $groupedItems[$index]['received_count'] = $receivedCount;
}


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

                    <!-- RFQ Created -->
                    <li class="list-group-item">
                        <a data-bs-toggle="collapse" href="#createdDetails" 
                        class="d-flex justify-content-between text-decoration-none">
                            <span>RFQ Created</span>
                            <span class="badge bg-success">Done</span>
                        </a>
                        <div class="collapse mt-2" id="createdDetails">
                            <div class="text-muted small">
                                Created on <?= date('d M Y, H:i', strtotime($rfq['created_at'])) ?>
                            </div>
                        </div>
                    </li>


                    <!-- Item Grouping -->
                    <li class="list-group-item">
                        <a data-bs-toggle="collapse" href="#groupDetails"
                        class="d-flex justify-content-between text-decoration-none">
                            <span>Item Grouping</span>
                            <span class="<?= $itemGroupingBadgeClass ?>">
                                <?= $totalItems ?> Items
                            </span>
                        </a>
                        <div class="collapse mt-2" id="groupDetails">
                            <div class="text-muted small">
                                <?php if ($totalItems > 0){ 
                                    foreach ($groupedItems as $group) {
                                        echo "Group " . $group['group_id'] . "-" . $group['group_code'] . ": " . count($group['assigned_suppliers']) . " suppliers assigned<br>";
                                    }
                                } else {
                                    echo "Item grouping is pending.";
                                } ?>
                            </div>
                        </div>
                    </li>


                    <!-- Suppliers Assigned -->
                    <li class="list-group-item">
                        <a data-bs-toggle="collapse" href="#supplierDetails"
                        class="d-flex justify-content-between text-decoration-none">
                            <span>Suppliers Assigned</span>
                            <span class="<?= $supplierAssignmentBadgeClass ?>">
                                <?php 
                                $assignedSuppliers = 0;
                                foreach ($groupedItems as $group) {
                                    $assignedSuppliers += count($group['assigned_suppliers']);
                                }
                                echo $assignedSuppliers . " Suppliers";
                                ?>                            
                        </a>
                        <div class="collapse mt-2" id="supplierDetails">
                            <div class="text-muted small">
                                <?php foreach ($groupedItems as $group) {
                                $assignedCount = count($group['assigned_suppliers']);
                                echo "<span class='" . ($assignedCount > 0 ? 'badge bg-success' : 'badge bg-secondary') . "'>";
                                echo "Group " . $group['group_id'] . "-" . $group['group_code'] . ": " . $assignedCount . " suppliers";
                                echo "</span><br>";
                                } ?>
                            </div>
                        </div>
                    </li>


                    <!-- RFQ Sent -->
                    <li class="list-group-item">
                        <a data-bs-toggle="collapse" href="#sentDetails"
                        class="d-flex justify-content-between text-decoration-none">
                            <span>RFQ Sent to Suppliers</span>
                            <span class="<?= $rfqSentBadgeClass ?>">
                                <?php 
                                $totalSent = array_sum(array_column($groupedItems, 'sent_count'));
                                echo $totalSent . " / " . $assignedSuppliers;
                                ?>
                            </span>
                        </a>
                        <div class="collapse mt-2" id="sentDetails">
                            <div class="text-muted small">
                                <?php foreach ($groupedItems as $group) {
                                    $sentCount = $group['sent_count'];
                                    echo "Group " . $group['group_id'] . "-" . $group['group_code'] . ": " . $sentCount . " / " . count($group['assigned_suppliers']) . " sent<br>";
                                } ?>
                            </div>
                        </div>
                    </li>


                    <!-- Quotes Received -->
                    <li class="list-group-item">
                        <a data-bs-toggle="collapse" href="#quoteDetails"
                        class="d-flex justify-content-between text-decoration-none">
                            <span>Quotes Received</span>
                            <span class="<?= $quotesReceivedBadgeClass ?>">
                                <?php 
                                $totalReceived = array_sum(array_column($groupedItems, 'received_count'));
                                echo $totalReceived . " / " . $assignedSuppliers;
                                ?>
                            </span>
                        </a>
                        <div class="collapse mt-2" id="quoteDetails">
                            <div class="text-muted small">
                                <?php foreach ($groupedItems as $group) {
                                    $receivedCount = $group['received_count'];  
                                    echo "Group " . $group['group_id'] . "-" . $group['group_code'] . ": " . $receivedCount . " / " . count($group['assigned_suppliers']) . " received<br>";
                                } ?>
                            </div>
                        </div>
                    </li>

                </ul>
                
                <?php $allQuotesReceived = false;
$deadlinePassed = false;

$totalAssigned = $assignedSuppliers;
$totalReceived = array_sum(array_column($groupedItems, 'received_count'));

if ($totalAssigned > 0 && $totalReceived >= $totalAssigned) {
    $allQuotesReceived = true;
}

if (strtotime($rfq['quote_deadline']) < time()) {
    $deadlinePassed = true;
} 
//Decide if comparison is ready 
$readyForComparison = false;

if ($allQuotesReceived || $deadlinePassed) {
    if ($totalReceived > 0) {
        $readyForComparison = true;
    }
}?>
<?php if (!$readyForComparison): ?>
    <button class="btn btn-secondary w-100 mt-3" disabled>
        Waiting for supplier quotes...
    </button>
<?php else: ?>
    <a href="rfq_comparison.php?rfq_id=<?= $rfqId ?>"
       class="btn btn-success w-100 mt-3">
       Proceed to Quote Comparison
    </a>
<?php endif; ?>



            </div>
            
        </div>
    </div>
</div>

<?php require '../../footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>