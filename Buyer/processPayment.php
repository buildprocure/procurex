<?php
include '../_dbconnect.php';
// Sanitize and validate invoice_id
$invoiceId = isset($_GET['invoice_id']) ? trim($_GET['invoice_id']) : '';

if (empty($invoiceId)) {
    die("Invalid invoice ID.");
}

// Optionally: validate UUID format
if (!preg_match('/^[a-f0-9\-]{36}$/i', $invoiceId)) {
    die("Invalid invoice format.");
}

// Optional: check if invoice exists and is unpaid
$sqlCheck = "SELECT * FROM payments WHERE id = ?";
$stmt = $conn->prepare($sqlCheck);
$stmt->bind_param("s", $invoiceId);
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
$sqlUpdate = "UPDATE payments SET status = 'Completed', payment_date = NOW() WHERE id = ?";
$stmt = $conn->prepare($sqlUpdate);
$stmt->bind_param("s", $invoiceId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "Payment successful for Invoice ID: " . htmlspecialchars($invoiceId);
    // Optional: redirect
    // header("Location: invoiceList.php?message=paid");
    // exit;
} else {
    echo "Failed to update invoice.";
}

$conn->close();
?>
