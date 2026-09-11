<?php
$pageTitle = 'ตรวจสอบและอนุมัติ';
$pageHeading = 'ตรวจสอบ / อนุมัติโครงการ';
$pageDescription = 'Workflow จริงสำหรับงานแผน งานงบประมาณ และผู้อำนวยการ พร้อมประวัติการตัดสินใจ';
$activeMenu = 'approvals';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

$db = qa_db();
$user = qa_current_user();
$userId = $user ? (int)$user['user_id'] : 0;
$currentYear = qa_current_year_row();
$yearId = $currentYear ? (int)$currentYear['year_id'] : 0;
$yearBE = $currentYear ? (int)$currentYear['fiscal_year_be'] : 0;

$breadcrumbs = array(array('label' => 'ตรวจสอบ / อนุมัติ'));

function approval_redirect($query)
{
    $url = qa_url('approvals/index.php');
    if ($query !== '') $url .= '?' . $query;
    header('Location: ' . $url);
    exit;
}

function approval_flash($type, $text)
{
    $_SESSION['qa_approval_flash'] = array('type'=>$type, 'text'=>$text);
}

function approval_load_project($projectId)
{
    $db = qa_db();
    $stmt = $db->prepare(
        "SELECT p.*, d.division_name, ps.status_name,
                pl.plan_code, pl.plan_name,
                CONCAT(COALESCE(u.prefix,''),COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS owner_name
         FROM qa_projects p
         INNER JOIN qa_divisions d ON d.division_id=p.division_id
         LEFT JOIN qa_project_statuses ps ON ps.status_code=p.status_code
         LEFT JOIN qa_plans pl ON pl.plan_id=p.plan_id
         LEFT JOIN qa_users u ON u.user_id=p.owner_user_id
         WHERE p.project_id=? LIMIT 1"
    );
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if ($result) $result->free();
    $stmt->close();
    return $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!qa_verify_csrf($token)) {
        http_response_code(400);
        die('CSRF token ไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }

    $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    $approvalId = isset($_POST['approval_id']) ? (int)$_POST['approval_id'] : 0;
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    $project = approval_load_project($projectId);
    $activeApproval = qa_project_active_approval($projectId);

    if (!$project || !$activeApproval || (int)$activeApproval['approval_id'] !== $approvalId) {
        approval_flash('danger', 'รายการนี้ไม่ได้อยู่ในขั้นตรวจสอบที่สามารถดำเนินการได้แล้ว');
        approval_redirect('');
    }

    $stage = $activeApproval['approval_stage'];
    if (!qa_project_stage_allowed($stage)) {
        http_response_code(403);
        die('บัญชีของคุณไม่มีสิทธิ์ดำเนินการในขั้นนี้');
    }

    if (!in_array($action, array('approve','revision','reject'), true)) {
        approval_flash('danger', 'คำสั่งไม่ถูกต้อง');
        approval_redirect('id=' . $projectId);
    }

    if (($action === 'revision' || $action === 'reject') && $comment === '') {
        approval_flash('danger', 'กรุณาระบุเหตุผลหรือข้อเสนอแนะ');
        approval_redirect('id=' . $projectId);
    }

    $oldStatus = $project['status_code'];
    $db->autocommit(false);
    $ok = true;
    $errorText = '';

    try {
        /* งานงบประมาณ: กำหนดวงเงินจริงที่อนุมัติ */
        if ($stage === 'budget' && $action === 'approve') {
            $approvedInput = isset($_POST['approved_amount']) && is_array($_POST['approved_amount'])
                ? $_POST['approved_amount'] : array();

            $items = array();
            $stmt = $db->prepare(
                "SELECT budget_item_id, budget_pool_id, item_name, requested_amount
                 FROM qa_project_budget_items
                 WHERE project_id=?
                 ORDER BY sort_order, budget_item_id"
            );
            $stmt->bind_param('i', $projectId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) $items[] = $row;
                $result->free();
            }
            $stmt->close();

            $proposedByPool = array();
            $approvedTotal = 0.0;
            $approvedValues = array();

            foreach ($items as $item) {
                $itemId = (int)$item['budget_item_id'];
                $raw = isset($approvedInput[$itemId]) ? str_replace(',', '', trim($approvedInput[$itemId])) : '';
                if ($raw === '' || !is_numeric($raw)) {
                    throw new Exception('กรุณาระบุวงเงินอนุมัติให้ครบทุกรายการ');
                }
                $amount = (float)$raw;
                $requested = (float)$item['requested_amount'];

                if ($amount < 0 || $amount > $requested + 0.005) {
                    throw new Exception('วงเงินอนุมัติของรายการ "' . $item['item_name'] . '" ต้องอยู่ระหว่าง 0 ถึง ' . qa_money($requested) . ' บาท');
                }

                $approvedValues[$itemId] = $amount;
                $approvedTotal += $amount;

                $poolId = (int)$item['budget_pool_id'];
                if (!isset($proposedByPool[$poolId])) $proposedByPool[$poolId] = 0.0;
                $proposedByPool[$poolId] += $amount;
            }

            foreach ($proposedByPool as $poolId => $amount) {
                $capacity = qa_project_pool_capacity(
                    (int)$poolId,
                    (int)$project['division_id'],
                    (int)$project['year_id'],
                    $projectId
                );
                if ($amount > $capacity['available'] + 0.005) {
                    throw new Exception(
                        'วงเงินอนุมัติจากก้อนงบหนึ่งเกินวงเงินพร้อมใช้ ' .
                        qa_money($capacity['available']) . ' บาท'
                    );
                }
            }

            foreach ($approvedValues as $itemId => $amount) {
                $stmt = $db->prepare(
                    "UPDATE qa_project_budget_items
                     SET approved_amount=?
                     WHERE budget_item_id=? AND project_id=?"
                );
                $stmt->bind_param('dii', $amount, $itemId, $projectId);
                if (!$stmt->execute()) throw new Exception('ไม่สามารถบันทึกวงเงินอนุมัติรายรายการได้');
                $stmt->close();
            }

            $stmt = $db->prepare("UPDATE qa_projects SET approved_budget=? WHERE project_id=?");
            $stmt->bind_param('di', $approvedTotal, $projectId);
            if (!$stmt->execute()) throw new Exception('ไม่สามารถบันทึกวงเงินอนุมัติรวมได้');
            $stmt->close();
        }

        $decision = $action === 'approve'
            ? 'approved'
            : ($action === 'revision' ? 'revision' : 'rejected');

        $stmt = $db->prepare(
            "UPDATE qa_project_approvals
             SET approver_user_id=?,
                 decision_status=?,
                 decision_comment=NULLIF(?, ''),
                 decision_at=NOW()
             WHERE approval_id=? AND project_id=?"
        );
        $stmt->bind_param('issii', $userId, $decision, $comment, $approvalId, $projectId);
        if (!$stmt->execute()) throw new Exception('ไม่สามารถบันทึกผลการตรวจสอบได้');
        $stmt->close();

        $newStatus = $oldStatus;
        $historyComment = '';

        if ($action === 'approve') {
            if ($stage === 'plan') {
                $newStatus = 'BUDGET_REVIEW';
                $historyComment = 'งานแผนตรวจสอบผ่าน' . ($comment !== '' ? ': ' . $comment : '');
            } elseif ($stage === 'budget') {
                $newStatus = 'PENDING_APPROVAL';
                $historyComment = 'งานงบประมาณตรวจสอบผ่าน' . ($comment !== '' ? ': ' . $comment : '');
            } elseif ($stage === 'director') {
                $newStatus = 'APPROVED';
                $historyComment = 'ผู้อำนวยการอนุมัติโครงการ' . ($comment !== '' ? ': ' . $comment : '');
            }
        } elseif ($action === 'revision') {
            $newStatus = 'REVISION';
            $historyComment = qa_project_stage_label($stage) . ' ส่งกลับแก้ไข: ' . $comment;
        } else {
            $newStatus = 'REJECTED';
            $historyComment = qa_project_stage_label($stage) . ' ไม่อนุมัติ: ' . $comment;
        }

        if ($newStatus === 'APPROVED') {
            $stmt = $db->prepare(
                "UPDATE qa_projects
                 SET status_code=?, approved_at=NOW()
                 WHERE project_id=?"
            );
            $stmt->bind_param('si', $newStatus, $projectId);
        } else {
            $stmt = $db->prepare(
                "UPDATE qa_projects
                 SET status_code=?,
                     approved_at=CASE WHEN ?='APPROVED' THEN approved_at ELSE NULL END
                 WHERE project_id=?"
            );
            $stmt->bind_param('ssi', $newStatus, $newStatus, $projectId);
        }
        if (!$stmt->execute()) throw new Exception('ไม่สามารถเปลี่ยนสถานะโครงการได้');
        $stmt->close();

        if (!qa_project_add_history($projectId, $oldStatus, $newStatus, $userId, $historyComment)) {
            throw new Exception('ไม่สามารถบันทึกประวัติสถานะได้');
        }

        $commentType = $action === 'approve' ? 'approval' : ($action === 'revision' ? 'revision' : 'rejection');
        if ($comment !== '') {
            if (!qa_project_add_comment($projectId, $stage, $commentType, $comment, $userId)) {
                throw new Exception('ไม่สามารถบันทึกความเห็นได้');
            }
        }

        $db->commit();
        $db->autocommit(true);

        qa_project_audit_action(
            'project_' . $action . '_' . $stage,
            'qa_project_approvals',
            $approvalId,
            array('project_status'=>$oldStatus, 'approval'=>$activeApproval),
            array('project_status'=>$newStatus, 'decision'=>$decision, 'comment'=>$comment)
        );

        $successText = '';
        if ($action === 'approve') {
            $successText = qa_project_stage_label($stage) . ' บันทึกผล “ผ่าน” เรียบร้อยแล้ว';
        } elseif ($action === 'revision') {
            $successText = 'ส่งโครงการกลับให้เจ้าของแก้ไขเรียบร้อยแล้ว';
        } else {
            $successText = 'บันทึกผล “ไม่อนุมัติ” เรียบร้อยแล้ว';
        }

        approval_flash('success', $successText);
        approval_redirect('');

    } catch (Exception $e) {
        $db->rollback();
        $db->autocommit(true);
        approval_flash('danger', $e->getMessage());
        approval_redirect('id=' . $projectId);
    }
}

$message = '';
$messageType = 'success';
if (isset($_SESSION['qa_approval_flash']) && is_array($_SESSION['qa_approval_flash'])) {
    $messageType = isset($_SESSION['qa_approval_flash']['type']) ? $_SESSION['qa_approval_flash']['type'] : 'success';
    $message = isset($_SESSION['qa_approval_flash']['text']) ? $_SESSION['qa_approval_flash']['text'] : '';
    unset($_SESSION['qa_approval_flash']);
}

$changeRequestQueueBlock = '';
if (qa_project_change_table_ready()) {
    $changeRequestQueueBlock =
        " AND NOT EXISTS (
            SELECT 1 FROM qa_project_change_requests cr
            WHERE cr.project_id=p.project_id
              AND cr.request_status='pending'
          ) ";
}

