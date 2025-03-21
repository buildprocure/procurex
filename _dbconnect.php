<?php
ob_start();
include 'config.php';
/* $update = false;
$delete = false; */
  // Connect to the database
// $host = '143.198.64.132';
// $db   = 'ilife';
// $user = 'root';
// $pass = '@Shova595Bhandari';
// $port = '3306';

// Use mysqli to connect to MySQL

  $conn = mysqli_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE, MYSQL_PORT);

  // Die if connection was not successful
 if (!$conn){
    die("Sorry we failed to connect to database: ". mysqli_connect_error());
 }
?>