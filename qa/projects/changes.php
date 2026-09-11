<?php
$pageTitle='แก้ไข / ยกเลิกโครงการ';
$pageHeading='Project Correction & Cancellation';
$pageDescription='ติดตามคำขอแก้ไขและยกเลิกโครงการ พร้อมการพิจารณา การเปิดกลับ Revision และ Audit Trail';
$activeMenu='project-changes';

require_once __DIR__.'/../includes/bootstrap.php';
qa_require_login();

$db=qa_db();
$user=qa_current_user();
$userId=$user?(int)$user['user_id']:0;
$currentYear=qa_current_year_row();
$yearId=$currentYear?(int)$currentYear['year_id']:0;
$yearBE=$currentYear?(int)$currentYear['fiscal_year_be']:0;
$canReview=qa_project_change_can_review();

$breadcrumbs=array(array('label'=>'แก้ไข / ยกเลิกโครงการ'));

function changes_flash($type,$text)
{
    $_SESSION['qa_change_flash']=array('type'=>$type,'text'=>$text);
}

function changes_redirect($query)
{
    $url=qa_url('projects/changes.php');
    if($query!=='')$url.='?'.$query;
    header('Location: '.$url);
    exit;
}

if(!qa_project_change_table_ready()){
    require QA_ROOT.'/includes/header.php';
    ?>
    <div class="alert alert-danger">
        ยังไม่ได้ติดตั้งฐานข้อมูล Phase 3.5 กรุณา Import
        <strong>anusarn_qa_v1.1_project_correction.sql</strong>
        แล้วโหลดหน้านี้ใหม่
    </div>
    <?php
    require QA_ROOT.'/includes/footer.php';
    exit;
}

