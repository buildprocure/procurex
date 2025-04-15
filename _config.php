<?php
   
    include 'session_check.php';
    define('SITE_URL','/');
    define('BASE_PATH', __DIR__ . '/');  // Gives absolute path to the root directory

    define('MYSQL_HOST', getenv('MYSQL_HOST') ?: 'localhost');
    define('MYSQL_PORT', getenv('MYSQL_PORT') ?: '3306');
    define('MYSQL_DATABASE', getenv('MYSQL_DATABASE') ?: 'default_db');
    define ('MYSQL_USER', getenv('MYSQL_USER') ?: 'root');
    define ('MYSQL_PASSWORD', getenv('MYSQL_PASSWORD') ?: '');

   
?>