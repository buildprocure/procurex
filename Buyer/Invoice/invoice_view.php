<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Modules\Supplier\Invoice\InvoiceController;
use App\Modules\Supplier\Invoice\InvoiceRenderer;
use App\Modules\Buyer\Payment\PaymentController;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$controller        = new InvoiceController();
$paymentController = new PaymentController();

$error = null;
$invoiceId = (int) ($_GET['invoice_id'] ?? 0);

// Stripe redirects the buyer's browser back here after confirmPayment()
// with ?payment_intent=... - resolve it once, then Post/Redirect/Get to a
// clean URL so a page refresh never re-processes the same redirect.
if (isset($_GET['payment_intent']) && $invoiceId > 0) {
    try {
        $result = $paymentController->confirmFromReturn((string) $_GET['payment_intent']);
        $status = $result['status'] ?? 'UNKNOWN';
        $flag = $status === 'SUCCEEDED' ? 'succeeded' : ($status === 'PROCESSING' ? 'processing' : 'failed');
        header('Location: invoice_view.php?invoice_id=' . $invoiceId . '&payment=' . $flag);
        exit;
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

$detail = null;

if (!$error) {
    try {
        if ($invoiceId <= 0) {
            throw new Exception('invoice_id is required.');
        }
        $detail = $controller->getForBuyer($invoiceId);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$payments  = [];
$totalPaid = 0.0;
$balance   = 0.0;

if ($detail) {
    $payments  = $paymentController->getPayments($invoiceId);
    $totalPaid = $paymentController->getTotalPaid($invoiceId);
    $balance   = max(0.0, (float) $detail['invoice']['total_amount'] - $totalPaid);
}

$paymentMethodLabels = [
    'BANK_TRANSFER' => 'Bank Transfer',
    'CARD'          => 'Card',
];

$paymentFlag = $_GET['payment'] ?? null;
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
            transition: background-color .15s ease, border-color .15s ease, color .15s ease;
        }
        .primary_button:hover, .secondary_button:hover { text-decoration: none; }
        .primary_button {
            background-color: var(--bp-primary);
            border-color: var(--bp-primary);
            color: #fff;
            box-shadow: 0 1px 2px rgba(13, 110, 253, 0.18);
        }
        .primary_button:hover { background-color: var(--bp-primary-dark); border-color: var(--bp-primary-dark); color: #fff; }
        .primary_button:disabled { background-color: #a9cbfb; border-color: #a9cbfb; color: #fff; cursor: not-allowed; }
        .secondary_button {
            background-color: #fff;
            border-color: var(--bp-primary);
            color: var(--bp-primary-dark);
        }
        .secondary_button:hover { background-color: var(--bp-primary-light); border-color: var(--bp-primary-dark); color: var(--bp-primary-darker); }

        .rfq-page-header { position: relative; text-align: center; padding: 28px 16px 20px; }
        .rfq-page-header h3 { font-weight: 700; color: var(--bp-ink); margin-bottom: 6px; }
        .rfq-page-header p { color: var(--bp-muted); max-width: 640px; margin: 0 auto; }
        .page-print-btn { position: absolute; top: 20px; right: 16px; }

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

        .payment-summary { display: flex; gap: 32px; flex-wrap: wrap; margin-bottom: 18px; }
        .payment-summary-item .party-label { margin-bottom: 2px; }
        .payment-summary-item .amount { font-size: 1.2rem; font-weight: 700; color: var(--bp-ink); }
        .payment-summary-item .amount.paid-in-full { color: #198754; }
        .payment-summary-item .amount.balance-due { color: #b02a37; }

        .payment-form-row { display: flex; gap: 14px; flex-wrap: wrap; align-items: flex-end; }
        .payment-form-row .form-group { flex: 1 1 160px; }
        .payment-form-row label { font-size: 0.78rem; font-weight: 600; color: var(--bp-muted); margin-bottom: 4px; display: block; }

        #payment-element { padding: 4px 2px; }

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
        <button type="button" class="secondary_button page-print-btn" onclick="window.print()">Print / Save as PDF</button>
        <h3>Invoice <?= e($detail['invoice']['invoice_number']) ?></h3>
        <p>From <?= e($detail['po']['supplier_name']) ?> for Purchase Order #<?= (int) $detail['po']['po_id'] ?></p>
    </div>

    <?php if ($paymentFlag === 'succeeded'): ?>
        <div class="alert alert-success no-print">Payment successful. Thank you!</div>
    <?php elseif ($paymentFlag === 'processing'): ?>
        <div class="alert alert-warning no-print">Your payment is processing. Bank transfers can take a few business days to clear - this page will update once it settles.</div>
    <?php elseif ($paymentFlag === 'failed'): ?>
        <div class="alert alert-danger no-print">Your payment could not be completed. Please try again.</div>
    <?php endif; ?>

    <?= InvoiceRenderer::renderWebHtml($detail) ?>

    <?php if ($balance > 0): ?>
        <div class="d-flex justify-content-end no-print mb-4">
            <button type="button" class="primary_button" id="payToggleBtn" onclick="togglePaymentForm()">Pay</button>
        </div>
    <?php endif; ?>

    <div class="invoice-card no-print">
        <div class="invoice-eyebrow mb-2">Payments</div>

        <div class="payment-summary">
            <div class="payment-summary-item">
                <div class="party-label">Invoice Total</div>
                <div class="amount">$<?= number_format((float) $detail['invoice']['total_amount'], 2) ?></div>
            </div>
            <div class="payment-summary-item">
                <div class="party-label">Total Paid</div>
                <div class="amount">$<?= number_format($totalPaid, 2) ?></div>
            </div>
            <div class="payment-summary-item">
                <div class="party-label">Balance Due</div>
                <div class="amount <?= $balance <= 0 ? 'paid-in-full' : 'balance-due' ?>">
                    $<?= number_format($balance, 2) ?>
                </div>
            </div>
            <div class="payment-summary-item">
                <div class="party-label">Status</div>
                <?php
                    $payStatus = $detail['invoice']['payment_status'] ?? 'UNPAID';
                    $payBadge  = $payStatus === 'PAID' ? 'bg-success' : ($payStatus === 'PARTIALLY_PAID' ? 'bg-warning text-dark' : 'bg-secondary');
                ?>
                <span class="badge <?= $payBadge ?>"><?= e(str_replace('_', ' ', $payStatus)) ?></span>
            </div>
        </div>

        <?php if (!empty($payments)): ?>
            <table class="invoice-items-table mb-3">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Details</th>
                        <th>Status</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                        <?php
                            if ($p['payment_method'] === 'CARD') {
                                $paymentDetails = $p['card_last4']
                                    ? 'Card ****' . e($p['card_last4']) . ' · exp ' . e($p['card_expiry'])
                                    : '-';
                            } elseif ($p['payment_method'] === 'BANK_TRANSFER') {
                                $paymentDetails = $p['bank_last4']
                                    ? e($p['bank_account_name']) . ' ****' . e($p['bank_last4'])
                                    : '-';
                            } else {
                                $paymentDetails = '-';
                            }

                            $statusBadge = match ($p['status']) {
                                'SUCCEEDED' => 'bg-success',
                                'PENDING'   => 'bg-warning text-dark',
                                'FAILED'    => 'bg-danger',
                                default     => 'bg-secondary',
                            };
                        ?>
                        <tr>
                            <td><?= e(date('d M Y', strtotime($p['created_at']))) ?></td>
                            <td><?= e($p['payment_method'] ? ($paymentMethodLabels[$p['payment_method']] ?? $p['payment_method']) : 'Pending selection') ?></td>
                            <td><?= $paymentDetails ?></td>
                            <td>
                                <span class="badge <?= $statusBadge ?>"><?= e($p['status']) ?></span>
                                <?php if ($p['status'] === 'FAILED' && !empty($p['failure_message'])): ?>
                                    <div class="text-muted" style="font-size:0.75rem;"><?= e($p['failure_message']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">$<?= number_format((float) $p['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted mb-3">No payments recorded yet.</p>
        <?php endif; ?>

        <?php if ($balance > 0): ?>
            <hr>
            <div id="paymentFormWrap" style="display:none;">
                <div class="invoice-eyebrow mb-2">Make a Payment</div>
                <div id="paymentClientError" class="text-danger mb-2" style="font-size:0.85rem;"></div>

                <div id="amountStep">
                    <div class="payment-form-row mb-3">
                        <div class="form-group">
                            <label for="amount">Amount</label>
                            <input type="number" step="0.01" min="0.01" max="<?= number_format($balance, 2, '.', '') ?>"
                                   class="form-control" id="amount" value="<?= number_format($balance, 2, '.', '') ?>">
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label for="notes">Notes (optional)</label>
                        <textarea class="form-control" id="notes" rows="2"></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="secondary_button" onclick="togglePaymentForm()">Cancel</button>
                        <button type="button" class="primary_button" id="startPaymentBtn" onclick="startStripePayment()">Continue to Payment</button>
                    </div>
                </div>

                <div id="stripeStep" style="display:none;">
                    <div id="payment-element" class="mb-3"></div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="secondary_button" onclick="togglePaymentForm()">Cancel</button>
                        <button type="button" class="primary_button" id="confirmPayBtn" onclick="submitStripePayment()">Pay Now</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

</div>
</div>

<?php if (!$error): require '../../footer.php'; endif; ?>

<?php if (!$error && $balance > 0): ?>
<script src="https://js.stripe.com/v3/"></script>
<script>
var invoiceId = <?= (int) $detail['invoice']['id'] ?>;
var stripe = null;
var elements = null;

function togglePaymentForm() {
    var wrap = document.getElementById('paymentFormWrap');
    if (!wrap) { return; }
    var isHidden = wrap.style.display === 'none';
    wrap.style.display = isHidden ? 'block' : 'none';
    if (isHidden) {
        wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

async function startStripePayment() {
    var amountInput = document.getElementById('amount');
    var notesInput = document.getElementById('notes');
    var startBtn = document.getElementById('startPaymentBtn');
    var errorBox = document.getElementById('paymentClientError');
    errorBox.textContent = '';

    var amount = parseFloat(amountInput.value);
    if (!amount || amount <= 0) {
        errorBox.textContent = 'Enter a valid amount.';
        return;
    }

    startBtn.disabled = true;
    startBtn.textContent = 'Preparing secure payment...';

    try {
        var resp = await fetch('create_payment_intent.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ invoice_id: invoiceId, amount: amount, notes: notesInput.value })
        });
        var data = await resp.json();
        if (!resp.ok) {
            throw new Error(data.error || 'Could not start payment.');
        }

        stripe = Stripe(data.publishable_key);
        elements = stripe.elements({ clientSecret: data.client_secret });
        var paymentElement = elements.create('payment');
        paymentElement.mount('#payment-element');

        document.getElementById('amountStep').style.display = 'none';
        document.getElementById('stripeStep').style.display = 'block';
    } catch (err) {
        errorBox.textContent = err.message;
    } finally {
        startBtn.disabled = false;
        startBtn.textContent = 'Continue to Payment';
    }
}

async function submitStripePayment() {
    var payBtn = document.getElementById('confirmPayBtn');
    var errorBox = document.getElementById('paymentClientError');
    errorBox.textContent = '';
    payBtn.disabled = true;
    payBtn.textContent = 'Processing...';

    var returnUrl = window.location.origin + window.location.pathname + '?invoice_id=' + invoiceId;

    var result = await stripe.confirmPayment({
        elements: elements,
        confirmParams: { return_url: returnUrl }
    });

    if (result.error) {
        errorBox.textContent = result.error.message;
        payBtn.disabled = false;
        payBtn.textContent = 'Pay Now';
    }
    // On success Stripe redirects the browser to returnUrl itself - the
    // PHP at the top of this page picks up ?payment_intent=... from there.
}
</script>
<?php endif; ?>
</body>
</html>
