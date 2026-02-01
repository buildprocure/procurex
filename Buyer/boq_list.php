<?php
// 1️⃣ Bootstrap
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Modules\Buyer\BOQ\BOQController;

// 2️⃣ Security
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: " . SITE_URL . "login.php");
    exit;
}

$boqController = new BOQController();
$boqs = $boqController->getBOQListByUser($_SESSION['username']);

// Display messages
if (isset($_GET['display'])) {

    switch ($_GET['display']) {
        case 'edited':
            $message = "BOQ edited successfully. New BOQ ID: " . (int)($_GET['boq_id'] ?? 0);
            break;
        case 'published':
            $message = "BOQ with ID " . (int)($_GET['boq_id'] ?? 0) . " has been published successfully!!";
            break;
        default:
            $message = "";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>BOQ List</title>
</head>
<body>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/header.php'; ?>

<div class="main-content">
    <h2>My BOQs</h2>
    <?php if (!empty($message)): ?>
        <p style="color:green"><?= $message ?></p>
    <?php endif; ?>
    <table border="1" width="100%">
        <tr>
            <th>BOQ ID</th>
            <th>Project</th>
            <th>Items</th>
            <th>Status</th>
            <th>Created</th>
            <th>Action</th>
        </tr>

        <?php foreach ($boqs as $boq): ?>
            <tr>
                <td>#<?= $boq['id'] ?></td>
                <td><?= htmlspecialchars($boq['project_name']) ?></td>
                <td><?= $boq['total_items'] ?></td>
                <td><?= $boq['status'] ?></td>
                <td><?= date('d M Y', strtotime($boq['created_at'])) ?></td>
                <td>
                    <a href="boq_view.php?boq_id=<?= $boq['id'] ?>">View</a> 
                    <?php if ($boq['status'] === 'DRAFT') { ?>
                    | <a href="boq_edit.php?boq_id=<?= $boq['id'] ?>">Edit</a>
                    | <a href="boq_publish.php" onclick="event.preventDefault(); if(confirm('Publish BOQ? This action cannot be undone.')) { document.getElementById('publish-form-<?= $boq['id'] ?>').submit(); }">Publish</a> |
                    <form id="publish-form-<?= $boq['id'] ?>" method="POST" action="boq_publish.php" style="display:none;">
                        <input type="hidden" name="boq_id" value="<?= $boq['id'] ?>"> 
                    </form>                    
                    <a onclick="deleteboq(<?= $boq['id'] ?>)" href="javascript:void(0)">Delete</a>   
                    <?php }  ?>                   
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

</body>
</html>
<script>
    function deleteboq(boqId) {
        if (confirm('Are you sure you want to delete BOQ #' + boqId + '? This action cannot be undone.')) {
            window.location.href = 'boq_delete.php?boq_id=' + boqId;
        }
    }
</script>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/footer.php'; ?>