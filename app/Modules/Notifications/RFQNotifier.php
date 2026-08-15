<?php
declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Core\DB;
use App\Core\Mailer;
use App\Core\InviteToken;

/**
 * Builds and sends the RFQ invitation that suppliers receive.
 *
 * The email is deliberately self-contained: it shows the full line-item
 * table, so a supplier can decide whether the job is worth their time
 * without clicking anything. The button then takes them to a page where
 * they type prices and submit. No account, no password.
 */
class RFQNotifier
{
    /**
     * Notify one invited supplier about one RFQ group.
     *
     * @param int    $invitationId rfq_group_suppliers.id
     * @param string $plainToken   The plaintext token (never re-readable later)
     */
    public function notifyInvitation(int $invitationId, string $plainToken): bool
    {
        $conn = DB::getConnection();

        $stmt = $conn->prepare("
            SELECT rgs.id,
                   rgs.rfq_item_group_id,
                   rgs.supplier_company_id,
                   sc.name                 AS company_name,
                   sc.email                AS company_email,
                   sc.quote_contact_name,
                   sc.quote_contact_email,
                   r.id                    AS rfq_id,
                   r.rfq_title             AS rfq_title,
                   r.quote_deadline,
                   ig.group_name,
                   bc.name                 AS buyer_name
            FROM rfq_group_suppliers rgs
            JOIN companies sc ON sc.id = rgs.supplier_company_id AND sc.type = 'Supplier'
            JOIN rfq_item_groups rig   ON rig.id = rgs.rfq_item_group_id
            JOIN rfqs r                ON r.id  = rig.rfq_id
            LEFT JOIN projects p       ON p.id  = r.project_id
            LEFT JOIN companies bc     ON bc.id = p.buyer_company_id
            JOIN item_groups ig        ON ig.id = rig.item_group_id
            WHERE rgs.id = ?
        ");
        $stmt->bind_param('i', $invitationId);
        $stmt->execute();
        $inv = $stmt->get_result()->fetch_assoc();

        if (!$inv) {
            error_log("[RFQNotifier] Invitation {$invitationId} not found");
            return false;
        }

        $recipient = $inv['quote_contact_email'] ?: $inv['company_email'];

        if (!$recipient || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->recordResult($invitationId, 'FAILED', null,
                'No valid email address on supplier record');
            return false;
        }

        $items = $this->fetchItems((int) $inv['rfq_item_group_id']);

        if (empty($items)) {
            $this->recordResult($invitationId, 'FAILED', $recipient,
                'RFQ group contains no items');
            return false;
        }

        $url      = InviteToken::url($plainToken);
        $subject  = $this->buildSubject($inv, count($items));
        $htmlBody = $this->buildHtml($inv, $items, $url);
        $textBody = $this->buildText($inv, $items, $url);

        $sent = Mailer::send(
            $recipient,
            $subject,
            $htmlBody,
            $textBody,
            'rfq_invite',
            'rfq_group_supplier',
            $invitationId
        );

        $this->recordResult(
            $invitationId,
            $sent ? 'SENT' : 'FAILED',
            $recipient,
            $sent ? null : 'Mailer reported failure; see email_log'
        );

        return $sent;
    }

    /** @return array<int,array<string,mixed>> */
    private function fetchItems(int $groupId): array
    {
        $conn = DB::getConnection();

        $stmt = $conn->prepare("
            SELECT material_name, specification, unit, quantity
            FROM rfq_items
            WHERE rfq_item_group_id = ?
            ORDER BY id ASC
        ");
        $stmt->bind_param('i', $groupId);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Subject lines are the whole battle for supplier response rates.
     * Lead with the category and size so it reads as a real job, not a blast.
     */
    private function buildSubject(array $inv, int $itemCount): string
    {
        $group = $inv['group_name'] ?: 'Materials';
        $buyer = $inv['buyer_name'] ?: 'A contractor';
        $noun  = $itemCount === 1 ? 'item' : 'items';

        $subject = "Quote request: {$group} ({$itemCount} {$noun}) - {$buyer}";

        if (!empty($inv['quote_deadline'])) {
            $days = $this->daysUntil((string) $inv['quote_deadline']);
            if ($days !== null && $days >= 0 && $days <= 7) {
                $subject .= $days === 0
                    ? ' - due today'
                    : ($days === 1 ? ' - due tomorrow' : " - due in {$days} days");
            }
        }

        return $subject;
    }

    private function daysUntil(string $date): ?int
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return null;
        }
        return (int) floor(($ts - time()) / 86400);
    }

    private function buildHtml(array $inv, array $items, string $url): string
    {
        $e = static fn($v): string => htmlspecialchars(
            (string) $v, ENT_QUOTES, 'UTF-8'
        );

        $greeting = $inv['quote_contact_name']
            ? 'Hi ' . $e($inv['quote_contact_name']) . ','
            : 'Hello,';

        $buyer    = $e($inv['buyer_name'] ?: 'A contractor');
        $group    = $e($inv['group_name'] ?: 'Materials');
        $rfqRef   = $e('RFQ-' . str_pad((string) $inv['rfq_id'], 5, '0', STR_PAD_LEFT));

        $deadline = '';
        if (!empty($inv['quote_deadline'])) {
            $ts = strtotime((string) $inv['quote_deadline']);
            if ($ts !== false) {
                $deadline = '<p style="margin:0 0 20px;font-size:15px;color:#b45309;">'
                    . '<strong>Quotes needed by ' . $e(date('l, j F Y', $ts)) . '</strong></p>';
            }
        }

        $rows = '';
        foreach ($items as $i => $item) {
            $bg   = $i % 2 === 0 ? '#ffffff' : '#f8fafc';
            $spec = $item['specification']
                ? '<br><span style="color:#64748b;font-size:13px;">'
                    . $e($item['specification']) . '</span>'
                : '';

            $rows .= '<tr style="background:' . $bg . ';">'
                . '<td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:14px;">'
                    . $e($item['material_name']) . $spec . '</td>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:14px;text-align:right;white-space:nowrap;">'
                    . $e(rtrim(rtrim(number_format((float) $item['quantity'], 2), '0'), '.'))
                    . ' ' . $e($item['unit']) . '</td>'
                . '</tr>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08);">

  <tr><td style="background:#0f172a;padding:20px 28px;">
    <span style="color:#ffffff;font-size:18px;font-weight:600;letter-spacing:-0.3px;">BuildProcure</span>
    <span style="color:#94a3b8;font-size:13px;float:right;padding-top:4px;">{$rfqRef}</span>
  </td></tr>

  <tr><td style="padding:28px;">
    <p style="margin:0 0 14px;font-size:15px;color:#0f172a;">{$greeting}</p>

    <p style="margin:0 0 18px;font-size:15px;color:#334155;line-height:1.6;">
      <strong>{$buyer}</strong> is requesting a quote for <strong>{$group}</strong>.
      The full list is below.
    </p>

    {$deadline}

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:6px;border-collapse:separate;margin:0 0 24px;">
      <tr style="background:#f1f5f9;">
        <th align="left"  style="padding:10px 12px;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;color:#475569;border-bottom:1px solid #e2e8f0;">Material</th>
        <th align="right" style="padding:10px 12px;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;color:#475569;border-bottom:1px solid #e2e8f0;">Quantity</th>
      </tr>
      {$rows}
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 20px;">
      <tr><td style="border-radius:6px;background:#2563eb;">
        <a href="{$url}" style="display:inline-block;padding:14px 34px;font-size:16px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:6px;">
          Enter your prices
        </a>
      </td></tr>
    </table>

    <p style="margin:0 0 6px;font-size:14px;color:#475569;text-align:center;line-height:1.6;">
      No account needed. No password. Takes about a minute.
    </p>
    <p style="margin:0;font-size:13px;color:#94a3b8;text-align:center;">
      Not quoting this one? Just ignore this email.
    </p>
  </td></tr>

  <tr><td style="background:#f8fafc;padding:16px 28px;border-top:1px solid #e2e8f0;">
    <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.6;">
      This link is unique to {$group} and expires after the quote deadline. Please do not forward it.
    </p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }

    private function buildText(array $inv, array $items, string $url): string
    {
        $buyer = $inv['buyer_name'] ?: 'A contractor';
        $group = $inv['group_name'] ?: 'Materials';

        $lines = [];
        $lines[] = $inv['quote_contact_name']
            ? "Hi {$inv['quote_contact_name']},"
            : 'Hello,';
        $lines[] = '';
        $lines[] = "{$buyer} is requesting a quote for {$group}.";
        $lines[] = '';

        if (!empty($inv['quote_deadline'])) {
            $ts = strtotime((string) $inv['quote_deadline']);
            if ($ts !== false) {
                $lines[] = 'Quotes needed by: ' . date('l, j F Y', $ts);
                $lines[] = '';
            }
        }

        $lines[] = 'ITEMS';
        $lines[] = str_repeat('-', 46);

        foreach ($items as $item) {
            $qty  = rtrim(rtrim(number_format((float) $item['quantity'], 2), '0'), '.');
            $line = "  {$item['material_name']} - {$qty} {$item['unit']}";
            if ($item['specification']) {
                $line .= "\n      ({$item['specification']})";
            }
            $lines[] = $line;
        }

        $lines[] = str_repeat('-', 46);
        $lines[] = '';
        $lines[] = 'Enter your prices here:';
        $lines[] = $url;
        $lines[] = '';
        $lines[] = 'No account needed. No password. Takes about a minute.';
        $lines[] = '';
        $lines[] = 'Not quoting this one? Just ignore this email.';

        return implode("\n", $lines);
    }

    private function recordResult(
        int $invitationId,
        string $status,
        ?string $email,
        ?string $error
    ): void {
        try {
            $conn = DB::getConnection();
            $stmt = $conn->prepare("
                UPDATE rfq_group_suppliers
                SET notify_status = ?,
                    invite_email  = COALESCE(?, invite_email),
                    notify_error  = ?,
                    invited_at    = COALESCE(invited_at, NOW())
                WHERE id = ?
            ");
            $truncated = $error !== null ? substr($error, 0, 500) : null;
            $stmt->bind_param('sssi', $status, $email, $truncated, $invitationId);
            $stmt->execute();
        } catch (\Throwable $e) {
            error_log('[RFQNotifier] recordResult failed: ' . $e->getMessage());
        }
    }
}
