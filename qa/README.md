# ระบบประกันคุณภาพสถานศึกษา — Development v0.2

รองรับ PHP 5.6.40 + MariaDB 10.6 และฐานข้อมูล `anusarn_qa`

## ทำก่อนอัปโหลด
1. คัดลอก `includes/config.example.php` เป็น `includes/config.php`
2. ใส่ DB user/password จริง และเปลี่ยน `QA_SETUP_KEY` เป็นข้อความสุ่มยาว
3. Import `anusarn_qa_years.sql` (ไฟล์แจกแยก)
4. อัปโหลดโฟลเดอร์นี้เป็น `/qa/`
5. เปิด `/qa/setup-admin.php` เพื่อสร้าง Admin ครั้งแรก
6. ลบ `setup-admin.php` ออกจากเซิร์ฟเวอร์ทันที
7. เข้า `/qa/login.php`

## สิ่งที่ทำงานแล้ว
- เชื่อม MariaDB ผ่าน mysqli
- Session authentication
- password_hash / password_verify
- Role ของผู้ใช้ถูกโหลดเข้า session
- CSRF สำหรับ Login และ Setup Admin
- Dashboard อ่านปีและตัวเลขพื้นฐานจากฐานจริง
- แก้ syntax ทั้งชุดให้รองรับ PHP 5.6

## ยังต้องพัฒนาต่อ
- หน้างบประมาณประจำปีให้บันทึก DB
- จัดสรรงบ 4 ฝ่ายจริง
- CRUD แผน/วิสัยทัศน์/พันธกิจ/กลยุทธ์/จุดเน้น
- CRUD โครงการและ workflow อนุมัติ
- การเงิน/เบิกจ่าย
- รายงานผล/หลักฐาน/SAR


## Version 0.3 — Budget Pool CRUD

เพิ่มการทำงานจริงใน `/budget/index.php`

- อ่านปีงบประมาณปัจจุบันจาก `qa_years`
- อ่าน Master Data จาก `qa_budget_sources`
- เพิ่ม / แก้ไข / ลบ `qa_budget_pools`
- ใช้ CSRF token
- จำกัดสิทธิ์แก้ไขให้ `admin`, `director`, `plan`, `budget`
- ป้องกันการลบรายการที่ถูกอ้างอิงแล้ว
- บันทึก Audit Log ลง `qa_audit_logs`
- คำนวณงบรวม / จัดสรร 4 ฝ่าย / งบกลาง / ยังไม่ได้จัดสรรจากฐานข้อมูลจริง
- Dashboard จะอ่าน `qa_budget_pools` และเปลี่ยนยอดทันทีเมื่อบันทึกข้อมูล

### วิธีอัปเดตจาก v0.2
อัปโหลดทับไฟล์เดิมได้ทั้งหมด ยกเว้น `includes/config.php` ของเซิร์ฟเวอร์ ให้เก็บไฟล์เดิมไว้


## Version 0.4 — Allocation 4 Divisions

เพิ่มการทำงานจริง:

- `/budget/allocation.php`
  - อ่านก้อนงบจริงจาก `qa_budget_pools`
  - แบ่งแต่ละก้อนงบไปยัง 4 ฝ่าย + งบส่วนกลาง
  - ตรวจไม่ให้จัดสรรเกินวงเงินของก้อนงบ
  - คำนวณเปอร์เซ็นต์และยอดคงเหลือบนหน้าจอ
  - บันทึก `qa_budget_allocations`
  - ใช้ CSRF / Role Permission / Audit Log
  - รองรับหลายแหล่งเงิน โดยยังตรวจสอบย้อนกลับได้
- `/budget/allocation-detail.php`
  - อ่านวงเงินของฝ่ายจริง
  - แสดงที่มาของงบแต่ละแหล่ง
  - เตรียมสรุปโครงการ/อนุมัติ/เบิกจริงจากฐานข้อมูล
- `/dashboard.php`
  - แสดงสัดส่วนการจัดสรร 4 ฝ่ายจริง

ไม่ต้องแก้ฐานข้อมูลเพิ่มสำหรับ v0.4


