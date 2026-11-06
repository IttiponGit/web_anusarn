<?php
$pageTitle = 'จัดการผู้ใช้งาน';
$pageHeading = 'จัดการผู้ใช้งานและสิทธิ์';
$pageDescription = 'เพิ่ม แก้ไข เปิด/ปิดบัญชี รีเซ็ตรหัสผ่าน กำหนดฝ่ายหลัก และ Role ของผู้ใช้งานระบบ QA';
$activeMenu = 'admin-users';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

qa_require_permission('users.manage');

$db = qa_db();
$currentUser = qa_current_user();
$currentUserId = $currentUser ? (int)$currentUser['user_id'] : 0;
$currentYear = qa_current_year_row();

$breadcrumbs = array(
    array('label'=>'ตั้งค่าระบบ','url'=>qa_url('settings/index.php')),
    array('label'=>'จัดการผู้ใช้งาน')
);

function qa_user_admin_flash($type, $text)
{
    $_SESSION['qa_user_admin_flash'] = array('type'=>$type,'text'=>$text);
}

function qa_user_admin_redirect($query)
{
    $url = qa_url('settings/users.php');
    if ($query !== '') $url .= '?' . $query;
    header('Location: ' . $url);
    exit;
}

function qa_user_admin_audit($action, $recordId, $oldData, $newData)
{
    $db = qa_db();
    $user = qa_current_user();
    $userId = $user ? (int)$user['user_id'] : 0;
    $oldJson = $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE);
    $newJson = $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE);
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'],0,500) : '';
    $recordText = (string)$recordId;

    $stmt = $db->prepare(
        "INSERT INTO qa_audit_logs
        (user_id,action_name,module_name,record_table,record_id,old_data,new_data,ip_address,user_agent)
        VALUES (NULLIF(?,0),?,'users','qa_users',?,?,?,?,?)"
    );
    if (!$stmt) return false;
    $stmt->bind_param(
        'issssss',
        $userId,$action,$recordText,$oldJson,$newJson,$ip,$ua
    );
    $ok=$stmt->execute();
    $stmt->close();
    return $ok;
}

function qa_user_admin_load_roles($userId)
{
    $db=qa_db();
    $roles=array();
    $stmt=$db->prepare(
        "SELECT r.role_id,r.role_code,r.role_name
         FROM qa_user_roles ur
         INNER JOIN qa_roles r ON r.role_id=ur.role_id
         WHERE ur.user_id=?
         ORDER BY r.role_id"
    );
    if(!$stmt)return $roles;
    $stmt->bind_param('i',$userId);
    $stmt->execute();
    $result=$stmt->get_result();
    if($result){
        while($row=$result->fetch_assoc())$roles[]=$row;
        $result->free();
    }
    $stmt->close();
    return $roles;
}

function qa_user_admin_role_codes($userId)
{
    $rows=qa_user_admin_load_roles($userId);
    $codes=array();
    foreach($rows as $row)$codes[]=$row['role_code'];
    return $codes;
}

function qa_user_admin_load_user($userId)
{
    $db=qa_db();
    $stmt=$db->prepare(
        "SELECT u.*,d.division_code,d.division_name
         FROM qa_users u
         LEFT JOIN qa_divisions d ON d.division_id=u.primary_division_id
         WHERE u.user_id=? LIMIT 1"
    );
    if(!$stmt)return null;
    $stmt->bind_param('i',$userId);
    $stmt->execute();
    $result=$stmt->get_result();
    $row=$result?$result->fetch_assoc():null;
    if($result)$result->free();
    $stmt->close();
    if($row)$row['role_codes']=qa_user_admin_role_codes($userId);
    return $row;
}

function qa_user_admin_active_admin_count()
{
    return (int)qa_db_scalar(
        "SELECT COUNT(DISTINCT u.user_id)
         FROM qa_users u
         INNER JOIN qa_user_roles ur ON ur.user_id=u.user_id
         INNER JOIN qa_roles r ON r.role_id=ur.role_id
         WHERE u.status='active' AND r.role_code='admin' AND r.is_active=1",
        0
    );
}

function qa_user_admin_username_valid($username)
{
    return preg_match('/^[A-Za-z0-9._-]{3,50}$/',$username)===1;
}

function qa_user_admin_safe_status($status)
{
    return in_array($status,array('active','inactive'),true)?$status:'active';
}

$roles=array();
$result=$db->query(
    "SELECT role_id,role_code,role_name,description
     FROM qa_roles
     WHERE is_active=1
     ORDER BY role_id"
);
if($result){
    while($row=$result->fetch_assoc())$roles[]=$row;
    $result->free();
}

