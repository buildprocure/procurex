<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
include '../../_dbconnect.php';
include '../../config.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['query'])) {
    $query = "%" . $_GET['query'] . "%"; // For partial matches
    $buyer_name = $_GET['buyer_name'];
    $supplier_name = $_SESSION['username'];

    // echo "query: $query";
    // echo "buyer_name: $buyer_name";
    // echo "supplier_name: $supplier_name";

    // Fetch timesheets within date range and matching input query
    $sql = "SELECT ID 
    FROM Timesheet 
    WHERE ID LIKE ? AND BuyerName = ? AND user = ?";

    // Prepare the SQL statement
    $stmt = mysqli_prepare($conn, $sql);

    // Check if the statement preparation is successful
    if ($stmt === false) {
    die('Error preparing the SQL query: ' . mysqli_error($conn));
    }

    // Bind the parameters to the prepared statement
    mysqli_stmt_bind_param($stmt, 'sss', $query, $buyer_name, $supplier_name);

    // Execute the statement
    mysqli_stmt_execute($stmt);

    // Get the result
    $result = mysqli_stmt_get_result($stmt);

    $timesheets = [];
  
    while ($row = mysqli_fetch_assoc($result)) {
        $timesheets[] = $row['ID'];
    }
    //echo "timesheets: ";
    echo json_encode($timesheets);
} else {
    echo json_encode([]);
}
?>
