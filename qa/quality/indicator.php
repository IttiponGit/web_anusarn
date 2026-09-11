<?php
$pageTitle = 'สารสนเทศรายตัวชี้วัด';
$activeMenu = 'quality';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

$db=qa_db();
$currentYear=qa_current_year_row();
$yearId=$currentYear?(int)$currentYear['year_id']:0;
$yearBE=$currentYear?(int)$currentYear['fiscal_year_be']:0;

$indicatorId=isset($_GET['id'])?(int)$_GET['id']:0;
if($indicatorId<=0){
    http_response_code(400);
    die('ไม่พบรหัสตัวชี้วัด');
}

$indicator=null;
$stmt=$db->prepare(
    "SELECT
        i.indicator_id,i.indicator_code,i.indicator_name,i.description,i.criteria_text,i.evidence_guidance,
        s.standard_id,s.standard_code,s.standard_name,s.description AS standard_description,
        f.framework_code,f.framework_name,f.framework_owner,f.version_label
     FROM qa_indicators i
     INNER JOIN qa_standards s ON s.standard_id=i.standard_id
     INNER JOIN qa_quality_frameworks f ON f.framework_id=s.framework_id
     WHERE i.indicator_id=? AND f.framework_code='ONESQA'
     LIMIT 1"
);
$stmt->bind_param('i',$indicatorId);
$stmt->execute();
$result=$stmt->get_result();
$indicator=$result?$result->fetch_assoc():null;
if($result)$result->free();
$stmt->close();

if(!$indicator){
    http_response_code(404);
    die('ไม่พบตัวชี้วัด');
}

$pageHeading=$indicator['indicator_code'].' — '.$indicator['indicator_name'];
$pageDescription='สารสนเทศจากโครงการ งบประมาณ KPI ผลลัพธ์ และหลักฐานที่เชื่อมกับตัวชี้วัดนี้';
$breadcrumbs=array(
    array('label'=>'มาตรฐาน / ตัวชี้วัด','url'=>qa_url('quality/index.php')),
    array('label'=>$indicator['indicator_code'])
);

/* โครงการที่เชื่อมกับตัวชี้วัด */
$projects=array();
$sql="SELECT
        p.project_id,p.project_code,p.project_name,p.status_code,p.requested_budget,p.approved_budget,
        p.start_date,p.end_date,d.division_name,ps.status_name,
        pr.output_summary,pr.outcome_summary,pr.success_summary,pr.overall_status,pr.approved_at,
        COALESCE((
            SELECT SUM(e.amount)
            FROM qa_expenditures e
            WHERE e.project_id=p.project_id AND e.payment_status='paid'
        ),0) AS actual_expense,
        COALESCE((
            SELECT COUNT(*)
            FROM qa_project_kpis k
            WHERE k.project_id=p.project_id
        ),0) AS kpi_count,
        COALESCE((
            SELECT COUNT(*)
            FROM qa_project_kpis k
            WHERE k.project_id=p.project_id
              AND (
                  SELECT kr.achievement_status
                  FROM qa_project_kpi_results kr
                  WHERE kr.kpi_id=k.kpi_id
                  ORDER BY kr.kpi_result_id DESC
                  LIMIT 1
              )='achieved'
        ),0) AS kpi_achieved_count,
        COALESCE((
            SELECT COUNT(DISTINCT ev.evidence_id)
            FROM qa_evidences ev
            INNER JOIN qa_evidence_indicator_links eil ON eil.evidence_id=ev.evidence_id
            WHERE ev.project_id=p.project_id
              AND eil.indicator_id=".(int)$indicatorId."
        ),0) AS indicator_evidence_count
      FROM qa_project_indicator_links pil
      INNER JOIN qa_projects p ON p.project_id=pil.project_id
      INNER JOIN qa_divisions d ON d.division_id=p.division_id
      LEFT JOIN qa_project_statuses ps ON ps.status_code=p.status_code
      LEFT JOIN qa_project_results pr ON pr.project_id=p.project_id
      WHERE pil.indicator_id=".(int)$indicatorId."
        AND p.year_id=".(int)$yearId."
        AND p.status_code NOT IN ('DRAFT','REVISION','REJECTED','CANCELLED')
      ORDER BY
        CASE p.status_code
          WHEN 'CLOSED' THEN 1
          WHEN 'COMPLETED' THEN 2
          WHEN 'IN_PROGRESS' THEN 3
          WHEN 'APPROVED' THEN 4
          ELSE 5
        END,
        p.project_id DESC";