/* จำนวนคิวแต่ละขั้น เฉพาะรายการที่ถึงขั้นนั้นจริง */
$stageCounts = array('plan'=>0, 'budget'=>0, 'director'=>0);
foreach ($stageCounts as $stage => $unused) {
    $stmt = $db->prepare(
        "SELECT COUNT(*)
         FROM qa_project_approvals a
         INNER JOIN qa_projects p ON p.project_id=a.project_id
         WHERE p.year_id=?
           AND a.approval_stage=?
           AND a.decision_status='pending'
           AND p.status_code NOT IN ('DRAFT','REVISION','REJECTED','CANCELLED','APPROVED','COMPLETED','CLOSED')
           " . $changeRequestQueueBlock . " 
           AND NOT EXISTS (
               SELECT 1 FROM qa_project_approvals prev
               WHERE prev.project_id=a.project_id
                 AND prev.sequence_no<a.sequence_no
                 AND prev.decision_status<>'approved'
           )"
    );
    $stmt->bind_param('is', $yearId, $stage);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    $stageCounts[$stage] = (int)$count;
}

/* Stage ที่ผู้ใช้มีสิทธิ์ */
$allowedStages = array();
foreach (array('plan','budget','director') as $stage) {
    if (qa_project_stage_allowed($stage)) $allowedStages[] = $stage;
}

