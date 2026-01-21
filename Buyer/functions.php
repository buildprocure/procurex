<?php
// Database connection
include '../_dbconnect.php';

// Function to generate the new timesheet ID
function generateNewId($conn, $tablename, $pre) {
    $prefix = $pre.date("mdY")."-";
    echo $prefix ."</br>";

    // Query to get the latest timesheet ID
    $query = "SELECT ID FROM $tablename where ID like '$prefix%'";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {        
        // Custom array creation
        while ($row = mysqli_fetch_assoc($result)) {
        $Ids[] = $row['ID'];
        }
        // Finding the latest ID
        foreach ($Ids as $id) {
            if ($id > null) {
                $latestId = $id;                
            }
        }
        echo "latestid: ".$latestId ."</br>";
       
        $suffixID = substr($latestId, strpos($latestId, "-") + 1);
        $suffixID = (int)$suffixID + 1;
        $newId = $prefix . $suffixID;
    } else {
        // If no previous ID found, start with 01
        $newId = $prefix . "01";
    }

    return $newId;
}

function generatepdf($requestID){
    require_once('../TCPDF/tcpdf.php');
    global $conn;

    // Sanitize the requestID
    $requestID = mysqli_real_escape_string($conn, $requestID);

    // Perform query
    $query = "SELECT * FROM Purchase_Order WHERE ID = '$requestID'";
    $res = mysqli_query($conn, $query);

    // Check if query failed or no result found
    if (!$res || mysqli_num_rows($res) === 0) {
        die("PO Request not found.");
    }

    // Fetch the result
    $result = mysqli_fetch_assoc($res);

    // Create new PDF document
    $pdf = new TCPDF();
    $pdf->AddPage();

    // Set document title
    $pdf->SetTitle('PO Request Details');

    // Define content for PDF
    $htmlContent = '
        <h1>PO Request ID: ' . htmlspecialchars($result['ID']) . '</h1>
        <table border="1" cellpadding="5">
            <tr><th>Service Type</th><td>' . htmlspecialchars($result['ServiceType']) . '</td></tr>
            <tr><th>Start Date</th><td>' . htmlspecialchars($result['StartDate']) . '</td></tr>
            <tr><th>End Date</th><td>' . htmlspecialchars($result['EndDate']) . '</td></tr>
            <tr><th>Description</th><td>' . htmlspecialchars($result['Description']) . '</td></tr>
            <tr><th>Status</th><td>' . htmlspecialchars($result['Status']) . '</td></tr>
            <tr><th>Submitted Date</th><td>' . htmlspecialchars($result['SubmittedDate']) . '</td></tr>
        </table>';

    // Print text using writeHTMLCell
    $pdf->writeHTML($htmlContent, true, false, true, false, '');

    // Save the PDF
    $saveDirectory = '/var/www/html/files/PO_files/';
    if (!is_dir($saveDirectory)) {
        mkdir($saveDirectory, 0755, true);
    }

    $filePath = $saveDirectory . $result['ID'] . '.pdf';
    $pdf->Output($filePath, 'F');

    // Insert record into POFiles
    $sql = "INSERT INTO `PO_Files`(`ID`, `FileName`, `CreateDate`) VALUES ('$requestID','$filePath',CURRENT_TIMESTAMP());";
    mysqli_query($conn, $sql);
    $result = mysqli_query($conn, $query);
    if($result){ 
       echo "<script>console.log('File saved and inserted record on table successfully');</script>";
    } else{
        echo "Error: " . mysqli_error($conn);
    }
    return $filePath;  // Return file path instead of 'true'
}
?>
