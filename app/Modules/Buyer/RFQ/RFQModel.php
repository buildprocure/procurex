<?php
namespace App\Modules\Buyer\RFQ;
use App\Core\DB;
class RFQModel {
    private $db;
    public function __construct() {
        $this->db = DB::getConnection();
    }

    public function createRFQFromBOQ(int $boqId, int $projectId, string $title, string $username): int
    {
        $stmt = mysqli_prepare($this->db, "
            INSERT INTO rfqs (boq_id, project_id, title, created_by, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        mysqli_stmt_bind_param($stmt, "iiss", $boqId, $projectId, $title, $username);
        mysqli_stmt_execute($stmt);

        return mysqli_insert_id($this->db);
    }
    public function copyBOQItemsToRFQ(int $boqId, int $rfqId)
    {
        $stmt = mysqli_prepare($this->db, "
            INSERT INTO rfq_items (rfq_id, item_code, material_name, specification, unit, quantity, created_at)
            SELECT ?, item_code, material_name, specification, unit, quantity, NOW()
            FROM boq_items
            WHERE boq_id = ?
        ");
        mysqli_stmt_bind_param($stmt, "ii", $rfqId, $boqId);
        mysqli_stmt_execute($stmt);
    }
    // Check if BOQ is published
    public function isBOQPublished(int $boqId): bool
    {
        $stmt = mysqli_prepare($this->db, "
            SELECT status FROM boqs WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $boqId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $status);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        return $status === 'PUBLISHED';
    }
    // Get project ID from BOQ ID
    public function getProjectIdFromBOQ(int $boqId): int
    {
        $stmt = mysqli_prepare($this->db, "
            SELECT project_id FROM boqs WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $boqId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $projectId);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        return $projectId;
    }

}
