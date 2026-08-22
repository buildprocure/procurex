# awardItems() - design notes

Replaces group-level `awardSupplier()`. A buyer can now award any
quantity of a single line item to a supplier, and award the rest of that
same item's quantity to a different supplier - either in one call or
across separate award actions over time.

## Why item groups stopped being the award boundary

Item groups still exist and still do exactly one job: they group items
so an RFQ can be distributed to the right suppliers by category (civil,
electrical, plumbing, ...). They are no longer read or written by the
award path. `rfq_item_groups.status` is untouched by `awardItems()` - it
reflects distribution state (`SENT`, `QUOTED`, ...), not award state.

## What "who won" means now

Previously, one `rfq_group_quotes` row won or lost as a whole. Now the
unit of decision is `rfq_item_awards`: one row per (item, supplier,
quantity, price). A quote itself has no decision status any more; asking
"did supplier X win?" only makes sense per item, or by summing their
awards across the RFQ.

## Concurrency

Each award transaction opens with:

```sql
SELECT quantity, awarded_quantity FROM rfq_items WHERE id = ? FOR UPDATE
```

on every item in the batch, before validating anything. This is what
prevents two buyers (or one buyer double-clicking) from both reading
"400 remaining" and each awarding 400, producing 800 awarded against a
1000 item. The second transaction blocks on the row lock until the first
commits or rolls back, then re-reads the now-current remaining quantity.

## One PO per (RFQ, supplier), accumulated

The first award to a supplier on a given RFQ creates their PO. Every
later award to that same supplier - whether it's a different item, or
more of the same item - finds that PO and appends a line to it, then
recomputes `total_amount` as `SUM(purchase_order_items.line_total)`
rather than incrementing a running total. This was a deliberate choice
over incrementing: a sum can never drift from what its lines actually
add up to, whereas an accumulator can, if any single write is ever
retried, double-applied, or edited out of band.

Enforced by `purchase_orders.(rfq_id, supplier_company_id)` being a
unique key - the "find, else create" logic in `awardItems()` depends on
there being at most one row to find.

## Batches are all-or-nothing

`awardItems()` takes an array so a UI can submit a whole split (e.g. 600
to Alpha + 400 to Beta) in one call. The whole array is one transaction:
if the second line fails validation, the first line's award is rolled
back too, not left half-applied. `testAFailedSplitLeavesNeitherAwardApplied`
covers this directly.

## Validation, replacing the five awardSupplier defects

Each award line is checked against a single guarded query joining the
quote to the item's actual RFQ, requiring `SUBMITTED` status:

```sql
SELECT rgq.supplier_company_id, rgqi.unit_price
FROM rfq_group_quotes rgq
JOIN rfq_group_quote_items rgqi
    ON rgqi.rfq_group_quote_id = rgq.id
   AND rgqi.rfq_item_id = ?
JOIN rfq_item_groups rig ON rig.id = rgq.rfq_item_group_id
WHERE rgq.id = ?
  AND rig.rfq_id = ?
  AND rgq.status = 'SUBMITTED'
```

No match means: throw. This single lookup is what the old
`awardSupplier-findings.md` suggested as the fix for defects #1, #2 and
#5 (quote/group mismatch, quote/RFQ mismatch, draft quotes) - it's the
same idea, adapted to items instead of groups.

Defects #3 and #4 (double-award, re-award after decision) don't have
direct equivalents any more, because there is no longer a single
"decided" state to re-enter. Their replacement is the remaining-quantity
check under row lock described above: you cannot award more of an item
than is left, full stop, regardless of how many separate calls got you
there.

## Known limitation

If a supplier's quoted unit price implicitly assumed supplying the whole
quantity (e.g. a volume discount), awarding them a partial amount at that
same unit price may not reflect what they'd actually charge for the
smaller quantity. The system has no way to know this - it takes the
quoted unit price as fixed regardless of awarded quantity. Worth a note
to buyers in the UI at some point; not fixed here.

## Tests

`tests/Buyer/RFQ/AwardItemsTest.php` - not yet executed, same MySQL
constraint as before. Covers: full-quantity award, splitting in one call
and across separate calls, PO reuse and accumulation, all five guard
rails, snapshot immutability, and PO-total-never-drifts-from-its-lines.
