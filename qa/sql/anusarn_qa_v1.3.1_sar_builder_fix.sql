-- ============================================================
-- QA v1.3.1 — Phase 4.1 SAR Builder Compatibility Fix
-- แก้ชื่อ table ไม่ให้ชนกับ qa_sar_reports / qa_sar_indicator_summaries เดิม
-- ใช้ได้หลังจาก v1.3 import เดิมล้มเหลว
-- Target: MariaDB 10.6+
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `qa_sar_builder_reports` (
  `report_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `year_id` INT UNSIGNED NOT NULL,
  `framework_id` INT UNSIGNED NOT NULL,
  `report_title` VARCHAR(255) NOT NULL,
  `status_code` VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
  `prepared_by` INT UNSIGNED DEFAULT NULL,
  `submitted_by` INT UNSIGNED DEFAULT NULL,
  `finalized_by` INT UNSIGNED DEFAULT NULL,
  `submitted_at` DATETIME DEFAULT NULL,
  `finalized_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`report_id`),
  UNIQUE KEY `uq_qa_sar_builder_year_framework` (`year_id`,`framework_id`),
  KEY `idx_qa_sar_builder_status` (`status_code`),
  CONSTRAINT `fk_qa_sar_builder_year`
    FOREIGN KEY (`year_id`) REFERENCES `qa_years` (`year_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_qa_sar_builder_framework`
    FOREIGN KEY (`framework_id`) REFERENCES `qa_quality_frameworks` (`framework_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_qa_sar_builder_prepared_by`
    FOREIGN KEY (`prepared_by`) REFERENCES `qa_users` (`user_id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_qa_sar_builder_submitted_by`
    FOREIGN KEY (`submitted_by`) REFERENCES `qa_users` (`user_id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_qa_sar_builder_finalized_by`
    FOREIGN KEY (`finalized_by`) REFERENCES `qa_users` (`user_id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qa_sar_builder_standard_entries` (
  `standard_entry_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id` INT UNSIGNED NOT NULL,
  `standard_id` INT UNSIGNED NOT NULL,
  `overview_text` LONGTEXT,
  `strengths_text` LONGTEXT,
  `improvement_text` LONGTEXT,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`standard_entry_id`),
  UNIQUE KEY `uq_qa_sar_builder_standard` (`report_id`,`standard_id`),
  KEY `idx_qa_sar_builder_standard_id` (`standard_id`),
  CONSTRAINT `fk_qa_sar_builder_standard_report`
    FOREIGN KEY (`report_id`) REFERENCES `qa_sar_builder_reports` (`report_id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_qa_sar_builder_standard_standard`
    FOREIGN KEY (`standard_id`) REFERENCES `qa_standards` (`standard_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_qa_sar_builder_standard_user`
    FOREIGN KEY (`updated_by`) REFERENCES `qa_users` (`user_id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qa_sar_builder_indicator_entries` (
  `entry_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id` INT UNSIGNED NOT NULL,
  `indicator_id` INT UNSIGNED NOT NULL,
  `auto_summary_snapshot` LONGTEXT,
  `analysis_text` LONGTEXT,
  `strengths_text` LONGTEXT,
  `improvement_text` LONGTEXT,
  `evidence_note` LONGTEXT,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`entry_id`),
  UNIQUE KEY `uq_qa_sar_builder_indicator` (`report_id`,`indicator_id`),
  KEY `idx_qa_sar_builder_indicator_id` (`indicator_id`),
  CONSTRAINT `fk_qa_sar_builder_indicator_report`
    FOREIGN KEY (`report_id`) REFERENCES `qa_sar_builder_reports` (`report_id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_qa_sar_builder_indicator_indicator`
    FOREIGN KEY (`indicator_id`) REFERENCES `qa_indicators` (`indicator_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_qa_sar_builder_indicator_user`
    FOREIGN KEY (`updated_by`) REFERENCES `qa_users` (`user_id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
