<?php
$pageTitle = 'มาตรฐานและตัวชี้วัด';
$pageHeading = 'มาตรฐาน / ตัวชี้วัดประกันคุณภาพ';
$pageDescription = 'Coverage Matrix จากโครงการ งบประมาณ ผลการดำเนินงาน และหลักฐานจริง เพื่อใช้ตอบคำถามด้านการประกันคุณภาพ';
$activeMenu = 'quality';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

$db = qa_db();
$currentYear = qa_current_year_row();
$yearId = $currentYear ? (int)$currentYear['year_id'] : 0;
$yearBE = $currentYear ? (int)$currentYear['fiscal_year_be'] : 0;

$breadcrumbs = array(array('label'=>'มาตรฐาน / ตัวชี้วัด'));

$framework = null;
$result = $db->query(
    "SELECT framework_id,framework_code,framework_name,framework_owner,version_label,start_year_be,end_year_be,description
     FROM qa_quality_frameworks
     WHERE framework_code='ONESQA' AND is_active=1
     LIMIT 1"
);
if ($result) {
    $framework = $result->fetch_assoc();
    $result->free();
}

$standardFilter = isset($_GET['standard']) ? (int)$_GET['standard'] : 0;
$statusFilter = isset($_GET['coverage']) ? trim($_GET['coverage']) : '';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$standards = array();
if ($framework) {
    $stmt = $db->prepare(
        "SELECT standard_id,standard_code,standard_name,description,sort_order
         FROM qa_standards
         WHERE framework_id=? AND is_active=1
         ORDER BY sort_order,standard_id"
    );
    $stmt->bind_param('i',$framework['framework_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row=$result->fetch_assoc()) $standards[]=$row;
        $result->free();
    }
    $stmt->close();
}

$indicators = array();
if ($framework && $yearId > 0) {
    $sql = "SELECT
                i.indicator_id,i.indicator_code,i.indicator_name,i.description,i.criteria_text,i.evidence_guidance,
                s.standard_id,s.standard_code,s.standard_name,s.sort_order AS standard_sort,i.sort_order,
                (
                    SELECT COUNT(DISTINCT pil.project_id)
                    FROM qa_project_indicator_links pil
                    INNER JOIN qa_projects p ON p.project_id=pil.project_id
                    WHERE pil.indicator_id=i.indicator_id
                      AND p.year_id=".(int)$yearId."
                      AND p.status_code NOT IN ('DRAFT','REVISION','REJECTED','CANCELLED')
                ) AS project_count,
                (
                    SELECT COUNT(DISTINCT pil.project_id)
                    FROM qa_project_indicator_links pil
                    INNER JOIN qa_projects p ON p.project_id=pil.project_id
                    WHERE pil.indicator_id=i.indicator_id
                      AND p.year_id=".(int)$yearId."
                      AND p.status_code IN ('COMPLETED','CLOSED')
                ) AS completed_project_count,
                (
                    SELECT COUNT(DISTINCT ev.evidence_id)
                    FROM qa_evidence_indicator_links eil
                    INNER JOIN qa_evidences ev ON ev.evidence_id=eil.evidence_id
                    INNER JOIN qa_projects p ON p.project_id=ev.project_id
                    WHERE eil.indicator_id=i.indicator_id
                      AND p.year_id=".(int)$yearId."
                ) AS evidence_count,
                (
                    SELECT COUNT(DISTINCT pr.project_id)
                    FROM qa_project_indicator_links pil
                    INNER JOIN qa_projects p ON p.project_id=pil.project_id
                    INNER JOIN qa_project_results pr ON pr.project_id=p.project_id
                    WHERE pil.indicator_id=i.indicator_id
                      AND p.year_id=".(int)$yearId."
                      AND pr.approved_at IS NOT NULL
                ) AS certified_result_count,
                (
                    SELECT COALESCE(SUM(p.approved_budget),0)
                    FROM qa_project_indicator_links pil
                    INNER JOIN qa_projects p ON p.project_id=pil.project_id
                    WHERE pil.indicator_id=i.indicator_id
                      AND p.year_id=".(int)$yearId."
                      AND p.status_code NOT IN ('DRAFT','REVISION','REJECTED','CANCELLED')
                ) AS approved_budget,
                (
                    SELECT COALESCE(SUM(e.amount),0)
                    FROM qa_project_indicator_links pil
                    INNER JOIN qa_projects p ON p.project_id=pil.project_id
                    INNER JOIN qa_expenditures e ON e.project_id=p.project_id AND e.payment_status='paid'
                    WHERE pil.indicator_id=i.indicator_id
                      AND p.year_id=".(int)$yearId."
                      AND p.status_code NOT IN ('DRAFT','REVISION','REJECTED','CANCELLED')
                ) AS actual_expense
            FROM qa_indicators i
            INNER JOIN qa_standards s ON s.standard_id=i.standard_id
            INNER JOIN qa_quality_frameworks f ON f.framework_id=s.framework_id
            WHERE f.framework_code='ONESQA'
              AND i.is_active=1
              AND s.is_active=1
            ORDER BY s.sort_order,s.standard_id,i.sort_order,i.indicator_id";

    $result = $db->query($sql);
    if ($result) {
        while ($row=$result->fetch_assoc()) {
            $projectCount=(int)$row['project_count'];
            $evidenceCount=(int)$row['evidence_count'];
            $certifiedCount=(int)$row['certified_result_count'];

            if ($projectCount===0 && $evidenceCount===0) {
                $row['coverage_code']='gap';
                $row['coverage_label']='ยังไม่มีข้อมูลรองรับ';
                $row['coverage_badge']='badge-red';
            } elseif ($projectCount>0 && $evidenceCount>0 && $certifiedCount>0) {
                $row['coverage_code']='ready';
                $row['coverage_label']='มีข้อมูลรองรับ';
                $row['coverage_badge']='badge-green';
            } else {
                $row['coverage_code']='followup';
                $row['coverage_label']='ต้องติดตามข้อมูล';
                $row['coverage_badge']='badge-yellow';
            }

            $indicators[]=$row;
        }
        $result->free();
    }
}

/* ภาพรวมก่อน Filter */
$totalIndicators=count($indicators);
$withProjects=0;
$withEvidence=0;
$readyCount=0;
$followupCount=0;
$gapCount=0;
$totalApprovedLinked=0.0;
$totalActualLinked=0.0;

$standardStats=array();
foreach ($standards as $s) {
    $standardStats[(int)$s['standard_id']]=array(
        'standard'=>$s,
        'total'=>0,
        'projects'=>0,
        'evidence'=>0,
        'ready'=>0,
        'followup'=>0,
        'gap'=>0
    );
}

foreach ($indicators as $row) {
    if ((int)$row['project_count']>0) $withProjects++;
    if ((int)$row['evidence_count']>0) $withEvidence++;
    if ($row['coverage_code']==='ready') $readyCount++;
    elseif ($row['coverage_code']==='followup') $followupCount++;
    else $gapCount++;

    $totalApprovedLinked += (float)$row['approved_budget'];
    $totalActualLinked += (float)$row['actual_expense'];

    $sid=(int)$row['standard_id'];
    if (isset($standardStats[$sid])) {
        $standardStats[$sid]['total']++;
        if ((int)$row['project_count']>0) $standardStats[$sid]['projects']++;
        if ((int)$row['evidence_count']>0) $standardStats[$sid]['evidence']++;
        $standardStats[$sid][$row['coverage_code']]++;
    }
}

/* Filter */
$filtered=array();
foreach ($indicators as $row) {
    if ($standardFilter>0 && (int)$row['standard_id']!==$standardFilter) continue;
    if (in_array($statusFilter,array('ready','followup','gap'),true) && $row['coverage_code']!==$statusFilter) continue;
    if ($search!=='') {
        $hay=$row['indicator_code'].' '.$row['indicator_name'].' '.$row['standard_name'];
        if (mb_stripos($hay,$search,'UTF-8')===false) continue;
    }
    $filtered[]=$row;
}

require QA_ROOT . '/includes/header.php';
?>

<div class="notice" style="margin-bottom:16px">
    <strong>หลักการอ่านหน้านี้:</strong>
    “มีข้อมูลรองรับ” หมายถึงระบบพบโครงการ + หลักฐาน + ผลโครงการที่รับรองแล้วในปีงบประมาณที่เลือก
    ไม่ใช่การตัดสินว่า “ผ่านเกณฑ์ สมศ.” ซึ่งยังต้องพิจารณาตามเกณฑ์และดุลยพินิจของผู้ประเมิน
</div>

<div class="grid grid-5">
    <div class="card"><div class="metric-label">ตัวชี้วัดทั้งหมด</div><div class="metric-value"><?= h($totalIndicators) ?></div><div class="metric-note"><?= $framework?h($framework['framework_name']):'ONESQA' ?></div></div>
    <div class="card"><div class="metric-label">มีโครงการเชื่อมแล้ว</div><div class="metric-value"><?= h($withProjects) ?> / <?= h($totalIndicators) ?></div><div class="metric-note">ปีงบประมาณ <?= h($yearBE) ?></div></div>
    <div class="card"><div class="metric-label">มีหลักฐานแล้ว</div><div class="metric-value"><?= h($withEvidence) ?> / <?= h($totalIndicators) ?></div><div class="metric-note">Evidence ↔ Indicator</div></div>
    <div class="card"><div class="metric-label">มีข้อมูลรองรับครบวงจร</div><div class="metric-value"><?= h($readyCount) ?></div><div class="metric-note">โครงการ + หลักฐาน + ผลรับรอง</div></div>
    <div class="card"><div class="metric-label">ช่องว่าง / ต้องติดตาม</div><div class="metric-value"><?= h($gapCount+$followupCount) ?></div><div class="metric-note"><?= h($gapCount) ?> ยังไม่มีข้อมูล, <?= h($followupCount) ?> ต้องติดตาม</div></div>
</div>

<div class="grid grid-3" style="margin-top:16px">
<?php foreach ($standardStats as $stat): ?>
    <?php
    $coveragePct=$stat['total']>0?($stat['ready']/$stat['total'])*100:0;
    ?>
    <div class="card standard-summary-card">
        <div class="section-heading">
            <div>
                <h2>มาตรฐาน <?= h($stat['standard']['standard_code']) ?></h2>
                <p><?= h($stat['standard']['standard_name']) ?></p>
            </div>
            <strong><?= h($stat['ready']) ?> / <?= h($stat['total']) ?></strong>
        </div>
        <div class="progress-head"><span>ข้อมูลรองรับครบวงจร</span><strong><?= h(number_format($coveragePct,0)) ?>%</strong></div>
        <div class="progress"><span style="width:<?= h(number_format($coveragePct,2,'.','')) ?>%"></span></div>
        <div class="standard-mini-stats">
            <span>โครงการ <?= h($stat['projects']) ?>/<?= h($stat['total']) ?></span>
            <span>หลักฐาน <?= h($stat['evidence']) ?>/<?= h($stat['total']) ?></span>
            <span>ติดตาม <?= h($stat['followup']) ?></span>
            <span>ช่องว่าง <?= h($stat['gap']) ?></span>
        </div>
        <a class="btn btn-sm" href="<?= h(qa_url('quality/index.php?standard='.$stat['standard']['standard_id'])) ?>">ดูตัวชี้วัดในมาตรฐานนี้</a>
    </div>
<?php endforeach; ?>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>ค้นหา / กรอง Coverage Matrix</h2>
            <p>เลือกมาตรฐานหรือสถานะข้อมูลเพื่อหาจุดที่ต้องเร่งเติมโครงการ ผลลัพธ์ หรือหลักฐาน</p>
        </div>
    </div>
    <form method="get" class="form-grid">
        <div class="form-group">
            <label>มาตรฐาน</label>
            <select name="standard">
                <option value="0">ทุกมาตรฐาน</option>
                <?php foreach ($standards as $s): ?>
                <option value="<?= h($s['standard_id']) ?>" <?= (int)$s['standard_id']===$standardFilter?'selected':'' ?>>
                    <?= h($s['standard_code'].' — '.$s['standard_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>สถานะข้อมูล</label>
            <select name="coverage">
                <option value="">ทั้งหมด</option>
                <option value="ready" <?= $statusFilter==='ready'?'selected':'' ?>>มีข้อมูลรองรับ</option>
                <option value="followup" <?= $statusFilter==='followup'?'selected':'' ?>>ต้องติดตามข้อมูล</option>
                <option value="gap" <?= $statusFilter==='gap'?'selected':'' ?>>ยังไม่มีข้อมูลรองรับ</option>
            </select>
        </div>
        <div class="form-group">
            <label>ค้นหา</label>
            <input name="q" value="<?= h($search) ?>" placeholder="รหัส/ชื่อตัวชี้วัด">
        </div>
        <div class="form-group" style="align-self:end">
            <button class="btn btn-primary" type="submit">ค้นหา</button>
            <a class="btn" href="<?= h(qa_url('quality/index.php')) ?>">ล้าง</a>
        </div>
    </form>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>Coverage Matrix ปีงบประมาณ <?= h($yearBE) ?></h2>
            <p>ยอดงบในแต่ละแถวเป็นยอดของโครงการที่เชื่อมกับตัวชี้วัดนั้น จึงไม่ควรนำยอดทุกตัวชี้วัดมาบวกเป็นงบรวมของโรงเรียน เพราะโครงการหนึ่งเชื่อมได้หลายตัวชี้วัด</p>
        </div>
        <span class="badge badge-blue"><?= h(count($filtered)) ?> รายการ</span>
    </div>

    <div class="table-wrap">
        <table class="coverage-table">
            <thead>
            <tr>
                <th>ตัวชี้วัด</th>
                <th>โครงการ</th>
                <th class="text-right">งบอนุมัติ</th>
                <th class="text-right">ใช้จริง</th>
                <th>หลักฐาน</th>
                <th>ผลรับรอง</th>
                <th>สถานะข้อมูล</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($filtered)): ?>
                <tr><td colspan="8" class="empty-cell">ไม่พบตัวชี้วัดตามเงื่อนไขที่เลือก</td></tr>
            <?php else: ?>
                <?php foreach ($filtered as $row): ?>
                <tr>
                    <td>
                        <strong><?= h($row['indicator_code']) ?> — <?= h($row['indicator_name']) ?></strong>
                        <div class="subtle">มาตรฐาน <?= h($row['standard_code']) ?> <?= h($row['standard_name']) ?></div>
                    </td>
                    <td>
                        <strong><?= h($row['project_count']) ?></strong>
                        <?php if ((int)$row['completed_project_count']>0): ?>
                            <div class="subtle">เสร็จ/ปิด <?= h($row['completed_project_count']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-right money-cell"><?= h(qa_money($row['approved_budget'])) ?></td>
                    <td class="text-right money-cell"><?= h(qa_money($row['actual_expense'])) ?></td>
                    <td><strong><?= h($row['evidence_count']) ?></strong> รายการ</td>
                    <td><strong><?= h($row['certified_result_count']) ?></strong> โครงการ</td>
                    <td><span class="badge <?= h($row['coverage_badge']) ?>"><?= h($row['coverage_label']) ?></span></td>
                    <td><a class="btn btn-sm btn-primary" href="<?= h(qa_url('quality/indicator.php?id='.$row['indicator_id'])) ?>">เปิดสารสนเทศ</a></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require QA_ROOT . '/includes/footer.php'; ?>
