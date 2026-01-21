<?php
include_once __DIR__ . '/_dbconnect.php';
include_once 'view_as_buyer.php';
include_once __DIR__ . '/_config.php';

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? '';
$isImpersonating = $vpab->isImpersonating();
?>
<!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"> -->
<link rel="stylesheet" href="//cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="../global_bp.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<!-- Top Navbar using custom.css-->
<nav class="navbar-horizontal">
    <div class="nav-container">
        <div class ="company-name">
          <button class="bbtn btn-primary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sideMenu" aria-controls="sideMenu">
            <i class="fas fa-bars"></i>
          </button>
          <a href="#">BuildProcure</a>
        </div>
        <div class="nav-actions">
          <?php if (isset($_SESSION['username'])): ?>
          <div class="me-3 text-white">
            <?php renderViewAsBuyerDropdown($vpab); ?>
          </div>
          <div class="user-dropdown">
              <i id="userDropdownToggle" class="fas fa-user-circle user-icon"></i>
              <ul id="userDropdownMenu" class="dropdown-menu">
                  <li><a href="#">Profile</a></li>
                  <li><a href="#">Settings</a></li>
                  <li><a href="/logout.php">Logout</a></li>
              </ul>
          </div>
          <?php else: ?>
              <a href="login.php" class="btn btn-primary btn-sm">Log In</a>
              <a href="Sign_up.php" class="btn btn-outline-primary btn-sm">Sign Up</a>
          <?php endif; ?>
        </div>
    </div>
</nav>



<!-- Offcanvas Vertical Nav -->
<div class="navbar-vertical">
  <a href="./dashboard.php" class="nav-link">Dashboard</a>

  <?php if ($role == 'Buyer' || $isImpersonating): ?>
    <div class="accordion-item">
      <button class="accordion-toggle" aria-expanded="false" aria-controls="poMenu">Purchase Order</button>
      <div class="accordion-content" id="poMenu" hidden>
        <a href="../<?php echo htmlspecialchars($role) ?>/createPO.php" class="nav-link sub-link">Create PO</a>
        <a href="../<?php echo htmlspecialchars($role) ?>/submittedPO.php" class="nav-link sub-link">Submitted PO</a>
      </div>
    </div>
    <a href="../<?php echo htmlspecialchars($role) ?>/timeSheet.php" class="nav-link">Timesheet</a>
    <a href="../<?php echo htmlspecialchars($role) ?>/receivedInvoice.php" class="nav-link">Invoice</a>
    <a href="../<?php echo htmlspecialchars($role) ?>/invoicePayment.php" class="nav-link">Invoice Payment</a>
    <a href="/modules/duplicate_payment/index.php" class="nav-link">Duplicate Payment</a>
    <a href="../<?php echo htmlspecialchars($role) ?>/boq_upload.php" class="nav-link">BOQ Upload</a>
  <?php endif; ?>

  <?php if ($role == 'Supplier'): ?>
    <div class="accordion-item">
      <button class="accordion-toggle" aria-expanded="false" aria-controls="tsMenu">Timesheet</button>
      <div class="accordion-content" id="tsMenu" hidden>
        <a href="../<?php echo htmlspecialchars($role) ?>/createTS.php" class="nav-link sub-link">Create Timesheet</a>
        <a href="../<?php echo htmlspecialchars($role) ?>/submittedTS.php" class="nav-link sub-link">Submitted TS</a>
      </div>
    </div>
    <a href="../<?php echo htmlspecialchars($role) ?>/PO.php" class="nav-link">Received PO</a>
    <div class="accordion-item">
      <button class="accordion-toggle" aria-expanded="false" aria-controls="invoiceMenu">Invoice</button>
      <div class="accordion-content" id="invoiceMenu" hidden>
        <a href="../<?php echo htmlspecialchars($role) ?>/createInvoice.php" class="nav-link sub-link">Create Invoice</a>
        <a href="../<?php echo htmlspecialchars($role) ?>/submittedInvoice.php" class="nav-link sub-link">Submitted Invoice</a>
      </div>
    </div>
    <a href="../<?php echo htmlspecialchars($role) ?>/invoicePayment.php" class="nav-link">Invoice Payment</a>
    <a href="/modules/duplicate_payment/index.php" class="nav-link">Duplicate Payment</a>
  <?php endif; ?>

  <?php if ($role == 'Admin'): ?>
    <div class="accordion-item">
      <button class="accordion-toggle" aria-expanded="false" aria-controls="adminTSMenu">Timesheet</button>
      <div class="accordion-content" id="adminTSMenu" hidden>
        <a href="../<?php echo htmlspecialchars($role) ?>/submittedTS.php" class="nav-link sub-link">Submitted TS</a>
      </div>
    </div>
    <a href="../<?php echo htmlspecialchars($role) ?>/PO.php" class="nav-link">Received PO</a>
    <a href="#invoice.php" class="nav-link">Invoice</a>
    <a href="../<?php echo htmlspecialchars($role) ?>/invoicePayment.php" class="nav-link">Invoice Payment</a>
    <a href="/modules/duplicate_payment/index.php" class="nav-link">Duplicate Payment</a>
    <a href="<?php echo BASE_URL; ?>sftp/Onboarding.php" class="nav-link">SFTP Onboarding</a>
    <a href="/public/items-frontend/" target="_blank" class="nav-link">
      <i class="fas fa-boxes"></i> Items
    </a>
  <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const toggles = document.querySelectorAll('.navbar-vertical .accordion-toggle');
  toggles.forEach(toggle => {
    toggle.addEventListener('click', () => {
      const contentId = toggle.getAttribute('aria-controls');
      const content = document.getElementById(contentId);
      if (!content) return;

      if (content.hasAttribute('hidden')) {
        content.removeAttribute('hidden');
        toggle.setAttribute('aria-expanded', 'true');
      } else {
        content.setAttribute('hidden', '');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  });
});

document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.getElementById('userDropdownToggle');
            const menu = document.getElementById('userDropdownMenu');

            toggle.addEventListener('click', function(e) {
                console.log('Dropdown toggle clicked');
                e.stopPropagation(); // Prevent click from bubbling up
                menu.classList.toggle('show');
            });

            // Close dropdown if clicking outside
            document.addEventListener('click', function() {
                menu.classList.remove('show');
            });
        });
</script>

