<?php
$pageTitle = 'ติดตามและประเมินผล';
$pageHeading = 'ติดตามและประเมินผลโครงการ';
$pageDescription = 'ติดตามโครงการที่อนุมัติแล้ว ความก้าวหน้า การใช้จ่าย KPI ผลลัพธ์ และสถานะการรายงานผล';
$activeMenu = 'monitoring';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

$db = qa_db();
$currentYear = qa_current_year_row();
$yearId = $currentYear ? (int)$currentYear['year_id'] : 0;
$yearBE = $currentYear ? (int)$currentYear['fiscal_year_be'] : 0;

$breadcrumbs = array(array('label'=>'ติดตามและประเมินผล'));

$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
$filterDivision = isset($_GET['division']) ? (int)$_GET['division'] : 0;
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$divisions = array();
$result = $db->query("SELECT division_id, division_name FROM qa_divisions WHERE is_active=1 ORDER BY sort_order, division_id");
if ($result) {
    while ($row = $result->fetch_assoc()) $divisions[] = $row;
    $result->free();
}

$allowedStatuses = array('APPROVED','IN_PROGRESS','WAITING_REPORT','COMPLETED','CLOSED');
$statusMap = array();
$result = $db->query(
    "SELECT status_code,status_name FROM qa_project_statuses
     WHERE status_code IN ('APPROVED','IN_PROGRESS','WAITING_REPORT','COMPLETED','CLOSED')
     ORDER BY sort_order"
);
if ($result) {
    while ($row = $result->fetch_assoc()) $statusMap[$row['status_code']] = $row['status_name'];
    $result->free();
}

$countApproved = $yearId > 0 ? (int)qa_db_scalar(
    "SELECT COUNT(*) FROM qa_projects WHERE year_id=".$yearId." AND status_code='APPROVED'", 0
) : 0;
$countProgress = $yearId > 0 ? (int)qa_db_scalar(
    "SELECT COUNT(*) FROM qa_projects WHERE year_id=".$yearId." AND status_code='IN_PROGRESS'", 0
) : 0;
$countWaiting = $yearId > 0 ? (int)qa_db_scalar(
    "SELECT COUNT(*) FROM qa_projects WHERE year_id=".$yearId." AND status_code='WAITING_REPORT'", 0
) : 0;
$countCompleted = $yearId > 0 ? (int)qa_db_scalar(
    "SELECT COUNT(*) FROM qa_projects WHERE year_id=".$yearId." AND status_code IN ('COMPLETED','CLOSED')", 0
) : 0;

$where = array();
$where[] = "p.year_id=".(int)$yearId;
$where[] = "p.status_code IN ('APPROVED','IN_PROGRESS','WAITING_REPORT','COMPLETED','CLOSED')";

if ($filterDivision > 0) {
    $where[] = "p.division_id=".$filterDivision;
}

if (in_array($filterStatus, $allowedStatuses, true)) {
    $where[] = "p.status_code='".$db->real_escape_string($filterStatus)."'";
} else {
    $filterStatus = '';
}

if ($search !== '') {
    $esc = $db->real_escape_string($search);
    $where[] = "(p.project_code LIKE '%".$esc."%' OR p.project_name LIKE '%".$esc."%')";
}

$projects = array();
$sql = "SELECT
            p.project_id,p.project_code,p.project_name,p.status_code,p.start_date,p.end_date,
            p.approved_budget,d.division_name,ps.status_name,
            COALESCE((
                SELECT pr.progress_percent
                FROM qa_progress_reports pr
                WHERE pr.project_id=p.project_id
                ORDER BY pr.report_no DESC, pr.progress_report_id DESC
                LIMIT 1
            ),0) AS progress_percent,
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
                FROM qa_evidences ev
                WHERE ev.project_id=p.project_id
            ),0) AS evidence_count
        FROM qa_projects p
        INNER JOIN qa_divisions d ON d.division_id=p.division_id
        LEFT JOIN qa_project_statuses ps ON ps.status_code=p.status_code
        WHERE ".implode(' AND ', $where)."
        ORDER BY
          CASE p.status_code
            WHEN 'IN_PROGRESS' THEN 1
            WHEN 'WAITING_REPORT' THEN 2
            WHEN 'APPROVED' THEN 3
            WHEN 'COMPLETED' THEN 4
            ELSE 5
          END,
          p.project_id DESC";

$result = $db->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) $projects[] = $row;
    $result->free();
}

require QA_ROOT . '/includes/header.php';
?>

