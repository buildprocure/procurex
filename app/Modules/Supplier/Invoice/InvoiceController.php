<?php
namespace App\Modules\Supplier\Invoice;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Mailer;
use Exception;

class InvoiceController
{
    private InvoiceModel $model;

    public function __construct()
    {
        $this->model = new InvoiceModel();
    }

    /**
     * A PO is only invoiceable once the supplier has accepted it AND an
     * admin/CSR has confirmed delivery (status = DELIVERED) - being merely
     * SHIPPED isn't enough. There's no carrier-tracking API wired in here;
     * DELIVERED is set by hand via Admin/PO/po_shipment_tracking.php
     * (see App\Modules\Admin\Shipment\ShipmentModel::markDelivered()).
     */
    private function assertInvoiceable(array $po): void
    {
        if ($po['supplier_response'] !== 'ACCEPTED') {
            throw new Exception('This PO has not been accepted yet.');
        }
        if ($po['status'] !== 'DELIVERED') {
            throw new Exception('An invoice can only be generated once delivery has been confirmed.');
        }
    }

    private function assertSupplierOwnsPO(array $po): void
    {
        $companyId = (int) ($_SESSION['company_id'] ?? 0);
        if ($companyId <= 0 || (int) $po['supplier_company_id'] !== $companyId) {
            throw new Exception('You do not have permission to invoice this purchase order.');
        }
    }

    /**
     * Generate (or fetch, if it already exists - idempotent) the invoice
     * for a PO, returning its id.
     */
    public function generate(int $poId): int
    {
        Auth::checkSupplier();

        $po = $this->model->getPurchaseOrder($poId);
        if (!$po) {
            throw new Exception('Purchase order not found.');
        }

        $this->assertSupplierOwnsPO($po);
        $this->assertInvoiceable($po);

        return $this->model->createInvoice($poId, (int) ($_SESSION['user_id'] ?? 0));
    }

    /**
     * Fetch full invoice detail for the supplier's own view, enforcing
     * that the requesting supplier actually owns the underlying PO.
     */
    public function getForSupplier(int $invoiceId): array
    {
        Auth::checkSupplier();

        $detail = $this->model->getInvoiceDetail($invoiceId);
        if (!$detail) {
            throw new Exception('Invoice not found.');
        }

        $companyId = (int) ($_SESSION['company_id'] ?? 0);
        if ($companyId <= 0 || (int) $detail['po']['supplier_company_id'] !== $companyId) {
            throw new Exception('You do not have permission to view this invoice.');
        }

        return $detail;
    }

    /**
     * Fetch full invoice detail for the buyer's own view, enforcing that
     * the requesting buyer actually belongs to the company this PO/invoice
     * was raised against.
     */
    public function getForBuyer(int $invoiceId): array
    {
        Auth::checkBuyer();

        $detail = $this->model->getInvoiceDetail($invoiceId);
        if (!$detail) {
            throw new Exception('Invoice not found.');
        }

        $companyId = (int) ($_SESSION['company_id'] ?? 0);
        if ($companyId <= 0 || (int) $detail['po']['buyer_company_id'] !== $companyId) {
            throw new Exception('You do not have permission to view this invoice.');
        }

        return $detail;
    }

    /**
     * Email the invoice to the buyer contact who created the RFQ, and
     * record that it was sent. Mail failure never throws back up - the
     * invoice stays generated and can be retried; see Mailer::send().
     */
    public function send(int $invoiceId): bool
    {
        Auth::checkSupplier();

        $detail = $this->model->getInvoiceDetail($invoiceId);
        if (!$detail) {
            throw new Exception('Invoice not found.');
        }

        $companyId = (int) ($_SESSION['company_id'] ?? 0);
        if ($companyId <= 0 || (int) $detail['po']['supplier_company_id'] !== $companyId) {
            throw new Exception('You do not have permission to send this invoice.');
        }

        // Prefer the buyer company's dedicated invoice inbox (companies.emailforinvoice)
        // over the personal login email of whichever user happened to create the RFQ.
        // Mailer::send() handles the SEND_EMAILS_TO_RECEIPENT/DEFAULT_EMAIL_ADDRESS
        // redirect on top of whatever address we resolve here.
        $buyerEmail = $detail['po']['buyer_invoice_email'] ?? null;
        if (empty($buyerEmail)) {
            $buyerEmail = $detail['po']['buyer_contact_email'] ?? null;
        }
        if (empty($buyerEmail)) {
            throw new Exception('Buyer has no email on file - cannot send invoice.');
        }

        $viewUrl = Config::appUrl() . '/Buyer/Invoice/invoice_view.php?invoice_id=' . $invoiceId;
        $html = InvoiceRenderer::renderEmailHtml($detail, $viewUrl);
        $subject = 'Invoice ' . $detail['invoice']['invoice_number'] . ' from ' . $detail['po']['supplier_name'];

        $sent = Mailer::send(
            $buyerEmail,
            $subject,
            $html,
            null,
            'po_invoice',
            'purchase_order',
            (int) $detail['po']['po_id']
        );

        if ($sent) {
            $this->model->markSent($invoiceId, $buyerEmail);
        }

        return $sent;
    }
}
