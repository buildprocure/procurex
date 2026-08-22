<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Core\Auth;
use App\Modules\Buyer\RFQ\RFQController;

session_start();
Auth::checkBuyer();

$rfqId  = (int)$_GET['rfq_id'];
$itemId = (int)$_GET['item_id'];

$controller = new RFQController();
$controller->postponeItem($rfqId, $itemId);

header("Location: rfq_comparison.php?rfq_id=".$rfqId);
exit;
