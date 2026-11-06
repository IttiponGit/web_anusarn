<?php
$pageTitle='กิจกรรมภายใต้โครงการ';
$activeMenu='my-activities';
require_once __DIR__.'/../includes/bootstrap.php';
qa_require_login();

$db=qa_db();$user=qa_current_user();$userId=$user?(int)$user['user_id']:0;$currentYear=qa_current_year_row();$yearBE=$currentYear?(int)$currentYear['fiscal_year_be']:0;
$activityId=isset($_GET['id'])?(int)$_GET['id']:0;
$activity=qa_activity_load($activityId);
if(!$activity){http_response_code(404);die('ไม่พบกิจกรรม');}
if(!qa_activity_can_view($activity)){http_response_code(403);die('คุณไม่มีสิทธิ์ดูข้อมูลกิจกรรมนี้');}

$activityNo=qa_activity_display_no($activity);
$childCount=(int)$activity['child_count'];
$pageHeading=($activityNo!==''?$activityNo.' — ':'').$activity['activity_name'];
$pageDescription=(qa_activity_is_child($activity)?'กิจกรรมย่อย':'กิจกรรมหลัก').' ภายใต้ '.$activity['project_code'].' — '.$activity['project_name'];
$breadcrumbs=array(array('label'=>'กิจกรรมที่รับผิดชอบ','url'=>qa_url('activities/index.php')));
if(qa_activity_is_child($activity)){
    $breadcrumbs[]=array('label'=>$activity['parent_activity_no'].' — '.$activity['parent_activity_name'],'url'=>qa_url('activities/view.php?id='.$activity['parent_activity_id']));
}
$breadcrumbs[]=array('label'=>$activity['activity_name']);
$canReport=qa_activity_can_report($activity);
$canDirectReport=$canReport && $childCount===0;
$canAssign=qa_activity_can_manage_project($activity);

