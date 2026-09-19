<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../ilife/custom.css">
</head>
<body>
<?php
    if(isset($_SESSION['loggedin'])|| $_SESSION['loggedin'] == true){
        if($_SESSION['role'] == "Admin"){
            $role = "Admin";
        }elseif($_SESSION['role'] == "Buyer"){
            $role = "Buyer";
        } else {
            $role = "Supplier";
        }
        
            $hnav = '<div class="navbar-horizontal">
                        <div class="company-name">Company Name</div>
                        <div class="user-dropdown">
                            <button class="dropdown-button">
                                <img src="../img/user.png" style="width: 15px;" class="rounded-pill">
                            </button>
                            <div class="dropdown-menu">
                                <a href="#profile">Profile</a>
                                <a href="#settings">Settings</a>
                                <a href="./logout.php">Logout</a>
                            </div>
                        </div>
                    </div>';
    } 
    else {
        $hnav = '<div class="navbar-horizontal">
            <div class="company-name">Company Name</div>
            <button class="btn btn-primary px-3 py-0 login-button" >
                <a style="font-size: 1.2em;" class="nav-link" href="./login.php">Login</a>
            </button>
        </div>';
    }
?>
</body>
</html>