## Version 0.5 — Plan Master Data

เพิ่ม `/plans/index.php` เพื่อจัดการข้อมูลแม่บทของระบบประกันคุณภาพ:

- แผนพัฒนาคุณภาพ (`qa_plans`)
- วิสัยทัศน์ (`qa_visions`)
- พันธกิจ (`qa_missions`)
- กลยุทธ์ (`qa_strategies`)
- จุดเน้นโรงเรียน (`qa_focus_areas`)
- เป้าหมาย/ตัวชี้วัดของแผน (`qa_plan_targets`)

ฟังก์ชัน:
- เพิ่ม / แก้ไข / ลบ
- CSRF
- Role Permission: admin, director, plan
- Audit Log
- ป้องกันการลบข้อมูลที่มีโครงการหรือข้อมูลอื่นเชื่อมโยง
- ใช้ฐานข้อมูล 45 ตารางเดิม ไม่ต้อง Import SQL เพิ่ม

หลังกรอก Master Data นี้แล้ว ขั้นถัดไปคือเชื่อม `/projects/create.php` ให้ใช้ข้อมูลจริงทั้งหมด


## Version 0.6 — School Values

เพิ่ม Master Data "ค่านิยมของสถานศึกษา"

Database:
- ตารางใหม่ `qa_school_values`
- ผูกกับ `qa_plans`
- รหัสค่านิยมไม่ซ้ำภายในแผนเดียวกัน

Web:
- `/plans/index.php`
- เพิ่ม / แก้ไข / ลบ ค่านิยม
- Audit Log
- CSRF
- Permission: admin, director, plan
- ปรับลำดับหน้าแผนเป็น:
  1. วิสัยทัศน์
  2. พันธกิจ
  3. ค่านิยมของสถานศึกษา
  4. กลยุทธ์
  5. จุดเน้นโรงเรียน
  6. เป้าหมาย / ตัวชี้วัดของแผน

ต้อง Import `anusarn_qa_v0.6_add_school_values.sql` ก่อนอัปโหลดเว็บ v0.6


## Version 0.7 — Project Proposal

เพิ่มโมดูลเสนอโครงการจริง:

Database migration:
- `qa_projects.expected_output`
- `qa_projects.expected_outcome`
- ตาราง `qa_project_target_links`

Web:
- `/projects/create.php`
  - ข้อมูลทั่วไป
  - กลุ่มเป้าหมาย
  - หลักการและเหตุผล
  - วัตถุประสงค์
  - พันธกิจ
  - กลยุทธ์
  - จุดเน้น
  - เป้าหมายของแผน
  - ตัวชี้วัด สมศ.
  - กิจกรรมและรายการงบประมาณ
  - เลือกแหล่งเงินที่ฝ่ายได้รับจริง
  - ตรวจวงเงินก่อนส่งเสนอ
  - KPI
  - Expected Output / Outcome
  - บันทึกร่าง / ส่งเสนอ
  - Status History / Approval Stages / Audit Log

- `/projects/index.php`
  - รายการจริง + Filter

- `/projects/view.php`
  - แสดงรายละเอียดจริงทุกความสัมพันธ์

หมายเหตุ:
- การแก้ไขร่าง (Edit Draft) จะทำในรุ่นถัดไป
- การตรวจสอบ/อนุมัติจริงจะทำในโมดูลถัดไป


## Version 0.8 — Workflow ตรวจสอบ/อนุมัติ + แก้ไขร่าง

เพิ่ม:
- `/approvals/index.php` เป็น Workflow จริง
  - งานแผน
  - งานงบประมาณ
  - ผู้อำนวยการ
  - ผ่าน / ส่งกลับแก้ไข / ไม่อนุมัติ
  - ความเห็น
  - Audit Log
  - Status History
- งานงบประมาณกำหนด `approved_amount` รายรายการได้
- ตรวจวงเงินอนุมัติไม่ให้เกินยอดขอหรือวงเงินของฝ่าย
- ผู้อำนวยการอนุมัติแล้วเปลี่ยนสถานะ `APPROVED`
- `/projects/edit.php`
  - แก้ไขได้เฉพาะ `DRAFT` / `REVISION`
  - เจ้าของโครงการหรือ admin
  - แก้ข้อมูล แผนสัมพันธ์ งบ KPI Output Outcome
  - ส่งใหม่แล้ว reset Workflow ทั้ง 3 ขั้น
