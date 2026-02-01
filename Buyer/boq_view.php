<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Modules\Buyer\BOQ\BOQController;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: ../index.php");
    exit;
}

if (!isset($_GET['boq_id'])) {
    die("BOQ ID missing");
}

$boqId = (int) $_GET['boq_id'];

$controller = new BOQController();
$boq = $controller->getBOQListByID($boqId);
$items = $controller->viewBOQItems($boqId);

if (empty($items)) {
    die("No items found for this BOQ.");
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BOQ Items</title>
    <link rel="stylesheet" href="//cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <style>
        .container { padding: 20px; }
        h2 { margin-bottom: 15px; }
    </style>
</head>
<body>

<?php require '../header.php'; ?>

<div class="main-content container">
    <h2>BOQ Items</h2>
    <?php if ($boq['status'] === 'DRAFT'): ?>
    <form method="POST" action="boq_publish.php" onsubmit="return confirm('Publish BOQ? This action cannot be undone.')">
        <input type="hidden" name="boq_id" value="<?= $boqId ?>">
        <button class="btn btn-success">
            🚀 Publish BOQ
        </button>
        <br><br>
    </form>
    <?php else: ?>
    <span class="badge badge-success">Published</span>
    <?php endif; ?>


    <table id="boqTable" class="display">
        <thead>
            <tr>
                <th>Item Code</th>
                <th>Material</th>
                <th>Specification</th>
                <th>Unit</th>
                <th>Quantity</th>
                <th>Preferred Brand</th>
                <th>Remarks</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['item_code']) ?></td>
                <td><?= htmlspecialchars($row['material_name']) ?></td>
                <td><?= nl2br(htmlspecialchars($row['specification'])) ?></td>
                <td><?= htmlspecialchars($row['unit']) ?></td>
                <td><?= htmlspecialchars($row['quantity']) ?></td>
                <td><?= htmlspecialchars($row['preferred_brand']) ?></td>
                <td><?= nl2br(htmlspecialchars($row['remarks'])) ?></td>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <br>
    <a href="boq_list.php">← Back to BOQ List</a>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="//cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script>
    $(document).ready(function () {
        $('#boqTable').DataTable({
            pageLength: 25,
            ordering: true
        });
    });
</script>

</body>
</html>
