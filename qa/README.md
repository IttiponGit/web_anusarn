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


## Version 1.1.2 — Dashboard Charts + Return to Main Website

ไม่ต้อง Import SQL เพิ่ม

เพิ่ม:
- Sidebar menu: `← กลับเว็บไซต์หลัก` → `/`
- Topbar shortcut: `← เว็บไซต์หลัก`
- Dashboard ใหม่แบบไม่พึ่ง Chart.js/CDN
  - กราฟภาพรวมการใช้งบประมาณ
  - Donut สถานะโครงการ
  - กราฟงบประมาณรายฝ่าย: จัดสรร / อนุมัติ / ใช้จริง
  - Ring gauges ความครอบคลุมตัวชี้วัด สมศ.
  - Coverage bars แยกตามมาตรฐาน

หมายเหตุ:
- Chart ทั้งหมดเป็น HTML/CSS + PHP data จึงไม่ต้องโหลด library ภายนอก
- ใช้ข้อมูลปีงบประมาณปัจจุบันจากฐานข้อมูลจริง


## Version 1.1.3 — Division Color System

ไม่ต้อง Import SQL เพิ่ม

กำหนดสีประจำ 4 ฝ่ายแบบคงที่ทั้งระบบ:
- วิชาการ (ACADEMIC) = น้ำเงิน
- งบประมาณ (BUDGET) = เขียว
- บุคคล (PERSONNEL) = ม่วง
- ทั่วไป (GENERAL) = ส้ม
- ส่วนกลาง/สำรอง = เทา

ปรับ:
- Dashboard: กราฟงบรายฝ่ายใช้สีประจำฝ่าย และใช้ความเข้ม 3 ระดับแทน จัดสรร/อนุมัติ/ใช้จริง
- จัดสรรงบ 4 ฝ่าย: Summary, progress bars, input cards และตารางใช้สีประจำฝ่าย
- รายละเอียดฝ่าย: ใช้ accent color ของฝ่ายที่กำลังเปิด
- Sidebar: เมนูจัดสรรงบมี 4 จุดสีเพื่อช่วยจำแนก

สีเป็นเพียง visual aid; ชื่อฝ่ายยังแสดงทุกจุดเพื่อไม่พึ่งสีเพียงอย่างเดียว


## Version 1.1.4 — Admin User Management

ไม่ต้อง Import SQL เพิ่ม ใช้ `qa_users`, `qa_roles`, `qa_user_roles` เดิม

เพิ่มเมนู Admin:
- `/settings/users.php`
- Sidebar → จัดการผู้ใช้งาน
- Settings → 4 ฝ่ายและผู้ใช้งาน → จัดการผู้ใช้งาน

ความสามารถ:
- เพิ่มบัญชีผู้ใช้
- แก้ไข Username / ชื่อ / ตำแหน่ง / Email / โทรศัพท์
- กำหนดฝ่ายหลัก
- กำหนดหลาย Role ต่อบัญชี
- รีเซ็ตรหัสผ่าน
- เปิด/ปิดบัญชี
- Filter ตามฝ่าย / สถานะ / ค้นหา
- Audit Log module `users`

Safety:
- Admin only
- CSRF
- Password ใหม่อย่างน้อย 8 ตัวอักษร
- ไม่แสดง password hash
- ไม่ลบผู้ใช้ถาวร ให้ปิดใช้งานแทน
- ห้ามปิดบัญชีตัวเอง
- ห้ามถอด role admin จากบัญชีที่กำลัง login
- ป้องกันการปิด/ถอดสิทธิ์ Admin คนสุดท้ายของระบบ


## Version 1.2 — Phase 3.6 กิจกรรมย่อย สพฐ. / ปริมาณงาน

ต้อง Import `anusarn_qa_v1.2_phase3.6_obec_subactivities.sql`
เพิ่ม Master 151-155, ผูกโครงการ, รายงานแบบ 10, เปรียบเทียบปีก่อน, ตรวจ ±20%, เหตุผล, Print และ Master Mapping ฝ่าย. สัดส่วน 40/10/10/5/35 เก็บเป็นข้อมูลอ้างอิง ไม่แบ่งงบอัตโนมัติ.


