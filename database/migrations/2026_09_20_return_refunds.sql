-- =====================================================================
-- Buyer returns and Stripe refunds (AB#70)
-- =====================================================================
-- A buyer raises a return request against the delivered items of a PO
-- (per line item and quantity). The supplier (or an Admin) approves or
-- rejects it. On approval and receipt of the goods, a full or partial
-- refund is issued through Stripe against the original payment(s).
--
-- Amounts and quantities are captured at request time (unit_price,
-- line_total) so later PO edits cannot change what was agreed.
-- Run once, manually, per environment.
-- =====================================================================

CREATE TABLE `return_requests` (
  `id`                  INT NOT NULL AUTO_INCREMENT,
  `invoice_id`          INT NOT NULL,
  `purchase_order_id`   INT NOT NULL,
  `buyer_company_id`    INT NOT NULL,
  `supplier_company_id` INT NOT NULL,
  `status`              ENUM('REQUESTED','APPROVED','REJECTED','REFUNDING','REFUNDED','REFUND_FAILED')
                        NOT NULL DEFAULT 'REQUESTED',
  `reason`              TEXT NOT NULL,
  `refund_total`        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `requested_by`        INT NOT NULL,
  `requested_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `decided_by`          INT NULL,
  `decided_at`          DATETIME NULL,
  `decision_comment`    TEXT NULL,
  `failure_message`     VARCHAR(500) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_return_invoice` (`invoice_id`),
  KEY `idx_return_po` (`purchase_order_id`),
  KEY `idx_return_buyer` (`buyer_company_id`),
  KEY `idx_return_supplier` (`supplier_company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `return_request_items` (
  `id`                     INT NOT NULL AUTO_INCREMENT,
  `return_request_id`      INT NOT NULL,
  `purchase_order_item_id` INT NOT NULL,
  `quantity`               DECIMAL(15,3) NOT NULL,
  `unit_price`             DECIMAL(15,2) NOT NULL,
  `line_total`             DECIMAL(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rri_return` (`return_request_id`),
  KEY `idx_rri_po_item` (`purchase_order_item_id`),
  CONSTRAINT `fk_rri_return` FOREIGN KEY (`return_request_id`)
    REFERENCES `return_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit trail: who did what, when, on every return.
CREATE TABLE `return_request_events` (
  `id`                INT NOT NULL AUTO_INCREMENT,
  `return_request_id` INT NOT NULL,
  `action`            VARCHAR(40) NOT NULL,
  `actor_user_id`     INT NULL,
  `actor_role`        VARCHAR(20) NULL,
  `comment`           TEXT NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rre_return` (`return_request_id`),
  CONSTRAINT `fk_rre_return` FOREIGN KEY (`return_request_id`)
    REFERENCES `return_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per Stripe refund call. A return may need several (one per
-- original payment it is refunded against) and may be retried after a
-- failure, so this is deliberately separate from return_requests.
CREATE TABLE `invoice_refunds` (
  `id`                 INT NOT NULL AUTO_INCREMENT,
  `return_request_id`  INT NOT NULL,
  `invoice_id`         INT NOT NULL,
  `invoice_payment_id` INT NOT NULL,
  `amount`             DECIMAL(15,2) NOT NULL,
  `status`             ENUM('PENDING','SUCCEEDED','FAILED') NOT NULL DEFAULT 'PENDING',
  `stripe_refund_id`   VARCHAR(64) NULL,
  `failure_message`    VARCHAR(500) NULL,
  `created_by`         INT NULL,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_refund_stripe` (`stripe_refund_id`),
  KEY `idx_refund_return` (`return_request_id`),
  KEY `idx_refund_invoice` (`invoice_id`),
  KEY `idx_refund_payment` (`invoice_payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cached refund state on the invoice, kept in sync on every refund
-- write (same idea as po_invoices.payment_status). Separate columns
-- rather than new payment_status values, so the existing
-- UNPAID/PARTIALLY_PAID/PAID logic is untouched.
ALTER TABLE `po_invoices`
  ADD COLUMN `refunded_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN `refund_status` ENUM('NONE','PARTIALLY_REFUNDED','REFUNDED') NOT NULL DEFAULT 'NONE';
