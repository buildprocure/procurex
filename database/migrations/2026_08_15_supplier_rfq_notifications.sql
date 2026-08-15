-- =====================================================================
-- ProcureX: Supplier RFQ Notifications + Tokenized (no-login) Quoting
-- Corrected for actual schema: unified `companies` table (type enum),
-- MySQL 5.7+ compatible (no IF NOT EXISTS on columns/indexes), and
-- split out the admin-only CREATE TABLE separately.
-- =====================================================================
-- Run as the normal app DB user:
--   mysql -u user_read_write -p ilife < 2026_08_15_supplier_rfq_notifications.sql
--
-- Then run ADMIN_ONLY_create_email_log.sql separately with an account
-- that has CREATE TABLE privilege.
-- =====================================================================

-- 1. Contact fields on companies (used for both buyers and suppliers)
ALTER TABLE `companies` ADD COLUMN `email` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `companies` ADD COLUMN `quote_contact_name` VARCHAR(150) NULL DEFAULT NULL;
ALTER TABLE `companies` ADD COLUMN `quote_contact_email` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `companies` ADD COLUMN `quote_contact_phone` VARCHAR(50) NULL DEFAULT NULL;

-- 2. Invite token + notification tracking on rfq_group_suppliers
ALTER TABLE `rfq_group_suppliers` ADD COLUMN `invite_token_hash` CHAR(64) NULL DEFAULT NULL;
ALTER TABLE `rfq_group_suppliers` ADD COLUMN `invite_expires_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `rfq_group_suppliers` ADD COLUMN `invite_email` VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE `rfq_group_suppliers` ADD COLUMN `notify_status` ENUM('PENDING','SENT','FAILED','SKIPPED') NOT NULL DEFAULT 'PENDING';
ALTER TABLE `rfq_group_suppliers` ADD COLUMN `notify_error` VARCHAR(500) NULL DEFAULT NULL;
ALTER TABLE `rfq_group_suppliers` ADD COLUMN `first_viewed_at` DATETIME NULL DEFAULT NULL;

-- 3. Indexes
ALTER TABLE `rfq_group_suppliers` ADD UNIQUE KEY `idx_rgs_invite_token_hash` (`invite_token_hash`);
ALTER TABLE `rfq_group_suppliers` ADD UNIQUE KEY `idx_rgs_group_supplier` (`rfq_item_group_id`, `supplier_company_id`);
ALTER TABLE `rfq_group_suppliers` ADD KEY `idx_rgs_notify_status` (`notify_status`);

-- 4. Backfill: mark pre-existing invitations SKIPPED so the retry cron
--    doesn't try to notify suppliers about old RFQs that predate this
--    feature.
UPDATE `rfq_group_suppliers`
  SET `notify_status` = 'SKIPPED'
  WHERE `invite_token_hash` IS NULL
    AND `notify_status` = 'PENDING';
