<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\DB as Database;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Auth::checkSupplier();

$poId = (int)($_GET['po_id'] ?? 0);

$conn = Database::getConnection();

// Fetch PO Header
$stmt = $conn->prepare("
    SELECT po.*, c.name as buyer_name, s.name as supplier_name FROM purchase_orders po
    JOIN user u ON u.id = po.created_by
    JOIN companies c ON c.id = u.company_id
    JOIN companies s ON s.id = po.supplier_company_id
    WHERE po.id = ?
");
$stmt->bind_param("i", $poId);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();

if (!$po) {
    die("Purchase Order not found.");
}

// Fetch PO Items
$stmt = $conn->prepare("
    SELECT * FROM purchase_order_items
    WHERE purchase_order_id = ?
");
$stmt->bind_param("i", $poId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Purchase Order #<?= $poId ?></title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { width:100%; border-collapse: collapse; margin-top:20px;}
        th, td { border:1px solid #ccc; padding:8px; text-align:left;}
        th { background:#f4f4f4; }
        .total { text-align:right; font-weight:bold; }
    </style>
</head>
<body>

<?php require '../header.php'; ?>
<div class="main-content">
    <h2>Purchase Order #<?= $poId ?></h2>
    <p>
        <strong>Buyer:</strong> <?= ($po['buyer_name']) ?><br>
        <strong>Status:</strong> <?= ($po['status']) ?><br>
        <strong>Total Amount:</strong> $<?= number_format($po['total_amount'],2) ?><br>
        <strong>Created At:</strong> <?= $po['created_at'] ?>
    </p>
    <table>
        <thead>
            <tr>
                <th>Material</th>
                <th>Specification</th>
                <th>Unit</th>
                <th>Qty</th>
                <th>Rate</th>
                <th>Line Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($items as $item): ?>
            <tr>
                <td><?= ($item['material_name']) ?></td>
                <td><?= ($item['specification']) ?></td>
                <td><?= ($item['unit']) ?></td>
                <td><?= $item['quantity'] ?></td>
                <td>$<?= number_format($item['unit_price'],2) ?></td>
                <td>$<?= number_format($item['line_total'],2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <hr>
    <div>
        <h5>Supplier Response</h5>
        <p>
            Current Status:
            <span class="badge bg-warning">
            <?= $po['supplier_response'] ?>
            </span>
        </p>
        <?php if ($po['supplier_response'] === 'PENDING'): ?>
            <form method="POST" action="po_response.php">
                <input type="hidden" name="po_id" value="<?= $poId ?>">
                <div class="mb-3">
                    <label class="form-label">Message (optional)</label>
                    <textarea name="note" class="form-control"></textarea>
                </div>
                <button name="response" value="ACCEPTED" class="btn btn-success">
                    Accept PO
                </button>
                <button name="response" value="REJECTED" class="btn btn-danger">
                    Reject PO
                </button>
            </form>
        <?php else: ?>
            <p>
                Supplier responded on:
                <?= $po['supplier_response_at'] ?>
            </p>
        <?php endif; ?>
    </div>
    <div>
        <?php if ($po['supplier_response'] === 'ACCEPTED' && $po['status'] !== 'SHIPPED'){ ?>
            <hr>
            <h4>Shipment Details</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#shipmentModal">
             Add Shipment
            </button>
        <?php } ?>
    </div>


    <?php 
    // Fetch and display shipment history
    $stmt = $conn->prepare("SELECT * FROM po_shipments WHERE po_id = ?");
    $stmt->bind_param("i", $poId);
    $stmt->execute();
    $result = $stmt->get_result();
    ?>
    <?php if($result->num_rows > 0){ ?>
        <h4>Shipment History</h4>
        <?php 
        $shipmentCount = 0;
        while($shipment = $result->fetch_assoc()): ?>
            <p>
                <strong>Shipment #<?= ++$shipmentCount ?></strong><br>
                <strong>Delivery Method:</strong> <?= $shipment['delivery_method'] ?><br>
                <?php if($shipment['delivery_method'] === 'COURIER'): ?>
                    <strong>Courier Company:</strong> <?= $shipment['courier_company'] ?><br>
                    <strong>Tracking Number:</strong> <?= $shipment['tracking_number'] ?><br>
                    <?php if ($shipment['tracking_url']): ?>
                        <a href="<?= $shipment['tracking_url'] ?>" target="_blank">Track Shipment</a><br>
                    <?php endif; ?>
                <?php else: ?>
                    <strong>Driver Name:</strong> <?= $shipment['driver_name'] ?><br>
                    <strong>Vehicle Number:</strong> <?= $shipment['vehicle_number'] ?>
                    <?php if ($shipment['receipt_file']) {
                        $receiptUrl = "../storage/uploads/shipment_receipt/".$poId."/".$shipment['receipt_file'];
                        //I need receipt image to display as modal popup when clicked
                        echo '<br><a href="#" data-bs-toggle="modal" data-bs-target="#receiptModal'.$shipment['id'].'">View Receipt</a>';
                        // Modal for receipt
                        echo '<div class="modal fade" id="receiptModal'.$shipment['id'].'">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Shipment Receipt</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <img src="'.$receiptUrl.'" class="img-fluid" alt="Shipment Receipt">
                                        </div>
                                    </div>
                                </div>
                            </div>';
                    }?>
                    
                <?php endif; ?>
                <strong>Shipping Date:</strong> <?= $shipment['shipping_date'] ?><br>
                <strong>Note:</strong> <?= $shipment['note'] ?><br>
            </p>
            <hr>
        <?php endwhile; ?>
    <?php } ?>
</div>
    <div class="modal fade" id="shipmentModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Shipment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="save_shipment.php" enctype="multipart/form-data">

                    <div class="modal-body">

                        <input type="hidden" name="po_id" value="<?= $poId ?>">

                        <label>Delivery Method</label>
                        <select name="delivery_method" id="deliveryMethod" class="form-control" required>
                        <option value="">Select</option>
                        <option value="COURIER">Courier</option>
                        <option value="SELF">Self Delivery</option>
                        </select>

                        <div id="courierFields" style="display:none">
                            
                            <hr>
                            <label>Courier Company</label>
                            <select name="courier_company" id="courierCompany" class="form-control">
                            <option value="">Select Courier</option>
                            <option value="DHL">DHL</option>
                            <option value="UPS">UPS</option>
                            <option value="USPS">USPS</option>
                            <option value="FEDEX">FedEx</option>
                            <option value="OTHER">Other</option>
                            </select>

                            <label>Tracking Number</label>
                            <input type="text" name="tracking_number" class="form-control">

                            <div id="trackingUrlField" style="display:none;">

                            <label>Tracking URL</label>
                            <input type="text" name="tracking_url" class="form-control"
                            placeholder="https://tracking-company.com/track/123">
                            </div>

                            <label>Shipping Date</label>
                            <input type="date" name="shipping_date" class="form-control">
                            <hr>
                            <label>Note</label>
                            <textarea name="note" class="form-control"></textarea>
                        </div>

                        <div id="selfFields" style="display:none">
                            
                            <hr>
                            <label>Driver Name</label>
                            <input type="text" name="driver_name" class="form-control">
                            <label>Vehicle Number</label>
                            <input type="text" name="vehicle_number" class="form-control">
                            <label>Upload Receipt</label>
                            <input type="file" name="receipt_file" class="form-control">
                            
                            <hr>

                            <label>Note</label>
                            <textarea name="note" class="form-control"></textarea>
                        </div>


                    </div>

                    <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Submit Shipment</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('deliveryMethod').addEventListener('change', function(){
            let courier = document.getElementById('courierFields');
            let self = document.getElementById('selfFields');

            if(this.value === 'COURIER'){
                courier.style.display = 'block';
                self.style.display = 'none';
            }
            else if(this.value === 'SELF'){
                courier.style.display = 'none';
                self.style.display = 'block';
            }
            else{
                courier.style.display = 'none';
                self.style.display = 'none';
            }
        });
    </script>
    <script>

        document.addEventListener("DOMContentLoaded", function(){

            const courierSelect = document.getElementById("courierCompany");
            const trackingUrlContainer = document.getElementById("trackingUrlField");

            courierSelect.addEventListener("change", function(){
                if(this.value === "OTHER"){
                    trackingUrlContainer.style.display = "block";
                } else {
                    trackingUrlContainer.style.display = "none";
                }

            });

        });

    </script>
</body>
</html>
