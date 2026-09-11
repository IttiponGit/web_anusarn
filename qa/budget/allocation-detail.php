<?php
$pageTitle = 'รายละเอียดงบประมาณรายฝ่าย';
$activeMenu = 'allocation';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

$db = qa_db();
$currentYear = qa_current_year_row();
$yearId = $currentYear ? (int) $currentYear['year_id'] : 0;
$yearBE = $currentYear ? (int) $currentYear['fiscal_year_be'] : 0;

$divisionParam = isset($_GET['division']) ? strtoupper(trim($_GET['division'])) : 'ACADEMIC';

$division = null;
$stmt = $db->prepare(
    "SELECT division_id, division_code, division_name, short_name
     FROM qa_divisions
     WHERE division_code = ? AND is_active = 1
     LIMIT 1"
);
$stmt->bind_param('s', $divisionParam);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $division = $result->fetch_assoc();
    $result->free();
}
$stmt->close();

if (!$division) {
    $result = $db->query(
        "SELECT division_id, division_code, division_name, short_name
         FROM qa_divisions
         WHERE is_active = 1
         ORDER BY sort_order, division_id
         LIMIT 1"
    );
    $division = $result ? $result->fetch_assoc() : null;
    if ($result) {
        $result->free();
    }
}

if (!$division) {
    http_response_code(500);
    die('ไม่พบข้อมูลฝ่ายในระบบ');
}

$divisionId = (int) $division['division_id'];
$divisionName = $division['division_name'];
$pageHeading = 'รายละเอียดงบประมาณ — ' . $divisionName;
$pageDescription = 'ดูวงเงินที่ได้รับ โครงการที่เสนอ งบอนุมัติ การเบิกจ่ายจริง และยอดคงเหลือของฝ่าย';
$breadcrumbs = array(
    array('label' => 'จัดสรรงบ 4 ฝ่าย', 'url' => qa_url('budget/allocation.php')),
    array('label' => $divisionName)
);

$allocated = $yearId > 0 ? (float) qa_db_scalar(
    "SELECT COALESCE(SUM(current_allocation),0)
     FROM vw_qa_budget_allocation_summary
     WHERE year_id = " . $yearId . "
       AND allocation_type = 'division'
       AND division_id = " . $divisionId,
    0
) : 0;

$requested = $yearId > 0 ? (float) qa_db_scalar(
    "SELECT COALESCE(SUM(requested_budget),0)
     FROM qa_projects
     WHERE year_id = " . $yearId . "
       AND division_id = " . $divisionId . "
       AND status_code NOT IN ('REJECTED','CANCELLED')",
    0
) : 0;

$approved = $yearId > 0 ? (float) qa_db_scalar(
    "SELECT COALESCE(SUM(approved_budget),0)
     FROM qa_projects
     WHERE year_id = " . $yearId . "
       AND division_id = " . $divisionId . "
       AND status_code NOT IN ('REJECTED','CANCELLED')",
    0
) : 0;

$actualExpense = $yearId > 0 ? (float) qa_db_scalar(
    "SELECT COALESCE(SUM(e.amount),0)
     FROM qa_expenditures e
     INNER JOIN qa_projects p ON p.project_id = e.project_id
     WHERE p.year_id = " . $yearId . "
       AND p.division_id = " . $divisionId . "
       AND e.payment_status = 'paid'",
    0
) : 0;

$availableForProjects = $allocated - $approved;
$cashBalance = $allocated - $actualExpense;

$projects = array();
if ($yearId > 0) {
    $stmt = $db->prepare(
        "SELECT
            p.project_id,
            p.project_code,
            p.project_name,
            p.requested_budget,
            p.approved_budget,
            p.status_code,
            ps.status_name,
            COALESCE((
                SELECT SUM(e.amount)
                FROM qa_expenditures e
                WHERE e.project_id = p.project_id
                  AND e.payment_status = 'paid'
            ),0) AS actual_expense
         FROM qa_projects p
         LEFT JOIN qa_project_statuses ps ON ps.status_code = p.status_code
         WHERE p.year_id = ?
           AND p.division_id = ?
         ORDER BY p.project_id DESC"
    );
    $stmt->bind_param('ii', $yearId, $divisionId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
        $result->free();
    }
    $stmt->close();
}

$sourceBreakdown = array();
if ($yearId > 0) {
    $stmt = $db->prepare(
        "SELECT
            s.source_name,
            p.pool_name,
            v.current_allocation
         FROM vw_qa_budget_allocation_summary v
         INNER JOIN qa_budget_pools p ON p.budget_pool_id = v.budget_pool_id
         INNER JOIN qa_budget_sources s ON s.source_id = p.source_id
         WHERE v.year_id = ?
           AND v.allocation_type = 'division'
           AND v.division_id = ?
           AND v.current_allocation <> 0
         ORDER BY p.budget_pool_id"
    );
    $stmt->bind_param('ii', $yearId, $divisionId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $sourceBreakdown[] = $row;
        }
        $result->free();
    }
    $stmt->close();
}

