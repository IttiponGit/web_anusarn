<?php
$pageTitle = 'ตั้งค่าระบบ';
$pageHeading = 'ตั้งค่าระบบ';
$pageDescription = 'Master Data, ปีงบประมาณ, ฝ่าย, ผู้ใช้, สิทธิ์, แผน, จุดเน้น และตัวชี้วัด';
$activeMenu = 'settings';
$breadcrumbs = [['label' => 'ตั้งค่าระบบ']];
require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();
qa_require_permission('settings.manage');
require QA_ROOT . '/includes/header.php';
?>
<div class="grid grid-3">
    <div class="card"><h2>ปีงบประมาณ / ปีการศึกษา</h2><p class="help-text">กำหนดปีที่เปิดใช้งานและสถานะปี</p><button class="btn" type="button">จัดการ (TODO)</button></div>
    <div class="card">
        <h2>5 ฝ่ายและผู้ใช้งาน</h2>
        <p class="help-text">ฝ่าย ผู้ใช้ Role และสิทธิ์การเข้าถึง</p>
        <?php if (qa_user_can('users.manage')): ?>
            <a class="btn btn-primary" href="<?= h(qa_url('settings/users.php')) ?>">จัดการผู้ใช้งาน</a>
        <?php else: ?>
            <span class="badge badge-blue">Admin only</span>
        <?php endif; ?>
    </div>
    <?php if (qa_user_can('permissions.manage')): ?>
    <div class="card">
        <h2>สิทธิ์และ Role</h2>
        <p class="help-text">Capability-based RBAC สำหรับกำหนดสิทธิ์ของ Role โดยไม่ต้องแก้โค้ด</p>
        <a class="btn" href="<?= h(qa_url('settings/permissions.php')) ?>">จัดการสิทธิ์</a>
    </div>
    <?php endif; ?>
    <div class="card"><h2>Master Data แผน</h2><p class="help-text">วิสัยทัศน์ พันธกิจ กลยุทธ์ จุดเน้น</p><button class="btn" type="button">จัดการ (TODO)</button></div>
    <div class="card"><h2>มาตรฐาน / ตัวชี้วัด</h2><p class="help-text">ฐานตัวชี้วัด สมศ. และมาตรฐานสถานศึกษา</p><a class="btn" href="<?= h(qa_url('quality/index.php')) ?>">ดูโครงร่าง</a></div>
    <div class="card"><h2>แหล่งเงิน / หมวดค่าใช้จ่าย</h2><p class="help-text">Master Data สำหรับงบประมาณและรายการค่าใช้จ่าย</p><button class="btn" type="button">จัดการ (TODO)</button></div>
    <div class="card"><h2>Roadmap การพัฒนา</h2><p class="help-text">ใช้เป็น Checklist ว่าทำส่วนใดแล้วและส่วนใดต้องทำต่อ</p><a class="btn btn-primary" href="<?= h(qa_url('settings/roadmap.php')) ?>">เปิด Roadmap</a></div>
    <?php if (qa_user_can('obec.master')): ?>
    <div class="card">
        <h2>Master กิจกรรมย่อย สพฐ.</h2>
        <p class="help-text">รหัส 151-155, Mapping ฝ่าย, หน่วยนับ และสัดส่วนอ้างอิง</p>
        <a class="btn" href="<?= h(qa_url('settings/obec-subactivities.php')) ?>">จัดการ Master</a>
    </div>
    <?php endif; ?>

    <?php if (qa_user_can('system.test_cleanup')): ?>
    <div class="card danger-card">
        <h2>ล้างข้อมูลโครงการทดสอบ</h2>
        <p class="help-text">ลบเฉพาะข้อมูลธุรกรรมจากโครงการทดสอบทั้งหมด โดยเก็บแผน งบจัดสรร ผู้ใช้ และตัวชี้วัดไว้</p>
        <a class="btn btn-danger" href="<?= h(qa_url('settings/test-data-cleanup.php')) ?>">เปิดเครื่องมือล้างข้อมูล</a>
    </div>
    <?php endif; ?>
</div>
<?php require QA_ROOT . '/includes/footer.php'; ?>
