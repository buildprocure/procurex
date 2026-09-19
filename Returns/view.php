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

$controller = new ReturnController();
$returnId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $returnId > 0) {
    try {
        ReturnController::assertCsrf($_POST['csrf'] ?? null);
        $action = (string) ($_POST['action'] ?? '');
        $comment = isset($_POST['comment']) ? trim((string) $_POST['comment']) : null;

        if ($action === 'approve') {
            $controller->approve($returnId, $comment);
            $flash = 'approved';
        } elseif ($action === 'reject') {
            $controller->reject($returnId, $comment);
            $flash = 'rejected';
        } elseif ($action === 'refund') {
            $status = $controller->confirmReceiptAndRefund($returnId);
            $flash = 'refund_' . strtolower($status);
        } else {
            throw new Exception('Unknown action.');
        }

        header('Location: view.php?id=' . $returnId . '&flash=' . $flash);
        exit;
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

$view = null;
try {
    if ($returnId <= 0) {
        throw new Exception('Return id is required.');
    }
    $view = $controller->getForViewer($returnId);
} catch (Throwable $ex) {
    $error = $error ?? $ex->getMessage();
}

$flashMessages = [
    'requested'        => ['success', 'Return request submitted. The supplier has been notified.'],
    'approved'         => ['success', 'Return approved. Confirm receipt of the goods to issue the refund.'],
    'rejected'         => ['secondary', 'Return rejected.'],
    'refund_refunded'  => ['success', 'Refund issued to the original payment method.'],
    'refund_refunding' => ['info', 'Refund submitted. It is still settling with the bank - this page will update when it completes.'],
    'refund_refund_failed' => ['danger', 'The refund failed. See the details below; you can retry.'],
];
$flash = $flashMessages[$_GET['flash'] ?? ''] ?? null;

$badge = [
    'REQUESTED' => 'warning text-dark', 'APPROVED' => 'info text-dark', 'REJECTED' => 'secondary',
    'REFUNDING' => 'info text-dark', 'REFUNDED' => 'success', 'REFUND_FAILED' => 'danger',
];
$r = $view['return'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Return</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../header.php'; ?>
<div class="main-content">
    <div class="container-fluid mt-4">
        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash[0]) ?>"><?= e($flash[1]) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($r): ?>
            <h2>
                Return #<?= (int) $r['id'] ?>
                <span class="badge bg-<?= e($badge[$r['status']] ?? 'secondary') ?> fs-6"><?= e(str_replace('_', ' ', $r['status'])) ?></span>
            </h2>
            <p class="text-muted">
                Invoice <?= e($r['invoice_number']) ?> &middot; <?= e($r['buyer_name']) ?> to <?= e($r['supplier_name']) ?>
                &middot; requested <?= e(date('d M Y H:i', strtotime($r['requested_at']))) ?>
            </p>

            <?php if (!empty($r['failure_message']) && $r['status'] === 'REFUND_FAILED'): ?>
                <div class="alert alert-danger">Refund failed: <?= e($r['failure_message']) ?></div>
            <?php endif; ?>

            <h5 class="mt-4">Reason</h5>
            <p><?= nl2br(e($r['reason'])) ?></p>
            <?php if (!empty($r['decision_comment'])): ?>
                <h5>Decision comment</h5>
                <p><?= nl2br(e($r['decision_comment'])) ?></p>
            <?php endif; ?>

            <h5 class="mt-4">Items</h5>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>Item</th><th>Unit</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Line total</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($r['items'] as $item): ?>
                        <tr>
                            <td><?= e($item['material_name']) ?></td>
                            <td><?= e($item['unit']) ?></td>
                            <td class="text-end"><?= e(rtrim(rtrim(number_format((float) $item['quantity'], 3, '.', ''), '0'), '.')) ?></td>
                            <td class="text-end">$<?= number_format((float) $item['unit_price'], 2) ?></td>
                            <td class="text-end">$<?= number_format((float) $item['line_total'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><th colspan="4" class="text-end">Refund value</th><th class="text-end">$<?= number_format((float) $r['refund_total'], 2) ?></th></tr>
                    </tfoot>
                </table>
            </div>

            <?php if ($view['can_decide']): ?>
                <form method="post" action="view.php" class="mb-4">
                    <input type="hidden" name="csrf" value="<?= e(ReturnController::csrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <label for="comment" class="form-label">Comment (required to reject)</label>
                    <textarea class="form-control mb-3" id="comment" name="comment" rows="2" maxlength="1000"></textarea>
                    <button type="submit" name="action" value="approve" class="btn btn-primary">Approve return</button>
                    <button type="submit" name="action" value="reject" class="btn btn-secondary"
                            onclick="return confirm('Reject this return?');">Reject</button>
                </form>
            <?php endif; ?>

            <?php if ($view['can_refund']): ?>
                <form method="post" action="view.php" class="mb-4"
                      onsubmit="return confirm('Confirm the goods were received and refund $<?= number_format((float) $r['refund_total'], 2) ?> to the buyer?');">
                    <input type="hidden" name="csrf" value="<?= e(ReturnController::csrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="action" value="refund">
                    <button type="submit" class="btn btn-primary">
                        <?= $r['status'] === 'REFUND_FAILED' ? 'Retry refund' : 'Confirm goods received &amp; refund' ?>
                    </button>
                </form>
            <?php endif; ?>

            <?php if (!empty($view['refunds'])): ?>
                <h5 class="mt-4">Refunds</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Amount</th><th>Status</th><th>Stripe refund</th><th>Note</th><th>Created</th></tr></thead>
                        <tbody>
                        <?php foreach ($view['refunds'] as $f): ?>
                            <tr>
                                <td>$<?= number_format((float) $f['amount'], 2) ?></td>
                                <td><?= e($f['status']) ?></td>
                                <td><?= e($f['stripe_refund_id'] ?? '') ?></td>
                                <td><?= e($f['failure_message'] ?? '') ?></td>
                                <td><?= e(date('d M Y H:i', strtotime($f['created_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h5 class="mt-4">History</h5>
            <ul class="list-group mb-4">
                <?php foreach ($view['events'] as $ev): ?>
                    <li class="list-group-item">
                        <strong><?= e(str_replace('_', ' ', $ev['action'])) ?></strong>
                        <?php if (!empty($ev['username'])): ?>by <?= e($ev['username']) ?> (<?= e($ev['actor_role']) ?>)<?php endif; ?>
                        <span class="text-muted">&middot; <?= e(date('d M Y H:i', strtotime($ev['created_at']))) ?></span>
                        <?php if (!empty($ev['comment'])): ?><div class="small"><?= e($ev['comment']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <a class="btn btn-secondary" href="list.php">Back to returns</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
