<?php

    include '../_dbconnect.php';
    include_once 'functions.php';
    include_once '../send-email.php';

    if(!isset($_SESSION['loggedin'])|| $_SESSION['loggedin'] != true){
        header("location: index.php");
        exit;
    } 
    $submitted = false;
    if (isset($_POST['submit'])) {
        // Retrieve form data
        $invoiceID = htmlspecialchars($_POST['invoiceID']);   
        $buyer_name = htmlspecialchars($_POST['buyer_name']);
        $serviceType = htmlspecialchars($_POST['serviceType']);
        $hours = $_POST['hours'];
        $rate = $_POST['rate'];
        $amount = $_POST['amount'];
        $myusername = $_SESSION['username'];
        $timesheetIds = isset($_POST['timesheetIds']) ? $_POST['timesheetIds'] : '';


        // Basic validation
        if (empty($invoiceID) || empty($buyer_name) || empty($serviceType) || empty($hours) || empty($rate) || empty($amount) || empty($timesheetIds)) {
            echo '<p class="message" style="color: red;">Please fill out all fields correctly.</p>';
        } else {
            // Process the order (e.g., save to database, send email, etc.)
            $query = "INSERT INTO `Invoice` (`ID`, `BuyerName`, `ServiceType`,`TimesheetID`, `Hours`, `Rate`, `Amount`, `Supplier`,`Status`, `SubmittedDate`) VALUES ('$invoiceID', '$buyer_name', '$serviceType', '$timesheetIds', '$hours', '$rate', '$amount', '$myusername', 'Pending', CURRENT_TIMESTAMP());";
        
            $result = mysqli_query($conn, $query);
            
            if($result){
                $submitted = true; 
            } else {
                echo '<p class="message" style="color: red;">Error: ' . mysqli_error($conn) . '</p>';
            }
            
            $id_array = explode(',', $timesheetIds);

            // Step 3: Sanitize each ID using mysqli_real_escape_string and wrap in quotes
            $safe_ids = array_map(function($id) use ($conn) {
                return "'" . mysqli_real_escape_string($conn, trim($id)) . "'";
            }, $id_array);

            // Step 4: Implode into a comma-separated string of quoted IDs
            $id_list = implode(',', $safe_ids);
            $update_query = "UPDATE Timesheet SET Invoice_Generated = 'Yes' WHERE ID IN ($id_list)";
            $update_result = mysqli_query($conn, $update_query);
            if($update_result){
                //echo "<script>console.log('Timesheet updated successfully');</script>";
            } else {
                echo '<p class="message" style="color: red;">Error updating Invoice_Generated column in Timesheet Table: ' . mysqli_error($conn) . '</p>';
            }
            $pdfFile = generatepdf($invoiceID, 'Invoice');            
            //echo "<script>console.log('PDF: $pdfFile');</script>";
            if(!$pdfFile){
                echo '<p class="message" style="color: red;">Error generating PDF</p>';
            }else {
            //sendmail('rabin.khadka40@yahoo.com', 'Invoice Request', 'Invoice has been submitted. Please check the attached PDF and provide approval.', $pdfFile); 
        
            }}
    }
    
?>