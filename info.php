<?php
include '_config.php'; // Include your configuration file

$isLoggedIn = false;

// Username/password login (example: session key set during login)
if (isset($_SESSION['username'])) {
    $isLoggedIn = true;
}


if (!$isLoggedIn) {
    http_response_code(403);
    echo "Access denied. You must be logged in.";
    exit;
}

// Step 2: Require Basic Auth for extra protection
$user = INFOPAGE_USER;
$pass = INFOPAGE_PASS;

if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) || $_SERVER['PHP_AUTH_USER'] !== $user || $_SERVER['PHP_AUTH_PW'] !== $pass) {

    header('WWW-Authenticate: Basic realm="Restricted Area"');
    header('HTTP/1.0 401 Unauthorized');
    echo "Unauthorized Access.";
    exit;
}

// If both checks pass, display the info
phpinfo();
