<?php
class DuplicatePaymentModel {
    private $db;

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }

    public function getPossibleDuplicates() {
        $sql = "SELECT invoice_number, amount, payment_date, COUNT(*) as count
                FROM payments
                GROUP BY invoice_number, amount, payment_date
                HAVING count > 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
    $result = $stmt->get_result(); // ✅ This gets a mysqli_result object
    $data = $result->fetch_all(MYSQLI_ASSOC); // ✅ Now fetch all rows
    $stmt->close();
    return $data;
    }
}
