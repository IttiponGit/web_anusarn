<?php
$pageTitle = 'งบประมาณประจำปี';
$pageHeading = 'งบประมาณประจำปี';
$pageDescription = 'บันทึกแหล่งงบประมาณและวงเงินจริงของโรงเรียน เพื่อใช้เป็นต้นทางในการจัดสรรงบประมาณ';
$activeMenu = 'budget';
$breadcrumbs = array(array('label' => 'งบประมาณประจำปี'));

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

$db = qa_db();
$user = qa_current_user();
$currentYear = qa_current_year_row();
$yearId = $currentYear ? (int) $currentYear['year_id'] : 0;
$yearBE = $currentYear ? (int) $currentYear['fiscal_year_be'] : 0;

$canManage = (
    qa_user_has_role('admin') ||
    qa_user_has_role('director') ||
    qa_user_has_role('plan') ||
    qa_user_has_role('budget')
);

$message = '';
$messageType = 'success';

function budget_redirect($query)
{
    $url = qa_url('budget/index.php');
    if ($query !== '') {
        $url .= '?' . $query;
    }
    header('Location: ' . $url);
    exit;
}

function budget_clean_amount($value)
{
    $value = str_replace(',', '', trim((string) $value));
    if ($value === '' || !is_numeric($value)) {
        return null;
    }
    return (float) $value;
}

function budget_valid_date($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    $parts = explode('-', $value);
    if (count($parts) !== 3) {
        return false;
    }
    return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]) ? $value : false;
}

function budget_audit($actionName, $recordId, $oldData, $newData)
{
    $db = qa_db();
    $user = qa_current_user();
    $userId = $user ? (int) $user['user_id'] : 0;
    $oldJson = $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE);
    $newJson = $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE);
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '';

    $stmt = $db->prepare(
        "INSERT INTO qa_audit_logs
        (user_id, action_name, module_name, record_table, record_id, old_data, new_data, ip_address, user_agent)
        VALUES (NULLIF(?,0), ?, 'budget', 'qa_budget_pools', ?, ?, ?, ?, ?)"
    );
    if ($stmt) {
        $recordIdText = (string) $recordId;
        $stmt->bind_param('issssss', $userId, $actionName, $recordIdText, $oldJson, $newJson, $ip, $ua);
        $stmt->execute();
        $stmt->close();
    }
}

