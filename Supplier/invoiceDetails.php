<?php
include '../_dbconnect.php';
include '../_nocatche.php';
session_start();

$POupdate = false;
$POupdateFailed = false;

// Check if invoice ID is provided
if (!isset($_GET['ID']) || empty($_GET['ID'])) {
    die("No PO request ID provided.");
}

// Sanitize invoice ID
$requestID = $_GET['ID'];

// Fetch invoice details from the database
$query = "SELECT * FROM Invoice WHERE ID = '$requestID'";
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

// Handle form submission for updating PO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['savePO'])) {
    // Sanitize and capture updated data

    $updatedHours = mysqli_real_escape_string($conn, $_POST['Hours']);
    $updatedRate = mysqli_real_escape_string($conn, $_POST['Rate']);
    $updatedAmount = $updatedHours * $updatedRate;

    
    // Update query
    $updateQuery = "UPDATE Invoice 
                    SET Hours = '$updatedHours', Rate = '$updatedRate', Amount = '$updatedAmount', Status = 'Pending'
                    WHERE ID = '$requestID'";

    if (mysqli_query($conn, $updateQuery)) {
        $POupdate = true;
    } else {
        $POupdateFailed = true;
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
                            <strong>Success!</strong> PO request updated successfully.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php elseif ($POupdateFailed): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> PO request could not be updated.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <h2 class="mb-4">Submitted PO Request Details</h2>

                    <div class ="d-flex justify-content-end">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editModal">Edit PO</button>
                    </div>
                    <!-- PO Request Information -->
                    <div class="card shadow mb-4">
                        
                        <table class="table table-bordered">
                            <tr><th>PO Request ID:</th><td><?php echo htmlspecialchars($result['ID']); ?></td></tr>
                            <tr><th>Buyer</th><td><?php echo htmlspecialchars($result['BuyerName']); ?></td></tr>
                            <tr><th>Service Type</th><td><?php echo htmlspecialchars($result['ServiceType']); ?></td></tr>
                            <tr><th>Timesheet IDs</th><td><?php echo htmlspecialchars($result['TimesheetID']); ?></td></tr>
                            <tr><th>Hours</th><td><?php echo  htmlspecialchars($result['Hours']); ?></td></tr>
                            <tr><th>Rate</th><td><?php echo  "$".htmlspecialchars($result['Rate']); ?></td></tr>
                            <tr><th>Amount</th><td><?php echo "$".htmlspecialchars($result['Amount']); ?></td></tr>                            
                            <tr><th>Status</th><td><?php echo htmlspecialchars($result['Status']); ?></td></tr>
                            <tr><th>Submitted Date</th><td><?php echo htmlspecialchars($result['SubmittedDate']); ?></td></tr>
                            
                        </table>
                    </div>
                    <?php if ($result['Comments'] != null) { ?>                         
                        <div class="alert alert-warning" role="alert">
                            <strong>Buyer's Comments:</strong> <?php echo htmlspecialchars($result['Comments']); ?>
                        </div>
                    <?php } ?>
                    
                    <?php if ($result['Status'] == 'Accepted') { ?>
                        <div class="alert alert-success" role="alert">
                            <strong>PO Approved!</strong> Please proceed with the sending the payment link.
                        </div>
                    <?php } ?>
                    
                </div>

                <!-- Right Column: PDF Options -->
                <div class="col-md-6">
                    <!-- PDF Generation Button -->
                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5>Create PDF</h5>
                        </div>
                        <div class="card-body">
                            <form action="generate_pdf.php" method="post" target="_blank">
                                <input type="hidden" name="requestID" value="<?php echo htmlspecialchars($result['RequestID']); ?>">
                                <button type="submit" class="btn btn-success">Generate PDF</button>
                            </form>
                        </div>
                    </div>

                    <!-- PDF Preview -->
                    <div class="card shadow">
                        <div class="card-body">
                            <iframe src="/files/PO_files/<?php echo 'PO_Request_'.$result['RequestID']; ?>.pdf" width="100%" height="500px"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit PO Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="requestID" value="<?php echo htmlspecialchars($result['ID']); ?>">                        
                        <div class="mb-3">
                            <label for="Hours" class="form-label">Hours</label>
                            <input type="Number" class="form-control" id="Hours" name="Hours" value="<?php echo htmlspecialchars($result['Hours']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="Rate" class="form-label">Rate</label>
                            <input type="Number" class="form-control" id="Rate" name="Rate" value="<?php echo htmlspecialchars($result['Rate']); ?>">
                        </div>                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" name="savePO">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="//cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" ></script>
    <script src="../vnavdropdown.js"></script>
 </body>
</html>