function actv_redirect($activityId,$anchor=''){
    $url=qa_url('activities/view.php?id='.(int)$activityId);
    if($anchor!=='')$url.='#'.$anchor;
    header('Location: '.$url);exit;
}
function actv_date($v){$v=trim((string)$v);if($v==='')return false;$p=explode('-',$v);return count($p)===3&&checkdate((int)$p[1],(int)$p[2],(int)$p[0])?$v:false;}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $token=qa_request_scalar($_POST,'csrf_token');
    if(!qa_verify_csrf($token)){http_response_code(400);die('CSRF token ไม่ถูกต้อง');}
    $action=trim(qa_request_scalar($_POST,'action'));

    if($action==='add_progress'){
        if(!$canReport){http_response_code(403);die('คุณไม่มีสิทธิ์รายงานกิจกรรมนี้');}
        if($childCount>0){
            $_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>'กิจกรรมหลักนี้มีกิจกรรมย่อยแล้ว ความก้าวหน้าจะคำนวณอัตโนมัติจากกิจกรรมย่อย');
            actv_redirect($activityId,'progress');
        }
        if(!in_array($activity['project_status'],array('APPROVED','IN_PROGRESS','WAITING_REPORT'),true)){
            $_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>'สถานะโครงการไม่อนุญาตให้บันทึกรายงานกิจกรรม');actv_redirect($activityId);
        }
        $date=actv_date(qa_request_scalar($_POST,'report_date'));
        $progress=str_replace(',','',trim(qa_request_scalar($_POST,'progress_percent')));
        $summary=trim(qa_request_scalar($_POST,'activity_summary'));
        $output=trim(qa_request_scalar($_POST,'output_summary'));
        $outcome=trim(qa_request_scalar($_POST,'outcome_summary'));
        $problem=trim(qa_request_scalar($_POST,'problem_summary'));
        $next=trim(qa_request_scalar($_POST,'next_step_summary'));
        $errors=array();
        if($date===false)$errors[]='กรุณาระบุวันที่รายงาน';
        if($progress===''||!is_numeric($progress)||(float)$progress<0||(float)$progress>100)$errors[]='ความก้าวหน้าต้องอยู่ระหว่าง 0-100';
        if($summary==='')$errors[]='กรุณาระบุสรุปการดำเนินกิจกรรม';
        if(!empty($errors)){$_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>implode(' / ',$errors));actv_redirect($activityId,'progress');}
        $progress=(float)$progress;
        $reportNo=(int)qa_db_scalar("SELECT COALESCE(MAX(report_no),0)+1 FROM qa_activity_progress_reports WHERE activity_id=".(int)$activityId,1);
        $newStatus=$progress>=100?'completed':($progress>0?'in_progress':'planned');

        $db->autocommit(false);
        try{
            $stmt=$db->prepare(
                "INSERT INTO qa_activity_progress_reports
                 (activity_id,report_no,report_date,progress_percent,activity_summary,output_summary,outcome_summary,problem_summary,next_step_summary,reported_by)
                 VALUES (?,?,?,?,?,NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),?)"
            );
            $stmt->bind_param('iisdsssssi',$activityId,$reportNo,$date,$progress,$summary,$output,$outcome,$problem,$next,$userId);
            if(!$stmt->execute())throw new Exception('ไม่สามารถบันทึกรายงานกิจกรรมได้: '.$stmt->error);
            $reportId=$stmt->insert_id;$stmt->close();

            if($newStatus==='completed'){
                $stmt=$db->prepare("UPDATE qa_project_activities SET progress_percent=?,status=?,output_summary=NULLIF(?,''),outcome_summary=NULLIF(?,''),last_reported_at=NOW(),completed_at=NOW() WHERE activity_id=?");
                $stmt->bind_param('dsssi',$progress,$newStatus,$output,$outcome,$activityId);
            }else{
                $stmt=$db->prepare("UPDATE qa_project_activities SET progress_percent=?,status=?,output_summary=NULLIF(?,''),outcome_summary=NULLIF(?,''),last_reported_at=NOW(),completed_at=NULL WHERE activity_id=?");
                $stmt->bind_param('dsssi',$progress,$newStatus,$output,$outcome,$activityId);
            }
            if(!$stmt->execute())throw new Exception('ไม่สามารถอัปเดตสถานะกิจกรรมได้');
            $stmt->close();

            if(!qa_activity_recalculate_ancestors($activityId))throw new Exception('ไม่สามารถคำนวณความก้าวหน้ากิจกรรมหลักได้');

            $db->commit();$db->autocommit(true);
            qa_project_audit_action('add_activity_progress','qa_activity_progress_reports',$reportId,null,array('activity_id'=>$activityId,'progress_percent'=>$progress));
            $_SESSION['qa_activity_flash']=array('type'=>'success','text'=>'บันทึกรายงานกิจกรรมครั้งที่ '.$reportNo.' เรียบร้อยแล้ว');
        }catch(Throwable $e){$db->rollback();$db->autocommit(true);$_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>$e->getMessage());}
        actv_redirect($activityId,'progress');
    }

    if($action==='add_evidence'){
        if(!$canReport){http_response_code(403);die('คุณไม่มีสิทธิ์เพิ่มหลักฐานกิจกรรมนี้');}
        $title=trim(qa_request_scalar($_POST,'title'));
        $description=trim(qa_request_scalar($_POST,'description'));
        $documentNo=trim(qa_request_scalar($_POST,'document_no'));
        $evidenceDate=actv_date(qa_request_scalar($_POST,'evidence_date'));
        $externalUrl=trim(qa_request_scalar($_POST,'external_url'));
        $indicatorIds=isset($_POST['indicator_ids'])&&is_array($_POST['indicator_ids'])?array_map('intval',$_POST['indicator_ids']):array();

        if($title===''||$evidenceDate===false){$_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>'กรุณาระบุชื่อและวันที่หลักฐาน');actv_redirect($activityId,'evidence');}
        if($externalUrl!==''&&!preg_match('#^https?://#i',$externalUrl)){$_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>'URL ต้องขึ้นต้นด้วย http:// หรือ https://');actv_redirect($activityId,'evidence');}

        $file=qa_uploaded_file_entry($_FILES,'evidence_file');
        if(isset($_FILES['evidence_file'])&&$file===null){$_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>'ข้อมูลไฟล์อัปโหลดไม่ถูกต้อง');actv_redirect($activityId,'evidence');}
        $hasFile=$file!==null&&(int)$file['error']!==UPLOAD_ERR_NO_FILE;
        if(!$hasFile&&$externalUrl===''){$_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>'กรุณาแนบไฟล์หรือระบุ URL อย่างน้อยหนึ่งอย่าง');actv_redirect($activityId,'evidence');}

        $filePath='';$mimeType='';$fileSize=null;
        if($hasFile){
            if((int)$file['error']!==UPLOAD_ERR_OK){$_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>'อัปโหลดไฟล์ไม่สำเร็จ');actv_redirect($activityId,'evidence');}
            if(!is_uploaded_file((string)$file['tmp_name'])){$_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>'ไฟล์อัปโหลดไม่ถูกต้อง');actv_redirect($activityId,'evidence');}
            if((int)$file['size']>10*1024*1024){$_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>'ไฟล์ต้องไม่เกิน 10 MB');actv_redirect($activityId,'evidence');}
            $ext=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));
            if(!in_array($ext,array('pdf','jpg','jpeg','png','webp','doc','docx','xls','xlsx'),true)){$_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>'รองรับ PDF รูปภาพ Word และ Excel เท่านั้น');actv_redirect($activityId,'evidence');}
            $safeCode=preg_replace('/[^A-Za-z0-9_-]/','_',$activity['project_code']);
            $dirRelative='uploads/evidences/'.$yearBE.'/'.$safeCode;
            $dirAbsolute=QA_ROOT.'/'.$dirRelative;
            if(!is_dir($dirAbsolute)&&!mkdir($dirAbsolute,0755,true)){$_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>'ไม่สามารถสร้างโฟลเดอร์หลักฐานได้');actv_redirect($activityId,'evidence');}
            $fileName=date('YmdHis').'_'.sha1(uniqid(mt_rand(),true)).'.'.$ext;
            $absolute=$dirAbsolute.'/'.$fileName;
            if(!move_uploaded_file($file['tmp_name'],$absolute)){$_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>'บันทึกไฟล์ไม่สำเร็จ');actv_redirect($activityId,'evidence');}
            $filePath=$dirRelative.'/'.$fileName;$fileSize=(int)$file['size'];
            if(function_exists('finfo_open')){$fi=finfo_open(FILEINFO_MIME_TYPE);if($fi){$detectedMime=finfo_file($fi,$absolute);$mimeType=is_string($detectedMime)?$detectedMime:'';finfo_close($fi);}}
            if($mimeType!==''&&!qa_upload_mime_allowed($ext,$mimeType)){@unlink($absolute);$_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>'ชนิดไฟล์ไม่ตรงกับนามสกุล');actv_redirect($activityId,'evidence');}
            if($mimeType==='')$mimeType=isset($file['type'])&&is_scalar($file['type'])?(string)$file['type']:'application/octet-stream';
        }

        $code=qa_activity_evidence_code($yearBE);$fileSizeDb=$fileSize===null?null:$fileSize;
        $db->autocommit(false);
        try{
            $stmt=$db->prepare(
                "INSERT INTO qa_evidences
                 (evidence_code,plan_id,project_id,activity_id,progress_report_id,evidence_type,title,description,document_no,evidence_date,file_path,external_url,mime_type,file_size,is_public,uploaded_by)
                 VALUES (?,?,?,?,NULL,'activity',?,NULLIF(?,''),NULLIF(?,''),?,NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),?,0,?)"
            );
            $stmt->bind_param('siiisssssssii',$code,$activity['plan_id'],$activity['project_id'],$activityId,$title,$description,$documentNo,$evidenceDate,$filePath,$externalUrl,$mimeType,$fileSizeDb,$userId);
            if(!$stmt->execute())throw new Exception('ไม่สามารถบันทึกหลักฐานได้: '.$stmt->error);
            $evidenceId=$stmt->insert_id;$stmt->close();
            foreach($indicatorIds as $indicatorId){
                if($indicatorId<=0)continue;
                $stmt=$db->prepare("INSERT IGNORE INTO qa_evidence_indicator_links(evidence_id,indicator_id) VALUES (?,?)");
                $stmt->bind_param('ii',$evidenceId,$indicatorId);
                if(!$stmt->execute())throw new Exception('เชื่อมหลักฐานกับตัวชี้วัดไม่สำเร็จ');
                $stmt->close();
            }
            $db->commit();$db->autocommit(true);
            qa_project_audit_action('add_activity_evidence','qa_evidences',$evidenceId,null,array('activity_id'=>$activityId,'title'=>$title));
            $_SESSION['qa_activity_flash']=array('type'=>'success','text'=>'เพิ่มหลักฐาน '.$code.' เรียบร้อยแล้ว');
        }catch(Throwable $e){$db->rollback();$db->autocommit(true);if($filePath&&file_exists(QA_ROOT.'/'.$filePath))@unlink(QA_ROOT.'/'.$filePath);$_SESSION['qa_activity_flash']=array('type'=>'danger','text'=>$e->getMessage());}
        actv_redirect($activityId,'evidence');
    }
}

