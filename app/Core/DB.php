<?php
namespace App\Core;

class DB
{
    private static $conn = null;

    public static function getConnection()
    {
        if (self::$conn === null) {
            include __DIR__ . '/../../_dbconnect.php';
            self::$conn = $conn;
        }

        return self::$conn;
    }
}
