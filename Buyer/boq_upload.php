<?php
// if(session_status() == PHP_SESSION_NONE){
//     session_start();
// }
require_once $_SERVER['DOCUMENT_ROOT'] . '/header.php';
require '../vendor/autoload.php';
include_once '../_config.php';
use App\Modules\Buyer\BOQUpload;
// 1️⃣ Security check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] != true) {

    header("Location: " . SITE_URL . "login.php");
    exit;
    if ($_SESSION['role'] !== 'buyer') {
        header("Location: unauthorized.php");
        exit;
    }
}

// 2️⃣ Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   

    try {
        BOQUpload::handle($_POST, $_FILES);
        $_SESSION['success'] = "BOQ uploaded successfully";
        header("Location: boq_list.php");
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!-- 3️⃣ UI -->
<!DOCTYPE html>
<html>
<head>
    <title>Upload BOQ</title>
</head>
<body>
<div class = "main-content" >
    <h2>Upload BOQ</h2>

    <?php if (!empty($error)): ?>
        <p style="color:red"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Project Name</label>
        <input type="text" name="project_name" required>

        <label>BOQ Excel File</label>
        <input type="file" name="boq_file" accept=".xls,.xlsx" required>

        <button type="submit">Upload BOQ</button>
    </form>
</div>

</body>
</html>

