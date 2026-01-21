<?php
// Include the SimpleSAMLphp autoloader
require_once('/var/www/simplesamlphp/lib/_autoload.php');

// Initialize the auth source
$auth = new \SimpleSAML\Auth\Simple('auth0');

// Check if the user is authenticated
if (!$auth->isAuthenticated()) {
    // Redirect to login if not authenticated
    $auth->login(['ReturnTo' => 'https://ilifes.store/dashboard.php']);
}

// Get user attributes
$userAttributes = $auth->getAttributes();



// Access specific user attributes using their full keys
$email = $userAttributes['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress'][0] ?? 'Unknown';
$name = $userAttributes['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name'][0] ?? 'Unknown';
$role = $userAttributes['http://schemas.auth0.com/roles'][0] ?? 'Unknown';
$picture = $userAttributes['http://schemas.auth0.com/picture'][0] ?? '';

// Start session if not already started
// if (session_status() == PHP_SESSION_NONE) {
//     session_start();
// }
$_SESSION['loggedin'] = true;
$_SESSION['username'] = $email;

// error_log("Session data: " . print_r($_SESSION, true));
// error_log("Session ID: " . session_id());
// exit();
   
echo "Welcome, ".$_SESSION['username']."! You are logged in as $role.";
//header("location: ../$role/loggedinhome.php");

exit();

?>