$activity=qa_activity_load($activityId);
$reports=array();
$stmt=$db->prepare(
    "SELECT r.*,CONCAT(COALESCE(u.prefix,''),COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS reporter_name
     FROM qa_activity_progress_reports r LEFT JOIN qa_users u ON u.user_id=r.reported_by
     WHERE r.activity_id=? ORDER BY r.report_no DESC,r.activity_report_id DESC"
);
$stmt->bind_param('i',$activityId);$stmt->execute();$result=$stmt->get_result();if($result){while($r=$result->fetch_assoc())$reports[]=$r;$result->free();}$stmt->close();

$budgetItems=array();
$stmt=$db->prepare(
    "SELECT bi.*,COALESCE((SELECT SUM(e.amount) FROM qa_expenditures e WHERE e.budget_item_id=bi.budget_item_id AND e.payment_status='paid'),0) AS spent
     FROM qa_project_budget_items bi WHERE bi.activity_id=? ORDER BY bi.sort_order,bi.budget_item_id"
);
$stmt->bind_param('i',$activityId);$stmt->execute();$result=$stmt->get_result();if($result){while($r=$result->fetch_assoc())$budgetItems[]=$r;$result->free();}$stmt->close();

$evidences=array();
$stmt=$db->prepare(
    "SELECT ev.*,GROUP_CONCAT(i.indicator_code ORDER BY i.indicator_code SEPARATOR ', ') AS indicator_codes
     FROM qa_evidences ev
     LEFT JOIN qa_evidence_indicator_links l ON l.evidence_id=ev.evidence_id
     LEFT JOIN qa_indicators i ON i.indicator_id=l.indicator_id
     WHERE ev.activity_id=? GROUP BY ev.evidence_id ORDER BY ev.evidence_date DESC,ev.evidence_id DESC"
);
$stmt->bind_param('i',$activityId);$stmt->execute();$result=$stmt->get_result();if($result){while($r=$result->fetch_assoc())$evidences[]=$r;$result->free();}$stmt->close();

