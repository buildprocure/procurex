<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Modules\Supplier\Invoice\InvoiceController;
use App\Modules\Supplier\Invoice\InvoiceRenderer;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Auth::checkSupplier();

$poId      = (int) ($_POST['po_id'] ?? $_GET['po_id'] ?? 0);
$invoiceId = (int) ($_POST['invoice_id'] ?? $_GET['invoice_id'] ?? 0);

$controller = new InvoiceController();
$error   = null;
$notice  = null;
$detail  = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'generate' && $poId > 0) {
            $invoiceId = $controller->generate($poId);
        } elseif ($action === 'send' && $invoiceId > 0) {
            $sent = $controller->send($invoiceId);
            $notice = $sent
                ? 'Invoice sent to the buyer.'
                : 'Invoice could not be emailed right now - it stays generated, you can retry sending it.';
        }
    }

    if ($invoiceId > 0) {
        $detail = $controller->getForSupplier($invoiceId);
        $poId = (int) $detail['po']['po_id'];
    } elseif ($poId > 0) {
        $existing = (new \App\Modules\Supplier\Invoice\InvoiceModel())->getInvoiceByPO($poId);
        if ($existing) {
            $detail = $controller->getForSupplier((int) $existing['id']);
        }
    } else {
        throw new Exception('po_id or invoice_id is required.');
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

// If no invoice exists yet, still need the PO itself to show the "Generate" panel.
$po = null;
if (!$detail && $poId > 0 && !$error) {
    $po = (new \App\Modules\Supplier\Invoice\InvoiceModel())->getPurchaseOrder($poId);
}

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Invoice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bp-primary: #0d6efd;
            --bp-primary-dark: #0b5ed7;
            --bp-primary-darker: #0a4fb5;
            --bp-primary-light: #eaf2ff;
            --bp-primary-border: #cfe2ff;
            --bp-danger: #dc3545;
            --bp-ink: #1f2937;
            --bp-muted: #6b7280;
        }
        body { background-color: #f5f7fb; }

        .primary_button, .secondary_button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-family: inherit;
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.2;
            padding: 9px 18px;
            border-radius: 8px;
            border: 1.5px solid transparent;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
            transition: background-color .15s ease, border-color .15s ease,
                        color .15s ease, box-shadow .15s ease, transform .05s ease;
        }
        .primary_button:hover, .secondary_button:hover { text-decoration: none; }
        .primary_button:focus-visible, .secondary_button:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.28);
        }
        .primary_button:active, .secondary_button:active { transform: translateY(1px); }
        .primary_button {
            background-color: var(--bp-primary);
            border-color: var(--bp-primary);
            color: #fff;
            box-shadow: 0 1px 2px rgba(13, 110, 253, 0.18);
        }
        .primary_button:hover  { background-color: var(--bp-primary-dark); border-color: var(--bp-primary-dark); color: #fff; }
        .primary_button:active { background-color: var(--bp-primary-darker); border-color: var(--bp-primary-darker); box-shadow: none; }
        .primary_button:disabled { background-color: #a9cbfb; border-color: #a9cbfb; color: #fff; cursor: not-allowed; }
        .secondary_button {
            background-color: #fff;
            border-color: var(--bp-primary);
            color: var(--bp-primary-dark);
        }
        .secondary_button:hover { background-color: var(--bp-primary-light); border-color: var(--bp-primary-dark); color: var(--bp-primary-darker); }

        .rfq-page-header { text-align: center; padding: 28px 16px 20px; }
        .rfq-page-header h3 { font-weight: 700; color: var(--bp-ink); margin-bottom: 6px; }
        .rfq-page-header p { color: var(--bp-muted); max-width: 640px; margin: 0 auto; }

        .invoice-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04), 0 4px 16px rgba(16, 24, 40, 0.06);
            padding: 24px 28px;
            margin-bottom: 20px;
        }
        .invoice-head {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            border-bottom: 1px solid #eef1f6;
            padding-bottom: 18px;
            margin-bottom: 18px;
        }
        .invoice-eyebrow {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--bp-primary-dark);
            font-weight: 700;
        }
        .invoice-head h3 { margin: 4px 0 6px; font-weight: 700; }
        .invoice-meta { font-size: 0.85rem; color: var(--bp-muted); }
        .invoice-parties { display: flex; gap: 32px; }
        .party-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--bp-muted);
            font-weight: 700;
            margin-bottom: 4px;
        }
        .party-name { font-weight: 700; color: var(--bp-ink); }
        .party-detail { font-size: 0.82rem; color: var(--bp-muted); }

        .invoice-items-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .invoice-items-table th, .invoice-items-table td { padding: 10px 8px; border-bottom: 1px solid #eef1f6; }
        .invoice-items-table thead th {
            background: var(--bp-primary-light);
            color: var(--bp-primary-darker);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .invoice-items-table tfoot th { border-top: 2px solid var(--bp-primary-border); font-size: 1rem; }
        .text-end { text-align: right; }

        .invoice-shipment {
            margin-top: 16px;
            font-size: 0.85rem;
            color: var(--bp-muted);
            background: #f9fafb;
            border-radius: 8px;
            padding: 10px 14px;
        }
        .invoice-sent-note {
            margin-top: 14px;
            font-size: 0.82rem;
            color: #198754;
            background: #e6f6ec;
            border-radius: 8px;
            padding: 8px 14px;
            display: inline-block;
        }
        .invoice-actions { display: flex; gap: 10px; justify-content: flex-end; }
    </style>
</head>
<body>

<?php require '../header.php'; ?>

<div class="main-content">
<div class="container my-4">

<div class="rfq-page-header">
    <h3>Invoice</h3>
    <p>Generate an invoice for an accepted, shipped purchase order, then send it to the buyer.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($notice): ?>
    <div class="alert alert-success"><?= e($notice) ?></div>
<?php endif; ?>

<?php if ($detail): ?>

    <?= InvoiceRenderer::renderWebHtml($detail) ?>

    <?php if ($detail['invoice']['status'] !== 'SENT'): ?>
        <div class="invoice-actions">
            <form method="POST">
                <input type="hidden" name="action" value="send">
                <input type="hidden" name="invoice_id" value="<?= (int) $detail['invoice']['id'] ?>">
                <button type="submit" class="primary_button">Send to Customer</button>
            </form>
        </div>
    <?php else: ?>
        <div class="invoice-actions">
            <form method="POST">
                <input type="hidden" name="action" value="send">
                <input type="hidden" name="invoice_id" value="<?= (int) $detail['invoice']['id'] ?>">
                <button type="submit" class="secondary_button">Resend to Customer</button>
            </form>
        </div>
    <?php endif; ?>

<?php elseif ($po && !$error): ?>

    <div class="invoice-card">
        <p>
            Purchase Order #<?= (int) $po['id'] ?> is
            <strong><?= e($po['supplier_response']) ?></strong>
            with status <strong><?= e($po['status']) ?></strong>.
        </p>

        <?php
            $eligible = $po['supplier_response'] === 'ACCEPTED'
                && in_array($po['status'], ['SHIPPED', 'DELIVERED'], true);
        ?>

        <?php if ($eligible): ?>
            <form method="POST">
                <input type="hidden" name="action" value="generate">
                <input type="hidden" name="po_id" value="<?= (int) $po['id'] ?>">
                <button type="submit" class="primary_button">Generate Invoice</button>
            </form>
        <?php else: ?>
            <div class="alert alert-warning mb-0">
                An invoice can only be generated once this PO is accepted and marked shipped or delivered.
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

</div>
</div>

<?php require '../footer.php'; ?>
</body>
</html>