/* Actions */
if($_SERVER['REQUEST_METHOD']==='POST'){
    $token=isset($_POST['csrf_token'])?$_POST['csrf_token']:'';
    if(!qa_verify_csrf($token)){
        http_response_code(400);
        die('CSRF token ไม่ถูกต้อง');
    }

    $requestId=isset($_POST['request_id'])?(int)$_POST['request_id']:0;
    $action=isset($_POST['action'])?trim($_POST['action']):'';
    $reviewComment=isset($_POST['review_comment'])?trim($_POST['review_comment']):'';

    $stmt=$db->prepare(
        "SELECT r.*,p.project_code,p.project_name,p.status_code AS current_status_code,
                p.owner_user_id,p.division_id,p.approved_at
         FROM qa_project_change_requests r
         INNER JOIN qa_projects p ON p.project_id=r.project_id
         WHERE r.request_id=? LIMIT 1"
    );
    $stmt->bind_param('i',$requestId);
    $stmt->execute();
    $result=$stmt->get_result();
    $request=$result?$result->fetch_assoc():null;
    if($result)$result->free();
    $stmt->close();

    if(!$request || $request['request_status']!=='pending'){
        changes_flash('danger','คำขอนี้ไม่ได้อยู่ในสถานะรอพิจารณาแล้ว');
        changes_redirect('');
    }

    if($action==='withdraw'){
        if((int)$request['requested_by']!==$userId && !qa_user_has_role('admin')){
            http_response_code(403);
            die('คุณไม่มีสิทธิ์ถอนคำขอนี้');
        }

        $currentStatus=$request['current_status_code'];
        $stmt=$db->prepare(
            "UPDATE qa_project_change_requests
             SET request_status='withdrawn',
                 review_comment='ผู้ขอถอนคำขอ',
                 reviewed_at=NOW(),
                 resolved_status_code=?
             WHERE request_id=? AND request_status='pending'"
        );
        $stmt->bind_param('si',$currentStatus,$requestId);
        if($stmt->execute()){
            $stmt->close();
            qa_project_add_comment(
                (int)$request['project_id'],
                'correction',
                'change_withdrawn',
                'ถอนคำขอ #'.$requestId.' '.qa_project_change_type_label($request['request_type']),
                $userId
            );
            qa_project_audit_action(
                'withdraw_project_change_request',
                'qa_project_change_requests',
                $requestId,
                array('request_status'=>'pending'),
                array('request_status'=>'withdrawn')
            );
            changes_flash('success','ถอนคำขอเรียบร้อยแล้ว');
        }else{
            $err=$stmt->error;
            $stmt->close();
            changes_flash('danger','ไม่สามารถถอนคำขอได้: '.$err);
        }
        changes_redirect('');
    }

    if(!$canReview){
        http_response_code(403);
        die('เฉพาะผู้อำนวยการหรือผู้ดูแลระบบที่พิจารณาคำขอได้');
    }

    if(!in_array($action,array('approve','reject'),true)){
        changes_flash('danger','คำสั่งไม่ถูกต้อง');
        changes_redirect('id='.$requestId);
    }

    if($action==='reject' && $reviewComment===''){
        changes_flash('danger','กรุณาระบุเหตุผลที่ไม่อนุมัติคำขอ');
        changes_redirect('id='.$requestId);
    }

    /* Pending request freezes workflow, so status drift should not occur. */
    if($request['current_status_code']!==$request['previous_status_code']){
        changes_flash(
            'danger',
            'สถานะโครงการเปลี่ยนจาก '.$request['previous_status_code'].' เป็น '.$request['current_status_code'].
            ' หลังสร้างคำขอ กรุณาไม่อนุมัติคำขอเดิมและตรวจสอบโครงการก่อน'
        );
        changes_redirect('id='.$requestId);
    }

    $projectId=(int)$request['project_id'];
    $oldStatus=$request['current_status_code'];

    $db->autocommit(false);
    try{
        if($action==='reject'){
            $stmt=$db->prepare(
                "UPDATE qa_project_change_requests
                 SET request_status='rejected',reviewed_by=?,review_comment=?,reviewed_at=NOW(),
                     resolved_status_code=?
                 WHERE request_id=? AND request_status='pending'"
            );
            $stmt->bind_param('issi',$userId,$reviewComment,$oldStatus,$requestId);
            if(!$stmt->execute())throw new Exception('ไม่สามารถบันทึกผลไม่อนุมัติคำขอได้');
            $stmt->close();

            qa_project_add_comment(
                $projectId,'correction','change_rejected',
                'ไม่อนุมัติคำขอ #'.$requestId.': '.$reviewComment,$userId
            );

            $db->commit();
            $db->autocommit(true);

            qa_project_audit_action(
                'reject_project_change_request',
                'qa_project_change_requests',
                $requestId,
                array('request_status'=>'pending'),
                array('request_status'=>'rejected','review_comment'=>$reviewComment)
            );

            changes_flash('success','บันทึกผล “ไม่อนุมัติคำขอ” เรียบร้อยแล้ว');
            changes_redirect('');
        }

        $resolvedStatus=$oldStatus;
        $historyText='';
        $commentText='';

        if($request['request_type']==='cancellation'){
            if(!in_array($oldStatus,array(
                'DRAFT','REVISION','SUBMITTED','PLAN_REVIEW','BUDGET_REVIEW',
                'PENDING_APPROVAL','APPROVED','IN_PROGRESS','WAITING_REPORT'
            ),true)){
                throw new Exception('สถานะปัจจุบันไม่สามารถยกเลิกโครงการได้');
            }

            $resolvedStatus='CANCELLED';

            $stmt=$db->prepare("UPDATE qa_projects SET status_code='CANCELLED' WHERE project_id=?");
            $stmt->bind_param('i',$projectId);
            if(!$stmt->execute())throw new Exception('ไม่สามารถเปลี่ยนสถานะโครงการเป็นยกเลิกได้');
            $stmt->close();

            $cancelComment='โครงการถูกยกเลิกตามคำขอ #'.$requestId;
            if($reviewComment!=='')$cancelComment.=': '.$reviewComment;

            $stmt=$db->prepare(
                "UPDATE qa_project_approvals
                 SET decision_status='cancelled',
                     approver_user_id=?,
                     decision_comment=?,
                     decision_at=NOW()
                 WHERE project_id=? AND decision_status='pending'"
            );
            $stmt->bind_param('isi',$userId,$cancelComment,$projectId);
            if(!$stmt->execute())throw new Exception('ไม่สามารถปิดรายการอนุมัติที่ค้างอยู่ได้');
            $stmt->close();

            if(!qa_project_add_history(
                $projectId,$oldStatus,'CANCELLED',$userId,
                'อนุมัติยกเลิกโครงการตามคำขอ #'.$requestId.': '.$request['reason']
            )){
                throw new Exception('ไม่สามารถบันทึกประวัติการยกเลิกโครงการได้');
            }

            $historyText='ยกเลิกโครงการ';
            $commentText='อนุมัติยกเลิกโครงการตามคำขอ #'.$requestId.
                ' | เหตุผล: '.$request['reason'].
                ($reviewComment!==''?' | ความเห็นผู้อนุมัติ: '.$reviewComment:'');
        }elseif($request['request_type']==='correction'){
            if($request['resolution_mode']==='reopen_revision'){
                if(!in_array($oldStatus,array(
                    'SUBMITTED','PLAN_REVIEW','BUDGET_REVIEW','PENDING_APPROVAL','APPROVED'
                ),true)){
                    throw new Exception('สถานะปัจจุบันไม่รองรับการเปิดกลับ Revision');
                }

                if(qa_project_change_has_execution_data($projectId)){
                    throw new Exception('ตรวจพบข้อมูลการดำเนินงานแล้ว จึงไม่สามารถเปิดกลับ Revision ได้ ต้องใช้บันทึกแก้ไขย้อนหลังแทน');
                }

                $resolvedStatus='REVISION';
                $stmt=$db->prepare(
                    "UPDATE qa_projects
                     SET status_code='REVISION',approved_at=NULL
                     WHERE project_id=?"
                );
                $stmt->bind_param('i',$projectId);
                if(!$stmt->execute())throw new Exception('ไม่สามารถเปิดโครงการกลับมาแก้ไขได้');
                $stmt->close();

                if(!qa_project_reset_approvals($projectId)){
                    throw new Exception('ไม่สามารถ Reset Workflow สำหรับการอนุมัติใหม่ได้');
                }

                if(!qa_project_add_history(
                    $projectId,$oldStatus,'REVISION',$userId,
                    'อนุมัติคำขอแก้ไข #'.$requestId.' ให้เปิดกลับ Revision: '.$request['reason']
                )){
                    throw new Exception('ไม่สามารถบันทึกประวัติ Revision ได้');
                }

                $historyText='เปิดกลับ Revision';
                $commentText='อนุมัติคำขอแก้ไข #'.$requestId.
                    ' และเปิดโครงการกลับ Revision'.
                    ' | ต้องแก้: '.$request['change_summary'].
                    ($reviewComment!==''?' | ความเห็นผู้อนุมัติ: '.$reviewComment:'');
            }else{
                /*
                 * For projects already in execution / result stages:
                 * preserve original data and create an approved amendment record.
                 */
                $resolvedStatus=$oldStatus;
                $historyText='รับรองบันทึกแก้ไขย้อนหลัง';
                $commentText='Correction/Amendment ที่รับรองแล้ว #'.$requestId.
                    ' | หมวด: '.qa_project_change_category_label($request['request_category']).
                    ' | เหตุผล: '.$request['reason'].
                    ' | ข้อมูลแก้ไข: '.$request['change_summary'].
                    ($reviewComment!==''?' | ความเห็นผู้อนุมัติ: '.$reviewComment:'');
            }
        }else{
            throw new Exception('ประเภทคำขอไม่ถูกต้อง');
        }

        $stmt=$db->prepare(
            "UPDATE qa_project_change_requests
             SET request_status='approved',reviewed_by=?,review_comment=NULLIF(?,''),reviewed_at=NOW(),
                 resolved_status_code=?
             WHERE request_id=? AND request_status='pending'"
        );
        $stmt->bind_param('issi',$userId,$reviewComment,$resolvedStatus,$requestId);
        if(!$stmt->execute())throw new Exception('ไม่สามารถบันทึกผลอนุมัติคำขอได้');
        $stmt->close();

        if($commentText!==''){
            if(!qa_project_add_comment(
                $projectId,'correction',
                $request['request_type']==='cancellation'?'cancellation_approved':'correction_approved',
                $commentText,$userId
            )){
                throw new Exception('ไม่สามารถบันทึกหมายเหตุการแก้ไข/ยกเลิกได้');
            }
        }

        $db->commit();
        $db->autocommit(true);

        qa_project_audit_action(
            'approve_project_change_request',
            'qa_project_change_requests',
            $requestId,
            array(
                'request_status'=>'pending',
                'project_status'=>$oldStatus
            ),
            array(
                'request_status'=>'approved',
                'project_status'=>$resolvedStatus,
                'resolution_mode'=>$request['resolution_mode'],
                'review_comment'=>$reviewComment
            )
        );

        changes_flash(
            'success',
            $historyText.'เรียบร้อยแล้ว'.
            ($resolvedStatus==='REVISION'?' เจ้าของโครงการสามารถแก้ไขและส่งเสนอใหม่ได้':'')
        );
        changes_redirect('');

    }catch(Exception $e){
        $db->rollback();
        $db->autocommit(true);
        changes_flash('danger','ยกเลิกการดำเนินการทั้งหมด: '.$e->getMessage());
        changes_redirect('id='.$requestId);
    }
}

