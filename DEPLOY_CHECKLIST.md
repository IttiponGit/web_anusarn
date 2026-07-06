# Deploy Checklist

## ก่อนอัปโหลดขึ้น hosting

- [ ] สำรองไฟล์เดิมบน hosting
- [ ] สำรองฐานข้อมูลเดิม
- [ ] ตรวจ `api/db.php` ว่าเป็นค่าของ hosting หรือ localhost ให้ถูกสภาพแวดล้อม
- [ ] ตรวจว่า `uploads/news`, `uploads/personnel`, `uploads/downloads` มีอยู่จริง
- [ ] ตรวจ permission โฟลเดอร์ upload
- [ ] รัน `php -l` กับไฟล์ PHP หลักทั้งหมด

## คำสั่งตรวจ PHP

```bash
php -l api/db.php
php -l api/news.php
php -l api/personnel.php
php -l api/downloads.php
php -l api/setting.php
php -l api/admin/news_admin.php
php -l api/admin/personnel_admin.php
php -l api/admin/downloads_admin.php
```

## หลังอัปโหลด

- [ ] เปิดหน้าแรก
- [ ] เปิดหน้าข่าว
- [ ] เปิดหน้าบุคลากร
- [ ] เปิดหน้าดาวน์โหลด
- [ ] Login admin
- [ ] เพิ่ม/แก้ไข/ลบ ข่าว
- [ ] เพิ่ม/แก้ไข/ลบ บุคลากร
- [ ] ทดสอบแก้บุคลากรโดยไม่เปลี่ยนรูป
- [ ] ทดสอบอัปโหลดไฟล์ดาวน์โหลด
- [ ] เปิด DevTools > Network แล้วตรวจว่าไม่มี 404/500
