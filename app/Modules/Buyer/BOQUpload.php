<?php
namespace App\Modules\Buyer;

use App\Core\DB;
use App\Core\FileUploader;
use App\Core\ExcelParser;
use Exception;

class BOQUpload
{
    public static function handle(array $post, array $files): void
    {
        self::validate($post, $files);

        $db = DB::getConnection();

        mysqli_begin_transaction($db);

        try {
            // Upload file
            $fileUploader = new FileUploader();
            $filePath = $fileUploader->uploadBOQ($files['boq_file']);

             // 3️⃣ Parse BOQ Excel
            $excelParser = new ExcelParser();
            $rows = $excelParser->parseBOQ($filePath);
            if (empty($rows)) {
                throw new Exception("BOQ file is empty or invalid");
            }


            // 1️⃣ Insert / get project_id
            $projectId = self::getOrCreateProject($db, $post['project_name'], $rows, $_SESSION['username']);

            // 2️⃣ Insert BOQ master
            $boqId = self::insertBOQMaster($db, $projectId, $filePath, $_SESSION['username']);
     
            // 4️⃣ Insert BOQ items
            self::insertBOQItems($db, $boqId, $rows);

            mysqli_commit($db);
        } catch (Exception $e) {
            mysqli_rollback($db);
            throw $e;
        }
    }

    private static function validate(array $post, array $files): void
    {
        if (empty($post['project_name'])) {
            throw new Exception("Project name is required");
        }

        if (!isset($files['boq_file']) || $files['boq_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("BOQ Excel file is required");
        }

        $ext = pathinfo($files['boq_file']['name'], PATHINFO_EXTENSION);
        if (!in_array($ext, ['xls', 'xlsx'])) {
            throw new Exception("Only Excel files are allowed");
        }
    }

    private static function getOrCreateProject($db, string $projectName, $rows, string $username, string $status = 'ACTIVE'): int
    {
        // Get buyer_company_id
        $company_id = self::getCompanyIdByUsername($db, $username);
        $location = $rows[0]['location'] ?? 'Unknown';
        // Check if project exists
        $stmt = mysqli_prepare($db, "SELECT id FROM projects WHERE project_name = ? AND buyer_company_id = ?");
        mysqli_stmt_bind_param($stmt, "si", $projectName, $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $projectId);
        mysqli_stmt_fetch($stmt);

        if ($projectId) {
            return $projectId;
        }

        // Insert new project
        $stmt = mysqli_prepare($db, "INSERT INTO projects (buyer_company_id, project_name, location, status, created_by, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "issss", $company_id,$projectName, $location, $status, $username);
        mysqli_stmt_execute($stmt);

        return mysqli_insert_id($db);
    }

    private static function insertBOQMaster($db, int $projectId, string $filePath, string $username, string $status = 'DRAFT'): int
    {
        $stmt = mysqli_prepare($db, "
            INSERT INTO boqs
            (project_id, uploaded_file, status, created_by, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");

        mysqli_stmt_bind_param($stmt, "isss", $projectId, $filePath, $status, $username);
        mysqli_stmt_execute($stmt);

        return mysqli_insert_id($db);
    }

    private static function insertBOQItems($db, int $boqId, array $rows): void
    {
        // echo "<pre>";
        // var_dump($rows);
        // exit;
        $stmt = mysqli_prepare($db, "
            INSERT INTO boq_items
            (boq_id, item_code, material_name, specification, unit, quantity, preferred_brand, remarks, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

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
    public static function getCompanyIdByUsername($db, $username): int
    {
        $stmt = mysqli_prepare($db, "SELECT company_id FROM user WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $companyId);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        if (!$companyId) {
            throw new Exception("User company not found");
        }

        return $companyId;
    }
}
