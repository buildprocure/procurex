<?php
// Database connection
include_once '../_dbconnect.php';

// Fetch pending invoices
$pending_sql = "SELECT p.*, b.username As buyerName, s.username As supplierName FROM payments p 
Left Join buyer_list_table b ON p.buyer_id = b.buyer_id
Left Join supplier_list_table s ON p.supplier_id = s.supplier_id
WHERE status = 'pending' and b.username = '$_SESSION[username]'";
$pending_result = $conn->query($pending_sql);

// Fetch payment history
$history_sql = "SELECT p.*, b.username As buyerName, s.username As supplierName FROM payments p 
Left Join buyer_list_table b ON p.buyer_id = b.buyer_id
Left Join supplier_list_table s ON p.supplier_id = s.supplier_id
WHERE status != 'pending' and b.username = '$_SESSION[username]'";
$history_result = $conn->query($history_sql);

?>

<!DOCTYPE html>
<html lang="en">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

<link rel="stylesheet" href="//cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="../custom.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Payments</title>
    <link rel="stylesheet" href="../custom.css">
    <style>
        

    </style>
</head>
<body>
    <div>
        <?php 
            require '../_nav_afterLogin.php';
            require '../_vnav.php';
        ?>
        <div class = "main-content">
            <h4>Pending Payments</h4>
            <?php if ($pending_result->num_rows > 0) { ?>
                <table class='table table-bordered table-striped' color='white;' id='myTable'>
                    <tr>
                        <th scope='col'>SN</th>
                        <th scope='col'>Payment ID</th>
                        <th scope='col'>Invoice ID</th>
                        <th scope='col'>Supplier Name</th>
                        <th scope='col'>Amount</th>
                        <th scope='col'>Status</th>
                        <th scope='col'>Action</th>
                    </tr>
                    <?php $SN = 1; ?>
                    <?php while ($row = $pending_result->fetch_assoc()) { ?>
                        <tr>
                            <td scope='row'><?php echo $SN++; ?></td>
                            <td ><?php echo $row["id"]; ?></td>
                            <td ><?php echo $row["invoice_number"]; ?></td>
                            <td><?php echo $row["supplierName"]; ?></td>
                            <td><?php echo number_format($row["amount"], 2); ?></td>
                            <td><?php echo $row["status"]; ?></td>
                            <td><button onclick="payInvoice('<?= htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'); ?>')">Pay</button></td>
                        </tr>
                    <?php } ?>
                </table>
            <?php } else { ?>
                <p>No pending payments.</p>
            <?php } ?>

            <h4>Payment History</h4>
            <?php if ($history_result->num_rows > 0) { ?>
                <table class='table table-bordered table-striped' color='white;' id='myTable'>
                    <tr>
                        <th scope='col'>SN</th>
                        <th scope='col'>Payment ID</th>
                        <th scope='col'>Invoice ID</th>
                        <th scope='col'>Supplier Name</th>
                        <th scope='col'>Status</th>
                        <th scope='col'>Amount Paid</th>
                        <th scope='col'>Payment Date</th>
                    </tr>
                    <?php $SN = 1; ?>
                    <?php while ($row = $history_result->fetch_assoc()) { ?>
                        <tr >
                            <td scope='row'><?php echo $SN++; ?></td>
                            <td scope='row'><?php echo $row["id"]; ?></td>
                            <td><?php echo $row["invoice_number"]; ?></td>
                            <td><?php echo $row["supplierName"]; ?></td>
                            <td><?php echo $row["status"]; ?></td>
                            <td><?php echo number_format($row["amount"], 2); ?></td>
                            <td><?php echo $row["payment_date"]; ?></td>
                        </tr>
                    <?php } ?>
                </table>
            <?php } else { ?>
                <p>No payment history available.</p>
            <?php } ?>
        </div>
    </div>
    <script>
        function payInvoice(invoiceId) {
            if (confirm("Are you sure you want to pay this invoice-"+ invoiceId + "?")) {
                 // Redirect to the payment processing page
                 3
                window.location.href = "processPayment.php?invoice_id=" + invoiceId;
            }
        }
    </script>

</body>
</html>

<?php $conn->close(); ?>
