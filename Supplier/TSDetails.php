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
$requestID = $_GET['ID'];

// Fetch invoice details from the database
$query = "SELECT * FROM Timesheet WHERE ID = '$requestID'";
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

    $updatedServiceType = mysqli_real_escape_string($conn, $_POST['serviceType']);
    $updatedFromDate = mysqli_real_escape_string($conn, $_POST['FromDate']);
    $updatedToDate = mysqli_real_escape_string($conn, $_POST['ToDate']);
    $updatedHours = mysqli_real_escape_string($conn, $_POST['Hours']);
    $updatedDescription = mysqli_real_escape_string($conn, $_POST['Description']);

    
    // Update query
    $updateQuery = "UPDATE Timesheet 
                    SET ServiceType = '$updatedServiceType', FromDate = '$updatedFromDate', 
                        ToDate = '$updatedToDate', Hours = '$updatedHours', 
                        Description = '$updatedDescription', Status = 'Pending'
                    WHERE ID = '$requestID'";
                    
    if (mysqli_query($conn, $updateQuery)) {       
        $POupdate = true;
        $file = "var/www/html/files/TS_Files/$requestID.pdf";
        echo "<script>console.log('File: $file');</script>";
        require_once 'functions.php';
        if(file_exists("var/www/html/files/TS_Files/$requestID.pdf")){
            chmod("var/www/html/files/TS_Files/$requestID.pdf", 0777);
            unlink("var/www/html/files/TS_Files/$requestID.pdf");
        }else{
            echo "File does not exist";
        }
        $pdfFile = generatepdf($requestID, 'Timesheet');
        echo "<script>console.log('PDF: $pdfFile');</script>";
        if(!$pdfFile){
            echo '<p class="message" style="color: red;">Error updating PDF</p>';
        } 
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

                    <h2 class="mb-4">Timesheet Details</h2>

                    <div class ="d-flex justify-content-end">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editModal">Edit PO</button>
                    </div>
                    <!-- PO Request Information -->
                    <div class="card shadow mb-4">
                        
                        <table class="table table-bordered">
                            <tr><th>Timesheet ID:</th><td><?php echo htmlspecialchars($result['ID']); ?></td></tr>
                            <tr><th>Buyer</th><td><?php echo htmlspecialchars($result['BuyerName']); ?></td></tr>
                            <tr><th>Service Type</th><td><?php echo htmlspecialchars($result['ServiceType']); ?></td></tr>
                            <tr><th>From </th><td><?php echo htmlspecialchars($result['FromDate']); ?></td></tr>
                            <tr><th>To</th><td><?php echo  htmlspecialchars($result['ToDate']); ?></td></tr>
                            <tr><th>Hours</th><td><?php echo  htmlspecialchars($result['Hours']); ?></td></tr>
                            <tr><th>Description</th><td><?php echo htmlspecialchars($result['Description']); ?></td></tr>                            
                            <tr><th>Status</th><td><?php echo htmlspecialchars($result['Status']); ?></td></tr>
                            <tr><th>Submitted Date</th><td><?php echo htmlspecialchars($result['Datestamo']); ?></td></tr>
                            
                        </table>
                    </div>
                    <?php if ($result['Comments'] != null) { ?>                         
                        <div class="alert alert-warning" role="alert">
                            <strong>Buyer's Comments:</strong> <?php echo htmlspecialchars($result['Comments']); ?>
                        </div>
                    <?php } ?>
                    
                    <?php if ($result['Status'] == 'Accepted') { ?>
                        <div class="alert alert-success" role="alert">
                            <strong>PO Approved!</strong> Please proceed.
                        </div>
                    <?php } ?> 
                </div>

                <!-- Right Column: PDF Options -->
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-body">
                            <iframe src="/files/TS_Files/<?php echo $result['ID']; ?>.pdf" width="100%" height="500px"></iframe>
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
                            <label for="serviceType" class="form-label">Service Type</label>
                            <input type="text" class="form-control" id="serviceType" name="serviceType" value="<?php echo htmlspecialchars($result['ServiceType']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="FromDate" class="form-label">From</label>
                            <input type="Date" class="form-control" id="FromDate" name="FromDate" value="<?php echo htmlspecialchars($result['FromDate']); ?>">
                        </div>        
                        <div class="mb-3">
                            <label for="ToDate" class="form-label">To </label>
                            <input type="Date" class="form-control" id="ToDate" name="ToDate" value="<?php echo htmlspecialchars($result['ToDate']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="Hours" class="form-label">Hours</label>
                            <input type="Number" class="form-control" id="Hours" name="Hours" value="<?php echo htmlspecialchars($result['Hours']); ?>">
                        </div>   
                        <div class="mb-3">
                            <label for="Description" class="form-label">Description</label>
                            <input type="Text" class="form-control" id="Description" name="Description" value="<?php echo htmlspecialchars($result['Description']); ?>">
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
