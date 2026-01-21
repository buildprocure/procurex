<?php
namespace App\Core;

use Exception;

use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelParser {
    public function parseBOQ($filePath) {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        unset($rows[0]); // remove header
        return array_values($rows);
    }
}