- `/projects/view.php`
  - ปุ่มแก้ไขตามสิทธิ์/สถานะ
  - แสดงความเห็นผู้ตรวจ
- ไม่ต้องเพิ่มตารางใหม่ใน v0.8


## Version 0.9 — ดำเนินโครงการและติดตามผล

ใช้ตารางเดิมที่เตรียมไว้แล้ว ไม่ต้องเพิ่ม SQL

เพิ่ม:
- `/monitoring/index.php`
  - รายการจริงของ APPROVED / IN_PROGRESS / WAITING_REPORT / COMPLETED / CLOSED
  - ความก้าวหน้า
  - งบอนุมัติ / ใช้จริง
  - จำนวน KPI / หลักฐาน
- `/monitoring/project.php`
  - เริ่มดำเนินโครงการ APPROVED -> IN_PROGRESS
  - รายงานความก้าวหน้า `qa_progress_reports`
  - บันทึก KPI result `qa_project_kpi_results`
  - เบิกจ่ายจริง `qa_expenditures`
  - ตรวจไม่ให้เบิกเกิน approved_amount รายรายการ
  - หลักฐาน `qa_evidences`
  - เชื่อมหลักฐานกับตัวชี้วัด สมศ.
  - อัปโหลดไฟล์หรือ URL
  - สรุป Output / Outcome / ปัญหา / ข้อเสนอแนะ / บทเรียน
  - ส่งรายงานผล -> WAITING_REPORT
  - ผู้อำนวยการรับรอง -> COMPLETED
  - ปิดโครงการ -> CLOSED
- `/evidences/index.php`
  - คลังหลักฐานจริง
  - Filter ตามประเภท / ตัวชี้วัด / ค้นหา
- `/uploads/index.html`
  - ป้องกันการเปิดรายการไฟล์จากหน้า directory
  - ตัวอัปโหลด whitelist เฉพาะ PDF/รูป/Word/Excel และตั้งชื่อไฟล์ใหม่แบบสุ่ม

สิทธิ์:
- เจ้าของโครงการ / หัวหน้าฝ่าย / admin: ความก้าวหน้า หลักฐาน สรุปผล
- finance / admin: บันทึกเบิกจ่ายจริง
- director / admin: รับรองผลและปิดโครงการ


## Version 1.0 — Quality Intelligence / สมศ.

ไม่ต้องเพิ่ม SQL

เพิ่ม:
- `/quality/index.php`
  - Coverage Matrix จริง 3 มาตรฐาน / 16 ตัวชี้วัด
  - จำนวนโครงการ
  - งบอนุมัติ
  - เบิกจ่ายจริง
  - หลักฐาน
  - ผลโครงการที่รับรอง
  - สถานะข้อมูล: มีข้อมูลรองรับ / ต้องติดตาม / ยังไม่มีข้อมูล
  - Filter ตามมาตรฐานและสถานะ
- `/quality/indicator.php`
  - สารสนเทศรายตัวชี้วัด
  - เกณฑ์/ประเด็นพิจารณา
  - แนวทางหลักฐาน
  - ข้อความสรุปเพื่อใช้ตอบผู้ประเมิน
  - Gap checklist
  - รายชื่อโครงการและงบ
  - KPI / Output / Outcome
  - หลักฐานเชิงประจักษ์
- `/reports/index.php`
  - เปลี่ยนจาก TODO เป็นศูนย์ทางลัดสารสนเทศ
- `dashboard.php`
  - ความครอบคลุมตัวชี้วัดอิงปีงบประมาณปัจจุบัน
  - แสดงจำนวนตัวชี้วัดที่มีหลักฐาน
- Roadmap อัปเดตสถานะจริง

ข้อสำคัญ:
คำว่า “มีข้อมูลรองรับ” ในระบบไม่เท่ากับ “ผ่านการประเมิน สมศ.”
ระบบวัดเพียงความครบของข้อมูลที่ตรวจสอบได้จากฐานข้อมูล


