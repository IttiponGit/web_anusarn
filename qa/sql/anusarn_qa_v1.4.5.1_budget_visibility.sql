-- ============================================================
-- QA v1.4.5.1 — Budget Visibility for General Users
-- ให้ครู/ผู้รับผิดชอบโครงการ และหัวหน้าฝ่ายดูงบรวมและการจัดสรรได้
-- โดยไม่เพิ่มสิทธิ์แก้ไขหรือจัดสรรงบ
-- Target: MariaDB 10.6+
-- ============================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `qa_role_permissions` (`role_id`,`permission_id`)
SELECT r.role_id,p.permission_id
FROM qa_roles r
CROSS JOIN qa_permissions p
WHERE r.role_code IN ('project_owner','division_head')
  AND p.permission_code IN ('budget.view','budget.allocation.view');