## Version 1.2.1 — Phase 3.6.1 Five Divisions

ต้อง Import `anusarn_qa_v1.2.1_phase3.6.1_five_divisions.sql`

ปรับโครงสร้างโรงเรียนเฉพาะความพิการเป็น 5 กลุ่มบริหาร:
- ACADEMIC — กลุ่มบริหารวิชาการ
- BUDGET — กลุ่มบริหารงบประมาณ
- PERSONNEL — กลุ่มบริหารงานบุคคล
- GENERAL — กลุ่มบริหารทั่วไป
- STUDENT_AFFAIRS — กลุ่มบริหารกิจการนักเรียน

ผลกระทบ:
- จัดสรรงบ 5 ฝ่าย + ส่วนกลาง
- Dashboard รองรับสีฝ่ายที่ 5 (ชมพู/กุหลาบ)
- User Management / Project ใช้รายการฝ่ายจากฐานข้อมูล จึงเห็นฝ่ายที่ 5 อัตโนมัติ
- รหัส สพฐ. 155 map ไป STUDENT_AFFAIRS
- เมนู `กิจกรรมย่อย สพฐ. / ปริมาณงาน` ถูกย้ายจากแผนและงบประมาณไปใต้ `สารสนเทศและระบบ`
  และเปลี่ยนชื่อเป็น `ปริมาณงาน สพฐ. / แบบ 10`
- Master 151-155 ยังอยู่ใน Settings/Admin แยกจากเมนูรายงาน


### Current structure after v1.2.1
ระบบปัจจุบันใช้ **5 กลุ่มบริหาร**: วิชาการ, งบประมาณ, บุคคล, ทั่วไป, กิจการนักเรียน และงบส่วนกลางแยกต่างหาก
ข้อความ "4 ฝ่าย" ที่ปรากฏในส่วน changelog รุ่นเก่าเป็นประวัติของเวอร์ชันก่อนหน้า ไม่ใช่โครงสร้างปัจจุบัน


## Version 1.2.2 — Phase 3.6.2 Sidebar UX Improvement

ไม่ต้อง Import SQL เพิ่ม

- หัวข้อหมวดชัดขึ้น
- Accordion 4 หมวดหลัก
- ยุบ/ขยายได้
- หมวดของหน้าปัจจุบันเปิดอัตโนมัติ
- จำสถานะด้วย localStorage
- Active menu มีแถบสีน้ำเงินด้านซ้าย
- กลับเว็บไซต์หลักแยกไว้ด้านล่าง


## Version 1.3 — Phase 4.1 SAR Builder

ต้อง Import `anusarn_qa_v1.3_phase4.1_sar_builder.sql`

เพิ่ม:
- SAR 1 ฉบับต่อปีงบประมาณ / กรอบมาตรฐาน
- ดึงข้อมูลจริงรายตัวชี้วัด: โครงการ ผลรับรอง งบ ใช้จริง หลักฐาน KPI
- ผู้รับผิดชอบเพิ่มบทวิเคราะห์ จุดเด่น ประเด็นพัฒนา และหมายเหตุหลักฐาน
- บทสรุปรายมาตรฐาน
- Workflow SAR: DRAFT → REVIEW → FINAL
- plan/admin เขียนและส่งตรวจ
- director/admin รับรองหรือส่งกลับ
- admin เปิด FINAL กลับมาแก้ได้ พร้อม Audit Log
- ฉบับพิมพ์สำหรับ Print / Save as PDF


## Version 1.3.1 — SAR Builder Compatibility Fix

ฐานข้อมูลเดิมมี `qa_sar_reports` และ `qa_sar_indicator_summaries` อยู่ก่อนแล้ว จึงเปลี่ยน SAR Builder ใหม่ให้ใช้ namespace แยก:
- `qa_sar_builder_reports`
- `qa_sar_builder_standard_entries`
- `qa_sar_builder_indicator_entries`

