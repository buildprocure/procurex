<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\DB;

session_start();
Auth::checkSupplier();

$conn = DB::getConnection();

$poId = (int)$_POST['po_id'];
$method = $_POST['delivery_method'];
$courier = $_POST['courier_company'];
$tracking = $_POST['tracking_number'];
$url = $_POST['tracking_url'];
$shipDate = $_POST['shipping_date'] ? date('Y-m-d', strtotime($_POST['shipping_date'])) : date("Y-m-d");

$driver = $_POST['driver_name'];
$vehicle = $_POST['vehicle_number'];

$note = $_POST['note'];

$supplierId = $_SESSION['company_id'];

$receiptFile = null;

$url = null;

if($method === 'COURIER'){

    $tracking = $_POST['tracking_number'];

    switch($courier){

        case 'DHL':
            $url = "https://www.dhl.com/en/express/tracking.html?AWB=".$tracking;
        break;

        case 'UPS':
            $url = "https://wwwapps.ups.com/WebTracking/track?track=yes&trackNums=".$tracking;
        break;

        case 'USPS':
            $url = "https://tools.usps.com/go/TrackConfirmAction?tLabels=".$tracking;
        break;

        case 'FEDEX':
            $url = "https://www.fedex.com/fedextrack/?tracknumbers=".$tracking;
        break;

        case 'OTHER':
            $url = $_POST['tracking_url'] ?? null;
        break;
    }
}

if (!empty($_FILES['receipt_file']['name'])) {

    $targetDir = "../storage/uploads/shipment_receipt/".$poId."/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $fileName = time().'_'.basename($_FILES["receipt_file"]["name"]);
    $targetFile = $targetDir.$fileName;

    move_uploaded_file($_FILES["receipt_file"]["tmp_name"], $targetFile);

    $receiptFile = $fileName;
}

$stmt = $conn->prepare("
INSERT INTO po_shipments
(po_id,supplier_company_id,delivery_method,courier_company,
tracking_number,tracking_url,driver_name,vehicle_number,
shipping_date, receipt_file, note)
VALUES (?,?,?,?,?,?,?,?,?,?,?)
");

$stmt->bind_param(
"iisssssssss",
$poId,$supplierId,$method,$courier,$tracking,$url,
$driver,$vehicle,$shipDate,$receiptFile,$note
);

$stmt->execute();

//update purchase order status to SHIPPED
$stmt = $conn->prepare("
UPDATE purchase_orders
SET status = 'SHIPPED'
WHERE id = ?
");
$stmt->bind_param("i",$poId);
$stmt->execute();

header("Location: po_view.php?po_id=".$poId);
exit;