if ($yearId <= 0) {
    $message = 'ยังไม่ได้กำหนดปีงบประมาณที่ใช้งาน กรุณาตั้งค่าปีงบประมาณก่อน';
    $messageType = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        http_response_code(403);
        die('คุณไม่มีสิทธิ์แก้ไขข้อมูลงบประมาณ');
    }

    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!qa_verify_csrf($token)) {
        http_response_code(400);
        die('CSRF token ไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create' || $action === 'update') {
        $sourceId = isset($_POST['source_id']) ? (int) $_POST['source_id'] : 0;
        $poolName = isset($_POST['pool_name']) ? trim($_POST['pool_name']) : '';
        $amount = budget_clean_amount(isset($_POST['initial_amount']) ? $_POST['initial_amount'] : '');
        $receivedDate = budget_valid_date(isset($_POST['received_date']) ? $_POST['received_date'] : '');
        $documentNo = isset($_POST['document_no']) ? trim($_POST['document_no']) : '';
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

        $sourceExists = 0;
        if ($sourceId > 0) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM qa_budget_sources WHERE source_id = ? AND is_active = 1");
            $stmt->bind_param('i', $sourceId);
            $stmt->execute();
            $stmt->bind_result($sourceExists);
            $stmt->fetch();
            $stmt->close();
        }

        $errors = array();
        if ($yearId <= 0) {
            $errors[] = 'ไม่พบปีงบประมาณที่ใช้งาน';
        }
        if ($sourceId <= 0 || (int) $sourceExists !== 1) {
            $errors[] = 'กรุณาเลือกแหล่งงบประมาณที่ถูกต้อง';
        }
        if ($poolName === '') {
            $errors[] = 'กรุณาระบุชื่อรายการงบประมาณ';
        }
        if ($amount === null || $amount <= 0) {
            $errors[] = 'จำนวนเงินต้องมากกว่า 0 บาท';
        }
        if ($receivedDate === false) {
            $errors[] = 'วันที่ได้รับงบประมาณไม่ถูกต้อง';
        }

        if (!empty($errors)) {
            $_SESSION['qa_budget_flash'] = array(
                'type' => 'danger',
                'text' => implode(' / ', $errors)
            );

            if ($action === 'update') {
                $poolId = isset($_POST['budget_pool_id']) ? (int) $_POST['budget_pool_id'] : 0;
                budget_redirect('edit=' . $poolId);
            }
            budget_redirect('');
        }

        $receivedDateParam = $receivedDate === '' ? '' : $receivedDate;
        $userId = $user ? (int) $user['user_id'] : 0;

        if ($action === 'create') {
            $stmt = $db->prepare(
                "INSERT INTO qa_budget_pools
                (year_id, source_id, pool_name, initial_amount, received_date, document_no, notes, created_by)
                VALUES (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, 0))"
            );
            if (!$stmt) {
                die('ไม่สามารถเตรียมคำสั่งเพิ่มงบประมาณได้');
            }
            $stmt->bind_param(
                'iisdsssi',
                $yearId,
                $sourceId,
                $poolName,
                $amount,
                $receivedDateParam,
                $documentNo,
                $notes,
                $userId
            );

            if ($stmt->execute()) {
                $newId = $stmt->insert_id;
                $stmt->close();

                budget_audit('create_budget_pool', $newId, null, array(
                    'year_id' => $yearId,
                    'source_id' => $sourceId,
                    'pool_name' => $poolName,
                    'initial_amount' => $amount,
                    'received_date' => $receivedDateParam,
                    'document_no' => $documentNo,
                    'notes' => $notes
                ));

                $_SESSION['qa_budget_flash'] = array(
                    'type' => 'success',
                    'text' => 'เพิ่มรายการงบประมาณเรียบร้อยแล้ว'
                );
                budget_redirect('');
            } else {
                $stmt->close();
                $_SESSION['qa_budget_flash'] = array(
                    'type' => 'danger',
                    'text' => 'ไม่สามารถเพิ่มรายการงบประมาณได้'
                );
                budget_redirect('');
            }
        }

        if ($action === 'update') {
            $poolId = isset($_POST['budget_pool_id']) ? (int) $_POST['budget_pool_id'] : 0;

            $oldRow = null;
            $stmt = $db->prepare("SELECT * FROM qa_budget_pools WHERE budget_pool_id = ? AND year_id = ? LIMIT 1");
            $stmt->bind_param('ii', $poolId, $yearId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                $oldRow = $result->fetch_assoc();
                $result->free();
            }
            $stmt->close();

            if (!$oldRow) {
                $_SESSION['qa_budget_flash'] = array(
                    'type' => 'danger',
                    'text' => 'ไม่พบรายการงบประมาณที่ต้องการแก้ไข'
                );
                budget_redirect('');
            }

            $stmt = $db->prepare(
                "UPDATE qa_budget_pools
                 SET source_id = ?,
                     pool_name = ?,
                     initial_amount = ?,
                     received_date = NULLIF(?, ''),
                     document_no = NULLIF(?, ''),
                     notes = NULLIF(?, '')
                 WHERE budget_pool_id = ? AND year_id = ?"
            );
            if (!$stmt) {
                die('ไม่สามารถเตรียมคำสั่งแก้ไขงบประมาณได้');
            }
            $stmt->bind_param(
                'isdsssii',
                $sourceId,
                $poolName,
                $amount,
                $receivedDateParam,
                $documentNo,
                $notes,
                $poolId,
                $yearId
            );

            if ($stmt->execute()) {
                $stmt->close();

                budget_audit('update_budget_pool', $poolId, $oldRow, array(
                    'year_id' => $yearId,
                    'source_id' => $sourceId,
                    'pool_name' => $poolName,
                    'initial_amount' => $amount,
                    'received_date' => $receivedDateParam,
                    'document_no' => $documentNo,
                    'notes' => $notes
                ));

                $_SESSION['qa_budget_flash'] = array(
                    'type' => 'success',
                    'text' => 'แก้ไขรายการงบประมาณเรียบร้อยแล้ว'
                );
                budget_redirect('');
            } else {
                $stmt->close();
                $_SESSION['qa_budget_flash'] = array(
                    'type' => 'danger',
                    'text' => 'ไม่สามารถแก้ไขรายการงบประมาณได้'
                );
                budget_redirect('edit=' . $poolId);
            }
        }
    }

    if ($action === 'delete') {
        $poolId = isset($_POST['budget_pool_id']) ? (int) $_POST['budget_pool_id'] : 0;

        $oldRow = null;
        $stmt = $db->prepare("SELECT * FROM qa_budget_pools WHERE budget_pool_id = ? AND year_id = ? LIMIT 1");
        $stmt->bind_param('ii', $poolId, $yearId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $oldRow = $result->fetch_assoc();
            $result->free();
        }
        $stmt->close();

        if (!$oldRow) {
            $_SESSION['qa_budget_flash'] = array(
                'type' => 'danger',
                'text' => 'ไม่พบรายการงบประมาณที่ต้องการลบ'
            );
            budget_redirect('');
        }

        $dependencyCount = 0;
        $dependencyQueries = array(
            "SELECT COUNT(*) FROM qa_budget_adjustments WHERE budget_pool_id = ?",
            "SELECT COUNT(*) FROM qa_budget_allocations WHERE budget_pool_id = ?",
            "SELECT COUNT(*) FROM qa_project_budget_items WHERE budget_pool_id = ?",
            "SELECT COUNT(*) FROM qa_expenditures WHERE budget_pool_id = ?"
        );

        foreach ($dependencyQueries as $sql) {
            $count = 0;
            $stmt = $db->prepare($sql);
            $stmt->bind_param('i', $poolId);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();
            $dependencyCount += (int) $count;
        }

        if ($dependencyCount > 0) {
            $_SESSION['qa_budget_flash'] = array(
                'type' => 'danger',
                'text' => 'ลบไม่ได้ เนื่องจากรายการนี้ถูกนำไปจัดสรร ปรับงบ ผูกกับโครงการ หรือมีรายการเบิกจ่ายแล้ว'
            );
            budget_redirect('');
        }

        $stmt = $db->prepare("DELETE FROM qa_budget_pools WHERE budget_pool_id = ? AND year_id = ?");
        $stmt->bind_param('ii', $poolId, $yearId);
        if ($stmt->execute()) {
            $stmt->close();
            budget_audit('delete_budget_pool', $poolId, $oldRow, null);

            $_SESSION['qa_budget_flash'] = array(
                'type' => 'success',
                'text' => 'ลบรายการงบประมาณเรียบร้อยแล้ว'
            );
            budget_redirect('');
        } else {
            $stmt->close();
            $_SESSION['qa_budget_flash'] = array(
                'type' => 'danger',
                'text' => 'ไม่สามารถลบรายการงบประมาณได้'
            );
            budget_redirect('');
        }
    }
}