$roleIdByCode=array();
foreach($roles as $role)$roleIdByCode[$role['role_code']]=(int)$role['role_id'];

$divisions=array();
$result=$db->query(
    "SELECT division_id,division_code,division_name
     FROM qa_divisions
     WHERE is_active=1
     ORDER BY sort_order,division_id"
);
if($result){
    while($row=$result->fetch_assoc())$divisions[]=$row;
    $result->free();
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $token=isset($_POST['csrf_token'])?$_POST['csrf_token']:'';
    if(!qa_verify_csrf($token)){
        http_response_code(400);
        die('CSRF token ไม่ถูกต้อง');
    }

    $action=trim(qa_request_scalar($_POST,'action'));

    if($action==='save_user'){
        $userId=isset($_POST['user_id'])?(int)$_POST['user_id']:0;
        $isNew=$userId<=0;

        $username=trim(qa_request_scalar($_POST,'username'));
        $prefix=trim(qa_request_scalar($_POST,'prefix'));
        $firstName=trim(qa_request_scalar($_POST,'first_name'));
        $lastName=trim(qa_request_scalar($_POST,'last_name'));
        $email=trim(qa_request_scalar($_POST,'email'));
        $phone=trim(qa_request_scalar($_POST,'phone'));
        $position=trim(qa_request_scalar($_POST,'position_name'));
        $divisionId=isset($_POST['primary_division_id'])?(int)$_POST['primary_division_id']:0;
        $status=qa_user_admin_safe_status(isset($_POST['status'])?$_POST['status']:'active');
        $password=isset($_POST['password'])?(string)$_POST['password']:'';
        $selectedRoleIds=isset($_POST['role_ids'])&&is_array($_POST['role_ids'])
            ? array_unique(array_map('intval',$_POST['role_ids']))
            : array();

        $validRoleIds=array();
        foreach($roles as $role){
            if(in_array((int)$role['role_id'],$selectedRoleIds,true)){
                $validRoleIds[]=(int)$role['role_id'];
            }
        }

        $errors=array();
        if(!qa_user_admin_username_valid($username)){
            $errors[]='ชื่อผู้ใช้ต้องมี 3-50 ตัว และใช้เฉพาะ a-z, A-Z, 0-9, จุด, ขีดกลาง หรือขีดล่าง';
        }
        if($firstName==='')$errors[]='กรุณาระบุชื่อ';
        if($lastName==='')$errors[]='กรุณาระบุนามสกุล';
        if(empty($validRoleIds))$errors[]='กรุณาเลือก Role อย่างน้อย 1 รายการ';
        if($isNew && strlen($password)<8)$errors[]='ผู้ใช้ใหม่ต้องกำหนดรหัสผ่านอย่างน้อย 8 ตัวอักษร';
        if(!$isNew && $password!=='' && strlen($password)<8)$errors[]='รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร';

        if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)){
            $errors[]='รูปแบบอีเมลไม่ถูกต้อง';
        }

        $existing=null;
        if(!$isNew){
            $existing=qa_user_admin_load_user($userId);
            if(!$existing)$errors[]='ไม่พบผู้ใช้ที่ต้องการแก้ไข';
        }

        /* Username duplicate check */
        if(empty($errors)){
            if($isNew){
                $stmt=$db->prepare("SELECT user_id FROM qa_users WHERE username=? LIMIT 1");
                $stmt->bind_param('s',$username);
            }else{
                $stmt=$db->prepare("SELECT user_id FROM qa_users WHERE username=? AND user_id<>? LIMIT 1");
                $stmt->bind_param('si',$username,$userId);
            }
            $stmt->execute();
            $stmt->store_result();
            if($stmt->num_rows>0)$errors[]='ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว';
            $stmt->close();
        }

        /* Division must exist if selected */
        if($divisionId>0){
            $validDivision=false;
            foreach($divisions as $division){
                if((int)$division['division_id']===$divisionId){
                    $validDivision=true;
                    break;
                }
            }
            if(!$validDivision)$errors[]='ฝ่ายหลักไม่ถูกต้อง';
        }

        /* Protect own account and last active admin */
        $selectedRoleCodes=array();
        foreach($roles as $role){
            if(in_array((int)$role['role_id'],$validRoleIds,true))$selectedRoleCodes[]=$role['role_code'];
        }

        if(!$isNew && $existing){
            $existingIsAdmin=in_array('admin',$existing['role_codes'],true);
            $willBeAdmin=in_array('admin',$selectedRoleCodes,true);

            if($userId===$currentUserId){
                if($status!=='active')$errors[]='ไม่สามารถปิดใช้งานบัญชี Admin ที่กำลัง Login อยู่';
                if(!$willBeAdmin)$errors[]='ไม่สามารถนำ Role admin ออกจากบัญชีที่กำลัง Login อยู่';
            }

            if($existingIsAdmin && $existing['status']==='active' &&
               ($status!=='active' || !$willBeAdmin) &&
               qa_user_admin_active_admin_count()<=1){
                $errors[]='ไม่สามารถปิดบัญชีหรือนำ Role admin ออกจาก Admin คนสุดท้ายของระบบ';
            }
        }

        if(!empty($errors)){
            $_SESSION['qa_user_admin_form']=array(
                'user_id'=>$userId,'username'=>$username,'prefix'=>$prefix,
                'first_name'=>$firstName,'last_name'=>$lastName,'email'=>$email,
                'phone'=>$phone,'position_name'=>$position,
                'primary_division_id'=>$divisionId,'status'=>$status,
                'role_ids'=>$validRoleIds
            );
            qa_user_admin_flash('danger',implode(' / ',$errors));
            qa_user_admin_redirect($isNew?'mode=new':'edit='.$userId);
        }

        $oldData=null;
        if(!$isNew){
            $oldData=array(
                'username'=>$existing['username'],
                'prefix'=>$existing['prefix'],
                'first_name'=>$existing['first_name'],
                'last_name'=>$existing['last_name'],
                'email'=>$existing['email'],
                'phone'=>$existing['phone'],
                'position_name'=>$existing['position_name'],
                'primary_division_id'=>$existing['primary_division_id'],
                'status'=>$existing['status'],
                'roles'=>$existing['role_codes']
            );
        }

        $divisionDb=$divisionId>0?$divisionId:null;

        $db->autocommit(false);
        try{
            if($isNew){
                $passwordHash=password_hash($password,PASSWORD_DEFAULT);
                if(!$passwordHash)throw new Exception('ไม่สามารถเข้ารหัสรหัสผ่านได้');

                $stmt=$db->prepare(
                    "INSERT INTO qa_users
                    (username,password_hash,prefix,first_name,last_name,email,phone,position_name,primary_division_id,status)
                    VALUES (?, ?, NULLIF(?,''), ?, ?, NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), ?, ?)"
                );
                $stmt->bind_param(
                    'ssssssssis',
                    $username,$passwordHash,$prefix,$firstName,$lastName,$email,$phone,$position,$divisionDb,$status
                );
                if(!$stmt->execute())throw new Exception('ไม่สามารถเพิ่มผู้ใช้ได้: '.$stmt->error);
                $userId=$stmt->insert_id;
                $stmt->close();
            }else{
                $stmt=$db->prepare(
                    "UPDATE qa_users
                     SET username=?,prefix=NULLIF(?,''),first_name=?,last_name=?,
                         email=NULLIF(?,''),phone=NULLIF(?,''),position_name=NULLIF(?,''),primary_division_id=?,status=?
                     WHERE user_id=?"
                );
                $stmt->bind_param(
                    'sssssssisi',
                    $username,$prefix,$firstName,$lastName,$email,$phone,$position,$divisionDb,$status,$userId
                );
                if(!$stmt->execute())throw new Exception('ไม่สามารถแก้ไขผู้ใช้ได้: '.$stmt->error);
                $stmt->close();

                if($password!==''){
                    $passwordHash=password_hash($password,PASSWORD_DEFAULT);
                    if(!$passwordHash)throw new Exception('ไม่สามารถเข้ารหัสรหัสผ่านใหม่ได้');
                    $stmt=$db->prepare("UPDATE qa_users SET password_hash=? WHERE user_id=?");
                    $stmt->bind_param('si',$passwordHash,$userId);
                    if(!$stmt->execute())throw new Exception('ไม่สามารถเปลี่ยนรหัสผ่านได้');
                    $stmt->close();
                }
            }

            if(!$isNew){
                $stmt=$db->prepare("DELETE FROM qa_user_roles WHERE user_id=?");
                $stmt->bind_param('i',$userId);
                if(!$stmt->execute())throw new Exception('ไม่สามารถล้าง Role เดิมได้');
                $stmt->close();
            }

            $stmt=$db->prepare("INSERT INTO qa_user_roles(user_id,role_id) VALUES (?,?)");
            if(!$stmt)throw new Exception('ไม่สามารถเตรียมคำสั่งบันทึก Role ได้');
            foreach($validRoleIds as $roleId){
                $stmt->bind_param('ii',$userId,$roleId);
                if(!$stmt->execute())throw new Exception('ไม่สามารถกำหนด Role ได้');
            }
            $stmt->close();

            $db->commit();
            $db->autocommit(true);

            $newData=array(
                'username'=>$username,
                'prefix'=>$prefix,
                'first_name'=>$firstName,
                'last_name'=>$lastName,
                'email'=>$email,
                'phone'=>$phone,
                'position_name'=>$position,
                'primary_division_id'=>$divisionId,
                'status'=>$status,
                'roles'=>$selectedRoleCodes,
                'password_changed'=>$password!=='' ? 1 : 0
            );

            qa_user_admin_audit(
                $isNew?'create_user':'update_user',
                $userId,$oldData,$newData
            );

            /* Keep session label current when admin edits own profile. */
            if($userId===$currentUserId){
                $_SESSION['qa_username']=$username;
                $displayName=trim($prefix.$firstName.' '.$lastName);
                $_SESSION['qa_display_name']=$displayName!==''?$displayName:$username;
            }

            unset($_SESSION['qa_user_admin_form']);
            qa_user_admin_flash(
                'success',
                $isNew?'เพิ่มผู้ใช้งานเรียบร้อยแล้ว':'บันทึกการแก้ไขผู้ใช้งานเรียบร้อยแล้ว'
            );
            /* UX: หลังเพิ่มผู้ใช้ใหม่ให้กลับหน้ารายการผู้ใช้
               ส่วนการแก้ไขผู้ใช้เดิมให้อยู่หน้าแก้ไขเดิม */
            qa_user_admin_redirect($isNew ? '' : 'edit='.$userId);

        }catch(Throwable $e){
            $db->rollback();
            $db->autocommit(true);
            qa_user_admin_flash('danger','ยกเลิกการบันทึกทั้งหมด: '.$e->getMessage());
            qa_user_admin_redirect($isNew?'mode=new':'edit='.$userId);
        }
    }

    if($action==='toggle_status'){
        $userId=isset($_POST['user_id'])?(int)$_POST['user_id']:0;
        $target=qa_user_admin_load_user($userId);
        if(!$target){
            qa_user_admin_flash('danger','ไม่พบผู้ใช้งาน');
            qa_user_admin_redirect('');
        }

        $newStatus=$target['status']==='active'?'inactive':'active';

        if($userId===$currentUserId && $newStatus==='inactive'){
            qa_user_admin_flash('danger','ไม่สามารถปิดใช้งานบัญชีที่กำลัง Login อยู่');
            qa_user_admin_redirect('');
        }

        if($target['status']==='active' && in_array('admin',$target['role_codes'],true) &&
           $newStatus==='inactive' && qa_user_admin_active_admin_count()<=1){
            qa_user_admin_flash('danger','ไม่สามารถปิดใช้งาน Admin คนสุดท้ายของระบบ');
            qa_user_admin_redirect('');
        }

        $stmt=$db->prepare("UPDATE qa_users SET status=? WHERE user_id=?");
        $stmt->bind_param('si',$newStatus,$userId);
        if($stmt->execute()){
            $stmt->close();
            qa_user_admin_audit(
                'toggle_user_status',$userId,
                array('status'=>$target['status']),
                array('status'=>$newStatus)
            );
            qa_user_admin_flash(
                'success',
                $newStatus==='active'?'เปิดใช้งานบัญชีเรียบร้อยแล้ว':'ปิดใช้งานบัญชีเรียบร้อยแล้ว'
            );
        }else{
            $err=$stmt->error;
            $stmt->close();
            qa_user_admin_flash('danger','ไม่สามารถเปลี่ยนสถานะได้: '.$err);
        }
        qa_user_admin_redirect('');
    }
}

