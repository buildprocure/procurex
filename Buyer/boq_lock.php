<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Modules\Buyer\BOQ\BOQController;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_POST['boq_id'])) {
    die("Invalid request");
}

$boqId = (int) $_POST['boq_id'];

try {
    $controller = new BOQController();
    $controller->publishBOQ($boqId, $_SESSION['username']);

    header("Location: boq_list.php?display=published&boq_id=" . $boqId);
    exit;

} catch (Exception $e) {
    die($e->getMessage());
}
