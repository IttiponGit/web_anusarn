<?php
$pageTitle='สิทธิ์และ Role';
$pageHeading='สิทธิ์และ Role';
$pageDescription='กำหนด Permission ของแต่ละ Role เพื่อรองรับโครงสร้างบุคลากรและ Workflow ที่ขยายได้ในอนาคต';
$activeMenu='permissions';

require_once __DIR__.'/../includes/bootstrap.php';
qa_require_login();
qa_require_permission('permissions.manage');

$db=qa_db();
$breadcrumbs=array(
    array('label'=>'ตั้งค่าระบบ','url'=>qa_url('settings/index.php')),
    array('label'=>'สิทธิ์และ Role')
);

if(!qa_permissions_ready()){
    require QA_ROOT.'/includes/header.php';
    ?>
    <div class="alert alert-danger">
        กรุณา Import <strong>anusarn_qa_v1.4.1_permission_architecture.sql</strong> ก่อนใช้งาน
    </div>
    <?php
    require QA_ROOT.'/includes/footer.php';
    exit;
}

function perm_flash($type,$text)
{
    $_SESSION['qa_permission_flash']=array('type'=>$type,'text'=>$text);
}

function perm_redirect($roleId)
{
    header('Location: '.qa_url('settings/permissions.php?role='.(int)$roleId));
    exit;
}

$roles=array();
$result=$db->query(
    "SELECT role_id,role_code,role_name,description
     FROM qa_roles
     WHERE is_active=1
     ORDER BY role_id"
);
if($result){while($row=$result->fetch_assoc())$roles[]=$row;$result->free();}

$roleId=isset($_GET['role'])?(int)$_GET['role']:0;
if($roleId<=0 && !empty($roles))$roleId=(int)$roles[0]['role_id'];

$selectedRole=null;
foreach($roles as $role){
    if((int)$role['role_id']===$roleId){$selectedRole=$role;break;}
}
if(!$selectedRole && !empty($roles)){
    $selectedRole=$roles[0];
    $roleId=(int)$selectedRole['role_id'];
}

$permissions=array();
$result=$db->query(
    "SELECT permission_id,permission_code,permission_name,module_code,description,sort_order
     FROM qa_permissions
     WHERE is_active=1
     ORDER BY module_code,sort_order,permission_id"
);
if($result){while($row=$result->fetch_assoc())$permissions[]=$row;$result->free();}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $token=isset($_POST['csrf_token'])?$_POST['csrf_token']:'';
    if(!qa_verify_csrf($token)){http_response_code(400);die('CSRF token ไม่ถูกต้อง');}

    $saveRoleId=isset($_POST['role_id'])?(int)$_POST['role_id']:0;
    $role=null;
    foreach($roles as $r){
        if((int)$r['role_id']===$saveRoleId){$role=$r;break;}
    }
    if(!$role){perm_flash('danger','ไม่พบ Role');perm_redirect($roleId);}

    if($role['role_code']==='admin'){
        perm_flash('danger','Role admin เป็น Superuser และได้ทุก Permission อัตโนมัติ');
        perm_redirect($saveRoleId);
    }

    $selected=isset($_POST['permission_ids'])&&is_array($_POST['permission_ids'])
        ? array_unique(array_map('intval',$_POST['permission_ids']))
        : array();

    $validIds=array();
    foreach($permissions as $permission){
        if(in_array((int)$permission['permission_id'],$selected,true)){
            $validIds[]=(int)$permission['permission_id'];
        }
    }

    $oldCodes=array();
    $stmt=$db->prepare(
        "SELECT p.permission_code
         FROM qa_role_permissions rp
         INNER JOIN qa_permissions p ON p.permission_id=rp.permission_id
         WHERE rp.role_id=?
         ORDER BY p.permission_code"
    );
    $stmt->bind_param('i',$saveRoleId);
    $stmt->execute();
    $res=$stmt->get_result();
    if($res){while($row=$res->fetch_assoc())$oldCodes[]=$row['permission_code'];$res->free();}
    $stmt->close();

    $db->autocommit(false);
    try{
        $stmt=$db->prepare("DELETE FROM qa_role_permissions WHERE role_id=?");
        $stmt->bind_param('i',$saveRoleId);
        if(!$stmt->execute())throw new Exception('ไม่สามารถล้าง Permission เดิมได้');
        $stmt->close();

        foreach($validIds as $permissionId){
            $stmt=$db->prepare("INSERT INTO qa_role_permissions(role_id,permission_id) VALUES (?,?)");
            $stmt->bind_param('ii',$saveRoleId,$permissionId);
            if(!$stmt->execute())throw new Exception('บันทึก Permission ไม่สำเร็จ');
            $stmt->close();
        }

        $db->commit();
        $db->autocommit(true);

        $newCodes=array();
        foreach($permissions as $permission){
            if(in_array((int)$permission['permission_id'],$validIds,true)){
                $newCodes[]=$permission['permission_code'];
            }
        }

        qa_project_audit_action(
            'update_role_permissions','qa_roles',$saveRoleId,
            array('permissions'=>$oldCodes),
            array('permissions'=>$newCodes)
        );
        perm_flash('success','บันทึก Permission ของ Role “'.$role['role_name'].'” เรียบร้อยแล้ว');
    }catch(Throwable $e){
        $db->rollback();
        $db->autocommit(true);
        perm_flash('danger','ยกเลิกการบันทึกทั้งหมด: '.$e->getMessage());
    }
    perm_redirect($saveRoleId);
}

