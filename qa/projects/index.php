<?php
$pageTitle = 'โครงการทั้งหมด';
$pageHeading = 'โครงการ / งาน / กิจกรรม';
$pageDescription = 'ค้นหา กรอง และติดตามสถานะโครงการทั้งหมดในปีงบประมาณที่ใช้งาน';
$activeMenu = 'projects';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

$db = qa_db();
$currentYear = qa_current_year_row();
$yearId = $currentYear ? (int)$currentYear['year_id'] : 0;
$yearBE = $currentYear ? (int)$currentYear['fiscal_year_be'] : 0;

$breadcrumbs = array(array('label'=>'โครงการทั้งหมด'));

$message = '';
$messageType = 'success';
if (isset($_SESSION['qa_project_flash']) && is_array($_SESSION['qa_project_flash'])) {
    $messageType = isset($_SESSION['qa_project_flash']['type']) ? $_SESSION['qa_project_flash']['type'] : 'success';
    $message = isset($_SESSION['qa_project_flash']['text']) ? $_SESSION['qa_project_flash']['text'] : '';
    unset($_SESSION['qa_project_flash']);
}

$divisions = array();
$result = $db->query("SELECT division_id, division_name FROM qa_divisions WHERE is_active=1 ORDER BY sort_order, division_id");
if ($result) {
    while ($row = $result->fetch_assoc()) $divisions[] = $row;
    $result->free();
}

$statuses = array();
$result = $db->query("SELECT status_code, status_name FROM qa_project_statuses ORDER BY sort_order");
if ($result) {
    while ($row = $result->fetch_assoc()) $statuses[] = $row;
    $result->free();
}

$filterDivision = isset($_GET['division']) ? (int)$_GET['division'] : 0;
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$where = array();
$where[] = "p.year_id=" . $yearId;

if ($filterDivision > 0) $where[] = "p.division_id=" . $filterDivision;

$validStatus = '';
foreach ($statuses as $status) {
    if ($status['status_code'] === $filterStatus) {
        $validStatus = $filterStatus;
        break;
    }
}
if ($validStatus !== '') {
    $where[] = "p.status_code='" . $db->real_escape_string($validStatus) . "'";
}

if ($search !== '') {
    $esc = $db->real_escape_string($search);
    $where[] = "(p.project_code LIKE '%" . $esc . "%' OR p.project_name LIKE '%" . $esc . "%')";
}

$sql = "SELECT
            p.project_id, p.project_code, p.project_name, p.project_type,
            p.requested_budget, p.approved_budget, p.status_code, p.owner_user_id, p.division_id,
            p.created_at, p.submitted_at,
            d.division_name, ps.status_name,
            CONCAT(COALESCE(u.prefix,''), COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS owner_name
        FROM qa_projects p
        INNER JOIN qa_divisions d ON d.division_id=p.division_id
        LEFT JOIN qa_project_statuses ps ON ps.status_code=p.status_code
        LEFT JOIN qa_users u ON u.user_id=p.owner_user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.project_id DESC";

$projects = array();
$result = $db->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) $projects[] = $row;
    $result->free();
}

$totalRequested = 0.0;
$totalApproved = 0.0;
foreach ($projects as $project) {
    $totalRequested += (float)$project['requested_budget'];
    $totalApproved += (float)$project['approved_budget'];
}

require QA_ROOT . '/includes/header.php';
?>

<?php if ($message !== ''): ?>
<div class="alert <?= $messageType === 'danger' ? 'alert-danger' : 'alert-success' ?>" style="margin-bottom:16px"><?= h($message) ?></div>
<?php endif; ?>

<div class="action-bar">
    <a class="btn btn-primary" href="<?= h(qa_url('projects/create.php')) ?>">＋ เสนอโครงการใหม่</a>
    <a class="btn" href="<?= h(qa_url('approvals/index.php')) ?>">ไปหน้าตรวจสอบ / อนุมัติ</a>
    <?php if (qa_user_has_role('admin')): ?>
        <a class="btn btn-danger" href="<?= h(qa_url('settings/test-data-cleanup.php')) ?>">ล้างข้อมูลโครงการทดสอบ</a>
    <?php endif; ?>
</div>

