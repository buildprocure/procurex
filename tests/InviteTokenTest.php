<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Core\InviteToken;

/**
 * Token generation and hashing are pure functions, so they are testable
 * without a database. These are the security-critical parts.
 */
final class InviteTokenTest extends TestCase
{
    public function testTokensAreUnique(): void
    {
        $seen = [];
        for ($i = 0; $i < 1000; $i++) {
            $seen[InviteToken::generate()] = true;
        }
        $this->assertCount(1000, $seen, 'Token collision detected');
    }

    public function testTokenIsUrlSafe(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $token = InviteToken::generate();
            $this->assertSame(
                $token,
                urlencode($token),
                'Token must survive URL encoding unchanged'
            );
        }
    }

    public function testTokenHasSufficientEntropy(): void
    {
        // 32 raw bytes -> 43 chars of unpadded base64url
        $this->assertGreaterThanOrEqual(43, strlen(InviteToken::generate()));
    }

    public function testHashIsSha256Hex(): void
    {
        $hash = InviteToken::hash('example-token');
        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    public function testHashIsDeterministic(): void
    {
        $token = InviteToken::generate();
        $this->assertSame(InviteToken::hash($token), InviteToken::hash($token));
    }

    public function testDifferentTokensHashDifferently(): void
    {
        $this->assertNotSame(
            InviteToken::hash(InviteToken::generate()),
            InviteToken::hash(InviteToken::generate())
        );
    }

    public function testExpiryAddsGraceToDeadline(): void
    {
        $deadline = date('Y-m-d H:i:s', strtotime('+10 days'));
        $expiry   = InviteToken::expiryFor($deadline);

        $this->assertGreaterThan(
            strtotime($deadline),
            strtotime($expiry),
            'Expiry must fall after the quote deadline'
        );
    }

    public function testExpiryFallsBackWhenDeadlineMissing(): void
    {
        foreach ([null, '', 'not-a-date'] as $bad) {
            $expiry = InviteToken::expiryFor($bad);
            $this->assertGreaterThan(
                time(),
                strtotime($expiry),
                'Fallback expiry must be in the future'
            );
        }
    }
}
