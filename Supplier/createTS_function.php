<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
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
        $timesheetId = htmlspecialchars($_POST['timesheetID']);   
        $buyerName = htmlspecialchars($_POST['buyerName']);
        $serviceType = htmlspecialchars($_POST['serviceType']);
        $fromDate = htmlspecialchars($_POST['fromDate']);
        $toDate = htmlspecialchars($_POST['toDate']);
        $description = htmlspecialchars($_POST['description']);
        $hours = htmlspecialchars($_POST['hours']);
        $myusername = $_SESSION['username'];

        // Basic validation
        if (empty($timesheetId) || empty($buyerName) || empty($serviceType) || empty($fromDate) || empty($toDate) || empty($description) || empty($hours)) {
            echo '<p class="message" style="color: red;">Please fill out all fields correctly.</p>';
        } else {
            // Process the order (e.g., save to database, send email, etc.)
            
            $query = "INSERT INTO `Timesheet` (`ID`, `BuyerName`, `ServiceType`, `FromDate`, `ToDate`, `Hours`, `Status`, `Description`, `user`, `Datestamo`,`Comments`) VALUES ('$timesheetId', '$buyerName', '$serviceType', '$fromDate', '$toDate', '$hours', 'Pending', '$description','$myusername', CURRENT_TIMESTAMP(),'');";
        
            $result = mysqli_query($conn, $query);
            
            if($result){
                $submitted = true; 
            } else {
                echo '<p class="message" style="color: red;">Error: ' . mysqli_error($conn) . '</p>';
            }
            $pdfFile = generatepdf($timesheetId, 'Timesheet');            
            //echo "<script>console.log('PDF: $pdfFile');</script>";
            if(!$pdfFile){
                echo '<p class="message" style="color: red;">Error generating PDF</p>';
            }else {
            //sendmail('rabin.khadka40@yahoo.com', 'Timesheet Request', 'Timesheet has been submitted. Please check the attached PDF and provide approval.', $pdfFile); 
        }}
    }
    
?>