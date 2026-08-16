<?php
declare(strict_types=1);

namespace Tests\Buyer\RFQ;

use Tests\Support\DatabaseTestCase;
use App\Modules\Buyer\RFQ\RFQModel;

/**
 * Tests for RFQModel::awardSupplier().
 *
 * This is the function that decides who wins an RFQ and creates the
 * purchase order committing the buyer to pay. Everything downstream ???
 * shipment, invoicing, payment ??? is anchored to the PO it writes, so a
 * defect here becomes a real commercial dispute rather than a UI glitch.
 *
 * Tests are grouped into:
 *   1. Happy path        ??? what the function is supposed to do
 *   2. Snapshot accuracy ??? the PO must freeze the agreed prices
 *   3. Guard rails       ??? several of these FAIL against the current
 *                          implementation, by design. See the class
 *                          docblock on each.
 */
final class AwardSupplierTest extends DatabaseTestCase
{
    private RFQModel $model;

    private int $buyerId;
    private int $supplierA;
    private int $supplierB;
    private int $rfqId;
    private int $groupId;
    private int $itemCement;
    private int $itemSteel;
    private int $quoteA;
    private int $quoteB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new RFQModel();

        // One RFQ, one group, two items, two competing quotes.
        $this->buyerId   = $this->makeCompany('Acme Contractors', 'Buyer');
        $this->supplierA = $this->makeCompany('Alpha Supplies', 'Supplier');
        $this->supplierB = $this->makeCompany('Beta Materials', 'Supplier');

        $projectId = $this->makeProject($this->buyerId);
        $boqId     = $this->makeBoq($projectId);
        $this->rfqId = $this->makeRfq($projectId, $boqId);

        $itemGroupId   = $this->makeItemGroup('Civil');
        $this->groupId = $this->makeRfqItemGroup($this->rfqId, $itemGroupId);

        $this->itemCement = $this->makeRfqItem($this->rfqId, $this->groupId, 'Portland Cement', 100, 'Bag');
        $this->itemSteel  = $this->makeRfqItem($this->rfqId, $this->groupId, 'Rebar 12mm', 500, 'Kg');

        // Alpha: 100 x 8.50 + 500 x 1.20 = 850 + 600 = 1450.00
        $this->quoteA = $this->makeQuote($this->groupId, $this->supplierA, [
            $this->itemCement => 8.50,
            $this->itemSteel  => 1.20,
        ]);