$assigned=array();
if($selectedRole){
    $stmt=$db->prepare("SELECT permission_id FROM qa_role_permissions WHERE role_id=?");
    $stmt->bind_param('i',$roleId);
    $stmt->execute();
    $res=$stmt->get_result();
    if($res){while($row=$res->fetch_assoc())$assigned[]=(int)$row['permission_id'];$res->free();}
    $stmt->close();
}

$message='';$messageType='success';
if(isset($_SESSION['qa_permission_flash'])){
    $messageType=$_SESSION['qa_permission_flash']['type'];
    $message=$_SESSION['qa_permission_flash']['text'];
    unset($_SESSION['qa_permission_flash']);
}

$moduleLabels=array(
    'dashboard'=>'Dashboard',
    'plan'=>'แผนพัฒนาคุณภาพ',
    'budget'=>'งบประมาณ',
    'project'=>'โครงการ',
    'activity'=>'กิจกรรมภายใต้โครงการ',
    'monitoring'=>'ติดตามและประเมินผล',
    'evidence'=>'หลักฐาน',
    'quality'=>'มาตรฐาน / ตัวชี้วัด',
    'reports'=>'รายงาน / SAR / สพฐ.',
    'system'=>'ระบบและผู้ใช้งาน'
);

$grouped=array();
foreach($permissions as $permission){
    $module=$permission['module_code'];
    if(!isset($grouped[$module]))$grouped[$module]=array();
    $grouped[$module][]=$permission;
}

require QA_ROOT.'/includes/header.php';
?>

<?php if($message): ?>
<div class="alert <?= $messageType==='danger'?'alert-danger':'alert-success' ?>"><?= h($message) ?></div>
<?php endif; ?>

<div class="notice" style="margin-bottom:16px">
    <strong>แนวทางสำหรับรองผู้อำนวยการ:</strong>
    ใช้ Role <code>deputy_director</code> เพื่อดูภาพรวมและกำกับติดตามโดยไม่อนุมัติขั้นสุดท้ายโดยอัตโนมัติ
    หากกำกับฝ่าย ให้กำหนด <code>deputy_director + division_head</code>
    และหากได้รับแต่งตั้งรักษาราชการแทน จึงค่อยเพิ่ม <code>director</code> ตามช่วงเวลาที่มีอำนาจ
</div>

<div class="card">
    <div class="section-heading">
        <div><h2>เลือก Role</h2><p>ผู้ใช้หนึ่งคนมีหลาย Role ได้ และระบบจะรวม Permission ของทุก Role</p></div>
    </div>
    <div class="role-select-tabs">
        <?php foreach($roles as $role): ?>
        <a class="btn <?= (int)$role['role_id']===$roleId?'btn-primary':'' ?>"
           href="<?= h(qa_url('settings/permissions.php?role='.$role['role_id'])) ?>">
            <?= h($role['role_name']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if($selectedRole): ?>
<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2><?= h($selectedRole['role_name']) ?> <code><?= h($selectedRole['role_code']) ?></code></h2>
            <p><?= h($selectedRole['description']) ?></p>
        </div>
        <span class="badge badge-blue"><?= h(count($assigned)) ?> Permission</span>
    </div>

    <?php if($selectedRole['role_code']==='admin'): ?>
    <div class="notice">
        <strong>admin เป็น Superuser</strong> ระบบให้ทุก Permission อัตโนมัติเพื่อป้องกันการล็อกผู้ดูแลระบบออกจากระบบ
    </div>
    <?php else: ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="role_id" value="<?= h($roleId) ?>">

        <?php foreach($grouped as $module=>$items): ?>
        <div class="permission-module">
            <h3><?= h(isset($moduleLabels[$module])?$moduleLabels[$module]:$module) ?></h3>
            <div class="permission-grid">
                <?php foreach($items as $permission): ?>
                <label class="permission-item">
                    <input type="checkbox" name="permission_ids[]" value="<?= h($permission['permission_id']) ?>"
                        <?= in_array((int)$permission['permission_id'],$assigned,true)?'checked':'' ?>>
                    <span>
                        <strong><?= h($permission['permission_name']) ?></strong>
                        <small><?= h($permission['permission_code']) ?> — <?= h($permission['description']) ?></small>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="action-bar">
            <button class="btn btn-primary" type="submit">บันทึก Permission ของ Role</button>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require QA_ROOT.'/includes/footer.php'; ?>