$result=$db->query($sql);
if($result){
    while($row=$result->fetch_assoc())$projects[]=$row;
    $result->free();
}

/* หลักฐานที่เชื่อมตรงกับตัวชี้วัด */
$evidences=array();
$sql="SELECT
        ev.evidence_id,ev.evidence_code,ev.evidence_type,ev.title,ev.description,ev.document_no,
        ev.evidence_date,ev.file_path,ev.external_url,
        p.project_id,p.project_code,p.project_name,d.division_name
      FROM qa_evidence_indicator_links eil
      INNER JOIN qa_evidences ev ON ev.evidence_id=eil.evidence_id
      INNER JOIN qa_projects p ON p.project_id=ev.project_id
      INNER JOIN qa_divisions d ON d.division_id=p.division_id
      WHERE eil.indicator_id=".(int)$indicatorId."
        AND p.year_id=".(int)$yearId."
      ORDER BY ev.evidence_date DESC,ev.evidence_id DESC";
$result=$db->query($sql);
if($result){
    while($row=$result->fetch_assoc())$evidences[]=$row;
    $result->free();
}

/* สรุป */
$projectCount=count($projects);
$completedCount=0;
$certifiedResultCount=0;
$approvedBudget=0.0;
$actualExpense=0.0;
$kpiTotal=0;
$kpiAchieved=0;

foreach($projects as $p){
    if(in_array($p['status_code'],array('COMPLETED','CLOSED'),true))$completedCount++;
    if($p['approved_at'])$certifiedResultCount++;
    $approvedBudget+=(float)$p['approved_budget'];
    $actualExpense+=(float)$p['actual_expense'];
    $kpiTotal+=(int)$p['kpi_count'];
    $kpiAchieved+=(int)$p['kpi_achieved_count'];
}

$evidenceCount=count($evidences);
$balance=$approvedBudget-$actualExpense;

if($projectCount===0 && $evidenceCount===0){
    $coverageCode='gap';
    $coverageLabel='ยังไม่มีข้อมูลรองรับ';
    $coverageBadge='badge-red';
}elseif($projectCount>0 && $evidenceCount>0 && $certifiedResultCount>0){
    $coverageCode='ready';
    $coverageLabel='มีข้อมูลรองรับ';
    $coverageBadge='badge-green';
}else{
    $coverageCode='followup';
    $coverageLabel='ต้องติดตามข้อมูล';
    $coverageBadge='badge-yellow';
}

/* Gap checklist */
$gaps=array();
if($projectCount===0)$gaps[]='ยังไม่มีโครงการ / งาน / กิจกรรมในปีงบประมาณนี้ที่เชื่อมกับตัวชี้วัด';
if($projectCount>0 && $evidenceCount===0)$gaps[]='มีโครงการแล้ว แต่ยังไม่มีหลักฐานที่เชื่อมกับตัวชี้วัดโดยตรง';
if($projectCount>0 && $certifiedResultCount===0)$gaps[]='ยังไม่มีผลโครงการที่ผ่านการรับรองผล';
if($projectCount>0 && $kpiTotal===0)$gaps[]='โครงการที่เชื่อมยังไม่มี KPI ในระบบ';
if($projectCount>0 && $kpiTotal>0 && $kpiAchieved<$kpiTotal)$gaps[]='KPI ของโครงการที่เชื่อมยังไม่ได้บันทึกผลสำเร็จครบทุกตัว';
if(empty($gaps))$gaps[]='ไม่พบช่องว่างหลักจากข้อมูลที่ระบบตรวจได้ในขณะนี้';

/* ข้อความสรุปสารสนเทศ */
$summaryText='ปีงบประมาณ '.$yearBE.' ตัวชี้วัด '.$indicator['indicator_code'].' “'.$indicator['indicator_name'].'” '
    .'มีโครงการ/งาน/กิจกรรมเชื่อมโยง '.$projectCount.' รายการ '
    .'วงเงินอนุมัติรวม '.qa_money($approvedBudget).' บาท '
    .'เบิกจ่ายจริง '.qa_money($actualExpense).' บาท '
    .'มีหลักฐานที่เชื่อมโดยตรง '.$evidenceCount.' รายการ '
    .'และมีโครงการที่ผลการดำเนินงานได้รับการรับรองแล้ว '.$certifiedResultCount.' รายการ';

