<?php
$pageTitle='SAR Builder';
$pageHeading='SAR Builder';
$pageDescription='สร้างร่างรายงาน SAR รายมาตรฐาน/ตัวชี้วัดจากข้อมูลจริงในระบบ แล้วเพิ่มเติมบทวิเคราะห์ จุดเด่น และประเด็นพัฒนา';
$activeMenu='sar-builder';

require_once __DIR__.'/../../includes/bootstrap.php';
qa_require_login();
qa_require_permission('sar.view');

$db=qa_db();
$user=qa_current_user();
$userId=$user?(int)$user['user_id']:0;
$currentYear=qa_current_year_row();
$yearId=$currentYear?(int)$currentYear['year_id']:0;
$yearBE=$currentYear?(int)$currentYear['fiscal_year_be']:0;
$framework=qa_sar_framework();
$breadcrumbs=array(
    array('label'=>'รายงาน / SAR / สมศ.','url'=>qa_url('reports/index.php')),
    array('label'=>'SAR Builder')
);

if(!qa_sar_tables_ready()){
    require QA_ROOT.'/includes/header.php';
    ?>
    <div class="alert alert-danger">
        ยังไม่ได้ติดตั้งฐานข้อมูล Phase 4.1 กรุณา Import
        <strong>anusarn_qa_v1.3_phase4.1_sar_builder.sql</strong> ก่อนใช้งาน
    </div>
    <?php
    require QA_ROOT.'/includes/footer.php';
    exit;
}

if(!$framework){
    require QA_ROOT.'/includes/header.php';
    ?><div class="alert alert-danger">ไม่พบกรอบมาตรฐาน ONESQA ในระบบ</div><?php
    require QA_ROOT.'/includes/footer.php';
    exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $token=isset($_POST['csrf_token'])?$_POST['csrf_token']:'';
    if(!qa_verify_csrf($token)){http_response_code(400);die('CSRF token ไม่ถูกต้อง');}
    if(!qa_sar_can_edit()){http_response_code(403);die('ไม่มีสิทธิ์สร้าง SAR');}

    $action=isset($_POST['action'])?$_POST['action']:'';
    if($action==='create' && $yearId>0){
        $existing=qa_sar_current_report($yearId,(int)$framework['framework_id']);
        if(!$existing){
            $title='รายงานการประเมินตนเอง (SAR) ปีงบประมาณ พ.ศ. '.$yearBE;
            $stmt=$db->prepare(
                "INSERT INTO qa_sar_builder_reports(year_id,framework_id,report_title,status_code,prepared_by)
                 VALUES (?, ?, ?, 'DRAFT', ?)"
            );
            $frameworkId=(int)$framework['framework_id'];
            $stmt->bind_param('iisi',$yearId,$frameworkId,$title,$userId);
            if($stmt->execute()){
                $reportId=$stmt->insert_id;
                $stmt->close();
                qa_project_audit_action('create_sar_report','qa_sar_builder_reports',$reportId,null,array('year_id'=>$yearId,'framework_id'=>$frameworkId));
                header('Location: '.qa_url('reports/sar/edit.php?id='.$reportId));
                exit;
            }
            $error=$stmt->error;
            $stmt->close();
            $_SESSION['qa_sar_flash']=array('type'=>'danger','text'=>'สร้าง SAR ไม่สำเร็จ: '.$error);
        }
    }
}

$currentReport=qa_sar_current_report($yearId,(int)$framework['framework_id']);

$allReports=array();
$sql="SELECT r.report_id,r.report_title,r.status_code,r.updated_at,y.fiscal_year_be,f.framework_name,
             (SELECT COUNT(*) FROM qa_sar_builder_indicator_entries e WHERE e.report_id=r.report_id AND
                 (COALESCE(e.analysis_text,'')<>'' OR COALESCE(e.strengths_text,'')<>'' OR COALESCE(e.improvement_text,'')<>'')
             ) AS written_count,
             (SELECT COUNT(*) FROM qa_indicators i
               INNER JOIN qa_standards s ON s.standard_id=i.standard_id
               WHERE s.framework_id=r.framework_id AND i.is_active=1 AND s.is_active=1
             ) AS indicator_count
      FROM qa_sar_builder_reports r
      INNER JOIN qa_years y ON y.year_id=r.year_id
      INNER JOIN qa_quality_frameworks f ON f.framework_id=r.framework_id
      ORDER BY y.fiscal_year_be DESC,r.report_id DESC";
$result=$db->query($sql);
if($result){while($row=$result->fetch_assoc())$allReports[]=$row;$result->free();}

$message='';$messageType='success';
if(isset($_SESSION['qa_sar_flash'])){
    $messageType=$_SESSION['qa_sar_flash']['type'];
    $message=$_SESSION['qa_sar_flash']['text'];
    unset($_SESSION['qa_sar_flash']);
}

$totalIndicators=(int)qa_db_scalar(
    "SELECT COUNT(*) FROM qa_indicators i
     INNER JOIN qa_standards s ON s.standard_id=i.standard_id
     WHERE s.framework_id=".(int)$framework['framework_id']." AND i.is_active=1 AND s.is_active=1",0
);
$readyIndicators=$yearId>0?(int)qa_db_scalar(
    "SELECT COUNT(*) FROM (
       SELECT i.indicator_id
       FROM qa_indicators i
       INNER JOIN qa_standards s ON s.standard_id=i.standard_id
       WHERE s.framework_id=".(int)$framework['framework_id']." AND i.is_active=1 AND s.is_active=1
         AND EXISTS(
           SELECT 1 FROM qa_project_indicator_links pil
           INNER JOIN qa_projects p ON p.project_id=pil.project_id
           WHERE pil.indicator_id=i.indicator_id AND p.year_id=".$yearId."
             AND p.status_code NOT IN ('DRAFT','REVISION','REJECTED','CANCELLED')
         )
         AND EXISTS(
           SELECT 1 FROM qa_evidence_indicator_links eil
           INNER JOIN qa_evidences ev ON ev.evidence_id=eil.evidence_id
           INNER JOIN qa_projects p2 ON p2.project_id=ev.project_id
           WHERE eil.indicator_id=i.indicator_id AND p2.year_id=".$yearId."
         )
     ) x",0
):0;