## Version 1.0.1 — Safe Test Project Cleanup

เพิ่มเครื่องมือล้างข้อมูลโครงการทดสอบ:
- `/settings/test-data-cleanup.php`
- Admin only
- แสดง Preview จำนวนโครงการ / งบ / เบิกจ่าย / KPI / หลักฐานก่อนลบ
- ต้องติ๊กยืนยัน
- ต้องพิมพ์ข้อความ `ล้างข้อมูลโครงการทดสอบทั้งหมด`
- ต้องยืนยันรหัสผ่าน admin ปัจจุบัน
- CSRF protection
- Database Transaction + Rollback เมื่อมีข้อผิดพลาด
- ลบหลักฐานโครงการก่อนลบ project เพื่อรองรับ FK แบบ ON DELETE SET NULL
- ลบไฟล์หลักฐานจริงเฉพาะใต้ `/uploads/evidences/` และตรวจ path ก่อน unlink
- เก็บ Master Data, ปี, แผน, ตัวชี้วัด, งบประมาณ และการจัดสรรไว้
- หลัง cleanup สร้าง Audit Log ใหม่ 1 รายการเพื่อบันทึกว่าใครเป็นผู้ล้างข้อมูล


## Version 1.1 — Phase 3.5 Project Correction & Cancellation

ต้อง Import:
`anusarn_qa_v1.1_project_correction.sql`

เพิ่ม:
- `/projects/change-request.php`
  - เจ้าของโครงการ / หัวหน้าฝ่าย / admin สร้างคำขอ
  - Correction หรือ Cancellation
  - เหตุผล, หมวด, ระดับผลกระทบ, รายละเอียดข้อมูลที่ถูกต้อง
- `/projects/changes.php`
  - Queue คำขอ
  - director/admin พิจารณา
  - requester ถอนคำขอได้ก่อนพิจารณา
- `/includes/project_change.php`
  - สิทธิ์, กติกาสถานะ, pending freeze, execution guard

กติกา:
1. DRAFT / REVISION
   - แก้ไขตรงจากหน้าเดิม
   - หากต้องการยกเลิก สามารถส่ง Cancellation Request
2. SUBMITTED / PLAN_REVIEW / BUDGET_REVIEW / PENDING_APPROVAL / APPROVED
   - Correction ที่ยังไม่มีข้อมูลดำเนินงาน:
     APPROVED REQUEST -> REVISION
     Reset approvals -> แก้ข้อมูล -> ส่ง Workflow ใหม่
3. IN_PROGRESS / WAITING_REPORT / COMPLETED / CLOSED
   - Correction:
     ไม่เขียนทับข้อมูลเดิม
     เก็บ Approved Amendment เพื่อรักษา Audit Trail
4. Cancellation
   - อนุมัติแล้ว -> CANCELLED
   - ไม่ลบ Workflow, KPI, Expenditure, Evidence หรือประวัติเดิม
5. ระหว่างมี request_status=pending
   - Approval queue ถูกพัก
   - Monitoring mutations ถูกพัก
   - ป้องกันสถานะเปลี่ยนระหว่างพิจารณา


## Version 1.1.1 — Bright UI Refresh

ไม่มี SQL Migration เพิ่ม

Theme:
- Sidebar จาก Navy/Dark → ฟ้าอ่อน/ขาว
- Menu text เป็นน้ำเงินเทา อ่านง่าย
- Active menu เป็น white card + blue accent
- Background หลักสว่างขึ้น
- Topbar, cards, form focus, login page ปรับให้สว่างและเบาลง
- ไม่เปลี่ยน layout, database หรือ workflow

เว็บไซต์หลัก:
- มี package แยก `main-site-qa-menu-patch.zip`
- เพิ่มเมนู `ระบบประกันคุณภาพ` → `/qa/`
- Script clone style จากเมนู `ติดต่อโรงเรียน`
  จึงไม่ต้องรู้ class ของ Theme เว็บไซต์หลักล่วงหน้า
