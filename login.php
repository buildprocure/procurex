<?php
ob_start();
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
//require_once('simplesaml/lib/_autoload.php');

include '_dbconnect.php';
$timeout = $_GET['timeout'] ?? false;
//add condition to check weather session is active or not
if(isset($_SESSION['loggedin']) && $_SESSION['loggedin']==true){
  if($_SESSION['role']=='Admin'){
    header("location:../Admin/loggedinhome.php");
  } 
  if(($_SESSION['role']=='Buyer')){
    header("location:../Buyer/loggedinhome.php");
  }elseif(($_SESSION['role']=='Supplier')){
    header("location:../Supplier/loggedinhome.php");
  }
  exit();
}else{
$login = false;
$showerror = false;
$notapproved = false;

if($_SERVER['REQUEST_METHOD']=='POST'){
  $username = $_POST['username'];
  $password = $_POST["password"];
  
  
  $sql = "SELECT * FROM user WHERE username= '$username'";
  $result = mysqli_query($conn, $sql);
  $num = mysqli_num_rows($result);
  if ($num == 1){
    while($rows = mysqli_fetch_assoc($result)){

      if($rows['user_enrollment']=='Not approved' && $rows['Role']=='Buyer'){
        $notapproved = true;
      } else {

      if(password_verify($password, $rows['password'])){
        $login = true;
        session_set_cookie_params(604800);
        session_start();
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['has_duplicate_payment_access'] = $rows['as_duplicate_payment_access'];
        $sql = "SELECT Role FROM user WHERE username= '$username'";
        $result = mysqli_query($conn, $sql);
        while($role = mysqli_fetch_assoc($result)){
          $_SESSION['role'] = $role['Role'];
        }
        if($_SESSION['role']=='Admin'){
        header("location:../Admin/loggedinhome.php");
      } 
      if(($_SESSION['role']=='Buyer')){
        header("location:../Buyer/loggedinhome.php");
      }elseif(($_SESSION['role']=='Supplier')){
        header("location:../Supplier/loggedinhome.php");
      }
      } else {
        $showerror = true;

      }
    }
  }
} 
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
          <form method="Get" action="/simplesaml/index.php">
          <!-- Single Sign-On Button -->
          <button type="submit" name="sso_login">Single Sign-On</button>
        </form>
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