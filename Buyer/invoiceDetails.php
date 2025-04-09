<?php
include '../_dbconnect.php';
include '../_nocatche.php';
session_start();


// Enable error reporting for debugging
// error_reporting(E_ALL);
// ini_set("display_errors", 1);
// ini_set('error_reporting', E_ALL);
$POupdate = false;
$POupdateFailed = false;

// Check if invoice ID is provided
if (!isset($_GET['ID']) || empty($_GET['ID'])) {
    die("No PO request ID provided.");
}

// Sanitize invoice ID
$invoiceID = $_GET['ID'];

// Fetch invoice details from the database
$query = "SELECT * FROM Invoice WHERE ID = '$invoiceID'";
$res = mysqli_query($conn, $query);

// Check if query was successful
if (!$res) {
    die("Query failed! ");
}

// Fetch invoice data
if ($res->num_rows > 0) {
    $result = $res->fetch_assoc();
} else {
    die("PO Request not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'];
    $comments = $_POST['comments'];

    // Validate input
    if (empty($status)) {
        echo "Please select the status.";
        
    } else {
        if (($status == "Rejected") && empty($comments)) {
            echo "Please provide comments for rejection.";
        } else {
            $comment = $comments ? $comments : "";
            // Update invoice status and comments in the database
            $update_query = "UPDATE Invoice SET Status = '$status', comments = '$comment' WHERE ID = '$invoiceID'";
            $resu = mysqli_query($conn, $update_query);
            //Insert record into the payment table
            $insert_query = "INSERT INTO payments (id, invoice_number, buyer_id, amount, payment_date, supplier_id, method, status, payment_due_date created_at)
            SELECT UUID(), i.id, b.buyer_id, i.amount, Null, s.supplier_id, 'Bank Transfer', 'Pending', DATE_ADD(NOW(), INTERVAL 7 DAY), NOW()
            FROM Invoice i
            JOIN buyer_list_table b ON i.buyerName = b.username
            JOIN supplier_list_table s ON i.supplier = s.username
            WHERE i.id = '$invoiceID'";
            $insert_result = mysqli_query($conn, $insert_query);

            if ($resu) {
                //echo "PO request updated successfully.";
                $POupdate = true;
            } else {
                $POupdateFailed = true;
            }
        }
    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PO Request Details</title>
    <link rel="stylesheet" href="../custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="//cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
</head>
<body>
    <div>
        <?php require '../_nav_afterLogin.php'; ?>
        <?php require '../_vnav.php'; ?>

        <div class="main-content">
            <div class="row">
                <!-- Left Column: PO Details -->
                <div class="col-md-6">
                    <?php if ($POupdate): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <strong>Success!</strong> Invoice has been approved and notified to supplier successfully.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php elseif ($POupdateFailed): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> PO request could not be updated.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <h2 class="mb-4">Invoice Details</h2>

                    <!-- PO Request Information -->
                    <div class="card shadow mb-4">
                        
                            <table class="table table-bordered">
                                <tr><th>Invoice ID:</th><td><?php echo htmlspecialchars($result['ID']); ?></td></tr>
                                <tr><th>Service Type</th><td><?php echo htmlspecialchars($result['ServiceType']); ?></td></tr>
                                <tr><th>Supplier Name</th><td><?php echo htmlspecialchars($result['Supplier']); ?></td></tr>
                                <tr><th>Timesheet IDs</th><td><?php echo htmlspecialchars($result['TimesheetID']); ?></td></tr>
                                <tr><th>Hours</th><td><?php echo htmlspecialchars($result['Hours']); ?></td></tr>
                                <tr><th>Rate</th><td><?php echo htmlspecialchars($result['Rate']); ?></td></tr>
                                <tr><th>Amount</th><td><?php echo htmlspecialchars($result['Amount']); ?></td></tr>
                                <tr><th>Status</th><td><?php echo htmlspecialchars($result['Status']); ?></td></tr>
                                <tr><th>Submitted Date</th><td><?php echo htmlspecialchars($result['SubmittedDate']); ?></td></tr>
                            </table>
                        
                    </div>

                    <!-- Form for updating PO status -->
                    <div class="card shadow mb-5">
                        <div class="card-header bg-secondary text-white">
                            <h5>Update Invoice Status</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status:</label>
                                    <select name="status" id="status" class="form-select" required>
                                        <option value="">Select Status</option>
                                        <option value="Accepted">Approve</option>
                                        <option value="Rejected">Reject</option>
                                    </select>
                                </div>
                                <div class="mb-3" class="mb-3" id="comments-container" style="display: none;">
                                    <label for="comments" class="form-label">Buyer's Comments:</label>
                                    <textarea id="comments" name="comments" class="form-control" rows="4"><?php echo htmlspecialchars($result['Comments']); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Update Status</button>
                            </form>
                        </div>
                    </div>
                </div>                
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoX1B6ZF5pIHf6lue/r9G/FijGVbSWeJvOJ2C6jqT8I6B7j" crossorigin="anonymous"></script>
    <script src="../vnavdropdown.js"></script>
    <script src="//cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        $(document).ready(function () {
            // Listen for changes in the dropdown
            $('#status').on('change', function () {
                var status = $(this).val(); // Get the selected value
                
                if (status === 'Rejected') {
                    $('#comments-container').slideDown(); // Show the text field with slide effect
                    $('#comments').prop('required', true); // Make textarea required
                } else {
                    $('#comments-container').slideUp(); // Hide the text field with slide effect
                    $('#comments').prop('required', false); // Remove required attribute
                    $('#comments').val(''); // Clear the textarea
                }
            });
        });
    </script>

</body>
</html>