/* Queue */
$queue = array();
if (!empty($allowedStages)) {
    $quoted = array();
    foreach ($allowedStages as $stage) $quoted[] = "'" . $db->real_escape_string($stage) . "'";

    $sql = "SELECT
                a.approval_id, a.sequence_no, a.approval_stage,
                p.project_id, p.project_code, p.project_name, p.requested_budget, p.approved_budget,
                p.status_code, d.division_name,
                CONCAT(COALESCE(u.prefix,''),COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS owner_name
            FROM qa_project_approvals a
            INNER JOIN qa_projects p ON p.project_id=a.project_id
            INNER JOIN qa_divisions d ON d.division_id=p.division_id
            LEFT JOIN qa_users u ON u.user_id=p.owner_user_id
            WHERE p.year_id=" . (int)$yearId . "
              AND a.approval_stage IN (" . implode(',', $quoted) . ")
              AND a.decision_status='pending'
              AND p.status_code NOT IN ('DRAFT','REVISION','REJECTED','CANCELLED','APPROVED','COMPLETED','CLOSED')
              " . $changeRequestQueueBlock . " 
              AND NOT EXISTS (
                  SELECT 1 FROM qa_project_approvals prev
                  WHERE prev.project_id=a.project_id
                    AND prev.sequence_no<a.sequence_no
                    AND prev.decision_status<>'approved'
              )
            ORDER BY a.sequence_no, p.submitted_at, p.project_id";
    $result = $db->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) $queue[] = $row;
        $result->free();
    }
}

