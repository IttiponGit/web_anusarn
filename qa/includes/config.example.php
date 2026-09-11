<?php
// คัดลอกไฟล์นี้เป็น includes/config.php แล้วแก้ค่าก่อนใช้งานจริง
// ห้าม commit / อัปโหลดไฟล์ config.php ไปยังที่สาธารณะ

define('QA_DB_HOST', 'localhost');
define('QA_DB_NAME', 'anusarn_qa');
define('QA_DB_USER', 'ใส่ชื่อผู้ใช้ฐานข้อมูล');
define('QA_DB_PASS', 'ใส่รหัสผ่านฐานข้อมูล');

// ใช้เฉพาะตอนเปิด /qa/setup-admin.php ครั้งแรก
// เปลี่ยนเป็นข้อความสุ่มยาว ๆ อย่างน้อย 24 ตัวอักษร
define('QA_SETUP_KEY', 'CHANGE-THIS-TO-A-LONG-RANDOM-SECRET');
