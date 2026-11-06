<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/admin/load.php';
use Kap\Teacher\Bootstrap;
use Kap\Admin\{Security,View};
try {
  Bootstrap::start();$db=Bootstrap::db();$actor=Security::actor($db,'users');
  // No inferred data, no blanket SELECT against tables that may be missing on a partially migrated host.
  $tables=['AcademicYear'=>'ปีการศึกษา','Curriculum'=>'หลักสูตรสถานศึกษา','CurriculumSection'=>'หมวดหลักสูตรสถานศึกษา',
     'Subject'=>'ทะเบียนรายวิชา/กิจกรรม','CurriculumSubject'=>'รายวิชาตามหลักสูตรสถานศึกษา',
     'SubjectOffering'=>'รายการเปิดสอน','SubjectClass'=>'กลุ่มเรียน','Student'=>'นักเรียน','Teacher'=>'บุคลากร'];
  $names=array_keys($tables);$marks=implode(',',array_fill(0,count($names),'?'));
  $s=$db->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' AND TABLE_NAME IN ($marks)");
  $s->execute($names);$available=array_fill_keys($s->fetchAll(PDO::FETCH_COLUMN),true);$counts=[];
  foreach($tables as $name=>$label){
      $counts[$name]=isset($available[$name])?(int)$db->query('SELECT COUNT(*) FROM `'.$name.'`')->fetchColumn():null;
  }
  $draftSections=null;
  if(isset($available['CurriculumSection'])){
      $c=$db->query('SELECT COUNT(*) FROM CurriculumSection WHERE IsActive=0');$draftSections=(int)$c->fetchColumn();
  }
  $base=View::path('');$v=static fn(mixed $x):string=>View::e($x);
  View::header('ภาพรวมการจัดการงานวิชาการ','admin',$actor);
?>
<div class="kap-card mb-3"><strong>Admin 3.0 · ค่าที่แสดงอ่านจาก MariaDB ปัจจุบัน</strong><div class="kap-admin-hint">ระบบไม่เพิ่มหลักสูตรสถานศึกษาหรือรายวิชาทดสอบ และไม่เปลี่ยนสถานะหมวดหลักสูตรสถานศึกษาร่างโดยอัตโนมัติ</div></div>
<div class="row g-3 mb-3">
  <?php foreach($counts as $table=>$count): ?>
    <div class="col-6 col-lg-4 col-xl-3"><div class="kap-card kap-admin-stat h-100"><span class="kap-admin-hint"><?= $v($tables[$table]) ?></span><strong><?= $count===null?'ไม่มีตาราง':number_format($count) ?></strong><small class="text-muted"><?= $v($table) ?></small></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-12 col-lg-7"><div class="kap-card h-100"><h2 class="kap-admin-title">เมนูพร้อมใช้งาน</h2>
    <div class="kap-admin-links">
      <a href="<?= $v($base.'/pages/admin/curricula.php') ?>"><i class="bi bi-collection"></i><span><strong>หลักสูตรสถานศึกษาและหมวดหลักสูตรสถานศึกษา</strong><small>จัดการหลักสูตรสถานศึกษา หมวดหลัก/ย่อย และตรวจการใช้หลักสูตรสถานศึกษาตามปีการศึกษา</small></span><i class="bi bi-chevron-right"></i></a>
      <a href="<?= $v($base.'/pages/admin/subjects.php') ?>"><i class="bi bi-journal-text"></i><span><strong>ทะเบียนรายวิชาและกิจกรรม</strong><small>เพิ่ม/แก้ไข บันทึกร่าง ค้นหา และเปิดใช้งานหลังตรวจสอบ</small></span><i class="bi bi-chevron-right"></i></a>
      <a href="<?= $v($base.'/pages/admin/users.php') ?>"><i class="bi bi-people"></i><span><strong>จัดการผู้ใช้งาน</strong><small>บัญชี บทบาท และข้อมูลบุคลากร</small></span><i class="bi bi-chevron-right"></i></a>
      <a href="<?= $v($base.'/pages/admin/assignments.php') ?>"><i class="bi bi-person-workspace"></i><span><strong>มอบหมายครูผู้สอน</strong><small>ต้องมีข้อมูล SubjectOffering และ SubjectClass ก่อน</small></span><i class="bi bi-chevron-right"></i></a>
      <a href="<?= $v($base.'/pages/students/index.php') ?>"><i class="bi bi-mortarboard"></i><span><strong>ทะเบียนนักเรียน</strong><small>อ่านข้อมูลจริงตามปีการศึกษาและห้องเรียน</small></span><i class="bi bi-chevron-right"></i></a>
    </div></div></div>
  <div class="col-12 col-lg-5"><div class="kap-card h-100"><h2 class="kap-admin-title">งานที่ต้องพัฒนาต่อ</h2>
    <ul class="kap-admin-roadmap"><li>ปีการศึกษาและภาคเรียน</li><li>โครงสร้างรายวิชาตามหลักสูตรสถานศึกษา</li><li>โครงสร้างรายวิชาตามหลักสูตรสถานศึกษา</li><li>เปิดรายวิชาตามปี/ภาคเรียนและกลุ่มเรียน</li><li>ประวัติเปลี่ยนครู/ห้องเรียน</li><li>เชื่อมทะเบียนนักเรียนและคะแนน K/A/P</li></ul>
    <div class="kap-admin-hint">รายการเหล่านี้ยังไม่ใช่หน้าสำหรับบันทึกจริง และจะไม่สร้างเมนูที่นำไปยังหน้า 404</div>
  </div></div>
</div>
<?php View::footer();
}catch(\Throwable $e){\Kap\Teacher\Bootstrap::fail($e);}