<div class="grid grid-3">
    <div class="card"><div class="metric-label">โครงการที่แสดง</div><div class="metric-value"><?= h(count($projects)) ?></div><div class="metric-note">ปีงบประมาณ <?= h($yearBE) ?></div></div>
    <div class="card"><div class="metric-label">งบที่ขอรวม</div><div class="metric-value"><?= h(qa_money($totalRequested)) ?></div><div class="metric-note">ตามตัวกรองปัจจุบัน</div></div>
    <div class="card"><div class="metric-label">งบอนุมัติรวม</div><div class="metric-value"><?= h(qa_money($totalApproved)) ?></div><div class="metric-note">ตามตัวกรองปัจจุบัน</div></div>
</div>

<div class="card" style="margin-top:16px">
    <h2>ตัวกรอง</h2>
    <form method="get" class="form-grid">
        <div class="form-group">
            <label>ฝ่าย</label>
            <select name="division">
                <option value="0">ทุกฝ่าย</option>
                <?php foreach ($divisions as $division): ?>
                <option value="<?= h($division['division_id']) ?>" <?= (int)$division['division_id'] === $filterDivision ? 'selected' : '' ?>>
                    <?= h($division['division_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>สถานะ</label>
            <select name="status">
                <option value="">ทุกสถานะ</option>
                <?php foreach ($statuses as $status): ?>
                <option value="<?= h($status['status_code']) ?>" <?= $status['status_code'] === $validStatus ? 'selected' : '' ?>>
                    <?= h($status['status_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>ค้นหา</label>
            <input name="q" value="<?= h($search) ?>" placeholder="รหัสหรือชื่อโครงการ">
        </div>
        <div class="form-group" style="align-self:end">
            <button class="btn btn-primary" type="submit">ค้นหา</button>
            <a class="btn" href="<?= h(qa_url('projects/index.php')) ?>">ล้าง</a>
        </div>
    </form>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>รายการโครงการ</h2><p>ข้อมูลจริงจากฐาน `qa_projects`</p></div>
        <span class="badge badge-blue"><?= h(count($projects)) ?> รายการ</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>รหัส</th>
                <th>ชื่อโครงการ</th>
                <th>ฝ่าย</th>
                <th class="text-right">งบที่ขอ</th>
                <th class="text-right">อนุมัติ</th>
                <th>ผู้รับผิดชอบ</th>
                <th>สถานะ</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($projects)): ?>
                <tr><td colspan="8" class="empty-cell">ยังไม่มีโครงการตามเงื่อนไขที่เลือก</td></tr>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                <tr>
                    <td><strong><?= h($project['project_code']) ?></strong></td>
                    <td><?= h($project['project_name']) ?></td>
                    <td><?= h($project['division_name']) ?></td>
                    <td class="text-right money-cell"><?= h(qa_money($project['requested_budget'])) ?></td>
                    <td class="text-right money-cell"><?= h(qa_money($project['approved_budget'])) ?></td>
                    <td><?= h(trim($project['owner_name'])) ?></td>
                    <td><span class="badge badge-blue"><?= h($project['status_name'] ? $project['status_name'] : $project['status_code']) ?></span></td>
                    <td>
    <div class="row-actions">
        <a class="btn btn-sm" href="<?= h(qa_url('projects/view.php?id=' . $project['project_id'])) ?>">เปิด</a>
        <?php if (in_array($project['status_code'], array('DRAFT','REVISION'), true)): ?>
            <a class="btn btn-sm" href="<?= h(qa_url('projects/edit.php?id=' . $project['project_id'])) ?>">แก้ไข</a>
        <?php endif; ?>

        <?php
        $pendingChange = qa_project_change_pending_request((int)$project['project_id']);
        $allowedChangeTypes = qa_project_change_allowed_types($project);
        ?>
        <?php if ($pendingChange): ?>
            <a class="btn btn-sm" href="<?= h(qa_url('projects/changes.php?id=' . $pendingChange['request_id'])) ?>">คำขอรอพิจารณา</a>
        <?php elseif (qa_project_change_can_request($project) && !empty($allowedChangeTypes)): ?>
            <a class="btn btn-sm" href="<?= h(qa_url('projects/change-request.php?id=' . $project['project_id'])) ?>">ขอแก้ไข/ยกเลิก</a>
        <?php endif; ?>
    </div>
</td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require QA_ROOT . '/includes/footer.php'; ?>
