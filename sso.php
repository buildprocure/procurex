<?php
//generate code to redirect to appropriate dashboard based on role after SSO login
//from sso_index.php it is coming as a GET parameter 'token' - a short-lived,
//single-use token, NOT the username directly. A bare username would let
//anyone log in as any user just by guessing/knowing it, with no proof a
//real SSO login ever happened. The token lives in saml_users.session_token,
//set by sso_index.php right after a real SAML login succeeds; 'updated'
//auto-bumps to NOW() whenever session_token changes, so it also serves as
//the token's issued-at time.
session_start();
include '_dbconnect.php';
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);

    // Token must be recent (issued within the last 2 minutes)
    $tokenSql = "SELECT username FROM saml_users
                 WHERE session_token = ? AND updated >= (NOW() - INTERVAL 2 MINUTE)";
    $tokenStmt = mysqli_prepare($conn, $tokenSql);
    mysqli_stmt_bind_param($tokenStmt, "s", $token);
    mysqli_stmt_execute($tokenStmt);
    $tokenResult = mysqli_stmt_get_result($tokenStmt);
    $tokenRow = mysqli_fetch_assoc($tokenResult);
    $tokenStmt->close();

    if (!$tokenRow) {
        echo "Invalid or expired SSO token.";
        exit;
    }

    // Immediately clear the token so it can never be replayed, even if
    // it leaked (browser history, a proxy log, a shared link, etc.)
    $consumeStmt = mysqli_prepare($conn, "UPDATE saml_users SET session_token = NULL WHERE session_token = ?");
    mysqli_stmt_bind_param($consumeStmt, "s", $token);
    mysqli_stmt_execute($consumeStmt);
    $consumeStmt->close();

    $username = $tokenRow['username'];

    $sql = "SELECT id, username, Role, user_enrollment, as_duplicate_payment_access
            FROM user WHERE username = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        $userId = $row['id'];
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
    echo "No SSO token provided. ";
}
