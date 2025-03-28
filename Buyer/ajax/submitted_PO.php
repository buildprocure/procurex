<?php

include '../../_dbconnect.php';
session_start();

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// Check if 'username' is set in the session
if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
    $username = $_SESSION['username'];
} else {
    echo "Session 'username' is not set or is empty.";
    exit;
}

// Define the allowed columns for sorting
$columns = ['RequestID', 'SupplierName', 'ServiceType', 'StartDate', 'EndDate', 'Status', 'SubmittedDate'];
$column = isset($_GET['column']) && in_array($_GET['column'], $columns) ? $_GET['column'] : 'ID'; // Default sort column

// Get sorting order from URL parameter, default is ascending
$order = isset($_GET['order']) && $_GET['order'] == 'desc' ? 'desc' : 'asc';

// Determine the next sort order
$next_order = $order == 'asc' ? 'desc' : 'asc';

// Base SQL query
$sql = "SELECT * FROM `Purchase_Order` WHERE user = '$username'";

// Check if POST data is provided for filtering
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $requestID = isset($_POST['requestID']) ? $_POST['requestID'] : '';
    $supplierName = isset($_POST['supplierName']) ? $_POST['supplierName']: '';
    

    $conditions = [];
    if (!empty($requestID)) {
        $conditions[] = "ID = '" . $conn->real_escape_string($requestID) . "'";
    }
    if (!empty($supplierName)) {
        $conditions[] = "SupplierName = '" . $conn->real_escape_string($supplierName) . "'";
    }
    if(!empty($fromDate)){
        $conditions[]= "StartDate >= '" . $conn->real_escape_string($fromDate) . "'";
    }
    if(!empty($toDate)){
        $conditions[]= "EndDate <= '" . $conn->real_escape_string($toDate) . "'";
    }


    if (count($conditions) > 0) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
}

// Modify the query to include sorting
$sql .= " ORDER BY $column $order";
// Execute the query
$result = mysqli_query($conn, $sql);
$num = $result ? mysqli_num_rows($result) : 0;

// Display the total number of records
echo '<div class="container">
        <div class="row"> 
            <div class="col-md-4 d-flex align-items-center">
                <label for="total" class="form-label mb-0 me-4">Total records:</label>
                <input type="number" name="total" id="total" class="form-control" style="flex-grow: 1;" value = ' . $num . ' readonly>
            </div>
        </div>
    </div>';

// Start table output with sortable headers
echo "<table class='table table-bordered table-striped' color='white;' id='myTable'>
        <thead>
            <tr>
                <th scope='col'>SN</th>
                <th scope='col'><a href='?column=ID&order=$next_order'>Request ID</a></th>
                <th scope='col'><a href='?column=SupplierName&order=$next_order'>Supplier Name</a></th>
                <th scope='col'><a href='?column=ServiceType&order=$next_order'>Service Type</a></th>
                <th scope='col'><a href='?column=StartDate&order=$next_order'>Start Date</a></th>
                <th scope='col'><a href='?column=EndDate&order=$next_order'>End Date</a></th>
                <th scope='col'><a href='?column=Description&order=$next_order'>Description</a></th>
                <th scope='col'><a href='?column=Status&order=$next_order'>Status</a></th>
                <th scope='col'><a href='?column=SubmittedDate&order=$next_order'>Submitted Date</a></th>
                <th scope='col'><a href='?column=Comments&order=$next_order'>Supplier's Comments</a></th>
            </tr>
        </thead>";
echo "<tbody>";
$SNo = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $SNo++;
    switch ($row['Status']) {
        case 'Pending':
            $bgColor = 'yellow';
            break;
        case 'Accepted':
            $bgColor = 'green';
            break;
        case 'Rejected':
            $bgColor = 'red';
            break;
        default:
            $bgColor = 'white';
    }
    $row['Comments'] = $row['Comments'] ? $row['Comments'] : '--';
    echo "<tr>
            <td scope='row'>" . $SNo . "</td>
            <td><a href='PoDetails.php?ID=" . $row['ID'] . "'> " . $row['ID'] . " </a></td>
            <td>" . $row['SupplierName'] . "</td>
            <td>" . $row['ServiceType'] . "</td>
            <td>" . $row['StartDate'] . "</td>
            <td>" . $row['EndDate'] . "</td>
            <td>" . $row['Description'] . "</td>
            <td style='background-color: " . $bgColor . ";'>" . $row['Status'] . "</td>
            <td>" . $row['SubmittedDate'] . "</td>
            <td>" . $row['Comments'] . "</td>
        </tr>";
}
echo "</tbody>";
echo "</table>";

$conn->close();
?>
