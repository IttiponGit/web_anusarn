-- ============================================================
-- QA v1.4.8.1 — Target ↔ Focus Many-to-Many
-- เป้าหมาย 1 ข้อสามารถเชื่อมกับจุดเน้นได้หลายรายการ
-- Target: MariaDB 10.6+
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `qa_plan_target_focus_links` (
  `target_id` INT UNSIGNED NOT NULL,
  `focus_id` INT UNSIGNED NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`target_id`,`focus_id`),
  KEY `idx_qa_plan_target_focus_focus` (`focus_id`),
  KEY `idx_qa_plan_target_focus_sort` (`target_id`,`sort_order`),
  CONSTRAINT `fk_qa_plan_target_focus_target`
    FOREIGN KEY (`target_id`) REFERENCES `qa_plan_targets` (`target_id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_qa_plan_target_focus_focus`
    FOREIGN KEY (`focus_id`) REFERENCES `qa_focus_areas` (`focus_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ย้ายความสัมพันธ์แบบเดิม 1 เป้าหมาย : 1 จุดเน้น เข้า table กลางอัตโนมัติ
INSERT IGNORE INTO `qa_plan_target_focus_links`
(`target_id`,`focus_id`,`sort_order`)
SELECT `target_id`,`focus_id`,1
FROM `qa_plan_targets`
WHERE `focus_id` IS NOT NULL
  AND `focus_id` > 0;
