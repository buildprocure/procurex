<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Modules\Supplier\Invoice\InvoiceController;
use App\Modules\Supplier\Invoice\InvoiceRenderer;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$invoiceId = (int) ($_GET['invoice_id'] ?? 0);

$controller = new InvoiceController();
$error  = null;
$detail = null;

try {
    if ($invoiceId <= 0) {
        throw new Exception('invoice_id is required.');
    }
    $detail = $controller->getForBuyer($invoiceId);
} catch (Throwable $e) {
    $error = $e->getMessage();
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
            --bp-ink: #1f2937;
            --bp-muted: #6b7280;
        }
        body { background-color: #f5f7fb; }

        .secondary_button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-family: inherit;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 9px 18px;
            border-radius: 8px;
            border: 1.5px solid var(--bp-primary);
            background-color: #fff;
            color: var(--bp-primary-dark);
            text-decoration: none;
            cursor: pointer;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease;
        }
        .secondary_button:hover { background-color: var(--bp-primary-light); border-color: var(--bp-primary-dark); color: var(--bp-primary-darker); text-decoration: none; }

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

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
        }
    </style>
</head>
<body>

<?php if (!$error): require '../../header.php'; endif; ?>

<div class="main-content">
<div class="container my-4">

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php else: ?>

    <div class="rfq-page-header no-print">
        <h3>Invoice <?= e($detail['invoice']['invoice_number']) ?></h3>
        <p>From <?= e($detail['po']['supplier_name']) ?> for Purchase Order #<?= (int) $detail['po']['po_id'] ?></p>
    </div>

    <?= InvoiceRenderer::renderWebHtml($detail) ?>

    <div class="d-flex justify-content-end no-print">
        <button type="button" class="secondary_button" onclick="window.print()">Print / Save as PDF</button>
    </div>

<?php endif; ?>

</div>
</div>

<?php if (!$error): require '../../footer.php'; endif; ?>
</body>
</html>