        // Beta: 100 x 9.00 + 500 x 1.10 = 900 + 550 = 1450.00 (deliberate tie)
        $this->quoteB = $this->makeQuote($this->groupId, $this->supplierB, [
            $this->itemCement => 9.00,
            $this->itemSteel  => 1.10,
        ]);
    }

    // =================================================================
    // 1. Happy path
    // =================================================================

    public function testAwardMarksWinningQuoteAsAwarded(): void
    {
        $this->model->awardSupplier($this->rfqId, $this->groupId, $this->quoteA, 1);

        $this->assertSame('AWARDED', $this->decisionStatusOf($this->quoteA));
    }

    public function testAwardMarksCompetingQuotesAsLost(): void
    {
        $this->model->awardSupplier($this->rfqId, $this->groupId, $this->quoteA, 1);

        $this->assertSame('LOST', $this->decisionStatusOf($this->quoteB));
    }

    public function testAwardCreatesExactlyOnePurchaseOrder(): void
    {
        $this->model->awardSupplier($this->rfqId, $this->groupId, $this->quoteA, 1);

        $this->assertSame(1, $this->countRows('purchase_orders'));
    }

    public function testPurchaseOrderGoesToTheWinningSupplier(): void
    {
        $result = $this->model->awardSupplier($this->rfqId, $this->groupId, $this->quoteA, 1);

        $po = $this->fetchRow(
            "SELECT supplier_company_id FROM purchase_orders WHERE id = ?",
            [$result['po_id']]
        );

        $this->assertSame($this->supplierA, (int) $po['supplier_company_id']);
    }

    public function testPurchaseOrderTotalMatchesTheAwardedQuote(): void
    {
        $result = $this->model->awardSupplier($this->rfqId, $this->groupId, $this->quoteA, 1);

        $quote = $this->fetchRow(
            "SELECT total_amount FROM rfq_group_quotes WHERE id = ?",
            [$this->quoteA]
        );
        $po = $this->fetchRow(
            "SELECT total_amount FROM purchase_orders WHERE id = ?",
            [$result['po_id']]
        );

        $this->assertSame(
            (float) $quote['total_amount'],
            (float) $po['total_amount'],
            'PO total must equal the quote the buyer accepted'
        );
    }

    public function testItemGroupIsMarkedDecisionMade(): void
    {
        $this->model->awardSupplier($this->rfqId, $this->groupId, $this->quoteA, 1);

        $group = $this->fetchRow(
            "SELECT status FROM rfq_item_groups WHERE id = ?",
            [$this->groupId]
        );

        $this->assertSame('DECISION_MADE', $group['status']);
    }

    public function testAwardingLastGroupMarksRfqDecided(): void
    {
        $this->model->awardSupplier($this->rfqId, $this->groupId, $this->quoteA, 1);

        $rfq = $this->fetchRow("SELECT status FROM rfqs WHERE id = ?", [$this->rfqId]);

        $this->assertSame('DECIDED', $rfq['status']);
    }

    public function testRfqStaysOpenWhileAnotherGroupIsUndecided(): void
    {
        // A second group on the same RFQ, still awaiting a decision.
        $otherItemGroup = $this->makeItemGroup('Electrical');
        $otherGroup     = $this->makeRfqItemGroup($this->rfqId, $otherItemGroup);
        $this->makeRfqItem($this->rfqId, $otherGroup, 'Copper Wire', 200, 'Meter');

        $this->model->awardSupplier($this->rfqId, $this->groupId, $this->quoteA, 1);

        $rfq = $this->fetchRow("SELECT status FROM rfqs WHERE id = ?", [$this->rfqId]);

        $this->assertNotSame(
            'DECIDED',
            $rfq['status'],
            'RFQ must not be closed while another group is still undecided'
        );
    }

    // =================================================================
    // 2. Snapshot accuracy
    //
    // The PO must freeze what was agreed. If a quote or an RFQ item is
    // later edited, the PO must not move with it.
    // =================================================================

    public function testPurchaseOrderCopiesEveryQuotedLine(): void
    {
        $result = $this->model->awardSupplier($this->rfqId, $this->groupId, $this->quoteA, 1);

        $this->assertSame(
            2,
            $this->countRows('purchase_order_items', 'purchase_order_id = ?', [$result['po_id']])
        );
    }

    public function testPurchaseOrderLinesCarryTheAwardedPrices(): void
    {
        $result = $this->model->awardSupplier($this->rfqId, $this->groupId, $this->quoteA, 1);

        $line = $this->fetchRow(
            "SELECT unit_price, quantity, line_total
             FROM purchase_order_items
             WHERE purchase_order_id = ? AND rfq_item_id = ?",
            [$result['po_id'], $this->itemCement]
        );

        $this->assertSame(8.50, (float) $line['unit_price']);
        $this->assertSame(100.0, (float) $line['quantity']);
        $this->assertSame(850.0, (float) $line['line_total']);
    }

    public function testPurchaseOrderLinesAreImmuneToLaterQuoteEdits(): void
    {
        $result = $this->model->awardSupplier($this->rfqId, $this->groupId, $this->quoteA, 1);

        // Supplier's quote row is tampered with after the award.
        $this->db->query(
            "UPDATE rfq_group_quote_items SET unit_price = 99.99
             WHERE rfq_group_quote_id = {$this->quoteA}"
        );

        $line = $this->fetchRow(
            "SELECT unit_price FROM purchase_order_items
             WHERE purchase_order_id = ? AND rfq_item_id = ?",
            [$result['po_id'], $this->itemCement]
        );

        $this->assertSame(
            8.50,
            (float) $line['unit_price'],
            'PO is a snapshot; editing the quote afterwards must not change it'
        );
    }

    public function testPurchaseOrderLineTotalsSumToHeaderTotal(): void
    {
        $result = $this->model->awardSupplier($this->rfqId, $this->groupId, $this->quoteA, 1);

        $sum = $this->fetchRow(
            "SELECT SUM(line_total) AS s FROM purchase_order_items WHERE purchase_order_id = ?",
            [$result['po_id']]
        );
        $po = $this->fetchRow(
            "SELECT total_amount FROM purchase_orders WHERE id = ?",
            [$result['po_id']]
        );

        $this->assertEqualsWithDelta(
            (float) $po['total_amount'],
            (float) $sum['s'],
            0.01,
            'Header total must equal the sum of its lines'
        );
    }

    // =================================================================
    // 3. Guard rails
    //
    // The tests below describe how the function SHOULD behave. Several
    // currently FAIL. Each failure is a real defect, not a broken test ???
    // see docs/awardSupplier-findings.md.
    // =================================================================

    /**
     * DEFECT: awardSupplier() looks the quote up by id alone and never
     * checks it belongs to $groupId. Passing a quote from a different
     * group awards it anyway, and marks the WRONG group's quotes LOST.
     */
    public function testCannotAwardAQuoteBelongingToADifferentGroup(): void
    {
        $otherItemGroup = $this->makeItemGroup('Plumbing');
        $otherGroup     = $this->makeRfqItemGroup($this->rfqId, $otherItemGroup);
        $otherItem      = $this->makeRfqItem($this->rfqId, $otherGroup, 'PVC Pipe', 50, 'Meter');
        $foreignQuote   = $this->makeQuote($otherGroup, $this->supplierB, [$otherItem => 4.00]);

        $this->expectException(\Throwable::class);

        // Group is $this->groupId but the quote belongs to $otherGroup.
        $this->model->awardSupplier($this->rfqId, $this->groupId, $foreignQuote, 1);
    }

    /**
     * DEFECT: $rfqId is written straight onto the PO without checking the
     * group belongs to it, so a PO can be attached to an unrelated RFQ.
     */
    public function testCannotAwardUsingAGroupFromADifferentRfq(): void
    {
        $otherProject = $this->makeProject($this->buyerId);
        $otherBoq     = $this->makeBoq($otherProject);
        $otherRfq     = $this->makeRfq($otherProject, $otherBoq);

        $this->expectException(\Throwable::class);

        // Group belongs to $this->rfqId, not $otherRfq.
        $this->model->awardSupplier($otherRfq, $this->groupId, $this->quoteA, 1);
    }

    /**
     * DEFECT: there is no idempotency guard. Awarding twice writes a
     * second PO, committing the buyer to pay for the same materials
     * twice. A double-clicked button is enough to trigger this.
     */
    public function testAwardingTwiceDoesNotCreateASecondPurchaseOrder(): void
    {
        $this->model->awardSupplier($this->rfqId, $this->groupId, $this->quoteA, 1);

        try {
            $this->model->awardSupplier($this->rfqId, $this->groupId, $this->quoteA, 1);
        } catch (\Throwable $e) {
            // Rejecting the second attempt is the correct behaviour.
        }

        $this->assertSame(
            1,
            $this->countRows('purchase_orders'),
            'A group must not be awarded twice'
        );
    }

    /**
     * DEFECT: a group already marked DECISION_MADE can be awarded again
     * to a different supplier, silently flipping the first winner to LOST
     * after their PO already exists.
     */
    public function testCannotAwardAGroupThatIsAlreadyDecided(): void
    {
        $this->model->awardSupplier($this->rfqId, $this->groupId, $this->quoteA, 1);

        $this->expectException(\Throwable::class);

        $this->model->awardSupplier($this->rfqId, $this->groupId, $this->quoteB, 1);
    }

    /**
     * A draft (unsubmitted) quote is not an offer and must not be
     * awardable.
     */
    public function testCannotAwardADraftQuote(): void
    {
        $this->db->query(
            "UPDATE rfq_group_quotes SET status = 'DRAFT' WHERE id = {$this->quoteB}"
        );

        $this->expectException(\Throwable::class);

        $this->model->awardSupplier($this->rfqId, $this->groupId, $this->quoteB, 1);
    }

    // =================================================================
    // 4. Failure handling
    // =================================================================

    public function testUnknownQuoteIdThrows(): void
    {
        $this->expectException(\Throwable::class);

        $this->model->awardSupplier($this->rfqId, $this->groupId, 999999, 1);
    }

    public function testNothingIsWrittenWhenTheAwardFails(): void
    {
        try {
            $this->model->awardSupplier($this->rfqId, $this->groupId, 999999, 1);
        } catch (\Throwable $e) {
            // expected
        }

        $this->assertSame(0, $this->countRows('purchase_orders'),
            'A failed award must leave no PO behind');
        $this->assertSame('PENDING', $this->decisionStatusOf($this->quoteA),
            'A failed award must not change any quote status');
        $this->assertSame('PENDING', $this->decisionStatusOf($this->quoteB));
    }
}