/* Flash */
$message='';
$messageType='success';
if(isset($_SESSION['qa_change_flash'])&&is_array($_SESSION['qa_change_flash'])){
    $messageType=isset($_SESSION['qa_change_flash']['type'])?$_SESSION['qa_change_flash']['type']:'success';
    $message=isset($_SESSION['qa_change_flash']['text'])?$_SESSION['qa_change_flash']['text']:'';
    unset($_SESSION['qa_change_flash']);
}

$filterStatus=isset($_GET['status'])?trim($_GET['status']):'';
$filterType=isset($_GET['type'])?trim($_GET['type']):'';
$search=isset($_GET['q'])?trim($_GET['q']):'';

$where=array();
$where[]='p.year_id='.(int)$yearId;

if(in_array($filterStatus,array('pending','approved','rejected','withdrawn'),true)){
    $where[]="r.request_status='".$db->real_escape_string($filterStatus)."'";
}else{
    $filterStatus='';
}
if(in_array($filterType,array('correction','cancellation'),true)){
    $where[]="r.request_type='".$db->real_escape_string($filterType)."'";
}else{
    $filterType='';
}
if($search!==''){
    $esc=$db->real_escape_string($search);
    $where[]="(p.project_code LIKE '%".$esc."%' OR p.project_name LIKE '%".$esc."%' OR r.reason LIKE '%".$esc."%')";
}