/* Flash */
$message='';
$messageType='success';
if(isset($_SESSION['qa_user_admin_flash'])&&is_array($_SESSION['qa_user_admin_flash'])){
    $messageType=isset($_SESSION['qa_user_admin_flash']['type'])?$_SESSION['qa_user_admin_flash']['type']:'success';
    $message=isset($_SESSION['qa_user_admin_flash']['text'])?$_SESSION['qa_user_admin_flash']['text']:'';
    unset($_SESSION['qa_user_admin_flash']);
}

/* Filters */
$filterStatus=trim(qa_request_scalar($_GET,'status'));
$filterDivision=isset($_GET['division'])?(int)$_GET['division']:0;
$search=trim(qa_request_scalar($_GET,'q'));

$where=array('1=1');
if(in_array($filterStatus,array('active','inactive'),true)){
    $where[]="u.status='".$db->real_escape_string($filterStatus)."'";
}else{
    $filterStatus='';
}
if($filterDivision>0)$where[]='u.primary_division_id='.$filterDivision;
if($search!==''){
    $esc=$db->real_escape_string($search);
    $where[]="(
        u.username LIKE '%".$esc."%' OR
        u.first_name LIKE '%".$esc."%' OR
        u.last_name LIKE '%".$esc."%' OR
        u.position_name LIKE '%".$esc."%' OR
        u.email LIKE '%".$esc."%'
    )";
}

