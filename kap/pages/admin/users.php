<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/admin/load.php';
use Kap\Teacher\Bootstrap;
use Kap\Admin\{Security,Directory,View};
try {
    Bootstrap::start();
    $db=Bootstrap::db();
    $actor=Security::actor($db,'users');
    $dir=new Directory($db);
    $users=$dir->users();$teachers=$dir->teachers();$areas=$dir->learningAreas();$roles=$dir->roles();
    $hasPrefix=$dir->hasTeacherPrefix();
    // RoleCode remains the authority for RBAC; RoleName is its Thai display label.
    $roleNamesByCode=[];
    foreach($roles as $role){
        $roleNamesByCode[(string)$role['RoleCode']]=(string)$role['RoleName'];
    }
    // Teacher_id is the 13-digit citizen identifier after the PK migration.
    $edit=null;
    if(isset($_GET['edit'])) {
        $id=Security::positive($_GET['edit']);
        foreach($users as $u)if((int)$u['SystemUser_id']===$id)$edit=$u;
        if($edit===null)throw new \Kap\Teacher\WebError(404,'ไม่พบบัญชีที่ต้องการแก้ไข');
    }
    View::header('จัดการผู้ใช้งาน','users',$actor);
    if(!$hasPrefix)echo '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> ยังไม่มีคอลัมน์ <code>TeacherPrefix</code> โปรดตรวจ Migration ในคู่มือก่อนเพิ่มหรือแก้ไขข้อมูลบุคลากร</div>';

    echo '<div class="kap-card mb-3"><h2 class="kap-admin-title">'.($edit?'แก้ไขบัญชีผู้ใช้งาน':'เพิ่มบัญชีผู้ใช้งาน').'</h2>';
    echo '<form class="kap-admin-form" method="post" action="'.View::e(View::path('/api/admin/actions.php')).'" data-confirm="'.($edit?'ยืนยันการแก้ไขบัญชีนี้?':'ยืนยันการเพิ่มผู้ใช้งานใหม่?').'">'.View::csrf()
        .'<input type="hidden" name="action" value="'.($edit?'update-user':'create-user').'">';
    if($edit)echo '<input type="hidden" name="user_id" value="'.(int)$edit['SystemUser_id'].'">';
    echo '<div class="row g-3"><div class="col-md-3"><label class="form-label">Username</label><input name="username" maxlength="80" required class="form-control" autocomplete="off" value="'.View::e($edit['Username']??'').'"></div>';
    if(!$edit)echo '<div class="col-md-2"><label class="form-label">รหัสผ่านเริ่มต้น</label><input name="password" type="password" minlength="8" required class="form-control" autocomplete="new-password"></div>';
    echo '<div class="col-md-4"><label class="form-label">ข้อมูลบุคลากรที่เชื่อมบัญชี</label><select class="form-select" name="teacher_mode" data-teacher-mode><option value="existing">เลือกบุคลากรที่มีอยู่แล้ว</option>';
    if(!$edit)echo '<option value="new">เพิ่มข้อมูลบุคลากรใหม่พร้อมบัญชี</option>';
    echo '<option value="none"'.($edit && empty($edit['Teacher_id'])?' selected':'').'>ไม่เชื่อมข้อมูลบุคลากร (ผู้บริหาร / ผู้ดูแลระบบ)</option></select></div>';
    echo '<div class="col-md-6" data-teacher-existing><label class="form-label">รายชื่อบุคลากร</label><select class="form-select" name="teacher_citizen_id"><option value="">เลือกบุคลากร</option>';
    foreach($teachers as $t)echo '<option value="'.View::e($t['CitizenId']??'').'"'.(empty($t['CitizenId'])?' disabled':'').((string)($edit['CitizenId']??'')===(string)($t['CitizenId']??'')?' selected':'').'>'.View::e(trim(($t['TeacherPrefix']??'').' '.$t['Teacher_name'].' '.$t['Teacher_Lastname'])).' · '.\Kap\Admin\CitizenIdentity::mask($t['CitizenId']??null).'</option>';
    echo '</select></div>';
    if(!$edit){
        echo '<div class="col-12 kap-new-teacher" data-teacher-new hidden><div class="row g-3">'
         .'<div class="col-md-3"><label class="form-label">เลขบัตรประชาชน 13 หลัก</label><input name="teacher_id" class="form-control" inputmode="numeric" pattern="[0-9]{13}" maxlength="13" autocomplete="off" required></div>'
         .'<div class="col-md-2"><label class="form-label">คำนำหน้า</label><select name="prefix" class="form-select"><option value="">ไม่ระบุ</option><option>นาย</option><option>นาง</option><option>นางสาว</option></select></div>'
         .'<div class="col-md-3"><label class="form-label">ชื่อจริง</label><input name="first_name" class="form-control" maxlength="255" required></div>'
         .'<div class="col-md-4"><label class="form-label">นามสกุล</label><input name="last_name" class="form-control" maxlength="255" required></div>'
         .'<div class="col-md-4"><label class="form-label">กลุ่มสาระการเรียนรู้ <span class="text-muted">(เฉพาะครูประจำวิชา)</span></label><select name="learning_area_id" class="form-select"><option value="">ไม่สังกัดกลุ่มสาระ</option>'.View::options($areas,'LearningArea_id','LearningArea_name').'</select></div></div></div>';
    }
    echo '<div class="col-12"><div class="text-muted small" data-role-area-hint>กลุ่มสาระจำเป็นเมื่อเลือกบทบาทครูประจำวิชาเท่านั้น ผู้บริหารที่ไม่สอนสามารถเว้นว่างหรือไม่เชื่อมข้อมูลบุคลากรได้</div></div>';
    echo '<div class="col-md-8"><label class="form-label">บทบาทผู้ใช้งาน</label><div class="kap-role-grid">';
    $existingRoles=isset($edit['RoleCodes'])?explode(', ',(string)$edit['RoleCodes']):[];
    foreach($roles as $role){$checked=in_array($role['RoleCode'],$existingRoles,true)?' checked':'';
        echo '<label class="form-check"><input class="form-check-input" type="checkbox" name="role_ids[]" data-role-code="'.View::e($role['RoleCode']).'" value="'.(int)$role['Role_id'].'"'.$checked.'><span class="form-check-label">'.View::e($role['RoleName']).'</span></label>';
    }
    echo '</div></div>';
    if($edit)echo '<div class="col-md-4"><label class="form-label">สถานะบัญชี</label><select name="is_active" class="form-select"><option value="1"'.((int)$edit['IsActive']===1?' selected':'').'>เปิดใช้งาน</option><option value="0"'.((int)$edit['IsActive']!==1?' selected':'').'>ปิดใช้งาน</option></select></div>';
    echo '<div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-floppy"></i> บันทึกข้อมูล</button>';
    if($edit) echo '<a class="btn btn-outline-secondary" href="'.View::e(View::path('/pages/admin/users.php')).'">ยกเลิกแก้ไข</a>';
    echo '</div></div></form></div>';
    if($edit) {
        echo '<div class="row g-3 mb-3">';
        if(!empty($edit['Teacher_id'])) {
            echo '<div class="col-lg-8"><div class="kap-card"><h2 class="kap-admin-title">แก้ไขข้อมูลผู้ใช้งาน</h2><form method="post" class="kap-admin-form" data-confirm="ยืนยันแก้ไขข้อมูลผู้ใช้งาน?" action="'.View::e(View::path('/api/admin/actions.php')).'">'.View::csrf().'<input type="hidden" name="action" value="update-teacher"><input type="hidden" name="teacher_citizen_id" value="'.View::e($edit['CitizenId']??'').'"><div class="row g-2">'
              .'<div class="col-md-2"><label class="form-label">คำนำหน้า</label><input name="prefix" maxlength="50" class="form-control" value="'.View::e($edit['TeacherPrefix']??'').'"></div>'
              .'<div class="col-md-3"><label class="form-label">ชื่อ</label><input name="first_name" maxlength="255" required class="form-control" value="'.View::e($edit['Teacher_name']??'').'"></div>'
              .'<div class="col-md-3"><label class="form-label">นามสกุล</label><input name="last_name" maxlength="255" required class="form-control" value="'.View::e($edit['Teacher_Lastname']??'').'"></div>'
              .'<div class="col-md-4"><label class="form-label">กลุ่มสาระ (ไม่จำเป็นสำหรับผู้บริหาร)</label><select name="learning_area_id" class="form-select"><option value="">ไม่สังกัดกลุ่มสาระ</option>'.View::options($areas,'LearningArea_id','LearningArea_name',$edit['LearningArea_id']??null).'</select></div>'
              .'</div><button class="btn btn-outline-primary mt-3">บันทึกข้อมูลผู้ใช้งาน</button></form></div></div>';
        }
        echo '<div class="col-lg-4"><div class="kap-card"><h2 class="kap-admin-title">รีเซ็ตรหัสผ่าน</h2><form method="post" class="kap-admin-form" data-confirm="ยืนยันรีเซ็ตรหัสผ่านบัญชีนี้?" action="'.View::e(View::path('/api/admin/actions.php')).'">'.View::csrf().'<input type="hidden" name="action" value="reset-password"><input type="hidden" name="user_id" value="'.(int)$edit['SystemUser_id'].'"><label class="form-label">รหัสผ่านใหม่ (8 ตัวขึ้นไป)</label><input class="form-control" type="password" minlength="8" name="password" autocomplete="new-password" required><button class="btn btn-outline-danger mt-3">รีเซ็ตรหัสผ่าน</button></form></div></div></div>';
    }
    echo '<div class="kap-card"><h2 class="kap-admin-title">บัญชีผู้ใช้ในระบบ</h2><div class="table-responsive"><table class="table kap-table align-middle"><thead><tr><th>รหัส</th><th>Username</th><th>ชื่อ-นามสกุลผู้ใช้งาน</th><th>กลุ่มสาระ</th><th>บทบาท</th><th>สถานะ</th><th>แก้ไข</th></tr></thead><tbody>';
    foreach($users as $u){
        // Directory returns stable RoleCodes for edit and authorization; use RoleName for display.
        $roleLabels=[];
        foreach(explode(',',(string)($u['RoleCodes']??'')) as $roleCode){
            $roleCode=trim($roleCode);
            if($roleCode==='')continue;
            $roleLabels[]=$roleNamesByCode[$roleCode]??$roleCode;
        }
        echo '<tr><td>'.(int)$u['SystemUser_id'].'</td><td>'.View::e($u['Username']).'</td><td>'.View::e(trim(($u['TeacherPrefix']??'').' '.($u['Teacher_name']??'').' '.($u['Teacher_Lastname']??''))).'</td><td>'.View::e($u['LearningArea_name']??'').'</td><td>'.View::e(implode(', ',$roleLabels)).'</td><td>'.((int)$u['IsActive']===1?'<span class="kap-tag ok">เปิด</span>':'<span class="kap-tag wait">ปิด</span>').'</td><td><a href="'.View::e(View::path('/pages/admin/users.php?edit='.(int)$u['SystemUser_id'])).'" class="btn btn-sm btn-soft"><i class="bi bi-pencil"></i> แก้ไข</a></td></tr>';}
    if(!$users)echo '<tr><td colspan="7">ยังไม่มีบัญชีผู้ใช้</td></tr>';
    echo '</tbody></table></div></div>';
    View::footer();
} catch(\Throwable $e){Bootstrap::fail($e);}
