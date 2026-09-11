<?php
$pageTitle = 'Dashboard';
$pageHeading = 'Dashboard ภาพรวมระบบประกันคุณภาพ';
$pageDescription = 'ภาพรวมงบประมาณ โครงการ ผลการดำเนินงาน และความครอบคลุมด้านคุณภาพ';
$activeMenu = 'dashboard';
require_once __DIR__ . '/includes/bootstrap.php';
qa_require_login();

$db = qa_db();
$currentYear = qa_current_year_row();
$yearId = $currentYear ? (int) $currentYear['year_id'] : 0;
$yearBE = $currentYear ? (int) $currentYear['fiscal_year_be'] : 0;

$totalBudget = $yearId > 0 ? (float) qa_db_scalar("SELECT COALESCE(SUM(initial_amount),0) FROM qa_budget_pools WHERE year_id = " . $yearId, 0) : 0;
$allocated = $yearId > 0 ? (float) qa_db_scalar("SELECT COALESCE(SUM(current_allocation),0) FROM vw_qa_budget_allocation_summary WHERE year_id = " . $yearId, 0) : 0;
$approvedProjects = $yearId > 0 ? (float) qa_db_scalar("SELECT COALESCE(SUM(approved_budget),0) FROM qa_projects WHERE year_id = " . $yearId . " AND status_code NOT IN ('REJECTED','CANCELLED','DRAFT')", 0) : 0;
$actualExpense = $yearId > 0 ? (float) qa_db_scalar("SELECT COALESCE(SUM(e.amount),0) FROM qa_expenditures e INNER JOIN qa_projects p ON p.project_id = e.project_id WHERE p.year_id = " . $yearId . " AND e.payment_status = 'paid'", 0) : 0;
$balance = $totalBudget - $actualExpense;
$totalProjects = $yearId > 0 ? (int) qa_db_scalar("SELECT COUNT(*) FROM qa_projects WHERE year_id = " . $yearId, 0) : 0;
$totalEvidence = $yearId > 0 ? (int) qa_db_scalar("SELECT COUNT(*) FROM qa_evidences ev INNER JOIN qa_projects p ON p.project_id=ev.project_id WHERE p.year_id = " . $yearId, 0) : 0;
$coveredIndicators = $yearId > 0 ? (int) qa_db_scalar(
    "SELECT COUNT(DISTINCT pil.indicator_id)
     FROM qa_project_indicator_links pil
     INNER JOIN qa_projects p ON p.project_id=pil.project_id
     INNER JOIN qa_indicators i ON i.indicator_id=pil.indicator_id
     INNER JOIN qa_standards s ON s.standard_id=i.standard_id
     INNER JOIN qa_quality_frameworks f ON f.framework_id=s.framework_id
     WHERE f.framework_code='ONESQA'
       AND p.year_id=" . $yearId . "
       AND p.status_code NOT IN ('DRAFT','REVISION','REJECTED','CANCELLED')",
    0
) : 0;
$totalIndicators = (int) qa_db_scalar("SELECT COUNT(*) FROM qa_indicators i INNER JOIN qa_standards s ON s.standard_id=i.standard_id INNER JOIN qa_quality_frameworks f ON f.framework_id=s.framework_id WHERE f.framework_code='ONESQA'", 0);
$evidenceCoveredIndicators = $yearId > 0 ? (int) qa_db_scalar(
    "SELECT COUNT(DISTINCT eil.indicator_id)
     FROM qa_evidence_indicator_links eil
     INNER JOIN qa_evidences ev ON ev.evidence_id=eil.evidence_id
     INNER JOIN qa_projects p ON p.project_id=ev.project_id
     INNER JOIN qa_indicators i ON i.indicator_id=eil.indicator_id
     INNER JOIN qa_standards s ON s.standard_id=i.standard_id
     INNER JOIN qa_quality_frameworks f ON f.framework_id=s.framework_id
     WHERE f.framework_code='ONESQA' AND p.year_id=" . $yearId,
    0
) : 0;

$divisionRows = array();
if ($yearId > 0) {
    $stmt = $db->prepare(
        "SELECT
            d.division_code,
            d.division_name,
            COALESCE(SUM(v.current_allocation),0) AS amount
         FROM qa_divisions d
         LEFT JOIN vw_qa_budget_allocation_summary v
           ON v.division_id = d.division_id
          AND v.year_id = ?
          AND v.allocation_type = 'division'
         WHERE d.is_active = 1
         GROUP BY d.division_id, d.division_code, d.division_name, d.sort_order
         ORDER BY d.sort_order, d.division_id"
    );
    $stmt->bind_param('i', $yearId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $divisionRows[] = $row;
        }
        $result->free();
    }
    $stmt->close();
}

$centralAllocated = $yearId > 0 ? (float) qa_db_scalar(
    "SELECT COALESCE(SUM(current_allocation),0)
     FROM vw_qa_budget_allocation_summary
     WHERE year_id = " . $yearId . " AND allocation_type = 'central'",
    0
) : 0;

