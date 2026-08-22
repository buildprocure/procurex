<?php
declare(strict_types=1);

namespace Tests\Buyer\RFQ;

use Tests\Support\DatabaseTestCase;
use App\Modules\Buyer\RFQ\RFQModel;

/**
 * Tests for RFQModel::awardItems().
 *
 * Replaces the old group-level awardSupplier(). A line item's quantity
 * can now be split across suppliers, so the unit under test is a single
 * (item, supplier, quantity) award rather than a whole quote.
 */
final class AwardItemsTest extends DatabaseTestCase
{
    private RFQModel $model;

    private int $buyerId;
    private int $supplierA;
    private int $supplierB;
    private int $rfqId;
    private int $groupId;
    private int $itemCement; // quantity 1000
    private int $quoteA;     // Alpha: 12.00/unit
    private int $quoteB;     // Beta:  11.50/unit

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new RFQModel();

        $this->buyerId   = $this->makeCompany('Acme Contractors', 'Buyer');
        $this->supplierA = $this->makeCompany('Alpha Supplies', 'Supplier');
        $this->supplierB = $this->makeCompany('Beta Materials', 'Supplier');

        $projectId = $this->makeProject($this->buyerId);
        $boqId     = $this->makeBoq($projectId);
        $this->rfqId = $this->makeRfq($projectId, $boqId);

        $itemGroupId   = $this->makeItemGroup('Civil');
        $this->groupId = $this->makeRfqItemGroup($this->rfqId, $itemGroupId);

        $this->itemCement = $this->makeRfqItem($this->rfqId, $this->groupId, 'Portland Cement', 1000, 'Bag');

