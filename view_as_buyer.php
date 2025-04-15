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
        <form method="POST" action="" style="padding: 5px;">
            <select name="buyer_id" style="width: 152px; margin-bottom: 5px;" required>
                <option value="">View as Buyer</option>
                <?php foreach ($vpab->getAllBuyers() as $buyer): ?>
                    <option id ='SN' value="<?php echo $buyer['SN']; ?>">
                        <?php echo htmlspecialchars($buyer['username']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="switch_to_buyer" style="width: 100%; cursor: pointer;">
                Switch
            </button>
        </form>
    <?php endif;

    if ($vpab->isImpersonating()): ?>
    <div style="display: flex; align-items: center; gap: 10px; padding-top: 5px;">
        <form method="POST" action="">
            <button type="submit" name="restore_role" style="width: 152px; background: #ff6347; color: white; cursor: pointer;">
                Return to Admin
            </button>
        </form>
        <div type="submit" style="width: 100%; cursor: pointer;">
                Viewing as <?php echo htmlspecialchars($vpab->getCurrentUsername());?>
        </div>
    </div>
    <?php endif;
}
?>