require QA_ROOT . '/includes/header.php';
?>
<div class="action-bar">
    <a class="btn" href="<?= h(qa_url('plans/index.php')) ?>">แผนพัฒนาคุณภาพ</a>
    <a class="btn btn-primary" href="<?= h(qa_url('projects/create.php')) ?>">＋ เสนอโครงการใหม่</a>
    <a class="btn" href="<?= h(qa_url('budget/allocation.php')) ?>">จัดสรรงบ 4 ฝ่าย</a>
    <a class="btn" href="<?= h(qa_url('approvals/index.php')) ?>">ตรวจสอบ / อนุมัติ</a>
    <a class="btn" href="<?= h(qa_url('settings/roadmap.php')) ?>">ดู Roadmap การพัฒนา</a>
</div>

<section class="grid grid-5">
    <div class="card"><div class="metric-label">งบประมาณทั้งหมด</div><div class="metric-value"><?= h(qa_money($totalBudget)) ?></div><div class="metric-note">ปีงบประมาณ <?= $yearBE ? h($yearBE) : '-' ?></div></div>
    <div class="card"><div class="metric-label">จัดสรรแล้ว</div><div class="metric-value"><?= h(qa_money($allocated)) ?></div><div class="metric-note">จากข้อมูลจัดสรรจริง</div></div>
    <div class="card"><div class="metric-label">อนุมัติโครงการ</div><div class="metric-value"><?= h(qa_money($approvedProjects)) ?></div><div class="metric-note"><?= h($totalProjects) ?> โครงการทั้งหมด</div></div>
    <div class="card"><div class="metric-label">เบิกจ่ายจริง</div><div class="metric-value"><?= h(qa_money($actualExpense)) ?></div><div class="metric-note">รายการที่สถานะ paid</div></div>
    <div class="card"><div class="metric-label">งบคงเหลือ</div><div class="metric-value"><?= h(qa_money($balance)) ?></div><div class="metric-note">งบรวม - เบิกจ่ายจริง</div></div>
</section>

<section class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <div class="section-heading">
            <div>
                <h2>การจัดสรรงบประมาณ 4 ฝ่าย</h2>
                <p>วงเงินจริงจากฐานข้อมูล ปีงบประมาณ <?= $yearBE ? h($yearBE) : '-' ?></p>
            </div>
            <a class="btn btn-sm" href="<?= h(qa_url('budget/allocation.php')) ?>">เปิดการจัดสรร</a>
        </div>

        <?php if ($allocated <= 0): ?>
            <div class="placeholder-chart">ยังไม่มีข้อมูลจัดสรรงบประมาณ<br><small>เมื่อบันทึกการจัดสรร ระบบจะแสดงสัดส่วนจริงบริเวณนี้</small></div>
        <?php else: ?>
            <?php foreach ($divisionRows as $row): ?>
                <?php
                $pct = $totalBudget > 0 ? ((float) $row['amount'] / $totalBudget) * 100 : 0;
                if ($pct > 100) { $pct = 100; }
                ?>
                <div class="progress-row">
                    <div class="progress-head">
                        <a href="<?= h(qa_url('budget/allocation-detail.php?division=' . urlencode($row['division_code']))) ?>">
                            <?= h($row['division_name']) ?>
                        </a>
                        <strong><?= h(qa_money($row['amount'])) ?> (<?= h(number_format($pct, 2)) ?>%)</strong>
                    </div>
                    <div class="progress"><span style="width:<?= h(number_format($pct, 2, '.', '')) ?>%"></span></div>
                </div>
            <?php endforeach; ?>

            <?php
            $centralPct = $totalBudget > 0 ? ($centralAllocated / $totalBudget) * 100 : 0;
            if ($centralPct > 100) { $centralPct = 100; }
            ?>
            <div class="progress-row">
                <div class="progress-head">
                    <span>งบส่วนกลาง / เงินสำรอง</span>
                    <strong><?= h(qa_money($centralAllocated)) ?> (<?= h(number_format($centralPct, 2)) ?>%)</strong>
                </div>
                <div class="progress"><span style="width:<?= h(number_format($centralPct, 2, '.', '')) ?>%"></span></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>ภาพรวมระบบ</h2>
        <div class="grid grid-2">
            <div><div class="metric-label">โครงการทั้งหมด</div><div class="metric-value"><?= h($totalProjects) ?></div></div>
            <div><div class="metric-label">ตัวชี้วัดที่มีการดำเนินงาน</div><div class="metric-value"><?= h($coveredIndicators) ?> / <?= h($totalIndicators) ?></div></div>
            <div><div class="metric-label">ตัวชี้วัดที่มีหลักฐาน</div><div class="metric-value"><?= h($evidenceCoveredIndicators) ?> / <?= h($totalIndicators) ?></div></div>
            <div><div class="metric-label">หลักฐานในระบบ</div><div class="metric-value"><?= h($totalEvidence) ?></div></div>
        </div>
        <div style="margin-top:14px"><a class="btn" href="<?= h(qa_url('quality/index.php')) ?>">เปิดหน้ามาตรฐาน / ตัวชี้วัด</a></div>
    </div>
</section>
<?php require QA_ROOT . '/includes/footer.php'; ?>
