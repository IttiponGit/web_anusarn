<?php
$pageTitle = 'ตั้งค่าระบบ';
$pageHeading = 'ตั้งค่าระบบ';
$pageDescription = 'Master Data, ปีงบประมาณ, ฝ่าย, ผู้ใช้, สิทธิ์, แผน, จุดเน้น และตัวชี้วัด';
$activeMenu = 'settings';
$breadcrumbs = [['label' => 'ตั้งค่าระบบ']];
require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();
require QA_ROOT . '/includes/header.php';
?>
<div class="grid grid-3">
    <div class="card"><h2>ปีงบประมาณ / ปีการศึกษา</h2><p class="help-text">กำหนดปีที่เปิดใช้งานและสถานะปี</p><button class="btn" type="button">จัดการ (TODO)</button></div>
    <div class="card"><h2>4 ฝ่ายและผู้ใช้งาน</h2><p class="help-text">ฝ่าย ผู้ใช้ Role และสิทธิ์การเข้าถึง</p><button class="btn" type="button">จัดการ (TODO)</button></div>
    <div class="card"><h2>Master Data แผน</h2><p class="help-text">วิสัยทัศน์ พันธกิจ กลยุทธ์ จุดเน้น</p><button class="btn" type="button">จัดการ (TODO)</button></div>
    <div class="card"><h2>มาตรฐาน / ตัวชี้วัด</h2><p class="help-text">ฐานตัวชี้วัด สมศ. และมาตรฐานสถานศึกษา</p><a class="btn" href="<?= h(qa_url('quality/index.php')) ?>">ดูโครงร่าง</a></div>
    <div class="card"><h2>แหล่งเงิน / หมวดค่าใช้จ่าย</h2><p class="help-text">Master Data สำหรับงบประมาณและรายการค่าใช้จ่าย</p><button class="btn" type="button">จัดการ (TODO)</button></div>
    <div class="card"><h2>Roadmap การพัฒนา</h2><p class="help-text">ใช้เป็น Checklist ว่าทำส่วนใดแล้วและส่วนใดต้องทำต่อ</p><a class="btn btn-primary" href="<?= h(qa_url('settings/roadmap.php')) ?>">เปิด Roadmap</a></div>
    <?php if (qa_user_has_role('admin')): ?>
    <div class="card danger-card">
        <h2>ล้างข้อมูลโครงการทดสอบ</h2>
        <p class="help-text">ลบเฉพาะข้อมูลธุรกรรมจากโครงการทดสอบทั้งหมด โดยเก็บแผน งบจัดสรร ผู้ใช้ และตัวชี้วัดไว้</p>
        <a class="btn btn-danger" href="<?= h(qa_url('settings/test-data-cleanup.php')) ?>">เปิดเครื่องมือล้างข้อมูล</a>
    </div>
    <?php endif; ?>
</div>
<?php require QA_ROOT . '/includes/footer.php'; ?>
