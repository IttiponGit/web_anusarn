-- ============================================================
-- QA v1.2.1 — Phase 3.6.1
-- ปรับโครงสร้างโรงเรียนเฉพาะความพิการจาก 4 ฝ่าย เป็น 5 ฝ่าย
-- เพิ่ม "กลุ่มบริหารกิจการนักเรียน" และ Mapping รหัส สพฐ. 155
-- Target: MariaDB 10.6+
-- ============================================================

SET NAMES utf8mb4;

INSERT INTO `qa_divisions`
(`division_code`,`division_name`,`short_name`,`sort_order`,`is_active`)
VALUES
('STUDENT_AFFAIRS','กลุ่มบริหารกิจการนักเรียน','กิจการนักเรียน',50,1)
ON DUPLICATE KEY UPDATE
  `division_name`=VALUES(`division_name`),
  `short_name`=VALUES(`short_name`),
  `sort_order`=VALUES(`sort_order`),
  `is_active`=1;

UPDATE `qa_obec_subactivities`
SET `default_division_id`=(
    SELECT d.division_id
    FROM qa_divisions d
    WHERE d.division_code='STUDENT_AFFAIRS'
    LIMIT 1
)
WHERE `subactivity_code`='155';
