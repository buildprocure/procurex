-- =====================================================================
-- Item-level awarding, with quantity splitting across suppliers
-- =====================================================================
-- Replaces group-level awardSupplier(). A buyer can now award part or
-- all of a single line item's quantity to one supplier, and the rest to
-- a different supplier, at each supplier's quoted unit price.
--
-- Item groups remain in the schema unchanged: they still drive RFQ
-- distribution to suppliers. They are no longer touched by, or
-- meaningful to, the award decision.
-- =====================================================================

-- 1. rfq_items.quantity was decimal(5,0): no decimal places, ceiling of
--    99,999. That was already a money-accuracy defect (12.5 tonnes
--    stored as 13); it is now also a functional blocker, since splitting
--    a quantity across suppliers routinely produces fractional amounts.
ALTER TABLE `rfq_items`
  MODIFY COLUMN `quantity` DECIMAL(15,3) NOT NULL;

-- 2. Running award total + status, tracked directly on the item so the
--    award transaction can lock this exact row (SELECT ... FOR UPDATE)
--    and read the remaining quantity and the lock in the same statement.
ALTER TABLE `rfq_items`
  ADD COLUMN `awarded_quantity` DECIMAL(15,3) NOT NULL DEFAULT 0;

ALTER TABLE `rfq_items`
  ADD COLUMN `award_status` ENUM('OPEN','PARTIALLY_AWARDED','FULLY_AWARDED')
             NOT NULL DEFAULT 'OPEN';

-- 3. One row per (item, supplier, quantity) award decision. This is the
--    new unit of "who won what" - a quote no longer wins or loses as a
--    whole; each of its lines can be awarded independently and partially.
CREATE TABLE `rfq_item_awards` (
  `id`                  INT NOT NULL AUTO_INCREMENT,
  `rfq_item_id`         INT NOT NULL,
  `rfq_group_quote_id`  INT NOT NULL,
  `supplier_company_id` INT NOT NULL,
  `awarded_quantity`    DECIMAL(15,3) NOT NULL,
  `unit_price`          DECIMAL(15,2) NOT NULL,
  `line_total`          DECIMAL(15,2) NOT NULL,
  `purchase_order_id`   INT DEFAULT NULL,
  `created_by`          INT NOT NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ria_item` (`rfq_item_id`),
  KEY `idx_ria_quote` (`rfq_group_quote_id`),
  KEY `idx_ria_po` (`purchase_order_id`),
  CONSTRAINT `chk_ria_qty_positive` CHECK (`awarded_quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Trace a PO line back to the award that created it. Useful once a
--    single item can appear as separate lines on two different
--    suppliers' POs.
ALTER TABLE `purchase_order_items`
  ADD COLUMN `rfq_item_award_id` INT DEFAULT NULL;

-- 5. One PO per (rfq, supplier). Item-level awards to the same supplier
--    accumulate onto the same PO rather than creating a new one per
--    award action.
ALTER TABLE `purchase_orders`
  ADD UNIQUE KEY `idx_po_rfq_supplier` (`rfq_id`, `supplier_company_id`);
