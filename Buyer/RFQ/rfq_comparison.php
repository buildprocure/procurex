<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\DB;
use App\Core\Auth;

session_start();
Auth::checkBuyer();

if (!isset($_GET['rfq_id'])) {
    die("RFQ ID missing");
}

$conn  = DB::getConnection();
$rfqId = (int) $_GET['rfq_id'];

/* -------------------------------------------------
   Fetch RFQ
--------------------------------------------------*/
$stmt = $conn->prepare("SELECT * FROM rfqs WHERE id = ?");
$stmt->bind_param("i", $rfqId);
$stmt->execute();
$rfq = $stmt->get_result()->fetch_assoc();

if (!$rfq) {
    die("RFQ not found");
}

/* -------------------------------------------------
   Fetch Groups
   Groups are display grouping only - they drove RFQ
   distribution to suppliers and carry no award state.
--------------------------------------------------*/
$stmt = $conn->prepare("
    SELECT rig.id as group_id, ig.group_name
    FROM rfq_item_groups rig
    JOIN item_groups ig ON ig.id = rig.item_group_id
    WHERE rig.rfq_id = ?
");
$stmt->bind_param("i", $rfqId);
$stmt->execute();
$groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function fmtQty($v): string
{
    return rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>RFQ Comparison</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bp-primary: #0d6efd;
            --bp-primary-dark: #0b5ed7;
            --bp-primary-darker: #0a4fb5;
            --bp-primary-light: #eaf2ff;
            --bp-primary-border: #cfe2ff;
            --bp-success: #198754;
            --bp-success-bg: #e6f6ec;
            --bp-danger: #dc3545;
            --bp-ink: #1f2937;
            --bp-muted: #6b7280;
        }

        body { background-color: #f5f7fb; }

        .rfq-page-header {
            text-align: center;
            padding: 28px 16px 20px;
        }
        .rfq-page-header .eyebrow {
            display: inline-block;
            background: var(--bp-primary-light);
            color: var(--bp-primary-dark);
            font-weight: 600;
            font-size: 0.75rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 999px;
            margin-bottom: 10px;
        }
        .rfq-page-header h3 {
            font-weight: 700;
            color: var(--bp-ink);
            margin-bottom: 6px;
        }
        .rfq-page-header p { color: var(--bp-muted); max-width: 640px; margin: 0 auto; }

        .group-card {
            border: none;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04), 0 4px 16px rgba(16, 24, 40, 0.06);
        }

        .group-card .card-header {
            background: linear-gradient(135deg, var(--bp-primary), var(--bp-primary-darker));
            border: none;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .group-card .card-header .group-name {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }
        .group-card .card-header .badge-hint {
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
            font-weight: 500;
            font-size: 0.72rem;
            padding: 5px 10px;
            border-radius: 999px;
        }

        .table-scroll { overflow-x: auto; }
        .rfq-table { margin-bottom: 0; min-width: 640px; }
        .rfq-table thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            background: var(--bp-primary-light);
            color: var(--bp-primary-darker);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            font-weight: 700;
            border-bottom: 2px solid var(--bp-primary-border);
            white-space: nowrap;
            padding: 12px 14px;
        }
        .rfq-table td { padding: 14px; vertical-align: middle; }
        .rfq-table tbody tr.item-row:not(:last-of-type) { border-bottom: 1px solid #eef1f6; }

        .item-name { font-weight: 600; color: var(--bp-ink); }
        .item-qty-main { font-weight: 600; color: var(--bp-ink); }
        .item-remaining { color: var(--bp-muted); font-size: 0.8rem; }

        .price-cell { font-weight: 600; color: var(--bp-ink); }
        .price-cell.best {
            background-color: var(--bp-success-bg) !important;
            color: var(--bp-success);
            position: relative;
        }
        .best-price-badge {
            display: inline-block;
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            background: var(--bp-success);
            color: #fff;
            border-radius: 999px;
            padding: 1px 7px;
            margin-top: 4px;
        }

        .item-row.fully-awarded { background-color: #fafbfc; }
        .item-row.fully-awarded .item-name { color: var(--bp-muted); }

        .award-status-badge { font-size: 0.66rem; padding: 3px 8px; border-radius: 999px; font-weight: 600; }

        .award-history { font-size: 0.78rem; color: var(--bp-muted); margin-top: 6px; line-height: 1.5; }
        .award-history strong { color: var(--bp-ink); }

        .award-panel-row td {
            background: var(--bp-primary-light);
            border-top: 1px dashed var(--bp-primary-border);
            padding: 14px 16px;
        }
        .award-suppliers-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 14px;
        }
        .award-actions-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid var(--bp-primary-border);
        }
        .award-actions-row .award-actions-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .award-supplier-field label {
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--bp-ink);
            margin-bottom: 4px;
        }
        .award-supplier-field .supplier-price-tag {
            color: var(--bp-primary-dark);
            font-weight: 600;
        }
        .award-supplier-field input {
            width: 118px;
        }
        .award-remaining-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--bp-ink);
            background: #fff;
            border: 1px solid var(--bp-primary-border);
            border-radius: 8px;
            padding: 6px 12px;
            white-space: nowrap;
            transition: color .1s ease, border-color .1s ease, background-color .1s ease;
        }
        .award-remaining-label.is-exceeded {
            color: var(--bp-danger);
            border-color: #f0a8b0;
            background: #fdecee;
        }

        /* ===================================================================
           BuildProcure button system
           Two building blocks for every action in the app: primary_button
           for the one action a screen wants you to take, secondary_button
           for everything supporting/alternative to it. A .is-danger
           modifier layers a destructive tone onto secondary_button rather
           than introducing a third base class - Close is still a
           secondary-weight action, just a risky one.
           An .is-sm modifier shrinks either for dense contexts like table
           rows; omit it for standalone/full-size CTAs elsewhere in the app.
        =================================================================== */
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
        .primary_button:disabled, .primary_button.is-disabled {
            background-color: #a9cbfb; border-color: #a9cbfb; color: #fff;
            cursor: not-allowed; box-shadow: none; transform: none;
        }

        .secondary_button {
            background-color: #fff;
            border-color: var(--bp-primary);
            color: var(--bp-primary-dark);
        }
        .secondary_button:hover  { background-color: var(--bp-primary-light); border-color: var(--bp-primary-dark); color: var(--bp-primary-darker); }
        .secondary_button:active { background-color: #dbe9ff; }
        .secondary_button:disabled, .secondary_button.is-disabled {
            background-color: #fff; border-color: #d1d5db; color: #9ca3af;
            cursor: not-allowed; transform: none;
        }

        .secondary_button.is-danger { border-color: var(--bp-danger); color: var(--bp-danger); }
        .secondary_button.is-danger:hover {
            background-color: #fdecee; border-color: #b3202f; color: #b3202f;
        }
        .secondary_button.is-danger:active { background-color: #fbdadd; }
        .secondary_button.is-danger:focus-visible { box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.28); }

        .primary_button.is-sm, .secondary_button.is-sm {
            padding: 6px 13px;
            font-size: 0.78rem;
            border-radius: 7px;
        }

        .item-action-buttons { display: flex; gap: 8px; margin-left: auto; }

        .supplier-rate-label {
            font-size: 0.62rem;
            font-weight: 500;
            text-transform: none;
            letter-spacing: 0;
            color: var(--bp-primary-dark);
            opacity: 0.75;
            margin-top: 2px;
        }

        .award-toast { position: fixed; top: 20px; right: 20px; z-index: 2000; min-width: 280px; }
    </style>
</head>
<body>

<?php require '../../header.php'; ?>

<div class="main-content">
<div class="container my-4">

<div class="rfq-page-header">
    <span class="eyebrow">RFQ Award</span>
    <h3>RFQ #<?= $rfqId ?> &mdash; Full Comparison</h3>
    <p>Award any quantity of a line item to a supplier. The rest of that item's quantity can go to a different supplier at any time.</p>
</div>

<?php foreach ($groups as $group): ?>

    <?php
        $groupId = $group['group_id'];

        /* ------------------------------------------
        Suppliers who submitted a quote anywhere in this group
        -------------------------------------------*/
        $stmt = $conn->prepare("
            SELECT rgq.id as group_quote_id,
                rgq.supplier_company_id,
                c.name as company_name
            FROM rfq_group_quotes rgq
            JOIN companies c ON c.id = rgq.supplier_company_id
            WHERE rgq.rfq_item_group_id = ?
            AND rgq.status = 'SUBMITTED'
        ");
        $stmt->bind_param("i", $groupId);
        $stmt->execute();
        $suppliers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($suppliers)) {
            echo '<div class="alert alert-warning">
                    No quotes received yet for group: <strong>'.e($group['group_name']).'</strong>
                </div>';
            continue;
        }

        /* ------------------------------------------
        Items in this group, with award progress
        -------------------------------------------*/
        $stmt = $conn->prepare("
            SELECT id, material_name, specification, unit, quantity,
                   awarded_quantity, award_status, line_status
            FROM rfq_items
            WHERE rfq_item_group_id = ?
        ");
        $stmt->bind_param("i", $groupId);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        /* ------------------------------------------
        All quote items for suppliers in this group
        -------------------------------------------*/
        $stmt = $conn->prepare("
            SELECT rgqi.rfq_group_quote_id,
                rgqi.rfq_item_id,
                rgqi.unit_price,
                rgqi.total_price
            FROM rfq_group_quote_items rgqi
            JOIN rfq_group_quotes rgq
                ON rgq.id = rgqi.rfq_group_quote_id
            WHERE rgq.rfq_item_group_id = ?
            AND rgq.status = 'SUBMITTED'
        ");
        $stmt->bind_param("i", $groupId);
        $stmt->execute();
        $allQuoteItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $priceMatrix = [];

        foreach ($allQuoteItems as $row) {
            $qId = $row['rfq_group_quote_id'];
            $iId = $row['rfq_item_id'];

            $priceMatrix[$qId][$iId] = [
                'unit_price'  => $row['unit_price'],
                'total_price' => $row['total_price'],
            ];
        }

        /* ------------------------------------------
        Existing awards per item, for the "already awarded to" list
        -------------------------------------------*/
        $itemIds = array_column($items, 'id');
        $existingAwards = [];

        if (!empty($itemIds)) {
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $types = str_repeat('i', count($itemIds));

            $stmt = $conn->prepare("
                SELECT ria.rfq_item_id, ria.awarded_quantity, ria.unit_price,
                       c.name as supplier_name
                FROM rfq_item_awards ria
                JOIN companies c ON c.id = ria.supplier_company_id
                WHERE ria.rfq_item_id IN ($placeholders)
                ORDER BY ria.created_at ASC
            ");
            $stmt->bind_param($types, ...$itemIds);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $existingAwards[$row['rfq_item_id']][] = $row;
            }
        }
    ?>

    <div class="card group-card mb-4">
        <div class="card-header">
            <span class="group-name">Group: <?= e($group['group_name']) ?></span>
            <span class="badge-hint">Reference only &mdash; award per item below</span>
        </div>

        <div class="card-body p-0">
            <div class="table-scroll">
            <table class="table table-bordered rfq-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <?php foreach ($suppliers as $supplier): ?>
                            <th class="text-center">
                                <?= e($supplier['company_name']) ?>
                                <div class="supplier-rate-label">Rate (per unit)</div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>

                    <?php foreach ($items as $item):
                        $itemId    = (int) $item['id'];
                        $qty       = (float) $item['quantity'];
                        $awardedQ  = (float) $item['awarded_quantity'];
                        $remaining = max(0, $qty - $awardedQ);
                        $fullyAwarded = $item['award_status'] === 'FULLY_AWARDED';
                        $closedNoAward = ($item['line_status'] ?? 'ACTIVE') === 'CLOSED_NO_AWARD';
                        $postponed = ($item['line_status'] ?? 'ACTIVE') === 'POSTPONED';
                        $terminal = $fullyAwarded || $closedNoAward;

                        $itemPrices = [];
                        foreach ($suppliers as $supplier) {
                            $qId = $supplier['group_quote_id'];
                            $price = $priceMatrix[$qId][$itemId]['unit_price'] ?? null;
                            if ($price !== null) {
                                $itemPrices[] = (float) $price;
                            }
                        }
                        $lowestPrice = !empty($itemPrices) ? min($itemPrices) : null;

                        // Suppliers who actually priced this specific item.
                        $quotingSuppliers = array_values(array_filter($suppliers,
                            fn($s) => isset($priceMatrix[$s['group_quote_id']][$itemId])
                        ));
                    ?>

                    <tr class="item-row <?= $terminal ? 'fully-awarded' : '' ?>">
                        <td>
                            <span class="item-name"><?= e($item['material_name']) ?></span>
                            <?php if ($item['award_status'] === 'FULLY_AWARDED'): ?>
                                <span class="badge bg-success award-status-badge">Fully Awarded</span>
                            <?php elseif ($item['award_status'] === 'PARTIALLY_AWARDED'): ?>
                                <span class="badge bg-warning text-dark award-status-badge">Partially Awarded</span>
                            <?php endif; ?>
                            <?php if ($closedNoAward): ?>
                                <span class="badge bg-secondary award-status-badge">Closed &mdash; No Award</span>
                            <?php elseif ($postponed): ?>
                                <span class="badge bg-info text-dark award-status-badge">Postponed</span>
                            <?php endif; ?>

                            <?php if (!empty($existingAwards[$itemId])): ?>
                                <div class="award-history">
                                    <?php foreach ($existingAwards[$itemId] as $a): ?>
                                        Awarded <strong><?= fmtQty($a['awarded_quantity']) ?> <?= e($item['unit']) ?></strong>
                                        to <?= e($a['supplier_name']) ?> @ <?= number_format($a['unit_price'], 2) ?><br>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="item-qty-main"><?= fmtQty($qty) ?> <?= e($item['unit']) ?></div>
                            <div class="item-remaining">Remaining: <?= fmtQty($remaining) ?></div>
                        </td>

                        <?php foreach ($suppliers as $supplier):
                            $qId = $supplier['group_quote_id'];
                            $data = $priceMatrix[$qId][$itemId] ?? null;
                            $unitPrice = $data['unit_price'] ?? null;
                            $isLowest = ($lowestPrice !== null && $unitPrice == $lowestPrice);
                        ?>
                            <td class="text-center price-cell <?= $isLowest ? 'best' : '' ?>">
                                <?= $unitPrice !== null ? number_format($unitPrice, 2) : '-' ?>
                                <?php if ($isLowest): ?>
                                    <div><span class="best-price-badge">Best Price</span></div>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>

                    <?php if (!$terminal && !empty($quotingSuppliers)): ?>
                    <tr class="award-panel-row">
                        <td colspan="<?= 2 + count($suppliers) ?>">
                            <form class="award-item-form"
                                  data-item-id="<?= $itemId ?>"
                                  data-remaining="<?= e($remaining) ?>"
                                  data-unit="<?= e($item['unit']) ?>">
                                <div class="award-suppliers-row">
                                    <?php foreach ($quotingSuppliers as $supplier):
                                        $qId = $supplier['group_quote_id'];
                                        $price = $priceMatrix[$qId][$itemId]['unit_price'];
                                    ?>
                                        <div class="award-supplier-field">
                                            <label class="form-label d-block mb-0">
                                                <?= e($supplier['company_name']) ?>
                                                <span class="supplier-price-tag">(@<?= number_format($price, 2) ?>)</span>
                                            </label>
                                            <input type="number"
                                                   class="form-control form-control-sm award-qty-input"
                                                   data-quote-id="<?= $qId ?>"
                                                   min="0" step="0.001"
                                                   placeholder="0">
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="award-actions-row">
                                    <div class="award-actions-left">
                                        <div class="award-remaining-label">
                                            Remaining: <span class="award-remaining-value"><?= fmtQty($remaining) ?></span> <?= e($item['unit']) ?>
                                        </div>
                                        <button type="submit" class="primary_button is-sm">Award</button>
                                    </div>

                                    <div class="item-action-buttons">
                                        <a href="postpone_rfq_item.php?rfq_id=<?= $rfqId ?>&item_id=<?= $itemId ?>"
                                           class="secondary_button is-sm">Later</a>
                                        <a href="close_rfq_item.php?rfq_id=<?= $rfqId ?>&item_id=<?= $itemId ?>"
                                           class="secondary_button is-sm is-danger"
                                           onclick="return confirm('Close this item without award?')">Close</a>
                                    </div>
                                </div>
                                <div class="award-error text-danger small mt-2" style="display:none;"></div>
                            </form>
                        </td>
                    </tr>
                    <?php elseif (!$terminal): ?>
                    <tr class="award-panel-row">
                        <td colspan="<?= 2 + count($suppliers) ?>">
                            <div class="d-flex align-items-center justify-content-end item-action-buttons">
                                <a href="postpone_rfq_item.php?rfq_id=<?= $rfqId ?>&item_id=<?= $itemId ?>"
                                   class="secondary_button is-sm">Later</a>
                                <a href="close_rfq_item.php?rfq_id=<?= $rfqId ?>&item_id=<?= $itemId ?>"
                                   class="secondary_button is-sm is-danger"
                                   onclick="return confirm('Close this item without award?')">Close</a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <?php endforeach; ?>

                </tbody>
            </table>
            </div>
        </div>
    </div>

<?php endforeach; ?>

</div>
</div>

<div class="award-toast"></div>

<script>
function fmtQtyJs(v) {
    if (!isFinite(v)) return '0';
    return (Math.round(v * 1000) / 1000)
        .toFixed(3)
        .replace(/0+$/, '')
        .replace(/\.$/, '');
}

function updateRemainingLabel(form) {
    const remaining = parseFloat(form.dataset.remaining);
    const valueEl = form.querySelector('.award-remaining-value');
    const labelEl = form.querySelector('.award-remaining-label');
    if (!valueEl || !labelEl) return;

    let total = 0;
    form.querySelectorAll('.award-qty-input').forEach(function (input) {
        const qty = parseFloat(input.value);
        if (!isNaN(qty) && qty > 0) total += qty;
    });

    const left = remaining - total;
    valueEl.textContent = fmtQtyJs(left);
    labelEl.classList.toggle('is-exceeded', left < -0.0001);
}

document.addEventListener('input', function (e) {
    if (!e.target.classList.contains('award-qty-input')) return;
    const form = e.target.closest('.award-item-form');
    if (form) updateRemainingLabel(form);
});

document.addEventListener('submit', async function (e) {
    const form = e.target;
    if (!form.classList.contains('award-item-form')) return;
    e.preventDefault();

    const itemId    = parseInt(form.dataset.itemId, 10);
    const remaining = parseFloat(form.dataset.remaining);
    const errorBox  = form.querySelector('.award-error');
    errorBox.style.display = 'none';

    const awards = [];
    let total = 0;

    form.querySelectorAll('.award-qty-input').forEach(function (input) {
        const qty = parseFloat(input.value);
        if (!isNaN(qty) && qty > 0) {
            total += qty;
            awards.push({
                rfq_item_id: itemId,
                quote_id: parseInt(input.dataset.quoteId, 10),
                quantity: qty
            });
        }
    });

    if (awards.length === 0) {
        errorBox.textContent = 'Enter a quantity for at least one supplier.';
        errorBox.style.display = 'block';
        return;
    }

    if (total > remaining + 0.0001) {
        errorBox.textContent =
            'Total (' + total + ') exceeds remaining quantity (' + remaining + ').';
        errorBox.style.display = 'block';
        return;
    }

    const rfqId = <?= (int) $rfqId ?>;
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    try {
        const res = await fetch('award_items.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ rfq_id: rfqId, awards: awards })
        });
        const data = await res.json();

        if (!data.success) {
            errorBox.textContent = data.error || 'Award failed.';
            errorBox.style.display = 'block';
            submitBtn.disabled = false;
            return;
        }

        showToast('Awarded successfully. Reloading...');
        setTimeout(() => window.location.reload(), 700);

    } catch (err) {
        errorBox.textContent = 'Network error: ' + err.message;
        errorBox.style.display = 'block';
        submitBtn.disabled = false;
    }
});

function showToast(message) {
    const el = document.createElement('div');
    el.className = 'alert alert-success shadow';
    el.textContent = message;
    document.querySelector('.award-toast').appendChild(el);
}
</script>

<?php require '../../footer.php'; ?>
</body>
</html>