if (isset($_SESSION['qa_budget_flash']) && is_array($_SESSION['qa_budget_flash'])) {
    $messageType = isset($_SESSION['qa_budget_flash']['type']) ? $_SESSION['qa_budget_flash']['type'] : 'success';
    $message = isset($_SESSION['qa_budget_flash']['text']) ? $_SESSION['qa_budget_flash']['text'] : '';
    unset($_SESSION['qa_budget_flash']);
}

$sources = array();
$result = $db->query("SELECT source_id, source_code, source_name FROM qa_budget_sources WHERE is_active = 1 ORDER BY source_id");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sources[] = $row;
    }
    $result->free();
}

$editRow = null;
if ($canManage && isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $stmt = $db->prepare(
            "SELECT p.*, s.source_name
             FROM qa_budget_pools p
             INNER JOIN qa_budget_sources s ON s.source_id = p.source_id
             WHERE p.budget_pool_id = ? AND p.year_id = ?
             LIMIT 1"
        );
        $stmt->bind_param('ii', $editId, $yearId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $editRow = $result->fetch_assoc();
            $result->free();
        }
        $stmt->close();
    }
}

$budgetRows = array();
if ($yearId > 0) {
    $stmt = $db->prepare(
        "SELECT
            p.budget_pool_id,
            p.source_id,
            s.source_code,
            s.source_name,
            p.pool_name,
            p.initial_amount,
            p.received_date,
            p.document_no,
            p.notes,
            p.created_at,
            p.updated_at
         FROM qa_budget_pools p
         INNER JOIN qa_budget_sources s ON s.source_id = p.source_id
         WHERE p.year_id = ?
         ORDER BY s.source_id, p.budget_pool_id"
    );
    $stmt->bind_param('i', $yearId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $budgetRows[] = $row;
        }
        $result->free();
    }
    $stmt->close();
}

