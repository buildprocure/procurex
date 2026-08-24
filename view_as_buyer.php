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
    // Plain native <select> that submits itself the moment a buyer is
    // chosen - no separate "Switch" button, no plugin dependency. Only
    // offered when not already impersonating; the two states are mutually
    // exclusive in the UI.
    if ($_SESSION['role'] === 'Admin' && !$vpab->isImpersonating()):
        $buyers = $vpab->getAllBuyers();
        ?>
        <form method="POST" action="" class="view-as-buyer-form">
            <input type="hidden" name="switch_to_buyer" value="1">
            <select name="buyer_id" class="view-as-buyer-select" required
                    onchange="if (this.value) { this.form.submit(); }">
                <option value="">View as buyer&hellip;</option>
                <?php if (empty($buyers)): ?>
                    <option disabled>No buyers available</option>
                <?php else: foreach ($buyers as $buyer): ?>
                    <option value="<?php echo htmlspecialchars($buyer['id']); ?>">
                        <?php echo htmlspecialchars($buyer['username']); ?>
                    </option>
                <?php endforeach; endif; ?>
            </select>
        </form>
    <?php endif;

    if ($vpab->isImpersonating()): ?>
        <form method="POST" action="" class="viewing-as-pill">
            <span class="viewing-as-label">
                Viewing as <strong><?php echo htmlspecialchars($vpab->getCurrentUsername()); ?></strong>
            </span>
            <input type="hidden" name="restore_role" value="1">
            <button type="submit" class="viewing-as-exit">Exit</button>
        </form>
    <?php endif;
}

?>
