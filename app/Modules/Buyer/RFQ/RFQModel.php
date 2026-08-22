<?php
namespace App\Modules\Buyer\RFQ;

use App\Core\DB;
use App\Core\InviteToken;
use App\Modules\Notifications\RFQNotifier;

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
     *
     * @deprecated Kept only in case older callers still depend on
     * group-level rollup. New code should use
     * updateRFQStatusIfAllItemsDecided() instead, since item groups carry
     * no award decision - see awardItems().
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

    /**
     * Set a single line item aside for a later decision. Does not affect
     * any other item on the RFQ, and does not change award_status - the
     * item can still be awarded later, this only clears it from an
     * "items still needing a decision now" view if the UI chooses to
     * filter on it.
     */
    public function postponeItem(int $rfqId, int $itemId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE rfq_items
            SET line_status = 'POSTPONED'
            WHERE id = ? AND rfq_id = ?
        ");
        $stmt->bind_param("ii", $itemId, $rfqId);
        $stmt->execute();
    }

    /**
     * Close a single line item with no award. Terminal: the item will no
     * longer be offered an award panel once closed.
     */
    public function closeItem(int $rfqId, int $itemId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE rfq_items
            SET line_status = 'CLOSED_NO_AWARD'
            WHERE id = ? AND rfq_id = ?
        ");
        $stmt->bind_param("ii", $itemId, $rfqId);
        $stmt->execute();
    }

    /**
     * Re-evaluate the rfq status based on the current state of its line
     * items, mirroring updateRFQStatusIfAllGroupsDecided() but scoped to
     * items instead of groups - items are the real unit of decision now
     * (see awardItems()); groups only ever existed to route the RFQ to
     * suppliers.
     *
     * An item counts as "decided" once it is FULLY_AWARDED or its
     * line_status is CLOSED_NO_AWARD. POSTPONED items are not terminal -
     * they still need a decision eventually.
     */
    public function updateRFQStatusIfAllItemsDecided(int $rfqId): void
    {
        $stmt = $this->conn->prepare("
            SELECT award_status, line_status FROM rfq_items WHERE rfq_id = ?
        ");
        $stmt->bind_param("i", $rfqId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (empty($rows)) {
            return;
        }

        $terminalCount = 0;
        $totalCount = count($rows);

        foreach ($rows as $row) {
            if ($row['award_status'] === 'FULLY_AWARDED' || $row['line_status'] === 'CLOSED_NO_AWARD') {
                $terminalCount++;
            }
        }

        if ($terminalCount === 0) {
            return;
        } elseif ($terminalCount === $totalCount) {
            $this->updateStatus('rfqs', $rfqId, 'DECIDED');
        } else {
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
    /**
     * Match suppliers to each RFQ group, create invitations, and email them.
     *
     * Behaviour notes:
     *  - Each invitation gets its own single-purpose token so the supplier can
     *    quote without an account. Only the hash is persisted.
     *  - Persistence and delivery are separated. Rows are committed first;
     *    email is attempted afterwards. An SMTP outage therefore leaves
     *    invitations in notify_status='PENDING' for the retry cron rather
     *    than losing them.
     *  - Suppliers already invited to a group are skipped, so re-running is
     *    safe and nobody gets the same RFQ twice.
     *
     * @return array{invited:int, sent:int, failed:int, skipped:int}
     */
    public function autoAssignSuppliers(int $rfqId): array
    {
        $conn = DB::getConnection();

        $stats = ['invited' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];

        // Deadline drives token expiry.
        $dlStmt = $conn->prepare("SELECT quote_deadline FROM rfqs WHERE id = ?");
        $dlStmt->bind_param("i", $rfqId);
        $dlStmt->execute();
        $rfqRow = $dlStmt->get_result()->fetch_assoc();
        $quoteDeadline = $rfqRow['quote_deadline'] ?? null;
        $expiresAt = InviteToken::expiryFor($quoteDeadline);

        // All groups on this RFQ.
        $stmt = $conn->prepare("
            SELECT id, item_group_id
            FROM rfq_item_groups
            WHERE rfq_id = ?
        ");
        $stmt->bind_param("i", $rfqId);
        $stmt->execute();
        $groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Collect (invitationId => plaintext token) to email after commit.
        $pending = [];

        foreach ($groups as $group) {

            // Suppliers serving this category.
            $supStmt = $conn->prepare("
                SELECT sig.supplier_company_id
                FROM supplier_item_groups sig
                JOIN companies sc ON sc.id = sig.supplier_company_id
                WHERE sig.item_group_id = ?
                  AND sc.type = 'Supplier'
            ");
            $supStmt->bind_param("i", $group['item_group_id']);
            $supStmt->execute();
            $suppliers = $supStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($suppliers as $supplier) {

                $supplierId = (int) $supplier['supplier_company_id'];
                $groupId    = (int) $group['id'];

                // Skip if already invited to this group.
                $dupe = $conn->prepare("
                    SELECT id FROM rfq_group_suppliers
                    WHERE rfq_item_group_id = ? AND supplier_company_id = ?
                    LIMIT 1
                ");
                $dupe->bind_param("ii", $groupId, $supplierId);
                $dupe->execute();

                if ($dupe->get_result()->fetch_assoc()) {
                    $stats['skipped']++;
                    continue;
                }

                $token     = InviteToken::generate();
                $tokenHash = InviteToken::hash($token);

                $insert = $conn->prepare("
                    INSERT INTO rfq_group_suppliers
                        (rfq_item_group_id, supplier_company_id,
                         invite_token_hash, invite_expires_at, notify_status)
                    VALUES (?, ?, ?, ?, 'PENDING')
                ");
                $insert->bind_param(
                    "iiss",
                    $groupId,
                    $supplierId,
                    $tokenHash,
                    $expiresAt
                );

                if (!$insert->execute()) {
                    error_log(
                        "[autoAssignSuppliers] Insert failed for group {$groupId}, "
                        . "supplier {$supplierId}: {$conn->error}"
                    );
                    $stats['failed']++;
                    continue;
                }

                $pending[(int) $conn->insert_id] = $token;
                $stats['invited']++;
            }
        }

        // Delivery happens after all rows are safely written.
        $notifier = new RFQNotifier();

        foreach ($pending as $invitationId => $token) {
            try {
                if ($notifier->notifyInvitation($invitationId, $token)) {
                    $stats['sent']++;
                } else {
                    $stats['failed']++;
                }
            } catch (\Throwable $e) {
                error_log(
                    "[autoAssignSuppliers] Notify failed for invitation "
                    . "{$invitationId}: " . $e->getMessage()
                );
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * Award part or all of one or more RFQ line items to suppliers.
     *
     * Replaces the old group-level awardSupplier(). An item's quantity
     * can be split across multiple suppliers across separate calls, or
     * in one call:
     *
     *   awardItems($rfqId, [
     *       ['rfq_item_id' => 5, 'quote_id' => 12, 'quantity' => 600],
     *       ['rfq_item_id' => 5, 'quote_id' => 15, 'quantity' => 400],
     *   ], $userId);
     *
     * Item groups are not read or written here. They exist only to route
     * the RFQ to suppliers; they carry no award decision.
     *
     * For each award line, in one transaction:
     *   1. Lock the rfq_items row (SELECT ... FOR UPDATE) - this is what
     *      makes concurrent awards on the same item serialise instead of
     *      racing past each other's remaining-quantity check.
     *   2. Verify the quote actually priced this item, belongs to this
     *      RFQ, and was submitted (not a draft).
     *   3. Verify the requested quantity is positive and does not exceed
     *      what's left unawarded on the item.
     *   4. Record the award, bump the item's running total, and copy a
     *      frozen snapshot (name/spec/unit/qty/price) onto that
     *      supplier's purchase order - creating it on first award,
     *      reusing it on every award after.
     *
     * @param array<int,array{rfq_item_id:int,quote_id:int,quantity:float}> $awards
     * @return array{po_ids:int[], logs:string[]}
     */
    public function awardItems(int $rfqId, array $awards, int $createdBy): array
    {
        if (empty($awards)) {
            throw new \InvalidArgumentException('No awards supplied.');
        }

        $conn = $this->conn;
        $logs = [];
        $conn->begin_transaction();

        try {
            // supplier_company_id => accumulated PO line data for this call
            $poLines = [];

            foreach ($awards as $i => $award) {
                $itemId  = (int) ($award['rfq_item_id'] ?? 0);
                $quoteId = (int) ($award['quote_id'] ?? 0);
                $qty     = (float) ($award['quantity'] ?? 0);

                if ($itemId <= 0 || $quoteId <= 0) {
                    throw new \Exception("Award #{$i}: rfq_item_id and quote_id are required.");
                }
                if ($qty <= 0) {
                    throw new \Exception("Award #{$i}: quantity must be greater than zero.");
                }

                // Lock the item row for the rest of this transaction.
                $stmt = $conn->prepare("
                    SELECT id, material_name, specification, unit,
                           quantity, awarded_quantity
                    FROM rfq_items
                    WHERE id = ? AND rfq_id = ?
                    FOR UPDATE
                ");
                $stmt->bind_param('ii', $itemId, $rfqId);
                $stmt->execute();
                $item = $stmt->get_result()->fetch_assoc();

                if (!$item) {
                    throw new \Exception(
                        "Award #{$i}: item {$itemId} does not belong to RFQ {$rfqId}."
                    );
                }

                $remaining = (float) $item['quantity'] - (float) $item['awarded_quantity'];

                if ($qty > $remaining + 0.0001) {
                    throw new \Exception(
                        "Award #{$i}: requested {$qty} {$item['unit']} for " .
                        "\"{$item['material_name']}\" but only {$remaining} remains unawarded."
                    );
                }

                // The quote must have priced THIS item, on THIS rfq, and
                // must have been actually submitted - not a draft.
                $stmt = $conn->prepare("
                    SELECT rgq.supplier_company_id, rgqi.unit_price
                    FROM rfq_group_quotes rgq
                    JOIN rfq_group_quote_items rgqi
                        ON rgqi.rfq_group_quote_id = rgq.id
                       AND rgqi.rfq_item_id = ?
                    JOIN rfq_item_groups rig ON rig.id = rgq.rfq_item_group_id
                    WHERE rgq.id = ?
                      AND rig.rfq_id = ?
                      AND rgq.status = 'SUBMITTED'
                ");
                $stmt->bind_param('iii', $itemId, $quoteId, $rfqId);
                $stmt->execute();
                $quote = $stmt->get_result()->fetch_assoc();

                if (!$quote) {
                    throw new \Exception(
                        "Award #{$i}: quote {$quoteId} has no submitted price for item {$itemId} on RFQ {$rfqId}."
                    );
                }

                $supplierId = (int) $quote['supplier_company_id'];
                $unitPrice  = (float) $quote['unit_price'];
                $lineTotal  = round($qty * $unitPrice, 2);

                // Record the award.
                $stmt = $conn->prepare("
                    INSERT INTO rfq_item_awards
                        (rfq_item_id, rfq_group_quote_id, supplier_company_id,
                         awarded_quantity, unit_price, line_total, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    'iiidddi', $itemId, $quoteId, $supplierId,
                    $qty, $unitPrice, $lineTotal, $createdBy
                );
                $stmt->execute();
                $awardId = (int) $conn->insert_id;

                // Advance the item's running total and status.
                $newAwarded = (float) $item['awarded_quantity'] + $qty;
                $newStatus  = ($newAwarded + 0.0001 >= (float) $item['quantity'])
                    ? 'FULLY_AWARDED'
                    : 'PARTIALLY_AWARDED';

                $stmt = $conn->prepare("
                    UPDATE rfq_items
                    SET awarded_quantity = ?, award_status = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('dsi', $newAwarded, $newStatus, $itemId);
                $stmt->execute();

                $logs[] = "[Award #{$i}] Item {$itemId} ({$item['material_name']}): " .
                    "{$qty} {$item['unit']} @ {$unitPrice} = {$lineTotal} -> supplier {$supplierId}";

                $poLines[$supplierId][] = [
                    'award_id'      => $awardId,
                    'rfq_item_id'   => $itemId,
                    'material_name' => $item['material_name'],
                    'specification' => $item['specification'],
                    'unit'          => $item['unit'],
                    'quantity'      => $qty,
                    'unit_price'    => $unitPrice,
                    'line_total'    => $lineTotal,
                ];
            }

            // One PO per supplier touched in this call, reusing an
            // existing PO for (rfq, supplier) if one already exists from
            // an earlier award action.
            $poIds = [];

            foreach ($poLines as $supplierId => $lines) {
                $stmt = $conn->prepare("
                    SELECT id FROM purchase_orders
                    WHERE rfq_id = ? AND supplier_company_id = ?
                ");
                $stmt->bind_param('ii', $rfqId, $supplierId);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();

                if ($existing) {
                    $poId = (int) $existing['id'];
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO purchase_orders
                            (rfq_id, supplier_company_id, total_amount, status, created_by)
                        VALUES (?, ?, 0, 'CREATED', ?)
                    ");
                    $stmt->bind_param('iii', $rfqId, $supplierId, $createdBy);
                    $stmt->execute();
                    $poId = (int) $conn->insert_id;
                    $logs[] = "[PO] Created purchase order {$poId} for supplier {$supplierId}";
                }

                $insertItem = $conn->prepare("
                    INSERT INTO purchase_order_items
                        (purchase_order_id, rfq_item_id, material_name, specification,
                         unit, quantity, unit_price, line_total, rfq_item_award_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $updateAward = $conn->prepare("
                    UPDATE rfq_item_awards SET purchase_order_id = ? WHERE id = ?
                ");

                foreach ($lines as $line) {
                    $insertItem->bind_param(
                        'iisssdddi',
                        $poId,
                        $line['rfq_item_id'],
                        $line['material_name'],
                        $line['specification'],
                        $line['unit'],
                        $line['quantity'],
                        $line['unit_price'],
                        $line['line_total'],
                        $line['award_id']
                    );
                    $insertItem->execute();

                    $updateAward->bind_param('ii', $poId, $line['award_id']);
                    $updateAward->execute();
                }

                // Recompute from the PO's actual lines rather than
                // accumulating deltas, so total_amount can never drift
                // from what purchase_order_items actually sums to.
                $stmt = $conn->prepare("
                    UPDATE purchase_orders po
                    SET total_amount = (
                        SELECT COALESCE(SUM(line_total), 0)
                        FROM purchase_order_items
                        WHERE purchase_order_id = po.id
                    )
                    WHERE po.id = ?
                ");
                $stmt->bind_param('i', $poId);
                $stmt->execute();

                $poIds[] = $poId;
                $logs[] = "[PO] Applied " . count($lines) . " line(s) to PO {$poId}";
            }

            $conn->commit();
            $logs[] = '[Final] Transaction committed successfully';

            return ['po_ids' => $poIds, 'logs' => $logs];

        } catch (\Throwable $e) {
            $conn->rollback();
            $logs[] = '[ERROR] Transaction rolled back: ' . $e->getMessage();
            throw new \Exception(implode("\n", $logs));
        }
    }
}
