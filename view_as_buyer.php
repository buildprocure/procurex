<?php
global $conn;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
//include '_dbconnection.php'; // Database connection
include_once '_config.php';
include 'VPAB.php';   // Include the VPAB class

// Bootstrap Selectpicker CDN Links (for header inclusion)
define('SELECTPICKER_CSS', 'https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.18/dist/css/bootstrap-select.min.css');
define('SELECTPICKER_JS', 'https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.18/dist/js/bootstrap-select.min.js');

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
        <form method="POST" action="" id="buyerSwitchForm" style="display: flex; flex-direction: column; gap: 10px;">
            <div style="position: relative; z-index: 1050;">
                <select name="buyer_id" id="buyerSelect" class="selectpicker" data-style="btn-primary" data-width="100%" required data-live-search="true" data-placeholder="Select a Buyer">
                    <option value="">-- Select Buyer --</option>
                    <?php 
                    $buyers = $vpab->getAllBuyers();
                    if (empty($buyers)) {
                        echo '<option disabled>No buyers available</option>';
                    } else {
                        foreach ($buyers as $buyer): ?>
                            <option value="<?php echo htmlspecialchars($buyer['id']); ?>">
                                <?php echo htmlspecialchars($buyer['username']); ?>
                            </option>
                        <?php endforeach;
                    }
                    ?>
                </select>
            </div>
            <input type="hidden" name="switch_to_buyer" value="1">
            
            <button type="button" 
                    class="js-switch-buyer" 
                    style="width: 100%; background: #007bff; color: white; text-align: center; text-decoration: none; padding: 8px 0; display: inline-block; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">
                Switch
            </button>
        </form>
    <?php endif;

    if ($vpab->isImpersonating()): ?>
    <div style="display: flex; align-items: center; gap: 10px; padding-top: 5px;">
        <form method="POST" action="">
            <input type="hidden" name="restore_role" value="1">
            <a href="#"
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
