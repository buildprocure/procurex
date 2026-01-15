<?php
global $conn;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
//include '_dbconnection.php'; // Database connection
include_once '_config.php';
include 'VPAB.php';   // Include the VPAB class

// Initialize VPAB class
$vpab = new VPAB($conn, $_SESSION);

// Handle View as Buyer action
if (isset($_POST['switch_to_buyer'])) {
    $buyerId = $_POST['buyer_id'];
    if ($vpab->switchToBuyer($buyerId)) {
        header("Location:/Buyer/dashboard.php");
        exit();
    } else {
        echo "<script>alert('Invalid Buyer Selected!');</script>";
    }
}

// Handle Restore Admin Role action
if (isset($_POST['restore_role'])) {
    $vpab->restoreAdminRole();
    header("Location: ".SITE_URL."Admin/dashboard.php");
    exit();
}

// Function to Render the Dropdown Menu for Buyers
function renderViewAsBuyerDropdown($vpab) {
if ($_SESSION['role'] === 'Admin'): ?>
        <form method="POST" action="" style="display: flex; flex-direction: column; gap: 5px;">
            <select name="buyer_id" required>
                <option value="">View as Buyer</option>
                <?php foreach ($vpab->getAllBuyers() as $buyer): ?>
                    <option value="<?php echo htmlspecialchars($buyer['id']); ?>">
                        <?php echo htmlspecialchars($buyer['username']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="switch_to_buyer" value="1">
            
            <a href="javascript:void(0)" 
               class="js-switch-buyer" 
               style="width: 152px; background: #007bff; color: white; text-align: center; text-decoration: none; padding: 5px 0; display: inline-block;">
                Switch
            </a>
        </form>
    <?php endif;

    if ($vpab->isImpersonating()): ?>
    <div style="display: flex; align-items: center; gap: 10px; padding-top: 5px;">
        <form method="POST" action="">
            <input type="hidden" name="restore_role" value="1">
            <a href="javascript:void(0)" 
               class="js-restore-admin" 
               style="width: 152px; background: #ff6347; color: white; text-align: center; text-decoration: none; padding: 5px 0; display: inline-block;">
                Return to Admin
            </a>
        </form>
        <div style="width: 100%;">
            Viewing as <strong><?php echo htmlspecialchars($vpab->getCurrentUsername());?></strong>
        </div>
    </div>
    <?php endif;
}

?>