/* Selected review */
$selectedProjectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selectedProject = null;
$selectedApproval = null;
$selectedBudgetItems = array();
$selectedAlignments = array(
    'missions'=>array(),
    'strategies'=>array(),
    'focus'=>array(),
    'indicators'=>array()
);

if ($selectedProjectId > 0) {
    $selectedProject = approval_load_project($selectedProjectId);
    $selectedApproval = qa_project_active_approval($selectedProjectId);

    if ($selectedProject && $selectedApproval && qa_project_stage_allowed($selectedApproval['approval_stage'])) {
        $stmt = $db->prepare(
            "SELECT bi.*, a.activity_name, s.source_name, bp.pool_name
             FROM qa_project_budget_items bi
             LEFT JOIN qa_project_activities a ON a.activity_id=bi.activity_id
             LEFT JOIN qa_budget_pools bp ON bp.budget_pool_id=bi.budget_pool_id
             LEFT JOIN qa_budget_sources s ON s.source_id=bp.source_id
             WHERE bi.project_id=? ORDER BY bi.sort_order, bi.budget_item_id"
        );
        $stmt->bind_param('i', $selectedProjectId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) $selectedBudgetItems[] = $row;
            $result->free();
        }
        $stmt->close();

        $stmt = $db->prepare(
            "SELECT m.mission_code, m.mission_text
             FROM qa_project_mission_links l
             INNER JOIN qa_missions m ON m.mission_id=l.mission_id
             WHERE l.project_id=? ORDER BY m.sort_order"
        );
        $stmt->bind_param('i',$selectedProjectId); $stmt->execute(); $result=$stmt->get_result();
        if ($result) { while ($r=$result->fetch_assoc()) $selectedAlignments['missions'][]=$r; $result->free(); } $stmt->close();

        $stmt = $db->prepare(
            "SELECT s.strategy_code, s.strategy_name
             FROM qa_project_strategy_links l
             INNER JOIN qa_strategies s ON s.strategy_id=l.strategy_id
             WHERE l.project_id=? ORDER BY s.sort_order"
        );
        $stmt->bind_param('i',$selectedProjectId); $stmt->execute(); $result=$stmt->get_result();
        if ($result) { while ($r=$result->fetch_assoc()) $selectedAlignments['strategies'][]=$r; $result->free(); } $stmt->close();

        $stmt = $db->prepare(
            "SELECT f.focus_code, f.focus_name
             FROM qa_project_focus_links l
             INNER JOIN qa_focus_areas f ON f.focus_id=l.focus_id
             WHERE l.project_id=? ORDER BY f.sort_order"
        );
        $stmt->bind_param('i',$selectedProjectId); $stmt->execute(); $result=$stmt->get_result();
        if ($result) { while ($r=$result->fetch_assoc()) $selectedAlignments['focus'][]=$r; $result->free(); } $stmt->close();

        $stmt = $db->prepare(
            "SELECT i.indicator_code, i.indicator_name
             FROM qa_project_indicator_links l
             INNER JOIN qa_indicators i ON i.indicator_id=l.indicator_id
             WHERE l.project_id=? ORDER BY i.indicator_code"
        );
        $stmt->bind_param('i',$selectedProjectId); $stmt->execute(); $result=$stmt->get_result();
        if ($result) { while ($r=$result->fetch_assoc()) $selectedAlignments['indicators'][]=$r; $result->free(); } $stmt->close();
    } else {
        $selectedProject = null;
        $selectedApproval = null;
    }
}

require QA_ROOT . '/includes/header.php';
?>

<?php if ($message !== ''): ?>
<div class="alert <?= $messageType === 'danger' ? 'alert-danger' : 'alert-success' ?>" style="margin-bottom:16px"><?= h($message) ?></div>
<?php endif; ?>

