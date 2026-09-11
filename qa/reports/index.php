<?php
$pageTitle = 'รายงาน / SAR / สมศ.';
$pageHeading = 'รายงานและสารสนเทศเพื่อการประเมิน';
$pageDescription = 'ศูนย์รวมทางลัดไปยังสารสนเทศงบประมาณ โครงการ ผลลัพธ์ หลักฐาน และ Coverage Matrix สำหรับการประกันคุณภาพ';
$activeMenu = 'reports';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

$db=qa_db();
$currentYear=qa_current_year_row();
$yearId=$currentYear?(int)$currentYear['year_id']:0;
$yearBE=$currentYear?(int)$currentYear['fiscal_year_be']:0;
$breadcrumbs=array(array('label'=>'รายงาน / SAR / สมศ.'));

$totalIndicators=(int)qa_db_scalar(
    "SELECT COUNT(*)
     FROM qa_indicators i
     INNER JOIN qa_standards s ON s.standard_id=i.standard_id
     INNER JOIN qa_quality_frameworks f ON f.framework_id=s.framework_id
     WHERE f.framework_code='ONESQA' AND i.is_active=1",0
);

$projectCovered=$yearId>0?(int)qa_db_scalar(
    "SELECT COUNT(DISTINCT pil.indicator_id)
     FROM qa_project_indicator_links pil
     INNER JOIN qa_projects p ON p.project_id=pil.project_id
     INNER JOIN qa_indicators i ON i.indicator_id=pil.indicator_id
     INNER JOIN qa_standards s ON s.standard_id=i.standard_id
     INNER JOIN qa_quality_frameworks f ON f.framework_id=s.framework_id
     WHERE f.framework_code='ONESQA'
       AND p.year_id=".$yearId."
       AND p.status_code NOT IN ('DRAFT','REVISION','REJECTED','CANCELLED')",0
):0;

$evidenceCovered=$yearId>0?(int)qa_db_scalar(
    "SELECT COUNT(DISTINCT eil.indicator_id)
     FROM qa_evidence_indicator_links eil
     INNER JOIN qa_evidences ev ON ev.evidence_id=eil.evidence_id
     INNER JOIN qa_projects p ON p.project_id=ev.project_id
     INNER JOIN qa_indicators i ON i.indicator_id=eil.indicator_id
     INNER JOIN qa_standards s ON s.standard_id=i.standard_id
     INNER JOIN qa_quality_frameworks f ON f.framework_id=s.framework_id
     WHERE f.framework_code='ONESQA' AND p.year_id=".$yearId,0
):0;

$totalEvidence=$yearId>0?(int)qa_db_scalar(
    "SELECT COUNT(*) FROM qa_evidences ev
     INNER JOIN qa_projects p ON p.project_id=ev.project_id
     WHERE p.year_id=".$yearId,0
):0;

$completedProjects=$yearId>0?(int)qa_db_scalar(
    "SELECT COUNT(*) FROM qa_projects
     WHERE year_id=".$yearId." AND status_code IN ('COMPLETED','CLOSED')",0
):0;

require QA_ROOT . '/includes/header.php';
?>

<div class="grid grid-4">
    <div class="card"><div class="metric-label">ตัวชี้วัด สมศ.</div><div class="metric-value"><?= h($totalIndicators) ?></div><div class="metric-note">กรอบที่ใช้งาน</div></div>
    <div class="card"><div class="metric-label">มีโครงการเชื่อม</div><div class="metric-value"><?= h($projectCovered) ?> / <?= h($totalIndicators) ?></div><div class="metric-note">ปีงบประมาณ <?= h($yearBE) ?></div></div>
    <div class="card"><div class="metric-label">มีหลักฐานเชื่อม</div><div class="metric-value"><?= h($evidenceCovered) ?> / <?= h($totalIndicators) ?></div><div class="metric-note"><?= h($totalEvidence) ?> หลักฐานทั้งหมด</div></div>
    <div class="card"><div class="metric-label">โครงการสรุปผลแล้ว</div><div class="metric-value"><?= h($completedProjects) ?></div><div class="metric-note">COMPLETED / CLOSED</div></div>
</div>

<div class="grid grid-3" style="margin-top:16px">
    <div class="card report-launch-card">
        <h2>Coverage Matrix สมศ.</h2>
        <p class="help-text">ดูทุกมาตรฐานและตัวชี้วัดว่าเชื่อมโครงการ งบ ผลลัพธ์ และหลักฐานแล้วเพียงใด</p>
        <a class="btn btn-primary" href="<?= h(qa_url('quality/index.php')) ?>">เปิด Coverage Matrix</a>
    </div>
    <div class="card report-launch-card">
        <h2>คลังหลักฐาน</h2>
        <p class="help-text">ค้นหลักฐานตามตัวชี้วัด ประเภทหลักฐาน และโครงการ พร้อมเปิดไฟล์หรือ URL</p>
        <a class="btn btn-primary" href="<?= h(qa_url('evidences/index.php')) ?>">เปิดคลังหลักฐาน</a>
    </div>
    <div class="card report-launch-card">
        <h2>ผลการดำเนินโครงการ</h2>
        <p class="help-text">ติดตามงบจริง ความก้าวหน้า KPI Output Outcome และสถานะปิดโครงการ</p>
        <a class="btn btn-primary" href="<?= h(qa_url('monitoring/index.php')) ?>">เปิดผลการดำเนินงาน</a>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <h2>คำถามสารสนเทศที่ระบบตอบได้แล้ว</h2>
    <div class="todo-list">
        <div class="todo-item">
            <span>?</span>
            <div>
                <strong>ตัวชี้วัดใดมีโครงการและหลักฐานรองรับครบแล้ว?</strong>
                <small><a href="<?= h(qa_url('quality/index.php?coverage=ready')) ?>">เปิดรายการ “มีข้อมูลรองรับ”</a></small>
            </div>
        </div>
        <div class="todo-item">
            <span>?</span>
            <div>
                <strong>ตัวชี้วัดใดยังไม่มีข้อมูลหรือยังต้องติดตาม?</strong>
                <small><a href="<?= h(qa_url('quality/index.php?coverage=gap')) ?>">ดูช่องว่าง</a> • <a href="<?= h(qa_url('quality/index.php?coverage=followup')) ?>">ดูรายการต้องติดตาม</a></small>
            </div>
        </div>
        <div class="todo-item">
            <span>?</span>
            <div>
                <strong>ตัวชี้วัดหนึ่ง ๆ ใช้งบเท่าไร มีโครงการใด ผล KPI/Outcome เป็นอย่างไร และมีหลักฐานอะไร?</strong>
                <small>เปิด Coverage Matrix แล้วเลือก “เปิดสารสนเทศ” รายตัวชี้วัด</small>
            </div>
        </div>
    </div>
</div>

<div class="notice" style="margin-top:16px">
    ขั้นต่อไปของโมดูลรายงานคือสร้าง SAR ฉบับร่างจากข้อมูลจริงในระบบ และให้ผู้รับผิดชอบเพิ่มข้อความวิเคราะห์เชิงคุณภาพก่อนเสนอรับรอง
</div>

<?php require QA_ROOT . '/includes/footer.php'; ?>