หากเคย Import SQL v1.3 แล้วเกิด Foreign Key error ไม่ต้องลบ table SAR เดิม ให้ Import `anusarn_qa_v1.3.1_sar_builder_fix.sql` ได้ทันที


## Version 1.3.2 — Role-based Navigation

ไม่ต้อง Import SQL เพิ่ม

Sidebar จะกรองเมนูตาม Role:
- admin: ทุกเมนู
- director: แผน/งบ/โครงการ/อนุมัติขั้นสุดท้าย/ติดตาม/รายงาน/SAR
- plan: แผน/งบ/จัดสรร/โครงการ/อนุมัติขั้นงานแผน/รายงาน/SAR
- budget: งบ/จัดสรร/โครงการ/อนุมัติขั้นงบประมาณ/รายงาน สพฐ.
- finance: งบ/จัดสรรแบบดูข้อมูล/ติดตามการใช้จ่าย/รายงาน
- division_head: โครงการ/แก้ไขยกเลิก/ติดตาม/รายงาน
- project_owner: โครงการ/เสนอใหม่/แก้ไขยกเลิก/ติดตาม/หลักฐาน/ตัวชี้วัด
- viewer: เมนูข้อมูลอ่านอย่างเดียวและรายงาน

ผู้ใช้ที่มีหลาย Role จะเห็นผลรวมของเมนูทุก Role
การซ่อนเมนูเป็น UX layer; Server-side authorization เดิมยังคงทำงานในหน้าแก้ไข/อนุมัติ


## Version 1.3.3 — Thai Output / Outcome Terminology

ไม่ต้อง Import SQL เพิ่ม

ปรับคำที่แสดงต่อผู้ใช้:
- Output → ผลผลิต
- Outcome → ผลลัพธ์
- Output ที่คาดว่าจะได้รับ → ผลผลิตที่คาดว่าจะได้รับ
- Outcome ที่คาดว่าจะเกิดขึ้น → ผลลัพธ์ที่คาดว่าจะเกิดขึ้น

ชื่อ column/field ในฐานข้อมูล เช่น `expected_output`, `expected_outcome`, `output_summary`, `outcome_summary`
ยังคงเดิมเพื่อไม่กระทบข้อมูลและโค้ดเดิม


## Version 1.4 — Phase 4.2 Project Activity Management

ต้อง Import `anusarn_qa_v1.4_phase4.2_project_activity_management.sql`

เพิ่ม:
- เมนู “กิจกรรมที่รับผิดชอบ”
- ใช้ `qa_project_activities` เดิมเป็นกิจกรรมภายในโครงการ
- มอบหมาย `responsible_user_id` รายกิจกรรม
- รายงานความก้าวหน้ารายกิจกรรมหลายครั้ง
- ผลผลิต / ผลลัพธ์ / ปัญหา / ขั้นตอนถัดไป
- Progress 100% ปิดกิจกรรมเป็น completed อัตโนมัติ
- งบกิจกรรมและยอดใช้จริงจาก budget item / expenditure เดิม
- อัปโหลดหลักฐานกิจกรรมเข้าสู่ `qa_evidences` เดิม และเชื่อมตัวชี้วัดได้
- ผู้รับผิดชอบกิจกรรมไม่สามารถแก้งบหรืออนุมัติโครงการ
- ผู้รับผิดชอบโครงการ / หัวหน้าฝ่าย / admin จัดการการมอบหมายกิจกรรม


## Version 1.4.1 — Permission Architecture & Deputy Director Readiness

ต้อง Import `anusarn_qa_v1.4.1_permission_architecture.sql`