<div class="grid grid-3">
    <div class="card">
        <div class="metric-label">รอตรวจโดยงานแผน</div>
        <div class="metric-value"><?= h($stageCounts['plan']) ?></div>
        <div class="metric-note"><?= qa_project_stage_allowed('plan') ? 'คุณมีสิทธิ์ดำเนินการ' : 'แสดงภาพรวม' ?></div>
    </div>
    <div class="card">
        <div class="metric-label">รอตรวจงบประมาณ</div>
        <div class="metric-value"><?= h($stageCounts['budget']) ?></div>
        <div class="metric-note"><?= qa_project_stage_allowed('budget') ? 'คุณมีสิทธิ์ดำเนินการ' : 'แสดงภาพรวม' ?></div>
    </div>
    <div class="card">
        <div class="metric-label">รอผู้อำนวยการอนุมัติ</div>
        <div class="metric-value"><?= h($stageCounts['director']) ?></div>
        <div class="metric-note"><?= qa_project_stage_allowed('director') ? 'คุณมีสิทธิ์ดำเนินการ' : 'แสดงภาพรวม' ?></div>
    </div>
</div>

<?php if (empty($allowedStages)): ?>
<div class="notice" style="margin-top:16px">
    บัญชีนี้ไม่มี Role สำหรับตรวจสอบ/อนุมัติ (plan, budget, director) จึงดูได้เฉพาะภาพรวม
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>คิวที่รอดำเนินการของคุณ</h2>
            <p>ระบบจะแสดงเฉพาะขั้นที่ถึงคิวจริงและตรงกับ Role ของบัญชี</p>
        </div>
        <span class="badge badge-blue"><?= h(count($queue)) ?> รายการ</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>รหัส</th>
                <th>โครงการ</th>
                <th>ฝ่าย</th>
                <th class="text-right">งบขอ</th>
                <th>ผู้รับผิดชอบ</th>
                <th>ขั้นตอน</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($queue)): ?>
                <tr><td colspan="7" class="empty-cell">ไม่มีรายการที่รอดำเนินการสำหรับบัญชีนี้</td></tr>
            <?php else: ?>
                <?php foreach ($queue as $row): ?>
                <tr>
                    <td><strong><?= h($row['project_code']) ?></strong></td>
                    <td><?= h($row['project_name']) ?></td>
                    <td><?= h($row['division_name']) ?></td>
                    <td class="text-right money-cell"><?= h(qa_money($row['requested_budget'])) ?></td>
                    <td><?= h(trim($row['owner_name'])) ?></td>
                    <td><span class="badge badge-yellow"><?= h(qa_project_stage_label($row['approval_stage'])) ?></span></td>
                    <td><a class="btn btn-sm btn-primary" href="<?= h(qa_url('approvals/index.php?id=' . $row['project_id'])) ?>">เปิดตรวจ</a></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($selectedProject && $selectedApproval): ?>
