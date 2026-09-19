<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Modules\Buyer\Returns\ReturnController;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$error = null;
$returns = [];
try {
    $returns = (new ReturnController())->listForCurrentUser();
} catch (Throwable $ex) {
    $error = $ex->getMessage();
}

$badge = [
    'REQUESTED'     => 'warning text-dark',
    'APPROVED'      => 'info text-dark',
    'REJECTED'      => 'secondary',
    'REFUNDING'     => 'info text-dark',
    'REFUNDED'      => 'success',
    'REFUND_FAILED' => 'danger',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Returns</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../header.php'; ?>
<div class="main-content">
    <div class="container-fluid mt-4">
        <h2>Returns &amp; Refunds</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php elseif (empty($returns)): ?>
            <div class="alert alert-secondary mt-4">No returns yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mt-4">
                    <thead>
                        <tr>
                            <th>Return #</th>
                            <th>Invoice</th>
                            <th>Buyer</th>
                            <th>Supplier</th>
                            <th>Refund Value</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($returns as $r): ?>
                        <tr>
                            <td><?= (int) $r['id'] ?></td>
                            <td><?= e($r['invoice_number']) ?></td>
                            <td><?= e($r['buyer_name']) ?></td>
                            <td><?= e($r['supplier_name']) ?></td>
                            <td>$<?= number_format((float) $r['refund_total'], 2) ?></td>
                            <td><span class="badge bg-<?= e($badge[$r['status']] ?? 'secondary') ?>"><?= e(str_replace('_', ' ', $r['status'])) ?></span></td>
                            <td><?= e(date('d M Y', strtotime($r['requested_at']))) ?></td>
                            <td><a class="btn btn-primary btn-sm" href="view.php?id=<?= (int) $r['id'] ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
