-- ============================================================
-- QA v1.4.6 — Phase 4.2.2 Activity Hierarchy & Assignment
-- กิจกรรมหลัก / กิจกรรมย่อย + ผู้รับผิดชอบรายกิจกรรม
-- Target: MariaDB 10.6+
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `qa_project_activities`
  ADD COLUMN `parent_activity_id` INT UNSIGNED NULL AFTER `project_id`;

ALTER TABLE `qa_project_activities`
  ADD KEY `idx_qa_project_activity_parent` (`parent_activity_id`);

ALTER TABLE `qa_project_activities`
  ADD CONSTRAINT `fk_qa_project_activity_parent`
    FOREIGN KEY (`parent_activity_id`) REFERENCES `qa_project_activities` (`activity_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT;