$totalBudget = 0.0;
foreach ($budgetRows as $row) {
    $totalBudget += (float) $row['initial_amount'];
}

$divisionAllocated = 0.0;
$centralAllocated = 0.0;
if ($yearId > 0) {
    $divisionAllocated = (float) qa_db_scalar(
        "SELECT COALESCE(SUM(current_allocation),0)
         FROM vw_qa_budget_allocation_summary
         WHERE year_id = " . $yearId . " AND allocation_type = 'division'",
        0
    );
    $centralAllocated = (float) qa_db_scalar(
        "SELECT COALESCE(SUM(current_allocation),0)
         FROM vw_qa_budget_allocation_summary
         WHERE year_id = " . $yearId . " AND allocation_type = 'central'",
        0
    );
}
$unallocated = $totalBudget - $divisionAllocated - $centralAllocated;

require QA_ROOT . '/includes/header.php';
?>

<?php if ($message !== ''): ?>
    <div class="alert <?= $messageType === 'danger' ? 'alert-danger' : 'alert-success' ?>" style="margin-bottom:16px">
        <?= h($message) ?>
    </div>
<?php endif; ?>

<div class="action-bar">
    <?php if ($canManage): ?>
        <a class="btn btn-primary" href="#budget-form">＋ เพิ่มรายการงบประมาณ</a>
    <?php endif; ?>
    <a class="btn" href="<?= h(qa_url('budget/allocation.php')) ?>">ไปหน้าจัดสรรงบ 4 ฝ่าย</a>
    <a class="btn" href="<?= h(qa_url('reports/index.php')) ?>">ดูรายงานงบประมาณ</a>
</div>

<div class="grid grid-5">
    <div class="card">
        <div class="metric-label">งบประมาณรวม</div>
        <div class="metric-value"><?= h(qa_money($totalBudget)) ?></div>
        <div class="metric-note">จากรายการงบที่บันทึกจริง</div>
    </div>
    <div class="card">
        <div class="metric-label">จัดสรร 4 ฝ่าย</div>
        <div class="metric-value"><?= h(qa_money($divisionAllocated)) ?></div>
        <div class="metric-note">allocation_type = division</div>
    </div>
    <div class="card">
        <div class="metric-label">งบส่วนกลาง</div>
        <div class="metric-value"><?= h(qa_money($centralAllocated)) ?></div>
        <div class="metric-note">allocation_type = central</div>
    </div>
    <div class="card">
        <div class="metric-label">ยังไม่ได้จัดสรร</div>
        <div class="metric-value <?= $unallocated < 0 ? 'text-danger' : '' ?>"><?= h(qa_money($unallocated)) ?></div>
        <div class="metric-note"><?= $unallocated < 0 ? 'ยอดจัดสรรเกินงบรวม' : 'พร้อมนำไปจัดสรร' ?></div>
    </div>
    <div class="card">
        <div class="metric-label">ปีงบประมาณ</div>
        <div class="metric-value"><?= $yearBE ? h($yearBE) : '-' ?></div>
        <div class="metric-note"><?= $currentYear && !empty($currentYear['year_name']) ? h($currentYear['year_name']) : 'ปีที่ใช้งาน' ?></div>
    </div>
