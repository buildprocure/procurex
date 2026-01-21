<?php

 include '../../_dbconnect.php'; 
 session_start();


 $username = $_SESSION['username'];
  
 //check if data was sent via POST method
 if ($_SERVER['REQUEST_METHOD']=='POST'){
    //Get the requestID and supplierName from the POST request
    $fromDate = isset($_POST['fromDate']) ? $_POST['fromDate'] : '';
    $toDate = isset($_POST['toDate']) ? $_POST['toDate'] : '';
    $invoiceID = isset($_POST['invoiceID']) ? $_POST['invoiceID'] : '';
    $hours = isset($_POST['hours']) ? $_POST['hours'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $supplierName = isset($_POST['supplierName']) ? $_POST['supplierName']: '';
    

    $conditions = [];
    if(!empty($fromDate)){
        $conditions[]= "SubmittedDate >= '" . $conn->real_escape_string($fromDate) . "'";
    }
    if(!empty($toDate)){
        $conditions[]= "SubmittedDate <= '" . $conn->real_escape_string($toDate) . "'";
    }
    if(!empty($invoiceID)){
        $conditions[]= "ID = '" . $conn->real_escape_string($invoiceID) . "'";
    }
    if(!empty($hours)){
        $conditions[]= "Hours = '" . $conn->real_escape_string($hours) . "'";
    }
    if(!empty($status)){
        $conditions[]= "Status ='" . $conn->real_escape_string($status) . "'";
    }
    if(!empty($supplierName)){
        $conditions[]= "supplier ='" . $conn->real_escape_string($supplierName) . "'";
    }

    $sql = "SELECT * FROM `Invoice` WHERE BuyerName = '$username'";
    //Append conditions if exist
    if(count($conditions)>0){
        $sql .= " AND " . implode(" AND ", $conditions);
    }

    $result = mysqli_query ($conn, $sql);  
    if($result){
        $num = mysqli_num_rows($result);
    }
                
     // Count the number of rows in the result set
     $num = 0;
     if ($result) {
         $num = mysqli_num_rows($result);
     }

    echo '<div class="container">
            <div class="row"> 
                <div class="col-md-4 d-flex align-items-center">
                    <label for="total" class="form-label mb-0 me-4">Total records:</label>
                    <input type="number" name="total" id="total" class="form-control" style="flex-grow: 1;" value = ' . $num . ' readonly>
                </div>
            </div>
        </div>';

    echo "<table class='table table-bordered table-striped' color='white;' id = 'myTable'>
        <thead>
            <tr>
            <th scope='col'>SN</th>
            <th scope='col'>Request ID</th>
            <th scope='col'>Supplier Name</th>
            <th scope='col'>Service Type</th>
            <th scope='col'>Timesheet ID</th>
            <th scope='col'>Hours</th>
            <th scope='col'>Rate</th>
            <th scope='col'>Amount</th>
            <th scope='col'>Status</th>
            <th scope='col'>Submitted Date</th>
            </tr>
        </thead>";
        echo "<tbody>";
        $SNo = 0;
        while ($row = mysqli_fetch_assoc($result)){
            $SNo=$SNo+1;
            switch ($row['Status']){
            case 'Pending':
                $bgColor = 'yellow';
                break;
            case 'Accepted':
                $bgColor = 'green';
                break;
            case 'Rejected':
                $bgColor = 'red';
            }
                
            
             echo "<tr>
                    <td scope='row'>".$SNo."</td>
                    <td> <a href = 'invoiceDetails.php?ID=".$row['ID']."'> ".$row['ID']." </a></td>
                    <td>".$row['Supplier']."</td>
                    <td>".$row['ServiceType']."</td>
                    <td>".$row['TimesheetID']."</td>
                    <td>".$row['Hours']."</td>
                    <td>".$row['Rate']."</td>
                    <td>".$row['Amount']."</td>
                    <td style='background-color: " . $bgColor . ";'>".$row['Status']."</td>
                    <td>".$row['SubmittedDate']."</td>
                </tr>";
        }
        echo " </tbody>";
        echo "</table>";

 }


    ?> 