<div class="card review-card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>ตรวจโครงการ <?= h($selectedProject['project_code']) ?></h2>
            <p>ขั้นปัจจุบัน: <?= h(qa_project_stage_label($selectedApproval['approval_stage'])) ?></p>
        </div>
        <a class="btn" href="<?= h(qa_url('projects/view.php?id=' . $selectedProjectId)) ?>">เปิดรายละเอียดเต็ม</a>
    </div>

    <div class="grid grid-4">
        <div class="review-fact"><span>โครงการ</span><strong><?= h($selectedProject['project_name']) ?></strong></div>
        <div class="review-fact"><span>ฝ่าย</span><strong><?= h($selectedProject['division_name']) ?></strong></div>
        <div class="review-fact"><span>ผู้รับผิดชอบ</span><strong><?= h(trim($selectedProject['owner_name'])) ?></strong></div>
        <div class="review-fact"><span>งบที่ขอ</span><strong><?= h(qa_money($selectedProject['requested_budget'])) ?> บาท</strong></div>
    </div>

    <div class="grid grid-2" style="margin-top:16px">
        <div class="alignment-box">
            <h3>ความสอดคล้องกับแผน</h3>
            <p><strong>พันธกิจ:</strong>
                <?php foreach ($selectedAlignments['missions'] as $r): ?>
                    <span class="badge badge-blue"><?= h($r['mission_code']) ?></span>
                <?php endforeach; ?>
            </p>
            <p><strong>กลยุทธ์:</strong>
                <?php foreach ($selectedAlignments['strategies'] as $r): ?>
                    <span class="badge badge-blue"><?= h($r['strategy_code']) ?></span>
                <?php endforeach; ?>
            </p>
            <p><strong>จุดเน้น:</strong>
                <?php foreach ($selectedAlignments['focus'] as $r): ?>
                    <span class="badge badge-blue"><?= h($r['focus_code']) ?></span>
                <?php endforeach; ?>
            </p>
        </div>

        <div class="alignment-box">
            <h3>ตัวชี้วัด สมศ.</h3>
            <?php if (empty($selectedAlignments['indicators'])): ?>
                <div class="help-text">ไม่ได้เชื่อมตัวชี้วัด</div>
            <?php else: ?>
                <?php foreach ($selectedAlignments['indicators'] as $r): ?>
                    <div class="review-indicator"><strong><?= h($r['indicator_code']) ?></strong> <?= h($r['indicator_name']) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($selectedApproval['approval_stage'] === 'budget'): ?>
    <div style="margin-top:16px">
        <h3>ตรวจและกำหนดวงเงินอนุมัติรายรายการ</h3>
        <div class="notice">
            งานงบประมาณสามารถอนุมัติเต็มจำนวนหรือลดวงเงินได้ แต่ต้องไม่เกินยอดที่ขอและไม่เกินวงเงินของฝ่ายในแหล่งเงินนั้น
        </div>
    </div>
    <?php endif; ?>

    <form method="post" id="approvalForm" style="margin-top:16px">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="project_id" value="<?= h($selectedProjectId) ?>">
        <input type="hidden" name="approval_id" value="<?= h($selectedApproval['approval_id']) ?>">

        <?php if ($selectedApproval['approval_stage'] === 'budget'): ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>กิจกรรม</th>
                    <th>รายการ</th>
                    <th>แหล่งเงิน</th>
                    <th class="text-right">ขอ</th>
                    <th style="width:180px">อนุมัติ</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($selectedBudgetItems as $item): ?>
                <tr>
                    <td><?= h($item['activity_name']) ?></td>
                    <td><?= h($item['item_name']) ?></td>
                    <td><?= h($item['source_name'] . ' / ' . $item['pool_name']) ?></td>
                    <td class="text-right money-cell"><?= h(qa_money($item['requested_amount'])) ?></td>
                    <td>
                        <input
                            class="approval-budget-input"
                            type="number"
                            min="0"
                            max="<?= h(number_format((float)$item['requested_amount'],2,'.','')) ?>"
                            step="0.01"
                            name="approved_amount[<?= h($item['budget_item_id']) ?>]"
                            value="<?= h(number_format(
                                (float)$item['approved_amount'] > 0
                                    ? (float)$item['approved_amount']
                                    : (float)$item['requested_amount'],
                                2,'.',''
                            )) ?>">
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr class="table-total-row">
                    <td colspan="4"><strong>วงเงินอนุมัติรวม</strong></td>
                    <td><strong id="approvalBudgetTotal">0.00</strong></td>
                </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>

        <div class="form-group" style="margin-top:16px">
            <label for="comment">ความเห็น / ข้อเสนอแนะ</label>
            <textarea id="comment" name="comment" placeholder="ระบุความเห็นประกอบการตรวจสอบ โดยเฉพาะกรณีส่งกลับแก้ไขหรือไม่อนุมัติ"></textarea>
        </div>

        <div class="approval-actions">
            <button class="btn btn-primary" type="submit" name="action" value="approve"
                    onclick="return confirm('ยืนยันผล “ผ่าน” ในขั้นนี้?');">
                ผ่าน / ส่งต่อ
            </button>
            <button class="btn btn-warning" type="submit" name="action" value="revision"
                    onclick="return confirm('ยืนยันส่งกลับให้เจ้าของโครงการแก้ไข?');">
                ส่งกลับแก้ไข
            </button>
            <button class="btn btn-danger" type="submit" name="action" value="reject"
                    onclick="return confirm('ยืนยัน “ไม่อนุมัติ” โครงการนี้?');">
                ไม่อนุมัติ
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<script>
(function(){
    var inputs = document.querySelectorAll('.approval-budget-input');
    var total = document.getElementById('approvalBudgetTotal');
    if (!inputs.length || !total) return;

    function recalc(){
        var sum = 0;
        for (var i=0; i<inputs.length; i++) {
            var v = parseFloat(inputs[i].value || '0');
            if (isFinite(v) && v >= 0) sum += v;
        }
        total.innerHTML = sum.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    }

    for (var i=0; i<inputs.length; i++) inputs[i].addEventListener('input', recalc);
    recalc();
})();
</script>

<?php require QA_ROOT . '/includes/footer.php'; ?>