/* Access scope for ordinary users */
if(!$canReview){
    $scope=array("r.requested_by=".$userId,"p.owner_user_id=".$userId);
    if(qa_user_has_role('division_head')){
        $profile=qa_project_user_profile($userId);
        if($profile && (int)$profile['primary_division_id']>0){
            $scope[]='p.division_id='.(int)$profile['primary_division_id'];
        }
    }
    $where[]='('.implode(' OR ',$scope).')';
}

$rows=array();
$sql="SELECT r.*,p.project_code,p.project_name,p.status_code AS current_status_code,
            ps.status_name AS current_status_name,d.division_name,
            CONCAT(COALESCE(req.prefix,''),COALESCE(req.first_name,''),' ',COALESCE(req.last_name,'')) AS requester_name,
            CONCAT(COALESCE(rv.prefix,''),COALESCE(rv.first_name,''),' ',COALESCE(rv.last_name,'')) AS reviewer_name
      FROM qa_project_change_requests r
      INNER JOIN qa_projects p ON p.project_id=r.project_id
      INNER JOIN qa_divisions d ON d.division_id=p.division_id
      LEFT JOIN qa_project_statuses ps ON ps.status_code=p.status_code
      LEFT JOIN qa_users req ON req.user_id=r.requested_by
      LEFT JOIN qa_users rv ON rv.user_id=r.reviewed_by
      WHERE ".implode(' AND ',$where)."
      ORDER BY
        CASE r.request_status WHEN 'pending' THEN 1 ELSE 2 END,
        r.request_id DESC";
$result=$db->query($sql);
if($result){
    while($row=$result->fetch_assoc())$rows[]=$row;
    $result->free();
}

