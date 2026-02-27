<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\DB;
use App\Core\Auth;
use App\Modules\Buyer\RFQ\RFQController;

session_start();
Auth::checkBuyer();

$conn = DB::getConnection();
$rfqId = (int)$_GET['rfq_id'];
$groupId = (int)$_GET['group_id'];

$stmt = $conn->prepare("
    UPDATE rfq_item_groups
    SET status = 'CLOSED_NO_AWARD'
    WHERE id = ?
");
$stmt->bind_param("i", $groupId);
$stmt->execute();

// after closing the group, reevaluate RFQ overall status
$controller = new RFQController();
$controller->evaluateRFQ($rfqId);

header("Location: rfq_comparison.php?rfq_id=".$rfqId);
exit;