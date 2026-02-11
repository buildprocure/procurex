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
        $status = 'CREATED';

        $stmt = $this->conn->prepare("
            INSERT INTO rfqs
            (project_id, boq_id, rfq_title, instructions, delivery_location, required_delivery_date, quote_deadline, process_stage, created_user_id, created_at)
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
    public function updateBOQStatus(int $boqId, string $status): void {
        $stmt = $this->conn->prepare("UPDATE boqs SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $boqId);
        $stmt->execute();
    }
    public function getRFQsByBuyer(int $buyerId): array {
        $stmt = $this->conn->prepare("SELECT * FROM rfqs WHERE created_user_id = ? ORDER BY created_at ASC");
        $stmt->bind_param('i', $buyerId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