require QA_ROOT.'/includes/header.php';
?>

<?php if($message!==''): ?><div class="alert <?= $messageType==='danger'?'alert-danger':'alert-success' ?>"><?= h($message) ?></div><?php endif; ?>

<div class="notice" style="margin-bottom:16px">
    <strong>หลักการ:</strong> SAR Builder ใช้ข้อมูลโครงการ งบประมาณ KPI ผลลัพธ์ และหลักฐานในระบบเพื่อสร้าง “สารสนเทศตั้งต้น”
    แต่บทวิเคราะห์และข้อสรุปยังต้องให้ผู้รับผิดชอบตรวจสอบและเขียนเพิ่มเติม ไม่ได้ให้ระบบตัดสินระดับคุณภาพแทนสถานศึกษา
</div>

<div class="grid grid-4">
    <div class="card"><div class="metric-label">ปีงบประมาณ</div><div class="metric-value"><?= h($yearBE) ?></div><div class="metric-note">ปีที่ใช้งานอยู่</div></div>
    <div class="card"><div class="metric-label">มาตรฐาน/ตัวชี้วัด</div><div class="metric-value"><?= h($totalIndicators) ?></div><div class="metric-note"><?= h($framework['framework_name']) ?></div></div>
    <div class="card"><div class="metric-label">มีโครงการ + หลักฐาน</div><div class="metric-value"><?= h($readyIndicators) ?> / <?= h($totalIndicators) ?></div><div class="metric-note">ข้อมูลตั้งต้นสำหรับ SAR</div></div>
    <div class="card"><div class="metric-label">สถานะ SAR ปีปัจจุบัน</div><div class="metric-value sar-status-value"><?= $currentReport?h(qa_sar_status_label($currentReport['status_code'])):'ยังไม่สร้าง' ?></div><div class="metric-note">ปี <?= h($yearBE) ?></div></div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>SAR ปีงบประมาณ <?= h($yearBE) ?></h2>
            <p>สร้างหนึ่งฉบับต่อปี/กรอบมาตรฐาน เพื่อให้ข้อมูลสะสมต่อเนื่องและตรวจสอบย้อนหลังได้</p>
        </div>
        <?php if($currentReport): ?><span class="badge <?= h(qa_sar_status_badge($currentReport['status_code'])) ?>"><?= h(qa_sar_status_label($currentReport['status_code'])) ?></span><?php endif; ?>
    </div>

    <?php if($currentReport): ?>
        <div class="action-bar">
            <a class="btn btn-primary" href="<?= h(qa_url('reports/sar/edit.php?id='.$currentReport['report_id'])) ?>">เปิด SAR Builder</a>
            <a class="btn" href="<?= h(qa_url('reports/sar/print.php?id='.$currentReport['report_id'])) ?>" target="_blank">ดูฉบับพิมพ์</a>
        </div>
    <?php elseif(qa_sar_can_edit()): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
            <input type="hidden" name="action" value="create">
            <button class="btn btn-primary" type="submit">＋ สร้างร่าง SAR ปี <?= h($yearBE) ?></button>
        </form>
    <?php else: ?>
        <div class="notice">ยังไม่มี SAR ปีปัจจุบัน ผู้ใช้ Role plan หรือ admin สามารถสร้างร่างได้</div>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>ประวัติ SAR</h2><p>ดูฉบับย้อนหลังและสถานะการจัดทำ</p></div>
        <span class="badge badge-blue"><?= h(count($allReports)) ?> ฉบับ</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ปี</th><th>ชื่อรายงาน</th><th>ความคืบหน้าเนื้อหา</th><th>สถานะ</th><th>แก้ไขล่าสุด</th><th></th></tr></thead>
            <tbody>
            <?php if(empty($allReports)): ?><tr><td colspan="6" class="empty-cell">ยังไม่มี SAR ในระบบ</td></tr>
            <?php else: foreach($allReports as $row):
                $pct=(int)$row['indicator_count']>0?((int)$row['written_count']/(int)$row['indicator_count'])*100:0;
            ?>
                <tr>
                    <td><strong><?= h($row['fiscal_year_be']) ?></strong></td>
                    <td><?= h($row['report_title']) ?></td>
                    <td>
                        <div class="progress-head"><span><?= h($row['written_count']) ?>/<?= h($row['indicator_count']) ?> ตัวชี้วัด</span><strong><?= h(number_format($pct,0)) ?>%</strong></div>
                        <div class="progress"><span style="width:<?= h(number_format($pct,2,'.','')) ?>%"></span></div>
                    </td>
                    <td><span class="badge <?= h(qa_sar_status_badge($row['status_code'])) ?>"><?= h(qa_sar_status_label($row['status_code'])) ?></span></td>
                    <td><?= h($row['updated_at']) ?></td>
                    <td><a class="btn btn-sm" href="<?= h(qa_url('reports/sar/edit.php?id='.$row['report_id'])) ?>">เปิด</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require QA_ROOT.'/includes/footer.php'; ?>
