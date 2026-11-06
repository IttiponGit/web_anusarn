<?php
$pageTitle='SAR Builder';
$activeMenu='sar-builder';
require_once __DIR__.'/../../includes/bootstrap.php';
qa_require_login();
qa_require_permission('sar.view');

$db=qa_db();
$user=qa_current_user();
$userId=$user?(int)$user['user_id']:0;
$reportId=isset($_GET['id'])?(int)$_GET['id']:0;
$report=qa_sar_report_by_id($reportId);
if(!$report){http_response_code(404);die('ไม่พบ SAR');}

$pageHeading=$report['report_title'];
$pageDescription='กรอกบทวิเคราะห์รายมาตรฐานและตัวชี้วัด โดยใช้สารสนเทศจากระบบเป็นข้อมูลตั้งต้น';
$breadcrumbs=array(
    array('label'=>'รายงาน / SAR / สมศ.','url'=>qa_url('reports/index.php')),
    array('label'=>'SAR Builder','url'=>qa_url('reports/sar/index.php')),
    array('label'=>'ปี '.$report['fiscal_year_be'])
);

$editable=qa_sar_can_edit() && $report['status_code']!=='FINAL';
$canFinalize=qa_sar_can_finalize();

function sar_edit_redirect($reportId)
{
    header('Location: '.qa_url('reports/sar/edit.php?id='.(int)$reportId));
    exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $token=isset($_POST['csrf_token'])?$_POST['csrf_token']:'';
    if(!qa_verify_csrf($token)){http_response_code(400);die('CSRF token ไม่ถูกต้อง');}
    $action=isset($_POST['action'])?$_POST['action']:'';

    if($action==='save_indicator'){
        if(!$editable){http_response_code(403);die('SAR ฉบับนี้ไม่อยู่ในสถานะที่แก้ไขได้');}
        $indicatorId=isset($_POST['indicator_id'])?(int)$_POST['indicator_id']:0;
        $analysis=trim(qa_request_scalar($_POST,'analysis_text'));
        $strengths=trim(qa_request_scalar($_POST,'strengths_text'));
        $improvement=trim(qa_request_scalar($_POST,'improvement_text'));
        $evidenceNote=trim(qa_request_scalar($_POST,'evidence_note'));

        $stmt=$db->prepare(
            "SELECT i.indicator_id,i.indicator_code,i.indicator_name
             FROM qa_indicators i
             INNER JOIN qa_standards s ON s.standard_id=i.standard_id
             WHERE i.indicator_id=? AND s.framework_id=? LIMIT 1"
        );
        $frameworkId=(int)$report['framework_id'];
        $stmt->bind_param('ii',$indicatorId,$frameworkId);
        $stmt->execute();
        $result=$stmt->get_result();
        $indicator=$result?$result->fetch_assoc():null;
        if($result)$result->free();
        $stmt->close();
        if(!$indicator){http_response_code(400);die('ตัวชี้วัดไม่ถูกต้อง');}

        $metrics=qa_sar_indicator_metrics((int)$report['year_id'],$indicatorId);
        $snapshot=qa_sar_auto_summary((int)$report['fiscal_year_be'],$indicator,$metrics);

        $stmt=$db->prepare(
            "INSERT INTO qa_sar_builder_indicator_entries
             (report_id,indicator_id,auto_summary_snapshot,analysis_text,strengths_text,improvement_text,evidence_note,updated_by)
             VALUES (?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               auto_summary_snapshot=VALUES(auto_summary_snapshot),
               analysis_text=VALUES(analysis_text),
               strengths_text=VALUES(strengths_text),
               improvement_text=VALUES(improvement_text),
               evidence_note=VALUES(evidence_note),
               updated_by=VALUES(updated_by)"
        );
        $stmt->bind_param('iisssssi',$reportId,$indicatorId,$snapshot,$analysis,$strengths,$improvement,$evidenceNote,$userId);
        if($stmt->execute()){
            $stmt->close();
            qa_project_audit_action('save_sar_indicator','qa_sar_builder_indicator_entries',$indicatorId,null,array('report_id'=>$reportId));
            $_SESSION['qa_sar_flash']=array('type'=>'success','text'=>'บันทึกตัวชี้วัด '.$indicator['indicator_code'].' เรียบร้อยแล้ว');
            sar_edit_redirect($reportId);
        }
        $error=$stmt->error;$stmt->close();
        $_SESSION['qa_sar_flash']=array('type'=>'danger','text'=>'บันทึกไม่สำเร็จ: '.$error);
        sar_edit_redirect($reportId);
    }

    if($action==='save_standard'){
        if(!$editable){http_response_code(403);die('SAR ฉบับนี้ไม่อยู่ในสถานะที่แก้ไขได้');}
        $standardId=isset($_POST['standard_id'])?(int)$_POST['standard_id']:0;
        $overview=trim(qa_request_scalar($_POST,'overview_text'));
        $strengths=trim(qa_request_scalar($_POST,'strengths_text'));
        $improvement=trim(qa_request_scalar($_POST,'improvement_text'));

        $stmt=$db->prepare(
            "INSERT INTO qa_sar_builder_standard_entries(report_id,standard_id,overview_text,strengths_text,improvement_text,updated_by)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE overview_text=VALUES(overview_text),strengths_text=VALUES(strengths_text),
               improvement_text=VALUES(improvement_text),updated_by=VALUES(updated_by)"
        );
        $stmt->bind_param('iisssi',$reportId,$standardId,$overview,$strengths,$improvement,$userId);
        if($stmt->execute()){
            $stmt->close();
            qa_project_audit_action('save_sar_standard','qa_sar_builder_standard_entries',$standardId,null,array('report_id'=>$reportId));
            $_SESSION['qa_sar_flash']=array('type'=>'success','text'=>'บันทึกบทสรุปมาตรฐานเรียบร้อยแล้ว');
        }else{
            $error=$stmt->error;$stmt->close();
            $_SESSION['qa_sar_flash']=array('type'=>'danger','text'=>'บันทึกไม่สำเร็จ: '.$error);
        }
        sar_edit_redirect($reportId);
    }

    if($action==='submit_review'){
        if(!qa_sar_can_edit() || $report['status_code']!=='DRAFT'){http_response_code(403);die('ไม่สามารถส่งตรวจได้');}
        $stmt=$db->prepare("UPDATE qa_sar_builder_reports SET status_code='REVIEW',submitted_by=?,submitted_at=NOW() WHERE report_id=?");
        $stmt->bind_param('ii',$userId,$reportId);
        $stmt->execute();$stmt->close();
        qa_project_audit_action('submit_sar_review','qa_sar_builder_reports',$reportId,array('status'=>'DRAFT'),array('status'=>'REVIEW'));
        $_SESSION['qa_sar_flash']=array('type'=>'success','text'=>'ส่ง SAR เข้าสถานะรอตรวจรับรองแล้ว');
        sar_edit_redirect($reportId);
    }

    if($action==='return_draft'){
        if(!$canFinalize || $report['status_code']!=='REVIEW'){http_response_code(403);die('ไม่สามารถส่งกลับได้');}
        $stmt=$db->prepare("UPDATE qa_sar_builder_reports SET status_code='DRAFT',finalized_by=NULL,finalized_at=NULL WHERE report_id=?");
        $stmt->bind_param('i',$reportId);$stmt->execute();$stmt->close();
        qa_project_audit_action('return_sar_draft','qa_sar_builder_reports',$reportId,array('status'=>'REVIEW'),array('status'=>'DRAFT'));
        $_SESSION['qa_sar_flash']=array('type'=>'success','text'=>'ส่ง SAR กลับเป็นฉบับร่างแล้ว');
        sar_edit_redirect($reportId);
    }

    if($action==='finalize'){
        if(!$canFinalize || $report['status_code']!=='REVIEW'){http_response_code(403);die('ไม่สามารถรับรองได้');}
        $stmt=$db->prepare("UPDATE qa_sar_builder_reports SET status_code='FINAL',finalized_by=?,finalized_at=NOW() WHERE report_id=?");
        $stmt->bind_param('ii',$userId,$reportId);$stmt->execute();$stmt->close();
        qa_project_audit_action('finalize_sar','qa_sar_builder_reports',$reportId,array('status'=>'REVIEW'),array('status'=>'FINAL'));
        $_SESSION['qa_sar_flash']=array('type'=>'success','text'=>'รับรอง SAR เรียบร้อยแล้ว');
        sar_edit_redirect($reportId);
    }

    if($action==='reopen'){
        if(!qa_user_has_role('admin') || $report['status_code']!=='FINAL'){http_response_code(403);die('เฉพาะ admin เท่านั้นที่เปิดฉบับรับรองแล้วกลับมาแก้ไขได้');}
        $stmt=$db->prepare("UPDATE qa_sar_builder_reports SET status_code='DRAFT',finalized_by=NULL,finalized_at=NULL WHERE report_id=?");
        $stmt->bind_param('i',$reportId);$stmt->execute();$stmt->close();
        qa_project_audit_action('reopen_sar','qa_sar_builder_reports',$reportId,array('status'=>'FINAL'),array('status'=>'DRAFT'));
        $_SESSION['qa_sar_flash']=array('type'=>'success','text'=>'เปิด SAR กลับเป็นฉบับร่างแล้ว');
        sar_edit_redirect($reportId);
    }
}

$message='';$messageType='success';
if(isset($_SESSION['qa_sar_flash'])){
    $messageType=$_SESSION['qa_sar_flash']['type'];
    $message=$_SESSION['qa_sar_flash']['text'];
    unset($_SESSION['qa_sar_flash']);
}

$standards=array();
$sql="SELECT s.standard_id,s.standard_code,s.standard_name,s.description
      FROM qa_standards s
      WHERE s.framework_id=".(int)$report['framework_id']." AND s.is_active=1
      ORDER BY s.sort_order,s.standard_id";
$result=$db->query($sql);
if($result){while($row=$result->fetch_assoc())$standards[]=$row;$result->free();}

$standardEntries=array();
$result=$db->query("SELECT * FROM qa_sar_builder_standard_entries WHERE report_id=".$reportId);
if($result){while($row=$result->fetch_assoc())$standardEntries[(int)$row['standard_id']]=$row;$result->free();}

$indicatorEntries=array();
$result=$db->query("SELECT * FROM qa_sar_builder_indicator_entries WHERE report_id=".$reportId);
if($result){while($row=$result->fetch_assoc())$indicatorEntries[(int)$row['indicator_id']]=$row;$result->free();}

$indicatorsByStandard=array();
foreach($standards as $std)$indicatorsByStandard[(int)$std['standard_id']]=array();

$sql="SELECT i.indicator_id,i.indicator_code,i.indicator_name,i.description,i.criteria_text,i.evidence_guidance,i.standard_id
      FROM qa_indicators i
      INNER JOIN qa_standards s ON s.standard_id=i.standard_id
      WHERE s.framework_id=".(int)$report['framework_id']." AND i.is_active=1
      ORDER BY s.sort_order,s.standard_id,i.sort_order,i.indicator_id";
$result=$db->query($sql);
if($result){
    while($row=$result->fetch_assoc()){
        $sid=(int)$row['standard_id'];
        if(isset($indicatorsByStandard[$sid]))$indicatorsByStandard[$sid][]=$row;
    }
    $result->free();
}

$totalIndicators=0;$written=0;
foreach($indicatorsByStandard as $rows){
    foreach($rows as $row){
        $totalIndicators++;
        $eid=(int)$row['indicator_id'];
        if(isset($indicatorEntries[$eid])){
            $e=$indicatorEntries[$eid];
            if(trim((string)$e['analysis_text'])!=='' || trim((string)$e['strengths_text'])!=='' || trim((string)$e['improvement_text'])!=='')$written++;
        }
    }
}
$progressPct=$totalIndicators>0?($written/$totalIndicators)*100:0;

require QA_ROOT.'/includes/header.php';
?>

<?php if($message!==''): ?><div class="alert <?= $messageType==='danger'?'alert-danger':'alert-success' ?>"><?= h($message) ?></div><?php endif; ?>

<div class="action-bar">
    <a class="btn" href="<?= h(qa_url('reports/sar/index.php')) ?>">← SAR ทั้งหมด</a>
    <a class="btn" href="<?= h(qa_url('reports/sar/print.php?id='.$reportId)) ?>" target="_blank">ดูฉบับพิมพ์</a>

    <?php if($report['status_code']==='DRAFT' && qa_sar_can_edit()): ?>
    <form method="post" style="display:inline" onsubmit="return confirm('ส่ง SAR ให้ผู้อำนวยการตรวจรับรอง?');">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="submit_review">
        <button class="btn btn-primary" type="submit">ส่งตรวจรับรอง</button>
    </form>
    <?php endif; ?>

    <?php if($report['status_code']==='REVIEW' && $canFinalize): ?>
    <form method="post" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="return_draft">
        <button class="btn" type="submit">ส่งกลับแก้ไข</button>
    </form>
    <form method="post" style="display:inline" onsubmit="return confirm('ยืนยันรับรอง SAR ฉบับนี้? หลังรับรองจะเป็นแบบอ่านอย่างเดียว');">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="finalize">
        <button class="btn btn-primary" type="submit">รับรอง SAR</button>
    </form>
    <?php endif; ?>

    <?php if($report['status_code']==='FINAL' && qa_user_has_role('admin')): ?>
    <form method="post" style="display:inline" onsubmit="return confirm('เปิดฉบับรับรองแล้วกลับมาแก้ไข? การกระทำนี้จะถูกบันทึกใน Audit Log');">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="reopen">
        <button class="btn" type="submit">เปิดกลับเป็นฉบับร่าง</button>
    </form>
    <?php endif; ?>
</div>

<div class="grid grid-4 sar-builder-summary">
    <div class="card"><div class="metric-label">สถานะ</div><div class="metric-value sar-status-value"><?= h(qa_sar_status_label($report['status_code'])) ?></div><div class="metric-note">ปี <?= h($report['fiscal_year_be']) ?></div></div>
    <div class="card"><div class="metric-label">ตัวชี้วัดทั้งหมด</div><div class="metric-value"><?= h($totalIndicators) ?></div><div class="metric-note"><?= h($report['framework_name']) ?></div></div>
    <div class="card"><div class="metric-label">มีบทวิเคราะห์แล้ว</div><div class="metric-value"><?= h($written) ?></div><div class="metric-note">จาก <?= h($totalIndicators) ?> ตัวชี้วัด</div></div>
    <div class="card"><div class="metric-label">ความคืบหน้า</div><div class="metric-value"><?= h(number_format($progressPct,0)) ?>%</div><div class="progress"><span style="width:<?= h(number_format($progressPct,2,'.','')) ?>%"></span></div></div>
</div>

<div class="notice" style="margin-top:16px">
    <strong>ข้อมูลอัตโนมัติ</strong> จะคำนวณจากฐานข้อมูลปัจจุบันทุกครั้งที่เปิดหน้า และจะบันทึก Snapshot ล่าสุดเมื่อกดบันทึกตัวชี้วัด
    ส่วน “บทวิเคราะห์ / จุดเด่น / ประเด็นพัฒนา” เป็นข้อความที่ผู้รับผิดชอบจัดทำเอง
</div>

<?php foreach($standards as $standard):
    $sid=(int)$standard['standard_id'];
    $stdEntry=isset($standardEntries[$sid])?$standardEntries[$sid]:null;
?>
<section class="card sar-standard-card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>มาตรฐาน <?= h($standard['standard_code']) ?> — <?= h($standard['standard_name']) ?></h2>
            <?php if($standard['description']): ?><p><?= h($standard['description']) ?></p><?php endif; ?>
        </div>
        <span class="badge badge-blue"><?= h(count($indicatorsByStandard[$sid])) ?> ตัวชี้วัด</span>
    </div>

    <form method="post" class="sar-standard-form">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="save_standard">
        <input type="hidden" name="standard_id" value="<?= h($sid) ?>">
        <div class="form-grid">
            <div class="form-group sar-full">
                <label>บทสรุปภาพรวมมาตรฐาน</label>
                <textarea name="overview_text" <?= $editable?'':'readonly' ?> placeholder="สรุปภาพรวมผลการดำเนินงานของมาตรฐานนี้"><?= h($stdEntry?$stdEntry['overview_text']:'') ?></textarea>
            </div>
            <div class="form-group">
                <label>จุดเด่นของมาตรฐาน</label>
                <textarea name="strengths_text" <?= $editable?'':'readonly' ?>><?= h($stdEntry?$stdEntry['strengths_text']:'') ?></textarea>
            </div>
            <div class="form-group">
                <label>ประเด็นพัฒนา</label>
                <textarea name="improvement_text" <?= $editable?'':'readonly' ?>><?= h($stdEntry?$stdEntry['improvement_text']:'') ?></textarea>
            </div>
        </div>
        <?php if($editable): ?><button class="btn btn-sm" type="submit">บันทึกบทสรุปมาตรฐาน</button><?php endif; ?>
    </form>

    <div class="sar-indicator-list">
    <?php foreach($indicatorsByStandard[$sid] as $indicator):
        $iid=(int)$indicator['indicator_id'];
        $entry=isset($indicatorEntries[$iid])?$indicatorEntries[$iid]:null;
        $metrics=qa_sar_indicator_metrics((int)$report['year_id'],$iid);
        $autoSummary=qa_sar_auto_summary((int)$report['fiscal_year_be'],$indicator,$metrics);
        $hasWritten=$entry && (trim((string)$entry['analysis_text'])!=='' || trim((string)$entry['strengths_text'])!=='' || trim((string)$entry['improvement_text'])!=='');
    ?>
        <details class="sar-indicator-card" <?= $hasWritten?'':'open' ?>>
            <summary>
                <span><strong><?= h($indicator['indicator_code']) ?></strong> <?= h($indicator['indicator_name']) ?></span>
                <span class="badge <?= $hasWritten?'badge-green':'badge-yellow' ?>"><?= $hasWritten?'มีบทวิเคราะห์':'รอเขียน' ?></span>
            </summary>

            <div class="sar-auto-grid">
                <div><span>โครงการ</span><strong><?= h($metrics['project_count']) ?></strong></div>
                <div><span>ผลรับรอง</span><strong><?= h($metrics['certified_count']) ?></strong></div>
                <div><span>หลักฐาน</span><strong><?= h($metrics['evidence_count']) ?></strong></div>
                <div><span>KPI บรรลุ</span><strong><?= h($metrics['kpi_achieved']) ?>/<?= h($metrics['kpi_total']) ?></strong></div>
                <div><span>งบอนุมัติ</span><strong><?= h(qa_money($metrics['approved_budget'])) ?></strong></div>
                <div><span>ใช้จริง</span><strong><?= h(qa_money($metrics['actual_expense'])) ?></strong></div>
            </div>

            <div class="qa-answer-box sar-auto-summary"><?= h($autoSummary) ?></div>

            <form method="post" class="sar-indicator-form">
                <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
                <input type="hidden" name="action" value="save_indicator">
                <input type="hidden" name="indicator_id" value="<?= h($iid) ?>">

                <div class="form-group">
                    <label>บทวิเคราะห์ / การตีความข้อมูล</label>
                    <textarea name="analysis_text" <?= $editable?'':'readonly' ?> placeholder="อธิบายว่าข้อมูลสะท้อนคุณภาพอย่างไร โดยยึดข้อมูลจริงและบริบทของสถานศึกษา"><?= h($entry?$entry['analysis_text']:'') ?></textarea>
                </div>
                <div class="grid grid-2">
                    <div class="form-group"><label>จุดเด่น</label><textarea name="strengths_text" <?= $editable?'':'readonly' ?>><?= h($entry?$entry['strengths_text']:'') ?></textarea></div>
                    <div class="form-group"><label>ประเด็นพัฒนา</label><textarea name="improvement_text" <?= $editable?'':'readonly' ?>><?= h($entry?$entry['improvement_text']:'') ?></textarea></div>
                </div>
                <div class="form-group">
                    <label>หมายเหตุหลักฐาน / เอกสารสำคัญ</label>
                    <textarea name="evidence_note" <?= $editable?'':'readonly' ?> placeholder="ระบุหลักฐานสำคัญที่ควรอ้างถึงในรายงาน"><?= h($entry?$entry['evidence_note']:'') ?></textarea>
                </div>

                <div class="action-bar">
                    <a class="btn btn-sm" href="<?= h(qa_url('quality/indicator.php?id='.$iid)) ?>" target="_blank">เปิดสารสนเทศตัวชี้วัด</a>
                    <a class="btn btn-sm" href="<?= h(qa_url('evidences/index.php?indicator='.$iid)) ?>" target="_blank">เปิดหลักฐาน</a>
                    <?php if($editable): ?><button class="btn btn-sm btn-primary" type="submit">บันทึกตัวชี้วัด</button><?php endif; ?>
                </div>
            </form>
        </details>
    <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>

<?php require QA_ROOT.'/includes/footer.php'; ?>
