<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\DB;
use App\Core\Auth;

session_start();
Auth::checkBuyer();

if (!isset($_GET['rfq_id'])) {
    die("RFQ ID missing");
}

$conn = DB::getConnection();
$rfqId = (int)$_GET['rfq_id'];

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

?>
<!DOCTYPE html>
<html>
<head>
    <title>RFQ Comparison</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .lowest { background-color: #d4edda !important; font-weight: bold; }
        .supplier-header { background: #f8f9fa; font-weight: 600; }
        .rank-badge { font-size: 0.85rem; margin-right: 5px; }
    </style>
</head>
<body>

<?php require '../../header.php'; ?>

<div class="main-content">
<div class="container my-5">

<h3 class="text-center">RFQ #<?= $rfqId ?> - Full Comparison</h3>
<hr>

<?php foreach ($groups as $group): ?>

    <?php
        $groupId = $group['group_id'];

        /* ------------------------------------------
        Fetch Suppliers (Submitted Only)
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
                    No quotes received yet for group: <strong>'.htmlspecialchars($group['group_name']).'</strong>
                </div>';
            continue;
        }

        /* ------------------------------------------
        Fetch Items
        -------------------------------------------*/
        $stmt = $conn->prepare("
            SELECT id, material_name, quantity, unit
            FROM rfq_items
            WHERE rfq_item_group_id = ?
        ");
        $stmt->bind_param("i", $groupId);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        /* ------------------------------------------
        Fetch ALL Quote Items (Optimized)
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

        /* ------------------------------------------
        Build Lookup Arrays
        -------------------------------------------*/
        $priceMatrix = [];
        $supplierTotals = [];

        foreach ($allQuoteItems as $row) {

            $quoteId = $row['rfq_group_quote_id'];
            $itemId = $row['rfq_item_id'];

            if (!isset($supplierTotals[$quoteId])) {
                $supplierTotals[$quoteId] = 0;
            }
            $supplierTotals[$quoteId] += $row['total_price'];

            $priceMatrix[$quoteId][$itemId] = [
                'unit_price' => $row['unit_price'],
                'total_price' => $row['total_price']
            ];

        }

        //Get status of item group
        $stmt = $conn->prepare("SELECT status FROM rfq_item_groups WHERE id = ?");
        $stmt->bind_param("i", $groupId);
        $stmt->execute();
        $groupStatus = $stmt->get_result()->fetch_assoc()['status'];
        $enableActions = '';
        
        if ($groupStatus == 'DECISION_MADE' || $groupStatus == 'CLOSED_NO_AWARD') {
            $enableActions = "disabled";
        }

    ?>

    <div class="card mb-5">
        <div class="card-header bg-primary text-white">
        <strong>Group: <?= htmlspecialchars($group['group_name']) ?> - <div class="badge bg-light text-dark"><?= htmlspecialchars($groupStatus) ?></div></strong>
        </div>

        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <?php foreach ($suppliers as $supplier): ?>
                            <th class="supplier-header text-center">
                                <?= htmlspecialchars($supplier['company_name']) ?>
                            </th>
                        <?php endforeach; ?>    
                    </tr>
                </thead>
                <tbody>

                    <?php foreach ($items as $item): ?>

                        <?php
                        $itemPrices = [];

                        foreach ($suppliers as $supplier) {

                            $quoteId = $supplier['group_quote_id'];

                            $price = $priceMatrix[$quoteId][$item['id']]['unit_price'] ?? null;

                            if ($price !== null) {
                                $itemPrices[] = (float)$price;
                            }
                        }

                        $lowestPrice = !empty($itemPrices) ? min($itemPrices) : null;
                        ?>

                        <tr>
                            <td><?= htmlspecialchars($item['material_name']) ?></td>
                            <td><?= $item['quantity'] ?></td>

                            <?php foreach ($suppliers as $supplier):

                                $quoteId = $supplier['group_quote_id'];
                                $data = $priceMatrix[$quoteId][$item['id']] ?? null;

                                $unitPrice = $data['unit_price'] ?? null;
                                $isLowest = ($lowestPrice !== null && $unitPrice == $lowestPrice);
                            ?>

                            <td class="text-center <?= $isLowest ? 'lowest' : '' ?>">
                                <?= $unitPrice !== null ? number_format($unitPrice,2) : '-' ?>
                            </td>

                            <?php endforeach; ?>
                        </tr>

                    <?php endforeach; ?>

                    <tr class="table-secondary">
                        <th colspan="2">Supplier Total</th>
                        <?php foreach ($suppliers as $supplier):

                            $quoteId = $supplier['group_quote_id'];
                            $total = $supplierTotals[$quoteId] ?? 0;
                        ?>
                            <th class="text-center">
                                <?= number_format($total,2) ?>
                            </th>
                            
                        <?php endforeach; ?>
                        
                    </tr>
                    <tr class ="table-secondary">
                        <th colspan="2">Ranking</th>
                        <?php
                        $rankingTotals = $supplierTotals;
                        asort($rankingTotals);
                        $rank = 1;
                        foreach ($suppliers as $supplier):

                            $quoteId = $supplier['group_quote_id'];
                            $total = $supplierTotals[$quoteId] ?? 0;

                            // Determine rank
                            $currentRank = array_search($quoteId, array_keys($rankingTotals)) + 1;
                        ?>
                            <th class="text-center">
                                Lowest<?= $currentRank ?>
                            </th>
                            
                        <?php endforeach; ?>
                    </tr>

                </tbody>
            </table>
        </div>
        <div class="row mt-0">
            <div class="col-xl-6 col-lg-8 col-md-10"> 
                
                <div class="card border-0 ">
                    <div class="card-body p-3">
                        <h6 class="text-muted text-uppercase fw-bold mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">
                            Actions for RFQ #<?= $rfqId ?>
                        </h6>
                        
                        <div class="d-flex gap-2">
                            <button class="btn btn-success fw-bold px-4 py-2" 
                                    type="button" 
                                    data-bs-toggle="collapse" 
                                    data-bs-target="#awardSection_<?= $groupId ?>" <?= $enableActions ?>>
                                🏆 Award
                            </button>

                            <a href="postpone_rfq.php?rfq_id=<?= $rfqId ?>&group_id=<?= $groupId ?>" 
                            class="btn btn-outline-primary fw-bold px-3 py-2 <?= $enableActions ?>"
                            <?php echo $enableActions ? 'tabindex="-1" aria-disabled="true"' : ""; ?>
                            >
                                ⏳ Later
                            </a>

                            <a href="close_rfq.php?rfq_id=<?= $rfqId ?>&group_id=<?= $groupId ?>" 
                            class="btn btn-outline-danger fw-bold px-3 py-2 <?= $enableActions ?>"
                            <?php echo $enableActions ? 'tabindex="-1" aria-disabled="true"' : ""; ?>
                            onclick="return confirm('Close without award?')" <?= $enableActions ?>>
                                ❌ Close
                            </a>
                        </div>

                        <div class="collapse mt-3" id="awardSection_<?= $groupId ?>">
                            <form method="POST" action="award_supplier.php" class="p-3 border rounded bg-white shadow-sm">
                                <input type="hidden" name="rfq_id" value="<?= $rfqId ?>">
                                <input type="hidden" name="group_id" value="<?= $groupId ?>">
                                
                                <div class="row g-2 align-items-center">
                                    <div class="col-sm-8">
                                        <select name="quote_id" class="form-select" required>
                                            <option value="">Select Supplier...</option>
                                            <?php foreach ($suppliers as $supplier): ?>
                                                <option value="<?= $supplier['group_quote_id'] ?>">
                                                    <?= htmlspecialchars($supplier['company_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-4">
                                        <button type="submit" class="btn btn-dark w-100">Confirm</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
                
            </div>
        </div>

    </div>

<?php endforeach; ?>

</div>
</div>

<?php require '../../footer.php'; ?>
</body>
</html>