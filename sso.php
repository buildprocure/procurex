<?php
//generate code to redirect to appropriate dashboard based on role after SSO login
//from sso it is coming as Get parameters 'username' 
session_start();
include '_dbconnect.php';
if (isset($_GET['username'])) {
    $username = trim($_GET['username']);

    $sql = "SELECT SN, username, Role, user_enrollment, as_duplicate_payment_access 
            FROM user WHERE username = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        $userId = $row['SN'];
        $role = $row['Role'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

        if ($row['user_enrollment'] === 'Not approved' && $role === 'Buyer') {
            // Handle not approved case if needed
            echo "User not approved.";
        } else {
            // ✅ Successful SSO login
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['has_duplicate_payment_access'] = $row['as_duplicate_payment_access'];

            // Log success
            $logSql = "INSERT INTO login_history (user_id, username, role, ip_address, user_agent, status) 
                       VALUES (?, ?, ?, ?, ?, 'SUCCESS_SSO')";
            $logStmt = mysqli_prepare($conn, $logSql);
            mysqli_stmt_bind_param($logStmt, "issss", $userId, $username, $role, $ip, $agent);
            mysqli_stmt_execute($logStmt);

            // Redirect
            switch ($role) {
                case 'Admin': header("Location: ../Admin/loggedinhome.php"); exit;
                case 'Buyer': header("Location: ../Buyer/loggedinhome.php"); exit;
                case 'Supplier': header("Location: ../Supplier/loggedinhome.php"); exit;
                default: echo "Unknown role.";
            }
        }
    } else {
        echo "User not found.";
    }
} else {
    echo "No username provided. ";
}