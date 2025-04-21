<?php
include_once BASE_PATH . '_config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] != true) {
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.min.js"
        integrity="sha512-WW8/jxkELe2CAiE4LvQfwm1rajOS8PHasCCx+knHG0gBHt8EXxS6T6tJRTGuDQVnluuAvMxWF4j8SNFDKceLFg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL . 'custom.css'; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <?php
    require BASE_PATH . '_nav_afterLogin.php';
    ?>
    <?php
    require BASE_PATH . '_vnav.php';
    ?>
    <div class="main-content">
        <h2>Duplicate Payments</h2>
        <ul class="nav nav-tabs" id="paymentTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="duplicates-tab" data-bs-toggle="tab" href="#duplicates"
                    role="tab">Duplicate Payments</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="possible-duplicates-tab" data-bs-toggle="tab" href="#possible-duplicates"
                    role="tab">Possible Duplicates</a>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="duplicates" role="tabpanel">
                <div table-responsive>
                    <table class="table" border="1" cellpadding="8">
                        <tr>
                        <th>SN</th>
                        <th>Invoice</th>
                        <th>Buyer</th>
                        <th>Amount</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Payment Date</th>
                        <th>Payment Due Date</th>
                        </tr>
                        <?php foreach ($duplicates as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['SN']) ?></td>
                                <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                                <td><?= htmlspecialchars($row['buyer_username']) ?></td>
                                <td><?= htmlspecialchars($row['amount']) ?></td>
                                <td><?= htmlspecialchars($row['supplier_username']) ?></td>
                                <td><?= htmlspecialchars($row['status']) ?></td>
                                <td><?= htmlspecialchars($row['payment_date']) ?></td>
                                <td><?= $row['payment_due_date'] ? $row['payment_due_date'] : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="possible-duplicates" role="tabpanel">
                <table class="table" border="1" cellpadding="8">
                    <tr>
                        <th>SN</th>
                        <th>Invoice</th>
                        <th>Buyer</th>
                        <th>Amount</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Payment Date</th>
                        <th>Payment Due Date</th>

                    </tr>
                    <?php
                    if (empty($possibleDuplicates)) {
                        echo '<tr><td colspan="5">No possible duplicates found.</td></tr>';
                    }

                    foreach ($possibleDuplicates as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['invoice_number']) ?></td>
                            <td><?= htmlspecialchars($row['buyer_username']) ?></td>
                            <td><?= htmlspecialchars($row['amount']) ?></td>
                            <td><?= htmlspecialchars($row['supplier_username']) ?></td>
                            <td><?= htmlspecialchars($row['status']) ?></td>
                            <td><?= htmlspecialchars($row['payment_date']) ?></td>
                            <td><?= htmlspecialchars($row['payment_due_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>


    </div>
    <script src="<?php echo SITE_URL . 'vnavdropdown.js'; ?>"></script>
</body>

</html>