require QA_ROOT . '/includes/header.php';
?>

<div class="action-bar">
    <a class="btn" href="<?= h(qa_url('budget/allocation.php')) ?>">← กลับหน้าจัดสรร</a>
    <a class="btn btn-primary" href="<?= h(qa_url('projects/create.php?division=' . urlencode($division['division_code']))) ?>">＋ เสนอโครงการ</a>
</div>

<div class="grid grid-5">
    <div class="card">
        <div class="metric-label">ได้รับจัดสรร</div>
        <div class="metric-value"><?= h(qa_money($allocated)) ?></div>
        <div class="metric-note">รวมทุกแหล่งเงิน</div>
    </div>
    <div class="card">
        <div class="metric-label">โครงการที่เสนอ</div>
        <div class="metric-value"><?= h(qa_money($requested)) ?></div>
        <div class="metric-note"><?= h(count($projects)) ?> โครงการ</div>
    </div>
    <div class="card">
        <div class="metric-label">อนุมัติโครงการ</div>
        <div class="metric-value"><?= h(qa_money($approved)) ?></div>
        <div class="metric-note">approved_budget</div>
    </div>
    <div class="card">
        <div class="metric-label">เบิกจ่ายจริง</div>
        <div class="metric-value"><?= h(qa_money($actualExpense)) ?></div>
        <div class="metric-note">รายการสถานะ paid</div>
    </div>
    <div class="card">
        <div class="metric-label">คงเหลือสำหรับอนุมัติโครงการ</div>
        <div class="metric-value <?= $availableForProjects < -0.005 ? 'text-danger' : '' ?>"><?= h(qa_money($availableForProjects)) ?></div>
        <div class="metric-note">จัดสรร - งบโครงการที่อนุมัติ</div>
    </div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <div class="section-heading">
            <div>
                <h2>แหล่งที่มาของวงเงินฝ่าย</h2>
                <p>แสดงว่าฝ่ายนี้ได้รับงบจากก้อนงบประมาณใดบ้าง</p>
            </div>
        </div>

        <?php if (empty($sourceBreakdown)): ?>
            <div class="empty-cell">ยังไม่มีวงเงินจัดสรรให้ฝ่ายนี้</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>แหล่งเงิน</th>
                        <th>รายการงบ</th>
                        <th class="text-right">วงเงิน</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sourceBreakdown as $row): ?>
                        <tr>
                            <td><?= h($row['source_name']) ?></td>
                            <td><?= h($row['pool_name']) ?></td>
                            <td class="text-right money-cell"><?= h(qa_money($row['current_allocation'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="section-heading">
            <div>
                <h2>สถานะวงเงิน</h2>
                <p>เปรียบเทียบวงเงินที่ได้รับกับการอนุมัติและเบิกจ่ายจริง</p>
            </div>
        </div>

        <div class="budget-health">
            <div>
                <span>วงเงินที่ได้รับ</span>
                <strong><?= h(qa_money($allocated)) ?></strong>
            </div>
            <div>
                <span>คงเหลือหลังอนุมัติโครงการ</span>
                <strong class="<?= $availableForProjects < -0.005 ? 'text-danger' : '' ?>"><?= h(qa_money($availableForProjects)) ?></strong>
            </div>
            <div>
                <span>คงเหลือหลังเบิกจ่ายจริง</span>
                <strong class="<?= $cashBalance < -0.005 ? 'text-danger' : '' ?>"><?= h(qa_money($cashBalance)) ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>โครงการของฝ่าย</h2>
            <p>ปีงบประมาณ <?= $yearBE ? h($yearBE) : '-' ?></p>
        </div>
        <span class="badge badge-blue"><?= h(count($projects)) ?> โครงการ</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>รหัส</th>
                <th>โครงการ</th>
                <th class="text-right">ขอ</th>
                <th class="text-right">อนุมัติ</th>
                <th class="text-right">ใช้จริง</th>
                <th>สถานะ</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($projects)): ?>
                <tr><td colspan="7" class="empty-cell">ยังไม่มีโครงการของฝ่ายนี้</td></tr>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                    <tr>
                        <td><?= h($project['project_code']) ?></td>
                        <td><?= h($project['project_name']) ?></td>
                        <td class="text-right money-cell"><?= h(qa_money($project['requested_budget'])) ?></td>
                        <td class="text-right money-cell"><?= h(qa_money($project['approved_budget'])) ?></td>
                        <td class="text-right money-cell"><?= h(qa_money($project['actual_expense'])) ?></td>
                        <td><span class="badge badge-blue"><?= h($project['status_name'] ? $project['status_name'] : $project['status_code']) ?></span></td>
                        <td><a class="btn btn-sm" href="<?= h(qa_url('projects/view.php?id=' . urlencode($project['project_id']))) ?>">เปิด</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require QA_ROOT . '/includes/footer.php'; ?>
