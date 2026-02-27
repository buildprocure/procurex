<?php
namespace App\Modules\Buyer\RFQ;

use App\Core\DB;

class RFQModel {

    private \mysqli $conn;

    public function __construct() {
        $this->conn = DB::getConnection();
    }

    public function isBOQLocked(int $boqId): bool {
        $stmt = $this->conn->prepare("
            SELECT id FROM boqs WHERE id = ? AND status = 'LOCKED'
        ");
        $stmt->bind_param("i", $boqId);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_row();
    }

    public function createRFQ(
        int $boqId,
        string $deliveryLocation,
        string $instructions,
        string $requiredDeliveryDate,
        string $quoteDeadline,
        int $createdBy
    ): int {

        $projectId = $this->getProjectIdFromBOQ($boqId);
        $rfqTitle = 'RFQ of BOQ ' . $boqId;
        $status = 'DRAFT';

        $stmt = $this->conn->prepare("
            INSERT INTO rfqs
            (project_id, boq_id, rfq_title, instructions, delivery_location, required_delivery_date, quote_deadline, status, created_user_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param(
            "iissssssi",
            $projectId,
            $boqId,
            $rfqTitle,
            $instructions,
            $deliveryLocation,
            $requiredDeliveryDate,
            $quoteDeadline,
            $status,
            $createdBy
        );
        $stmt->execute();

        return $stmt->insert_id;
    }

    private function getProjectIdFromBOQ(int $boqId): int {
        $stmt = $this->conn->prepare("SELECT project_id FROM boqs WHERE id = ?");
        $stmt->bind_param("i", $boqId);
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_row()[0];
    }

    public function copyBOQItemsToRFQ(int $boqId, int $rfqId): void {

        $stmt = $this->conn->prepare("
            INSERT INTO rfq_items
            (rfq_id, boq_item_id, material_name, specification, unit, quantity)
            SELECT ?, id, material_name, specification, unit, quantity
            FROM boq_items
            WHERE boq_id = ?
        ");
        $stmt->bind_param("ii", $rfqId, $boqId);
        $stmt->execute();
        
    }
    public function updateStatus(string $table, int $id, string $status): void {
        $stmt = $this->conn->prepare("UPDATE $table SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
    }
    /**
     * Return all RFQs created by any user belonging to the same company
     * as the supplied buyer.  The RFQ table only stores the creating
     * user's id, so we have to join back to the user table to perform
     * the company lookup.
     *
     * @param int $buyerId user id of the buyer making the request
     * @return array list of RFQ rows sorted by creation date ascending
     */
    public function getRFQsByBuyer(int $buyerId): array {
        // Join against the user table to filter by company_id.  We could
        // also fetch the company_id first via a separate query, but a
        // single prepared statement keeps things concise.
        $stmt = $this->conn->prepare(
            "SELECT r.*
             FROM rfqs r
             JOIN `user` u ON r.created_user_id = u.id
             WHERE u.company_id = (
                 SELECT company_id FROM `user` WHERE id = ?
             )
             ORDER BY r.created_at ASC"
        );
        $stmt->bind_param('i', $buyerId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Re-evaluate the rfq status based on the current statuses of its
     * item groups.  Three-state logic:
     *
     * 1. DRAFT: None of the groups have been awarded or closed.
     * 2. ACTIVELY_AWARDING: At least one group is DECISION_MADE or CLOSED_NO_AWARD,
     *    but not all groups are in those terminal states.
     * 3. DECIDED: Every group is either DECISION_MADE or CLOSED_NO_AWARD.
     *
     * NOTE: the rfqs.status enum must include "DRAFT", "ACTIVELY_AWARDING",
     * and "DECIDED" for this method to succeed.
     */
    public function updateRFQStatusIfAllGroupsDecided(int $rfqId): void
    {
        // fetch statuses for all groups
        $stmt = $this->conn->prepare("SELECT status FROM rfq_item_groups WHERE rfq_id = ?");
        $stmt->bind_param("i", $rfqId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // nothing to do if there are no groups
        if (empty($rows)) {
            return;
        }

        $terminalCount = 0;
        $totalCount = count($rows);

        // count how many groups are in terminal states (DECISION_MADE or CLOSED_NO_AWARD)
        foreach ($rows as $row) {
            $st = $row['status'];
            if ($st === 'DECISION_MADE' || $st === 'CLOSED_NO_AWARD') {
                $terminalCount++;
            }
        }

        // determine target status based on terminal group count
        if ($terminalCount === 0) {
            // no groups awarded/closed yet - keep as it is - early return
            return;
        } elseif ($terminalCount === $totalCount) {
            // all groups are awarded/closed - mark as DECIDED
            $this->updateStatus('rfqs', $rfqId, 'DECIDED');
        } else {
            // some (but not all) groups are awarded/closed - mark as ACTIVELY_AWARDING
            $this->updateStatus('rfqs', $rfqId, 'ACTIVELY_AWARDING');
        }
    }
    //addCreate item grouping and supplier assignment functions here if needed
   public function autoCreateGroups(int $rfqId): void
{
    $conn = DB::getConnection();
    $conn->begin_transaction();

    try {

        // Fetch ungrouped items
        $stmt = $conn->prepare("
            SELECT id, material_name
            FROM rfq_items
            WHERE rfq_id = ? AND rfq_item_group_id IS NULL
        ");
        $stmt->bind_param("i", $rfqId);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($items)) {
            $conn->commit();
            return;
        }

        // Load existing RFQ groups
        $existingGroups = [];
        $stmt = $conn->prepare("
            SELECT id, item_group_id
            FROM rfq_item_groups
            WHERE rfq_id = ?
        ");
        $stmt->bind_param("i", $rfqId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $existingGroups[$row['item_group_id']] = $row['id'];
        }

        // Group items in memory
        $groupedItems = [];

        foreach ($items as $item) {
            $groupId = $this->detectGroupId($item['material_name']);
            $groupedItems[$groupId][] = $item['id'];
        }

        // Insert missing groups
        foreach ($groupedItems as $groupId => $itemIds) {

            if (!isset($existingGroups[$groupId])) {
                $insert = $conn->prepare("
                    INSERT INTO rfq_item_groups (rfq_id, item_group_id)
                    VALUES (?, ?)
                ");
                $insert->bind_param("ii", $rfqId, $groupId);
                $insert->execute();
                $existingGroups[$groupId] = $conn->insert_id;
            }

            $rfqGroupId = $existingGroups[$groupId];

            // Bulk update items
            $ids = implode(',', $itemIds);
            $conn->query("
                UPDATE rfq_items
                SET rfq_item_group_id = $rfqGroupId
                WHERE id IN ($ids)
            ");
        }

        $conn->commit();

    } catch (\Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

    private function detectGroupId(string $materialName): int
    {
        $material = strtolower($materialName);
        $civilMaterials = ['cement', 'sand', 'gravel', 'concrete', 'steel bar', 'rebar'];
        $electricalMaterials = ['wire', 'cable', 'light', 'switch', 'socket'];
        $plumbingMaterials = ['pipe', 'valve', 'fitting', 'fixture'];

        foreach ($civilMaterials as $keyword) {
            if (strpos($material, $keyword) !== false) {
                return 2; // Civil
            }

        }
        foreach ($electricalMaterials as $keyword) {
            if (strpos($material, $keyword) !== false) {
                return 3; // Electrical
            }
        }
        foreach ($plumbingMaterials as $keyword) {
            if (strpos($material, $keyword) !== false) {
                return 4; // Plumbing
            }
        }
        return 1; // General
    }
    public function autoAssignSuppliers(int $rfqId): void
{
    $conn = DB::getConnection();

    // Get all RFQ groups
    $stmt = $conn->prepare("
        SELECT id, item_group_id 
        FROM rfq_item_groups 
        WHERE rfq_id = ?
    ");
    $stmt->bind_param("i", $rfqId);
    $stmt->execute();
    $groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($groups as $group) {

        // Get suppliers matching this category
        $supStmt = $conn->prepare("
            SELECT supplier_company_id
            FROM supplier_item_groups
            WHERE item_group_id = ?
        ");
        $supStmt->bind_param("i", $group['item_group_id']);
        $supStmt->execute();
        $suppliers = $supStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($suppliers as $supplier) {

            // Insert supplier invitation
            $insert = $conn->prepare("
                INSERT INTO rfq_group_suppliers 
                (rfq_item_group_id, supplier_company_id)
                VALUES (?, ?)
            ");
            $insert->bind_param(
                "ii",
                $group['id'],
                $supplier['supplier_company_id']
            );
            $insert->execute();
        }
    }
}

    /**
     * Award a supplier for a group quote and create the purchase order.
     * Returns array with keys: po_id and logs
     */
    public function awardSupplier(int $rfqId, int $groupId, int $quoteId, int $createdBy): array
    {
        $conn = $this->conn;
        $logs = [];
        $conn->begin_transaction();

        try {
            // 1. Get awarded quote info
            $stmt = $conn->prepare("SELECT supplier_company_id, total_amount FROM rfq_group_quotes WHERE id = ?");
            $stmt->bind_param("i", $quoteId);
            $stmt->execute();
            $quote = $stmt->get_result()->fetch_assoc();
            if (!$quote) {
                throw new \Exception("Quote not found for ID: $quoteId");
            }
            $supplierId = (int)$quote['supplier_company_id'];
            $totalAmount = (float)$quote['total_amount'];
            $logs[] = "[Step 1] Retrieved quote: ID=$quoteId, Supplier=$supplierId, Amount=$totalAmount";

            // 2. Mark this quote AWARDED
            $stmt = $conn->prepare("UPDATE rfq_group_quotes SET decision_status = 'AWARDED' WHERE id = ?");
            $stmt->bind_param("i", $quoteId);
            $stmt->execute();
            $logs[] = "[Step 2] Marked quote as AWARDED: Affected rows=" . $stmt->affected_rows;

            // 3. Mark others as LOST
            $stmt = $conn->prepare("UPDATE rfq_group_quotes SET decision_status = 'LOST' WHERE rfq_item_group_id = ? AND id != ?");
            $stmt->bind_param("ii", $groupId, $quoteId);
            $stmt->execute();
            $logs[] = "[Step 3] Marked competing quotes as LOST: Affected rows=" . $stmt->affected_rows;

            // Update rfq item group status
            $stmt = $conn->prepare("UPDATE rfq_item_groups SET status = 'DECISION_MADE' WHERE id = ?");
            $stmt->bind_param("i", $groupId);
            $stmt->execute();
            $logs[] = "[Step 3.5] Updated item group status: Affected rows=" . $stmt->affected_rows;

            // 4. Create PO header
            $stmt = $conn->prepare("INSERT INTO purchase_orders (rfq_id, supplier_company_id, total_amount, status, created_by) VALUES (?, ?, ?, 'CREATED', ?)");
            $stmt->bind_param("iidi", $rfqId, $supplierId, $totalAmount, $createdBy);
            $stmt->execute();
            $poId = $conn->insert_id;
            $logs[] = "[Step 4] Created purchase order: ID=$poId";

            // 5. Insert PO items (FULL SNAPSHOT COPY)
            $stmt = $conn->prepare("
                SELECT 
                    rqi.id,
                    rqi.material_name,
                    rqi.specification,
                    rqi.unit,
                    rqi.quantity,
                    rgqi.unit_price,
                    rgqi.total_price
                FROM rfq_group_quote_items rgqi
                JOIN rfq_items rqi ON rqi.id = rgqi.rfq_item_id
                WHERE rgqi.rfq_group_quote_id = ?
            ");
            $stmt->bind_param("i", $quoteId);
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $insertItem = $conn->prepare("
                INSERT INTO purchase_order_items 
                (purchase_order_id, rfq_item_id, material_name, specification, unit, quantity, unit_price, line_total)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                $insertItem->bind_param(
                    "iisssidd",
                    $poId,
                    $item['id'],
                    $item['material_name'],
                    $item['specification'],
                    $item['unit'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price']
                );
                $insertItem->execute();
            }

            $logs[] = "[Step 5] Inserted " . count($items) . " PO snapshot items";
            // 6. Check and update RFQ status
            // after awarding the current group, verify whether every item group
            // for this RFQ has been handled. the helper method considers both
            // `DECISION_MADE` and `CLOSED_NO_AWARD` as terminal states.
            $this->updateRFQStatusIfAllGroupsDecided($rfqId);
            $logs[] = "[Step 6] Checked all group statuses; RFQ may have been marked DECIDED.";

            $conn->commit();
            $logs[] = "[Final] Transaction committed successfully";

            return ['po_id' => $poId, 'logs' => $logs];

        } catch (\Throwable $e) {
            $conn->rollback();
            $logs[] = "[ERROR] Transaction rolled back: " . $e->getMessage(). "\n" . $e->getTraceAsString(). "on line " . $e->getLine() . " in file " . $e->getFile() .
            "\n" . "Input params: RFQ ID=$rfqId, Group ID=$groupId, Quote ID=$quoteId, User ID=$createdBy";
            throw new \Exception(implode("\n", $logs));
        }
    }
}