<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\DB;

session_start();
Auth::checkSupplier();

$conn = DB::getConnection();
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}


try {
    $query = "SELECT company_id FROM `user` WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        die("No user found with ID: " . $_SESSION['user_id']);
    }

    $row = $result->fetch_assoc();
    $supplierId = $row['company_id'];

    $stmt->close();

} catch (Exception $e) {
    die("Error fetching supplier company ID: " . $e->getMessage());
}


// Count RFQs assigned
$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM rfq_group_suppliers
    WHERE supplier_company_id = ?
");
$stmt->bind_param("i", $supplierId);
$stmt->execute();
$rfqCount = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Count submitted quotes
$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM rfq_group_suppliers
    WHERE supplier_company_id = ?
    AND status = 'QUOTED'
");
$stmt->bind_param("i", $supplierId);
$stmt->execute();
$quotedCount = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Count assigned POs
$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM purchase_orders po
    JOIN user u ON u.id = po.created_by
    JOIN companies C ON C.id = u.company_id
    WHERE po.supplier_company_id = ?
");
$stmt->bind_param("i", $supplierId);
$stmt->execute();
$poCount = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Supplier Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .tile {
            border-radius: 16px;
            padding: 30px;
            color: white;
            cursor: pointer;
            transition: 0.3s;
        }
        .tile:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>

<?php require '../header.php'; ?>
<div class="main-content">
    <div class="container mt-5">

        <h2 class="mb-4">Supplier Dashboard</h2>

        <div class="row g-4">

            <div class="col-md-4">
                <div class="tile bg-primary"
                    onclick="window.location='rfq_list.php'">
                    <h4>RFQs</h4>
                    <h2><?= $rfqCount ?></h2>
                    <p>Assigned RFQs</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="tile bg-success"
                    onclick="window.location='rfq_list.php?filter=quoted'">
                    <h4>Submitted Quotes</h4>
                    <h2><?= $quotedCount ?></h2>
                    <p>Already Quoted</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="tile bg-success"
                    onclick="window.location='po_list.php'">
                    <h4>Purchase Orders</h4>
                    <h2><?= $poCount ?></h2>
                    <p>Assigned Purchase Orders</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="tile bg-warning"
                    onclick="window.location='invoice_list.php'">
                    <h4>Invoices</h4>
                    <h2>0</h2>
                    <p>Coming Soon</p>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>
