<?php
include './_dbconnect.php';
$insert = false;
$passerror = false;
$userError = false;
if($_SERVER['REQUEST_METHOD']=='POST'){
    $username = $_POST['username'];
    $password = $_POST["password"];
    $cpassword = $_POST["cpassword"];
    $role = $_POST['role'];
    
    $sql = "SELECT * FROM user WHERE username = '$username'";
    $result = mysqli_query($conn, $sql);
    $numexistrows = mysqli_num_rows($result);
    if($numexistrows>0){
        $userError = true;
    } else {
    if($password == $cpassword){
        $hash = password_hash($password, PASSWORD_DEFAULT);
      $sql = "INSERT INTO `user` (`username`, `password`, `Role`, `dt`, `user_enrollment`) VALUES ('$username', '$hash', '$role', current_timestamp(),'Not approved');";
      $result = mysqli_query($conn, $sql);
      if ($result){
        $insert = true;
      }
    } else {
      $passerror = true;
    }
  } 
}
?>


<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign Up</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
  </head>
  <body style="background-image: linear-gradient(to bottom right, rgb(0, 238, 255), rgb(184, 22, 220)  );    height: min-content;">
    <?php 
   // require  './_nav.php'; 
   /* echo '<nav class="navbar navbar-expand-lg navbar-dark bg-dark ">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">iNotes</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="./loggedinhome.php">Home</a>
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
          </nav>'; */
     
    if($insert){
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Success</strong> Your account has been successfully created and you can login.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    }
    if($passerror){
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error</strong> Your password do not match.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    }
    if($userError){
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error</strong> Username already exist. Please try another username.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    }
    ?>
    <div class="container my-5 p-4 col-md-3 " style="background-color: rgba(254, 251, 251, 0.825); height: 85vh; font-family: sans-serif ;">
    <br>
        <h2 class = "text-center">Sign Up</h2>
        <form action = "signup.php" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name= "username" aria-describedby="emailHelp">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password">
            </div>
            <div class="mb-3">
                <label for="cpassword" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="cpassword" name="cpassword">
                <div class ="form-text">Make sure to type the same password</div> 
            </div>
            <div class="mb-3">
              <select name = "role" class="form-select" aria-label="Default select example">
                Role
                <option value="admin">Admin</option>
                <option value="supplier">Supplier</option>
                <option value="buyer">Buyer</option>
              </select>
            </div>
            <button type="submit" class="btn btn-primary">Sign Up</button> <br><br><br><br><br><br><br>
            <div class>Already signed up? <a href="./login.php">Click here </a>to login.</div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
  </body>
</html>