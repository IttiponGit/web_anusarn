<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/admin/load.php';
use Kap\Teacher\{Bootstrap,WebError};
use Kap\Admin\{Security,Directory,View};
try {
    Bootstrap::start();$db=Bootstrap::db();$actor=Security::actor($db,'assignments');
    $dir=new Directory($db);$years=$dir->activeYears();$teachers=$dir->teachers();
    $yearId=isset($_GET['academic_year_id'])?Security::positive($_GET['academic_year_id']):($years?(int)$years[0]['AcademicYear_id']:0);
    if($yearId && !in_array($yearId,array_map(static fn($y)=>(int)$y['AcademicYear_id'],$years),true))throw new WebError(422,'ปีการศึกษาไม่ถูกต้อง');
    $groups=$yearId?$dir->subjectClasses($yearId):[];$assigned=$yearId?$dir->assignments($yearId):[];
    View::header('กำหนดรายวิชาที่ครูรับผิดชอบ','assignments',$actor);
    echo '<div class="kap-card mb-3"><form method="get" class="row align-items-end g-3"><div class="col-md-4"><label class="form-label">ปีการศึกษา</label><select class="form-select" name="academic_year_id" id="assignmentYear">'.View::options($years,'AcademicYear_id','AcademicYear_name',$yearId).'</select></div><div class="col-auto"><button class="btn btn-outline-primary">แสดงกลุ่มเรียน</button></div></form></div>';
    echo '<div class="kap-card mb-3"><h2 class="kap-admin-title">มอบหมายครูผู้สอนเข้ากลุ่มเรียน</h2><form class="row g-3 kap-admin-form" method="post" data-confirm="ยืนยันมอบหมายครูเข้ากลุ่มเรียนนี้?" action="'.View::e(View::path('/api/admin/actions.php')).'">'.View::csrf().'<input type="hidden" name="action" value="assign-teacher">'
        .'<div class="col-lg-5"><label class="form-label">กลุ่มเรียน / รายวิชา</label><select class="form-select" name="subject_class_id" required>';
    foreach($groups as $g){echo '<option value="'.(int)$g['SubjectClass_id'].'">'.View::e($g['Subject_code'].' · '.$g['Subject_name'].' / '.$g['GradeName'].' / '.($g['ClassGroupName']?:'#'.$g['SubjectClass_id'])).'</option>';}
    echo '</select></div><div class="col-lg-3"><label class="form-label">ครูผู้รับผิดชอบ</label><select name="teacher_citizen_id" class="form-select" required>';
    foreach($teachers as $t){if((int)$t['IsActive']===1 && !empty($t['CitizenId'])) echo '<option value="'.View::e($t['CitizenId']??'').'">'.View::e(trim(($t['TeacherPrefix']??'').' '.$t['Teacher_name'].' '.$t['Teacher_Lastname'])).' · '.\Kap\Admin\CitizenIdentity::mask($t['CitizenId']??null).'</option>';}
    echo '</select></div><div class="col-lg-2"><label class="form-label">TeachingRole</label><input maxlength="80" name="teaching_role" class="form-control" value="TEACHER" required></div><div class="col-lg-2 d-grid align-items-end"><button class="btn btn-primary" type="submit"'.(!$groups?' disabled':'').'>มอบหมายครู</button></div></form>';
    if(!$groups) echo '<div class="alert alert-warning mt-3 mb-0">ปีนี้ยังไม่มี SubjectClass กรุณาสร้างกลุ่มเรียน/เปิดรายวิชาก่อน จึงจะมอบหมายครูได้</div>';
    echo '<p class="kap-admin-hint mt-2 mb-0">TeachingRole ยังเป็นรหัสที่ต้องกำหนดมาตรฐานร่วมกับโรงเรียน ปัจจุบันเก็บเป็นข้อความและไม่ได้เพิ่มสิทธิ์โดยอัตโนมัติ</p></div>';
    echo '<div class="kap-card"><h2 class="kap-admin-title">รายวิชาที่มอบหมายแล้ว</h2><div class="table-responsive"><table class="table kap-table align-middle"><thead><tr><th>รหัสวิชา</th><th>ชื่อวิชา</th><th>ชั้น/กลุ่ม</th><th>ครู</th><th>บทบาทผู้สอน</th><th>สถานะ</th><th>จัดการ</th></tr></thead><tbody>';
    foreach($assigned as $r){echo '<tr><td>'.View::e($r['Subject_code']).'</td><td>'.View::e($r['Subject_name']).'</td><td>'.View::e($r['GradeName'].' / '.($r['ClassGroupName']?:'#'.$r['SubjectClass_id'])).'</td><td>'.View::e($r['Teacher_name'].' '.$r['Teacher_Lastname']).'</td><td>'.View::e($r['TeachingRole']).'</td><td>'.((int)$r['IsActive']===1?'เปิด':'ปิด').'</td><td>';
        if((int)$r['IsActive']===1)echo '<form method="post" data-confirm="ยืนยันปิดการมอบหมายนี้?" data-danger="1" class="kap-admin-form" action="'.View::e(View::path('/api/admin/actions.php')).'">'.View::csrf().'<input type="hidden" name="action" value="disable-assignment"><input type="hidden" name="assignment_id" value="'.(int)$r['SubjectTeacher_id'].'"><button type="submit" class="btn btn-sm btn-outline-danger">ปิดการมอบหมาย</button></form>';
        echo '</td></tr>';
    }
    if(!$assigned)echo '<tr><td colspan="7">ยังไม่มีรายการมอบหมายครูในปีนี้</td></tr>';
    echo '</tbody></table></div></div>';
    View::footer();
} catch(\Throwable $e){Bootstrap::fail($e);}
