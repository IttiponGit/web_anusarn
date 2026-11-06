-- ============================================================
-- QA v1.1
-- Phase 3.5 - Project Correction & Cancellation Management
-- Target: MariaDB 10.6+
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `qa_project_change_requests` (
  `request_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `request_type` VARCHAR(20) NOT NULL,
  `request_category` VARCHAR(30) DEFAULT NULL,
  `impact_level` VARCHAR(20) NOT NULL DEFAULT 'major',
  `resolution_mode` VARCHAR(30) NOT NULL,
  `reason` TEXT NOT NULL,
  `change_summary` LONGTEXT,
  `previous_status_code` VARCHAR(30) NOT NULL,
  `request_status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `requested_by` INT UNSIGNED DEFAULT NULL,
  `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by` INT UNSIGNED DEFAULT NULL,
  `review_comment` TEXT,
  `reviewed_at` DATETIME DEFAULT NULL,
  `resolved_status_code` VARCHAR(30) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  KEY `idx_qa_project_change_project` (`project_id`),
  KEY `idx_qa_project_change_status` (`request_status`),
  KEY `idx_qa_project_change_type` (`request_type`),
  KEY `idx_qa_project_change_requested_by` (`requested_by`),
  KEY `idx_qa_project_change_reviewed_by` (`reviewed_by`),
  CONSTRAINT `fk_qa_project_change_project`
    FOREIGN KEY (`project_id`) REFERENCES `qa_projects` (`project_id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_qa_project_change_requested_by`
    FOREIGN KEY (`requested_by`) REFERENCES `qa_users` (`user_id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_qa_project_change_reviewed_by`
    FOREIGN KEY (`reviewed_by`) REFERENCES `qa_users` (`user_id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_qa_project_change_prev_status`
    FOREIGN KEY (`previous_status_code`) REFERENCES `qa_project_statuses` (`status_code`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_qa_project_change_resolved_status`
    FOREIGN KEY (`resolved_status_code`) REFERENCES `qa_project_statuses` (`status_code`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
