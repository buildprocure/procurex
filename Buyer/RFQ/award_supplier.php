<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\DB;
use App\Core\Auth;

session_start();
Auth::checkBuyer();

$conn = DB::getConnection();

$rfqId   = (int)$_POST['rfq_id'];
$groupId = (int)$_POST['group_id'];
$quoteId = (int)$_POST['quote_id'];

$logs = []; // Transaction logs array

$conn->begin_transaction();

try {

    /* 1️⃣ Get Awarded Quote Info */
    $stmt = $conn->prepare("
        SELECT supplier_company_id, total_amount
        FROM rfq_group_quotes
        WHERE id = ?
    ");
    $stmt->bind_param("i", $quoteId);
    $stmt->execute();
    $quote = $stmt->get_result()->fetch_assoc();
    
    if (!$quote) {
        throw new Exception("Quote not found for ID: $quoteId");
    }

    $supplierId = $quote['supplier_company_id'];
    $totalAmount = $quote['total_amount'];
    
    $logs[] = "[Step 1] Retrieved quote: ID=$quoteId, Supplier=$supplierId, Amount=$totalAmount";

    /* 2️⃣ Mark This Quote Awarded */
    $stmt = $conn->prepare("
        UPDATE rfq_group_quotes
        SET decision_status = 'AWARDED'
        WHERE id = ?
    ");
    $stmt->bind_param("i", $quoteId);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $logs[] = "[Step 2] Marked quote as AWARDED: Affected rows=$affectedRows";

    /* 3️⃣ Mark Others as Lost */
    $stmt = $conn->prepare("
        UPDATE rfq_group_quotes
        SET decision_status = 'LOST'
        WHERE rfq_item_group_id = ?
        AND id != ?
    ");
    $stmt->bind_param("ii", $groupId, $quoteId);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $logs[] = "[Step 3] Marked competing quotes as LOST: Affected rows=$affectedRows";

    /* Update rfq item group status to awarded */
    $stmt = $conn->prepare("
        UPDATE rfq_item_groups
        SET status = 'DECISION_MADE'
        WHERE id = ?
    ");
    $stmt->bind_param("i", $groupId);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $logs[] = "[Step 3.5] Updated item group status: Affected rows=$affectedRows";

    /* 4️⃣ Create Purchase Order Header */
    $stmt = $conn->prepare("
        INSERT INTO purchase_orders
        (rfq_id, supplier_company_id, total_amount, status, created_by)
        VALUES (?, ?, ?, 'CREATED', ?)
    ");
    $stmt->bind_param("iids", $rfqId, $supplierId, $totalAmount, $_SESSION['user_id']);
    $stmt->execute();

    $poId = $conn->insert_id;
    $logs[] = "[Step 4] Created purchase order: ID=$poId";

    /* 5️⃣ Insert PO Items */
    $stmt = $conn->prepare("
        SELECT rfq_item_id, unit_price, total_price
        FROM rfq_group_quote_items
        WHERE rfq_group_quote_id = ?
    ");
    $stmt->bind_param("i", $quoteId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($items as $item) {

        // Get quantity from rfq_items
        $qtyStmt = $conn->prepare("
            SELECT quantity FROM rfq_items WHERE id = ?
        ");
        $qtyStmt->bind_param("i", $item['rfq_item_id']);
        $qtyStmt->execute();
        $qty = $qtyStmt->get_result()->fetch_assoc()['quantity'];

        $insertItem = $conn->prepare("
            INSERT INTO purchase_order_items
            (purchase_order_id, rfq_item_id, quantity, unit_price, line_total)
            VALUES (?, ?, ?, ?, ?)
        ");

        $insertItem->bind_param(
            "iiddd",
            $poId,
            $item['rfq_item_id'],
            $qty,
            $item['unit_price'],
            $item['total_price']
        );

        $insertItem->execute();
    }
    
    $logs[] = "[Step 5] Inserted " . count($items) . " PO items";

    // Check and update RFQ status
    $statement = $conn->prepare("Select * from rfq_item_groups where rfq_id = ?");
    $statement->bind_param("i", $rfqId);
    $statement->execute();
    $groups = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $allDecided = true;
    foreach ($groups as $group) {
        if ($group['status'] != 'DECISION_MADE') {
            $allDecided = false;
            break;
        }
    }
    if ($allDecided) {
        $updateRfq = $conn->prepare("Update rfqs set status = 'QUOTED' where id = ?");
        $updateRfq->bind_param("i", $rfqId);
        $updateRfq->execute();
        $logs[] = "[Step 6] All groups decided. Updated RFQ status to QUOTED.";
    } else {
        $updateRfq = $conn->prepare("Update rfqs set status = 'ACTIVELY_AWARDING' where id = ?");
        $updateRfq->bind_param("i", $rfqId);
        $updateRfq->execute();
         $logs[] = "[Step 6] Not all groups decided. Updated RFQ status to AWARDING.";
    }
    // Commit transaction
    $conn->commit();
    $logs[] = "[Final] Transaction committed successfully";

    // Store logs in session to display on next page
    $_SESSION['award_logs'] = $logs;
    $_SESSION['award_success'] = true;

    header("Location: ../PO/po_view.php?po_id=".$poId);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $logs[] = "[ERROR] Transaction rolled back: " . $e->getMessage();
    $_SESSION['award_logs'] = $logs;
    $_SESSION['award_success'] = false;
    
    die("Error awarding supplier." . $e->getMessage());
}