# Database Setup

ชื่อฐานข้อมูลของโปรเจกต์คือ `anusarn_db`

## ขั้นตอนใช้งาน

1. สร้างฐานข้อมูล (ถ้ายังไม่มี)
2. Import ไฟล์ `schema.sql`
3. Import ไฟล์ `seed.sql`

## ตัวอย่างคำสั่ง (MySQL)

```sql
CREATE DATABASE IF NOT EXISTS anusarn_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE anusarn_db;
SOURCE database/schema.sql;
SOURCE database/seed.sql;
```

หรือใช้คำสั่งผ่าน command line:

```bash
mysql -u YOUR_USERNAME -p anusarn_db < database/schema.sql
mysql -u YOUR_USERNAME -p anusarn_db < database/seed.sql
```

หมายเหตุ: ห้ามใส่ username/password จริงลงในไฟล์โปรเจกต์
