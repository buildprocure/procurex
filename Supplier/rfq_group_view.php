<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\DB;

session_start();
Auth::checkSupplier();

if (!isset($_GET['group_id'])) {
    die("Group ID missing");
}

$conn = DB::getConnection();
$groupId = (int)$_GET['group_id'];
$supplierId = $_SESSION['company_id'];

/* -------------------------------------------------
   Verify Supplier Assignment + Fetch RFQ Info
--------------------------------------------------*/
$stmt = $conn->prepare("
    SELECT rgs.status,
           r.id as rfq_id,
           ig.group_name,
           r.quote_deadline
    FROM rfq_group_suppliers rgs
    JOIN rfq_item_groups rig ON rig.id = rgs.rfq_item_group_id
    JOIN rfqs r ON r.id = rig.rfq_id
    JOIN item_groups ig ON ig.id = rig.item_group_id
    WHERE rgs.rfq_item_group_id = ?
    AND rgs.supplier_company_id = ?
");
$stmt->bind_param("ii", $groupId, $supplierId);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();

if (!$assignment) {
    die("Unauthorized access");
}

$isQuoted = $assignment['status'] === 'QUOTED';
$isExpired = strtotime($assignment['quote_deadline']) < time();

/* -------------------------------------------------
   Fetch Group Items
--------------------------------------------------*/
$stmt = $conn->prepare("
    SELECT ri.id,
           ri.material_name,
           ri.specification,
           ri.unit,
           ri.quantity
    FROM rfq_items ri
    WHERE ri.rfq_item_group_id = ?
");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* -------------------------------------------------
   Fetch Existing Quote (if already submitted)
--------------------------------------------------*/
$existingPrices = [];

if ($isQuoted) {
    $stmt = $conn->prepare("
        SELECT rgqi.rfq_item_id,
               rgqi.unit_price,
               rgqi.total_price
        FROM rfq_group_quotes rgq
        JOIN rfq_group_quote_items rgqi
             ON rgqi.rfq_group_quote_id = rgq.id
        WHERE rgq.rfq_item_group_id = ?
        AND rgq.supplier_company_id = ?
    ");
    $stmt->bind_param("ii", $groupId, $supplierId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($result as $row) {
        $existingPrices[$row['rfq_item_id']] = $row;
    }
}

/* -------------------------------------------------
   Handle Quote Submission
--------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isQuoted && !$isExpired) {

    $conn->begin_transaction();

    try {

        // 1️⃣ Insert Header
        $insertHeader = $conn->prepare("
            INSERT INTO rfq_group_quotes
            (rfq_item_group_id, supplier_company_id, status)
            VALUES (?, ?, 'SUBMITTED')
        ");
        $insertHeader->bind_param("ii", $groupId, $supplierId);
        $insertHeader->execute();

        $quoteId = $conn->insert_id;
        $totalAmount = 0;

        // 2️⃣ Insert Item Level Prices
        foreach ($_POST['price'] as $itemId => $unitPrice) {

            $unitPrice = (float)$unitPrice;

            // Get quantity from DB (never trust POST)
            $qtyStmt = $conn->prepare("
                SELECT quantity
                FROM rfq_items
                WHERE id = ?
            ");
            $qtyStmt->bind_param("i", $itemId);
            $qtyStmt->execute();
            $qty = $qtyStmt->get_result()->fetch_assoc()['quantity'];

            $lineTotal = $qty * $unitPrice;
            $totalAmount += $lineTotal;

            $insertItem = $conn->prepare("
                INSERT INTO rfq_group_quote_items
                (rfq_group_quote_id, rfq_item_id, unit_price, total_price)
                VALUES (?, ?, ?, ?)
            ");
            $insertItem->bind_param("iidd",
                $quoteId,
                $itemId,
                $unitPrice,
                $lineTotal
            );
            $insertItem->execute();
        }

        // 3️⃣ Update Header Total
        $updateHeader = $conn->prepare("
            UPDATE rfq_group_quotes
            SET total_amount = ?, submitted_at = NOW()
            WHERE id = ?
        ");
        $updateHeader->bind_param("di", $totalAmount, $quoteId);
        $updateHeader->execute();

        // 4️⃣ Update Supplier Status
        $updateStatus = $conn->prepare("
            UPDATE rfq_group_suppliers
            SET status = 'QUOTED', responded_at = NOW()
            WHERE rfq_item_group_id = ?
            AND supplier_company_id = ?
        ");
        $updateStatus->bind_param("ii", $groupId, $supplierId);
        $updateStatus->execute();

        $conn->commit();

        // Check if all suppliers for this group have quoted
        $checkGroup = $conn->prepare("
            SELECT COUNT(*) as pending
            FROM rfq_group_suppliers
            WHERE rfq_item_group_id = ?
            AND status != 'QUOTED'
        ");
        $checkGroup->bind_param("i", $groupId);
        $checkGroup->execute();
        $pending = $checkGroup->get_result()->fetch_assoc()['pending'];

        if ($pending == 0) {

            // All suppliers have quoted → mark group completed
            $updateGroup = $conn->prepare("
                UPDATE rfq_item_groups
                SET status = 'QUOTED'
                WHERE id = ?
            ");
            $updateGroup->bind_param("i", $groupId);
            $updateGroup->execute();
        }
        //For rfqs table update
        // Get RFQ id from this group
        $getRfq = $conn->prepare("
            SELECT rfq_id
            FROM rfq_item_groups
            WHERE id = ?
        ");
        $getRfq->bind_param("i", $groupId);
        $getRfq->execute();
        $rfqId = $getRfq->get_result()->fetch_assoc()['rfq_id'];

        // Check if any group still not completed
        $checkRfq = $conn->prepare("
            SELECT COUNT(*) as pending
            FROM rfq_item_groups
            WHERE rfq_id = ?
            AND status != 'QUOTED'
        ");
        $checkRfq->bind_param("i", $rfqId);
        $checkRfq->execute();
        $pendingGroups = $checkRfq->get_result()->fetch_assoc()['pending'];

        if ($pendingGroups == 0) {

            $updateRfq = $conn->prepare("
                UPDATE rfqs
                SET status = 'COMPARISON_READY'
                WHERE id = ?
            ");
            $updateRfq->bind_param("i", $rfqId);
            $updateRfq->execute();
        }
        header("Location: rfq_group_view.php?group_id=".$groupId);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        die("Error submitting quote.");
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Submit Quote</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php require '../header.php'; ?>

<div class="main-content">
<div class="container mt-5">

<h3>
RFQ #<?= $assignment['rfq_id'] ?> —
<?= htmlspecialchars($assignment['group_name']) ?>
</h3>

<p>
<strong>Deadline:</strong>
<?= date('d M Y', strtotime($assignment['quote_deadline'])) ?>
</p>

<?php if ($isExpired): ?>
<div class="alert alert-danger">Quote deadline has passed.</div>
<?php endif; ?>

<?php if ($isQuoted): ?>
<div class="alert alert-success">You have already submitted this quote.</div>
<?php endif; ?>

<form method="POST">

<table class="table table-bordered mt-4">
<thead>
<tr>
<th>Material</th>
<th>Qty</th>
<th>Unit</th>
<th>Unit Price</th>
<th>Total</th>
</tr>
</thead>
<tbody>

<?php foreach ($items as $item): ?>
<tr>
<td>
<strong><?= htmlspecialchars($item['material_name']) ?></strong><br>
<small><?= htmlspecialchars($item['specification']) ?></small>
</td>
<td><?= $item['quantity'] ?></td>
<td><?= $item['unit'] ?></td>
<td>
<?php if (!$isQuoted && !$isExpired): ?>
<input type="number"
step="0.01"
name="price[<?= $item['id'] ?>]"
class="form-control price-input"
required
data-quantity="<?= $item['quantity'] ?>"
data-item-id="<?= $item['id'] ?>">
<?php else: ?>
<?= isset($existingPrices[$item['id']])
    ? number_format($existingPrices[$item['id']]['unit_price'], 2)
    : '-' ?>
<?php endif; ?>
</td>
<td id="total-<?= $item['id'] ?>">
<?= isset($existingPrices[$item['id']])
    ? number_format($existingPrices[$item['id']]['total_price'], 2)
    : '0.00' ?>
</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>

<?php if (!$isQuoted && !$isExpired): ?>
<button type="submit" class="btn btn-success">
Submit Quote
</button>
<?php endif; ?>

<a href="rfq_list.php" class="btn btn-secondary">Back</a>

</form>
</div>
</div>

<script>
document.querySelectorAll('.price-input').forEach(input => {
input.addEventListener('input', function() {
const quantity = parseFloat(this.dataset.quantity) || 0;
const price = parseFloat(this.value) || 0;
const total = quantity * price;
document.getElementById('total-' + this.dataset.itemId)
.textContent = total.toFixed(2);
});
});
</script>

</body>
</html>
