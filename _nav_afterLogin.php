<?php 
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
include_once __DIR__ . '/_dbconnect.php';
include 'view_as_buyer.php'; ?>
<link rel="stylesheet" href="<?php echo SITE_URL.'custom.css';?>">
<div class="navbar-horizontal">
    <div class="company-name" style="color: red; font-family: Verdana, Geneva, Tahoma, sans-serif; font-size: 1.8em;">
        ilife
    </div>

    <!-- View as Buyer Dropdown (Left of User Icon) -->
    <div>
        <div class="view-as-buyer">
            <?php renderViewAsBuyerDropdown($vpab); ?>
        </div>
        <!-- User Icon Dropdown -->
        <div class="user-dropdown" style="display: inline-block;">
            <button class="dropdown-button">
                <img src="<?php echo SITE_URL.'img/user.png';?>" style="width: 30px;" class="rounded-pill">
            </button>
            <div class="dropdown-menu">
                <a href="#">Hi <?php echo $_SESSION['username']; ?></a>
                <a href="#">As <?php echo $_SESSION['role']; ?></a>

                <?php if ($vpab->isImpersonating()): ?>
                    <!-- Return to Admin -->
                    <form method="POST" action="">
                        <button type="submit" name="restore_role" style="width: 100%; background: #ff6347; color: white; cursor: pointer;">
                            Return to Admin
                        </button>
                    </form>
                <?php endif; ?>

                <a href="#settings">Settings</a>
                <a href="../logout.php">Logout</a>
            </div>
        </div>
    </div>
</div>
