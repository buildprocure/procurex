<?php
declare(strict_types=1);

namespace App\Modules\Buyer\Returns;

use InvalidArgumentException;

/**
 * Pure business rules for returns and refunds - no database, no Stripe -
 * so the money maths and the status machine can be unit tested directly.
 * All money is handled in integer cents internally to avoid float drift.
 */
final class ReturnRules
{
    public const REQUESTED     = 'REQUESTED';
    public const APPROVED      = 'APPROVED';
    public const REJECTED      = 'REJECTED';
    public const REFUNDING     = 'REFUNDING';
    public const REFUNDED      = 'REFUNDED';
    public const REFUND_FAILED = 'REFUND_FAILED';

    private const TRANSITIONS = [
        self::REQUESTED     => [self::APPROVED, self::REJECTED],
        self::APPROVED      => [self::REFUNDING],
        self::REFUNDING     => [self::REFUNDED, self::REFUND_FAILED],
        self::REFUND_FAILED => [self::REFUNDING],
        self::REJECTED      => [],
        self::REFUNDED      => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    public static function fromCents(int $cents): float
    {
        return $cents / 100;
    }

    public static function lineTotal(float $quantity, float $unitPrice): float
    {
        return self::fromCents(self::toCents($quantity * $unitPrice));
    }

    /** Quantity of a PO line that can still be put on a new return. */
    public static function returnableQuantity(float $poQuantity, float $alreadyReturned): float
    {
        return max(0.0, round($poQuantity - $alreadyReturned, 3));
    }

    /**
     * Amount that may still be refunded on an invoice: what was actually
     * paid, minus what has already been refunded (or is being refunded).
     */
    public static function refundableAmount(float $totalPaid, float $alreadyRefunded): float
    {
        return self::fromCents(max(0, self::toCents($totalPaid) - self::toCents($alreadyRefunded)));
    }

    /**
     * Split a refund across the original payments, oldest first, never
     * exceeding what is still refundable on each one.
     *
     * @param  array<int, array{id:int, refundable:float}> $payments
     * @return array<int, array{payment_id:int, amount:float}>
     */
    public static function allocateRefund(float $amount, array $payments): array
    {
        $remaining = self::toCents($amount);
        if ($remaining <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        $allocations = [];
        foreach ($payments as $payment) {
            if ($remaining <= 0) {
                break;
            }
            $available = self::toCents((float) $payment['refundable']);
            if ($available <= 0) {
                continue;
            }
            $take = min($available, $remaining);
            $allocations[] = ['payment_id' => (int) $payment['id'], 'amount' => self::fromCents($take)];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new InvalidArgumentException(
                'Refund exceeds the amount available to refund on the original payments.'
            );
        }

        return $allocations;
    }

    public static function refundStatusFor(float $totalPaid, float $refunded): string
    {
        $refundedCents = self::toCents($refunded);
        if ($refundedCents <= 0) {
            return 'NONE';
        }
        return $refundedCents >= self::toCents($totalPaid) ? 'REFUNDED' : 'PARTIALLY_REFUNDED';
    }
}
