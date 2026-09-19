<?php
declare(strict_types=1);

use App\Modules\Buyer\Returns\ReturnRules;
use PHPUnit\Framework\TestCase;

final class ReturnRulesTest extends TestCase
{
    public function testLineTotalRoundsToCents(): void
    {
        $this->assertSame(33.33, ReturnRules::lineTotal(3, 11.11));
        $this->assertSame(12.5, ReturnRules::lineTotal(2.5, 5.0));
    }

    public function testReturnableQuantityNeverGoesNegative(): void
    {
        $this->assertSame(7.0, ReturnRules::returnableQuantity(10, 3));
        $this->assertSame(0.0, ReturnRules::returnableQuantity(10, 10));
        $this->assertSame(0.0, ReturnRules::returnableQuantity(10, 12));
    }

    public function testRefundableAmountIsPaidMinusRefunded(): void
    {
        $this->assertSame(70.0, ReturnRules::refundableAmount(100.0, 30.0));
        $this->assertSame(0.0, ReturnRules::refundableAmount(100.0, 100.0));
        $this->assertSame(0.0, ReturnRules::refundableAmount(50.0, 80.0));
    }

    public function testFullRefundOnASinglePayment(): void
    {
        $allocations = ReturnRules::allocateRefund(100.0, [['id' => 1, 'refundable' => 100.0]]);
        $this->assertSame([['payment_id' => 1, 'amount' => 100.0]], $allocations);
    }

    public function testPartialRefundOnASinglePayment(): void
    {
        $allocations = ReturnRules::allocateRefund(40.25, [['id' => 1, 'refundable' => 100.0]]);
        $this->assertSame([['payment_id' => 1, 'amount' => 40.25]], $allocations);
    }

    public function testRefundIsSplitAcrossPaymentsOldestFirst(): void
    {
        $allocations = ReturnRules::allocateRefund(120.0, [
            ['id' => 1, 'refundable' => 50.0],
            ['id' => 2, 'refundable' => 0.0],
            ['id' => 3, 'refundable' => 100.0],
        ]);
        $this->assertSame([
            ['payment_id' => 1, 'amount' => 50.0],
            ['payment_id' => 3, 'amount' => 70.0],
        ], $allocations);
    }

    public function testRefundCannotExceedWhatIsRefundable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ReturnRules::allocateRefund(100.01, [['id' => 1, 'refundable' => 100.0]]);
    }

    public function testRefundMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ReturnRules::allocateRefund(0.0, [['id' => 1, 'refundable' => 100.0]]);
    }

    public function testFloatDriftDoesNotBreakExactRefunds(): void
    {
        // 0.1 + 0.2 style drift must not leave a phantom cent unallocated.
        $allocations = ReturnRules::allocateRefund(0.1 + 0.2, [['id' => 1, 'refundable' => 0.3]]);
        $this->assertSame(0.3, $allocations[0]['amount']);
    }

    public function testRefundStatusForInvoice(): void
    {
        $this->assertSame('NONE', ReturnRules::refundStatusFor(100.0, 0.0));
        $this->assertSame('PARTIALLY_REFUNDED', ReturnRules::refundStatusFor(100.0, 40.0));
        $this->assertSame('REFUNDED', ReturnRules::refundStatusFor(100.0, 100.0));
    }

    public function testApprovedReturnFlowsToRefunded(): void
    {
        $this->assertTrue(ReturnRules::canTransition('REQUESTED', 'APPROVED'));
        $this->assertTrue(ReturnRules::canTransition('APPROVED', 'REFUNDING'));
        $this->assertTrue(ReturnRules::canTransition('REFUNDING', 'REFUNDED'));
    }

    public function testFailedRefundCanBeRetried(): void
    {
        $this->assertTrue(ReturnRules::canTransition('REFUNDING', 'REFUND_FAILED'));
        $this->assertTrue(ReturnRules::canTransition('REFUND_FAILED', 'REFUNDING'));
    }

    public function testRejectedAndRefundedAreFinal(): void
    {
        foreach (['APPROVED', 'REFUNDING', 'REFUNDED', 'REFUND_FAILED', 'REQUESTED'] as $to) {
            $this->assertFalse(ReturnRules::canTransition('REJECTED', $to), "REJECTED -> {$to}");
            $this->assertFalse(ReturnRules::canTransition('REFUNDED', $to), "REFUNDED -> {$to}");
        }
    }

    public function testCannotSkipApprovalOrRefundWithoutClaim(): void
    {
        $this->assertFalse(ReturnRules::canTransition('REQUESTED', 'REFUNDING'));
        $this->assertFalse(ReturnRules::canTransition('REQUESTED', 'REFUNDED'));
        $this->assertFalse(ReturnRules::canTransition('APPROVED', 'REFUNDED'));
        $this->assertFalse(ReturnRules::canTransition('APPROVED', 'REJECTED'));
    }
}
