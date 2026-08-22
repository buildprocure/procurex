<?php
namespace App\Modules\Supplier\Invoice;

/**
 * Two render paths sharing the same data shape (InvoiceModel::getInvoiceDetail()):
 *
 *  - renderWebHtml(): meant to be echoed inside a page that already loads
 *    the app's brand button classes (primary_button/secondary_button) -
 *    see Buyer/RFQ/rfq_comparison.php for that design system.
 *  - renderEmailHtml(): a standalone, fully inline-styled fragment, since
 *    email clients strip <style> blocks and external stylesheets
 *    unreliably. Kept table-based for the line items for the same reason.
 */
class InvoiceRenderer
{
    private static function e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    private static function money($v): string
    {
        return number_format((float) $v, 2);
    }

    private static function qty($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
    }

    public static function renderWebHtml(array $detail): string
    {
        $invoice  = $detail['invoice'];
        $po       = $detail['po'];
        $items    = $detail['items'];
        $shipment = $detail['shipment'];

        ob_start();
        ?>
        <div class="invoice-card">
            <div class="invoice-head">
                <div>
                    <div class="invoice-eyebrow">Invoice</div>
                    <h3><?= self::e($invoice['invoice_number']) ?></h3>
                    <div class="invoice-meta">
                        PO #<?= (int) $po['po_id'] ?>
                        &middot; Status:
                        <span class="badge <?= $invoice['status'] === 'SENT' ? 'bg-success' : 'bg-secondary' ?>">
                            <?= self::e($invoice['status']) ?>
                        </span>
                    </div>
                </div>
                <div class="invoice-parties">
                    <div>
                        <div class="party-label">From</div>
                        <div class="party-name"><?= self::e($po['supplier_name']) ?></div>
                        <div class="party-detail"><?= nl2br(self::e($po['supplier_address'])) ?></div>
                        <?php if (!empty($po['supplier_email'])): ?>
                            <div class="party-detail"><?= self::e($po['supplier_email']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="party-label">Bill To</div>
                        <div class="party-name"><?= self::e($po['buyer_name']) ?></div>
                        <div class="party-detail"><?= nl2br(self::e($po['buyer_address'])) ?></div>
                        <div class="party-detail"><?= self::e($po['buyer_contact_email']) ?></div>
                    </div>
                </div>
            </div>

            <table class="invoice-items-table">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Specification</th>
                        <th>Unit</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= self::e($item['material_name']) ?></td>
                            <td><?= self::e($item['specification']) ?></td>
                            <td><?= self::e($item['unit']) ?></td>
                            <td class="text-end"><?= self::qty($item['quantity']) ?></td>
                            <td class="text-end">$<?= self::money($item['unit_price']) ?></td>
                            <td class="text-end">$<?= self::money($item['line_total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-end">Total Due</th>
                        <th class="text-end">$<?= self::money($invoice['total_amount']) ?></th>
                    </tr>
                </tfoot>
            </table>

            <?php if ($shipment): ?>
                <div class="invoice-shipment">
                    <strong>Shipment:</strong>
                    <?= self::e($shipment['delivery_method']) ?>
                    <?php if (!empty($shipment['courier_company'])): ?>
                        via <?= self::e($shipment['courier_company']) ?>
                    <?php endif; ?>
                    <?php if (!empty($shipment['tracking_number'])): ?>
                        &middot; Tracking: <?= self::e($shipment['tracking_number']) ?>
                    <?php endif; ?>
                    <?php if (!empty($shipment['shipping_date'])): ?>
                        &middot; Shipped <?= self::e($shipment['shipping_date']) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($invoice['status'] === 'SENT'): ?>
                <div class="invoice-sent-note">
                    Sent to <?= self::e($invoice['sent_to_email']) ?> on <?= self::e($invoice['sent_at']) ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Standalone inline-styled HTML for the email body. Uses hex values
     * directly rather than CSS variables/classes - most email clients
     * don't support either reliably.
     */
    public static function renderEmailHtml(array $detail, string $viewUrl): string
    {
        $invoice = $detail['invoice'];
        $po      = $detail['po'];
        $items   = $detail['items'];

        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr>'
                . '<td style="padding:8px;border-bottom:1px solid #eef1f6;">' . self::e($item['material_name']) . '</td>'
                . '<td style="padding:8px;border-bottom:1px solid #eef1f6;text-align:right;">' . self::qty($item['quantity']) . ' ' . self::e($item['unit']) . '</td>'
                . '<td style="padding:8px;border-bottom:1px solid #eef1f6;text-align:right;">$' . self::money($item['unit_price']) . '</td>'
                . '<td style="padding:8px;border-bottom:1px solid #eef1f6;text-align:right;">$' . self::money($item['line_total']) . '</td>'
                . '</tr>';
        }

        $supplierName = self::e($po['supplier_name']);
        $buyerName    = self::e($po['buyer_name']);
        $invoiceNo    = self::e($invoice['invoice_number']);
        $total        = self::money($invoice['total_amount']);
        $poId         = (int) $po['po_id'];
        $viewUrlSafe  = self::e($viewUrl);

        return <<<HTML
        <div style="font-family:Arial,Helvetica,sans-serif;color:#1f2937;max-width:640px;margin:0 auto;">
            <div style="background:linear-gradient(135deg,#0d6efd,#0a4fb5);padding:20px 24px;border-radius:10px 10px 0 0;color:#fff;">
                <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.04em;opacity:0.85;">Invoice</div>
                <div style="font-size:1.3rem;font-weight:700;margin-top:4px;">{$invoiceNo}</div>
                <div style="font-size:0.85rem;margin-top:6px;opacity:0.9;">For Purchase Order #{$poId}</div>
            </div>
            <div style="border:1px solid #eef1f6;border-top:none;padding:20px 24px;border-radius:0 0 10px 10px;">
                <p style="margin:0 0 16px;">Hi {$buyerName},</p>
                <p style="margin:0 0 16px;">
                    {$supplierName} has sent you an invoice for the purchase order below.
                </p>
                <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                    <thead>
                        <tr style="background:#eaf2ff;color:#0a4fb5;">
                            <th style="padding:8px;text-align:left;">Material</th>
                            <th style="padding:8px;text-align:right;">Qty</th>
                            <th style="padding:8px;text-align:right;">Rate</th>
                            <th style="padding:8px;text-align:right;">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$rows}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="padding:10px 8px;text-align:right;font-weight:700;">Total Due</td>
                            <td style="padding:10px 8px;text-align:right;font-weight:700;">\${$total}</td>
                        </tr>
                    </tfoot>
                </table>
                <div style="text-align:center;margin-top:24px;">
                    <a href="{$viewUrlSafe}"
                       style="display:inline-block;background:#0d6efd;color:#ffffff;text-decoration:none;
                              font-weight:600;padding:10px 22px;border-radius:8px;font-size:0.9rem;">
                        View Full Invoice
                    </a>
                </div>
                <p style="margin:20px 0 0;font-size:0.78rem;color:#6b7280;">
                    This is an automated notice from BuildProcure on behalf of {$supplierName}.
                </p>
            </div>
        </div>
        HTML;
    }
}
