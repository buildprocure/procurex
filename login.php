<?php
session_start();
include '_dbconnect.php';

$login = false;
$showerror = false;
$notapproved = false;

// If already logged in, redirect
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    switch ($_SESSION['role']) {
        case 'Admin': header("Location: ../Admin/dashboard.php"); exit;
        case 'Buyer': header("Location: ../Buyer/dashboard.php"); exit;
        case 'Supplier': header("Location: ../Supplier/dashboard.php"); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT id, username, password, Role, user_enrollment, as_duplicate_payment_access 
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
            $notapproved = true;
            // Log attempt
            $logSql = "INSERT INTO login_history (user_id, username, role, ip_address, user_agent, status) 
                       VALUES (?, ?, ?, ?, ?, 'NOT_APPROVED')";
            $logStmt = mysqli_prepare($conn, $logSql);
            mysqli_stmt_bind_param($logStmt, "issss", $userId, $username, $role, $ip, $agent);
            mysqli_stmt_execute($logStmt);

        } elseif (password_verify($password, $row['password'])) {
            // ✅ Successful login
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['has_duplicate_payment_access'] = $row['as_duplicate_payment_access'];

            // Log success
            $logSql = "INSERT INTO login_history (user_id, username, role, ip_address, user_agent, status) 
                       VALUES (?, ?, ?, ?, ?, 'SUCCESS')";
            $logStmt = mysqli_prepare($conn, $logSql);
            mysqli_stmt_bind_param($logStmt, "issss", $userId, $username, $role, $ip, $agent);
            mysqli_stmt_execute($logStmt);

            // Redirect
            switch ($role) {
                case 'Admin': header("Location: ../Admin/dashboard.php"); exit;
                case 'Buyer': header("Location: ../Buyer/dashboard.php"); exit;
                case 'Supplier': header("Location: ../Supplier/dashboard.php"); exit;
            }

        } else {
            $showerror = true;
            // Log failed password attempt
            $logSql = "INSERT INTO login_history (user_id, username, role, ip_address, user_agent, status) 
                       VALUES (?, ?, ?, ?, ?, 'FAILED')";
            $logStmt = mysqli_prepare($conn, $logSql);
            mysqli_stmt_bind_param($logStmt, "issss", $userId, $username, $role, $ip, $agent);
            mysqli_stmt_execute($logStmt);
        }
    } else {
        $showerror = true; // Username not found
    }
}
?>



<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
  </head>
  <body style="background-image: linear-gradient(to bottom right, rgb(0, 238, 255), rgb(184, 22, 220)  );    height: min-content;" >
    <?php 
    //require  './_nav.php'; 
   
     //$user=$_SESSION['loggedin'];
    /* echo '<nav class="navbar navbar-expand-lg navbar-dark bg-dark ">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">iNotes</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="./">Home</a>
                        </li>
                        <li class="nav-item">
                        <a class="nav-link" href="#">About</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">Contact us</a>
                        </li>
                        <form class="d-flex" role="search">
                        
                    </form>
                    </ul>
                    <form class="d-flex" role="search">
                      <input class="form-control me-2" type="search" placeholder="Search" aria-label="Search">
                      <button class="btn btn-outline-success" type="submit">Search</button>
                    </form>
                </div>
            </div>
          </nav>';  */
    
    if($login){
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Success</strong> You are logged in.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    }
    if($notapproved){
      echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
              <strong>Pending Approval</strong> Your account is not approved yet. Please wait for admin approval.
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
  }
    if($showerror){
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error</strong> Username and password do not match.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    }
    if(isset($_GET['timeout']) && $_GET['timeout'] == 'true'){
      echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
              <strong>Error</strong> Your session has been expired. Please login again.
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
  }
    ?>
   
    <div class="container my-5 p-4 col-md-3 " style="background-color: rgba(254, 251, 251, 0.825); height: 85vh; font-family: sans-serif ;">
      <br>
        <h2 class = "text-center">Login</h2>
        </br>
        <div class="text-center">
        <a href="/simplesaml/sso_index.php" class="btn btn-primary">Single Sign-On</a>

        </div>
        </br>
        <form action = "login.php" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name= "username" aria-describedby="emailHelp">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password">
            </div>
            
            <div class="d-grid gap-2 col-12 mx-auto">
              <button style="background-image: linear-gradient(to right, rgb(0, 238, 255), rgb(184, 22, 220)  ) ;" class="btn btn-primary" type="submit">Login</button>
            </div>
        </form> <br><br>
        <p class="text-center">Or Sign Up Using?</p>
        <div class="text-center">
          <div class="my-4">
          <a href="https://www.facebook.com/login"><img class="rounded-circle m-1" src="./img/facebook.png" style="height: 25px; width: 25px;"/></a>
          <a href="https://www.google.com/login"><img class="rounded-circle m-1" src="./img/Google.png" style="height: 25px; width: 25px;"/></a>
          <a href="https://www.twitter.com/login"><img class="rounded-circle m-1" src="./img/x.png" style="height: 25px; width: 25px;"/></a>
        </div>

        <br><br><br><br><br><br>


        <a style="text-decoration: none;" href="./Sign_up.php">New Here? Sign up </a>
        
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
  </body>
</html>