<?php
ob_start();
/* $update = false;
$delete = false; */
  // Connect to the database
  $servername = "html-db-1";
  $username = "root";
  $password = "@Shova595Bhandari";
  $database = "ilife";

  //Create a connection
  $conn = mysqli_connect($servername, $username, $password, $database);
  // Die if connection was not successful
if (!$conn){
    die("Sorry we failed to connect to database: ". mysqli_connect_error());
}
?>