- เพิ่ม Role `deputy_director`
- เพิ่ม `qa_permissions` และ `qa_role_permissions`
- เปลี่ยนสิทธิ์หลักจาก hard-code Role เป็น Permission/Capability
- เพิ่ม Admin → สิทธิ์และ Role เพื่อกำหนด Permission ราย Role
- Workflow อนุมัติโครงการแยก `project.approve.plan`, `project.approve.budget`, `project.approve.final`
- SAR แยก `sar.edit` และ `sar.finalize`
- รองรับ Scope การดูข้อมูล `all / division / own`
- `project_owner` เห็นเฉพาะโครงการที่ตนเป็นเจ้าของหรือได้รับมอบหมายกิจกรรม
- `division_head` เห็นข้อมูลภายในฝ่ายหลัก
- `deputy_director` ดูภาพรวมได้ แต่ไม่มีสิทธิ์อนุมัติขั้นสุดท้ายหรือรับรอง SAR โดยค่าเริ่มต้น
- รองผู้อำนวยการที่กำกับฝ่ายควรใช้ `deputy_director + division_head`


## Version 1.4.2 — User Management Redirect Fix

ไม่ต้อง Import SQL เพิ่ม

แก้พฤติกรรมหน้า Admin → จัดการผู้ใช้งาน:
- หลัง “เพิ่มผู้ใช้งาน” สำเร็จ → กลับหน้ารายการผู้ใช้งาน
- ไม่เปิดหน้า “แก้ไขผู้ใช้งาน” ของบัญชีที่เพิ่งสร้างโดยอัตโนมัติ
- หลัง “แก้ไขผู้ใช้งาน” สำเร็จ → ยังคงอยู่หน้าแก้ไขของผู้ใช้นั้นตามเดิม
- หากเพิ่มข้อมูลผิด/Validation ไม่ผ่าน → ยังคงกลับหน้าเพิ่มพร้อมข้อมูลเดิมเพื่อแก้ไข


## Version 1.4.3 — Dashboard Action Center & Role-aware View

ไม่ต้อง Import SQL เพิ่ม

เพิ่ม:
- “งานที่ต้องดำเนินการ” ตาม Permission: งานแผน, งานงบ, ผอ., คำขอแก้ไข/ยกเลิก, ร่าง/Revision, กิจกรรมที่ต้องรายงาน, รอสรุปผล, โครงการเลยกำหนด
- Dashboard ตาม Role/Scope: ผู้บริหาร/แผน/งบ/การเงินเห็นภาพรวม, division_head เห็นเฉพาะฝ่าย, project_owner เห็นเฉพาะ own/assigned
- ปุ่มลัดบน Dashboard แสดงตาม Permission
- ผู้ใช้ที่ไม่มีสิทธิ์ดูงบทั้งโรงเรียนจะไม่เห็นงบรวมทั้งโรงเรียน แต่เห็นเฉพาะงบของโครงการในขอบเขต
- ปรับคำงบประมาณให้ชัด: งบทั้งหมด, จัดสรรแล้ว, อนุมัติโครงการแล้ว, เบิกจ่ายจริง, คงเหลือหลังเบิกจ่าย
- แยก “ยังไม่จัดสรร” และ “จัดสรรแล้วแต่ยังไม่อนุมัติโครงการ”


## Version 1.4.4 — Simplified Dashboard

ไม่ต้อง Import SQL

- ตัด Role banner ที่ซ้ำ
- Quick Action เหลือเฉพาะงานหลัก
- งานที่ต้องดำเนินการแสดงเฉพาะ count > 0
- ถ้าไม่มีงานค้าง แสดงเพียงบรรทัดเดียว
- ลด KPI ด้านบนจาก 5 กล่องเหลือ 4 กล่อง
- ย้ายยอดจัดสรรไปเป็นรายละเอียดใต้การ์ดงบประมาณประจำปี


## Version 1.4.5 — Dashboard Budget Summary & Role Scope Fix

ไม่ต้อง Import SQL

แก้:
- สรุปงบใต้กราฟไม่ให้ข้อความติดกัน
- เปลี่ยนเป็นกล่อง 2x2 อ่านง่าย
- เปลี่ยนคำเป็น “วงเงินที่ยังไม่ผูกกับโครงการ”
- จำกัดงบประมาณรวมทั้งโรงเรียน/งบรายฝ่ายไว้สำหรับผู้บริหาร งานงบ งานการเงิน และผู้มีสิทธิ์บริหารงบ
- project_owner / division-limited user จะเห็นงบเฉพาะโครงการในขอบเขตของตนแทน