require QA_ROOT . '/includes/header.php';
?>

<div class="action-bar">
    <a class="btn" href="<?= h(qa_url('quality/index.php')) ?>">← Coverage Matrix</a>
    <a class="btn" href="<?= h(qa_url('evidences/index.php?indicator='.$indicatorId)) ?>">เปิดหลักฐานของตัวชี้วัดนี้</a>
</div>

<div class="notice" style="margin-bottom:16px">
    <strong>สถานะข้อมูล:</strong>
    <span class="badge <?= h($coverageBadge) ?>"><?= h($coverageLabel) ?></span>
    <span style="margin-left:8px">สถานะนี้เป็นการประเมิน “ความครบของข้อมูลในระบบ” ไม่ใช่ผลตัดสินผ่าน/ไม่ผ่านเกณฑ์ สมศ.</span>
</div>

<div class="grid grid-5">
    <div class="card"><div class="metric-label">โครงการสนับสนุน</div><div class="metric-value"><?= h($projectCount) ?></div><div class="metric-note">เสร็จ/ปิด <?= h($completedCount) ?></div></div>
    <div class="card"><div class="metric-label">งบอนุมัติ</div><div class="metric-value"><?= h(qa_money($approvedBudget)) ?></div><div class="metric-note">เฉพาะโครงการที่เชื่อมตัวชี้วัดนี้</div></div>
    <div class="card"><div class="metric-label">เบิกจ่ายจริง</div><div class="metric-value"><?= h(qa_money($actualExpense)) ?></div><div class="metric-note">คงเหลือ <?= h(qa_money($balance)) ?></div></div>
    <div class="card"><div class="metric-label">หลักฐาน</div><div class="metric-value"><?= h($evidenceCount) ?></div><div class="metric-note">เชื่อม Indicator โดยตรง</div></div>
    <div class="card"><div class="metric-label">KPI ของโครงการ</div><div class="metric-value"><?= h($kpiAchieved) ?> / <?= h($kpiTotal) ?></div><div class="metric-note">ผลล่าสุดที่สถานะ achieved</div></div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <h2>ข้อมูลตัวชี้วัด</h2>
        <table>
            <tbody>
                <tr><th>กรอบ</th><td><?= h($indicator['framework_name']) ?></td></tr>
                <tr><th>มาตรฐาน</th><td><?= h($indicator['standard_code'].' — '.$indicator['standard_name']) ?></td></tr>
                <tr><th>ตัวชี้วัด</th><td><strong><?= h($indicator['indicator_code'].' — '.$indicator['indicator_name']) ?></strong></td></tr>
            </tbody>
        </table>

        <?php if($indicator['description']): ?>
        <h3>คำอธิบาย</h3>
        <div class="quality-long-text"><?= nl2br(h($indicator['description'])) ?></div>
        <?php endif; ?>

        <?php if($indicator['criteria_text']): ?>
        <h3>เกณฑ์ / ประเด็นพิจารณา</h3>
        <div class="quality-long-text"><?= nl2br(h($indicator['criteria_text'])) ?></div>
        <?php endif; ?>

        <?php if($indicator['evidence_guidance']): ?>
        <h3>แนวทางหลักฐาน</h3>
        <div class="quality-long-text"><?= nl2br(h($indicator['evidence_guidance'])) ?></div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>สารสนเทศสำหรับตอบผู้ประเมิน</h2>
        <div class="qa-answer-box"><?= h($summaryText) ?></div>

        <h3 style="margin-top:18px">ระบบตรวจพบสิ่งที่ควรติดตาม</h3>
        <div class="todo-list">
            <?php foreach($gaps as $gap): ?>
            <div class="todo-item">
                <span><?= $coverageCode==='ready'?'✓':'!' ?></span>
                <div><strong><?= h($gap) ?></strong></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="notice" style="margin-top:14px">
            เวลาตอบผู้ประเมินควรใช้ข้อมูลเชิงประจักษ์จากตารางด้านล่างประกอบ ไม่ควรใช้ข้อความสรุปอัตโนมัติเพียงอย่างเดียว
        </div>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>โครงการ / งาน / กิจกรรมที่สนับสนุน</h2>
            <p>แสดงงบ ผล KPI ผลสรุป และหลักฐานที่เชื่อมกับตัวชี้วัดนี้</p>
        </div>
        <span class="badge badge-blue"><?= h($projectCount) ?> รายการ</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>โครงการ</th>
                <th>ฝ่าย</th>
                <th class="text-right">อนุมัติ</th>
                <th class="text-right">ใช้จริง</th>
                <th>KPI</th>
                <th>หลักฐาน</th>
                <th>ผลโครงการ</th>
                <th>สถานะ</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if(empty($projects)): ?>
                <tr><td colspan="9" class="empty-cell">ยังไม่มีโครงการที่เชื่อมกับตัวชี้วัดนี้ในปีงบประมาณ <?= h($yearBE) ?></td></tr>
            <?php else: ?>
                <?php foreach($projects as $p): ?>
                <tr>
                    <td><strong><?= h($p['project_code']) ?></strong><div><?= h($p['project_name']) ?></div></td>
                    <td><?= h($p['division_name']) ?></td>
                    <td class="text-right money-cell"><?= h(qa_money($p['approved_budget'])) ?></td>
                    <td class="text-right money-cell"><?= h(qa_money($p['actual_expense'])) ?></td>
                    <td><?= h($p['kpi_achieved_count']) ?> / <?= h($p['kpi_count']) ?></td>
                    <td><?= h($p['indicator_evidence_count']) ?></td>
                    <td>
                        <?php if($p['approved_at']): ?>
                            <span class="badge badge-green">รับรองแล้ว</span>
                            <?php if($p['overall_status']): ?><div class="subtle"><?= h($p['overall_status']) ?></div><?php endif; ?>
                        <?php else: ?>
                            <span class="badge badge-yellow">ยังไม่รับรองผล</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-blue"><?= h($p['status_name']?$p['status_name']:$p['status_code']) ?></span></td>
                    <td><a class="btn btn-sm" href="<?= h(qa_url('projects/view.php?id='.$p['project_id'])) ?>">เปิด</a></td>
                </tr>
                <?php if($p['output_summary'] || $p['outcome_summary']): ?>
                <tr class="project-result-row">
                    <td colspan="9">
                        <div class="grid grid-2">
                            <div><strong>Output</strong><div><?= $p['output_summary']?nl2br(h($p['output_summary'])):'-' ?></div></div>
                            <div><strong>Outcome</strong><div><?= $p['outcome_summary']?nl2br(h($p['outcome_summary'])):'-' ?></div></div>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>หลักฐานเชิงประจักษ์</h2>
            <p>หลักฐานที่ผูกกับตัวชี้วัด <?= h($indicator['indicator_code']) ?> โดยตรง</p>
        </div>
        <span class="badge badge-blue"><?= h($evidenceCount) ?> รายการ</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th>รหัส</th><th>หลักฐาน</th><th>ประเภท</th><th>โครงการ</th><th>วันที่</th><th>เปิด</th></tr></thead>
            <tbody>
            <?php if(empty($evidences)): ?>
                <tr><td colspan="6" class="empty-cell">ยังไม่มีหลักฐานที่เชื่อมกับตัวชี้วัดนี้</td></tr>
            <?php else: ?>
                <?php foreach($evidences as $ev): ?>
                <tr>
                    <td><strong><?= h($ev['evidence_code']) ?></strong></td>
                    <td><?= h($ev['title']) ?><?php if($ev['description']): ?><div class="subtle"><?= h($ev['description']) ?></div><?php endif; ?></td>
                    <td><?= h($ev['evidence_type']) ?></td>
                    <td><strong><?= h($ev['project_code']) ?></strong><div class="subtle"><?= h($ev['project_name']) ?></div></td>
                    <td><?= $ev['evidence_date']?h($ev['evidence_date']):'-' ?></td>
                    <td>
                        <a class="btn btn-sm" href="<?= h(qa_url('monitoring/project.php?id='.$ev['project_id'].'#evidence')) ?>">โครงการ</a>
                        <?php if($ev['file_path']): ?><a class="btn btn-sm" target="_blank" href="<?= h(qa_url($ev['file_path'])) ?>">ไฟล์</a><?php endif; ?>
                        <?php if($ev['external_url']): ?><a class="btn btn-sm" target="_blank" rel="noopener" href="<?= h($ev['external_url']) ?>">URL</a><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require QA_ROOT . '/includes/footer.php'; ?>
