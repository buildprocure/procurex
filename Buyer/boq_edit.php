<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/../_dbconnect.php';

use App\Core\Auth;
use App\Modules\Buyer\BOQ\BOQController;


// 2️⃣ Security check
Auth::checkBuyer();

$boqId = (int)($_GET['boq_id'] ?? 0);
if ($boqId <= 0) {
    die("Invalid BOQ ID");
}else {
    $controller = new BOQController();
    //check boq belogs to company
    $authorityCheck = $controller->checkownership($boqId, $_SESSION['username']);
    if (!$authorityCheck) {
        die("Unauthorized access to BOQ");
    }

    $getBOQListByID = $controller->getBOQListByID($boqId);
    if (empty($getBOQListByID)) {
        die("BOQ not found or not editable");
    }else {
        if ($getBOQListByID['status'] !== 'DRAFT') {
            die("This BOQ is LOCKED and cannot be edited.");
        }
        //fetch boq items for boq id
        $items = $controller->getBOQItemsbyBOQId($boqId);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
    $controller = new BOQController();
    $newBoqId= $controller->saveEditedBOQ($boqId, $_POST['items'], $_SESSION['username']);
        } catch (Throwable $e) {
            die("Error saving BOQ: " . $e->getMessage()." in ".$e->getFile()." at line ".$e->getLine());
        }
    header("Location: boq_list.php?display=edited &boq_id=" . $newBoqId);
    exit;
    // mysqli_begin_transaction($conn);

    // try {
    //     // 1️⃣ Deactivate old BOQ
    //     $deactivate = $conn->prepare("
    //         UPDATE boqs SET is_active = 0 WHERE id = ?
    //     ");
    //     $deactivate->bind_param("i", $boqId);
    //     $deactivate->execute();

    //     // 2️⃣ Create new BOQ version
    //     $newVersion = $boq['version_no'] + 1;
    //     $parentId = $boq['parent_boq_id'] ?? $boq['id'];

    //     $insertBoq = $conn->prepare("
    //         INSERT INTO boq 
    //         (project_id, uploaded_file, status, created_by, parent_boq_id, version_no, is_active)
    //         VALUES (?, ?, 'DRAFT', ?, ?, ?, 1)
    //     ");

    //     $insertBoq->bind_param(
    //         "issii",
    //         $boq['project_id'],
    //         $boq['uploaded_file'],
    //         $boq['created_by'],
    //         $parentId,
    //         $newVersion
    //     );
    //     $insertBoq->execute();
    //     $newBoqId = $insertBoq->insert_id;

    //     // 3️⃣ Insert edited items
    //     foreach ($_POST['items'] as $row) {
    //         $stmt = $conn->prepare("
    //             INSERT INTO boq_items
    //             (boq_id, item_code, material_name, specification, unit, quantity, preferred_brand, remarks)
    //             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    //         ");

    //         $stmt->bind_param(
    //             "isssssds",
    //             $newBoqId,
    //             $row['item_code'],
    //             $row['material_name'],
    //             $row['specification'],
    //             $row['unit'],
    //             $row['quantity'],
    //             $row['preferred_brand'],
    //             $row['remarks']
    //         );

    //         $stmt->execute();
    //     }

    //     mysqli_commit($conn);

    //     header("Location: boq_list.php");
    //     exit;

    // } catch (Exception $e) {
    //     mysqli_rollback($conn);
    //     die("Error saving BOQ: " . $e->getMessage());
    // }
}



?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit BOQ</title>
</head>
<body>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/header.php'; ?>

<div class="main-content">
<h2>Edit BOQ (Version <?= $getBOQListByID['version_no'] ?>)</h2>

<form method="POST">

<table border="1" width="100%">
<tr>
    <th>Item Code</th>
    <th>Material</th>
    <th>Specification</th>
    <th>Unit</th>
    <th>Qty</th>
    <th>Brand</th>
    <th>Remarks</th>
</tr>

<?php foreach ($items as $i => $item): ?>
<tr>
    <td><input name="items[<?= $i ?>][item_code]" value="<?= htmlspecialchars($item['item_code']) ?>"></td>
    <td><input name="items[<?= $i ?>][material_name]" value="<?= htmlspecialchars($item['material_name']) ?>"></td>
    <td><input name="items[<?= $i ?>][specification]" value="<?= htmlspecialchars($item['specification']) ?>"></td>
    <td><input name="items[<?= $i ?>][unit]" value="<?= htmlspecialchars($item['unit']) ?>"></td>
    <td><input name="items[<?= $i ?>][quantity]" type="number" step="0.01" value="<?= $item['quantity'] ?>"></td>
    <td><input name="items[<?= $i ?>][preferred_brand]" value="<?= htmlspecialchars($item['preferred_brand']) ?>"></td>
    <td><input name="items[<?= $i ?>][remarks]" value="<?= htmlspecialchars($item['remarks']) ?>"></td>
</tr>
<?php endforeach; ?>

</table>

<br>
<button type="submit">Save New Version</button>
</form>
</div>

</body>
</html>
