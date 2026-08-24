<?php
namespace App\Core;

/**
 * Previously this connected by include()-ing the legacy root _dbconnect.php
 * from inside this method, which set $conn only in this method's local
 * scope. That worked for self::$conn, but since PHP's include/include_once
 * "already included" tracking is global regardless of which scope did the
 * including, it silently made header.php's own top-level
 * `include_once _dbconnect.php` a no-op whenever a Model called
 * DB::getConnection() before header.php ran - leaving header.php's own
 * $conn (which view_as_buyer.php's VPAB reads) null. Only surfaced on
 * Admin pages that load a Model before requiring header.php, since that's
 * the only combination that both triggers the ordering and actually reads
 * $conn (the buyer-switcher dropdown only queries for role === 'Admin').
 * Connecting directly here, independent of that legacy file, removes the
 * shared include-order coupling entirely.
 */
class DB
{
    private static ?\mysqli $conn = null;

    public static function getConnection(): \mysqli
    {
        if (self::$conn === null) {
            self::$conn = new \mysqli(
                Config::get('MYSQL_HOST', 'localhost'),
                Config::get('MYSQL_USER', 'root'),
                Config::get('MYSQL_PASSWORD', ''),
                Config::get('MYSQL_DATABASE', ''),
                (int) Config::get('MYSQL_PORT', '3306')
            );

            if (self::$conn->connect_error) {
                throw new \RuntimeException('Database connection failed: ' . self::$conn->connect_error);
            }
        }

        return self::$conn;
    }
}
