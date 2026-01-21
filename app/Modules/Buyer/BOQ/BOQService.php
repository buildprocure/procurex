<?php

use App\Core\FileUploader;
use App\Core\ExcelParser;
class BOQService {
    
    public function uploadAndParse($file, $projectId, $userId) {

        $uploader = new FileUploader();
        $parser   = new ExcelParser();
        $boqRepo  = new BOQRepository();
        $itemRepo = new BOQRepository();

        // 1. Upload file
        $filename = $uploader->uploadBOQ($file);

        // 2. Create BOQ header
        $boqId = $boqRepo->create([
            'project_id' => $projectId,
            'uploaded_file' => $filename,
            'created_by' => $userId
        ]);

        // 3. Parse Excel
        $rows = $parser->parseBOQ(__DIR__ . "/../../../storage/uploads/boq/$filename");

        // 4. Save items
        foreach ($rows as $row) {
            $itemRepo->create([
                'boq_id' => $boqId,
                'item_no' => $row[0],
                'material_name' => $row[1],
                'specification' => $row[2],
                'unit' => $row[3],
                'quantity' => $row[4],
                'preferred_brand' => $row[5],
                'remarks' => $row[6],
            ]);
        }

        return $boqId;
    }
}
?>