<?php
declare(strict_types=1);

/**
 * Retry RFQ invitations whose email did not go out.
 *
 * Invitations are written to the database before any mail is attempted, so an
 * SMTP outage leaves rows in notify_status='PENDING' or 'FAILED' rather than
 * losing the invitation. This job picks those up.
 *
 * Important: the plaintext token is not recoverable (we store only its hash),
 * so a retry issues a fresh token and replaces the stored hash. Any earlier
 * link for that invitation stops working, which is the correct behaviour ???
 * only the most recent email is ever valid.
 *
 * Suggested crontab (every 15 minutes):
 *   *\/15 * * * * php /var/www/html/cronjobs/retry_rfq_notifications.php >> /var/log/procurex-cron.log 2>&1
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\DB;
use App\Core\InviteToken;
use App\Modules\Notifications\RFQNotifier;

const MAX_PER_RUN = 100;

$startedAt = date('Y-m-d H:i:s');
echo "[{$startedAt}] retry_rfq_notifications starting\n";

try {
    $conn = DB::getConnection();

    // Only retry invitations that are still worth sending:
    // not yet quoted, not expired, and not already delivered.
    $stmt = $conn->prepare("
        SELECT rgs.id, rgs.invite_expires_at
        FROM rfq_group_suppliers rgs
        JOIN rfq_item_groups rig ON rig.id = rgs.rfq_item_group_id
        JOIN rfqs r              ON r.id  = rig.rfq_id
        WHERE rgs.notify_status IN ('PENDING', 'FAILED')
          AND rgs.status <> 'QUOTED'
          AND (rgs.invite_expires_at IS NULL OR rgs.invite_expires_at > NOW())
          AND (r.quote_deadline IS NULL OR r.quote_deadline > NOW())
        ORDER BY rgs.id ASC
        LIMIT " . MAX_PER_RUN . "
    ");
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($rows)) {
        echo "Nothing to retry.\n";
        exit(0);
    }

    echo 'Found ' . count($rows) . " invitation(s) to retry.\n";

    $notifier = new RFQNotifier();
    $sent = 0;
    $failed = 0;

    foreach ($rows as $row) {
        $invitationId = (int) $row['id'];

        try {
            // Fresh token; previous link is invalidated.
            $token = InviteToken::generate();
            $hash  = InviteToken::hash($token);

            $upd = $conn->prepare("
                UPDATE rfq_group_suppliers
                SET invite_token_hash = ?
                WHERE id = ?
            ");
            $upd->bind_param('si', $hash, $invitationId);
            $upd->execute();

            if ($notifier->notifyInvitation($invitationId, $token)) {
                $sent++;
                echo "  #{$invitationId} sent\n";
            } else {
                $failed++;
                echo "  #{$invitationId} failed\n";
            }

        } catch (\Throwable $e) {
            $failed++;
            echo "  #{$invitationId} error: " . $e->getMessage() . "\n";
            error_log("[retry_rfq_notifications] #{$invitationId}: " . $e->getMessage());
        }

        // Be gentle with the SMTP provider.
        usleep(200000);
    }

    echo "Done. Sent: {$sent}, Failed: {$failed}\n";
    exit($failed > 0 ? 1 : 0);

} catch (\Throwable $e) {
    echo 'Fatal: ' . $e->getMessage() . "\n";
    error_log('[retry_rfq_notifications] Fatal: ' . $e->getMessage());
    exit(1);
}