/* Current-year counts */
$pendingCorrection=(int)qa_db_scalar(
    "SELECT COUNT(*) FROM qa_project_change_requests r
     INNER JOIN qa_projects p ON p.project_id=r.project_id
     WHERE p.year_id=".(int)$yearId." AND r.request_status='pending' AND r.request_type='correction'",0
);
$pendingCancel=(int)qa_db_scalar(
    "SELECT COUNT(*) FROM qa_project_change_requests r
     INNER JOIN qa_projects p ON p.project_id=r.project_id
     WHERE p.year_id=".(int)$yearId." AND r.request_status='pending' AND r.request_type='cancellation'",0
);
$approvedCount=(int)qa_db_scalar(
    "SELECT COUNT(*) FROM qa_project_change_requests r
     INNER JOIN qa_projects p ON p.project_id=r.project_id
     WHERE p.year_id=".(int)$yearId." AND r.request_status='approved'",0
);
$allCount=(int)qa_db_scalar(
    "SELECT COUNT(*) FROM qa_project_change_requests r
     INNER JOIN qa_projects p ON p.project_id=r.project_id
     WHERE p.year_id=".(int)$yearId,0
);

/* Selected request */
$selectedId=isset($_GET['id'])?(int)$_GET['id']:0;
$selected=null;
if($selectedId>0){
    foreach($rows as $row){
        if((int)$row['request_id']===$selectedId){
            $selected=$row;
            break;
        }
    }
    if(!$selected){
        $stmt=$db->prepare(
            "SELECT r.*,p.project_code,p.project_name,p.status_code AS current_status_code,
                    ps.status_name AS current_status_name,d.division_name,
                    CONCAT(COALESCE(req.prefix,''),COALESCE(req.first_name,''),' ',COALESCE(req.last_name,'')) AS requester_name,
                    CONCAT(COALESCE(rv.prefix,''),COALESCE(rv.first_name,''),' ',COALESCE(rv.last_name,'')) AS reviewer_name
             FROM qa_project_change_requests r
             INNER JOIN qa_projects p ON p.project_id=r.project_id
             INNER JOIN qa_divisions d ON d.division_id=p.division_id
             LEFT JOIN qa_project_statuses ps ON ps.status_code=p.status_code
             LEFT JOIN qa_users req ON req.user_id=r.requested_by
             LEFT JOIN qa_users rv ON rv.user_id=r.reviewed_by
             WHERE r.request_id=? LIMIT 1"
        );
        $stmt->bind_param('i',$selectedId);
        $stmt->execute();
        $result=$stmt->get_result();
        $candidate=$result?$result->fetch_assoc():null;
        if($result)$result->free();
        $stmt->close();

        if($candidate){
            $projectForAccess=qa_project_change_load_project((int)$candidate['project_id']);
            if($canReview || qa_project_change_can_request($projectForAccess) || (int)$candidate['requested_by']===$userId){
                $selected=$candidate;
            }
        }
    }
}

require QA_ROOT.'/includes/header.php';
?>

<?php if($message!==''): ?>
<div class="alert <?= $messageType==='danger'?'alert-danger':'alert-success' ?>" style="margin-bottom:16px"><?= h($message) ?></div>
<?php endif; ?>

<div class="grid grid-4">
    <div class="card"><div class="metric-label">รอแก้ไข</div><div class="metric-value"><?= h($pendingCorrection) ?></div><div class="metric-note">คำขอ Correction</div></div>
    <div class="card"><div class="metric-label">รอยกเลิก</div><div class="metric-value"><?= h($pendingCancel) ?></div><div class="metric-note">Cancellation</div></div>
    <div class="card"><div class="metric-label">อนุมัติคำขอแล้ว</div><div class="metric-value"><?= h($approvedCount) ?></div><div class="metric-note">ปีงบประมาณ <?= h($yearBE) ?></div></div>
    <div class="card"><div class="metric-label">คำขอทั้งหมด</div><div class="metric-value"><?= h($allCount) ?></div><div class="metric-note">ปีงบประมาณปัจจุบัน</div></div>
</div>

