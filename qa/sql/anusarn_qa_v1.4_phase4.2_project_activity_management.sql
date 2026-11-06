-- ============================================================
-- QA v1.4 — Phase 4.2 Project Activity Management
-- กิจกรรมภายใต้โครงการ + ผู้รับผิดชอบรายกิจกรรม + รายงานความก้าวหน้า
-- Target: MariaDB 10.6+
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `qa_project_activities`
  ADD COLUMN IF NOT EXISTS `activity_description` TEXT NULL AFTER `activity_name`,
  ADD COLUMN IF NOT EXISTS `progress_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `status`,
  ADD COLUMN IF NOT EXISTS `output_summary` LONGTEXT NULL AFTER `progress_percent`,
  ADD COLUMN IF NOT EXISTS `outcome_summary` LONGTEXT NULL AFTER `output_summary`,
  ADD COLUMN IF NOT EXISTS `last_reported_at` DATETIME NULL AFTER `outcome_summary`,
  ADD COLUMN IF NOT EXISTS `completed_at` DATETIME NULL AFTER `last_reported_at`;

CREATE TABLE IF NOT EXISTS `qa_activity_progress_reports` (
  `activity_report_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `activity_id` INT UNSIGNED NOT NULL,
  `report_no` INT UNSIGNED NOT NULL,
  `report_date` DATE NOT NULL,
  `progress_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `activity_summary` LONGTEXT NOT NULL,
  `output_summary` LONGTEXT NULL,
  `outcome_summary` LONGTEXT NULL,
  `problem_summary` LONGTEXT NULL,
  `next_step_summary` LONGTEXT NULL,
  `reported_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`activity_report_id`),
  UNIQUE KEY `uq_qa_activity_report_no` (`activity_id`,`report_no`),
  KEY `idx_qa_activity_report_user` (`reported_by`),
  KEY `idx_qa_activity_report_date` (`report_date`),
  CONSTRAINT `fk_qa_activity_report_activity`
    FOREIGN KEY (`activity_id`) REFERENCES `qa_project_activities` (`activity_id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_qa_activity_report_user`
    FOREIGN KEY (`reported_by`) REFERENCES `qa_users` (`user_id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
