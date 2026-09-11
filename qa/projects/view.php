<?php
$pageTitle = 'รายละเอียดโครงการ';
$activeMenu = 'projects';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

$db = qa_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    die('ไม่พบรหัสโครงการ');
}

$stmt = $db->prepare(
    "SELECT
        p.*,
        d.division_name,
        ps.status_name,
        pl.plan_code, pl.plan_name,
        CONCAT(COALESCE(u.prefix,''),COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS owner_name
     FROM qa_projects p
     INNER JOIN qa_divisions d ON d.division_id=p.division_id
     LEFT JOIN qa_project_statuses ps ON ps.status_code=p.status_code
     LEFT JOIN qa_plans pl ON pl.plan_id=p.plan_id
     LEFT JOIN qa_users u ON u.user_id=p.owner_user_id
     WHERE p.project_id=? LIMIT 1"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$project = $result ? $result->fetch_assoc() : null;
if ($result) $result->free();
$stmt->close();

if (!$project) {
    http_response_code(404);
    die('ไม่พบโครงการ');
}

$pageHeading = $project['project_code'] . ' — ' . $project['project_name'];
$pageDescription = 'ข้อมูลโครงการ ความสอดคล้อง งบประมาณ KPI และประวัติการดำเนินการ';
$breadcrumbs = array(
    array('label'=>'โครงการทั้งหมด','url'=>qa_url('projects/index.php')),
    array('label'=>$project['project_code'])
);

$message = '';
$messageType = 'success';
if (isset($_SESSION['qa_project_flash']) && is_array($_SESSION['qa_project_flash'])) {
    $messageType = isset($_SESSION['qa_project_flash']['type']) ? $_SESSION['qa_project_flash']['type'] : 'success';
    $message = isset($_SESSION['qa_project_flash']['text']) ? $_SESSION['qa_project_flash']['text'] : '';
    unset($_SESSION['qa_project_flash']);
}

$objectives = array();
$stmt = $db->prepare("SELECT objective_text FROM qa_project_objectives WHERE project_id=? ORDER BY sort_order, objective_id");
$stmt->bind_param('i',$id); $stmt->execute(); $result=$stmt->get_result();
if ($result) { while ($row=$result->fetch_assoc()) $objectives[]=$row; $result->free(); } $stmt->close();

$beneficiaries = array();
$stmt = $db->prepare("SELECT * FROM qa_project_beneficiaries WHERE project_id=? ORDER BY beneficiary_id");
$stmt->bind_param('i',$id); $stmt->execute(); $result=$stmt->get_result();
if ($result) { while ($row=$result->fetch_assoc()) $beneficiaries[]=$row; $result->free(); } $stmt->close();

$missions = array();
$stmt = $db->prepare(
    "SELECT m.mission_code, m.mission_text
     FROM qa_project_mission_links l INNER JOIN qa_missions m ON m.mission_id=l.mission_id
     WHERE l.project_id=? ORDER BY m.sort_order"
);
$stmt->bind_param('i',$id); $stmt->execute(); $result=$stmt->get_result();
if ($result) { while ($row=$result->fetch_assoc()) $missions[]=$row; $result->free(); } $stmt->close();

$strategies = array();
$stmt = $db->prepare(
    "SELECT s.strategy_code, s.strategy_name
     FROM qa_project_strategy_links l INNER JOIN qa_strategies s ON s.strategy_id=l.strategy_id
     WHERE l.project_id=? ORDER BY s.sort_order"
);
$stmt->bind_param('i',$id); $stmt->execute(); $result=$stmt->get_result();
if ($result) { while ($row=$result->fetch_assoc()) $strategies[]=$row; $result->free(); } $stmt->close();

$focusAreas = array();
$stmt = $db->prepare(
    "SELECT f.focus_code, f.focus_name
     FROM qa_project_focus_links l INNER JOIN qa_focus_areas f ON f.focus_id=l.focus_id
     WHERE l.project_id=? ORDER BY f.sort_order"
);
$stmt->bind_param('i',$id); $stmt->execute(); $result=$stmt->get_result();
if ($result) { while ($row=$result->fetch_assoc()) $focusAreas[]=$row; $result->free(); } $stmt->close();

$targets = array();
$stmt = $db->prepare(
    "SELECT t.target_code, t.target_name, t.target_value, t.target_unit
     FROM qa_project_target_links l INNER JOIN qa_plan_targets t ON t.target_id=l.target_id
     WHERE l.project_id=? ORDER BY t.sort_order"
);
$stmt->bind_param('i',$id); $stmt->execute(); $result=$stmt->get_result();
if ($result) { while ($row=$result->fetch_assoc()) $targets[]=$row; $result->free(); } $stmt->close();

$indicators = array();
$stmt = $db->prepare(
    "SELECT i.indicator_code, i.indicator_name, l.is_primary
     FROM qa_project_indicator_links l INNER JOIN qa_indicators i ON i.indicator_id=l.indicator_id
     WHERE l.project_id=? ORDER BY l.is_primary DESC, i.indicator_code"
);
$stmt->bind_param('i',$id); $stmt->execute(); $result=$stmt->get_result();
if ($result) { while ($row=$result->fetch_assoc()) $indicators[]=$row; $result->free(); } $stmt->close();

$budgetItems = array();
$stmt = $db->prepare(
    "SELECT
        a.activity_name, bi.expense_category, bi.item_name, bi.quantity, bi.unit_name,
        bi.unit_price, bi.requested_amount, bi.approved_amount,
        s.source_name, bp.pool_name
     FROM qa_project_budget_items bi
     LEFT JOIN qa_project_activities a ON a.activity_id=bi.activity_id
     LEFT JOIN qa_budget_pools bp ON bp.budget_pool_id=bi.budget_pool_id
     LEFT JOIN qa_budget_sources s ON s.source_id=bp.source_id
     WHERE bi.project_id=?
     ORDER BY bi.sort_order, bi.budget_item_id"
);
$stmt->bind_param('i',$id); $stmt->execute(); $result=$stmt->get_result();
if ($result) { while ($row=$result->fetch_assoc()) $budgetItems[]=$row; $result->free(); } $stmt->close();

$kpis = array();
$stmt = $db->prepare("SELECT * FROM qa_project_kpis WHERE project_id=? ORDER BY sort_order, kpi_id");
$stmt->bind_param('i',$id); $stmt->execute(); $result=$stmt->get_result();
if ($result) { while ($row=$result->fetch_assoc()) $kpis[]=$row; $result->free(); } $stmt->close();

$approvals = array();
$stmt = $db->prepare("SELECT * FROM qa_project_approvals WHERE project_id=? ORDER BY sequence_no, approval_id");
$stmt->bind_param('i',$id); $stmt->execute(); $result=$stmt->get_result();
if ($result) { while ($row=$result->fetch_assoc()) $approvals[]=$row; $result->free(); } $stmt->close();

$history = array();
$stmt = $db->prepare(
    "SELECT h.*, CONCAT(COALESCE(u.prefix,''),COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS changed_by_name,
            ps.status_name AS to_status_name
     FROM qa_project_status_history h
     LEFT JOIN qa_users u ON u.user_id=h.changed_by
     LEFT JOIN qa_project_statuses ps ON ps.status_code=h.to_status_code
     WHERE h.project_id=? ORDER BY h.changed_at DESC, h.history_id DESC"
);
$stmt->bind_param('i',$id); $stmt->execute(); $result=$stmt->get_result();
if ($result) { while ($row=$result->fetch_assoc()) $history[]=$row; $result->free(); } $stmt->close();

$comments = array();
$stmt = $db->prepare(
    "SELECT c.*, CONCAT(COALESCE(u.prefix,''),COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS creator_name
     FROM qa_project_comments c
     LEFT JOIN qa_users u ON u.user_id=c.created_by
     WHERE c.project_id=? ORDER BY c.created_at DESC, c.comment_id DESC"
);
$stmt->bind_param('i',$id); $stmt->execute(); $result=$stmt->get_result();
if ($result) { while ($row=$result->fetch_assoc()) $comments[]=$row; $result->free(); } $stmt->close();

$changeRequests = qa_project_change_list_for_project($id);
$pendingChangeRequest = qa_project_change_pending_request($id);
$allowedChangeTypes = qa_project_change_allowed_types($project);

$actualExpense = (float)qa_db_scalar("SELECT COALESCE(SUM(amount),0) FROM qa_expenditures WHERE project_id=" . $id . " AND payment_status='paid'",0);
$projectBalance = (float)$project['approved_budget'] - $actualExpense;

require QA_ROOT . '/includes/header.php';
?>

<?php if ($message !== ''): ?>
<div class="alert <?= $messageType === 'danger' ? 'alert-danger' : 'alert-success' ?>" style="margin-bottom:16px"><?= h($message) ?></div>
<?php endif; ?>

<div class="action-bar">
    <a class="btn" href="<?= h(qa_url('projects/index.php')) ?>">← โครงการทั้งหมด</a>
    <?php if (qa_project_can_edit_row($project)): ?>
        <a class="btn btn-primary" href="<?= h(qa_url('projects/edit.php?id=' . $id)) ?>">
            <?= $project['status_code'] === 'REVISION' ? 'แก้ไขตามข้อเสนอแนะ' : 'แก้ไขร่าง' ?>
        </a>
    <?php endif; ?>

    <?php if ($pendingChangeRequest): ?>
        <a class="btn" href="<?= h(qa_url('projects/changes.php?id=' . $pendingChangeRequest['request_id'])) ?>">คำขอแก้ไข/ยกเลิกรอพิจารณา</a>
    <?php elseif (qa_project_change_can_request($project) && !empty($allowedChangeTypes)): ?>
        <a class="btn" href="<?= h(qa_url('projects/change-request.php?id=' . $id)) ?>">ขอแก้ไข / ยกเลิก</a>
    <?php endif; ?>

    <a class="btn" href="<?= h(qa_url('approvals/index.php?id=' . $id)) ?>">ตรวจสอบ / อนุมัติ</a>
</div>

<?php if ($pendingChangeRequest): ?>
<div class="notice" style="margin-bottom:16px">
    <strong>โครงการถูกพัก Workflow ชั่วคราว:</strong>
    มี <?= h(qa_project_change_type_label($pendingChangeRequest['request_type'])) ?>
    #<?= h($pendingChangeRequest['request_id']) ?> รอพิจารณา
</div>
<?php endif; ?>

<div class="grid grid-4">
    <div class="card"><div class="metric-label">สถานะ</div><div style="margin-top:8px"><span class="badge badge-blue"><?= h($project['status_name'] ? $project['status_name'] : $project['status_code']) ?></span></div></div>
    <div class="card"><div class="metric-label">งบที่ขอ</div><div class="metric-value"><?= h(qa_money($project['requested_budget'])) ?></div></div>
    <div class="card"><div class="metric-label">งบอนุมัติ</div><div class="metric-value"><?= h(qa_money($project['approved_budget'])) ?></div></div>
    <div class="card"><div class="metric-label">เบิกจ่ายจริง</div><div class="metric-value"><?= h(qa_money($actualExpense)) ?></div><div class="metric-note">คงเหลือหลังอนุมัติ <?= h(qa_money($projectBalance)) ?></div></div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <h2>ข้อมูลโครงการ</h2>
        <table><tbody>
            <tr><th>รหัส</th><td><?= h($project['project_code']) ?></td></tr>
            <tr><th>ชื่อ</th><td><?= h($project['project_name']) ?></td></tr>
            <tr><th>ประเภท</th><td><?= h($project['project_type']) ?></td></tr>
            <tr><th>ฝ่าย</th><td><?= h($project['division_name']) ?></td></tr>
            <tr><th>ผู้รับผิดชอบ</th><td><?= h(trim($project['owner_name'])) ?></td></tr>
            <tr><th>แผน</th><td><?= h($project['plan_code'] . ' — ' . $project['plan_name']) ?></td></tr>
            <tr><th>ระยะเวลา</th><td><?= h(($project['start_date'] ? $project['start_date'] : '-') . ' ถึง ' . ($project['end_date'] ? $project['end_date'] : '-')) ?></td></tr>
            <tr><th>สถานที่</th><td><?= $project['location_text'] ? h($project['location_text']) : '-' ?></td></tr>
        </tbody></table>
    </div>

    <div class="card">
        <h2>กลุ่มเป้าหมาย</h2>
        <?php if (empty($beneficiaries)): ?>
            <div class="empty-cell">ไม่ได้ระบุ</div>
        <?php else: ?>
            <?php foreach ($beneficiaries as $b): ?>
            <div class="master-item">
                <div>
                    <strong><?= h($b['group_type']) ?></strong>
                    <?php if ($b['group_detail']): ?><div><?= h($b['group_detail']) ?></div><?php endif; ?>
                </div>
                <strong><?= $b['target_count'] ? h(number_format($b['target_count'])) . ' ' . h($b['unit_name']) : '-' ?></strong>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <h2>หลักการและเหตุผล</h2>
    <p class="project-long-text"><?= $project['principle_reason'] ? nl2br(h($project['principle_reason'])) : '-' ?></p>
    <?php if ($project['need_problem']): ?>
        <h3>ปัญหา / ความต้องการจำเป็น</h3>
        <p class="project-long-text"><?= nl2br(h($project['need_problem'])) ?></p>
    <?php endif; ?>
    <h3>วัตถุประสงค์</h3>
    <?php if (empty($objectives)): ?><div class="empty-cell">ยังไม่มีวัตถุประสงค์</div><?php else: ?>
    <ol>
        <?php foreach ($objectives as $objective): ?><li><?= h($objective['objective_text']) ?></li><?php endforeach; ?>
    </ol>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:16px">
    <h2>ความสอดคล้อง</h2>
    <div class="alignment-view-grid">
        <div><h3>พันธกิจ</h3><?php foreach ($missions as $r): ?><span class="badge badge-blue"><?= h($r['mission_code']) ?></span> <?= h($r['mission_text']) ?><br><?php endforeach; ?></div>
        <div><h3>กลยุทธ์</h3><?php foreach ($strategies as $r): ?><span class="badge badge-blue"><?= h($r['strategy_code']) ?></span> <?= h($r['strategy_name']) ?><br><?php endforeach; ?></div>
        <div><h3>จุดเน้น</h3><?php foreach ($focusAreas as $r): ?><span class="badge badge-blue"><?= h($r['focus_code']) ?></span> <?= h($r['focus_name']) ?><br><?php endforeach; ?></div>
        <div><h3>เป้าหมายแผน</h3><?php foreach ($targets as $r): ?><span class="badge badge-blue"><?= h($r['target_code']) ?></span> <?= h($r['target_name']) ?><br><?php endforeach; ?></div>
    </div>

    <h3 style="margin-top:16px">ตัวชี้วัด สมศ.</h3>
    <?php if (empty($indicators)): ?><div class="empty-cell">ยังไม่ได้เชื่อมตัวชี้วัด</div><?php else: ?>
        <?php foreach ($indicators as $r): ?>
            <div class="master-item">
                <div><span class="badge badge-green"><?= h($r['indicator_code']) ?></span> <?= h($r['indicator_name']) ?></div>
                <?= $r['is_primary'] ? '<strong>หลัก</strong>' : '' ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:16px">
    <h2>กิจกรรมและงบประมาณ</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>กิจกรรม</th><th>หมวด</th><th>รายการ</th><th>จำนวน</th><th class="text-right">ราคาต่อหน่วย</th><th>แหล่งเงิน</th><th class="text-right">ขอ</th><th class="text-right">อนุมัติ</th></tr></thead>
            <tbody>
            <?php if (empty($budgetItems)): ?>
                <tr><td colspan="8" class="empty-cell">ยังไม่มีรายการงบประมาณ</td></tr>
            <?php else: ?>
                <?php foreach ($budgetItems as $item): ?>
                <tr>
                    <td><?= h($item['activity_name']) ?></td>
                    <td><?= $item['expense_category'] ? h($item['expense_category']) : '-' ?></td>
                    <td><?= h($item['item_name']) ?></td>
                    <td><?= h(number_format((float)$item['quantity'],2)) ?> <?= h($item['unit_name']) ?></td>
                    <td class="text-right"><?= h(qa_money($item['unit_price'])) ?></td>
                    <td><?= h($item['source_name'] . ' / ' . $item['pool_name']) ?></td>
                    <td class="text-right"><?= h(qa_money($item['requested_amount'])) ?></td>
                    <td class="text-right"><?= h(qa_money($item['approved_amount'])) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <h2>KPI</h2>
        <?php if (empty($kpis)): ?><div class="empty-cell">ยังไม่มี KPI</div><?php else: ?>
            <?php foreach ($kpis as $kpi): ?>
            <div class="master-item">
                <div><strong><?= h($kpi['kpi_code'] . ' — ' . $kpi['kpi_name']) ?></strong><div class="subtle"><?= h($kpi['measurement_method']) ?></div></div>
                <strong><?= h($kpi['target_operator'] . ' ' . number_format((float)$kpi['target_value'],2) . ' ' . $kpi['target_unit']) ?></strong>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Output / Outcome ที่คาดหวัง</h2>
        <h3>Output</h3>
        <p class="project-long-text"><?= $project['expected_output'] ? nl2br(h($project['expected_output'])) : '-' ?></p>
        <h3>Outcome</h3>
        <p class="project-long-text"><?= $project['expected_outcome'] ? nl2br(h($project['expected_outcome'])) : '-' ?></p>
    </div>
</div>

<?php if (!empty($approvals)): ?>
<div class="card" style="margin-top:16px">
    <h2>ลำดับการตรวจสอบ / อนุมัติ</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ลำดับ</th><th>ขั้น</th><th>สถานะ</th><th>ความเห็น</th></tr></thead>
            <tbody>
            <?php foreach ($approvals as $a): ?>
                <tr><td><?= h($a['sequence_no']) ?></td><td><?= h($a['approval_stage']) ?></td><td><?= h($a['decision_status']) ?></td><td><?= $a['decision_comment'] ? h($a['decision_comment']) : '-' ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($comments)): ?>
<div class="card" style="margin-top:16px">
    <h2>ความเห็น / ข้อเสนอแนะ</h2>
    <?php foreach ($comments as $comment): ?>
    <div class="comment-item">
        <div class="comment-head">
            <strong><?= h(qa_project_stage_label($comment['comment_stage'])) ?></strong>
            <span><?= h($comment['created_at']) ?> • <?= h(trim($comment['creator_name'])) ?></span>
        </div>
        <div><?= nl2br(h($comment['comment_text'])) ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($changeRequests)): ?>
