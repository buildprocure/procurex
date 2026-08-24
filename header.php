<?php
include_once __DIR__ . '/_dbconnect.php';
include_once 'view_as_buyer.php';
include_once __DIR__ . '/_config.php';

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? '';
$isImpersonating = $vpab->isImpersonating();
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="//cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>global_bp.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
    /* --- View as Buyer (admin impersonation) --- */
    .view-as-buyer-form { display: inline-block; }
    .view-as-buyer-select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-color: #fff;
        color: #0d6efd;
        border: 1.5px solid #0d6efd;
        border-radius: 8px;
        padding: 7px 30px 7px 12px;
        font-size: 0.85rem;
        font-weight: 600;
        line-height: 1.2;
        cursor: pointer;
        max-width: 180px;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%230d6efd'%3E%3Cpath d='M4.5 6l3.5 4 3.5-4z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 12px;
    }
    .view-as-buyer-select:hover { background-color: #eaf2ff; }
    .view-as-buyer-select:focus-visible { outline: none; box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.28); }

    .viewing-as-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #eaf2ff;
        border: 1px solid #cfe2ff;
        border-radius: 20px;
        padding: 5px 6px 5px 12px;
        font-size: 0.8rem;
        color: #0a4fb5;
        white-space: nowrap;
    }
    .viewing-as-label strong { color: #08306b; }
    .viewing-as-exit {
        background: #0d6efd;
        color: #fff;
        border: none;
        border-radius: 14px;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 4px 10px;
        cursor: pointer;
    }
    .viewing-as-exit:hover { background: #0b5ed7; }

    /* --- Mobile sidebar (hamburger toggle) --- */
    .nav-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        z-index: 1040;
    }
    .nav-backdrop.show { display: block; }

    @media (max-width: 991.98px) {
        .navbar-vertical {
            position: fixed !important;
            top: 0 !important;
            left: -280px !important;
            width: 260px !important;
            height: 100vh !important;
            background: #fff;
            z-index: 1045;
            overflow-y: auto;
            box-shadow: 2px 0 16px rgba(0, 0, 0, 0.15);
            transition: left 0.25s ease;
        }
        .navbar-vertical.mobile-open {
            left: 0 !important;
        }
    }
</style>

<!-- Top Navbar using custom.css-->
<nav class="navbar-horizontal">
    <div class="nav-container">
        <div class ="company-name">
          <button class="bbtn btn-primary d-lg-none" type="button" id="mobileNavToggle" aria-expanded="false" aria-controls="sideMenu">
            <i class="fas fa-bars"></i>
          </button>
          <a href="#">BuildProcure</a>
        </div>
        <div class="nav-actions">
          <?php if (isset($_SESSION['username'])): ?>
          <div class="me-3">
            <?php renderViewAsBuyerDropdown($vpab); ?>
          </div>
          <div class="user-dropdown">
              <i id="userDropdownToggle" class="fas fa-user-circle user-icon"></i>
              <ul id="userDropdownMenu" class="dropdown-menu">
                  <li class="dropdown-header"><?php echo htmlspecialchars($username); ?></li>
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



<!-- Mobile sidebar backdrop -->
<div class="nav-backdrop" id="navBackdrop"></div>

<!-- Offcanvas Vertical Nav -->
<div class="navbar-vertical" id="sideMenu">
  <a href="./dashboard.php" class="nav-link">Dashboard</a>

  <?php if ($role == 'Buyer' || $isImpersonating): ?>
    <div class="accordion-item">
      <button class="accordion-toggle" aria-expanded="false" aria-controls="boqMenu">BOQ</button>
      <div class="accordion-content" id="boqMenu" hidden>
        <a href="<?php echo BASE_URL; ?><?php echo htmlspecialchars($role) ?>/boq_upload.php" class="nav-link sub-link">BOQ Upload</a>
        <a href="<?php echo BASE_URL; ?><?php echo htmlspecialchars($role) ?>/boq_list.php" class="nav-link sub-link">BOQ List</a>
      </div>
    </div>
    <div class="accordion-item">
      <button class="accordion-toggle" aria-expanded="false" aria-controls="rfqMenu">RFQ</button>
      <div class="accordion-content" id="rfqMenu" hidden>        
        <a href="<?php echo BASE_URL; ?><?php echo htmlspecialchars($role) ?>/RFQ/rfq_list.php" class="nav-link sub-link">RFQ List</a>
      </div>
    </div>
    <div class="accordion-item">
      <button class="accordion-toggle" aria-expanded="false" aria-controls="poMenu">PO</button>
      <div class="accordion-content" id="poMenu" hidden>
        <a href="<?php echo BASE_URL; ?><?php echo htmlspecialchars($role) ?>/PO/po_list.php" class="nav-link sub-link">PO List</a>
      </div>
    </div>
    <div class="accordion-item">
      <button class="accordion-toggle" aria-expanded="false" aria-controls="invoiceMenu">Invoices</button>
      <div class="accordion-content" id="invoiceMenu" hidden>
        <a href="<?php echo BASE_URL; ?><?php echo htmlspecialchars($role) ?>/Invoice/invoice_list.php" class="nav-link sub-link">Invoice List</a>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($role == 'Supplier'): ?>
    <a href="<?php echo BASE_URL; ?><?php echo htmlspecialchars($role) ?>/rfq_list.php" class="nav-link">Quote Requests</a>
    <a href="<?php echo BASE_URL; ?><?php echo htmlspecialchars($role) ?>/po_list.php" class="nav-link">Purchase Orders</a>
    <a href="<?php echo BASE_URL; ?><?php echo htmlspecialchars($role) ?>/invoice_list.php" class="nav-link">Invoices</a>
  <?php endif; ?>

  <?php if ($role == 'Admin'): ?>
    <a href="<?php echo BASE_URL; ?>Admin/PO/po_shipment_tracking.php" class="nav-link">Shipment Tracking</a>
    <a href="<?php echo BASE_URL; ?>sftp/Onboarding.php" class="nav-link">SFTP Onboarding</a>
    <a href="<?php echo BASE_URL; ?>public/items-frontend/" target="_blank" class="nav-link">
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

// Mobile sidebar: hamburger opens/closes the drawer, tapping the backdrop
// or any nav link inside it closes it again (standard off-canvas UX).
document.addEventListener('DOMContentLoaded', () => {
  const menu = document.getElementById('sideMenu');
  const toggleBtn = document.getElementById('mobileNavToggle');
  const backdrop = document.getElementById('navBackdrop');
  if (!menu || !toggleBtn || !backdrop) return;

  const closeMenu = () => {
    menu.classList.remove('mobile-open');
    backdrop.classList.remove('show');
    toggleBtn.setAttribute('aria-expanded', 'false');
  };
  const openMenu = () => {
    menu.classList.add('mobile-open');
    backdrop.classList.add('show');
    toggleBtn.setAttribute('aria-expanded', 'true');
  };

  toggleBtn.addEventListener('click', () => {
    menu.classList.contains('mobile-open') ? closeMenu() : openMenu();
  });
  backdrop.addEventListener('click', closeMenu);
  menu.querySelectorAll('a.nav-link').forEach(link => link.addEventListener('click', closeMenu));
});
</script>

<!-- jQuery (used by chat widget) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

