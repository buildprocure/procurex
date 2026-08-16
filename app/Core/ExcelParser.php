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

        // Drop fully-empty rows and any row missing an item code (e.g. trailing
        // blank rows or notes below the data table that aren't real BOQ items)
        $rows = array_filter($rows, function ($row) {
            return !empty(trim($row[0] ?? ''));
        });

        return array_values($rows);
    }
}
