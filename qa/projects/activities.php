<?php
$pageTitle='จัดการกิจกรรมภายใต้โครงการ';
$activeMenu='projects';
require_once __DIR__.'/../includes/bootstrap.php';
qa_require_login();

$db=qa_db();
$user=qa_current_user();
$userId=$user?(int)$user['user_id']:0;
$projectId=isset($_GET['project_id'])?(int)$_GET['project_id']:0;
if($projectId<=0){http_response_code(400);die('ไม่พบรหัสโครงการ');}

$stmt=$db->prepare(
    "SELECT p.*,d.division_name,
            CONCAT(COALESCE(u.prefix,''),COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS owner_name
     FROM qa_projects p
     INNER JOIN qa_divisions d ON d.division_id=p.division_id
     LEFT JOIN qa_users u ON u.user_id=p.owner_user_id
     WHERE p.project_id=? LIMIT 1"
);
$stmt->bind_param('i',$projectId);
$stmt->execute();
$result=$stmt->get_result();
$project=$result?$result->fetch_assoc():null;
if($result)$result->free();
$stmt->close();
if(!$project){http_response_code(404);die('ไม่พบโครงการ');}

$pageHeading='กิจกรรมภายใต้โครงการ — '.$project['project_code'];
$pageDescription=$project['project_name'].' • สร้างกิจกรรมหลัก/กิจกรรมย่อย และมอบหมายผู้รับผิดชอบแต่ละกิจกรรม';
$breadcrumbs=array(
    array('label'=>'โครงการทั้งหมด','url'=>qa_url('projects/index.php')),
    array('label'=>$project['project_code'],'url'=>qa_url('projects/view.php?id='.$projectId)),
    array('label'=>'จัดการกิจกรรม')
);

if(!qa_activity_tables_ready()){
    require QA_ROOT.'/includes/header.php';
    ?><div class="alert alert-danger">กรุณา Import <strong>anusarn_qa_v1.4.6_phase4.2.2_activity_hierarchy.sql</strong> ก่อนใช้งาน</div><?php
    require QA_ROOT.'/includes/footer.php';
    exit;
}

$canManage=qa_activity_can_manage_project($project);
if(!$canManage){http_response_code(403);die('เฉพาะผู้รับผิดชอบโครงการ หัวหน้าฝ่าย หรือผู้มีสิทธิ์จัดการกิจกรรมเท่านั้น');}

$editableStatuses=array('APPROVED','IN_PROGRESS','WAITING_REPORT');
$canEditActivities=in_array($project['status_code'],$editableStatuses,true);

function actm_redirect($projectId,$extra='')
{
    $url=qa_url('projects/activities.php?project_id='.(int)$projectId);
    if($extra!=='')$url.=$extra;
    header('Location: '.$url);
    exit;
}

function actm_date_or_empty($value)
{
    $value=trim((string)$value);
    if($value==='')return '';
    $p=explode('-',$value);
    if(count($p)!==3 || !checkdate((int)$p[1],(int)$p[2],(int)$p[0]))return false;
    return $value;
}

function actm_validate_dates($start,$end,$projectStart,$projectEnd)
{
    $errors=array();
    if($start===false || $end===false){$errors[]='รูปแบบวันที่ไม่ถูกต้อง';return $errors;}
    if($start!=='' && $end!=='' && $start>$end)$errors[]='วันที่เริ่มต้องไม่เกินวันที่สิ้นสุด';
    if($projectStart && $start!=='' && $start<$projectStart)$errors[]='วันที่เริ่มกิจกรรมต้องไม่ก่อนวันเริ่มโครงการ';
    if($projectEnd && $end!=='' && $end>$projectEnd)$errors[]='วันที่สิ้นสุดกิจกรรมต้องไม่เกินวันสิ้นสุดโครงการ';
    return $errors;
}

function actm_user_active($userId)
{
    $userId=(int)$userId;
    if($userId<=0)return false;
    return (int)qa_db_scalar("SELECT COUNT(*) FROM qa_users WHERE user_id=".$userId." AND status='active'",0)>0;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $token=isset($_POST['csrf_token'])?$_POST['csrf_token']:'';
    if(!qa_verify_csrf($token)){http_response_code(400);die('CSRF token ไม่ถูกต้อง');}
    if(!$canEditActivities){http_response_code(403);die('สถานะโครงการปัจจุบันไม่อนุญาตให้เพิ่มหรือแก้ไขกิจกรรม');}

    $action=trim(qa_request_scalar($_POST,'action'));

    if($action==='create_activity'){
        $parentId=isset($_POST['parent_activity_id'])?(int)$_POST['parent_activity_id']:0;
        $name=trim(qa_request_scalar($_POST,'activity_name'));
        $description=trim(qa_request_scalar($_POST,'activity_description'));
        $location=trim(qa_request_scalar($_POST,'location_text'));
        $responsible=isset($_POST['responsible_user_id'])?(int)$_POST['responsible_user_id']:0;
        $start=actm_date_or_empty(isset($_POST['start_date'])?$_POST['start_date']:'');
        $end=actm_date_or_empty(isset($_POST['end_date'])?$_POST['end_date']:'');
        $errors=actm_validate_dates($start,$end,$project['start_date'],$project['end_date']);
        if($name==='')$errors[]='กรุณาระบุชื่อกิจกรรม';
        if(!actm_user_active($responsible))$errors[]='กรุณาเลือกผู้รับผิดชอบกิจกรรม';

        $parent=null;
        if($parentId>0){
            $stmt=$db->prepare("SELECT activity_id,parent_activity_id,activity_no,activity_name FROM qa_project_activities WHERE activity_id=? AND project_id=? LIMIT 1");
            $stmt->bind_param('ii',$parentId,$projectId);$stmt->execute();$res=$stmt->get_result();$parent=$res?$res->fetch_assoc():null;if($res)$res->free();$stmt->close();
            if(!$parent)$errors[]='ไม่พบกิจกรรมหลักที่เลือก';
            elseif((int)$parent['parent_activity_id']>0)$errors[]='ระบบรองรับกิจกรรมย่อย 1 ระดับเท่านั้น';
        }

        if(empty($errors)){
            $activityNo=qa_activity_next_no($projectId,$parentId);
            $sort=$activityNo;
            $stmt=$db->prepare(
                "INSERT INTO qa_project_activities
                 (project_id,parent_activity_id,activity_no,activity_name,activity_description,start_date,end_date,location_text,responsible_user_id,status,sort_order)
                 VALUES (?,NULLIF(?,0),?,?,NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),?,'planned',?)"
            );
            $stmt->bind_param('iiisssssii',$projectId,$parentId,$activityNo,$name,$description,$start,$end,$location,$responsible,$sort);
            if($stmt->execute()){
                $newId=$stmt->insert_id;
                $stmt->close();
                if($parentId>0)qa_activity_recalculate_parent($parentId);
                qa_project_audit_action('create_project_activity','qa_project_activities',$newId,null,array('project_id'=>$projectId,'parent_activity_id'=>$parentId,'activity_name'=>$name,'responsible_user_id'=>$responsible));
                $_SESSION['qa_activity_flash']=array('type'=>'success','text'=>$parentId>0?'เพิ่มกิจกรรมย่อยและมอบหมายผู้รับผิดชอบเรียบร้อยแล้ว':'เพิ่มกิจกรรมหลักและมอบหมายผู้รับผิดชอบเรียบร้อยแล้ว');
                actm_redirect($projectId,'#activity-'.$newId);
            }
            $err=$stmt->error;$stmt->close();
            $errors[]='เพิ่มกิจกรรมไม่สำเร็จ: '.$err;
        }
        $_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>implode(' / ',$errors));
        actm_redirect($projectId,'#add-activity');
    }

    if($action==='update_activity'){
        $activityId=isset($_POST['activity_id'])?(int)$_POST['activity_id']:0;
        $name=trim(qa_request_scalar($_POST,'activity_name'));
        $description=trim(qa_request_scalar($_POST,'activity_description'));
        $location=trim(qa_request_scalar($_POST,'location_text'));
        $responsible=isset($_POST['responsible_user_id'])?(int)$_POST['responsible_user_id']:0;
        $start=actm_date_or_empty(isset($_POST['start_date'])?$_POST['start_date']:'');
        $end=actm_date_or_empty(isset($_POST['end_date'])?$_POST['end_date']:'');
        $errors=actm_validate_dates($start,$end,$project['start_date'],$project['end_date']);
        if($name==='')$errors[]='กรุณาระบุชื่อกิจกรรม';
        if(!actm_user_active($responsible))$errors[]='กรุณาเลือกผู้รับผิดชอบกิจกรรม';

        $stmt=$db->prepare("SELECT * FROM qa_project_activities WHERE activity_id=? AND project_id=? LIMIT 1");
        $stmt->bind_param('ii',$activityId,$projectId);$stmt->execute();$res=$stmt->get_result();$old=$res?$res->fetch_assoc():null;if($res)$res->free();$stmt->close();
        if(!$old)$errors[]='ไม่พบกิจกรรม';

        if(empty($errors)){
            $stmt=$db->prepare(
                "UPDATE qa_project_activities
                 SET activity_name=?,responsible_user_id=?,activity_description=NULLIF(?,''),start_date=NULLIF(?,''),end_date=NULLIF(?,''),location_text=NULLIF(?, '')
                 WHERE activity_id=? AND project_id=?"
            );
            $stmt->bind_param('sissssii',$name,$responsible,$description,$start,$end,$location,$activityId,$projectId);
            if($stmt->execute()){
                $stmt->close();
                qa_project_audit_action('update_project_activity','qa_project_activities',$activityId,$old,array('activity_name'=>$name,'responsible_user_id'=>$responsible,'description'=>$description,'start_date'=>$start,'end_date'=>$end,'location_text'=>$location));
                $_SESSION['qa_activity_flash']=array('type'=>'success','text'=>'บันทึกกิจกรรมและผู้รับผิดชอบเรียบร้อยแล้ว');
                actm_redirect($projectId,'#activity-'.$activityId);
            }
            $err=$stmt->error;$stmt->close();$errors[]='บันทึกไม่สำเร็จ: '.$err;
        }
        $_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>implode(' / ',$errors));
        actm_redirect($projectId,'#activity-'.$activityId);
    }

    if($action==='delete_activity'){
        $activityId=isset($_POST['activity_id'])?(int)$_POST['activity_id']:0;
        $stmt=$db->prepare("SELECT * FROM qa_project_activities WHERE activity_id=? AND project_id=? LIMIT 1");
        $stmt->bind_param('ii',$activityId,$projectId);$stmt->execute();$res=$stmt->get_result();$row=$res?$res->fetch_assoc():null;if($res)$res->free();$stmt->close();
        if(!$row){$_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>'ไม่พบกิจกรรม');actm_redirect($projectId);}

        $childCount=qa_activity_child_count($activityId);
        $budgetCount=(int)qa_db_scalar("SELECT COUNT(*) FROM qa_project_budget_items WHERE activity_id=".$activityId,0);
        $reportCount=(int)qa_db_scalar("SELECT COUNT(*) FROM qa_activity_progress_reports WHERE activity_id=".$activityId,0);
        $evidenceCount=(int)qa_db_scalar("SELECT COUNT(*) FROM qa_evidences WHERE activity_id=".$activityId,0);
        $expenseCount=(int)qa_db_scalar("SELECT COUNT(*) FROM qa_expenditures WHERE activity_id=".$activityId,0);
        if($childCount>0||$budgetCount>0||$reportCount>0||$evidenceCount>0||$expenseCount>0){
            $_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>'ลบไม่ได้: กิจกรรมนี้มีรายการงบ กิจกรรมย่อย รายงาน หลักฐาน หรือการเบิกจ่ายแล้ว');
            actm_redirect($projectId,'#activity-'.$activityId);
        }
        $parentId=(int)$row['parent_activity_id'];
        $stmt=$db->prepare("DELETE FROM qa_project_activities WHERE activity_id=? AND project_id=?");
        $stmt->bind_param('ii',$activityId,$projectId);
        if($stmt->execute()){
            $stmt->close();
            if($parentId>0)qa_activity_recalculate_parent($parentId);
            qa_project_audit_action('delete_project_activity','qa_project_activities',$activityId,$row,null);
            $_SESSION['qa_activity_flash']=array('type'=>'success','text'=>'ลบกิจกรรมที่ยังไม่มีข้อมูลอ้างอิงเรียบร้อยแล้ว');
            actm_redirect($projectId);
        }
        $err=$stmt->error;$stmt->close();
        $_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>'ลบไม่สำเร็จ: '.$err);
        actm_redirect($projectId,'#activity-'.$activityId);
    }
}

