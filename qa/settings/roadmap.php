<?php
$pageTitle = 'Roadmap การพัฒนา';
$pageHeading = 'Roadmap / Checklist การพัฒนาระบบ';
$pageDescription = 'ติดตามว่าส่วนใดใช้งานจริงแล้ว และส่วนใดเป็นงานถัดไปของระบบประกันคุณภาพ';
$activeMenu = 'roadmap';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();
qa_require_permission('roadmap.view');

$breadcrumbs = array(
    array('label'=>'ตั้งค่าระบบ','url'=>qa_url('settings/index.php')),
    array('label'=>'Roadmap การพัฒนา')
);

require QA_ROOT . '/includes/header.php';
?>

<div class="notice">สถานะปัจจุบัน: ระบบเดินข้อมูลจริงได้ตั้งแต่งบประมาณ → โครงการ → อนุมัติ → ดำเนินงาน → KPI/เบิกจ่าย/หลักฐาน → สรุปผล → Coverage Matrix สมศ.</div>

<div class="card">
    <h2>Phase 1–4.2.2 — ใช้งานจริงแล้ว</h2>
    <div class="todo-list">
        <div class="todo-item"><span>✓</span><div><strong>Authentication + Role/Permission</strong><small>Admin, Director, Plan, Budget, Finance, Division Head, Project Owner</small></div></div>
        <div class="todo-item"><span>✓</span><div><strong>แผนพัฒนาคุณภาพ</strong><small>วิสัยทัศน์ พันธกิจ ค่านิยม กลยุทธ์ จุดเน้น เป้าหมาย</small></div></div>
        <div class="todo-item"><span>✓</span><div><strong>งบประมาณและจัดสรร 5 ฝ่าย</strong><small>แหล่งเงิน ก้อนงบ วงเงินแต่ละฝ่าย และงบส่วนกลาง</small></div></div>
        <div class="todo-item"><span>✓</span><div><strong>เสนอโครงการ / งาน / กิจกรรม</strong><small>เชื่อมแผน จุดเน้น เป้าหมาย สมศ. KPI และงบประมาณ</small></div></div>
        <div class="todo-item"><span>✓</span><div><strong>Workflow ตรวจสอบและอนุมัติ</strong><small>งานแผน → งานงบประมาณ → ผู้อำนวยการ พร้อม Revision / Reject / Audit Log</small></div></div>
        <div class="todo-item"><span>✓</span><div><strong>Monitoring / KPI / ผลผลิต / ผลลัพธ์</strong><small>ความก้าวหน้า ผล KPI เบิกจ่ายจริง สรุปผล รับรองผล และปิดโครงการ</small></div></div>
        <div class="todo-item"><span>✓</span><div><strong>Evidence Repository</strong><small>ไฟล์/URL + Metadata + Mapping ไปยัง Project และ Indicator</small></div></div>
        <div class="todo-item"><span>✓</span><div><strong>Coverage Matrix สมศ.</strong><small>โครงการ งบ ใช้จริง ผลรับรอง และหลักฐานรายตัวชี้วัด</small></div></div>
        <div class="todo-item"><span>✓</span><div><strong>Project Correction & Cancellation</strong><small>ขอแก้ไข/ยกเลิก, พัก Workflow ระหว่างพิจารณา, Revision ใหม่, Amendment ย้อนหลัง และ Audit Trail</small></div></div>
        <div class="todo-item"><span>✓</span><div><strong>Admin User Management</strong><small>เพิ่ม/แก้ไขผู้ใช้ กำหนดฝ่าย Role เปิด/ปิดบัญชี และรีเซ็ตรหัสผ่าน พร้อม Audit Log</small></div></div>\n        <div class="todo-item"><span>✓</span><div><strong>กิจกรรมย่อย สพฐ. / ปริมาณงาน</strong><small>Master 151-155, ผูกโครงการ, เปรียบเทียบปีก่อน, ตรวจ ±20%, เหตุผล และแบบพิมพ์ 10</small></div></div>
        <div class="todo-item"><span>✓</span><div><strong>โครงสร้าง 5 ฝ่ายสำหรับโรงเรียนเฉพาะความพิการ</strong><small>เพิ่มกลุ่มบริหารกิจการนักเรียน, จัดสรรงบ 5 ฝ่าย, Dashboard 5 สี และ Mapping รหัส 155</small></div></div>\n        <div class="todo-item"><span>✓</span><div><strong>Sidebar UX Improvement</strong><small>หัวข้อหมวดชัดขึ้น, ยุบ/ขยายเมนูย่อย, เปิดหมวดปัจจุบันอัตโนมัติ และจำสถานะด้วย localStorage</small></div></div>
        <div class="todo-item"><span>✓</span><div><strong>SAR Builder</strong><small>สร้างร่าง SAR จากข้อมูลจริงรายมาตรฐาน/ตัวชี้วัด เพิ่มบทวิเคราะห์ ส่งตรวจรับรอง และพิมพ์/PDF</small></div></div>
        <div class="todo-item"><span>✓</span><div><strong>Role-based Navigation</strong><small>Sidebar แสดงเฉพาะเมนูที่เกี่ยวข้องกับ Role และรวมสิทธิ์เมื่อผู้ใช้มีหลาย Role</small></div></div>
        <div class="todo-item"><span>✓</span><div><strong>Project Activity Management</strong><small>มอบหมายผู้รับผิดชอบกิจกรรมภายใต้โครงการ รายงานความก้าวหน้า ผลผลิต/ผลลัพธ์ งบ และหลักฐานรายกิจกรรม</small></div></div>
        <div class="todo-item"><span>✓</span><div><strong>Permission Architecture</strong><small>Capability-based RBAC, Role รองผู้อำนวยการ, Permission Matrix และ Scope all/division/own</small></div></div>
        <div class="todo-item"><span>✓</span><div><strong>Activity Hierarchy & Assignment</strong><small>กิจกรรมหลัก/กิจกรรมย่อย ผู้รับผิดชอบรายกิจกรรม My Activities เฉพาะงานที่ได้รับมอบหมาย และรวมความก้าวหน้าจากกิจกรรมย่อย</small></div></div>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <h2>Phase 4 — งานถัดไป</h2>
    <div class="todo-list">
        
        <div class="todo-item"><span>1</span><div><strong>รายงานงบประมาณเชิงยุทธศาสตร์</strong><small>ตอบว่าเงินถูกใช้กับพันธกิจ กลยุทธ์ จุดเน้น และตัวชี้วัดใดเท่าไร</small></div></div>
        <div class="todo-item"><span>2</span><div><strong>Dashboard ผู้บริหาร</strong><small>ความเสี่ยง ช่องว่างตัวชี้วัด งบคงเหลือ โครงการล่าช้า และ KPI ไม่บรรลุ</small></div></div>
        <div class="todo-item"><span>3</span><div><strong>Export / Print</strong><small>PDF/Excel สำหรับรายงานกรรมการ ผู้บริหาร และเอกสารประกอบ SAR</small></div></div>
        <div class="todo-item"><span>4</span><div><strong>Hardening ก่อน Production</strong><small>สิทธิ์ไฟล์อัปโหลด, backup, log, restore test, validation และ security review</small></div></div>
    </div>
</div>

<?php require QA_ROOT . '/includes/footer.php'; ?>
