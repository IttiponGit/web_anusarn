<?php
$pageTitle = 'ขอแก้ไข / ยกเลิกโครงการ';
$activeMenu = 'project-changes';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

$db=qa_db();
$user=qa_current_user();
$userId=$user?(int)$user['user_id']:0;

$projectId=isset($_GET['id'])?(int)$_GET['id']:0;
if($projectId<=0){
    http_response_code(400);
    die('ไม่พบรหัสโครงการ');
}

$project=qa_project_change_load_project($projectId);
if(!$project){
    http_response_code(404);
    die('ไม่พบโครงการ');
}

$pageHeading='ขอแก้ไข / ยกเลิก '.$project['project_code'];
$pageDescription='สร้างคำขออย่างเป็นทางการเพื่อรักษาประวัติและป้องกันการแก้ข้อมูลหลังส่ง/อนุมัติโดยไม่มีร่องรอย';
$breadcrumbs=array(
    array('label'=>'โครงการทั้งหมด','url'=>qa_url('projects/index.php')),
    array('label'=>$project['project_code'],'url'=>qa_url('projects/view.php?id='.$projectId)),
    array('label'=>'ขอแก้ไข / ยกเลิก')
);

if(!qa_project_change_table_ready()){
    require QA_ROOT.'/includes/header.php';
    ?>
    <div class="alert alert-danger">
        ยังไม่ได้ติดตั้งตาราง Phase 3.5 กรุณา Import
        <strong>anusarn_qa_v1.1_project_correction.sql</strong> ก่อนใช้งานเมนูนี้
    </div>
    <?php
    require QA_ROOT.'/includes/footer.php';
    exit;
}

if(!qa_project_change_can_request($project)){
    http_response_code(403);
    die('บัญชีของคุณไม่มีสิทธิ์สร้างคำขอสำหรับโครงการนี้');
}

$allowedTypes=qa_project_change_allowed_types($project);
$pending=qa_project_change_pending_request($projectId);
$execution=qa_project_change_execution_summary($projectId);

function change_request_flash($type,$text)
{
    $_SESSION['qa_project_flash']=array('type'=>$type,'text'=>$text);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $token=isset($_POST['csrf_token'])?$_POST['csrf_token']:'';
    if(!qa_verify_csrf($token)){
        http_response_code(400);
        die('CSRF token ไม่ถูกต้อง');
    }

    if($pending){
        change_request_flash('danger','โครงการนี้มีคำขอที่รอพิจารณาอยู่แล้ว');
        header('Location: '.qa_url('projects/view.php?id='.$projectId));
        exit;
    }

    $requestType=isset($_POST['request_type'])?trim($_POST['request_type']):'';
    $category=isset($_POST['request_category'])?trim($_POST['request_category']):'other';
    $impact=isset($_POST['impact_level'])?trim($_POST['impact_level']):'major';
    $reason=isset($_POST['reason'])?trim($_POST['reason']):'';
    $summary=isset($_POST['change_summary'])?trim($_POST['change_summary']):'';
    $ack=isset($_POST['acknowledge'])?$_POST['acknowledge']:'';

    $validCategories=array('general','schedule','alignment','budget','objective','result','other');
    if(!in_array($requestType,$allowedTypes,true)){
        change_request_flash('danger','ประเภทคำขอไม่สอดคล้องกับสถานะปัจจุบันของโครงการ');
        header('Location: '.qa_url('projects/change-request.php?id='.$projectId));
        exit;
    }

    if($requestType==='cancellation'){
        $category='cancel';
        $impact='major';
    }else{
        if(!in_array($category,$validCategories,true))$category='other';
        if(!in_array($impact,array('minor','major'),true))$impact='major';
    }

    $errors=array();
    if($ack!=='1')$errors[]='กรุณายืนยันว่าตรวจสอบข้อมูลก่อนส่งคำขอ';
    if(mb_strlen($reason,'UTF-8')<5)$errors[]='กรุณาระบุเหตุผลอย่างน้อย 5 ตัวอักษร';
    if($requestType==='correction' && mb_strlen($summary,'UTF-8')<5){
        $errors[]='กรุณาระบุข้อมูลที่ต้องการแก้ และข้อมูลที่ถูกต้อง';
    }

    if(!empty($errors)){
        change_request_flash('danger',implode(' / ',$errors));
        header('Location: '.qa_url('projects/change-request.php?id='.$projectId));
        exit;
    }

    $resolution=qa_project_change_resolution_mode($project,$requestType);
    $previousStatus=$project['status_code'];

    $stmt=$db->prepare(
        "INSERT INTO qa_project_change_requests
        (project_id,request_type,request_category,impact_level,resolution_mode,
         reason,change_summary,previous_status_code,request_status,requested_by)
        VALUES (?,?,?,?,?,?,NULLIF(?,''),?,'pending',NULLIF(?,0))"
    );
    if(!$stmt){
        change_request_flash('danger','ไม่สามารถเตรียมคำสั่งบันทึกคำขอได้');
        header('Location: '.qa_url('projects/view.php?id='.$projectId));
        exit;
    }
    $stmt->bind_param(
        'isssssssi',
        $projectId,$requestType,$category,$impact,$resolution,$reason,$summary,$previousStatus,$userId
    );

    if($stmt->execute()){
        $requestId=$stmt->insert_id;
        $stmt->close();

        $commentText=qa_project_change_type_label($requestType).': '.$reason;
        if($summary!=='')$commentText.=' | รายละเอียด: '.$summary;
        qa_project_add_comment(
            $projectId,
            'correction',
            $requestType==='correction'?'correction_request':'cancellation_request',
            $commentText,
            $userId
        );

        qa_project_audit_action(
            'create_project_change_request',
            'qa_project_change_requests',
            $requestId,
            null,
            array(
                'project_id'=>$projectId,
                'request_type'=>$requestType,
                'resolution_mode'=>$resolution,
                'previous_status_code'=>$previousStatus,
                'reason'=>$reason,
                'change_summary'=>$summary
            )
        );

        change_request_flash(
            'success',
            'ส่ง'.qa_project_change_type_label($requestType).'เรียบร้อยแล้ว ระบบจะหยุด Workflow/การบันทึกความก้าวหน้าชั่วคราวจนกว่าจะพิจารณาคำขอ'
        );
        header('Location: '.qa_url('projects/view.php?id='.$projectId));
        exit;
    }

    $err=$stmt->error;
    $stmt->close();
    change_request_flash('danger','ไม่สามารถบันทึกคำขอได้: '.$err);
    header('Location: '.qa_url('projects/view.php?id='.$projectId));
    exit;
}

