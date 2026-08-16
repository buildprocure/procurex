# awardSupplier() ??? findings

Notes from adding test coverage to `RFQModel::awardSupplier()`.

This function decides who wins an RFQ and writes the purchase order that
commits the buyer to pay. Shipment, invoicing and payment all anchor to
that PO, so a defect here surfaces as a commercial dispute rather than a
visible bug.

## What it does correctly

- Wraps everything in a transaction and rolls back on any failure
- Copies quoted lines into `purchase_order_items` as a **snapshot**, so a
  later edit to the quote cannot silently change an agreed PO
- Marks competing quotes `LOST` in the same transaction as the winner
- Defers closing the RFQ until every group is decided

That snapshot behaviour is the right call and worth keeping.

## Defects

### 1. The quote is never checked against the group

Step 1 loads the quote by id alone:

```sql
SELECT supplier_company_id, total_amount FROM rfq_group_quotes WHERE id = ?
```

`$groupId` is never used to validate it. Step 3 then runs:

```sql
UPDATE rfq_group_quotes SET decision_status = 'LOST'
WHERE rfq_item_group_id = ? AND id != ?
```

So passing a quote from a *different* group awards that quote, and marks
the quotes of the group you named as `LOST`. Two groups are corrupted in
one call, and a PO is created for materials nobody agreed to buy.

Covered by `testCannotAwardAQuoteBelongingToADifferentGroup`.

### 2. The group is never checked against the RFQ

`$rfqId` is written directly onto the PO:

```sql
INSERT INTO purchase_orders (rfq_id, supplier_company_id, total_amount, ...)
```

Nothing confirms `$groupId` belongs to `$rfqId`, so a PO can be attached
to an unrelated RFQ. Since `award_supplier.php` takes all three ids from
the request, this is reachable by editing a form value.

Covered by `testCannotAwardUsingAGroupFromADifferentRfq`.

### 3. No idempotency guard

Nothing prevents a second award. Calling it twice writes a second PO for
the same materials ??? the buyer is committed to pay twice. A double-clicked
button is enough.

Covered by `testAwardingTwiceDoesNotCreateASecondPurchaseOrder`.

### 4. An already-decided group can be re-awarded

The function does not check `rfq_item_groups.status` before proceeding.
A group already at `DECISION_MADE` can be awarded to a different supplier,
flipping the original winner to `LOST` **after** their PO exists and
possibly after they have shipped.

Covered by `testCannotAwardAGroupThatIsAlreadyDecided`.

### 5. Draft quotes are awardable

`rfq_group_quotes.status` distinguishes `DRAFT` from `SUBMITTED`, but the
award path ignores it. A quote the supplier never submitted is not an
offer and should not be awardable.

Covered by `testCannotAwardADraftQuote`.

## Suggested fix

One guarded lookup at the top replaces all five holes, and keeps the fix
inside the existing transaction:

```php
$stmt = $conn->prepare("
    SELECT q.supplier_company_id, q.total_amount
    FROM rfq_group_quotes q
    JOIN rfq_item_groups g ON g.id = q.rfq_item_group_id
    WHERE q.id = ?
      AND q.rfq_item_group_id = ?
      AND g.rfq_id = ?
      AND q.status = 'SUBMITTED'
      AND q.decision_status = 'PENDING'
      AND g.status <> 'DECISION_MADE'
");
$stmt->bind_param('iii', $quoteId, $groupId, $rfqId);
```

If this returns no row, throw. That single query enforces: the quote
belongs to the group, the group belongs to the RFQ, the quote was actually
submitted, it has not already been decided, and the group is still open.

For defence in depth against concurrent double-submits, a unique index
also helps, since one award per RFQ per supplier is the intended shape:

```sql
ALTER TABLE purchase_orders
  ADD UNIQUE KEY idx_po_rfq_supplier (rfq_id, supplier_company_id);
```

Consider `SELECT ... FOR UPDATE` on the group row too, so two simultaneous
awards serialise rather than interleave.

## Unrelated issue found while writing fixtures

`rfq_items.quantity` is `decimal(5,0)`:

- **No decimal places.** 12.5 tonnes stores as 13.
- **Maximum 99,999.** 150,000 bricks overflows.

This feeds `line_total` in both quotes and POs, so it is a money-accuracy
problem, not a display one. `decimal(15,3)` would match the precision used
elsewhere in the schema. Worth correcting before real volume.

## Running the tests

These are integration tests; they need MySQL.

```bash
docker compose exec db mysql -uroot -p -e "CREATE DATABASE ilife_test"
docker compose exec db sh -c 'mysqldump -uroot -p --no-data ilife | mysql -uroot -p ilife_test'

docker compose exec app composer install
docker compose exec app ./vendor/bin/phpunit --testsuite integration
```

The suite truncates tables between tests and refuses to run if
`TEST_MYSQL_DATABASE` is set to `ilife`, `prod` or `production`.

**These tests have not been executed.** They are written against a verified
export of the live schema and pass `php -l`, but no run has confirmed them.
Expect fixture adjustments on the first run, and expect the five guard-rail
tests to fail until the fix above is applied ??? those failures are the
point.
