<?php
   
    include 'session_check.php';
    define('SITE_URL','/');
    define('BASE_PATH', __DIR__ . '/');  // Gives absolute path to the root directory
    define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/");


    define('MYSQL_HOST', getenv('MYSQL_HOST') ?: 'localhost');
    define('MYSQL_PORT', getenv('MYSQL_PORT') ?: '3306');
    define('MYSQL_DATABASE', getenv('MYSQL_DATABASE') ?: 'default_db');
    define ('MYSQL_USER', getenv('MYSQL_USER') ?: 'root');
    define ('MYSQL_PASSWORD', getenv('MYSQL_PASSWORD') ?: '');
    define ('INFOPAGE_USER', getenv('INFOPAGE_USER'));
    define ('INFOPAGE_PASS', getenv('INFOPAGE_PASS'));  
?>