<div class="card" style="margin-top:16px">
    <form method="get" class="form-grid">
        <div class="form-group">
            <label>ประเภท</label>
            <select name="type">
                <option value="">ทั้งหมด</option>
                <option value="correction" <?= $filterType==='correction'?'selected':'' ?>>ขอแก้ไขโครงการ</option>
                <option value="cancellation" <?= $filterType==='cancellation'?'selected':'' ?>>ขอยกเลิกโครงการ</option>
            </select>
        </div>
        <div class="form-group">
            <label>สถานะคำขอ</label>
            <select name="status">
                <option value="">ทั้งหมด</option>
                <option value="pending" <?= $filterStatus==='pending'?'selected':'' ?>>รอพิจารณา</option>
                <option value="approved" <?= $filterStatus==='approved'?'selected':'' ?>>อนุมัติคำขอ</option>
                <option value="rejected" <?= $filterStatus==='rejected'?'selected':'' ?>>ไม่อนุมัติ</option>
                <option value="withdrawn" <?= $filterStatus==='withdrawn'?'selected':'' ?>>ถอนคำขอ</option>
            </select>
        </div>
        <div class="form-group">
            <label>ค้นหา</label>
            <input name="q" value="<?= h($search) ?>" placeholder="รหัส / ชื่อโครงการ / เหตุผล">
        </div>
        <div class="form-group" style="align-self:end">
            <button class="btn btn-primary" type="submit">ค้นหา</button>
            <a class="btn" href="<?= h(qa_url('projects/changes.php')) ?>">ล้าง</a>
        </div>
    </form>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>รายการคำขอ</h2><p>คำขอที่รอพิจารณาจะหยุด Workflow และการบันทึกความก้าวหน้าของโครงการนั้นชั่วคราว</p></div>
        <span class="badge badge-blue"><?= h(count($rows)) ?> รายการ</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>โครงการ</th><th>ประเภท</th><th>ผู้ขอ</th><th>สถานะเดิม</th><th>วิธีดำเนินการ</th><th>สถานะคำขอ</th><th>วันที่</th><th></th></tr></thead>
            <tbody>
            <?php if(empty($rows)): ?>
                <tr><td colspan="9" class="empty-cell">ยังไม่มีคำขอตามเงื่อนไขที่เลือก</td></tr>
            <?php else: ?>
                <?php foreach($rows as $r): ?>
                <tr>
                    <td><strong>#<?= h($r['request_id']) ?></strong></td>
                    <td><strong><?= h($r['project_code']) ?></strong><div><?= h($r['project_name']) ?></div></td>
                    <td><?= h(qa_project_change_type_label($r['request_type'])) ?><div class="subtle"><?= h(qa_project_change_category_label($r['request_category'])) ?></div></td>
                    <td><?= h(trim($r['requester_name'])) ?></td>
                    <td><?= h($r['previous_status_code']) ?></td>
                    <td><?= h(qa_project_change_resolution_label($r['resolution_mode'])) ?></td>
                    <td><span class="badge <?= h(qa_project_change_status_badge($r['request_status'])) ?>"><?= h(qa_project_change_status_label($r['request_status'])) ?></span></td>
                    <td><?= h($r['requested_at']) ?></td>
                    <td><a class="btn btn-sm" href="<?= h(qa_url('projects/changes.php?id='.$r['request_id'])) ?>">เปิด</a></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if($selected): ?>