$users=array();
$sql="SELECT
        u.user_id,u.username,u.prefix,u.first_name,u.last_name,u.email,u.phone,
        u.position_name,u.status,u.last_login_at,u.primary_division_id,
        d.division_code,d.division_name,
        GROUP_CONCAT(r.role_name ORDER BY r.role_id SEPARATOR ' • ') AS role_names,
        GROUP_CONCAT(r.role_code ORDER BY r.role_id SEPARATOR ',') AS role_codes
      FROM qa_users u
      LEFT JOIN qa_divisions d ON d.division_id=u.primary_division_id
      LEFT JOIN qa_user_roles ur ON ur.user_id=u.user_id
      LEFT JOIN qa_roles r ON r.role_id=ur.role_id
      WHERE ".implode(' AND ',$where)."
      GROUP BY u.user_id
      ORDER BY
        CASE u.status WHEN 'active' THEN 1 ELSE 2 END,
        u.first_name,u.last_name,u.user_id";
$result=$db->query($sql);
if($result){
    while($row=$result->fetch_assoc())$users[]=$row;
    $result->free();
}

$totalUsers=(int)qa_db_scalar("SELECT COUNT(*) FROM qa_users",0);
$activeUsers=(int)qa_db_scalar("SELECT COUNT(*) FROM qa_users WHERE status='active'",0);
$inactiveUsers=(int)qa_db_scalar("SELECT COUNT(*) FROM qa_users WHERE status<>'active'",0);
$adminUsers=qa_user_admin_active_admin_count();

