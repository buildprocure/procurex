<?php
// 1️⃣ Bootstrap FIRST
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../_config.php';

use App\Modules\Buyer\BOQUpload;

// 2️⃣ Security check
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: " . SITE_URL . "login.php");
    exit;
}

if (strtolower($_SESSION['role']) !== 'buyer') {
    header("Location: unauthorized.php");
    exit;
}

// 3️⃣ Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        BOQUpload::handle($_POST, $_FILES);
        $_SESSION['success'] = "BOQ uploaded successfully";
        header("Location: boq_list.php");
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>

<!-- 4️⃣ UI -->
<!DOCTYPE html>
<html>
<head>
    <title>Upload BOQ</title>
</head>
<body>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/header.php'; ?>

<div class="main-content">
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
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/footer.php'; ?>