$message='';
$messageType='success';
if(isset($_SESSION['qa_project_flash'])&&is_array($_SESSION['qa_project_flash'])){
    $messageType=isset($_SESSION['qa_project_flash']['type'])?$_SESSION['qa_project_flash']['type']:'success';
    $message=isset($_SESSION['qa_project_flash']['text'])?$_SESSION['qa_project_flash']['text']:'';
    unset($_SESSION['qa_project_flash']);
}

require QA_ROOT.'/includes/header.php';
?>

<?php if($message!==''): ?>
<div class="alert <?= $messageType==='danger'?'alert-danger':'alert-success' ?>" style="margin-bottom:16px"><?= h($message) ?></div>
<?php endif; ?>

<div class="action-bar">
    <a class="btn" href="<?= h(qa_url('projects/view.php?id='.$projectId)) ?>">← กลับรายละเอียดโครงการ</a>
    <a class="btn" href="<?= h(qa_url('projects/changes.php')) ?>">รายการคำขอทั้งหมด</a>
</div>

<?php if($pending): ?>
<div class="alert alert-danger">
    โครงการนี้มีคำขอ <strong><?= h(qa_project_change_type_label($pending['request_type'])) ?></strong>
    ที่รอพิจารณาอยู่แล้ว (#<?= h($pending['request_id']) ?>)
    <a href="<?= h(qa_url('projects/changes.php?id='.$pending['request_id'])) ?>">เปิดคำขอ</a>
</div>
<?php elseif(empty($allowedTypes)): ?>
<div class="notice">สถานะ <?= h($project['status_name']) ?> ไม่เปิดให้สร้างคำขอแก้ไขหรือยกเลิกเพิ่มเติม</div>
<?php else: ?>

<div class="grid grid-4">
    <div class="card"><div class="metric-label">สถานะปัจจุบัน</div><div style="margin-top:8px"><span class="badge badge-blue"><?= h($project['status_name']) ?></span></div></div>
    <div class="card"><div class="metric-label">งบอนุมัติ</div><div class="metric-value"><?= h(qa_money($project['approved_budget'])) ?></div></div>
    <div class="card"><div class="metric-label">เบิกจ่ายจริง</div><div class="metric-value"><?= h(qa_money($execution['actual_expense'])) ?></div></div>
    <div class="card"><div class="metric-label">ข้อมูลการดำเนินงาน</div><div class="metric-value"><?= h($execution['progress_reports']+$execution['evidences']+$execution['project_results']) ?></div><div class="metric-note">Progress + Evidence + Result</div></div>
</div>

<?php if(in_array('correction',$allowedTypes,true)): ?>
<div class="card correction-rule-card" style="margin-top:16px">
    <h2>กติกาการแก้ไขสำหรับสถานะนี้</h2>
    <?php $mode=qa_project_change_resolution_mode($project,'correction'); ?>
    <?php if($mode==='reopen_revision'): ?>
        <p>หากผู้อำนวยการอนุมัติคำขอ ระบบจะเปลี่ยนโครงการกลับเป็น <strong>REVISION</strong>
        เพื่อให้แก้ข้อมูลจริงในแบบฟอร์ม แล้วต้องส่งผ่าน Workflow งานแผน → งานงบประมาณ → ผู้อำนวยการใหม่</p>
    <?php else: ?>
        <p>โครงการมีการดำเนินงานแล้วหรืออยู่ในขั้นผลลัพธ์ ระบบจะ <strong>ไม่เขียนทับข้อมูลเดิม</strong>
        แต่บันทึก “Correction / Amendment” ที่ได้รับการรับรองไว้เป็นหลักฐานย้อนหลัง เพื่อรักษาความถูกต้องของ Audit Trail</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<form method="post" class="card" style="margin-top:16px">
    <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">

    <div class="section-heading">
        <div><h2>สร้างคำขอ</h2><p><?= h($project['project_code'].' — '.$project['project_name']) ?></p></div>
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label>ประเภทคำขอ *</label>
            <select name="request_type" id="requestType" required>
                <?php foreach($allowedTypes as $type): ?>
                <option value="<?= h($type) ?>"><?= h(qa_project_change_type_label($type)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group correction-only">
            <label>หมวดข้อมูลที่ต้องแก้</label>
            <select name="request_category">
                <option value="general">ข้อมูลทั่วไป</option>
                <option value="schedule">ระยะเวลา / สถานที่</option>
                <option value="alignment">แผน / กลยุทธ์ / ตัวชี้วัด</option>
                <option value="budget">งบประมาณ</option>
                <option value="objective">วัตถุประสงค์ / กลุ่มเป้าหมาย</option>
                <option value="result">ผลการดำเนินงาน</option>
                <option value="other">อื่น ๆ</option>
            </select>
        </div>

        <div class="form-group correction-only">
            <label>ระดับผลกระทบ</label>
            <select name="impact_level">
                <option value="minor">เล็กน้อย — ไม่เปลี่ยนสาระหลัก</option>
                <option value="major" selected>สาระสำคัญ — ควรตรวจสอบใหม่</option>
            </select>
        </div>

        <div class="form-group full">
            <label>เหตุผล *</label>
            <textarea name="reason" required placeholder="เช่น กรอกข้อมูลผิดจากเอกสารต้นฉบับ / กิจกรรมไม่สามารถดำเนินการได้ตามเหตุผล..."></textarea>
        </div>

        <div class="form-group full correction-only">
            <label>ข้อมูลที่ต้องการแก้ / ข้อมูลที่ถูกต้อง *</label>
            <textarea name="change_summary" placeholder="ระบุให้ชัด เช่น เดิม: วันที่สิ้นสุด 30 ก.ย. 2570 / ที่ถูกต้อง: 31 มี.ค. 2570"></textarea>
        </div>
    </div>

    <label class="change-ack">
        <input type="checkbox" name="acknowledge" value="1" required>
        <span>ฉันตรวจสอบโครงการและรายละเอียดคำขอนี้แล้ว และเข้าใจว่าคำขอจะถูกเก็บเป็นประวัติในระบบ</span>
    </label>

    <div class="notice" style="margin-top:14px">
        เมื่อมีคำขอรอพิจารณา ระบบจะหยุดการอนุมัติและการบันทึกความก้าวหน้าของโครงการนี้ชั่วคราว เพื่อป้องกันสถานะเปลี่ยนระหว่างพิจารณาคำขอ
    </div>

    <button class="btn btn-primary" type="submit" style="margin-top:14px">ส่งคำขอ</button>
</form>

<script>
(function(){
    var select=document.getElementById('requestType');
    if(!select)return;

    function refresh(){
        var nodes=document.querySelectorAll('.correction-only');
        for(var i=0;i<nodes.length;i++){
            nodes[i].style.display=select.value==='correction'?'':'none';
        }
    }
    select.addEventListener('change',refresh);
    refresh();
})();
</script>
<?php endif; ?>

<?php require QA_ROOT.'/includes/footer.php'; ?>
