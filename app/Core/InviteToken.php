<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Tokens that let a supplier open and answer an RFQ without an account.
 *
 * Security model
 * --------------
 *  - 32 bytes from random_bytes(), URL-safe base64 => 256 bits of entropy.
 *    Not guessable, and not enumerable by incrementing an id.
 *  - Only the SHA-256 hash is stored. A database dump does not yield
 *    working links.
 *  - Lookup is by hash, so the comparison happens inside the unique index
 *    rather than in PHP. No timing side-channel on our side.
 *  - Every token is scoped to exactly one (rfq_item_group_id,
 *    supplier_company_id) pair and carries its own expiry.
 *
 * A token grants precisely one capability: view this group's line items and
 * submit one quote for it. It is not a session and confers nothing else.
 */
class InviteToken
{
    private const TOKEN_BYTES = 32;

    /** Fallback validity when the RFQ has no usable deadline. */
    private const DEFAULT_TTL_DAYS = 30;

    /** Grace period after the quote deadline, so a late click still explains itself. */
    private const GRACE_DAYS = 7;

    /**
     * Generate a new plaintext token.
     * Store hash() of it; put the plaintext in the email only.
     */
    public static function generate(): string
    {
        return rtrim(
            strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'),
            '='
        );
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Expiry for a token, derived from the RFQ deadline plus a grace period.
     */
    public static function expiryFor(?string $quoteDeadline): string
    {
        if ($quoteDeadline !== null && $quoteDeadline !== '') {
            $deadline = strtotime($quoteDeadline);
            if ($deadline !== false) {
                return date('Y-m-d H:i:s',
                    $deadline + (self::GRACE_DAYS * 86400));
            }
        }

        return date('Y-m-d H:i:s',
            time() + (self::DEFAULT_TTL_DAYS * 86400));
    }

    /**
     * Full quoting URL for an email.
     */
    public static function url(string $token): string
    {
        return Config::appUrl() . '/quote.php?t=' . urlencode($token);
    }

    /**
     * Resolve a plaintext token to its invitation.
     *
     * Returns null when the token is unknown. Returns a row otherwise; the
     * caller must still check is_expired and already_quoted, because those
     * states need distinct messages rather than a blanket rejection.
     *
     * @return array<string,mixed>|null
     */
    public static function resolve(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $conn = DB::getConnection();
        $hash = self::hash($token);

        $stmt = $conn->prepare("
            SELECT rgs.id                AS invitation_id,
                   rgs.rfq_item_group_id,
                   rgs.supplier_company_id,
                   rgs.status,
                   rgs.invite_expires_at,
                   rgs.first_viewed_at,
                   r.id                  AS rfq_id,
                   r.quote_deadline,
                   ig.group_name,
                   sc.company_name       AS supplier_name
            FROM rfq_group_suppliers rgs
            JOIN rfq_item_groups rig ON rig.id = rgs.rfq_item_group_id
            JOIN rfqs r              ON r.id  = rig.rfq_id
            JOIN item_groups ig      ON ig.id = rig.item_group_id
            JOIN supplier_companies sc ON sc.id = rgs.supplier_company_id
            WHERE rgs.invite_token_hash = ?
            LIMIT 1
        ");

        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            return null;
        }

        $expiresAt = $row['invite_expires_at'];

        $row['is_expired'] = $expiresAt !== null
            && strtotime((string) $expiresAt) < time();

        $row['deadline_passed'] = $row['quote_deadline'] !== null
            && strtotime((string) $row['quote_deadline']) < time();

        $row['already_quoted'] = ($row['status'] === 'QUOTED');

        return $row;
    }

    /**
     * Record the first open. Powers "viewed but not quoted" follow-ups.
     */
    public static function markViewed(int $invitationId): void
    {
        try {
            $conn = DB::getConnection();
            $stmt = $conn->prepare("
                UPDATE rfq_group_suppliers
                SET first_viewed_at = NOW()
                WHERE id = ? AND first_viewed_at IS NULL
            ");
            $stmt->bind_param('i', $invitationId);
            $stmt->execute();
        } catch (\Throwable $e) {
            error_log('[InviteToken] markViewed failed: ' . $e->getMessage());
        }
    }
}
