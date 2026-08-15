<?php
ob_start();
include_once '_config.php';


// Use mysqli to connect to MySQL

  $conn = mysqli_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE, MYSQL_PORT);

  // Die if connection was not successful
 if (!$conn){
    die("Sorry we failed to connect to database: ". mysqli_connect_error());
 }
 return $conn;
?>
