<?php
session_start();
require_once __DIR__ . '/../../_dbconnect.php';
// Example: session carries logged-in user info
$role = $_SESSION['role'] ?? null;
$query = 'SELECT * FROM user WHERE username= ?';
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
// Check if the user has a subscription
// Assuming you have a way to check if the user has a subscription
// For example, you might have a column in the user table that indicates this
$has_subscription = $user['has_duplicate_payment_access']; // Check if the user has the required role and subscription status
$access_granted = false;

if ($role === 'Supplier'|| $role === 'Admin') {
    // Admins and suppliers can access the page without a subscription
    $access_granted = true; // Suppliers can access without subscription
} elseif ($role === 'Buyer' && $has_subscription) {
    $access_granted = true; // Buyer must have subscription
}

if (!$access_granted) {
    die("Access Denied: You are not authorized to view this page.");
}

// Load the module
require_once __DIR__ . '/controllers/DuplicatePaymentController.php';


$controller = new DuplicatePaymentController($conn);
$controller->index();