## Version 1.4.5.1 — Budget Visibility for General Users

ต้อง Import `anusarn_qa_v1.4.5.1_budget_visibility.sql`

- `project_owner` และ `division_head` ได้สิทธิ์ `budget.view` และ `budget.allocation.view`
- ไม่มีการเพิ่ม `budget.manage` หรือ `budget.allocation.manage`
- ครูจึงดูงบรวมโรงเรียนและการจัดสรร 5 ฝ่ายได้ แต่แก้ไขไม่ได้
- Dashboard แสดงภาพรวมงบเมื่อมีสิทธิ์ดูงบ
- หน้า Allocation ในโหมด Read-only แสดงชัดว่า “ดูข้อมูลอย่างเดียว”


## Version 1.4.6 — Phase 4.2.2 Activity Hierarchy & Assignment

ต้อง Import `anusarn_qa_v1.4.6_phase4.2.2_activity_hierarchy.sql`

เพิ่ม/ปรับ:
- เพิ่ม `parent_activity_id` ใน `qa_project_activities` เพื่อรองรับกิจกรรมหลัก → กิจกรรมย่อย
- รองรับกิจกรรมย่อย 1 ระดับ เช่น 1.1, 1.2, 2.1
- ผู้รับผิดชอบโครงการ/หัวหน้าฝ่าย/ผู้มี Permission จัดการกิจกรรม สามารถเพิ่มกิจกรรมหลักและกิจกรรมย่อยหลังโครงการอนุมัติ
- การสร้างกิจกรรมใหม่บังคับเลือกผู้รับผิดชอบ
- ผู้รับผิดชอบกิจกรรมหลักและกิจกรรมย่อยเป็นคนละคนได้
- เมนู “กิจกรรมที่รับผิดชอบ” แสดงเฉพาะกิจกรรมที่ `responsible_user_id` ตรงกับบัญชีที่ Login เท่านั้น แม้ผู้ใช้จะมีสิทธิ์ดูภาพรวมกิจกรรม
- ภาพรวมของกิจกรรมอื่นให้ดูจากหน้าโครงการหรือหน้าติดตามและประเมินผล
- เมื่อกิจกรรมหลักมีกิจกรรมย่อย ระบบไม่ให้กรอก % ของกิจกรรมหลักซ้ำ แต่คำนวณค่าเฉลี่ยจากกิจกรรมย่อยอัตโนมัติ
- ผู้รายงานกิจกรรมโดยปกติต้องเป็นผู้รับผิดชอบที่ถูกมอบหมายโดยตรง (`responsible_user_id`); ผู้มี `activity.report_all` รายงานแทนได้
- เพิ่มการลบกิจกรรมที่กรอกผิดได้เฉพาะกิจกรรมที่ยังไม่มีงบ/รายงาน/หลักฐาน/เบิกจ่าย/กิจกรรมลูก


## Version 1.4.6.1 — Project List Column Alignment Fix

ไม่ต้อง Import SQL

แก้หน้า `projects/index.php`:
- เพิ่มค่าคอลัมน์ “กิจกรรมย่อย สพฐ.” ให้ตรงกับหัวตาราง
- งบที่ขอ / งบอนุมัติ / ผู้รับผิดชอบ / สถานะ กลับมาอยู่คอลัมน์ที่ถูกต้อง
- รวมกิจกรรมย่อย สพฐ. หลายรายการด้วย `GROUP_CONCAT` ผ่าน subquery เพื่อไม่ให้โครงการซ้ำในตาราง

## Version 1.4.7 — Draft-first Project Submission & Thai Calendar

ไม่ต้อง Import SQL

