-- QA v1.2 — Phase 3.6 กิจกรรมย่อย สพฐ. / ปริมาณงาน
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `qa_obec_subactivities` (
  `subactivity_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subactivity_code` VARCHAR(10) NOT NULL,
  `subactivity_name` VARCHAR(255) NOT NULL,
  `institution_type` VARCHAR(120) NOT NULL DEFAULT 'โรงเรียนการศึกษาพิเศษ',
  `unit_name` VARCHAR(50) NOT NULL DEFAULT 'โครงการ',
  `default_division_id` INT UNSIGNED DEFAULT NULL,
  `allocation_weight` DECIMAL(5,2) DEFAULT NULL,
  `source_reference` VARCHAR(255) DEFAULT 'คู่มือการกำหนดกิจกรรมย่อยของ สพฐ.',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`subactivity_id`),
  UNIQUE KEY `uq_qa_obec_subactivity_code` (`subactivity_code`),
  KEY `idx_qa_obec_subactivity_division` (`default_division_id`),
  CONSTRAINT `fk_qa_obec_subactivity_division` FOREIGN KEY (`default_division_id`) REFERENCES `qa_divisions` (`division_id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `qa_obec_subactivities`
(`subactivity_code`,`subactivity_name`,`institution_type`,`unit_name`,`default_division_id`,`allocation_weight`,`sort_order`,`is_active`)
VALUES
('151','การบริหารงานวิชาการ','โรงเรียนการศึกษาพิเศษ','โครงการ',(SELECT division_id FROM qa_divisions WHERE division_code='ACADEMIC' LIMIT 1),40.00,151,1),
('152','การบริหารงานทั่วไป','โรงเรียนการศึกษาพิเศษ','โครงการ',(SELECT division_id FROM qa_divisions WHERE division_code='GENERAL' LIMIT 1),10.00,152,1),
('153','การบริหารงานบุคคล','โรงเรียนการศึกษาพิเศษ','โครงการ',(SELECT division_id FROM qa_divisions WHERE division_code='PERSONNEL' LIMIT 1),10.00,153,1),
('154','การบริหารงานงบประมาณและแผนงาน','โรงเรียนการศึกษาพิเศษ','โครงการ',(SELECT division_id FROM qa_divisions WHERE division_code='BUDGET' LIMIT 1),5.00,154,1),
('155','การบริหารกิจการนักเรียน','โรงเรียนการศึกษาพิเศษ','โครงการ',NULL,35.00,155,1)
ON DUPLICATE KEY UPDATE subactivity_name=VALUES(subactivity_name),institution_type=VALUES(institution_type),unit_name=VALUES(unit_name),allocation_weight=VALUES(allocation_weight),sort_order=VALUES(sort_order),is_active=1;

CREATE TABLE IF NOT EXISTS `qa_project_obec_subactivity_links` (
  `project_id` INT UNSIGNED NOT NULL,
  `subactivity_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`project_id`),
  KEY `idx_qa_project_obec_subactivity` (`subactivity_id`),
  CONSTRAINT `fk_qa_project_obec_project` FOREIGN KEY (`project_id`) REFERENCES `qa_projects` (`project_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_qa_project_obec_subactivity` FOREIGN KEY (`subactivity_id`) REFERENCES `qa_obec_subactivities` (`subactivity_id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qa_obec_workload_records` (
  `record_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `year_id` INT UNSIGNED NOT NULL,
  `subactivity_id` INT UNSIGNED NOT NULL,
  `manual_quantity` INT UNSIGNED DEFAULT NULL,
  `change_reason` TEXT,
  `source_note` VARCHAR(255) DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`record_id`),
  UNIQUE KEY `uq_qa_obec_workload_year_subactivity` (`year_id`,`subactivity_id`),
  CONSTRAINT `fk_qa_obec_workload_year` FOREIGN KEY (`year_id`) REFERENCES `qa_years` (`year_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_qa_obec_workload_subactivity` FOREIGN KEY (`subactivity_id`) REFERENCES `qa_obec_subactivities` (`subactivity_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_qa_obec_workload_user` FOREIGN KEY (`updated_by`) REFERENCES `qa_users` (`user_id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
