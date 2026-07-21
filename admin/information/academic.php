<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
$user = requireAnyRole(['academic']);
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="../../favicon.ico">
  <title>จัดการข้อมูลวิชาการ | Admin</title>
  <link rel="stylesheet" href="../admin.css">
  <link rel="stylesheet" href="academic.css">
</head>
<body class="academic-admin-page">
  <div class="dashboard-layout">
    <aside class="sidebar">
      <div class="sidebar-top">
        <div class="sidebar-brand">
          <div class="brand-logo-wrap"><img src="../../images/logo-school.png" alt="โลโก้โรงเรียน" class="brand-logo"></div>
          <div class="auth-badge light">Admin Panel</div>
          <h1 class="school-name">โรงเรียนโสตศึกษาอนุสารสุนทร</h1>
        </div>
        <p class="sidebar-user"><span class="sidebar-user-row"><strong>ชื่อ:</strong><span><?= htmlspecialchars((string) ($user['full_name'] ?: $user['username']), ENT_QUOTES, 'UTF-8') ?></span></span><span class="sidebar-user-row"><strong>บทบาท:</strong><span><?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?></span></span></p>
      </div>
      <nav class="sidebar-nav" aria-label="เมนูจัดการ">
        <a href="../../admin/dashboard.html" class="nav-link"><span>แดชบอร์ด</span></a>
        <a href="academic.html" class="nav-link" aria-current="page"><span>ข้อมูลวิชาการ</span></a>
        <a href="../../information/academic.html" class="nav-link"><span>ดูหน้าสาธารณะ</span></a>
        <button type="button" id="logoutBtn" class="logout-btn"><span>ออกจากระบบ</span></button>
      </nav>
    </aside>

    <main class="dashboard-main">
      <section class="hero-panel page-header academic-hero">
        <p class="eyebrow">Academic Information Management</p>
        <h2 class="page-title">จัดการข้อมูลวิชาการ</h2>
        <p>เพิ่ม แก้ไข จัดลำดับ และกำหนดสถานะข้อมูลที่แสดงในหน้าสาธารณะ</p>
        <a class="secondary-btn public-link" href="../../information/academic.html">กลับไปหน้าข้อมูลวิชาการ</a>
      </section>

      <p id="message" class="form-message" aria-live="polite"></p>

      <section class="academic-card filter-card" aria-label="ตัวกรอง">
        <div class="filter-grid">
          <label><span>ปีการศึกษา</span><select id="filterYear"><option value="">ทุกปี</option></select></label>
          <label><span>หมวดหมู่</span><select id="filterCategory"><option value="">ทุกหมวด</option></select></label>
          <label><span>สถานะเผยแพร่</span><select id="filterStatus"><option value="">ทุกสถานะ</option><option value="published">เผยแพร่</option><option value="draft">ฉบับร่าง</option></select></label>
          <label><span>ค้นหา</span><input id="filterSearch" type="search" maxlength="100" placeholder="ค้นหาหัวข้อหรือรายละเอียด"></label>
        </div>
      </section>

      <section class="academic-card">
        <div class="academic-list-header"><h3>รายการข้อมูล</h3><button id="addBtn" type="button" class="primary-btn">+ เพิ่มข้อมูล</button></div>
        <div class="table-wrap">
          <table class="admin-table academic-table">
            <thead><tr><th>ลำดับ</th><th>หัวข้อหรือชื่อรายการ</th><th>หมวดหมู่</th><th>ปีการศึกษา</th><th>สถานะ</th><th>วันที่แก้ไข</th><th>จัดการ</th></tr></thead>
            <tbody id="tableBody"><tr><td colspan="7">กำลังโหลดข้อมูล...</td></tr></tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <div id="editorModal" class="academic-modal" hidden>
    <div class="academic-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="formTitle">
      <div class="modal-heading"><h3 id="formTitle">เพิ่มข้อมูลวิชาการ</h3><button type="button" id="closeModal" class="icon-close" aria-label="ปิด">×</button></div>
      <form id="academicForm" autocomplete="off">
        <input id="itemId" type="hidden">
        <div class="form-grid">
          <label class="span-2"><span>หัวข้อหรือชื่อรายการ *</span><input id="title" required maxlength="255"></label>
          <label><span>หมวดหมู่ *</span><select id="category" required></select></label>
          <label><span>ปีการศึกษา *</span><input id="academicYear" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required></label>
          <label><span>ระดับ/หน่วยเปรียบเทียบ</span><input id="level" maxlength="150"></label>
          <label><span>ลำดับแสดงผล</span><input id="displayOrder" type="number" min="0" max="99999" value="0" required></label>
          <label><span>สถานะ</span><select id="status"><option value="published">เผยแพร่</option><option value="draft">ฉบับร่าง</option></select></label>
          <label><span>ค่าตัวเลขหลัก</span><input id="value" type="number" min="0" max="1000000" step="0.01"></label>
          <label><span>ค่าตัวเลขที่สอง</span><input id="value2" type="number" min="0" max="1000000" step="0.01"></label>
          <label><span>รหัสรายการ/วิชา</span><input id="key" maxlength="50" placeholder="เช่น thai, math"></label>
          <label><span>รหัสระดับ</span><input id="levelKey" maxlength="50" placeholder="เช่น p6, m3"></label>
          <label><span>ชื่อระดับแบบย่อ</span><input id="shortLabel" maxlength="50" placeholder="เช่น ป.6"></label>
          <label><span>ชนิดข้อมูล NT</span><select id="scope"><option value="">-</option><option value="trend">แนวโน้ม</option><option value="comparison">เปรียบเทียบ</option></select></label>
          <label><span>รหัสหน่วยเปรียบเทียบ NT</span><input id="entityKey" maxlength="50" placeholder="school, obec, country"></label>
          <label class="span-2"><span>รายละเอียด</span><textarea id="description" rows="4" maxlength="5000"></textarea></label>
          <label class="span-2"><span>รายการย่อย (หนึ่งรายการต่อบรรทัด)</span><textarea id="items" rows="5"></textarea></label>
        </div>
        <p class="field-help">ผลสัมฤทธิ์/O-NET/NT ใช้ “ค่าตัวเลขหลัก”; การสำเร็จการศึกษาใช้ค่าหลักเป็นจำนวนผู้จบและค่าที่สองเป็นร้อยละ</p>
        <div class="modal-actions"><button type="submit" class="primary-btn">บันทึก</button><button type="button" id="cancelBtn" class="secondary-btn">ยกเลิก</button></div>
      </form>
    </div>
  </div>
  <script src="academic.js"></script>
</body>
</html>
