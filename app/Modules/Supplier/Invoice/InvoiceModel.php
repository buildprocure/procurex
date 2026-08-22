<?php
namespace App\Modules\Supplier\Invoice;

use App\Core\DB;

/**
 * Invoice generation for an accepted, shipped purchase order.
 *
 * po_invoices is a header/tracking record only - one row per PO, unique on
 * purchase_order_id. Line items are never copied in; they're read live from
 * purchase_order_items at render/send time, the same snapshot-from-source
 * pattern the PO itself already uses (see RFQModel::awardItems()). That
 * keeps the invoice's line items always consistent with what was actually
 * awarded/shipped, with nothing to drift out of sync.
 */
class InvoiceModel
{
    private \mysqli $conn;

    public function __construct()
    {
        $this->conn = DB::getConnection();
    }

    /**
     * Fetch the purchase order this invoice would be for, along with
     * enough context to decide whether generating one is allowed.
     */
    public function getPurchaseOrder(int $poId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT id, rfq_id, supplier_company_id, total_amount, status,
                   created_by, supplier_response, supplier_response_at
            FROM purchase_orders
            WHERE id = ?
        ");
        $stmt->bind_param("i", $poId);
        $stmt->execute();
        $po = $stmt->get_result()->fetch_assoc();
        return $po ?: null;
    }

    public function getInvoiceByPO(int $poId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM po_invoices WHERE purchase_order_id = ?");
        $stmt->bind_param("i", $poId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function getInvoiceById(int $invoiceId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM po_invoices WHERE id = ?");
        $stmt->bind_param("i", $invoiceId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    /**
     * Human-readable, guaranteed-unique invoice number. Uniqueness comes
     * from embedding the PO id (po_invoices already enforces one invoice
     * per PO), not from randomness.
     */
    public function generateInvoiceNumber(int $poId): string
    {
        return 'INV-' . date('Ymd') . '-' . $poId;
    }

    /**
     * Create the invoice header if one doesn't already exist for this PO.
     * Idempotent - calling this again for the same PO just returns the
     * existing invoice's id rather than erroring or duplicating.
     */
    public function createInvoice(int $poId, int $createdBy): int
    {
        $existing = $this->getInvoiceByPO($poId);
        if ($existing) {
            return (int) $existing['id'];
        }

        $stmt = $this->conn->prepare("
            SELECT COALESCE(SUM(line_total), 0) AS subtotal
            FROM purchase_order_items
            WHERE purchase_order_id = ?
        ");
        $stmt->bind_param("i", $poId);
        $stmt->execute();
        $subtotal = (float) ($stmt->get_result()->fetch_assoc()['subtotal'] ?? 0);

        $invoiceNumber = $this->generateInvoiceNumber($poId);

        $stmt = $this->conn->prepare("
            INSERT INTO po_invoices
                (purchase_order_id, invoice_number, status, subtotal, total_amount, created_by, created_at)
            VALUES (?, ?, 'DRAFT', ?, ?, ?, NOW())
        ");
        $stmt->bind_param(
            "isddi",
            $poId,
            $invoiceNumber,
            $subtotal,
            $subtotal, // total_amount == subtotal, no tax for now
            $createdBy
        );
        $stmt->execute();

        return (int) $this->conn->insert_id;
    }

    public function markSent(int $invoiceId, string $sentToEmail): void
    {
        $stmt = $this->conn->prepare("
            UPDATE po_invoices
            SET status = 'SENT', sent_at = NOW(), sent_to_email = ?
            WHERE id = ?
        ");
        $stmt->bind_param("si", $sentToEmail, $invoiceId);
        $stmt->execute();
    }

    /**
     * Everything a render (web page or email) needs for one invoice:
     * the invoice header, the PO, supplier + buyer company info, the
     * buyer contact's own email (the actual person who created the RFQ,
     * not just a generic company inbox), shipment info if any, and the
     * line items pulled live from purchase_order_items.
     */
    public function getInvoiceDetail(int $invoiceId): ?array
    {
        $invoice = $this->getInvoiceById($invoiceId);
        if (!$invoice) {
            return null;
        }

        $poId = (int) $invoice['purchase_order_id'];

        $stmt = $this->conn->prepare("
            SELECT
                po.id AS po_id,
                po.status AS po_status,
                po.supplier_response,
                po.created_at AS po_created_at,
                supplier.id AS supplier_company_id,
                supplier.name AS supplier_name,
                supplier.address AS supplier_address,
                supplier.email AS supplier_email,
                buyer.id AS buyer_company_id,
                buyer.name AS buyer_name,
                buyer.address AS buyer_address,
                buyer.invoice_contact_email AS buyer_invoice_email,
                u.email AS buyer_contact_email,
                u.username AS buyer_contact_name
            FROM purchase_orders po
            JOIN companies supplier ON supplier.id = po.supplier_company_id
            JOIN `user` u ON u.id = po.created_by
            JOIN companies buyer ON buyer.id = u.company_id
            WHERE po.id = ?
        ");
        $stmt->bind_param("i", $poId);
        $stmt->execute();
        $context = $stmt->get_result()->fetch_assoc();

        if (!$context) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT material_name, specification, unit, quantity, unit_price, line_total
            FROM purchase_order_items
            WHERE purchase_order_id = ?
            ORDER BY id ASC
        ");
        $stmt->bind_param("i", $poId);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $this->conn->prepare("
            SELECT delivery_method, courier_company, tracking_number, shipping_date, delivery_date
            FROM po_shipments
            WHERE po_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $poId);
        $stmt->execute();
        $shipment = $stmt->get_result()->fetch_assoc();

        return [
            'invoice'  => $invoice,
            'po'       => $context,
            'items'    => $items,
            'shipment' => $shipment ?: null,
        ];
    }
}
