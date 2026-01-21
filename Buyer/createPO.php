<?php 
    include '../_dbconnect.php';
      
    //include './nocatche.php';
    session_start();
    if(!isset($_SESSION['loggedin'])|| $_SESSION['loggedin'] != true){
        header("location: index.php");
        exit;
    } 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Request Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="//cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../custom.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>

        .form-container {
            max-width: 600px;

            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        .form-group button {
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .form-group button:hover {
            background-color: #45a049;
        }
        .message {
            margin-top: 20px;
        }
        textarea{
            width: -webkit-fill-available;
        }
        ::placeholder {
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <?php 
        //include './nocatche.php';
        include 'createPO_function.php';
        require '../_nav_afterLogin.php';
        require '../_vnav.php';
        require_once 'functions.php';
   
        $tableName = 'Purchase_Order';
        $prefix = 'PO';
        $POID = generateNewId($conn, $tableName, $prefix);
    ?>
    <div class = "main-content">
        <?php
            if($submitted){
                echo '<div class="alert alert-success alert-dismissible fade show" role="alert"><strong>Success! </strong>your request has been submitted successfully.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            }else {
                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>Failed! </strong>your request has not been submitted due to-'.$errormsg.'.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            }
        ?>
        <div class="form-container">
            <h1>Service Request Form</h1>
            <form action="createPO.php" method="POST">
                <div class="form-group">
                    <label for="requestId">Request ID</label>
                    <input type="text" id="requestId" name="requestId" value = <?php echo $POID; ?> required readonly>
                </div>
                <div class="form-group">
                    <label for="supplierName">Request To</label>
                    <input type="text" id="supplierName" name="supplierName" placeholder = "Supplier Name" required>
                </div>
                <div class="form-group">
                <label for="serviceType">Service Type</label>
                <select id="serviceType" name="serviceType" required>
                    <option value="">Select a product</option>
                    <option value="Design and Consulting">Design and Consulting</option>
                </select>
            </div>
                <div class="form-group">
                    <label for="startDate">Start Date:</label>
                    <input type="date" id="startDate" name="startDate" required>
                <!-- </div>
                <div class="form-group"> -->
                    <label for="endDate">End Date:</label>
                    <input type="date" id="endDate" name="endDate" required>
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea rows= "5" id="description" name="description" placeholder = "Short Description" required></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" name="submit">Submit Order</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src = "../vnavdropdown.js"></script>
</body>
</html>
