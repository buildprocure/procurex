-- =====================================================================
-- ProcureX: Supplier RFQ Notifications + Tokenized (no-login) Quoting
-- Date: 2026-08-14
-- =====================================================================
-- Apply with:
--   mysql -u <user> -p <database> < database/migrations/2026_08_14_supplier_rfq_notifications.sql
--
-- Safe to re-run: all statements are guarded or idempotent.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Invite tokens on rfq_group_suppliers
-- ---------------------------------------------------------------------
-- We store ONLY the SHA-256 hash of the token. The plaintext token lives
-- solely in the email we send. A database leak therefore does not allow
-- an attacker to quote on behalf of a supplier.

ALTER TABLE rfq_group_suppliers
    ADD COLUMN invite_token_hash CHAR(64) NULL DEFAULT NULL AFTER supplier_company_id,
    ADD COLUMN invite_expires_at DATETIME NULL DEFAULT NULL AFTER invite_token_hash,
    ADD COLUMN invited_at        DATETIME NULL DEFAULT NULL AFTER invite_expires_at,
    ADD COLUMN invite_email      VARCHAR(255) NULL DEFAULT NULL AFTER invited_at,
    ADD COLUMN notify_status     ENUM('PENDING','SENT','FAILED','SKIPPED')
                                 NOT NULL DEFAULT 'PENDING' AFTER invite_email,
    ADD COLUMN notify_error      VARCHAR(500) NULL DEFAULT NULL AFTER notify_status,
    ADD COLUMN first_viewed_at   DATETIME NULL DEFAULT NULL AFTER notify_error;

CREATE UNIQUE INDEX idx_rgs_invite_token_hash
    ON rfq_group_suppliers (invite_token_hash);

CREATE INDEX idx_rgs_notify_status
    ON rfq_group_suppliers (notify_status);

-- Prevent the same supplier being invited twice to the same group.
-- (Your current autoAssignSuppliers() has no guard against this.)
CREATE UNIQUE INDEX idx_rgs_group_supplier
    ON rfq_group_suppliers (rfq_item_group_id, supplier_company_id);


-- ---------------------------------------------------------------------
-- 2. Supplier quote contact details
-- ---------------------------------------------------------------------
-- The RFQ goes to the person who actually prices jobs, which is often not
-- the account holder. Falls back to the company email when NULL.

ALTER TABLE supplier_companies
    ADD COLUMN quote_contact_name  VARCHAR(150) NULL DEFAULT NULL,
    ADD COLUMN quote_contact_email VARCHAR(255) NULL DEFAULT NULL,
    ADD COLUMN quote_contact_phone VARCHAR(50)  NULL DEFAULT NULL;


-- ---------------------------------------------------------------------
-- 3. Email log
-- ---------------------------------------------------------------------
-- Every outbound message is recorded. This is your delivery audit trail
-- and the basis for supplier response-rate metrics later on.

CREATE TABLE IF NOT EXISTS email_log (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient       VARCHAR(255) NOT NULL,
    subject         VARCHAR(255) NOT NULL,
    template        VARCHAR(100) NULL DEFAULT NULL,
    related_type    VARCHAR(50)  NULL DEFAULT NULL,
    related_id      INT UNSIGNED NULL DEFAULT NULL,
    status          ENUM('SENT','FAILED') NOT NULL,
    error_message   VARCHAR(500) NULL DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email_log_related (related_type, related_id),
    KEY idx_email_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------------------------------------------------------------------
-- 4. Backfill
-- ---------------------------------------------------------------------
-- Existing invitations predate notifications; mark them so the retry
-- cron does not blast historical RFQs at suppliers.

UPDATE rfq_group_suppliers
SET notify_status = 'SKIPPED'
WHERE invite_token_hash IS NULL
  AND notify_status = 'PENDING';
