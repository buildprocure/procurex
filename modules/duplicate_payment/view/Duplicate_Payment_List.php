
<?php
include_once BASE_PATH .'_config.php';

if(!isset($_SESSION['loggedin'])|| $_SESSION['loggedin'] != true){
    header("location: " . SITE_URL . "index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL.'custom.css';?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php 
        require BASE_PATH.'_nav_afterLogin.php';
    ?>

    <?php 
        require BASE_PATH.'_vnav.php';
    ?>
    <div class = "main-content" >
    <h2>Duplicate Payments</h2>
    <table border="1" cellpadding="8">
        <tr>
            <th>Invoice</th>
            <th>Amount</th>
            <th>Payment Date</th>
            <th>Count</th>
        </tr>
        <?php foreach ($duplicates as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                <td><?= htmlspecialchars($row['amount']) ?></td>
                <td><?= htmlspecialchars($row['payment_date']) ?></td>
                <td><?= htmlspecialchars($row['count']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <script src = "<?php echo SITE_URL.'vnavdropdown.js';?>"></script>
</body>
</html>