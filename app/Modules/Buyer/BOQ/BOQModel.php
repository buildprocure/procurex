<?php
namespace App\Modules\Buyer\BOQ;

use App\Core\DB;
class BOQModel {
    private $db;
    public function __construct() {
        $this->db = DB::getConnection();
    }

    public function getOrCreateProject($buerComanyid, $projectName, $location,  $createdBy, $createdAt, $status='ACTIVE') {
        $stmt = mysqli_prepare($this->db, "
            SELECT id FROM projects 
            WHERE buyer_company_id = ? AND project_name = ?;
        ");
        mysqli_stmt_bind_param($stmt, "is", $buerComanyid, $projectName);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['id'];
        }

        // Insert new project
        $insertStmt = mysqli_prepare($this->db, "
            INSERT INTO projects 
            (buyer_company_id, project_name, location, status, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($insertStmt, "isssss", $buerComanyid, $projectName, $location, $status, $createdBy, $createdAt);
        mysqli_stmt_execute($insertStmt);
        return mysqli_insert_id($this->db);
    }

    public function insertBOQMaster($projectId,  $filepath, $createdBy, $createdAt, $parentBoqId = null, $versionNo = 1, $status = 'DRAFT', $is_active = 1) {
        $stmt = mysqli_prepare($this->db, "
            INSERT INTO boqs 
            (project_id, parent_boq_id, version_no, uploaded_file, status, is_active, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "iiississ", $projectId, $parentBoqId, $versionNo, $filepath, $status, $is_active, $createdBy, $createdAt);
        mysqli_stmt_execute($stmt);
        return mysqli_insert_id($this->db);
    }

    public function insertBOQItems($boqId, $rows) {
        $query = "INSERT INTO boq_items (boq_id, item_code, material_name, specification, unit, quantity, preferred_brand, remarks, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";  
        $stmt = mysqli_prepare($this->db, $query);
        foreach ($rows as $row) {
            $itemCode       = trim($row[0] ?? '');
            $materialName   = trim($row[1] ?? '');
            $spec           = trim($row[2] ?? '');
            $unit           = trim($row[3] ?? '');
            $quantity       = (int)($row[4] ?? 0);
            $preferredBrand = trim($row[5] ?? null);
            $remarks        = trim($row[6] ?? null);

            mysqli_stmt_bind_param(
                $stmt,
                "issssiss",
                $boqId,
                $itemCode,
                $materialName,
                $spec,
                $unit,
                $quantity,
                $preferredBrand,
                $remarks
            );
            mysqli_stmt_execute($stmt);
        }
    }
    public function deactivateBOQ($boqId) {
        $stmt = mysqli_prepare($this->db, "
            UPDATE boqs SET is_active = 0, status = 'FROZEN' WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $boqId);
        mysqli_stmt_execute($stmt);
    }
    public function getBuyerCompanyID ($username) {
        $stmt = mysqli_prepare($this->db, "
            SELECT company_id FROM user WHERE username = ?
        ");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        return $row ? $row['company_id'] : null;
    }
    public function getBOQItemsByBOQId($boqId) {
        $stmt = mysqli_prepare($this->db, "
            SELECT 
                item_code,
                material_name,
                specification,
                unit,
                quantity,
                preferred_brand,
                remarks,
                created_at
            FROM boq_items
            WHERE boq_id = ?
            ORDER BY id ASC
        ");
        mysqli_stmt_bind_param($stmt, "i", $boqId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
    public function getBOQListByUser($username) {
        $stmt = mysqli_prepare($this->db, "
            SELECT 
                b.id,
                b.status,
                b.created_at,
                p.project_name,
                COUNT(i.id) AS total_items
            FROM boqs b
            JOIN projects p ON p.id = b.project_id
            LEFT JOIN boq_items i ON i.boq_id = b.id
            WHERE b.created_by = ? AND b.is_active = 1 AND b.status != 'FROZEN'
            GROUP BY b.id
            ORDER BY b.created_at DESC
        ");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    public function checkBOQOwnership($boqId, $username) {
        $stmt = mysqli_prepare($this->db, "
            SELECT COUNT(*) as count
            FROM boqs b
            JOIN projects p ON b.project_id = p.id
            JOIN user u ON p.buyer_company_id = u.company_id
            WHERE b.id = ? AND u.username = ?
        ");
        mysqli_stmt_bind_param($stmt, "is", $boqId, $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        return $row['count'] > 0;
    }
    public function getBOQByID($boqId) {
        $stmt = mysqli_prepare($this->db, "
            SELECT * FROM boqs WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $boqId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_assoc($result);
    }
    // Save edited BOQ items
    public function saveEditedBOQ($boqId, $items, $username) {
        // Get old BOQ details
        $oldBoq = $this->getBOQByID($boqId);
        if (!$oldBoq) {
            throw new \Exception("BOQ not found");
        }
        // Deactivate old BOQ
        $this->deactivateBOQ($boqId);
      
        // Create new BOQ version
        $newVersion = $oldBoq['version_no'] + 1;
        $parentId = $oldBoq['parent_boq_id'] ?? $oldBoq['id'];

        $newBoqId = $this->insertBOQMaster(
            $oldBoq['project_id'],
            $oldBoq['uploaded_file'],
            $oldBoq['created_by'],
            date('Y-m-d H:i:s'),
            $parentId,
            $newVersion,
            'DRAFT',
            1
        );

        // Insert edited items
        foreach ($items as $row) {
            $this->insertBOQItems($newBoqId, [ 
                [
                    $row['item_code'],
                    $row['material_name'],
                    $row['specification'],
                    $row['unit'],
                    $row['quantity'],
                    $row['preferred_brand'],
                    $row['remarks']
                ]
            ]);
        }

        return $newBoqId;
    }
    // Lock BOQ
    public function lockBOQ($boqId, $username) {
        $stmt = mysqli_prepare($this->db, "
            UPDATE boqs SET status = 'LOCKED', locked_by = ?, locked_at = NOW() WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt, "si", $username, $boqId);
        mysqli_stmt_execute($stmt);
    }
    // Delete BOQ
    public function deleteBOQ($boqId, $username) {
        //Update status to deleted do not actually delete
        $stmt = mysqli_prepare($this->db, "
            UPDATE boqs SET is_active = 0, status = 'DELETED', deleted_by = ?, deleted_at = NOW() WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt, "si", $username, $boqId);
        mysqli_stmt_execute($stmt);
    }
}