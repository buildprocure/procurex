<?php
include_once __DIR__ . '/_dbconnect.php';
include_once 'view_as_buyer.php';

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? '';
$isImpersonating = $vpab->isImpersonating();
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="//cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="../custom.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<!-- Top Navbar -->
<nav class="navbar navbar-expand-sm bg-dark navbar-dark sticky-top">
  <div class="container-fluid">
    <button class="bbtn btn-primary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sideMenu" aria-controls="sideMenu">
      <i class="fas fa-bars"></i>
    </button>
    <a class="navbar-brand" href="#" style="color: red; font-size: 1.8em;">ilifes</a>

    <div class="d-flex align-items-center ms-auto">
      <div class="me-3 text-white">
        <?php renderViewAsBuyerDropdown($vpab); ?>
      </div>

      <div class="dropdown">
        <button class="btn btn-secondary dropdown-toggle rounded-pill" type="button" data-bs-toggle="dropdown">
          <img src="<?php echo SITE_URL . 'img/user.png'; ?>" width="30" class="rounded-circle me-1">
          <?php echo htmlspecialchars($username); ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item">Hi <?php echo htmlspecialchars($username); ?></a></li>
          <li><a class="dropdown-item">As <?php echo htmlspecialchars($role); ?></a></li>
          <?php if ($isImpersonating): ?>
            <li>
              <form method="POST" action="" class="px-3 py-1">
                <button type="submit" name="restore_role" class="btn btn-danger w-100">Return to Admin</button>
              </form>
            </li>
          <?php endif; ?>
          <li><a class="dropdown-item" href="#">Settings</a></li>
          <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<!-- Offcanvas Vertical Nav -->
<div class="d-none d-lg-block bg-light position-fixed" style="width: 250px; height: 100vh; padding: 1rem; overflow-y: auto;">
  <div class="offcanvas-body d-flex flex-column gap-2">
    <a class="nav-link" href="./dashboard.php">Dashboard</a>

    <?php if ($role == 'Buyer' || $isImpersonating): ?>
      <div class="accordion" id="buyerAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingPO">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePO" aria-expanded="false">
              Purchase Order
            </button>
          </h2>
          <div id="collapsePO" class="accordion-collapse collapse" data-bs-parent="#buyerAccordion">
            <div class="accordion-body d-flex flex-column">
              <a href="../<?php echo htmlspecialchars($role) ?>/createPO.php">Create PO</a>
              <a href="../<?php echo htmlspecialchars($role) ?>/submittedPO.php">Submitted PO</a>
            </div>
          </div>
        </div>
      </div>
      <a class="nav-link" href="../<?php echo htmlspecialchars($role) ?>/timeSheet.php">Timesheet</a>
      <a class="nav-link" href="../<?php echo htmlspecialchars($role) ?>/receivedInvoice.php">Invoice</a>
      <a class="nav-link" href="../<?php echo htmlspecialchars($role) ?>/invoicePayment.php">Invoice Payment</a>
      <a class="nav-link" href="/modules/duplicate_payment/index.php">Duplicate Payment</a>
    <?php endif; ?>

    <?php if ($role == 'Supplier'): ?>
      <div class="accordion" id="supplierAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingTS">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTS" aria-expanded="false">
              Timesheet
            </button>
          </h2>
          <div id="collapseTS" class="accordion-collapse collapse" data-bs-parent="#supplierAccordion">
            <div class="accordion-body d-flex flex-column">
              <a href="../<?php echo htmlspecialchars($role) ?>/createTS.php">Create Timesheet</a>
              <a href="../<?php echo htmlspecialchars($role) ?>/submittedTS.php">Submitted TS</a>
            </div>
          </div>
        </div>
      </div>
      <a class="nav-link" href="../<?php echo htmlspecialchars($role) ?>/PO.php">Received PO</a>
      <div class="accordion" id="supplierInvoice">
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseInvoice">
              Invoice
            </button>
          </h2>
          <div id="collapseInvoice" class="accordion-collapse collapse">
            <div class="accordion-body d-flex flex-column">
              <a href="../<?php echo htmlspecialchars($role) ?>/createInvoice.php">Create Invoice</a>
              <a href="../<?php echo htmlspecialchars($role) ?>/submittedInvoice.php">Submitted Invoice</a>
            </div>
          </div>
        </div>
      </div>
      <a class="nav-link" href="../<?php echo htmlspecialchars($role) ?>/invoicePayment.php">Invoice Payment</a>
      <a class="nav-link" href="/modules/duplicate_payment/index.php">Duplicate Payment</a>
    <?php endif; ?>

    <?php if ($role == 'Admin'): ?>
      <div class="accordion" id="adminAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingAdminTS">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdminTS">
              Timesheet
            </button>
          </h2>
          <div id="collapseAdminTS" class="accordion-collapse collapse">
            <div class="accordion-body d-flex flex-column">
              <a href="../<?php echo htmlspecialchars($role) ?>/submittedTS.php">Submitted TS</a>
            </div>
          </div>
        </div>
      </div>
      <a class="nav-link" href="../<?php echo htmlspecialchars($role) ?>/PO.php">Received PO</a>
      <a class="nav-link" href="#invoice.php">Invoice</a>
      <a class="nav-link" href="../<?php echo htmlspecialchars($role) ?>/invoicePayment.php">Invoice Payment</a>
      <a class="nav-link" href="/modules/duplicate_payment/index.php">Duplicate Payment</a>
      <a href="/public/items-frontend/" target="_blank">
        <i class="fas fa-boxes"></i> <!-- Font Awesome icon for "items", adjust as needed -->
        <span>Items</span>
      </a>
    <?php endif; ?>
  </div>
</div>
