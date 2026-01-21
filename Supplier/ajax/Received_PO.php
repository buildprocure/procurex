<?php

 include '../../_dbconnect.php'; 
 session_start();


 $username = $_SESSION['username'];
 //why I'm getting $username null



 $sql = "SELECT * FROM `Timesheet` WHERE user = '$username'";
 
 //check if data was sent via POST method
 if ($_SERVER['REQUEST_METHOD']=='POST'){
    //Get the requestID and supplierName from the POST request
    $requestID = isset($_POST['ID']) ? $_POST['ID'] : '';
    $buyerName = isset($_POST['buyerName']) ? $_POST['buyerName']: '';
    $startDate = isset($_POST['startDate']) ? $_POST['startDate']: '';
    $endDate = isset($_POST['endDate']) ? $_POST['endDate']: '';

    $conditions = [];
    if(!empty($timesheetID)){
        $conditions[]= "ID = '" . $conn->real_escape_string($timesheetID) . "'";

    }
    if(!empty($buyerName)){
        $conditions[]= "user ='" . $conn->real_escape_string($buyerName) . "'";
    }
    if(!empty($fromDate)){
        $conditions[]= "StartDate >= '" . $conn->real_escape_string($startDate) . "'";
    }
    if(!empty($toDate)){
        $conditions[]= "EndDate <= '" . $conn->real_escape_string($endDate) . "'";
    }
    

    $sql = "SELECT * FROM `Purchase_Order` WHERE SupplierName = '$username'";
    //Append conditions if exist
    if(count($conditions)>0){
        $sql .= " AND " . implode(" AND ", $conditions);
    }

    $result = mysqli_query ($conn, $sql);
    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }   
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
                <th scope='col'>Request By</th>
                <th scope='col'>Service Type</th>
                <th scope='col'>Start Date</th>
                <th scope='col'>End Date</th>
                <th scope='col'>Description</th>
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
                    $bgColor = '#f7687b';
                }
                echo " <tr>
                          <td scope='row'>".$SNo."</td>
                          <td> <a href = 'poDetails.php?ID=".$row['ID']."'> ".$row['ID']." </a></td>
                          <td>".$row['user']."</td>
                          <td>".$row['ServiceType']."</td>
                          <td>".$row['StartDate']."</td>
                          <td>".$row['EndDate']."</td>
                          <td>".$row['Description']."</td>
                          <td style='background-color: " . $bgColor . ";'>".$row['Status']."</td>
                          <td>".$row['SubmittedDate']."</td>
                        </tr>";
        }
        echo " </tbody>";
        echo "</table>";

    }

    ?> 