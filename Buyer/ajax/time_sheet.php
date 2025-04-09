<?php

 include '../../_dbconnect.php'; 
 session_start();

 $username = $_SESSION['username'];
 //why I'm getting $username null

 //check if data was sent via POST method
 if ($_SERVER['REQUEST_METHOD']=='POST'){
    //Get the requestID and supplierName from the POST request
    $requestID = isset($_POST['requestID']) ? $_POST['requestID'] : '';
    $supplierName = isset($_POST['supplierName']) ? $_POST['supplierName']: '';

    $conditions = [];
    if(!empty($requestID)){
        $conditions[]= "requestID = '" . $conn->real_escape_string($requestID) . "'";

    }
    if(!empty($supplierName)){
        $conditions[]= "supplierName ='" . $conn->real_escape_string($supplierName) . "'";
    }

    $sql = "SELECT * FROM `Timesheet` WHERE BuyerName = '$username'";
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
            <th scope='col'>Start Date</th>
            <th scope='col'>End Date</th>
            <th scope='col'>Description</th>
            <th scope='col'>Hours</th>
            <th scope='col'>Status</th>
            <th scope='col'>Submitted Date</th>
            <th scope='col'>Comments</th>
            <th scope='col'>Invoice Generated</th>
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
            $row['Comments'] = $row['Comments'] ? $row['Comments'] : '--';                
             echo "<tr>
                    <td scope='row'>".$SNo."</td>
                    <td> <a href = 'TSDetails.php?ID=".$row['ID']."'> ".$row['ID']." </a></td>
                    <td>".$row['user']."</td>
                    <td>".$row['ServiceType']."</td>
                    <td>".$row['FromDate']."</td>
                    <td>".$row['ToDate']."</td>
                    <td>".$row['Description']."</td>
                    <td>".$row['Hours']."</td>
                    <td style='background-color: " . $bgColor . ";'>".$row['Status']."</td>
                    <td>".$row['Datestamo']."</td>
                    <td>".$row['Comments']."</td>
                    <td>".$row['Invoice_Generated']."</td>
                </tr>";
        }
        echo " </tbody>";
        echo "</table>";

 }


    ?> 