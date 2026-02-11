<?php

// ...existing code...
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\DB;
use App\Core\Auth;
use App\Modules\Buyer\RFQ\RFQController;

if (!isset($_GET['boq_id'])) {
    die("BOQ ID missing");
}

use App\Modules\Buyer\BOQ\BOQController;

$boqId = (int)$_GET['boq_id'];
$boqcontroller = new BOQController();
if(!$boqcontroller->checkownership($boqId, $_SESSION['username'])) {
    die("Unauthorized access to BOQ");
}
//fetch boq details for display
$boq = $boqcontroller->getBOQListByID($boqId);
if (empty($boq)) {
    die("BOQ not found");
}
//get project name for display
$projectName = $boqcontroller->getProjectNameByBOQId($boqId);


$controller = new RFQController();
//check access control - ensure the logged in buyer has access to this BOQ

//check if BOQ is locked before showing the form
if (!$controller->checkBOQLocked($boqId)) {
    die("BOQ must be locked before creating RFQ");
}
//handle post request to create RFQ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $controller->handle($_POST);
    } catch (Throwable $e) {
        die("Error creating RFQ: " . $e->getMessage()." in ".$e->getFile()." at line ".$e->getLine());
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Create RFQ</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background: #f5f7fb; }
        .card { border-radius: 12px; box-shadow: 0 6px 18px rgba(66,77,88,0.08); }
        .required::after { content: " *"; color: #d9534f; }
        .form-actions { display:flex; gap:.5rem; align-items:center; }
    </style>
</head>
<body>

<?php require '../../header.php'; ?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="mb-0">Create RFQ</h4>
                        <small class="">Project: <?= htmlspecialchars($projectName) ?> </small>
                        <small class="text d-block">BOQ ID: <?= htmlspecialchars($boqId) ?></small>
                    </div>
                </div>

                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="boq_id" value="<?= $boqId ?>">
                    <div class="mb-3">
                        <label class="form-label required" for="delivery_location">Delivery Location</label>
                        <input type="text" class="form-control" id="delivery_location" name="delivery_location" required>
                        <div class="invalid-feedback">Please enter delivery location.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required" for="required_delivery_date">Required Delivery Date</label>
                            <input type="date" class="form-control" id="required_delivery_date" name="required_delivery_date" required>
                            <div class="invalid-feedback">Please select required delivery date.</div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label required" for="quote_deadline">Quote Deadline</label>
                            <input type="date" class="form-control" id="quote_deadline" name="quote_deadline" required>
                            <div class="invalid-feedback">Please select quote deadline.</div>
                        </div>
                        <div class="mb-3">
                        <label class="form-label required" for="instructions">Delivery Notes/Instructions</label>
                        <textarea class="form-control" id="instructions" name="instructions" rows="3"></textarea>
                        </div>
                    </div>

                    <div class="form-actions mt-4">
                        <button type="submit" class="btn btn-primary">Create RFQ</button>
                        <a href="../boq_view.php?boq_id=<?= $boqId ?>" class="btn btn-outline-secondary">Cancel</a>
                        <div class="ms-auto text-muted small">Version: <?= htmlspecialchars($boq['version_no'] ?? 'N/A') ?></div>
                    </div>
                </form>
            </div>

            <div class="text-center mt-3">
                <a href="../boq_view.php?boq_id=<?= $boqId ?>" class="text-decoration-none">Back to BOQ</a>
            </div>
        </div>
    </div>
</div>

<?php require '../../footer.php'; ?>

<!-- Bootstrap JS (optional) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // simple client-side validation
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })();
</script>

</body>
</html>
