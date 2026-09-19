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
$invoiceId = (int) ($_GET['invoice_id'] ?? $_POST['invoice_id'] ?? 0);
$error = null;
$data = null;

try {
    if ($invoiceId <= 0) {
        throw new Exception('invoice_id is required.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ReturnController::assertCsrf($_POST['csrf'] ?? null);
        $quantities = is_array($_POST['qty'] ?? null) ? $_POST['qty'] : [];
        $returnId = $controller->requestReturn($invoiceId, $quantities, (string) ($_POST['reason'] ?? ''));
        header('Location: view.php?id=' . $returnId . '&flash=requested');
        exit;
    }
} catch (Throwable $ex) {
    $error = $ex->getMessage();
}

try {
    if ($invoiceId > 0) {
        $data = $controller->getRequestFormData($invoiceId);
    }
} catch (Throwable $ex) {
    $error = $error ?? $ex->getMessage();
}

$posted = is_array($_POST['qty'] ?? null) ? $_POST['qty'] : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Request a Return</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/../header.php'; ?>
<div class="main-content">
    <div class="container-fluid mt-4">
        <h2>Request a Return</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($data): ?>
            <p class="text-muted">
                Invoice <?= e($data['detail']['invoice']['invoice_number']) ?> from <?= e($data['detail']['po']['supplier_name']) ?>.
                Refundable so far: <strong>$<?= number_format($data['refundable'], 2) ?></strong>
                (refunds go back to the original payment method).
            </p>

            <?php if (!$data['can_return']): ?>
                <div class="alert alert-warning">
                    Returns are only available for delivered orders that have been paid.
                </div>
            <?php else: ?>
                <form method="post" action="request.php">
                    <input type="hidden" name="csrf" value="<?= e(ReturnController::csrfToken()) ?>">
                    <input type="hidden" name="invoice_id" value="<?= (int) $invoiceId ?>">

                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Unit</th>
                                    <th class="text-end">Ordered</th>
                                    <th class="text-end">Already returned</th>
                                    <th class="text-end">Unit price</th>
                                    <th style="width: 140px;">Return qty</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($data['lines'] as $line):
                                $max = max(0, (float) $line['quantity'] - (float) $line['returned_quantity']); ?>
                                <tr>
                                    <td>
                                        <?= e($line['material_name']) ?>
                                        <?php if (!empty($line['specification'])): ?>
                                            <div class="small text-muted"><?= e($line['specification']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($line['unit']) ?></td>
                                    <td class="text-end"><?= e(rtrim(rtrim(number_format((float) $line['quantity'], 3, '.', ''), '0'), '.')) ?></td>
                                    <td class="text-end"><?= e(rtrim(rtrim(number_format((float) $line['returned_quantity'], 3, '.', ''), '0'), '.')) ?></td>
                                    <td class="text-end">$<?= number_format((float) $line['unit_price'], 2) ?></td>
                                    <td>
                                        <input type="number" class="form-control" name="qty[<?= (int) $line['id'] ?>]"
                                               min="0" max="<?= e(number_format($max, 3, '.', '')) ?>" step="any"
                                               value="<?= e($posted[$line['id']] ?? '') ?>"
                                               <?= $max <= 0 ? 'disabled' : '' ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for return</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" maxlength="2000" required><?= e($_POST['reason'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Submit return request</button>
                    <a class="btn btn-secondary" href="../Buyer/Invoice/invoice_view.php?invoice_id=<?= (int) $invoiceId ?>">Cancel</a>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
