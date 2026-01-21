<?php
// 1️⃣ Bootstrap
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../_config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Modules\Buyer\BOQ\BOQRepository;

// 2️⃣ Security
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: " . SITE_URL . "login.php");
    exit;
}

$boqRepo = new BOQRepository();
$boqs = $boqRepo->getByUser($_SESSION['username']);
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
                    <a href="boq_view.php?id=<?= $boq['id'] ?>">View</a> |
                    <a href="boq_edit.php?id=<?= $boq['id'] ?>">Edit</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

</body>
</html>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/footer.php'; ?>