$childActivities=array();
if($childCount>0){
    $stmt=$db->prepare(
        "SELECT a.*,? AS parent_activity_no,
                CONCAT(COALESCE(u.prefix,''),COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS responsible_name,
                (SELECT COUNT(*) FROM qa_evidences ev WHERE ev.activity_id=a.activity_id) AS evidence_count
         FROM qa_project_activities a
         LEFT JOIN qa_users u ON u.user_id=a.responsible_user_id
         WHERE a.parent_activity_id=?
         ORDER BY a.sort_order,a.activity_id"
    );
    $parentNo=(int)$activity['activity_no'];
    $stmt->bind_param('ii',$parentNo,$activityId);
    $stmt->execute();$res=$stmt->get_result();
    if($res){while($row=$res->fetch_assoc())$childActivities[]=$row;$res->free();}
    $stmt->close();
}

$indicators=array();
$stmt=$db->prepare(
    "SELECT i.indicator_id,i.indicator_code,i.indicator_name
     FROM qa_project_indicator_links l INNER JOIN qa_indicators i ON i.indicator_id=l.indicator_id
     WHERE l.project_id=? ORDER BY i.indicator_code"
);
$stmt->bind_param('i',$activity['project_id']);$stmt->execute();$result=$stmt->get_result();if($result){while($r=$result->fetch_assoc())$indicators[]=$r;$result->free();}$stmt->close();

$approved=0;$spent=0;foreach($budgetItems as $bi){$approved+=(float)$bi['approved_amount'];$spent+=(float)$bi['spent'];}
$message='';$messageType='success';if(isset($_SESSION['qa_activity_flash'])){$messageType=$_SESSION['qa_activity_flash']['type'];$message=$_SESSION['qa_activity_flash']['text'];unset($_SESSION['qa_activity_flash']);}

require QA_ROOT.'/includes/header.php';
?>
<?php if($message): ?><div class="alert <?= $messageType==='danger'?'alert-danger':'alert-success' ?>"><?= h($message) ?></div><?php endif; ?>
<div class="action-bar">
    <a class="btn" href="<?= h(qa_url('activities/index.php')) ?>">← กิจกรรมที่รับผิดชอบ</a>
    <a class="btn" href="<?= h(qa_url('projects/view.php?id='.$activity['project_id'])) ?>">โครงการใหญ่</a>
    <?php if($canAssign): ?><a class="btn" href="<?= h(qa_url('projects/activities.php?project_id='.$activity['project_id'])) ?>">จัดการผู้รับผิดชอบกิจกรรม</a><?php endif; ?>
</div>

<div class="grid grid-5">
    <div class="card"><div class="metric-label">สถานะ</div><div style="margin-top:10px"><span class="badge <?= h(qa_activity_status_badge($activity['status'])) ?>"><?= h(qa_activity_status_label($activity['status'])) ?></span></div></div>
    <div class="card"><div class="metric-label">ความก้าวหน้า</div><div class="metric-value"><?= h(number_format((float)$activity['progress_percent'],0)) ?>%</div></div>
    <div class="card"><div class="metric-label">งบกิจกรรม</div><div class="metric-value"><?= h(qa_money($approved)) ?></div></div>
    <div class="card"><div class="metric-label">ใช้จริง</div><div class="metric-value"><?= h(qa_money($spent)) ?></div></div>
    <div class="card"><div class="metric-label">หลักฐาน</div><div class="metric-value"><?= h(count($evidences)) ?></div></div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading"><div><h2>ขอบเขตกิจกรรม</h2><p><?= h($activity['division_name']) ?> • ผู้รับผิดชอบโครงการ <?= h(trim($activity['project_owner_name'])) ?></p></div></div>
    <div class="activity-info-grid">
        <div><span>ผู้รับผิดชอบกิจกรรม</span><strong><?= trim($activity['responsible_name'])?h(trim($activity['responsible_name'])):'ยังไม่มอบหมาย' ?></strong></div>
        <div><span>ระยะเวลา</span><strong><?= h(($activity['start_date']?$activity['start_date']:'-').' ถึง '.($activity['end_date']?$activity['end_date']:'-')) ?></strong></div>
    </div>
    <p class="project-long-text"><?= $activity['activity_description']?nl2br(h($activity['activity_description'])):'ยังไม่ได้ระบุรายละเอียดกิจกรรม' ?></p>
</div>

<?php if(qa_activity_is_child($activity)): ?>
<div class="card activity-parent-context" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>กิจกรรมหลักที่อ้างอิง</h2><p>กิจกรรมย่อยนี้เป็นส่วนหนึ่งของกิจกรรมหลักด้านล่าง</p></div>
    </div>
    <a class="activity-parent-link" href="<?= h(qa_url('activities/view.php?id='.$activity['parent_activity_id'])) ?>">
        <span class="activity-level-badge main">กิจกรรมหลัก</span>
        <strong><?= h($activity['parent_activity_no'].'. '.$activity['parent_activity_name']) ?></strong>
    </a>
</div>
<?php elseif($childCount>0): ?>
<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>กิจกรรมย่อย</h2><p>ความก้าวหน้าของกิจกรรมหลักคำนวณจากค่าเฉลี่ยของกิจกรรมย่อยที่ไม่ถูกยกเลิก</p></div>
        <span class="badge badge-blue"><?= h($childCount) ?> รายการ</span>
    </div>
    <div class="activity-project-list">
        <?php foreach($childActivities as $child): $childNo=qa_activity_display_no($child); ?>
        <a class="activity-project-row is-child" href="<?= h(qa_url('activities/view.php?id='.$child['activity_id'])) ?>">
            <span class="activity-no"><?= h($childNo) ?></span>
            <span class="activity-main">
                <strong><?= h($child['activity_name']) ?></strong>
                <small><?= trim($child['responsible_name'])?'ผู้รับผิดชอบ '.h(trim($child['responsible_name'])):'ยังไม่มอบหมาย' ?> • หลักฐาน <?= h($child['evidence_count']) ?></small>
            </span>
            <span class="activity-progress"><?= h(number_format((float)$child['progress_percent'],0)) ?>%</span>
            <span class="badge <?= h(qa_activity_status_badge($child['status'])) ?>"><?= h(qa_activity_status_label($child['status'])) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card" id="progress" style="margin-top:16px">
    <div class="section-heading"><div><h2>รายงานความก้าวหน้ากิจกรรม</h2><p><?= $childCount>0?'กิจกรรมหลักนี้คำนวณความก้าวหน้าจากกิจกรรมย่อยโดยอัตโนมัติ':'ผู้รับผิดชอบกิจกรรมกรอกเฉพาะงานของกิจกรรมนี้ ไม่ต้องแก้ข้อมูลโครงการใหญ่' ?></p></div><span class="badge badge-blue"><?= h(count($reports)) ?> ครั้ง</span></div>
    <?php if(!empty($reports)): ?><div class="table-wrap"><table><thead><tr><th>ครั้ง</th><th>วันที่</th><th>ความก้าวหน้า</th><th>สรุป</th><th>ผู้รายงาน</th></tr></thead><tbody><?php foreach($reports as $r): ?><tr><td><?= h($r['report_no']) ?></td><td><?= h($r['report_date']) ?></td><td><?= h(number_format((float)$r['progress_percent'],0)) ?>%</td><td><?= h($r['activity_summary']) ?></td><td><?= h(trim($r['reporter_name'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>

    <?php if($canReport && $childCount>0): ?>
    <div class="notice">ไม่ต้องกรอกเปอร์เซ็นต์กิจกรรมหลักซ้ำ ระบบจะคำนวณจากกิจกรรมย่อยทั้งหมด เมื่อผู้รับผิดชอบกิจกรรมย่อยบันทึกรายงาน</div>
    <?php elseif($canDirectReport && in_array($activity['project_status'],array('APPROVED','IN_PROGRESS','WAITING_REPORT'),true)): ?>
    <form method="post" class="master-form">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>"><input type="hidden" name="action" value="add_progress">
        <div class="form-grid">
            <div class="form-group"><label>วันที่รายงาน *</label><input type="date" name="report_date" required value="<?= h(date('Y-m-d')) ?>"></div>
            <div class="form-group"><label>ความก้าวหน้า (%) *</label><input type="number" min="0" max="100" step="0.01" name="progress_percent" required value="<?= h(number_format((float)$activity['progress_percent'],2,'.','')) ?>"></div>
            <div class="form-group full"><label>สรุปการดำเนินกิจกรรม *</label><textarea name="activity_summary" required></textarea></div>
            <div class="form-group full"><label>ผลผลิตที่เกิดขึ้น</label><textarea name="output_summary"></textarea><div class="help-text">สิ่งที่กิจกรรมทำได้หรือส่งมอบได้โดยตรง</div></div>
            <div class="form-group full"><label>ผลลัพธ์ที่เกิดขึ้น</label><textarea name="outcome_summary"></textarea><div class="help-text">การเปลี่ยนแปลงหรือประโยชน์ที่เกิดจากผลผลิต</div></div>
            <div class="form-group"><label>ปัญหา / อุปสรรค</label><textarea name="problem_summary"></textarea></div>
            <div class="form-group"><label>ขั้นตอนถัดไป</label><textarea name="next_step_summary"></textarea></div>
        </div>
        <button class="btn btn-primary" type="submit">บันทึกรายงานกิจกรรม</button>
    </form>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading"><div><h2>งบประมาณของกิจกรรม</h2><p>ดูรายการงบที่ผูกกับกิจกรรมนี้ ผู้รับผิดชอบกิจกรรมไม่สามารถอนุมัติหรือแก้ยอดงบจากหน้านี้</p></div></div>
    <div class="table-wrap"><table><thead><tr><th>รายการ</th><th>หมวด</th><th class="text-right">อนุมัติ</th><th class="text-right">ใช้จริง</th></tr></thead><tbody>
    <?php if(empty($budgetItems)): ?><tr><td colspan="4" class="empty-cell">ไม่มีรายการงบประมาณ</td></tr>
    <?php else: foreach($budgetItems as $bi): ?><tr><td><?= h($bi['item_name']) ?></td><td><?= h($bi['expense_category']) ?></td><td class="text-right"><?= h(qa_money($bi['approved_amount'])) ?></td><td class="text-right"><?= h(qa_money($bi['spent'])) ?></td></tr><?php endforeach; endif; ?>
    </tbody></table></div>
</div>

<div class="card" id="evidence" style="margin-top:16px">
    <div class="section-heading"><div><h2>หลักฐานของกิจกรรม</h2><p>รูปภาพ เอกสาร หรือ URL จะถูกเก็บในคลังหลักฐานกลางและผูกกับกิจกรรมนี้โดยตรง</p></div><span class="badge badge-blue"><?= h(count($evidences)) ?> รายการ</span></div>
    <?php if(!empty($evidences)): ?><div class="table-wrap"><table><thead><tr><th>รหัส</th><th>วันที่</th><th>ชื่อหลักฐาน</th><th>ตัวชี้วัด</th><th>ไฟล์/URL</th></tr></thead><tbody><?php foreach($evidences as $ev): ?><tr><td><?= h($ev['evidence_code']) ?></td><td><?= h($ev['evidence_date']) ?></td><td><?= h($ev['title']) ?></td><td><?= $ev['indicator_codes']?h($ev['indicator_codes']):'-' ?></td><td><?php if($ev['file_path']): ?><a href="<?= h(qa_url($ev['file_path'])) ?>" target="_blank">ไฟล์</a><?php endif; ?><?= $ev['file_path']&&$ev['external_url']?' / ':'' ?><?php if($ev['external_url']): ?><a href="<?= h($ev['external_url']) ?>" target="_blank">URL</a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>

    <?php if($canReport): ?>
    <form method="post" enctype="multipart/form-data" class="master-form">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>"><input type="hidden" name="action" value="add_evidence">
        <div class="form-grid">
            <div class="form-group"><label>ชื่อหลักฐาน *</label><input name="title" required></div>
            <div class="form-group"><label>วันที่ *</label><input type="date" name="evidence_date" required value="<?= h(date('Y-m-d')) ?>"></div>
            <div class="form-group"><label>เลขที่เอกสาร</label><input name="document_no"></div>
            <div class="form-group"><label>ไฟล์ (ไม่เกิน 10 MB)</label><input type="file" name="evidence_file" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"></div>
            <div class="form-group full"><label>URL ภายนอก</label><input name="external_url" placeholder="https://..."></div>
            <div class="form-group full"><label>รายละเอียด</label><textarea name="description"></textarea></div>
        </div>
        <?php if(!empty($indicators)): ?><div class="activity-indicator-checks"><strong>เชื่อมหลักฐานกับตัวชี้วัดของโครงการ</strong><?php foreach($indicators as $i): ?><label><input type="checkbox" name="indicator_ids[]" value="<?= h($i['indicator_id']) ?>"> <span><?= h($i['indicator_code'].' — '.$i['indicator_name']) ?></span></label><?php endforeach; ?></div><?php endif; ?>
        <button class="btn btn-primary" type="submit">เพิ่มหลักฐานกิจกรรม</button>
    </form>
    <?php endif; ?>
</div>

<?php require QA_ROOT.'/includes/footer.php'; ?>
