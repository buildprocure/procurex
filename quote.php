<?php
declare(strict_types=1);

/**
 * Tokenized quote submission ??? no account, no password.
 *
 * A supplier reaches this page from the link in their RFQ email. The token
 * in ?t= identifies exactly one (rfq_item_group, supplier) invitation and
 * authorises exactly one action: submit a quote for that group.
 *
 * Deliberately NOT here:
 *   - session_start()  ??? this page creates no session
 *   - Auth::check*()   ??? the token is the authorisation
 *   - any nav/header include ??? a supplier on a phone gets one screen, no chrome
 *
 * Quantities are always read from the database, never from the request, so a
 * tampered form cannot alter the job being priced.
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\DB;
use App\Core\InviteToken;

$token = isset($_GET['t']) ? (string) $_GET['t'] : '';

$invitation = InviteToken::resolve($token);

/* ---------------------------------------------------------------
   Gate: unknown / expired / already answered
----------------------------------------------------------------*/
if ($invitation === null) {
    render_notice(
        'Link not recognised',
        'This quote link is not valid. It may have been mistyped or already replaced by a newer request.',
        'error'
    );
    exit;
}

if ($invitation['already_quoted']) {
    render_notice(
        'Quote already submitted',
        'We have your prices for this request. The buyer will be in touch if you are awarded the order. Thank you.',
        'success'
    );
    exit;
}

if ($invitation['is_expired'] || $invitation['deadline_passed']) {
    render_notice(
        'This request has closed',
        'The deadline for this quote request has passed. If you would still like to be considered, please contact the buyer directly.',
        'warning'
    );
    exit;
}

InviteToken::markViewed((int) $invitation['invitation_id']);

$conn    = DB::getConnection();
$groupId = (int) $invitation['rfq_item_group_id'];

