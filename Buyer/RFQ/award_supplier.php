<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Modules\Buyer\RFQ\RFQController;
use App\Core\Auth;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

Auth::checkBuyer();

$rfqId   = (int)($_POST['rfq_id'] ?? 0);
$groupId = (int)($_POST['group_id'] ?? 0);
$quoteId = (int)($_POST['quote_id'] ?? 0);

try {
    $controller = new RFQController();
    $result = $controller->awardSupplier($rfqId, $groupId, $quoteId, (int)($_SESSION['user_id'] ?? 0));

    $_SESSION['award_logs'] = $result['logs'] ?? [];
    $_SESSION['award_success'] = true;

    header("Location: ../PO/po_view.php?po_id=" . ($result['po_id'] ?? 0));
    exit;

} catch (Exception $e) {
    $_SESSION['award_logs'] = [ $e->getMessage() ];
    $_SESSION['award_success'] = false;
    die("Error awarding supplier. " . $e->getMessage());
}