/* Edit/new form */
$editId=isset($_GET['edit'])?(int)$_GET['edit']:0;
$modeNew=isset($_GET['mode'])&&$_GET['mode']==='new';
$formUser=null;
$formRoleIds=array();

if(isset($_SESSION['qa_user_admin_form'])&&is_array($_SESSION['qa_user_admin_form'])){
    $formUser=$_SESSION['qa_user_admin_form'];
    $formRoleIds=isset($formUser['role_ids'])?$formUser['role_ids']:array();
    unset($_SESSION['qa_user_admin_form']);
}elseif($editId>0){
    $formUser=qa_user_admin_load_user($editId);
    if($formUser){
        foreach($roles as $role){
            if(in_array($role['role_code'],$formUser['role_codes'],true)){
                $formRoleIds[]=(int)$role['role_id'];
            }
        }
    }
}elseif($modeNew){
    $formUser=array(
        'user_id'=>0,'username'=>'','prefix'=>'','first_name'=>'','last_name'=>'',
        'email'=>'','phone'=>'','position_name'=>'','primary_division_id'=>0,
        'status'=>'active'
    );
    if(isset($roleIdByCode['project_owner']))$formRoleIds[]=$roleIdByCode['project_owner'];
}

require QA_ROOT . '/includes/header.php';
?>

<?php if($message!==''): ?>
<div class="alert <?= $messageType==='danger'?'alert-danger':'alert-success' ?>" style="margin-bottom:16px">
    <?= h($message) ?>
</div>
<?php endif; ?>