### Draft-first Workflow
- หน้า “เสนอโครงการใหม่” สร้างได้เฉพาะสถานะ DRAFT
- ต้องกด “บันทึกร่างและไปตรวจสอบ” ก่อน ระบบจึงสร้างรหัสโครงการ
- หลังบันทึกร่าง ระบบเปิดหน้าแก้ไขของโครงการนั้นทันที
- หน้าแก้ไขสามารถกด “บันทึกร่าง” ซ้ำได้หลายครั้งโดยยังไม่เข้า Workflow
- “ส่งเสนอ” ทำได้จากหน้าแก้ไขเท่านั้น
- หากตรวจความครบถ้วนตอนส่งเสนอไม่ผ่าน หน้าแก้ไขรักษาค่าที่ POST ไว้ ไม่ย้อนกลับไปใช้ค่าฐานข้อมูลเดิม
- การเปลี่ยนแผน/ฝ่ายในหน้า Create ใช้ POST reload เพื่อรักษาข้อมูลอื่นที่กรอกไว้ ไม่โหลดด้วย GET แบบเดิม

### ปฏิทินภาษาไทย
- เพิ่มปฏิทินภาษาไทยให้ input[type=date] ทั่วระบบ
- แสดงชื่อเดือนภาษาไทยและปีพุทธศักราช เช่น “14 กันยายน 2569”
- ค่าที่ส่ง PHP/ฐานข้อมูลยังคงเป็น ISO `YYYY-MM-DD` จึงไม่กระทบ schema หรือ query เดิม
- รองรับปุ่ม วันนี้ / ล้างวันที่ / เดือนก่อนหน้า / เดือนถัดไป


## Version 1.4.7.1 — Thai Calendar Month/Year Navigation Fix

ไม่ต้อง Import SQL

ตรวจสอบปัญหาเดิมพบว่า header ของปฏิทิน v1.4.7 มีเพียงปุ่มเดือนก่อนหน้า/เดือนถัดไป และชื่อเดือน-ปีเป็นข้อความธรรมดา จึงไม่สามารถเลือกเดือนหรือปีโดยตรงได้

ปรับปรุง:
- เพิ่ม dropdown เลือกเดือนภาษาไทยทั้ง 12 เดือน
- เพิ่ม dropdown เลือกปี พ.ศ. โดยตรง
- ปีที่เก็บ/ส่งเข้า PHP และฐานข้อมูลยังเป็น ค.ศ. รูปแบบ YYYY-MM-DD เช่นเดิม
- ปุ่มเดือนก่อนหน้า/ถัดไปยังใช้งานได้
- ถ้ามี min/max จะปิดเดือนที่อยู่นอกช่วง และปิดปุ่มย้อน/ถัดเมื่อไปต่อไม่ได้
- ถ้าวันที่ปัจจุบันอยู่นอก min/max จะเปิดปฏิทินที่เดือนแรก/สุดท้ายที่เลือกได้
- ค่าเริ่มต้นของรายการปีเมื่อไม่มี min/max คือ 20 ปีย้อนหลังถึง 20 ปีข้างหน้า และจะแสดงปีที่เลือกเดิมเสมอ

ตรวจสอบก่อนแพ็ก:
- PHP syntax ผ่าน 48 ไฟล์
- ไม่พบ syntax เกิน PHP 5.6
- JavaScript ผ่าน `node --check`
- ทดสอบ logic ของช่วง min/max, การ clamp เดือน และการแปลง ค.ศ. → พ.ศ. ผ่าน


## Version 1.4.7.2 — Approval Sequence & Thai Activity Log

ไม่ต้อง Import SQL