<div class="grid grid-4">
    <div class="card"><div class="metric-label">อนุมัติแล้ว / รอเริ่ม</div><div class="metric-value"><?= h($countApproved) ?></div><div class="metric-note">พร้อมเริ่มดำเนินงาน</div></div>
    <div class="card"><div class="metric-label">กำลังดำเนินการ</div><div class="metric-value"><?= h($countProgress) ?></div><div class="metric-note">มีการติดตามความก้าวหน้า</div></div>
    <div class="card"><div class="metric-label">รอรายงานผล</div><div class="metric-value"><?= h($countWaiting) ?></div><div class="metric-note">รอตรวจผลการดำเนินงาน</div></div>
    <div class="card"><div class="metric-label">เสร็จสิ้น / ปิดโครงการ</div><div class="metric-value"><?= h($countCompleted) ?></div><div class="metric-note">ผ่านการสรุปผลแล้ว</div></div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>ตัวกรอง</h2><p>ค้นหาโครงการที่อยู่ในขั้นดำเนินงานและติดตามผล</p></div>
    </div>
    <form method="get" class="form-grid">
        <div class="form-group">
            <label>ฝ่าย</label>
            <select name="division">
                <option value="0">ทุกฝ่าย</option>
                <?php foreach ($divisions as $division): ?>
                    <option value="<?= h($division['division_id']) ?>" <?= (int)$division['division_id']===$filterDivision?'selected':'' ?>>
                        <?= h($division['division_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>สถานะ</label>
            <select name="status">
                <option value="">ทุกสถานะ</option>
                <?php foreach ($statusMap as $code=>$name): ?>
                    <option value="<?= h($code) ?>" <?= $code===$filterStatus?'selected':'' ?>><?= h($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>ค้นหา</label>
            <input name="q" value="<?= h($search) ?>" placeholder="รหัสหรือชื่อโครงการ">
        </div>
        <div class="form-group" style="align-self:end">
            <button class="btn btn-primary" type="submit">ค้นหา</button>
            <a class="btn" href="<?= h(qa_url('monitoring/index.php')) ?>">ล้าง</a>
        </div>
    </form>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>รายการติดตาม</h2><p>ข้อมูลจริงจากโครงการที่ผ่านการอนุมัติแล้ว</p></div>
        <span class="badge badge-blue"><?= h(count($projects)) ?> รายการ</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>โครงการ</th>
                <th>ฝ่าย</th>
                <th>ระยะเวลา</th>
                <th>ความก้าวหน้า</th>
                <th class="text-right">งบอนุมัติ</th>
                <th class="text-right">ใช้จริง</th>
                <th>KPI / หลักฐาน</th>
                <th>สถานะ</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($projects)): ?>
                <tr><td colspan="9" class="empty-cell">ยังไม่มีโครงการในขั้นติดตามตามเงื่อนไขที่เลือก</td></tr>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                <?php
                    $pct = (float)$project['progress_percent'];
                    if ($project['status_code']==='COMPLETED' || $project['status_code']==='CLOSED') $pct = 100;
                    if ($pct < 0) $pct = 0;
                    if ($pct > 100) $pct = 100;
                ?>
                <tr>
                    <td>
                        <strong><?= h($project['project_code']) ?></strong>
                        <div><?= h($project['project_name']) ?></div>
                    </td>
                    <td><?= h($project['division_name']) ?></td>
                    <td><?= h(($project['start_date']?$project['start_date']:'-').' ถึง '.($project['end_date']?$project['end_date']:'-')) ?></td>
                    <td style="min-width:180px">
                        <div class="progress-head"><span><?= h(number_format($pct,0)) ?>%</span></div>
                        <div class="progress"><span style="width:<?= h(number_format($pct,2,'.','')) ?>%"></span></div>
                    </td>
                    <td class="text-right money-cell"><?= h(qa_money($project['approved_budget'])) ?></td>
                    <td class="text-right money-cell"><?= h(qa_money($project['actual_expense'])) ?></td>
                    <td><?= h($project['kpi_count']) ?> KPI / <?= h($project['evidence_count']) ?> หลักฐาน</td>
                    <td><span class="badge badge-blue"><?= h($project['status_name']?$project['status_name']:$project['status_code']) ?></span></td>
                    <td><a class="btn btn-sm btn-primary" href="<?= h(qa_url('monitoring/project.php?id='.$project['project_id'])) ?>">ติดตาม</a></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require QA_ROOT . '/includes/footer.php'; ?>
