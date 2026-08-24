<?php
namespace App\Modules\Admin\Shipment;

use App\Core\DB;

/**
 * Admin/CSR-driven delivery confirmation. There's no carrier tracking API
 * wired in (deliberately - see conversation history) - a CSR looks up the
 * tracking number/URL supplied by the supplier themselves and marks the PO
 * delivered by hand once it's actually arrived. That's the only thing that
 * flips purchase_orders.status from SHIPPED to DELIVERED, and DELIVERED is
 * the only status that unlocks "Generate Invoice" on the supplier side
 * (see InvoiceController::assertInvoiceable()).
 */
class ShipmentModel
{
    private \mysqli $conn;

    public function __construct()
    {
        $this->conn = DB::getConnection();
    }

    /**
     * All POs currently sitting in SHIPPED, each with its most recent
     * shipment record (tracking number/courier/URL) so a CSR has what they
     * need to go check the carrier's own site without leaving this page.
     */
    public function getShippedPOs(): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                po.id, po.status, po.total_amount, po.created_at,
                buyer.name AS buyer_name,
                supplier.name AS supplier_name,
                ps.delivery_method, ps.courier_company,
                ps.tracking_number, ps.tracking_url, ps.shipping_date
            FROM purchase_orders po
            JOIN `user` u ON u.id = po.created_by
            JOIN companies buyer ON buyer.id = u.company_id
            JOIN companies supplier ON supplier.id = po.supplier_company_id
            LEFT JOIN po_shipments ps ON ps.id = (
                SELECT id FROM po_shipments
                WHERE po_id = po.id
                ORDER BY created_at DESC
                LIMIT 1
            )
            WHERE po.status = 'SHIPPED'
            ORDER BY ps.shipping_date ASC, po.created_at ASC
        ");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getPO(int $poId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM purchase_orders WHERE id = ?");
        $stmt->bind_param("i", $poId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    /**
     * Only ever moves a PO from SHIPPED -> DELIVERED (the WHERE guard makes
     * this a no-op, not an error, if it's already been marked or was never
     * shipped in the first place).
     */
    public function markDelivered(int $poId, int $adminUserId): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE purchase_orders
            SET status = 'DELIVERED', delivered_at = NOW(), delivered_by = ?
            WHERE id = ? AND status = 'SHIPPED'
        ");
        $stmt->bind_param("ii", $adminUserId, $poId);
        $stmt->execute();

        return $stmt->affected_rows > 0;
    }
}