        $this->quoteA = $this->makeQuote($this->groupId, $this->supplierA, [$this->itemCement => 12.00]);
        $this->quoteB = $this->makeQuote($this->groupId, $this->supplierB, [$this->itemCement => 11.50]);
    }

    // =================================================================
    // Happy path - single supplier, full quantity
    // =================================================================

    public function testAwardingFullQuantityMarksItemFullyAwarded(): void
    {
        $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => 1000],
        ], 1);

        $state = $this->itemAwardState($this->itemCement);

        $this->assertSame(1000.0, $state['awarded_quantity']);
        $this->assertSame('FULLY_AWARDED', $state['award_status']);
    }

    public function testAwardCreatesAPurchaseOrderForTheSupplier(): void
    {
        $result = $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => 1000],
        ], 1);

        $po = $this->poForSupplier($this->rfqId, $this->supplierA);

        $this->assertNotNull($po);
        $this->assertSame([(int) $po['id']], $result['po_ids']);
        $this->assertSame(12000.0, (float) $po['total_amount']); // 1000 * 12.00
    }

    public function testPurchaseOrderItemCarriesTheAwardedQuantityAndPrice(): void
    {
        $result = $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => 1000],
        ], 1);

        $line = $this->fetchRow(
            "SELECT quantity, unit_price, line_total FROM purchase_order_items WHERE purchase_order_id = ?",
            [$result['po_ids'][0]]
        );

        $this->assertSame(1000.0, (float) $line['quantity']);
        $this->assertSame(12.00, (float) $line['unit_price']);
        $this->assertSame(12000.0, (float) $line['line_total']);
    }

    // =================================================================
    // Splitting across suppliers - the actual feature
    // =================================================================

    public function testOneItemCanBeSplitAcrossTwoSuppliers(): void
    {
        $result = $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => 600],
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteB, 'quantity' => 400],
        ], 1);

        $this->assertCount(2, $result['po_ids'], 'A split award must create one PO per supplier');

        $state = $this->itemAwardState($this->itemCement);
        $this->assertSame(1000.0, $state['awarded_quantity']);
        $this->assertSame('FULLY_AWARDED', $state['award_status']);

        $poA = $this->poForSupplier($this->rfqId, $this->supplierA);
        $poB = $this->poForSupplier($this->rfqId, $this->supplierB);

        $this->assertSame(7200.0, (float) $poA['total_amount']); // 600 * 12.00
        $this->assertSame(4600.0, (float) $poB['total_amount']); // 400 * 11.50
    }

    public function testSplitCanHappenAcrossSeparateAwardCalls(): void
    {
        $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => 600],
        ], 1);

        // Second call, later - the remaining 400 goes to Beta.
        $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteB, 'quantity' => 400],
        ], 1);

        $state = $this->itemAwardState($this->itemCement);
        $this->assertSame(1000.0, $state['awarded_quantity']);
        $this->assertSame('FULLY_AWARDED', $state['award_status']);
    }

    public function testPartialAwardLeavesItemPartiallyAwarded(): void
    {
        $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => 300],
        ], 1);

        $state = $this->itemAwardState($this->itemCement);

        $this->assertSame(300.0, $state['awarded_quantity']);
        $this->assertSame('PARTIALLY_AWARDED', $state['award_status']);
    }

    public function testASecondAwardToTheSameSupplierAppendsToTheExistingPO(): void
    {
        $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => 300],
        ], 1);

        $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => 200],
        ], 1);

        $this->assertSame(
            1,
            $this->countRows('purchase_orders', 'rfq_id = ? AND supplier_company_id = ?',
                [$this->rfqId, $this->supplierA]),
            'Repeated awards to the same supplier on the same RFQ must reuse one PO'
        );

        $po = $this->poForSupplier($this->rfqId, $this->supplierA);
        $this->assertSame(6000.0, (float) $po['total_amount']); // 500 * 12.00
    }

    // =================================================================
    // Guard rails - the whole point of moving to per-item locking
    // =================================================================

    public function testCannotAwardMoreThanTheItemsRemainingQuantity(): void
    {
        $this->expectException(\Throwable::class);

        $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => 1001],
        ], 1);
    }

    public function testCannotAwardMoreThanWhatRemainsAfterAPriorAward(): void
    {
        $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => 700],
        ], 1);

        // Only 300 left; asking for 400 must fail.
        $this->expectException(\Throwable::class);

        $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteB, 'quantity' => 400],
        ], 1);
    }

    public function testOverAwardingWithinASingleSplitCallIsRejected(): void
    {
        // 600 + 500 = 1100, item only has 1000.
        $this->expectException(\Throwable::class);

        $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => 600],
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteB, 'quantity' => 500],
        ], 1);
    }

    public function testAFailedSplitLeavesNeitherAwardApplied(): void
    {
        try {
            $this->model->awardItems($this->rfqId, [
                ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => 600],
                ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteB, 'quantity' => 500],
            ], 1);
        } catch (\Throwable $e) {
            // expected
        }

        $state = $this->itemAwardState($this->itemCement);
        $this->assertSame(0.0, $state['awarded_quantity'],
            'A rejected batch must not partially apply - the valid line before the invalid one must roll back too');
        $this->assertSame(0, $this->countRows('purchase_orders'));
    }

    public function testZeroQuantityIsRejected(): void
    {
        $this->expectException(\Throwable::class);

        $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => 0],
        ], 1);
    }

    public function testNegativeQuantityIsRejected(): void
    {
        $this->expectException(\Throwable::class);

        $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => -50],
        ], 1);
    }

    public function testCannotAwardAQuoteThatDidNotPriceThisItem(): void
    {
        $otherItem = $this->makeRfqItem($this->rfqId, $this->groupId, 'Rebar 12mm', 500, 'Kg');
        // quoteA never priced $otherItem - makeQuote only inserted a line for itemCement.

        $this->expectException(\Throwable::class);

        $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $otherItem, 'quote_id' => $this->quoteA, 'quantity' => 100],
        ], 1);
    }

    public function testCannotAwardAnItemFromADifferentRfq(): void
    {
        $otherProject = $this->makeProject($this->buyerId);
        $otherBoq     = $this->makeBoq($otherProject);
        $otherRfq     = $this->makeRfq($otherProject, $otherBoq);

        $this->expectException(\Throwable::class);

        // itemCement belongs to $this->rfqId, not $otherRfq.
        $this->model->awardItems($otherRfq, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => 100],
        ], 1);
    }

    public function testDraftQuoteIsNotAwardable(): void
    {
        $this->db->query(
            "UPDATE rfq_group_quotes SET status = 'DRAFT' WHERE id = {$this->quoteB}"
        );

        $this->expectException(\Throwable::class);

        $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteB, 'quantity' => 100],
        ], 1);
    }

    public function testEmptyAwardsArrayIsRejected(): void
    {
        $this->expectException(\Throwable::class);

        $this->model->awardItems($this->rfqId, [], 1);
    }

    // =================================================================
    // Snapshot accuracy
    // =================================================================

    public function testPurchaseOrderLineIsImmuneToLaterQuoteEdits(): void
    {
        $result = $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => 500],
        ], 1);

        $this->db->query(
            "UPDATE rfq_group_quote_items SET unit_price = 99.99
             WHERE rfq_group_quote_id = {$this->quoteA}"
        );

        $line = $this->fetchRow(
            "SELECT unit_price FROM purchase_order_items WHERE purchase_order_id = ?",
            [$result['po_ids'][0]]
        );

        $this->assertSame(12.00, (float) $line['unit_price']);
    }

    public function testPurchaseOrderTotalNeverDriftsFromItsLines(): void
    {
        $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => 300],
        ], 1);

        $this->model->awardItems($this->rfqId, [
            ['rfq_item_id' => $this->itemCement, 'quote_id' => $this->quoteA, 'quantity' => 200],
        ], 1);

        $po  = $this->poForSupplier($this->rfqId, $this->supplierA);
        $sum = $this->fetchRow(
            "SELECT SUM(line_total) AS s FROM purchase_order_items WHERE purchase_order_id = ?",
            [$po['id']]
        );

        $this->assertEqualsWithDelta((float) $po['total_amount'], (float) $sum['s'], 0.01);
    }
}
