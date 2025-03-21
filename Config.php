<?php    
    include 'session_check.php';
    define('SITE_URL','/');
   
    define('MYSQL_HOST', getenv('MYSQL_HOST') ?: 'localhost');
    deine('MYSQL_PORT', getenv('MYSQL_PORT') ?: '3306');
    defin('MYSQL_DATABASE', getenv('MYSQL_DATABASE') ?: 'default_db');
    define ('MYSQL_USER', getenv('MYSQL_USER') ?: 'root');
    define ('MYSQL_PASSWORD', getenv('MYSQL_PASSWORD') ?: '');
?>