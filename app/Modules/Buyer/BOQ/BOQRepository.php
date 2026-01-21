<?php
namespace App\Modules\Buyer\BOQ;

use App\Core\DB;
use App\Modules\Buyer\BOQupload;
class BOQRepository {
       private $db;

    public function __construct() {
        $this->db = DB::getConnection();
    }

    public function create(array $data) {
         $stmt = mysqli_prepare($this->db, "
            INSERT INTO boqs 
            (project_id, uploaded_file, status, created_by)
            VALUES (?, ?, 'DRAFT', ?)
        ");

        mysqli_stmt_bind_param(
            $stmt,
            "iss",
            $data['project_id'],
            $data['uploaded_file'],
            $data['created_by']
        );

        mysqli_stmt_execute($stmt);
        return mysqli_insert_id($this->db);
    }

    public function getByUser($username) {
        $boqupload = new BOQUpload();
        $companyId = $boqupload->getCompanyIdByUsername($this->db, $username);

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
            WHERE b.created_by = ?
            GROUP BY b.id
            ORDER BY b.created_at DESC
        ");

        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}
?>