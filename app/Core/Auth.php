<?php
namespace App\Core;
class Auth {
    public static function check() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    public static function requireLogin() {
        self::check();
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            header("Location: " . SITE_URL . "login.php");
            exit;
        }
    }

    public static function checkBuyer() {
        self::requireLogin();
        if (strtolower($_SESSION['role']) !== 'buyer') {
            header("Location: ../unauthorized.php");
            exit;
        }
    }
    public static function checkSupplier() {
        self::requireLogin();
        if (strtolower($_SESSION['role']) !== 'supplier') {
            header("Location: ../unauthorized.php");
            exit;
        }
    }
}