<div class="card role-visibility-guide" style="margin-bottom:16px">
    <div class="section-heading">
        <div>
            <h2>การมองเห็นเมนูตาม Role</h2>
            <p>ระบบใช้ Permission ภายใต้ Role ผู้ใช้ที่มีหลาย Role จะได้รับผลรวมของสิทธิ์ทั้งหมด และ Admin ปรับ Permission ได้จากเมนู “สิทธิ์และ Role”</p>
        </div>
    </div>
    <div class="role-visibility-grid">
        <div><strong>project_owner</strong><span>โครงการ, เสนอใหม่, แก้ไข/ยกเลิก, กิจกรรมที่รับผิดชอบ, ติดตาม, หลักฐาน, ตัวชี้วัด</span></div>
        <div><strong>division_head</strong><span>โครงการ, เสนอใหม่, แก้ไข/ยกเลิก, ติดตาม, รายงาน</span></div>
        <div><strong>plan</strong><span>แผน, งบ, จัดสรร, โครงการ, อนุมัติขั้นงานแผน, SAR/รายงาน</span></div>
        <div><strong>budget</strong><span>งบ, จัดสรร, โครงการ, อนุมัติขั้นงบประมาณ, รายงาน สพฐ.</span></div>
        <div><strong>finance</strong><span>งบ/จัดสรรแบบดูข้อมูล, ติดตามการใช้จ่าย, รายงาน</span></div>
        <div><strong>director</strong><span>ภาพรวม แผน/งบ โครงการ อนุมัติขั้นสุดท้าย รายงาน และรับรอง SAR</span></div><div><strong>deputy_director</strong><span>ดูภาพรวม กำกับติดตาม แผน/งบ/โครงการ/รายงาน แต่ไม่อนุมัติขั้นสุดท้ายโดยอัตโนมัติ</span></div>
        <div><strong>viewer</strong><span>ข้อมูลอ่านอย่างเดียว เช่น แผน งบ โครงการ คุณภาพ และรายงาน</span></div>
        <div><strong>admin</strong><span>เห็นและเข้าถึงทุกเมนู รวมการตั้งค่าระบบและจัดการผู้ใช้</span></div>
    </div>
</div>

<div class="action-bar">
    <a class="btn btn-primary" href="<?= h(qa_url('settings/users.php?mode=new')) ?>">＋ เพิ่มผู้ใช้งาน</a>
    <a class="btn" href="<?= h(qa_url('settings/index.php')) ?>">ตั้งค่าระบบ</a>
</div>

<div class="grid grid-4 admin-user-metrics">
    <div class="card"><div class="metric-label">ผู้ใช้ทั้งหมด</div><div class="metric-value"><?= h($totalUsers) ?></div><div class="metric-note">ทุกสถานะ</div></div>
    <div class="card"><div class="metric-label">ใช้งานอยู่</div><div class="metric-value"><?= h($activeUsers) ?></div><div class="metric-note">เข้าสู่ระบบได้</div></div>
    <div class="card"><div class="metric-label">ปิดใช้งาน</div><div class="metric-value"><?= h($inactiveUsers) ?></div><div class="metric-note">ไม่สามารถ Login</div></div>
    <div class="card"><div class="metric-label">Admin ที่ใช้งาน</div><div class="metric-value"><?= h($adminUsers) ?></div><div class="metric-note">ระบบป้องกัน Admin คนสุดท้าย</div></div>
</div>

