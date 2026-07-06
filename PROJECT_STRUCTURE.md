# โครงสร้างไฟล์ปลายทางของโปรเจกต์ web_anusarn

```text
web_anusarn/
├── index.html
├── about.html
├── news.html
├── news-detail.html
├── personnel.html
├── downloads.html
├── contact.html
├── css/
│   └── style.css
├── js/
│   └── main.js
├── admin/
│   ├── login.html
│   ├── dashboard.html
│   ├── news.html
│   ├── personnel.html
│   ├── downloads.html
│   ├── admin.css
│   └── admin.js
├── api/
│   ├── db.php
│   ├── news.php
│   ├── personnel.php
│   ├── downloads.php
│   ├── setting.php
│   └── admin/
│       ├── news_admin.php
│       ├── personnel_admin.php
│       └── downloads_admin.php
├── database/
│   ├── schema.sql
│   ├── seed.sql
│   └── README.md
├── data/
│   ├── news.json
│   ├── personnel.json
│   └── downloads.json
├── images/
│   ├── news/
│   └── personnel/
└── uploads/
    ├── news/
    ├── personnel/
    └── downloads/
```

## หลักการจัดไฟล์

- หน้าเว็บหลักอยู่ระดับ root
- ไฟล์หน้าบ้านใช้ `css/`, `js/`, `images/`
- หน้าแอดมินอยู่ใน `admin/`
- API สาธารณะอยู่ใน `api/`
- API สำหรับหลังบ้านอยู่ใน `api/admin/`
- ไฟล์ฐานข้อมูลอยู่ใน `database/`
- ไฟล์ที่ผู้ดูแลอัปโหลดอยู่ใน `uploads/`
- ไฟล์ JSON สำรองหรือ fallback อยู่ใน `data/`

## Path ที่ควรรักษา

จากหน้าเว็บ root:

```text
css/style.css
js/main.js
api/news.php
api/personnel.php
api/downloads.php
uploads/news/...
uploads/personnel/...
```

จากหน้าใน `admin/`:

```text
admin.css
admin.js
../api/admin/news_admin.php
../api/admin/personnel_admin.php
../api/admin/downloads_admin.php
```

จากไฟล์ใน `api/admin/`:

```text
../db.php
../../uploads/news/
../../uploads/personnel/
../../uploads/downloads/
```
