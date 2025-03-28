<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// ini_set('memory_limit', '256M');

// Hardcoded values for testing
$employee_name = 'Rabin';  // Example employee name
$hours_worked = 56;        // Example hours worked
$rate_per_hour = 15;       // Example rate per hour
$status = 'approved';      // Hardcoded approved status

// Initialize invoice data
$invoice_data = [];
$total_amount = 0;

// Only process if the status is 'approved'
if ($status === 'approved') {
    // Calculate amount for this timesheet
    $amount = $hours_worked * $rate_per_hour;
    $invoice_data[] = [
        'employee_name' => $employee_name,
        'hours_worked' => $hours_worked,
        'rate_per_hour' => $rate_per_hour,
        'amount' => $amount
    ];

    // Calculate the total amount
    $total_amount += $amount;

    // Generate the invoice HTML
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Invoice</title>
        <style>
            body {
                font-family: Arial, sans-serif;
            }
            .invoice-container {
                width: 80%;
                margin: 0 auto;
                padding: 20px;
                border: 1px solid #ccc;
            }
            .invoice-header, .invoice-footer {
                text-align: center;
            }
            .invoice-items table {
                width: 100%;
                margin-top: 20px;
                border-collapse: collapse;
            }
            .invoice-items th, .invoice-items td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            .total {
                font-size: 18px;
                font-weight: bold;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>

    <div class="invoice-container">
        <div class="invoice-header">
            <h1>Invoice</h1>
            <p>Date: <?php echo date('Y-m-d'); ?></p>
        </div>

        <div class="invoice-items">
            <table>
                <thead>
                    <tr>
                        <th>Employee Name</th>
                        <th>Hours Worked</th>
                        <th>Rate per Hour</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoice_data as $data): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($data['employee_name']); ?></td>
                            <td><?php echo $data['hours_worked']; ?></td>
                            <td><?php echo number_format($data['rate_per_hour'], 2); ?></td>
                            <td><?php echo number_format($data['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="total">
            <p>Total Amount: $<?php echo number_format($total_amount, 2); ?></p>
        </div>

        <div class="invoice-footer">
            <p>Thank you for your business!</p>
        </div>
    </div>

    </body>
    </html>
    <?php
} else {
    echo "<p>The timesheet is not approved. Cannot generate invoice.</p>";
}
?>
