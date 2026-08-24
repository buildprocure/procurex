<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\Auth;
use App\Modules\Admin\Shipment\ShipmentModel;


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Auth::checkAdmin();

$model = new ShipmentModel();

$notice = null;
$error  = null;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_delivered') {
    $poId = (int) ($_POST['po_id'] ?? 0);
    try {
        if ($poId <= 0) {
            throw new Exception('Invalid purchase order.');
        }
        $updated = $model->markDelivered($poId, (int) ($_SESSION['user_id'] ?? 0));
        header('Location: po_shipment_tracking.php?' . ($updated ? 'marked=' . $poId : 'already_handled=' . $poId));
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$shippedPOs = $model->getShippedPOs();

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$courierTrackingBase = [
    'DHL'   => 'https://www.dhl.com/en/express/tracking.html?AWB=',
    'UPS'   => 'https://wwwapps.ups.com/WebTracking/track?track=yes&trackNums=',
    'USPS'  => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=',
    'FEDEX' => 'https://www.fedex.com/fedextrack/?tracknumbers=',
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Shipment Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bp-primary: #0d6efd;
            --bp-primary-dark: #0b5ed7;
        }
        .primary_button, .secondary_button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 7px 14px;
            border-radius: 8px;
            border: 1.5px solid transparent;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
        }
        .primary_button { background-color: var(--bp-primary); border-color: var(--bp-primary); color: #fff; }
        .primary_button:hover { background-color: var(--bp-primary-dark); border-color: var(--bp-primary-dark); color: #fff; }
        .secondary_button { background-color: #fff; border-color: var(--bp-primary); color: var(--bp-primary-dark); text-decoration: none; }
        .secondary_button:hover { background-color: #eaf2ff; }
        table { font-size: 0.9rem; }
    </style>
</head>
<body>

<?php require '../../header.php'; ?>

<div class="main-content">
<div class="container my-4">

    <h2>Shipment Tracking</h2>
    <p class="text-muted">
        Purchase orders currently marked shipped. Check the tracking link with the carrier,
        then mark the order delivered once it's actually arrived - that's what unlocks
        invoicing on the supplier's side.
    </p>

    <?php if (isset($_GET['marked'])): ?>
        <div class="alert alert-success">PO #<?= (int) $_GET['marked'] ?> marked as delivered.</div>
    <?php elseif (isset($_GET['already_handled'])): ?>
        <div class="alert alert-warning">PO #<?= (int) $_GET['already_handled'] ?> was already updated (no longer SHIPPED).</div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (empty($shippedPOs)): ?>
        <div class="alert alert-secondary">No purchase orders are currently awaiting delivery confirmation.</div>
    <?php else: ?>
        <table class="table table-hover mt-3">
            <thead>
                <tr>
                    <th>PO #</th>
                    <th>Buyer</th>
                    <th>Supplier</th>
                    <th>Total</th>
                    <th>Courier</th>
                    <th>Tracking</th>
                    <th>Shipped</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shippedPOs as $po): ?>
                    <?php
                        $trackingUrl = $po['tracking_url'] ?: (
                            isset($courierTrackingBase[$po['courier_company']])
                                ? $courierTrackingBase[$po['courier_company']] . urlencode((string) $po['tracking_number'])
                                : null
                        );
                    ?>
                    <tr>
                        <td>#<?= (int) $po['id'] ?></td>
                        <td><?= e($po['buyer_name']) ?></td>
                        <td><?= e($po['supplier_name']) ?></td>
                        <td>$<?= number_format((float) $po['total_amount'], 2) ?></td>
                        <td><?= e($po['delivery_method'] === 'COURIER' ? ($po['courier_company'] ?: '-') : ($po['delivery_method'] ?: '-')) ?></td>
                        <td>
                            <?php if ($po['tracking_number']): ?>
                                <?php if ($trackingUrl): ?>
                                    <a href="<?= e($trackingUrl) ?>" target="_blank" rel="noopener">
                                        <?= e($po['tracking_number']) ?>
                                    </a>
                                <?php else: ?>
                                    <?= e($po['tracking_number']) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= $po['shipping_date'] ? e(date('d M Y', strtotime($po['shipping_date']))) : '-' ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Confirm PO #<?= (int) $po['id'] ?> has actually been delivered?');">
                                <input type="hidden" name="action" value="mark_delivered">
                                <input type="hidden" name="po_id" value="<?= (int) $po['id'] ?>">
                                <button type="submit" class="primary_button">Mark Delivered</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>
</div>

<?php require '../../footer.php'; ?>
</body>
</html>
