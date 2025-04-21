<?php
class DuplicatePaymentModel {
    private $db;

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }

    public function getDuplicatePayment() {
        $sql = "SELECT *, b.username AS buyer_username, s.username AS supplier_username
                FROM payments
                left JOIN buyer_list_table b ON payments.buyer_id = b.buyer_id
                left JOIN supplier_list_table s ON payments.supplier_id = s.supplier_id
                WHERE invoice_number IN (
                    SELECT invoice_number
                    FROM payments
                    GROUP BY invoice_number
                    HAVING COUNT(*) > 1
                )
                ORDER BY invoice_number";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
    $result = $stmt->get_result(); // ✅ This gets a mysqli_result object
    $data = $result->fetch_all(MYSQLI_ASSOC); // ✅ Now fetch all rows
        
    $stmt->close();
    return $data;
    }
    public function getPossibleDuplicatePayment() {
        $sql = "SELECT * FROM payments p1
            JOIN payments p2 ON p1.id != p2.id 
                AND p1.buyer_id = p2.buyer_id 
                AND p1.supplier_id = p2.supplier_id 
                AND (p1.amount = p2.amount OR p1.payment_date = p2.payment_date)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
    $result = $stmt->get_result(); // ✅ This gets a mysqli_result object
    $data = $result->fetch_all(MYSQLI_ASSOC); // ✅ Now fetch all rows
    $stmt->close();
    return $data;
    }
}