$users=array();
$result=$db->query(
    "SELECT u.user_id,u.username,u.prefix,u.first_name,u.last_name,u.position_name,d.division_name,
            GROUP_CONCAT(r.role_code ORDER BY r.role_id SEPARATOR ',') AS role_codes
     FROM qa_users u
     LEFT JOIN qa_divisions d ON d.division_id=u.primary_division_id
     LEFT JOIN qa_user_roles ur ON ur.user_id=u.user_id
     LEFT JOIN qa_roles r ON r.role_id=ur.role_id
     WHERE u.status='active'
     GROUP BY u.user_id
     ORDER BY CASE WHEN u.primary_division_id=".(int)$project['division_id']." THEN 0 ELSE 1 END,u.first_name,u.last_name"
);
if($result){while($r=$result->fetch_assoc())$users[]=$r;$result->free();}

$activities=array();
$stmt=$db->prepare(
    "SELECT a.*,pa.activity_no AS parent_activity_no,pa.activity_name AS parent_activity_name,
            CONCAT(COALESCE(u.prefix,''),COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS responsible_name,
            COALESCE((SELECT SUM(bi.approved_amount) FROM qa_project_budget_items bi WHERE bi.activity_id=a.activity_id),0) AS approved_budget,
            COALESCE((SELECT SUM(e.amount) FROM qa_expenditures e WHERE e.activity_id=a.activity_id AND e.payment_status='paid'),0) AS actual_expense,
            (SELECT COUNT(*) FROM qa_evidences ev WHERE ev.activity_id=a.activity_id) AS evidence_count,
            (SELECT COUNT(*) FROM qa_activity_progress_reports pr WHERE pr.activity_id=a.activity_id) AS report_count,
            (SELECT COUNT(*) FROM qa_project_activities ch WHERE ch.parent_activity_id=a.activity_id) AS child_count
     FROM qa_project_activities a
     LEFT JOIN qa_project_activities pa ON pa.activity_id=a.parent_activity_id
     LEFT JOIN qa_users u ON u.user_id=a.responsible_user_id
     WHERE a.project_id=?
     ORDER BY COALESCE(a.parent_activity_id,a.activity_id),CASE WHEN a.parent_activity_id IS NULL THEN 0 ELSE 1 END,a.sort_order,a.activity_id"
);
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();if($result){while($r=$result->fetch_assoc())$activities[]=$r;$result->free();}$stmt->close();

$roots=array();$childrenByParent=array();
foreach($activities as $a){
    if((int)$a['parent_activity_id']>0){
        $pid=(int)$a['parent_activity_id'];
        if(!isset($childrenByParent[$pid]))$childrenByParent[$pid]=array();
        $childrenByParent[$pid][]=$a;
    }else $roots[]=$a;
}

$selectedParent=isset($_GET['parent'])?(int)$_GET['parent']:0;
if($selectedParent>0){
    $valid=false;foreach($roots as $root){if((int)$root['activity_id']===$selectedParent){$valid=true;break;}}
    if(!$valid)$selectedParent=0;
}

$message='';$messageType='success';
if(isset($_SESSION['qa_activity_flash'])){$messageType=$_SESSION['qa_activity_flash']['type'];$message=$_SESSION['qa_activity_flash']['text'];unset($_SESSION['qa_activity_flash']);}

require QA_ROOT.'/includes/header.php';
?>
<?php if($message): ?><div class="alert <?= $messageType==='danger'?'alert-danger':'alert-success' ?>"><?= h($message) ?></div><?php endif; ?>

<div class="notice" style="margin-bottom:16px">
    <strong>โครงสร้างกิจกรรม:</strong> กิจกรรมหลักอยู่ภายใต้โครงการ และสามารถมีกิจกรรมย่อยได้ 1 ระดับ ผู้รับผิดชอบแต่ละกิจกรรมสามารถเป็นคนละคนกันได้
</div>
<div class="action-bar"><a class="btn" href="<?= h(qa_url('projects/view.php?id='.$projectId)) ?>">← กลับรายละเอียดโครงการ</a></div>

<?php if(!$canEditActivities): ?>
<div class="notice" style="margin-top:14px">โครงการสถานะ <strong><?= h($project['status_code']) ?></strong> เปิดดูโครงสร้างกิจกรรมได้ แต่ไม่สามารถเพิ่ม/แก้ไข/ลบกิจกรรม</div>
<?php endif; ?>

<?php if($canEditActivities): ?>
<div class="card activity-create-card" id="add-activity" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>＋ เพิ่มกิจกรรม</h2><p>เพิ่มกิจกรรมหลัก หรือเลือกกิจกรรมหลักเพื่อสร้างกิจกรรมย่อย พร้อมกำหนดผู้รับผิดชอบทันที</p></div>
    </div>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="create_activity">
        <div class="form-grid">
            <div class="form-group">
                <label>ระดับกิจกรรม</label>
                <select name="parent_activity_id">
                    <option value="0" <?= $selectedParent===0?'selected':'' ?>>กิจกรรมหลักภายใต้โครงการ</option>
                    <?php foreach($roots as $root): ?>
                    <option value="<?= h($root['activity_id']) ?>" <?= $selectedParent===(int)$root['activity_id']?'selected':'' ?>>กิจกรรมย่อยของ <?= h($root['activity_no'].'. '.$root['activity_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>ชื่อกิจกรรม *</label><input name="activity_name" required></div>
            <div class="form-group">
                <label>ผู้รับผิดชอบกิจกรรม *</label>
                <select name="responsible_user_id" required>
                    <option value="">-- เลือกผู้รับผิดชอบ --</option>
                    <?php foreach($users as $u): $name=trim($u['prefix'].$u['first_name'].' '.$u['last_name']); ?>
                    <option value="<?= h($u['user_id']) ?>"><?= h($name) ?><?= $u['division_name']?' — '.h($u['division_name']):'' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>ช่วงดำเนินกิจกรรม</label><div class="date-pair"><input type="date" name="start_date" value="<?= h($project['start_date']) ?>"><input type="date" name="end_date" value="<?= h($project['end_date']) ?>"></div></div>
            <div class="form-group full"><label>รายละเอียด / ขอบเขตกิจกรรม</label><textarea name="activity_description" placeholder="สิ่งที่ต้องดำเนินการ ผลที่ต้องส่งมอบ หรือขอบเขตความรับผิดชอบ"></textarea></div>
            <div class="form-group full"><label>สถานที่ (ถ้ามี)</label><input name="location_text"></div>
        </div>
        <button class="btn btn-primary" type="submit">เพิ่มกิจกรรมและมอบหมาย</button>
    </form>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>โครงสร้างกิจกรรม</h2><p>กิจกรรมหลัก → กิจกรรมย่อย → ผู้รับผิดชอบ → รายงานความก้าวหน้าและหลักฐาน</p></div>
        <span class="badge badge-blue"><?= h(count($activities)) ?> รายการ</span>
    </div>

    <?php if(empty($roots)): ?>
        <div class="empty-cell">ยังไม่มีกิจกรรมภายใต้โครงการ</div>
    <?php else: foreach($roots as $root): $rootNo=qa_activity_display_no($root); ?>
        <div class="activity-tree-root" id="activity-<?= h($root['activity_id']) ?>">
            <div class="activity-tree-header">
                <div class="activity-tree-title"><span class="activity-level-badge main">กิจกรรมหลัก</span><strong><?= h($rootNo.'. '.$root['activity_name']) ?></strong></div>
                <div><span class="badge <?= h(qa_activity_status_badge($root['status'])) ?>"><?= h(qa_activity_status_label($root['status'])) ?> <?= h(number_format((float)$root['progress_percent'],0)) ?>%</span></div>
            </div>

            <form method="post" class="activity-edit-form">
                <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
                <input type="hidden" name="action" value="update_activity">
                <input type="hidden" name="activity_id" value="<?= h($root['activity_id']) ?>">
                <div class="form-grid">
                    <div class="form-group"><label>ชื่อกิจกรรม *</label><input name="activity_name" required value="<?= h($root['activity_name']) ?>" <?= $canEditActivities?'':'readonly' ?>></div>
                    <div class="form-group"><label>ผู้รับผิดชอบ *</label><select name="responsible_user_id" required <?= $canEditActivities?'':'disabled' ?>><?php foreach($users as $u): $name=trim($u['prefix'].$u['first_name'].' '.$u['last_name']); ?><option value="<?= h($u['user_id']) ?>" <?= (int)$u['user_id']===(int)$root['responsible_user_id']?'selected':'' ?>><?= h($name) ?><?= $u['division_name']?' — '.h($u['division_name']):'' ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>ช่วงดำเนินกิจกรรม</label><div class="date-pair"><input type="date" name="start_date" value="<?= h($root['start_date']) ?>" <?= $canEditActivities?'':'readonly' ?>><input type="date" name="end_date" value="<?= h($root['end_date']) ?>" <?= $canEditActivities?'':'readonly' ?>></div></div>
                    <div class="form-group"><label>สถานที่</label><input name="location_text" value="<?= h($root['location_text']) ?>" <?= $canEditActivities?'':'readonly' ?>></div>
                    <div class="form-group full"><label>รายละเอียด / ขอบเขต</label><textarea name="activity_description" <?= $canEditActivities?'':'readonly' ?>><?= h($root['activity_description']) ?></textarea></div>
                </div>
                <div class="activity-meta-line">งบอนุมัติ <?= h(qa_money($root['approved_budget'])) ?> • ใช้จริง <?= h(qa_money($root['actual_expense'])) ?> • รายงาน <?= h($root['report_count']) ?> ครั้ง • หลักฐาน <?= h($root['evidence_count']) ?> รายการ<?= (int)$root['child_count']>0?' • ความก้าวหน้าหลักคำนวณจากกิจกรรมย่อย':'' ?></div>
                <div class="action-bar">
                    <?php if($canEditActivities): ?><button class="btn btn-primary btn-sm" type="submit">บันทึก</button><a class="btn btn-sm" href="<?= h(qa_url('projects/activities.php?project_id='.$projectId.'&parent='.$root['activity_id'].'#add-activity')) ?>">＋ เพิ่มกิจกรรมย่อย</a><?php endif; ?>
                    <a class="btn btn-sm" href="<?= h(qa_url('activities/view.php?id='.$root['activity_id'])) ?>">เปิดหน้ากิจกรรม</a>
                </div>
            </form>

            <?php $children=isset($childrenByParent[(int)$root['activity_id']])?$childrenByParent[(int)$root['activity_id']]:array(); ?>
            <?php if(!empty($children)): ?>
            <div class="activity-tree-children">
                <?php foreach($children as $child): $childNo=qa_activity_display_no($child); ?>
                <div class="activity-tree-child" id="activity-<?= h($child['activity_id']) ?>">
                    <div class="activity-tree-header">
                        <div class="activity-tree-title"><span class="activity-level-badge sub">กิจกรรมย่อย</span><strong><?= h($childNo.'. '.$child['activity_name']) ?></strong></div>
                        <span class="badge <?= h(qa_activity_status_badge($child['status'])) ?>"><?= h(qa_activity_status_label($child['status'])) ?> <?= h(number_format((float)$child['progress_percent'],0)) ?>%</span>
                    </div>
                    <form method="post" class="activity-edit-form">
                        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_activity">
                        <input type="hidden" name="activity_id" value="<?= h($child['activity_id']) ?>">
                        <div class="form-grid">
                            <div class="form-group"><label>ชื่อกิจกรรมย่อย *</label><input name="activity_name" required value="<?= h($child['activity_name']) ?>" <?= $canEditActivities?'':'readonly' ?>></div>
                            <div class="form-group"><label>ผู้รับผิดชอบกิจกรรมย่อย *</label><select name="responsible_user_id" required <?= $canEditActivities?'':'disabled' ?>><?php foreach($users as $u): $name=trim($u['prefix'].$u['first_name'].' '.$u['last_name']); ?><option value="<?= h($u['user_id']) ?>" <?= (int)$u['user_id']===(int)$child['responsible_user_id']?'selected':'' ?>><?= h($name) ?><?= $u['division_name']?' — '.h($u['division_name']):'' ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>ช่วงดำเนินกิจกรรม</label><div class="date-pair"><input type="date" name="start_date" value="<?= h($child['start_date']) ?>" <?= $canEditActivities?'':'readonly' ?>><input type="date" name="end_date" value="<?= h($child['end_date']) ?>" <?= $canEditActivities?'':'readonly' ?>></div></div>
                            <div class="form-group"><label>สถานที่</label><input name="location_text" value="<?= h($child['location_text']) ?>" <?= $canEditActivities?'':'readonly' ?>></div>
                            <div class="form-group full"><label>รายละเอียด / ขอบเขต</label><textarea name="activity_description" <?= $canEditActivities?'':'readonly' ?>><?= h($child['activity_description']) ?></textarea></div>
                        </div>
                        <div class="activity-meta-line">ผู้รับผิดชอบ <?= trim($child['responsible_name'])?h(trim($child['responsible_name'])):'-' ?> • รายงาน <?= h($child['report_count']) ?> ครั้ง • หลักฐาน <?= h($child['evidence_count']) ?> รายการ</div>
                        <div class="action-bar">
                            <?php if($canEditActivities): ?><button class="btn btn-primary btn-sm" type="submit">บันทึก</button><?php endif; ?>
                            <a class="btn btn-sm" href="<?= h(qa_url('activities/view.php?id='.$child['activity_id'])) ?>">เปิดหน้ากิจกรรม</a>
                        </div>
                    </form>
                    <?php if($canEditActivities && (int)$child['report_count']===0 && (int)$child['evidence_count']===0 && (float)$child['approved_budget']==0 && (float)$child['actual_expense']==0): ?>
                    <form method="post" class="activity-delete-form" onsubmit="return confirm('ลบกิจกรรมย่อยนี้หรือไม่?');">
                        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>"><input type="hidden" name="action" value="delete_activity"><input type="hidden" name="activity_id" value="<?= h($child['activity_id']) ?>"><button class="btn btn-danger btn-sm" type="submit">ลบกิจกรรมย่อย</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
</div>

<?php require QA_ROOT.'/includes/footer.php'; ?>
