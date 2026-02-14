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
    public function getRFQsByBuyer(int $buyerId): array {
        $stmt = $this->conn->prepare("SELECT * FROM rfqs WHERE created_user_id = ? ORDER BY created_at ASC");
        $stmt->bind_param('i', $buyerId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
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
                return 1; // Civil
            }

        }
        foreach ($electricalMaterials as $keyword) {
            if (strpos($material, $keyword) !== false) {
                return 2; // Electrical
            }
        }
        foreach ($plumbingMaterials as $keyword) {
            if (strpos($material, $keyword) !== false) {
                return 3; // Plumbing
            }
        }
        return 4; // General
    }
}