</div>

<?php if ($unallocated < 0): ?>
    <div class="alert alert-danger" style="margin-top:16px">
        พบยอดจัดสรรสูงกว่างบประมาณรวม <?= h(qa_money(abs($unallocated))) ?> บาท กรุณาตรวจสอบข้อมูลการจัดสรรงบประมาณ
    </div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>รายการงบประมาณ</h2>
            <p>หนึ่งแหล่งเงินสามารถมีได้หลายรายการ เช่น งบที่ได้รับคนละรอบหรือคนละเอกสาร</p>
        </div>
        <span class="badge badge-blue"><?= h(count($budgetRows)) ?> รายการ</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th style="width:52px">#</th>
                <th>แหล่งงบประมาณ</th>
                <th>ชื่อรายการ</th>
                <th class="text-right">จำนวนเงิน</th>
                <th>วันที่ได้รับ</th>
                <th>เลขที่เอกสาร</th>
                <th>หมายเหตุ</th>
                <?php if ($canManage): ?><th style="width:150px">จัดการ</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($budgetRows)): ?>
                <tr>
                    <td colspan="<?= $canManage ? '8' : '7' ?>" class="empty-cell">
                        ยังไม่มีข้อมูลงบประมาณในปี <?= $yearBE ? h($yearBE) : '-' ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php $n = 1; foreach ($budgetRows as $row): ?>
                    <tr>
                        <td><?= h($n++) ?></td>
                        <td>
                            <strong><?= h($row['source_name']) ?></strong>
                            <div class="subtle"><?= h($row['source_code']) ?></div>
                        </td>
                        <td><?= h($row['pool_name']) ?></td>
                        <td class="text-right money-cell"><?= h(qa_money($row['initial_amount'])) ?></td>
                        <td><?= $row['received_date'] ? h($row['received_date']) : '-' ?></td>
                        <td><?= $row['document_no'] ? h($row['document_no']) : '-' ?></td>
                        <td><?= $row['notes'] ? nl2br(h($row['notes'])) : '-' ?></td>
                        <?php if ($canManage): ?>
                            <td>
                                <div class="row-actions">
                                    <a class="btn btn-sm" href="<?= h(qa_url('budget/index.php?edit=' . (int) $row['budget_pool_id'] . '#budget-form')) ?>">แก้ไข</a>
                                    <form method="post" action="<?= h(qa_url('budget/index.php')) ?>" onsubmit="return confirm('ยืนยันลบรายการงบประมาณนี้? การลบจะทำได้เฉพาะรายการที่ยังไม่ถูกนำไปใช้งาน');">
                                        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="budget_pool_id" value="<?= h($row['budget_pool_id']) ?>">
                                        <button class="btn btn-danger btn-sm" type="submit">ลบ</button>
                                    </form>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-total-row">
                    <td></td>
                    <td colspan="2"><strong>รวมงบประมาณ</strong></td>
                    <td class="text-right"><strong><?= h(qa_money($totalBudget)) ?></strong></td>
                    <td colspan="<?= $canManage ? '4' : '3' ?>"></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($canManage): ?>
