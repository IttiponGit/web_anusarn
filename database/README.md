# Database - เว็บไซต์โรงเรียนโสตศึกษาอนุสารสุนทร

โฟลเดอร์นี้ใช้เก็บไฟล์ฐานข้อมูลของโปรเจกต์ เพื่อให้ย้ายระหว่าง localhost และ hosting ได้เป็นระบบ

## ไฟล์สำคัญ

- `schema.sql` — โครงสร้างตารางหลักของระบบ
- `seed.sql` — ข้อมูลตั้งต้นที่ปลอดภัย เช่น site settings และค่าสถิติเริ่มต้น

## วิธี import บน localhost

1. สร้างฐานข้อมูลใหม่ เช่น `web_anusarn`
2. เปิด phpMyAdmin หรือ MySQL client
3. Import `schema.sql`
4. Import `seed.sql`
5. แก้ค่าเชื่อมต่อฐานข้อมูลใน `api/db.php` ให้ตรงกับ localhost

ตัวอย่างค่า localhost ที่พบบ่อย:

```php
$host = 'localhost';
$dbname = 'web_anusarn';
$username = 'root';
$password = '';
```

## วิธีใช้บน hosting

1. Export ฐานข้อมูลจริงจาก hosting ก่อนทุกครั้ง
2. สำรองไฟล์ `api/db.php` เดิมไว้ก่อน
3. Import เฉพาะไฟล์ที่จำเป็น ห้าม import ทับฐานข้อมูลจริงโดยไม่ backup
4. ตรวจว่า charset เป็น `utf8mb4`
5. ตรวจ permission ของโฟลเดอร์ upload

## โฟลเดอร์ upload ที่ระบบต้องมี

- `uploads/news/`
- `uploads/personnel/`
- `uploads/downloads/`

หากอัปโหลดขึ้น hosting ให้ตรวจว่า PHP เขียนไฟล์ในโฟลเดอร์เหล่านี้ได้

## ข้อควรระวัง

- ห้ามเก็บรหัสผ่านจริงไว้ใน `seed.sql`
- ห้าม commit ไฟล์ backup ฐานข้อมูลจริงที่มีข้อมูลส่วนตัว
- ก่อน deploy ให้ทดสอบคำสั่ง `php -l` กับไฟล์ API ทุกครั้ง
