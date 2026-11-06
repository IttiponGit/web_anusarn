<?php
$pageTitle = 'รายละเอียดโครงการ';
$activeMenu = 'projects';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();
qa_require_any_permission(array('project.view_all','project.view_division','project.view_own'));

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

$scopeSql="SELECT COUNT(*) FROM qa_projects p WHERE p.project_id=".(int)$id." AND (".qa_project_scope_condition('p').")";
if((int)qa_db_scalar($scopeSql,0)<=0){
    http_response_code(403);
    die('คุณไม่มีสิทธิ์ดูโครงการนี้');
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

$obecSubactivity = qa_obec_project_subactivity($id);

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

$projectActivities = array();
if(qa_activity_tables_ready()){
    $stmt=$db->prepare(
        "SELECT a.*,pa.activity_no AS parent_activity_no,pa.activity_name AS parent_activity_name,
                CONCAT(COALESCE(u.prefix,''),COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS responsible_name,
                COALESCE((SELECT SUM(bi.approved_amount) FROM qa_project_budget_items bi WHERE bi.activity_id=a.activity_id),0) AS approved_budget,
                (SELECT COUNT(*) FROM qa_evidences ev WHERE ev.activity_id=a.activity_id) AS evidence_count
         FROM qa_project_activities a
         LEFT JOIN qa_project_activities pa ON pa.activity_id=a.parent_activity_id
         LEFT JOIN qa_users u ON u.user_id=a.responsible_user_id
         WHERE a.project_id=?
         ORDER BY COALESCE(a.parent_activity_id,a.activity_id),CASE WHEN a.parent_activity_id IS NULL THEN 0 ELSE 1 END,a.sort_order,a.activity_id"
    );
    $stmt->bind_param('i',$id);$stmt->execute();$result=$stmt->get_result();
    if($result){while($row=$result->fetch_assoc())$projectActivities[]=$row;$result->free();}$stmt->close();
}

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
$stmt = $db->prepare(
    "SELECT a.*,
" .
    "       CONCAT(COALESCE(u.prefix,''),COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS approver_name,
" .
    "       u.position_name AS approver_position
" .
    "FROM qa_project_approvals a
" .
    "LEFT JOIN qa_users u ON u.user_id=a.approver_user_id
" .
    "WHERE a.project_id=? ORDER BY a.sequence_no,a.approval_id"
);
$stmt->bind_param('i',$id); $stmt->execute(); $result=$stmt->get_result();
if ($result) { while ($row=$result->fetch_assoc()) $approvals[]=$row; $result->free(); } $stmt->close();

$approvalResponsibleUsers = array();
foreach (array('plan','budget','director') as $approvalStageCode) {
    $approvalResponsibleUsers[$approvalStageCode] = qa_project_stage_responsible_users($approvalStageCode);
}

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

<div class="project-detail-page">
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

<div class="card obec-project-classification" style="margin-top:16px">
    <div class="section-heading"><div><h2>กิจกรรมย่อย สพฐ.</h2><p>การจำแนกปริมาณงานสำหรับแบบ 10 โรงเรียนการศึกษาพิเศษ</p></div><a class="btn btn-sm" href="<?= h(qa_url('reports/obec-workload.php')) ?>">ดูรายงานปริมาณงาน</a></div>
    <?php if($obecSubactivity): ?>
        <div class="obec-code-line"><span class="obec-code-badge"><?= h($obecSubactivity['subactivity_code']) ?></span><strong><?= h($obecSubactivity['subactivity_name']) ?></strong></div>
        <div class="help-text">หน่วยนับ: <?= h($obecSubactivity['unit_name']) ?><?= $obecSubactivity['division_name']?' • Mapping: '.h($obecSubactivity['division_name']):'' ?></div>
    <?php else: ?><div class="notice">โครงการนี้ยังไม่ได้กำหนดกิจกรรมย่อย สพฐ.</div><?php endif; ?>
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
    <div class="section-heading">
        <div><h2>กิจกรรมภายใต้โครงการ</h2><p>ผู้รับผิดชอบรายกิจกรรม ความก้าวหน้า และหลักฐานของแต่ละกิจกรรม</p></div>
        <?php if(qa_activity_tables_ready() && qa_activity_can_manage_project($project)): ?><a class="btn btn-sm" href="<?= h(qa_url('projects/activities.php?project_id='.$id)) ?>">จัดการกิจกรรม</a><?php endif; ?>
    </div>
    <?php if(!qa_activity_tables_ready()): ?><div class="notice">ยังไม่ได้ติดตั้ง Phase 4.2</div>
    <?php elseif(empty($projectActivities)): ?><div class="empty-cell">ยังไม่มีกิจกรรมภายใต้โครงการ</div>
    <?php else: ?><div class="activity-project-list">
        <?php foreach($projectActivities as $a): $displayNo=qa_activity_display_no($a); $isChild=qa_activity_is_child($a); ?>
        <a class="activity-project-row <?= $isChild?'is-child':'' ?>" href="<?= h(qa_url('activities/view.php?id='.$a['activity_id'])) ?>">
            <span class="activity-no"><?= h($displayNo) ?></span>
            <span class="activity-main"><strong><?= h($a['activity_name']) ?></strong><small><?= $isChild?'กิจกรรมย่อย • ':'' ?><?= trim($a['responsible_name'])?'ผู้รับผิดชอบ '.h(trim($a['responsible_name'])):'ยังไม่มอบหมายผู้รับผิดชอบ' ?> • งบ <?= h(qa_money($a['approved_budget'])) ?> • หลักฐาน <?= h($a['evidence_count']) ?></small></span>
            <span class="activity-progress"><?= h(number_format((float)$a['progress_percent'],0)) ?>%</span>
            <span class="badge <?= h(qa_activity_status_badge($a['status'])) ?>"><?= h(qa_activity_status_label($a['status'])) ?></span>
        </a>
        <?php endforeach; ?>
    </div><?php endif; ?>
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

<div class="grid grid-2 project-kpi-outcome-grid" style="margin-top:16px">
    <div class="card project-kpi-card">
        <div class="project-section-heading">
            <h2>KPI</h2>
            <p>ตัวชี้วัดความสำเร็จและค่าเป้าหมายของโครงการ</p>
        </div>
        <?php if (empty($kpis)): ?><div class="empty-cell">ยังไม่มี KPI</div><?php else: ?>
            <div class="project-kpi-list">
            <?php foreach ($kpis as $kpi): ?>
                <div class="project-kpi-item">
                    <div class="project-kpi-main">
                        <strong><?= h($kpi['kpi_code'] . ' — ' . $kpi['kpi_name']) ?></strong>
                        <div class="project-meta-text"><?= h($kpi['measurement_method']) ?></div>
                    </div>
                    <div class="project-kpi-target">
                        <span>เป้าหมาย</span>
                        <strong><?= h($kpi['target_operator'] . ' ' . number_format((float)$kpi['target_value'],2) . ' ' . $kpi['target_unit']) ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card project-outcomes-card">
        <div class="project-section-heading">
            <h2>ผลผลิต / ผลลัพธ์ที่คาดหวัง</h2>
            <p>สิ่งที่เกิดขึ้นโดยตรงจากการดำเนินงาน และการเปลี่ยนแปลงที่คาดว่าจะเกิดขึ้น</p>
        </div>
        <section class="project-outcome-section">
            <h3>ผลผลิต</h3>
            <p class="project-long-text"><?= $project['expected_output'] ? nl2br(h($project['expected_output'])) : '-' ?></p>
        </section>
        <section class="project-outcome-section">
            <h3>ผลลัพธ์</h3>
            <p class="project-long-text"><?= $project['expected_outcome'] ? nl2br(h($project['expected_outcome'])) : '-' ?></p>
        </section>
    </div>
</div>

<?php if (!empty($approvals)): ?>
<div class="card project-approval-card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>ลำดับการตรวจสอบและอนุมัติโครงการ</h2>
            <p>แสดงขั้นตอน ผู้มีหน้าที่ดำเนินการ สถานะปัจจุบัน และผลการพิจารณาของแต่ละขั้น</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="approval-sequence-table">
            <thead>
                <tr>
                    <th style="width:60px">ลำดับ</th>
                    <th>ขั้นตอนการตรวจสอบ</th>
                    <th>ผู้รับผิดชอบ / ผู้ดำเนินการ</th>
                    <th>สถานะ</th>
                    <th>ความเห็น / ผลการพิจารณา</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($approvals as $a): ?>
                <?php
                    $stageCode = $a['approval_stage'];
                    $responsibleUsers = isset($approvalResponsibleUsers[$stageCode]) ? $approvalResponsibleUsers[$stageCode] : array();
                    $decisionLabel = qa_project_decision_label_for_stage($stageCode,$a['decision_status']);
                    $statusClass = $a['decision_status']==='approved' ? 'badge-green' : ($a['decision_status']==='pending' ? 'badge-gray' : ($a['decision_status']==='revision' ? 'badge-yellow' : 'badge-red'));
                ?>
                <tr>
                    <td><strong><?= h($a['sequence_no']) ?></strong></td>
                    <td>
                        <strong><?= h(qa_project_stage_label($stageCode)) ?></strong>
                        <?php if (qa_project_stage_description($stageCode)!==''): ?>
                            <div class="approval-stage-note"><?= h(qa_project_stage_description($stageCode)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($a['approver_user_id'])): ?>
                            <div class="approval-person"><strong><?= h(trim($a['approver_name'])) ?></strong><?php if($a['approver_position']): ?><small><?= h($a['approver_position']) ?></small><?php endif; ?></div>
                            <div class="approval-actor-note">ดำเนินการแล้ว</div>
                        <?php elseif (empty($responsibleUsers)): ?>
                            <span class="subtle">ยังไม่พบผู้ใช้ที่ได้รับสิทธิ์ในขั้นตอนนี้</span>
                        <?php else: ?>
                            <div class="approval-candidate-title">ผู้มีสิทธิ์ดำเนินการ</div>
                            <div class="approval-candidate-list">
                                <?php foreach($responsibleUsers as $ru): ?>
                                    <span><?= h(trim($ru['prefix'].$ru['first_name'].' '.$ru['last_name'])) ?><?php if($ru['position_name']): ?> <small>(<?= h($ru['position_name']) ?>)</small><?php endif; ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= h($statusClass) ?>"><?= h($decisionLabel) ?></span></td>
                    <td>
                        <?php if ($a['decision_comment']): ?>
                            <?= nl2br(h($a['decision_comment'])) ?>
                        <?php elseif ($a['decision_status']==='pending'): ?>
                            <span class="subtle">ยังไม่มีผลการพิจารณา</span>
                        <?php else: ?>
                            <span class="subtle">ไม่มีความเห็นเพิ่มเติม</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($comments)): ?>
<div class="card project-comments-card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>บันทึกการดำเนินการและข้อเสนอแนะ</h2>
            <p>แสดงว่าใครดำเนินการอะไรในโครงการ พร้อมข้อความที่บันทึกไว้ในแต่ละครั้ง</p>
        </div>
        <span class="badge badge-blue"><?= h(count($comments)) ?> รายการ</span>
    </div>
    <?php foreach ($comments as $comment): ?>
    <div class="comment-item project-comment-item">
        <div class="comment-head project-comment-head">
            <div>
                <strong><?= h(qa_project_comment_type_label($comment['comment_type'])) ?></strong>
                <span class="comment-stage-badge"><?= h(qa_project_stage_label($comment['comment_stage'])) ?></span>
            </div>
            <span><?= h(qa_project_thai_datetime($comment['created_at'])) ?> • <?= h(trim($comment['creator_name'])) ?></span>
        </div>
        <div class="project-comment-text"><?= nl2br(h($comment['comment_text'])) ?></div>
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

</div><!-- /.project-detail-page -->

<?php require QA_ROOT . '/includes/footer.php'; ?>
