-- =====================================================================
-- email_log ??? requires CREATE TABLE privilege (admin/DBA account)
-- =====================================================================
-- mysql -u root -p ilife < ADMIN_ONLY_create_email_log.sql
--
-- The application works without this table (Mailer::log() catches the
-- failure and writes to error_log instead), but you lose the delivery
-- audit trail until it exists.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `email_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `recipient` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `template` VARCHAR(100) NULL DEFAULT NULL,
  `related_type` VARCHAR(50) NULL DEFAULT NULL,
  `related_id` INT UNSIGNED NULL DEFAULT NULL,
  `status` ENUM('SENT','FAILED') NOT NULL,
  `error_message` VARCHAR(500) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_log_related` (`related_type`, `related_id`),
  KEY `idx_email_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