<?php if($formUser): ?>
<div class="card admin-user-form-card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2><?= (int)$formUser['user_id']>0?'แก้ไขผู้ใช้งาน':'เพิ่มผู้ใช้งาน' ?></h2>
            <p><?= (int)$formUser['user_id']>0?'เปลี่ยนข้อมูล ฝ่าย Role สถานะ หรือกำหนดรหัสผ่านใหม่':'สร้างบัญชีใหม่และกำหนดสิทธิ์เริ่มต้น' ?></p>
        </div>
        <?php if((int)$formUser['user_id']>0): ?>
            <span class="badge badge-blue">User ID <?= h($formUser['user_id']) ?></span>
        <?php endif; ?>
    </div>

    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="save_user">
        <input type="hidden" name="user_id" value="<?= h($formUser['user_id']) ?>">

        <div class="form-grid">
            <div class="form-group">
                <label>ชื่อผู้ใช้ *</label>
                <input name="username" required maxlength="50" value="<?= h($formUser['username']) ?>" autocomplete="off">
                <div class="help-text">ใช้ a-z, A-Z, 0-9, จุด, - หรือ _ จำนวน 3-50 ตัว</div>
            </div>

            <div class="form-group">
                <?php if((int)$formUser['user_id']>0): ?>
                    <label>รหัสผ่านปัจจุบัน</label>
                    <div class="password-current-state">
                        <span class="password-mask">••••••••</span>
                        <small>จัดเก็บแบบเข้ารหัส ไม่สามารถอ่านหรือแสดงรหัสผ่านเดิมกลับมาได้</small>
                    </div>
                    <label style="margin-top:10px">กำหนดรหัสผ่านใหม่</label>
                <?php else: ?>
                    <label>รหัสผ่าน *</label>
                <?php endif; ?>

                <div class="password-input-wrap">
                    <input id="qa-user-password"
                           type="password"
                           name="password"
                           <?= (int)$formUser['user_id']>0?'':'required' ?>
                           minlength="8"
                           autocomplete="new-password"
                           placeholder="<?= (int)$formUser['user_id']>0?'เว้นว่างหากไม่ต้องการเปลี่ยน':'อย่างน้อย 8 ตัวอักษร' ?>">
                    <button class="password-toggle-btn" type="button" data-password-target="qa-user-password" aria-label="แสดงหรือซ่อนรหัสผ่าน">แสดง</button>
                </div>
                <div class="help-text">
                    <?= (int)$formUser['user_id']>0
                        ? 'หากต้องการเปลี่ยนรหัสผ่าน ให้พิมพ์รหัสใหม่ แล้วกด “แสดง” เพื่อตรวจสอบก่อนบันทึก'
                        : 'อย่างน้อย 8 ตัวอักษร และสามารถกด “แสดง” เพื่อตรวจสอบก่อนบันทึก' ?>
                </div>
            </div>

            <div class="form-group">
                <label>คำนำหน้า</label>
                <input name="prefix" value="<?= h($formUser['prefix']) ?>" placeholder="นาย / นาง / นางสาว">
            </div>
            <div class="form-group">
                <label>ชื่อ *</label>
                <input name="first_name" required value="<?= h($formUser['first_name']) ?>">
            </div>
            <div class="form-group">
                <label>นามสกุล *</label>
                <input name="last_name" required value="<?= h($formUser['last_name']) ?>">
            </div>
            <div class="form-group">
                <label>ตำแหน่ง</label>
                <input name="position_name" value="<?= h($formUser['position_name']) ?>" placeholder="เช่น ครู / หัวหน้ากลุ่มบริหาร">
            </div>
            <div class="form-group">
                <label>อีเมล</label>
                <input type="email" name="email" value="<?= h($formUser['email']) ?>">
            </div>
            <div class="form-group">
                <label>โทรศัพท์</label>
                <input name="phone" value="<?= h($formUser['phone']) ?>">
            </div>

            <div class="form-group">
                <label>ฝ่ายหลัก</label>
                <select name="primary_division_id">
                    <option value="0">-- ไม่ระบุ --</option>
                    <?php foreach($divisions as $division): ?>
                    <option value="<?= h($division['division_id']) ?>"
                        <?= (int)$formUser['primary_division_id']===(int)$division['division_id']?'selected':'' ?>>
                        <?= h($division['division_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>สถานะบัญชี</label>
                <select name="status">
                    <option value="active" <?= $formUser['status']==='active'?'selected':'' ?>>ใช้งาน</option>
                    <option value="inactive" <?= $formUser['status']==='inactive'?'selected':'' ?>>ปิดใช้งาน</option>
                </select>
            </div>
        </div>

        <div class="admin-role-box">
            <h3>Role / สิทธิ์ของผู้ใช้งาน *</h3>
            <p class="help-text">หนึ่งบัญชีสามารถมีหลาย Role ได้ ระบบจะรวมสิทธิ์ของ Role ที่เลือกทั้งหมด</p>
            <div class="role-choice-grid">
                <?php foreach($roles as $role): ?>
                <label class="role-choice role-<?= h($role['role_code']) ?>">
                    <input type="checkbox" name="role_ids[]" value="<?= h($role['role_id']) ?>"
                        <?= in_array((int)$role['role_id'],$formRoleIds,true)?'checked':'' ?>>
                    <span>
                        <strong><?= h($role['role_name']) ?></strong>
                        <small><?= h($role['role_code']) ?><?= $role['description']?' — '.h($role['description']):'' ?></small>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="notice" style="margin-top:14px">
            ระบบไม่เปิดให้ลบบัญชีถาวร เพื่อรักษาประวัติผู้ดำเนินการและ Audit Log หากบุคลากรพ้นหน้าที่ให้ใช้ “ปิดใช้งาน” แทน
        </div>

        <div class="action-bar" style="margin-top:14px;margin-bottom:0">
            <button class="btn btn-primary" type="submit">บันทึกผู้ใช้งาน</button>
            <a class="btn" href="<?= h(qa_url('settings/users.php')) ?>">ยกเลิก</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>ค้นหา / กรองผู้ใช้งาน</h2><p>ค้นหาด้วย Username ชื่อ ตำแหน่ง หรืออีเมล</p></div>
    </div>
    <form method="get" class="form-grid">
        <div class="form-group">
            <label>ฝ่าย</label>
            <select name="division">
                <option value="0">ทุกฝ่าย</option>
                <?php foreach($divisions as $division): ?>
                <option value="<?= h($division['division_id']) ?>" <?= (int)$division['division_id']===$filterDivision?'selected':'' ?>>
                    <?= h($division['division_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>สถานะ</label>
            <select name="status">
                <option value="">ทุกสถานะ</option>
                <option value="active" <?= $filterStatus==='active'?'selected':'' ?>>ใช้งาน</option>
                <option value="inactive" <?= $filterStatus==='inactive'?'selected':'' ?>>ปิดใช้งาน</option>
            </select>
        </div>
        <div class="form-group">
            <label>ค้นหา</label>
            <input name="q" value="<?= h($search) ?>" placeholder="ชื่อผู้ใช้ / ชื่อ / ตำแหน่ง / อีเมล">
        </div>
        <div class="form-group" style="align-self:end">
            <button class="btn btn-primary" type="submit">ค้นหา</button>
            <a class="btn" href="<?= h(qa_url('settings/users.php')) ?>">ล้าง</a>
        </div>
    </form>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>บัญชีผู้ใช้งาน</h2><p>เฉพาะ Admin สามารถเข้าถึงหน้านี้</p></div>
        <span class="badge badge-blue"><?= h(count($users)) ?> รายการ</span>
    </div>

    <div class="table-wrap">
        <table class="admin-user-table">
            <thead>
            <tr>
                <th>ผู้ใช้งาน</th>
                <th>ฝ่าย / ตำแหน่ง</th>
                <th>Role</th>
                <th>สถานะ</th>
                <th>Login ล่าสุด</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if(empty($users)): ?>
                <tr><td colspan="6" class="empty-cell">ไม่พบผู้ใช้งานตามเงื่อนไข</td></tr>
            <?php else: ?>
                <?php foreach($users as $row): ?>
                <?php
                $roleCodes=$row['role_codes']!==null&&$row['role_codes']!==''?explode(',',$row['role_codes']):array();
                $divisionClass=$row['division_code']?qa_division_theme_class($row['division_code']):'division-neutral';
                ?>
                <tr class="<?= h($row['status']==='active'?'':'user-row-inactive') ?>">
                    <td>
                        <strong><?= h(trim($row['prefix'].$row['first_name'].' '.$row['last_name'])) ?></strong>
                        <div class="subtle">@<?= h($row['username']) ?></div>
                        <?php if($row['email']): ?><div class="subtle"><?= h($row['email']) ?></div><?php endif; ?>
                    </td>
                    <td>
                        <?php if($row['division_name']): ?>
                        <span class="user-division-chip <?= h($divisionClass) ?>"><i></i><?= h($row['division_name']) ?></span>
                        <?php else: ?>-<?php endif; ?>
                        <?php if($row['position_name']): ?><div class="subtle" style="margin-top:5px"><?= h($row['position_name']) ?></div><?php endif; ?>
                    </td>
                    <td>
                        <div class="user-role-badges">
                            <?php if(empty($roleCodes)): ?>
                                <span class="badge badge-red">ไม่มี Role</span>
                            <?php else: ?>
                                <?php foreach($roleCodes as $code): ?>
                                    <span class="user-role-badge role-<?= h($code) ?>"><?= h($code) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge <?= $row['status']==='active'?'badge-green':'badge-red' ?>">
                            <?= $row['status']==='active'?'ใช้งาน':'ปิดใช้งาน' ?>
                        </span>
                        <?php if((int)$row['user_id']===$currentUserId): ?><div class="subtle">บัญชีปัจจุบัน</div><?php endif; ?>
                    </td>
                    <td><?= $row['last_login_at']?h($row['last_login_at']):'-' ?></td>
                    <td>
                        <div class="row-actions">
                            <a class="btn btn-sm" href="<?= h(qa_url('settings/users.php?edit='.$row['user_id'])) ?>">แก้ไข</a>
                            <?php if((int)$row['user_id']!==$currentUserId): ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('ยืนยัน<?= $row['status']==='active'?'ปิดใช้งาน':'เปิดใช้งาน' ?>บัญชี @<?= h($row['username']) ?> ?');">
                                <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="user_id" value="<?= h($row['user_id']) ?>">
                                <button class="btn btn-sm <?= $row['status']==='active'?'':'btn-primary' ?>" type="submit">
                                    <?= $row['status']==='active'?'ปิดใช้งาน':'เปิดใช้งาน' ?>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<script>
(function(){
    var buttons=document.querySelectorAll('[data-password-target]');
    for(var i=0;i<buttons.length;i++){
        buttons[i].onclick=function(){
            var targetId=this.getAttribute('data-password-target');
            var input=document.getElementById(targetId);
            if(!input)return;
            if(input.type==='password'){
                input.type='text';
                this.innerHTML='ซ่อน';
            }else{
                input.type='password';
                this.innerHTML='แสดง';
            }
        };
    }
})();
</script>

<?php require QA_ROOT . '/includes/footer.php'; ?>
