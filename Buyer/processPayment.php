<?php
include '../_dbconnect.php';
// Sanitize and validate invoice_id
$paymentId = isset($_GET['payment_id']) ? trim($_GET['payment_id']) : '';

if (empty($paymentId)) {
    die("Invalid payment ID.");
}

// Optionally: validate UUID format
if (!preg_match('/^[A-Za-z]{4}[a-f0-9]{10}$/i', $paymentId)) {
    die("Invalid payment id format.");
}

// Optional: check if invoice exists and is unpaid
$sqlCheck = "SELECT * FROM payments WHERE id = ?";
$stmt = $conn->prepare($sqlCheck);
$stmt->bind_param("s", $paymentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Invoice not found.");
}

$invoice = $result->fetch_assoc();

if ($invoice['status'] === 'Completed') {
    die("Invoice already paid.");
}

// Process payment (example: mark as paid)
$sqlUpdate = "UPDATE payments SET status = 'Completed', payment_date = NOW(), payment_due_date = 'NA' WHERE id = ?";
$stmt = $conn->prepare($sqlUpdate);
$stmt->bind_param("s", $paymentId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "Payment successful for Invoice ID: " . htmlspecialchars($paymentId);
    // Optional: redirect
    // header("Location: invoiceList.php?message=paid");
    // exit;
} else {
    echo "Failed to update invoice.";
}

$conn->close();
?>
