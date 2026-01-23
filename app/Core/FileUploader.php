<?php
namespace App\Core;

use Exception;

class FileUploader {
    public function uploadBOQ($file) {
        $path = BOQUPLOAD_PATH ?? BASE_PATH . '/../../storage/uploads/boq/';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $filename = uniqid() . '_' . basename($file['name']);
        $destination = $path . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception("Failed to upload file");
        }
        return $destination;
    }
}
