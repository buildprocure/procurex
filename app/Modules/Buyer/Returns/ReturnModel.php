<?php
declare(strict_types=1);

namespace App\Modules\Buyer\Returns;

use App\Core\DB;
use Exception;

/**
 * Persistence for return requests, their audit trail, and the Stripe
 * refund rows issued against them. Amounts already refunded are always
 * summed live from invoice_refunds; po_invoices.refunded_amount and
 * refund_status are cached labels re-synced on every refund write.
 *
 * Quantity already on a return counts against a PO line unless that
 * return was REJECTED, so the same goods cannot be returned twice.
 */
class ReturnModel
{
    private \mysqli $conn;

    public function __construct()
    {
        $this->conn = DB::getConnection();
    }

    public function begin(): void
    {
        $this->conn->begin_transaction();
    }

    public function commit(): void
    {
        $this->conn->commit();
    }

    public function rollback(): void
    {
        $this->conn->rollback();
    }

    /**
     * PO lines with how much of each is already on a non-rejected return.
     * $lock = true takes row locks (call inside begin()) so two concurrent
     * requests cannot both claim the same remaining quantity.
     */
    public function getPoLinesWithReturned(int $poId, bool $lock = false): array
    {
        if ($lock) {
            // Lock first, read second: the totals below must be read after
            // the lock is held, or a concurrent request's snapshot could
            // miss a return that committed while we waited.
            $stmt = $this->conn->prepare("SELECT id FROM purchase_order_items WHERE purchase_order_id = ? FOR UPDATE");
            $stmt->bind_param('i', $poId);
            $stmt->execute();
            $stmt->get_result();
        }

        $stmt = $this->conn->prepare("
            SELECT poi.id, poi.material_name, poi.specification, poi.unit,
                   poi.quantity, poi.unit_price,
                   COALESCE((
                       SELECT SUM(rri.quantity)
                       FROM return_request_items rri
                       JOIN return_requests rr ON rr.id = rri.return_request_id
                       WHERE rri.purchase_order_item_id = poi.id
                         AND rr.status <> 'REJECTED'
                   ), 0) AS returned_quantity
            FROM purchase_order_items poi
            WHERE poi.purchase_order_id = ?
            ORDER BY poi.id ASC
        ");
        $stmt->bind_param('i', $poId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * @param array<int, array{po_item_id:int, quantity:float, unit_price:float, line_total:float}> $lines
     */
    public function createReturn(
        int $invoiceId,
        int $poId,
        int $buyerCompanyId,
        int $supplierCompanyId,
        string $reason,
        float $refundTotal,
        int $requestedBy,
        array $lines
    ): int {
        $stmt = $this->conn->prepare("
            INSERT INTO return_requests
                (invoice_id, purchase_order_id, buyer_company_id, supplier_company_id,
                 status, reason, refund_total, requested_by, requested_at)
            VALUES (?, ?, ?, ?, 'REQUESTED', ?, ?, ?, NOW())
        ");
        $stmt->bind_param('iiiisdi', $invoiceId, $poId, $buyerCompanyId, $supplierCompanyId, $reason, $refundTotal, $requestedBy);
        $stmt->execute();
        $returnId = (int) $this->conn->insert_id;

        $itemStmt = $this->conn->prepare("
            INSERT INTO return_request_items
                (return_request_id, purchase_order_item_id, quantity, unit_price, line_total)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($lines as $line) {
            $itemStmt->bind_param('iiddd', $returnId, $line['po_item_id'], $line['quantity'], $line['unit_price'], $line['line_total']);
            $itemStmt->execute();
        }

        return $returnId;
    }

    public function getReturn(int $returnId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT rr.*, inv.invoice_number, inv.total_amount AS invoice_total,
                   po.status AS po_status,
                   sc.name AS supplier_name, sc.email AS supplier_email,
                   bc.name AS buyer_name,
                   u.email AS buyer_contact_email
            FROM return_requests rr
            JOIN po_invoices inv     ON inv.id = rr.invoice_id
            JOIN purchase_orders po  ON po.id  = rr.purchase_order_id
            JOIN companies sc        ON sc.id  = rr.supplier_company_id
            JOIN companies bc        ON bc.id  = rr.buyer_company_id
            LEFT JOIN `user` u       ON u.id   = rr.requested_by
            WHERE rr.id = ?
        ");
        $stmt->bind_param('i', $returnId);
        $stmt->execute();
        $return = $stmt->get_result()->fetch_assoc();
        if (!$return) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT rri.quantity, rri.unit_price, rri.line_total,
                   poi.material_name, poi.specification, poi.unit
            FROM return_request_items rri
            JOIN purchase_order_items poi ON poi.id = rri.purchase_order_item_id
            WHERE rri.return_request_id = ?
            ORDER BY rri.id ASC
        ");
        $stmt->bind_param('i', $returnId);
        $stmt->execute();
        $return['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return $return;
    }

    /** @return array<int, array> newest first; filter by company or all when both null. */
    public function listReturns(?int $buyerCompanyId, ?int $supplierCompanyId): array
    {
        $sql = "
            SELECT rr.id, rr.invoice_id, rr.status, rr.refund_total, rr.requested_at,
                   inv.invoice_number, sc.name AS supplier_name, bc.name AS buyer_name
            FROM return_requests rr
            JOIN po_invoices inv ON inv.id = rr.invoice_id
            JOIN companies sc    ON sc.id = rr.supplier_company_id
            JOIN companies bc    ON bc.id = rr.buyer_company_id
            WHERE 1 = 1
        ";
        $types = '';
        $params = [];
        if ($buyerCompanyId !== null) {
            $sql .= ' AND rr.buyer_company_id = ?';
            $types .= 'i';
            $params[] = $buyerCompanyId;
        }
        if ($supplierCompanyId !== null) {
            $sql .= ' AND rr.supplier_company_id = ?';
            $types .= 'i';
            $params[] = $supplierCompanyId;
        }
        $sql .= ' ORDER BY rr.id DESC';

        $stmt = $this->conn->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function listForInvoice(int $invoiceId): array
    {
        $stmt = $this->conn->prepare("
            SELECT id, status, refund_total, requested_at
            FROM return_requests WHERE invoice_id = ? ORDER BY id DESC
        ");
        $stmt->bind_param('i', $invoiceId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Atomic compare-and-set on status. Returns false if the return was no
     * longer in $from (someone else got there first) - this is what stops
     * a double-click or two approvers from triggering two refunds.
     */
    public function transition(int $returnId, string $from, string $to, ?int $decidedBy = null, ?string $comment = null): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE return_requests
            SET status = ?,
                decided_by = COALESCE(?, decided_by),
                decided_at = IF(? IS NULL, decided_at, NOW()),
                decision_comment = COALESCE(?, decision_comment)
            WHERE id = ? AND status = ?
        ");
        $stmt->bind_param('siisis', $to, $decidedBy, $decidedBy, $comment, $returnId, $from);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    public function setFailure(int $returnId, ?string $message): void
    {
        $stmt = $this->conn->prepare("UPDATE return_requests SET failure_message = ? WHERE id = ?");
        $stmt->bind_param('si', $message, $returnId);
        $stmt->execute();
    }

    public function addEvent(int $returnId, string $action, ?int $userId, ?string $role, ?string $comment): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO return_request_events (return_request_id, action, actor_user_id, actor_role, comment)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('isiss', $returnId, $action, $userId, $role, $comment);
        $stmt->execute();
    }

    public function getEvents(int $returnId): array
    {
        $stmt = $this->conn->prepare("
            SELECT e.action, e.actor_role, e.comment, e.created_at, u.username
            FROM return_request_events e
            LEFT JOIN `user` u ON u.id = e.actor_user_id
            WHERE e.return_request_id = ?
            ORDER BY e.id ASC
        ");
        $stmt->bind_param('i', $returnId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ---------------------------------------------------------------
    // Refunds
    // ---------------------------------------------------------------

    /**
     * Successful payments with the amount still refundable on each,
     * oldest first. PENDING and SUCCEEDED refunds both count as spent.
     */
    public function getRefundablePayments(int $invoiceId): array
    {
        $stmt = $this->conn->prepare("
            SELECT p.id, p.stripe_payment_intent_id,
                   p.amount - COALESCE((
                       SELECT SUM(r.amount) FROM invoice_refunds r
                       WHERE r.invoice_payment_id = p.id AND r.status IN ('PENDING','SUCCEEDED')
                   ), 0) AS refundable
            FROM invoice_payments p
            WHERE p.invoice_id = ? AND p.status = 'SUCCEEDED'
              AND p.stripe_payment_intent_id IS NOT NULL
            ORDER BY p.id ASC
        ");
        $stmt->bind_param('i', $invoiceId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getTotalRefunded(int $invoiceId): float
    {
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total
            FROM invoice_refunds WHERE invoice_id = ? AND status IN ('PENDING','SUCCEEDED')
        ");
        $stmt->bind_param('i', $invoiceId);
        $stmt->execute();
        return (float) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    }

    /** Sum of refunds for one return that are PENDING or SUCCEEDED. */
    public function getRefundedForReturn(int $returnId): float
    {
        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total
            FROM invoice_refunds WHERE return_request_id = ? AND status IN ('PENDING','SUCCEEDED')
        ");
        $stmt->bind_param('i', $returnId);
        $stmt->execute();
        return (float) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    }

    public function getRefundCountsForReturn(int $returnId): array
    {
        $stmt = $this->conn->prepare("
            SELECT status, COUNT(*) AS n FROM invoice_refunds
            WHERE return_request_id = ? GROUP BY status
        ");
        $stmt->bind_param('i', $returnId);
        $stmt->execute();
        $counts = ['PENDING' => 0, 'SUCCEEDED' => 0, 'FAILED' => 0];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }
        return $counts;
    }

    public function getRefundsForReturn(int $returnId): array
    {
        $stmt = $this->conn->prepare("
            SELECT id, amount, status, stripe_refund_id, failure_message, created_at
            FROM invoice_refunds WHERE return_request_id = ? ORDER BY id ASC
        ");
        $stmt->bind_param('i', $returnId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function createRefundRow(int $returnId, int $invoiceId, int $paymentId, float $amount, ?int $createdBy): int
    {
        $stmt = $this->conn->prepare("
            INSERT INTO invoice_refunds
                (return_request_id, invoice_id, invoice_payment_id, amount, status, created_by)
            VALUES (?, ?, ?, ?, 'PENDING', ?)
        ");
        $stmt->bind_param('iiidi', $returnId, $invoiceId, $paymentId, $amount, $createdBy);
        $stmt->execute();
        return (int) $this->conn->insert_id;
    }

    public function setRefundResult(int $refundRowId, string $status, ?string $stripeRefundId, ?string $failureMessage): void
    {
        $stmt = $this->conn->prepare("
            UPDATE invoice_refunds
            SET status = ?, stripe_refund_id = COALESCE(?, stripe_refund_id), failure_message = ?
            WHERE id = ?
        ");
        $stmt->bind_param('sssi', $status, $stripeRefundId, $failureMessage, $refundRowId);
        $stmt->execute();
    }

    public function findRefundByStripeId(string $stripeRefundId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM invoice_refunds WHERE stripe_refund_id = ?");
        $stmt->bind_param('s', $stripeRefundId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /** Only moves a PENDING row; returns true if this call was the one that finalised it. */
    public function finalizePendingRefund(int $refundRowId, string $status, ?string $failureMessage): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE invoice_refunds SET status = ?, failure_message = ?
            WHERE id = ? AND status = 'PENDING'
        ");
        $stmt->bind_param('ssi', $status, $failureMessage, $refundRowId);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    public function syncInvoiceRefund(int $invoiceId, float $totalPaid): void
    {
        $refunded = $this->getTotalRefunded($invoiceId);
        $status = ReturnRules::refundStatusFor($totalPaid, $refunded);

        $stmt = $this->conn->prepare("UPDATE po_invoices SET refunded_amount = ?, refund_status = ? WHERE id = ?");
        $stmt->bind_param('dsi', $refunded, $status, $invoiceId);
        $stmt->execute();
    }
}
