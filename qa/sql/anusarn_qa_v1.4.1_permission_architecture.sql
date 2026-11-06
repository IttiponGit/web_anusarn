-- QA v1.4.1 — Permission Architecture & Deputy Director Readiness
SET NAMES utf8mb4;

INSERT INTO `qa_roles` (`role_code`,`role_name`,`description`,`is_active`)
SELECT 'deputy_director','รองผู้อำนวยการ','ผู้บริหารระดับรอง: ดูภาพรวม/กำกับติดตาม แต่ไม่อนุมัติขั้นสุดท้ายโดยอัตโนมัติ',1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `qa_roles` WHERE `role_code`='deputy_director');

UPDATE `qa_roles`
SET `role_name`='รองผู้อำนวยการ',
    `description`='ผู้บริหารระดับรอง: ดูภาพรวม/กำกับติดตาม แต่ไม่อนุมัติขั้นสุดท้ายโดยอัตโนมัติ',
    `is_active`=1
WHERE `role_code`='deputy_director';

CREATE TABLE IF NOT EXISTS `qa_permissions` (
  `permission_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `permission_code` VARCHAR(100) NOT NULL,
  `permission_name` VARCHAR(255) NOT NULL,
  `module_code` VARCHAR(50) NOT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `uq_qa_permission_code` (`permission_code`),
  KEY `idx_qa_permission_module` (`module_code`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qa_role_permissions` (
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `idx_qa_role_permission_permission` (`permission_id`),
  CONSTRAINT `fk_qa_role_permission_role` FOREIGN KEY (`role_id`) REFERENCES `qa_roles` (`role_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_qa_role_permission_permission` FOREIGN KEY (`permission_id`) REFERENCES `qa_permissions` (`permission_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('dashboard.view','ดู Dashboard','dashboard','เปิดหน้า Dashboard',10,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('dashboard.executive','ดู Dashboard ผู้บริหาร','dashboard','ดูข้อมูลภาพรวมระดับผู้บริหาร',20,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('plan.view','ดูแผนพัฒนาคุณภาพ','plan','ดูแผน วิสัยทัศน์ พันธกิจ กลยุทธ์ และเป้าหมาย',100,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('plan.manage','จัดการแผนพัฒนาคุณภาพ','plan','เพิ่ม/แก้ไขข้อมูล Master ของแผน',110,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('budget.view','ดูงบประมาณ','budget','ดูงบประมาณประจำปี',200,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('budget.manage','จัดการงบประมาณ','budget','เพิ่ม/แก้ไขก้อนงบประมาณ',210,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('budget.allocation.view','ดูการจัดสรรงบ','budget','ดูการจัดสรรงบ 5 ฝ่าย',220,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('budget.allocation.manage','จัดการการจัดสรรงบ','budget','บันทึก/แก้ไขการจัดสรรงบ',230,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('finance.record','บันทึกการเบิกจ่าย','budget','บันทึกค่าใช้จ่ายจริงของโครงการ',240,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('project.view_all','ดูโครงการทุกฝ่าย','project','ดูโครงการทั้งหมดในระบบ',300,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('project.view_division','ดูโครงการในฝ่าย','project','ดูโครงการของฝ่ายหลักที่ตนรับผิดชอบ',310,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('project.view_own','ดูโครงการที่เกี่ยวข้องกับตน','project','ดูโครงการที่ตนเป็นเจ้าของหรือได้รับมอบหมายกิจกรรม',320,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('project.create','เสนอโครงการ','project','สร้างและส่งโครงการใหม่',330,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('project.edit_own','แก้ไขโครงการของตน','project','แก้ไขโครงการที่ตนรับผิดชอบในสถานะที่อนุญาต',340,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('project.change.request','ขอแก้ไข/ยกเลิกโครงการ','project','ส่งคำขอแก้ไขหรือยกเลิกโครงการตามขอบเขตที่รับผิดชอบ',350,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('project.change.review','พิจารณาคำขอแก้ไข/ยกเลิก','project','อนุมัติ/ไม่อนุมัติคำขอแก้ไขหรือยกเลิก',360,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('project.approve.plan','อนุมัติขั้นงานแผน','project','พิจารณาโครงการในขั้นงานแผน',370,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('project.approve.budget','อนุมัติขั้นงบประมาณ','project','พิจารณาโครงการในขั้นงานงบประมาณ',380,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('project.approve.final','อนุมัติโครงการขั้นสุดท้าย','project','อนุมัติโครงการขั้นผู้อำนวยการ',390,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('activity.view_all','ดูกิจกรรมทุกฝ่าย','activity','ดูกิจกรรมภายใต้โครงการทั้งหมด',400,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('activity.view_division','ดูกิจกรรมในฝ่าย','activity','ดูกิจกรรมภายในฝ่ายหลัก',410,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('activity.view_own','ดูกิจกรรมที่เกี่ยวข้องกับตน','activity','ดูกิจกรรมที่ได้รับมอบหมายหรืออยู่ในโครงการของตน',420,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('activity.manage.context','จัดการกิจกรรมตามขอบเขต','activity','มอบหมาย/จัดการกิจกรรมในโครงการที่มีสิทธิ์',430,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('activity.report.context','รายงานกิจกรรมตามขอบเขต','activity','รายงานความก้าวหน้ากิจกรรมที่ได้รับมอบหมาย',440,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('monitoring.view_all','ดูการติดตามทุกฝ่าย','monitoring','ดูข้อมูลติดตามโครงการทั้งหมด',500,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('monitoring.view_division','ดูการติดตามในฝ่าย','monitoring','ดูข้อมูลติดตามของฝ่ายหลัก',510,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('monitoring.view_own','ดูการติดตามที่เกี่ยวข้องกับตน','monitoring','ดูข้อมูลติดตามโครงการที่ตนเกี่ยวข้อง',520,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('monitoring.manage.context','รายงานผลโครงการตามขอบเขต','monitoring','บันทึกความก้าวหน้า/KPI/สรุปผลในโครงการที่มีสิทธิ์',530,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('monitoring.result.approve','รับรองผลโครงการ','monitoring','รับรองผลสรุปโครงการ',540,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('monitoring.finance','บันทึกค่าใช้จ่ายจริง','monitoring','บันทึกการเบิกจ่ายจริง',550,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('evidence.view_all','ดูหลักฐานทุกฝ่าย','evidence','ดูคลังหลักฐานทั้งหมด',600,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('evidence.view_division','ดูหลักฐานในฝ่าย','evidence','ดูหลักฐานของฝ่ายหลัก',610,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('evidence.view_own','ดูหลักฐานที่เกี่ยวข้องกับตน','evidence','ดูหลักฐานของโครงการ/กิจกรรมที่ตนเกี่ยวข้อง',620,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('evidence.add.context','เพิ่มหลักฐานตามขอบเขต','evidence','เพิ่มหลักฐานในโครงการหรือกิจกรรมที่รับผิดชอบ',630,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('quality.view','ดูมาตรฐาน/ตัวชี้วัด','quality','ดูข้อมูลมาตรฐานและตัวชี้วัด',700,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('reports.view','ดูรายงาน','reports','ดูรายงานภาพรวมของระบบ',800,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('sar.view','ดู SAR','reports','ดู SAR Builder และฉบับรายงาน',810,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('sar.edit','จัดทำ SAR','reports','เขียน/แก้ไข SAR และส่งตรวจรับรอง',820,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('sar.finalize','รับรอง SAR','reports','รับรอง SAR เป็นฉบับ FINAL',830,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('obec.view','ดูปริมาณงาน สพฐ.','reports','ดูรายงานแบบ 10 / ปริมาณงาน สพฐ.',840,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('obec.manage','จัดทำปริมาณงาน สพฐ.','reports','บันทึก Baseline และเหตุผลการเปลี่ยนแปลง',850,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('settings.manage','เข้าหน้าตั้งค่าระบบ','system','เข้าหน้าตั้งค่าระบบ',900,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('users.manage','จัดการผู้ใช้งาน','system','เพิ่ม/แก้ไขผู้ใช้ Role และสถานะบัญชี',910,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('permissions.manage','จัดการสิทธิ์ Role','system','กำหนด Permission ให้ Role',920,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('obec.master','จัดการ Master สพฐ.','system','จัดการ Master กิจกรรมย่อย สพฐ. 151-155',930,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('roadmap.view','ดู Roadmap','system','ดู Roadmap การพัฒนาระบบ',940,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('system.test_cleanup','ล้างข้อมูลทดสอบ','system','ใช้เครื่องมือล้างข้อมูลโครงการทดสอบ',950,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

DELETE rp FROM `qa_role_permissions` rp INNER JOIN `qa_roles` r ON r.role_id=rp.role_id WHERE r.role_code='admin';
INSERT IGNORE INTO `qa_role_permissions` (`role_id`,`permission_id`) SELECT r.role_id,p.permission_id FROM `qa_roles` r CROSS JOIN `qa_permissions` p WHERE r.role_code='admin' AND p.permission_code IN ('dashboard.view','dashboard.executive','plan.view','plan.manage','budget.view','budget.manage','budget.allocation.view','budget.allocation.manage','finance.record','project.view_all','project.view_division','project.view_own','project.create','project.edit_own','project.change.request','project.change.review','project.approve.plan','project.approve.budget','project.approve.final','activity.view_all','activity.view_division','activity.view_own','activity.manage.context','activity.report.context','monitoring.view_all','monitoring.view_division','monitoring.view_own','monitoring.manage.context','monitoring.result.approve','monitoring.finance','evidence.view_all','evidence.view_division','evidence.view_own','evidence.add.context','quality.view','reports.view','sar.view','sar.edit','sar.finalize','obec.view','obec.manage','settings.manage','users.manage','permissions.manage','obec.master','roadmap.view','system.test_cleanup');

DELETE rp FROM `qa_role_permissions` rp INNER JOIN `qa_roles` r ON r.role_id=rp.role_id WHERE r.role_code='director';
INSERT IGNORE INTO `qa_role_permissions` (`role_id`,`permission_id`) SELECT r.role_id,p.permission_id FROM `qa_roles` r CROSS JOIN `qa_permissions` p WHERE r.role_code='director' AND p.permission_code IN ('dashboard.view','dashboard.executive','plan.view','budget.view','budget.allocation.view','project.view_all','project.create','project.change.review','project.approve.final','activity.view_all','monitoring.view_all','monitoring.result.approve','evidence.view_all','quality.view','reports.view','sar.view','sar.finalize','obec.view');

DELETE rp FROM `qa_role_permissions` rp INNER JOIN `qa_roles` r ON r.role_id=rp.role_id WHERE r.role_code='deputy_director';
INSERT IGNORE INTO `qa_role_permissions` (`role_id`,`permission_id`) SELECT r.role_id,p.permission_id FROM `qa_roles` r CROSS JOIN `qa_permissions` p WHERE r.role_code='deputy_director' AND p.permission_code IN ('dashboard.view','dashboard.executive','plan.view','budget.view','budget.allocation.view','project.view_all','project.create','activity.view_all','monitoring.view_all','evidence.view_all','quality.view','reports.view','sar.view','obec.view');

DELETE rp FROM `qa_role_permissions` rp INNER JOIN `qa_roles` r ON r.role_id=rp.role_id WHERE r.role_code='plan';
INSERT IGNORE INTO `qa_role_permissions` (`role_id`,`permission_id`) SELECT r.role_id,p.permission_id FROM `qa_roles` r CROSS JOIN `qa_permissions` p WHERE r.role_code='plan' AND p.permission_code IN ('dashboard.view','plan.view','plan.manage','budget.view','budget.manage','budget.allocation.view','budget.allocation.manage','project.view_all','project.create','project.approve.plan','activity.view_all','monitoring.view_all','evidence.view_all','quality.view','reports.view','sar.view','sar.edit','obec.view','obec.manage');

DELETE rp FROM `qa_role_permissions` rp INNER JOIN `qa_roles` r ON r.role_id=rp.role_id WHERE r.role_code='budget';
INSERT IGNORE INTO `qa_role_permissions` (`role_id`,`permission_id`) SELECT r.role_id,p.permission_id FROM `qa_roles` r CROSS JOIN `qa_permissions` p WHERE r.role_code='budget' AND p.permission_code IN ('dashboard.view','budget.view','budget.manage','budget.allocation.view','budget.allocation.manage','project.view_all','project.create','project.approve.budget','activity.view_all','monitoring.view_all','evidence.view_all','quality.view','reports.view','obec.view','obec.manage');

DELETE rp FROM `qa_role_permissions` rp INNER JOIN `qa_roles` r ON r.role_id=rp.role_id WHERE r.role_code='finance';
INSERT IGNORE INTO `qa_role_permissions` (`role_id`,`permission_id`) SELECT r.role_id,p.permission_id FROM `qa_roles` r CROSS JOIN `qa_permissions` p WHERE r.role_code='finance' AND p.permission_code IN ('dashboard.view','budget.view','budget.allocation.view','finance.record','project.view_all','activity.view_all','monitoring.view_all','monitoring.finance','evidence.view_all','quality.view','reports.view','obec.view','obec.manage');

DELETE rp FROM `qa_role_permissions` rp INNER JOIN `qa_roles` r ON r.role_id=rp.role_id WHERE r.role_code='division_head';
INSERT IGNORE INTO `qa_role_permissions` (`role_id`,`permission_id`) SELECT r.role_id,p.permission_id FROM `qa_roles` r CROSS JOIN `qa_permissions` p WHERE r.role_code='division_head' AND p.permission_code IN ('dashboard.view','project.view_division','project.create','project.change.request','activity.view_division','activity.manage.context','activity.report.context','monitoring.view_division','monitoring.manage.context','evidence.view_division','evidence.add.context','quality.view','reports.view');

DELETE rp FROM `qa_role_permissions` rp INNER JOIN `qa_roles` r ON r.role_id=rp.role_id WHERE r.role_code='project_owner';
INSERT IGNORE INTO `qa_role_permissions` (`role_id`,`permission_id`) SELECT r.role_id,p.permission_id FROM `qa_roles` r CROSS JOIN `qa_permissions` p WHERE r.role_code='project_owner' AND p.permission_code IN ('dashboard.view','project.view_own','project.create','project.edit_own','project.change.request','activity.view_own','activity.manage.context','activity.report.context','monitoring.view_own','monitoring.manage.context','evidence.view_own','evidence.add.context','quality.view');

DELETE rp FROM `qa_role_permissions` rp INNER JOIN `qa_roles` r ON r.role_id=rp.role_id WHERE r.role_code='viewer';
INSERT IGNORE INTO `qa_role_permissions` (`role_id`,`permission_id`) SELECT r.role_id,p.permission_id FROM `qa_roles` r CROSS JOIN `qa_permissions` p WHERE r.role_code='viewer' AND p.permission_code IN ('dashboard.view','plan.view','budget.view','budget.allocation.view','project.view_all','activity.view_all','monitoring.view_all','evidence.view_all','quality.view','reports.view','sar.view','obec.view');

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('project.edit_all','แก้ไขโครงการทุกโครงการ','project','แก้ไขโครงการทุกโครงการในสถานะที่อนุญาต',345,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('project.change.request_all','ขอแก้ไข/ยกเลิกทุกโครงการ','project','สร้างคำขอแก้ไข/ยกเลิกแทนทุกโครงการ',355,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('activity.manage_all','จัดการกิจกรรมทุกโครงการ','activity','จัดการและมอบหมายกิจกรรมทุกโครงการ',435,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('activity.report_all','รายงานกิจกรรมทุกโครงการ','activity','บันทึกรายงานกิจกรรมทุกโครงการ',445,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT INTO `qa_permissions` (`permission_code`,`permission_name`,`module_code`,`description`,`sort_order`,`is_active`) VALUES ('monitoring.manage_all','จัดการติดตามทุกโครงการ','monitoring','บันทึกข้อมูลติดตาม/KPI/สรุปผลได้ทุกโครงการ',535,1) ON DUPLICATE KEY UPDATE `permission_name`=VALUES(`permission_name`),`module_code`=VALUES(`module_code`),`description`=VALUES(`description`),`sort_order`=VALUES(`sort_order`),`is_active`=1;

INSERT IGNORE INTO `qa_role_permissions` (`role_id`,`permission_id`) SELECT r.role_id,p.permission_id FROM qa_roles r CROSS JOIN qa_permissions p WHERE r.role_code='admin' AND p.permission_code IN ('project.edit_all','project.change.request_all','activity.manage_all','activity.report_all','monitoring.manage_all');

INSERT IGNORE INTO `qa_role_permissions` (`role_id`,`permission_id`) SELECT r.role_id,p.permission_id FROM qa_roles r CROSS JOIN qa_permissions p WHERE r.role_code='director' AND p.permission_code IN ('plan.manage','budget.manage','budget.allocation.manage');