<div class="card change-review-card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>คำขอ #<?= h($selected['request_id']) ?> — <?= h(qa_project_change_type_label($selected['request_type'])) ?></h2>
            <p><?= h($selected['project_code'].' — '.$selected['project_name']) ?></p>
        </div>
        <span class="badge <?= h(qa_project_change_status_badge($selected['request_status'])) ?>"><?= h(qa_project_change_status_label($selected['request_status'])) ?></span>
    </div>

    <div class="grid grid-2">
        <div>
            <table><tbody>
                <tr><th>ผู้ขอ</th><td><?= h(trim($selected['requester_name'])) ?></td></tr>
                <tr><th>ฝ่าย</th><td><?= h($selected['division_name']) ?></td></tr>
                <tr><th>สถานะตอนยื่น</th><td><?= h($selected['previous_status_code']) ?></td></tr>
                <tr><th>สถานะปัจจุบัน</th><td><?= h($selected['current_status_name']?$selected['current_status_name']:$selected['current_status_code']) ?></td></tr>
                <tr><th>หมวด</th><td><?= h(qa_project_change_category_label($selected['request_category'])) ?></td></tr>
                <tr><th>ผลกระทบ</th><td><?= h($selected['impact_level']==='minor'?'เล็กน้อย':'สาระสำคัญ') ?></td></tr>
                <tr><th>วิธีดำเนินการ</th><td><strong><?= h(qa_project_change_resolution_label($selected['resolution_mode'])) ?></strong></td></tr>
            </tbody></table>
        </div>
        <div>
            <h3>เหตุผล</h3>
            <div class="quality-long-text"><?= nl2br(h($selected['reason'])) ?></div>
            <?php if($selected['change_summary']): ?>
            <h3>ข้อมูลที่ต้องการแก้</h3>
            <div class="amendment-box"><?= nl2br(h($selected['change_summary'])) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="action-bar" style="margin-top:14px">
        <a class="btn" href="<?= h(qa_url('projects/view.php?id='.$selected['project_id'])) ?>">เปิดโครงการ</a>
    </div>

    <?php if($selected['request_status']==='pending'): ?>
        <?php if((int)$selected['requested_by']===$userId || qa_user_has_role('admin')): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('ยืนยันถอนคำขอนี้?');">
            <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
            <input type="hidden" name="request_id" value="<?= h($selected['request_id']) ?>">
            <input type="hidden" name="action" value="withdraw">
            <button class="btn" type="submit">ถอนคำขอ</button>
        </form>
        <?php endif; ?>

        <?php if($canReview): ?>
        <form method="post" class="master-form">
            <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
            <input type="hidden" name="request_id" value="<?= h($selected['request_id']) ?>">
            <div class="form-group">
                <label>ความเห็นผู้พิจารณา</label>
                <textarea name="review_comment" placeholder="เหตุผล/เงื่อนไขในการอนุมัติ หรือเหตุผลกรณีไม่อนุมัติ"></textarea>
            </div>

            <?php if((int)$selected['requested_by']===$userId): ?>
            <div class="notice">บัญชีนี้เป็นทั้งผู้ยื่นคำขอและผู้มีสิทธิ์พิจารณา ควรใช้ผู้พิจารณาคนอื่นเมื่อกระบวนงานจริงของโรงเรียนกำหนดให้แยกหน้าที่</div>
            <?php endif; ?>

            <?php if($selected['request_type']==='cancellation'): ?>
            <div class="alert alert-danger" style="margin-top:12px">
                การอนุมัติจะเปลี่ยนสถานะโครงการเป็น <strong>CANCELLED</strong> แต่จะไม่ลบ Workflow, การเบิกจ่าย, KPI, หลักฐาน หรือประวัติเดิม
            </div>
            <?php elseif($selected['resolution_mode']==='reopen_revision'): ?>
            <div class="notice" style="margin-top:12px">
                การอนุมัติจะเปลี่ยนโครงการเป็น <strong>REVISION</strong>, Reset ลำดับอนุมัติ และเมื่อเจ้าของส่งใหม่จะเริ่มตรวจ งานแผน → งานงบประมาณ → ผู้อำนวยการ อีกครั้ง
            </div>
            <?php else: ?>
            <div class="notice" style="margin-top:12px">
                การอนุมัติจะสร้าง <strong>Correction/Amendment ที่รับรองแล้ว</strong> โดยสถานะและข้อมูลต้นฉบับของโครงการจะไม่ถูกเขียนทับ
            </div>
            <?php endif; ?>

            <div class="action-bar" style="margin-top:12px;margin-bottom:0">
                <button class="btn btn-primary" type="submit" name="action" value="approve" onclick="return confirm('ยืนยันอนุมัติคำขอนี้?');">อนุมัติคำขอ</button>
                <button class="btn btn-danger" type="submit" name="action" value="reject" onclick="return confirm('ยืนยันไม่อนุมัติคำขอนี้?');">ไม่อนุมัติคำขอ</button>
            </div>
        </form>
        <?php endif; ?>
    <?php else: ?>
        <div class="grid grid-2" style="margin-top:14px">
            <div><strong>ผู้พิจารณา</strong><div><?= $selected['reviewer_name']?h(trim($selected['reviewer_name'])):'-' ?></div></div>
            <div><strong>วันที่พิจารณา</strong><div><?= $selected['reviewed_at']?h($selected['reviewed_at']):'-' ?></div></div>
        </div>
        <?php if($selected['review_comment']): ?>
        <h3>ความเห็นผู้พิจารณา</h3>
        <div class="quality-long-text"><?= nl2br(h($selected['review_comment'])) ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require QA_ROOT.'/includes/footer.php'; ?>