/* ---------------------------------------------------------------
   Line items
----------------------------------------------------------------*/
$stmt = $conn->prepare("
    SELECT id, material_name, specification, unit, quantity
    FROM rfq_items
    WHERE rfq_item_group_id = ?
    ORDER BY id ASC
");
$stmt->bind_param('i', $groupId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($items)) {
    render_notice(
        'Nothing to quote',
        'This request contains no line items. Please contact the buyer.',
        'error'
    );
    exit;
}

$errors = [];
$posted = [];

/* ---------------------------------------------------------------
   Submission
----------------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $posted   = $_POST['price'] ?? [];
    $leadTime = trim((string) ($_POST['lead_time'] ?? ''));
    $notes    = trim((string) ($_POST['notes'] ?? ''));

    // Index quantities by item id from the DB ??? never trust the form.
    $validItems = [];
    foreach ($items as $item) {
        $validItems[(int) $item['id']] = $item;
    }

    $priced = [];

    foreach ($validItems as $itemId => $item) {
        $raw = isset($posted[$itemId]) ? trim((string) $posted[$itemId]) : '';

        // Blank means "not quoting this line" ??? allowed, and common.
        if ($raw === '') {
            continue;
        }

        if (!is_numeric($raw)) {
            $errors[] = "Price for \"{$item['material_name']}\" must be a number.";
            continue;
        }

        $price = (float) $raw;

        if ($price < 0) {
            $errors[] = "Price for \"{$item['material_name']}\" cannot be negative.";
            continue;
        }

        if ($price > 99999999) {
            $errors[] = "Price for \"{$item['material_name']}\" looks too large.";
            continue;
        }

        $priced[$itemId] = $price;
    }

    if (empty($priced) && empty($errors)) {
        $errors[] = 'Please enter a price for at least one item.';
    }

    if (empty($errors)) {
        $conn->begin_transaction();

        try {
            $supplierId = (int) $invitation['supplier_company_id'];

            $insertHeader = $conn->prepare("
                INSERT INTO rfq_group_quotes
                    (rfq_item_group_id, supplier_company_id, status)
                VALUES (?, ?, 'SUBMITTED')
            ");
            $insertHeader->bind_param('ii', $groupId, $supplierId);
            $insertHeader->execute();

            $quoteId     = (int) $conn->insert_id;
            $totalAmount = 0.0;

            $insertItem = $conn->prepare("
                INSERT INTO rfq_group_quote_items
                    (rfq_group_quote_id, rfq_item_id, unit_price, total_price)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($priced as $itemId => $unitPrice) {
                $qty       = (float) $validItems[$itemId]['quantity'];
                $lineTotal = $qty * $unitPrice;
                $totalAmount += $lineTotal;

                $insertItem->bind_param(
                    'iidd', $quoteId, $itemId, $unitPrice, $lineTotal
                );
                $insertItem->execute();
            }

            $updateHeader = $conn->prepare("
                UPDATE rfq_group_quotes
                SET total_amount = ?, submitted_at = NOW()
                WHERE id = ?
            ");
            $updateHeader->bind_param('di', $totalAmount, $quoteId);
            $updateHeader->execute();

            // Retire the token: one invitation, one quote.
            $updateStatus = $conn->prepare("
                UPDATE rfq_group_suppliers
                SET status = 'QUOTED',
                    responded_at = NOW(),
                    invite_token_hash = NULL
                WHERE id = ?
            ");
            $updateStatus->bind_param('i', $invitation['invitation_id']);
            $updateStatus->execute();

            $conn->commit();

            render_notice(
                'Quote received',
                'Thank you. Your prices have been sent to the buyer, who will be in touch if you are awarded the order.',
                'success'
            );
            exit;

        } catch (\Throwable $e) {
            $conn->rollback();
            error_log('[quote.php] Submission failed: ' . $e->getMessage());
            $errors[] = 'Something went wrong saving your quote. Please try again.';
        }
    }
}

/* ---------------------------------------------------------------
   Helpers
----------------------------------------------------------------*/
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function qty_fmt($v): string
{
    return rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
}

function render_notice(string $title, string $message, string $type): void
{
    $colors = [
        'success' => ['#059669', '#ecfdf5'],
        'warning' => ['#b45309', '#fffbeb'],
        'error'   => ['#dc2626', '#fef2f2'],
    ];
    [$accent, $bg] = $colors[$type] ?? $colors['error'];

    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $m = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    echo <<<HTML
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{$t} - BuildProcure</title>
<style>
  body{margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;
       background:#f1f5f9;display:flex;align-items:center;justify-content:center;
       min-height:100vh;padding:20px;}
  .card{background:#fff;border-radius:12px;padding:40px 32px;max-width:440px;text-align:center;
        box-shadow:0 1px 3px rgba(0,0,0,.1);border-top:4px solid {$accent};}
  h1{margin:0 0 12px;font-size:21px;color:#0f172a;}
  p{margin:0;font-size:15px;color:#475569;line-height:1.65;}
  .badge{display:inline-block;background:{$bg};color:{$accent};border-radius:50%;
         width:48px;height:48px;line-height:48px;font-size:24px;margin-bottom:18px;}
  .brand{margin-top:28px;font-size:13px;color:#94a3b8;}
</style></head>
<body><div class="card">
  <div class="badge">&#9679;</div>
  <h1>{$t}</h1>
  <p>{$m}</p>
  <div class="brand">BuildProcure</div>
</div></body></html>
HTML;
}

$groupName    = $invitation['group_name'] ?: 'Materials';
$supplierName = $invitation['supplier_name'] ?: '';
$rfqRef       = 'RFQ-' . str_pad((string) $invitation['rfq_id'], 5, '0', STR_PAD_LEFT);
$deadlineTs   = $invitation['quote_deadline']
    ? strtotime((string) $invitation['quote_deadline'])
    : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Quote request: <?= e($groupName) ?> - BuildProcure</title>
<style>
  *{box-sizing:border-box;}
  body{margin:0;background:#f1f5f9;color:#0f172a;
       font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;
       padding:0 0 40px;}
  .bar{background:#0f172a;color:#fff;padding:16px 20px;display:flex;
       justify-content:space-between;align-items:center;}
  .bar strong{font-size:17px;letter-spacing:-.3px;}
  .bar span{color:#94a3b8;font-size:13px;}
  .wrap{max-width:680px;margin:0 auto;padding:0 16px;}
  .card{background:#fff;border-radius:12px;margin-top:20px;padding:24px 20px;
        box-shadow:0 1px 3px rgba(0,0,0,.08);}
  h1{margin:0 0 6px;font-size:22px;letter-spacing:-.4px;}
  .sub{margin:0 0 20px;color:#64748b;font-size:15px;line-height:1.5;}
  .due{display:inline-block;background:#fffbeb;color:#b45309;border-radius:6px;
       padding:7px 12px;font-size:14px;font-weight:600;margin-bottom:20px;}
  .item{border:1px solid #e2e8f0;border-radius:8px;padding:14px;margin-bottom:12px;}
  .item-name{font-size:16px;font-weight:600;margin-bottom:3px;}
  .item-spec{font-size:13px;color:#64748b;margin-bottom:10px;line-height:1.5;}
  .item-qty{font-size:14px;color:#475569;margin-bottom:12px;}
  .item-qty b{color:#0f172a;}
  .price-row{display:flex;align-items:center;gap:10px;}
  .price-label{font-size:14px;color:#475569;white-space:nowrap;}
  .price-input{flex:1;padding:12px 14px;font-size:17px;border:1.5px solid #cbd5e1;
               border-radius:8px;-webkit-appearance:none;}
  .price-input:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12);}
  .line-total{font-size:13px;color:#64748b;margin-top:8px;min-height:18px;}
  label.blk{display:block;font-size:14px;font-weight:600;margin:0 0 6px;}
  input.txt,textarea.txt{width:100%;padding:11px 13px;font-size:15px;
       border:1.5px solid #cbd5e1;border-radius:8px;font-family:inherit;}
  input.txt:focus,textarea.txt:focus{outline:none;border-color:#2563eb;}
  .total{background:#0f172a;color:#fff;border-radius:10px;padding:18px 20px;
         display:flex;justify-content:space-between;align-items:center;margin:20px 0;}
  .total span{font-size:15px;color:#cbd5e1;}
  .total b{font-size:24px;letter-spacing:-.5px;}
  button{width:100%;padding:16px;font-size:17px;font-weight:600;color:#fff;
         background:#2563eb;border:0;border-radius:8px;cursor:pointer;}
  button:active{background:#1d4ed8;}
  .hint{margin:14px 0 0;font-size:13px;color:#94a3b8;text-align:center;line-height:1.6;}
  .errs{background:#fef2f2;border:1px solid #fecaca;border-radius:8px;
        padding:14px 16px;margin-bottom:18px;}
  .errs p{margin:0 0 6px;font-size:14px;color:#b91c1c;font-weight:600;}
  .errs li{font-size:14px;color:#dc2626;margin-bottom:3px;}
  .errs ul{margin:0;padding-left:18px;}
  @media(min-width:560px){
    .price-row{max-width:340px;}
    .card{padding:32px 28px;}
  }
</style>
</head>
<body>

<div class="bar">
  <strong>BuildProcure</strong>
  <span><?= e($rfqRef) ?></span>
</div>

<div class="wrap">
  <div class="card">

    <h1>Quote request: <?= e($groupName) ?></h1>
    <p class="sub">
      <?php if ($supplierName): ?>
        Prepared for <?= e($supplierName) ?>.
      <?php endif; ?>
      Enter your unit prices below. Leave any line blank if you are not quoting it.
    </p>

    <?php if ($deadlineTs !== false): ?>
      <div class="due">Quotes needed by <?= e(date('j M Y', $deadlineTs)) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="errs">
        <p>Please check the following:</p>
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <?php foreach ($items as $item):
            $iid = (int) $item['id'];
            $prev = isset($posted[$iid]) ? (string) $posted[$iid] : '';
      ?>
        <div class="item">
          <div class="item-name"><?= e($item['material_name']) ?></div>
          <?php if (!empty($item['specification'])): ?>
            <div class="item-spec"><?= e($item['specification']) ?></div>
          <?php endif; ?>
          <div class="item-qty">
            Quantity: <b><?= e(qty_fmt($item['quantity'])) ?> <?= e($item['unit']) ?></b>
          </div>
          <div class="price-row">
            <span class="price-label">Unit price</span>
            <input class="price-input"
                   type="number" inputmode="decimal" step="0.01" min="0"
                   name="price[<?= $iid ?>]"
                   value="<?= e($prev) ?>"
                   data-qty="<?= e((string) (float) $item['quantity']) ?>"
                   placeholder="0.00">
          </div>
          <div class="line-total" data-for="<?= $iid ?>"></div>
        </div>
      <?php endforeach; ?>

      <div class="total">
        <span>Quote total</span>
        <b id="grand">0.00</b>
      </div>

      <div style="margin-bottom:16px;">
        <label class="blk" for="lead_time">Lead time (optional)</label>
        <input class="txt" type="text" id="lead_time" name="lead_time"
               value="<?= e($_POST['lead_time'] ?? '') ?>"
               placeholder="e.g. 5-7 working days">
      </div>

      <div style="margin-bottom:20px;">
        <label class="blk" for="notes">Notes (optional)</label>
        <textarea class="txt" id="notes" name="notes" rows="3"
                  placeholder="Substitutions, minimum order, delivery terms..."><?= e($_POST['notes'] ?? '') ?></textarea>
      </div>

      <button type="submit">Submit quote</button>
      <p class="hint">
        No account needed. You can close this page once submitted.
      </p>
    </form>

  </div>
</div>

<script>
(function () {
  var inputs = document.querySelectorAll('.price-input');
  var grand  = document.getElementById('grand');

  function fmt(n) {
    return n.toLocaleString(undefined, {
      minimumFractionDigits: 2, maximumFractionDigits: 2
    });
  }

  function recalc() {
    var total = 0;
    inputs.forEach(function (input) {
      var qty   = parseFloat(input.getAttribute('data-qty')) || 0;
      var price = parseFloat(input.value);
      var id    = input.name.replace(/\D/g, '');
      var slot  = document.querySelector('[data-for="' + id + '"]');

      if (!isNaN(price) && price >= 0) {
        var line = qty * price;
        total += line;
        if (slot) { slot.textContent = 'Line total: ' + fmt(line); }
      } else if (slot) {
        slot.textContent = '';
      }
    });
    grand.textContent = fmt(total);
  }

  inputs.forEach(function (input) {
    input.addEventListener('input', recalc);
  });

  recalc();
})();
</script>

</body>
</html>
