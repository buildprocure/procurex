<?php
// Database credentials
include '../../_dbconnect.php';


    if (isset($_GET['query'])) {
        $query = $_GET['query'];

        // Query to fetch buyers where the name matches the search query
        $sql = "SELECT username FROM user WHERE Role = 'Buyer' AND username LIKE ? LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt = $conn->prepare($sql);

        $likeQuery = "%" . $query . "%";
        $stmt->bind_param("s", $likeQuery);
        $stmt->execute();

        $result = $stmt->get_result();
        $buyers = [];

        while ($row = $result->fetch_assoc()) {
            $buyers[] = $row;
        }        
        echo json_encode($buyers); // Return the result as JSON
    }

$conn->close();
?>
