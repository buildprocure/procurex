<?php
    include '../_dbconnect.php';
    include_once 'functions.php';
    include_once '../send-email.php';

    if(!isset($_SESSION['loggedin'])|| $_SESSION['loggedin'] != true){
        header("location: index.php");
        exit;
    } 
    $errormsg = '';
    $submitted = false;
    if (isset($_POST['submit'])) {
        // Retrieve form data
        $requestId = htmlspecialchars($_POST['requestId']);
        $supplierName = htmlspecialchars($_POST['supplierName']);
        $serviceType = htmlspecialchars($_POST['serviceType']);
        $startDate = htmlspecialchars($_POST['startDate']);
        $endDate = htmlspecialchars($_POST['endDate']);
        $description = htmlspecialchars($_POST['description']);
        $myusername = $_SESSION['username'];

        // Basic validation
        if (empty($requestId) || empty($supplierName) || empty($serviceType) || empty($startDate) || empty($endDate) || empty($description)) {
            echo '<p class="message" style="color: red;">Please fill out all fields correctly.</p>';
        } else {
            // Process the order (e.g., save to database, send email, etc.)
            // For demonstration, we're just displaying the information

            
            $query = "INSERT INTO `Purchase_Order` (`ID`, `SupplierName`, `ServiceType`, `StartDate`, `EndDate`, `Description`, `Status`, `user`, `SubmittedDate`) VALUES ('$requestId', '$supplierName', '$serviceType', '$startDate', '$endDate', '$description', 'Pending', '$myusername', CURRENT_TIMESTAMP())";
        
            $result = mysqli_query($conn, $query);
      
            if($result){                             
                $submitted = true; 
            }else{
                $errormsg = "Error: " . $query . "<br>" . mysqli_error($conn);
            }
            $pdfFile = generatepdf($requestId);
            echo "<script>console.log('PDF: $pdfFile');</script>";
            if(!$pdfFile){
                echo '<p class="message" style="color: red;">Error generating PDF</p>';
            }
            sendmail('connect2ilife@gmail.com', 'PO Request', 'PO Request has been submitted. Please check the attached PDF.', $pdfFile); 
        }
    }
    
?>