- ลำดับตรวจสอบ/อนุมัติแสดงชื่อขั้นตอนภาษาไทย
- เพิ่มคำอธิบายหน้าที่ของงานแผน งานงบประมาณ และผู้อำนวยการ
- เพิ่มคอลัมน์ผู้รับผิดชอบ/ผู้ดำเนินการในแต่ละขั้น
- ถ้ายังไม่พิจารณา จะแสดงผู้ใช้ที่มี Permission สำหรับขั้นนั้น
- ถ้าพิจารณาแล้ว จะแสดงชื่อและตำแหน่งผู้ที่ดำเนินการจริงจาก approver_user_id
- สถานะ Approval แสดงเป็นภาษาไทยแบบสื่อความหมายตามขั้นตอน
- เปลี่ยน “ความเห็น / ข้อเสนอแนะ” เป็น “บันทึกการดำเนินการและข้อเสนอแนะ”
- แปล owner/edit/resubmit/approval/revision/rejection/correction เป็นข้อความภาษาไทย
- วันที่ในบันทึกแสดงเป็นวัน เดือน ปี พ.ศ. และเวลา


## Version 1.4.7.3 — Project Detail Typography Consistency

ไม่ต้อง Import SQL

ปรับหน้า Projects/View ให้ใช้ลำดับชั้นตัวอักษรเดียวกันทั้งหน้า:
- หัวข้อ Section 18px
- หัวข้อย่อย 14px
- ข้อความหลัก 12.5–13px
- คำอธิบาย/Metadata 10.5–11.5px
- KPI แยกชื่อ ตัวชี้วัด วิธีวัด และค่าเป้าหมายให้อ่านง่าย
- ผลผลิต/ผลลัพธ์ใช้รูปแบบ Section เดียวกัน
- ตารางลำดับตรวจสอบเพิ่มขนาดคำอธิบาย ชื่อผู้รับผิดชอบ และสถานะ
- บันทึกการดำเนินการ/ข้อเสนอแนะปรับขนาดให้สอดคล้องกับส่วนอื่น


## Version 1.4.8 — Plan Master UX & Linkage Improvement

ไม่ต้อง Import SQL

ปรับหน้าแผนพัฒนาคุณภาพ:
- จัด Summary ของแผนใหม่ พร้อมสถานะภาษาไทยและจำนวน Master Data
- เพิ่มเมนูนำทางภายในหน้า
- ปรับ Typography ให้สม่ำเสมอ
- เพิ่มแผนภาพความเชื่อมโยง Mission → Strategy → Focus → Target จากข้อมูลจริง
- จุดเน้นที่ยังไม่เชื่อมจะแสดงคำเตือนแทนการเดาข้อมูล
- ค่านิยมเปลี่ยนจากตารางยาวเป็น Card
- ตารางเป้าหมายแสดง Mission / Strategy / Focus เป็น chip และค่าฐานมีหน่วย

ปรับหน้าจัดการผู้ใช้งาน:
- เนื่องจากระบบเก็บรหัสผ่านด้วย password_hash จึงไม่สามารถแสดงรหัสผ่านเดิมได้
- แสดงสถานะรหัสผ่านเดิมแบบ Masked พร้อมคำอธิบาย
- ช่องกำหนดรหัสผ่านใหม่มีปุ่ม แสดง/ซ่อน เพื่อให้ Admin ตรวจสอบก่อนบันทึก


## Version 1.4.8.1 — Target ↔ Focus Many-to-Many

ต้อง Import `anusarn_qa_v1.4.8.1_target_focus_many_to_many.sql`

เพิ่ม:
- ตาราง `qa_plan_target_focus_links`
- เป้าหมาย 1 ข้อเลือกจุดเน้นได้หลายรายการ
- Migration ย้าย `qa_plan_targets.focus_id` เดิมเข้าสู่ตารางกลางอัตโนมัติ
- หน้าแก้ไขเป้าหมายเปลี่ยนจาก Dropdown จุดเน้นเป็น Checkbox หลายรายการ
- ความเชื่อมโยง Mission → Strategy → Focus → Target แสดง F ได้หลายตัว
- ตารางเป้าหมายแสดง Focus chip ทุกตัว
- คง `qa_plan_targets.focus_id` เป็น Primary Focus เพื่อรองรับโค้ด/รายงานรุ่นเก่า
- การบันทึกเป้าหมายและ Focus links ทำใน Transaction เดียวกัน
