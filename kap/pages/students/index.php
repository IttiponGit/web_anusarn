<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/config/bootstrap.php';
require_once dirname(__DIR__,2).'/app/admin/FieldError.php';
require_once dirname(__DIR__,2).'/app/admin/Security.php';
require_once dirname(__DIR__,2).'/app/teacher/StudentRegistry.php';
use Kap\Teacher\{Bootstrap,RealStudentDirectory,StudentRegistry,Ui,WebError};
use Kap\Admin\Security;

try {
    Bootstrap::start();
    if(Bootstrap::mode()!=='database')throw new WebError(409,'หน้านี้ใช้สำหรับทะเบียนนักเรียนจริง กรุณาเลือกโหมด database');
    $db=Bootstrap::db();$actor=Bootstrap::actor($db);
    $admin=in_array('SYSTEM_ADMIN',(array)($actor['Roles']??[]),true);
    if(!$admin&&!array_intersect($actor['Roles']??[],['HEAD_ASSESSMENT','DEPUTY_ACADEMICS','DIRECTOR']))
        throw new WebError(403,'บัญชีไม่มีสิทธิ์ดูทะเบียนนักเรียนทั้งโรงเรียน');
    $registry=new StudentRegistry($db,$actor);
    $dataset=RealStudentDirectory::read($db,'2569');
    $students=$dataset['students'];
    $rooms=$registry->roomOptions('2569');
    $e=static fn(mixed $v):string=>Bootstrap::escape($v);
    $url=rtrim((string)Bootstrap::config()['base_path'],'/').'/pages/students/index.php';
    $api=rtrim((string)Bootstrap::config()['base_path'],'/').'/api/students/actions.php';
    $edit=null;$move=null;
    if($admin && isset($_GET['edit'])){
        $edit=$registry->student(Security::positive($_GET['edit'],'edit','นักเรียน'));
        if(!$edit)throw new WebError(404,'ไม่พบข้อมูลนักเรียนที่ต้องการแก้ไข');
    }
    if($admin && isset($_GET['move'])){
        $move=$registry->student(Security::positive($_GET['move'],'move','นักเรียน'));
        if(!$move)throw new WebError(404,'ไม่พบข้อมูลนักเรียนที่ต้องการย้ายห้อง');
        if(!array_filter($students,static fn($s)=>(int)$s['Student_id']===(int)$move['Student_id']))
            throw new WebError(409,'ไม่พบทะเบียนห้องนักเรียนในปี 2569 กรุณาตรวจประวัติก่อนย้ายห้อง');
    }
    $groups=[];$grades=[];
    foreach($rooms as $room){
        $gid=(int)$room['GradeLevel_id'];
        $grades[$gid]=(string)$room['GradeName'];
    }
    foreach($students as $row){
        $group=(string)$row['GradeName'].' · ห้อง '.(string)$row['RoomName'];
        $groups[$group]=($groups[$group]??0)+1;
    }
    $html=Ui::headline('ทะเบียนนักเรียนปีการศึกษา 2569','ข้อมูลจริงจาก Student + StudentClassEnrollment + ClassRoom + GradeLevel + AcademicYear');
    $html.='<div class="alert alert-info"><i class="bi bi-shield-check"></i> '.($admin?'ผู้ดูแลระบบสามารถเพิ่ม แก้ไข ย้ายห้อง และปิดใช้งานข้อมูลนักเรียนได้ โดยเก็บประวัติการเปลี่ยนห้องและ AuditLog':'บัญชีของคุณได้รับสิทธิ์ตรวจสอบทะเบียนนักเรียนแบบอ่านอย่างเดียว').'</div>';
    if(!$students)$html.='<div class="alert alert-warning">ยังไม่พบทะเบียนนักเรียนปี 2569 กรุณาตรวจความสัมพันธ์ ClassRoom กับ StudentClassEnrollment ไม่ต้องนำเข้าข้อมูลซ้ำ</div>';
    if($admin){
        $p=$edit??['Student_id'=>null,'StudentCode'=>'','StudentPrefix'=>'','StudentFirstName'=>'','StudentLastName'=>''];
        $html.='<div class="kap-card mb-3" id="studentFormCard"><div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3"><h2 class="h5 mb-0">'.($edit?'แก้ไขข้อมูลนักเรียน #'.(int)$edit['Student_id']:'เพิ่มนักเรียนและทะเบียนห้องปี 2569').'</h2>';
        if($edit)$html.='<a class="btn btn-sm btn-outline-secondary" href="'.$e($url).'">ยกเลิกการแก้ไข</a>';
        $html.='</div><form class="kap-student-form row g-3" method="post" action="'.$e($api).'" data-confirm="'.($edit?'ยืนยันการแก้ไขข้อมูลนักเรียน?':'ยืนยันเพิ่มนักเรียนพร้อมทะเบียนห้องเรียนปี 2569?').'">';
        $html.='<input type="hidden" name="csrf" value="'.$e(Bootstrap::csrf()).'"><input type="hidden" name="action" value="'.($edit?'update-student':'create-student').'">';
        if($edit)$html.='<input type="hidden" name="student_id" value="'.(int)$edit['Student_id'].'">';
        $fields=[['student_code','รหัสนักเรียน','StudentCode',50,'col-md-3'],['student_prefix','คำนำหน้า','StudentPrefix',40,'col-md-2'],['first_name','ชื่อ','StudentFirstName',255,'col-md-3'],['last_name','นามสกุล','StudentLastName',255,'col-md-4']];
        foreach($fields as [$field,$label,$key,$max,$col]){
            $req=$field==='student_prefix'?'':' required';
            $html.='<div class="'.$col.'"><label class="form-label" for="studentField-'.$field.'">'.$label.($req?' *':'').'</label><input class="form-control" type="text" id="studentField-'.$field.'" name="'.$field.'" maxlength="'.$max.'" value="'.$e($p[$key]??'').'"'.$req.'></div>';
        }
        if(!$edit){
            $html.='<div class="col-md-4"><label class="form-label" for="manageGradeFilter">ระดับชั้น *</label><select class="form-select" id="manageGradeFilter" name="grade_level_id" required><option value="">เลือกระดับชั้นก่อน</option>';
            foreach($grades as $gradeId=>$name)$html.='<option value="'.$gradeId.'">'.$e($name).'</option>';
            $html.='</select></div><div class="col-md-4"><label class="form-label" for="manageRoomFilter">ห้องเรียน *</label><select class="form-select" id="manageRoomFilter" name="classroom_id" required disabled><option value="">เลือกระดับชั้นก่อน</option>';
            foreach($rooms as $r)if((int)$r['RoomActive']===1 && (int)$r['GradeActive']===1)$html.='<option value="'.(int)$r['ClassRoom_id'].'" data-grade="'.(int)$r['GradeLevel_id'].'">ห้อง '.$e($r['RoomName']).'</option>';
            $html.='</select></div><div class="col-md-4"><label class="form-label" for="startDate">วันที่เริ่มอยู่ห้อง *</label><input class="form-control" type="date" id="startDate" name="start_date" value="'.date('Y-m-d').'" required></div>';
        }
        $html.='<div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-floppy"></i> '.($edit?'บันทึกการแก้ไข':'เพิ่มนักเรียน').'</button><span class="small text-muted ms-2">รหัสนักเรียนต้องไม่ซ้ำ และห้ามแปลงเป็นตัวเลขเพื่อรักษาศูนย์นำหน้า</span></div></form></div>';
        if($move){
            $html.='<div class="kap-card mb-3" id="studentMoveCard"><h2 class="h5">ย้ายห้องนักเรียน #'.(int)$move['Student_id'].'</h2><p class="text-muted">'.$e(trim((string)$move['StudentPrefix'].' '.$move['StudentFirstName'].' '.$move['StudentLastName'])).' · เก็บประวัติห้องเดิม ไม่ลบรายการย้อนหลัง</p>';
            $html.='<form method="post" class="kap-student-form row g-3" action="'.$e($api).'" data-confirm="ยืนยันย้ายห้องนักเรียนและเก็บประวัติเดิม?">';
            $html.='<input type="hidden" name="csrf" value="'.$e(Bootstrap::csrf()).'"><input type="hidden" name="action" value="move-student"><input type="hidden" name="student_id" value="'.(int)$move['Student_id'].'">';
            $html.='<div class="col-md-4"><label class="form-label" for="moveGradeFilter">ระดับชั้น *</label><select class="form-select" id="moveGradeFilter" name="grade_level_id" required><option value="">เลือกระดับชั้นก่อน</option>';
            foreach($grades as $gid=>$name)$html.='<option value="'.$gid.'">'.$e($name).'</option>';
            $html.='</select></div><div class="col-md-4"><label class="form-label" for="moveRoomFilter">ห้องใหม่ *</label><select class="form-select" id="moveRoomFilter" name="classroom_id" required disabled><option value="">เลือกระดับชั้นก่อน</option>';
            foreach($rooms as $r)if((int)$r['RoomActive']===1 && (int)$r['GradeActive']===1)$html.='<option value="'.(int)$r['ClassRoom_id'].'" data-grade="'.(int)$r['GradeLevel_id'].'">ห้อง '.$e($r['RoomName']).'</option>';
            $html.='</select></div><div class="col-md-4"><label class="form-label" for="moveDate">วันที่เริ่มห้องใหม่ *</label><input class="form-control" type="date" id="moveDate" name="start_date" value="'.date('Y-m-d').'" required></div>';
            $html.='<div class="col-12"><button class="btn btn-outline-primary" type="submit">บันทึกการย้ายห้อง</button><a href="'.$e($url).'" class="btn btn-outline-secondary ms-2">ยกเลิก</a><p class="small text-muted mt-2">การเปลี่ยนระดับชั้นต้องทำผ่านกระบวนการเลื่อนชั้น ไม่ใช่การย้ายห้อง</p></div></form></div>';
        }
    }
    $statuses=[];foreach(($dataset['status_counts']??[]) as $s=>$n)$statuses[]=$e($s).' '.(int)$n.' คน';
    $html.='<div class="kap-card mb-3"><div class="d-flex justify-content-between flex-wrap gap-2 mb-2"><h2 class="h5">สรุปจำนวนนักเรียนตามระดับชั้นและห้อง</h2><span class="kap-tag ok">รวม '.count($students).' คน</span></div>';
    if($statuses)$html.='<div class="small text-muted mb-2">สถานะ: '.implode(' · ',$statuses).'</div>';
    $html.='<div class="row g-2">';foreach($groups as $group=>$count)$html.='<div class="col-sm-6 col-lg-3"><div class="progress-course"><strong>'.$e($group).'</strong><div>'.(int)$count.' คน</div></div></div>';
    $html.='</div></div><div class="kap-card"><div class="section-title"><div><h2>ตรวจสอบรายชื่อนักเรียน</h2><p>เลือกชั้นก่อนเลือกห้อง หรือค้นหาจากรหัส/ชื่อ</p></div></div>';
    $html.='<div class="row g-2 mb-3"><div class="col-md-4"><label class="form-label" for="rosterGradeFilter">ระดับชั้น</label><select class="form-select" id="rosterGradeFilter"><option value="">เลือกระดับชั้น</option>';
    foreach($grades as $gid=>$name)$html.='<option value="'.$gid.'">'.$e($name).'</option>';
    $html.='</select></div><div class="col-md-4"><label class="form-label" for="rosterRoomFilter">ห้องเรียน</label><select class="form-select" id="rosterRoomFilter" disabled><option value="">เลือกระดับชั้นก่อน</option>';
    foreach($rooms as $r)$html.='<option value="'.(int)$r['ClassRoom_id'].'" data-grade="'.(int)$r['GradeLevel_id'].'">ห้อง '.$e($r['RoomName']).'</option>';
    $html.='</select></div><div class="col-md-4"><label class="form-label" for="rosterTextFilter">ค้นหานักเรียน</label><input class="form-control" id="rosterTextFilter" placeholder="รหัส / ชื่อ-นามสกุล"></div></div>';
    $html.='<div class="table-responsive roster-scroll"><table class="table kap-table align-middle" id="schoolRosterTable"><thead><tr><th>ลำดับ</th><th>รหัสนักเรียน</th><th class="text-start">ชื่อ-นามสกุล</th><th>ระดับชั้น</th><th>ห้อง</th><th>สถานะ</th>'.($admin?'<th>จัดการ</th>':'').'</tr></thead><tbody>';
    foreach($students as $i=>$r){
        $group=(string)$r['GradeName'].' · ห้อง '.(string)$r['RoomName'];
        $search=strtolower((string)$r['StudentCode'].' '.(string)$r['StudentName'].' '.$group);
        $flags=[];if((int)($r['StudentActive']??0)!==1)$flags[]='ปิดใช้งาน';
        if((int)($r['RoomActive']??0)!==1)$flags[]='ห้องปิดใช้งาน';
        if((int)($r['GradeActive']??0)!==1)$flags[]='ระดับชั้นปิดใช้งาน';
        if(($r['EndDate']??null)!==null && (string)$r['EndDate']!=='')$flags[]='สิ้นสุด '.(string)$r['EndDate'];
        $status=$e($r['ClassStatus']??'ไม่ระบุ');if($flags)$status.='<div class="small text-muted">'.$e(implode(' · ',$flags)).'</div>';
        $html.='<tr data-grade="'.(int)$r['GradeLevel_id'].'" data-room="'.(int)$r['ClassRoom_id'].'" data-search="'.$e($search).'"><td>'.($i+1).'</td><td>'.$e($r['StudentCode']).'</td><td class="text-start">'.$e($r['StudentName']).'</td><td>'.$e($r['GradeName']).'</td><td>'.$e($r['RoomName']).'</td><td>'.$status.'</td>';
        if($admin){
            $sid=(int)$r['Student_id'];$on=(int)($r['StudentActive']??0)===1;
            $html.='<td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="'.$e($url.'?edit='.$sid).'">แก้ไข</a> <a class="btn btn-sm btn-outline-secondary" href="'.$e($url.'?move='.$sid).'">ย้ายห้อง</a> <form class="kap-student-form d-inline" method="post" action="'.$e($api).'" data-confirm="'.($on?'ยืนยันปิดใช้งานนักเรียน (ไม่ลบประวัติ)?':'ยืนยันเปิดใช้งานนักเรียน?').'"><input type="hidden" name="csrf" value="'.$e(Bootstrap::csrf()).'"><input type="hidden" name="action" value="set-student-active"><input type="hidden" name="student_id" value="'.$sid.'"><input type="hidden" name="is_active" value="'.($on?'0':'1').'"><button type="submit" class="btn btn-sm '.($on?'btn-outline-danger':'btn-outline-success').'">'.($on?'ปิดใช้งาน':'เปิดใช้งาน').'</button></form></td>';
        }
        $html.='</tr>';
    }
    if(!$students)$html.='<tr><td colspan="'.($admin?'7':'6').'">ไม่พบทะเบียนนักเรียนปี 2569</td></tr>';
    $html.='</tbody></table></div><p class="footnote mt-2">แสดง <strong id="schoolRosterVisible">'.count($students).'</strong> จาก '.count($students).' คน · การลงทะเบียนรายวิชาเป็นคนละขั้นตอนกับทะเบียนห้อง</p></div>';
    $html.='<script src="'.$e(rtrim((string)Bootstrap::config()['base_path'],'/').'/assets/js/kap-school-roster.js?v=registry-crud-1').'" defer></script>';
    Ui::page('ทะเบียนนักเรียน 2569',$actor,'students',$html);
}catch(\Throwable $e){Bootstrap::fail($e);}