<div class="card" id="budget-form" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2><?= $editRow ? 'แก้ไขรายการงบประมาณ' : 'เพิ่มรายการงบประมาณ' ?></h2>
            <p>ข้อมูลที่บันทึกตรงนี้จะถูกนำไปคำนวณงบรวมใน Dashboard และเป็นวงเงินตั้งต้นสำหรับการจัดสรร 4 ฝ่าย</p>
        </div>
        <?php if ($editRow): ?>
            <a class="btn btn-sm" href="<?= h(qa_url('budget/index.php#budget-form')) ?>">ยกเลิกการแก้ไข</a>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= h(qa_url('budget/index.php')) ?>">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $editRow ? 'update' : 'create' ?>">
        <?php if ($editRow): ?>
            <input type="hidden" name="budget_pool_id" value="<?= h($editRow['budget_pool_id']) ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div class="form-group">
                <label for="source_id">แหล่งงบประมาณ <span class="required">*</span></label>
                <select name="source_id" id="source_id" required>
                    <option value="">-- เลือกแหล่งงบประมาณ --</option>
                    <?php foreach ($sources as $source): ?>
                        <?php $selectedSource = $editRow ? (int) $editRow['source_id'] : 0; ?>
                        <option value="<?= h($source['source_id']) ?>" <?= $selectedSource === (int) $source['source_id'] ? 'selected' : '' ?>>
                            <?= h($source['source_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="pool_name">ชื่อรายการงบประมาณ <span class="required">*</span></label>
                <input
                    type="text"
                    id="pool_name"
                    name="pool_name"
                    maxlength="255"
                    required
                    value="<?= $editRow ? h($editRow['pool_name']) : '' ?>"
                    placeholder="เช่น เงินอุดหนุนค่าใช้จ่ายในการจัดการศึกษาประจำปี">
            </div>

            <div class="form-group">
                <label for="initial_amount">จำนวนเงิน (บาท) <span class="required">*</span></label>
                <input
                    type="number"
                    id="initial_amount"
                    name="initial_amount"
                    min="0.01"
                    step="0.01"
                    required
                    value="<?= $editRow ? h(number_format((float) $editRow['initial_amount'], 2, '.', '')) : '' ?>"
                    placeholder="0.00">
            </div>

            <div class="form-group">
                <label for="received_date">วันที่ได้รับงบประมาณ</label>
                <input
                    type="date"
                    id="received_date"
                    name="received_date"
                    value="<?= $editRow && $editRow['received_date'] ? h($editRow['received_date']) : '' ?>">
            </div>

            <div class="form-group">
                <label for="document_no">เลขที่เอกสาร / หนังสือ</label>
                <input
                    type="text"
                    id="document_no"
                    name="document_no"
                    maxlength="100"
                    value="<?= $editRow && $editRow['document_no'] ? h($editRow['document_no']) : '' ?>"
                    placeholder="เช่น ศธ 04007/...">
            </div>

            <div class="form-group">
                <label>ปีงบประมาณ</label>
                <input type="text" value="<?= $yearBE ? h($yearBE) : '-' ?>" readonly>
                <div class="help-text">ระบบบันทึกเข้าปีงบประมาณที่กำลังใช้งานโดยอัตโนมัติ</div>
            </div>

            <div class="form-group full">
                <label for="notes">หมายเหตุ</label>
                <textarea id="notes" name="notes" placeholder="รายละเอียดเพิ่มเติม (ถ้ามี)"><?= $editRow && $editRow['notes'] ? h($editRow['notes']) : '' ?></textarea>
            </div>
        </div>

        <div class="action-bar" style="margin-top:16px; margin-bottom:0">
            <button class="btn btn-primary" type="submit"><?= $editRow ? 'บันทึกการแก้ไข' : 'บันทึกรายการงบประมาณ' ?></button>
            <?php if ($editRow): ?>
                <a class="btn" href="<?= h(qa_url('budget/index.php')) ?>">ยกเลิก</a>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php else: ?>
<div class="notice" style="margin-top:16px">
    บัญชีของคุณมีสิทธิ์ดูข้อมูลงบประมาณ แต่ไม่มีสิทธิ์เพิ่ม แก้ไข หรือลบรายการ
</div>
<?php endif; ?>

<?php require QA_ROOT . '/includes/footer.php'; ?>