<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>ประวัติการแก้ไข / ยกเลิกโครงการ</h2>
            <p>คำขอทุกครั้งถูกเก็บไว้เพื่อ Audit Trail</p>
        </div>
        <span class="badge badge-blue"><?= h(count($changeRequests)) ?> คำขอ</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>ประเภท</th><th>เหตุผล / ข้อมูลแก้ไข</th><th>วิธีดำเนินการ</th><th>สถานะ</th><th>ผู้ขอ / ผู้พิจารณา</th><th></th></tr></thead>
            <tbody>
            <?php foreach($changeRequests as $cr): ?>
            <tr>
                <td><strong>#<?= h($cr['request_id']) ?></strong></td>
                <td><?= h(qa_project_change_type_label($cr['request_type'])) ?><div class="subtle"><?= h(qa_project_change_category_label($cr['request_category'])) ?></div></td>
                <td>
                    <?= nl2br(h($cr['reason'])) ?>
                    <?php if($cr['change_summary']): ?><div class="amendment-box" style="margin-top:8px"><?= nl2br(h($cr['change_summary'])) ?></div><?php endif; ?>
                </td>
                <td><?= h(qa_project_change_resolution_label($cr['resolution_mode'])) ?></td>
                <td><span class="badge <?= h(qa_project_change_status_badge($cr['request_status'])) ?>"><?= h(qa_project_change_status_label($cr['request_status'])) ?></span></td>
                <td>
                    <?= h(trim($cr['requester_name'])) ?>
                    <?php if($cr['reviewer_name']): ?><div class="subtle">พิจารณา: <?= h(trim($cr['reviewer_name'])) ?></div><?php endif; ?>
                </td>
                <td><a class="btn btn-sm" href="<?= h(qa_url('projects/changes.php?id='.$cr['request_id'])) ?>">เปิด</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
    <h2>ประวัติการดำเนินการ</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>วันที่</th><th>ผู้ดำเนินการ</th><th>สถานะ</th><th>รายละเอียด</th></tr></thead>
            <tbody>
            <?php foreach ($history as $hrow): ?>
                <tr>
                    <td><?= h($hrow['changed_at']) ?></td>
                    <td><?= h(trim($hrow['changed_by_name'])) ?></td>
                    <td><?= h($hrow['to_status_name'] ? $hrow['to_status_name'] : $hrow['to_status_code']) ?></td>
                    <td><?= $hrow['comment_text'] ? h($hrow['comment_text']) : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require QA_ROOT . '/includes/footer.php'; ?>
