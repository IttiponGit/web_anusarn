<?php
$pageTitle = 'จัดสรรงบ 4 ฝ่าย';
$pageHeading = 'จัดสรรงบประมาณ 4 ฝ่าย';
$pageDescription = 'แบ่งวงเงินจากแต่ละก้อนงบประมาณไปยัง 4 ฝ่ายและงบส่วนกลาง โดยยังคงตรวจสอบย้อนกลับถึงแหล่งเงินได้';
$activeMenu = 'allocation';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

$breadcrumbs = array(
    array('label' => 'งบประมาณ', 'url' => qa_url('budget/index.php')),
    array('label' => 'จัดสรรงบ 4 ฝ่าย')
);

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

function allocation_redirect($query)
{
    $url = qa_url('budget/allocation.php');
    if ($query !== '') {
        $url .= '?' . $query;
    }
    header('Location: ' . $url);
    exit;
}

function allocation_amount($value)
{
    $value = str_replace(',', '', trim((string) $value));
    if ($value === '') {
        return 0.0;
    }
    if (!is_numeric($value)) {
        return null;
    }
    return (float) $value;
}

function allocation_audit($poolId, $oldData, $newData)
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
        VALUES (NULLIF(?,0), 'save_budget_allocation', 'budget', 'qa_budget_allocations', ?, ?, ?, ?, ?)"
    );
    if ($stmt) {
        $recordId = 'pool:' . (int) $poolId;
        $stmt->bind_param('isssss', $userId, $recordId, $oldJson, $newJson, $ip, $ua);
        $stmt->execute();
        $stmt->close();
    }
}

function allocation_snapshot($poolId)
{
    $db = qa_db();
    $rows = array();

    $stmt = $db->prepare(
        "SELECT
            a.allocation_id,
            a.allocation_code,
            a.allocation_type,
            a.division_id,
            d.division_code,
            d.division_name,
            a.allocated_amount,
            a.allocation_date,
            a.status
         FROM qa_budget_allocations a
         LEFT JOIN qa_divisions d ON d.division_id = a.division_id
         WHERE a.budget_pool_id = ?
         ORDER BY a.allocation_type, d.sort_order, a.allocation_id"
    );
    $stmt->bind_param('i', $poolId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    $stmt->close();

    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        http_response_code(403);
        die('คุณไม่มีสิทธิ์จัดสรรงบประมาณ');
    }

    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!qa_verify_csrf($token)) {
        http_response_code(400);
        die('CSRF token ไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'save_allocation') {
        $poolId = isset($_POST['budget_pool_id']) ? (int) $_POST['budget_pool_id'] : 0;

        $pool = null;
        $stmt = $db->prepare(
            "SELECT p.budget_pool_id, p.year_id, p.initial_amount, p.pool_name, s.source_name
             FROM qa_budget_pools p
             INNER JOIN qa_budget_sources s ON s.source_id = p.source_id
             WHERE p.budget_pool_id = ? AND p.year_id = ?
             LIMIT 1"
        );
        $stmt->bind_param('ii', $poolId, $yearId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $pool = $result->fetch_assoc();
            $result->free();
        }
        $stmt->close();

        if (!$pool) {
            $_SESSION['qa_allocation_flash'] = array(
                'type' => 'danger',
                'text' => 'ไม่พบก้อนงบประมาณที่ต้องการจัดสรร'
            );
            allocation_redirect('');
        }

        $divisions = array();
        $result = $db->query(
            "SELECT division_id, division_code, division_name
             FROM qa_divisions
             WHERE is_active = 1
             ORDER BY sort_order, division_id"
        );
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $divisions[] = $row;
            }
            $result->free();
        }

        $errors = array();
        $divisionAmounts = array();
        $sumRequested = 0.0;

        foreach ($divisions as $division) {
            $key = 'division_' . (int) $division['division_id'];
            $amount = allocation_amount(isset($_POST[$key]) ? $_POST[$key] : '');
            if ($amount === null || $amount < 0) {
                $errors[] = 'จำนวนเงินของ ' . $division['division_name'] . ' ไม่ถูกต้อง';
                $amount = 0.0;
            }
            $divisionAmounts[(int) $division['division_id']] = $amount;
            $sumRequested += $amount;
        }

        $centralAmount = allocation_amount(isset($_POST['central_amount']) ? $_POST['central_amount'] : '');
        if ($centralAmount === null || $centralAmount < 0) {
            $errors[] = 'จำนวนงบส่วนกลางไม่ถูกต้อง';
            $centralAmount = 0.0;
        }
        $sumRequested += $centralAmount;

        $poolAmount = (float) $pool['initial_amount'];

        if ($sumRequested > $poolAmount + 0.005) {
            $errors[] = 'ยอดจัดสรรรวมเกินวงเงินของก้อนงบประมาณ ' . qa_money($sumRequested - $poolAmount) . ' บาท';
        }

        if (!empty($errors)) {
            $_SESSION['qa_allocation_flash'] = array(
                'type' => 'danger',
                'text' => implode(' / ', $errors)
            );
            allocation_redirect('pool=' . $poolId);
        }

        $oldSnapshot = allocation_snapshot($poolId);
        $userId = $user ? (int) $user['user_id'] : 0;
        $allocationDate = date('Y-m-d');

        $db->autocommit(false);
        $ok = true;

        try {
            foreach ($divisions as $division) {
                $divisionId = (int) $division['division_id'];
                $amount = (float) $divisionAmounts[$divisionId];

                $existingId = 0;
                $stmt = $db->prepare(
                    "SELECT allocation_id
                     FROM qa_budget_allocations
                     WHERE budget_pool_id = ?
                       AND allocation_type = 'division'
                       AND division_id = ?
                     LIMIT 1"
                );
                $stmt->bind_param('ii', $poolId, $divisionId);
                $stmt->execute();
                $stmt->bind_result($existingId);
                $stmt->fetch();
                $stmt->close();

                if ($existingId > 0) {
                    $stmt = $db->prepare(
                        "UPDATE qa_budget_allocations
                         SET allocated_amount = ?,
                             allocation_date = ?,
                             status = 'active'
                         WHERE allocation_id = ?"
                    );
                    $stmt->bind_param('dsi', $amount, $allocationDate, $existingId);
                    if (!$stmt->execute()) {
                        $ok = false;
                    }
                    $stmt->close();
                } else {
                    $allocationCode = 'ALLOC-' . $yearBE . '-P' . $poolId . '-D' . $divisionId;
                    $notes = 'จัดสรรวงเงินให้ ' . $division['division_name'];
                    $stmt = $db->prepare(
                        "INSERT INTO qa_budget_allocations
                        (allocation_code, year_id, budget_pool_id, allocation_type, division_id,
                         allocated_amount, allocation_date, status, notes, created_by)
                        VALUES (?, ?, ?, 'division', ?, ?, ?, 'active', ?, NULLIF(?,0))"
                    );
                    $stmt->bind_param(
                        'siiidssi',
                        $allocationCode,
                        $yearId,
                        $poolId,
                        $divisionId,
                        $amount,
                        $allocationDate,
                        $notes,
                        $userId
                    );
                    if (!$stmt->execute()) {
                        $ok = false;
                    }
                    $stmt->close();
                }

                if (!$ok) {
                    break;
                }
            }

            if ($ok) {
                $centralExistingId = 0;
                $stmt = $db->prepare(
                    "SELECT allocation_id
                     FROM qa_budget_allocations
                     WHERE budget_pool_id = ?
                       AND allocation_type = 'central'
                       AND division_id IS NULL
                     LIMIT 1"
                );
                $stmt->bind_param('i', $poolId);
                $stmt->execute();
                $stmt->bind_result($centralExistingId);
                $stmt->fetch();
                $stmt->close();

                if ($centralExistingId > 0) {
                    $stmt = $db->prepare(
                        "UPDATE qa_budget_allocations
                         SET allocated_amount = ?,
                             allocation_date = ?,
                             status = 'active'
                         WHERE allocation_id = ?"
                    );
                    $stmt->bind_param('dsi', $centralAmount, $allocationDate, $centralExistingId);
                    if (!$stmt->execute()) {
                        $ok = false;
                    }
                    $stmt->close();
                } else {
                    $allocationCode = 'ALLOC-' . $yearBE . '-P' . $poolId . '-CENTRAL';
                    $notes = 'งบส่วนกลาง / เงินสำรอง';
                    $stmt = $db->prepare(
                        "INSERT INTO qa_budget_allocations
                        (allocation_code, year_id, budget_pool_id, allocation_type, division_id,
                         allocated_amount, allocation_date, status, notes, created_by)
                        VALUES (?, ?, ?, 'central', NULL, ?, ?, 'active', ?, NULLIF(?,0))"
                    );
                    $stmt->bind_param(
                        'siidssi',
                        $allocationCode,
                        $yearId,
                        $poolId,
                        $centralAmount,
                        $allocationDate,
                        $notes,
                        $userId
                    );
                    if (!$stmt->execute()) {
                        $ok = false;
                    }
                    $stmt->close();
                }
            }

            if ($ok) {
                $db->commit();
            } else {
                $db->rollback();
            }
        } catch (Exception $e) {
            $ok = false;
            $db->rollback();
        }

        $db->autocommit(true);

        if ($ok) {
            $newSnapshot = allocation_snapshot($poolId);
            allocation_audit($poolId, $oldSnapshot, $newSnapshot);

            $_SESSION['qa_allocation_flash'] = array(
                'type' => 'success',
                'text' => 'บันทึกการจัดสรรงบประมาณเรียบร้อยแล้ว'
            );
            allocation_redirect('pool=' . $poolId);
        } else {
            $_SESSION['qa_allocation_flash'] = array(
                'type' => 'danger',
                'text' => 'ไม่สามารถบันทึกการจัดสรรงบประมาณได้ กรุณาลองใหม่'
            );
            allocation_redirect('pool=' . $poolId);
        }
    }
}

$message = '';
$messageType = 'success';
if (isset($_SESSION['qa_allocation_flash']) && is_array($_SESSION['qa_allocation_flash'])) {
    $messageType = isset($_SESSION['qa_allocation_flash']['type']) ? $_SESSION['qa_allocation_flash']['type'] : 'success';
    $message = isset($_SESSION['qa_allocation_flash']['text']) ? $_SESSION['qa_allocation_flash']['text'] : '';
    unset($_SESSION['qa_allocation_flash']);
}

$divisions = array();
$result = $db->query(
    "SELECT division_id, division_code, division_name, short_name
     FROM qa_divisions
     WHERE is_active = 1
     ORDER BY sort_order, division_id"
);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $divisions[] = $row;
    }
    $result->free();
}

$budgetPools = array();
if ($yearId > 0) {
    $stmt = $db->prepare(
        "SELECT
            p.budget_pool_id,
            p.pool_name,
            p.initial_amount,
            s.source_code,
            s.source_name,
            COALESCE((
                SELECT SUM(v.current_allocation)
                FROM vw_qa_budget_allocation_summary v
                WHERE v.budget_pool_id = p.budget_pool_id
            ),0) AS allocated_total
         FROM qa_budget_pools p
         INNER JOIN qa_budget_sources s ON s.source_id = p.source_id
         WHERE p.year_id = ?
         ORDER BY p.budget_pool_id"
    );
    $stmt->bind_param('i', $yearId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $budgetPools[] = $row;
        }
        $result->free();
    }
    $stmt->close();
}

$totalBudget = 0.0;
foreach ($budgetPools as $pool) {
    $totalBudget += (float) $pool['initial_amount'];
}

$divisionSummary = array();
foreach ($divisions as $division) {
    $divisionSummary[(int) $division['division_id']] = array(
        'division_id' => (int) $division['division_id'],
        'division_code' => $division['division_code'],
        'division_name' => $division['division_name'],
        'short_name' => $division['short_name'],
        'amount' => 0.0
    );
}

if ($yearId > 0) {
    $stmt = $db->prepare(
        "SELECT division_id, COALESCE(SUM(current_allocation),0) AS amount
         FROM vw_qa_budget_allocation_summary
         WHERE year_id = ? AND allocation_type = 'division'
         GROUP BY division_id"
    );
    $stmt->bind_param('i', $yearId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $divisionId = (int) $row['division_id'];
            if (isset($divisionSummary[$divisionId])) {
                $divisionSummary[$divisionId]['amount'] = (float) $row['amount'];
            }
        }
        $result->free();
    }
    $stmt->close();
}

$totalDivisionAllocated = 0.0;
foreach ($divisionSummary as $summary) {
    $totalDivisionAllocated += (float) $summary['amount'];
}

$centralAllocated = $yearId > 0 ? (float) qa_db_scalar(
    "SELECT COALESCE(SUM(current_allocation),0)
     FROM vw_qa_budget_allocation_summary
     WHERE year_id = " . $yearId . " AND allocation_type = 'central'",
    0
) : 0;

$totalAllocated = $totalDivisionAllocated + $centralAllocated;
$totalUnallocated = $totalBudget - $totalAllocated;

$selectedPoolId = isset($_GET['pool']) ? (int) $_GET['pool'] : 0;
if ($selectedPoolId <= 0 && !empty($budgetPools)) {
    $selectedPoolId = (int) $budgetPools[0]['budget_pool_id'];
}

$selectedPool = null;
foreach ($budgetPools as $pool) {
    if ((int) $pool['budget_pool_id'] === $selectedPoolId) {
        $selectedPool = $pool;
        break;
    }
}

$selectedBaseAmounts = array();
foreach ($divisions as $division) {
    $selectedBaseAmounts[(int) $division['division_id']] = 0.0;
}
$selectedCentralBase = 0.0;
$selectedCurrentAllocated = 0.0;

if ($selectedPool) {
    $stmt = $db->prepare(
        "SELECT allocation_type, division_id, allocated_amount
         FROM qa_budget_allocations
         WHERE budget_pool_id = ?"
    );
    $stmt->bind_param('i', $selectedPoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['allocation_type'] === 'division' && $row['division_id'] !== null) {
                $selectedBaseAmounts[(int) $row['division_id']] = (float) $row['allocated_amount'];
            } elseif ($row['allocation_type'] === 'central') {
                $selectedCentralBase = (float) $row['allocated_amount'];
            }
        }
        $result->free();
    }
    $stmt->close();

    $selectedCurrentAllocated = (float) qa_db_scalar(
        "SELECT COALESCE(SUM(current_allocation),0)
         FROM vw_qa_budget_allocation_summary
         WHERE budget_pool_id = " . (int) $selectedPoolId,
        0
    );
}

require QA_ROOT . '/includes/header.php';
?>

<?php if ($message !== ''): ?>
    <div class="alert <?= $messageType === 'danger' ? 'alert-danger' : 'alert-success' ?>" style="margin-bottom:16px">
        <?= h($message) ?>
    </div>
<?php endif; ?>

<div class="action-bar">
    <a class="btn" href="<?= h(qa_url('budget/index.php')) ?>">← งบประมาณประจำปี</a>
    <?php if ($selectedPool): ?>
        <a class="btn btn-primary" href="#allocation-form">กำหนดวงเงินของก้อนนี้</a>
    <?php endif; ?>
</div>

<div class="grid grid-4">
    <div class="card">
        <div class="metric-label">งบประมาณทั้งหมด</div>
        <div class="metric-value"><?= h(qa_money($totalBudget)) ?></div>
        <div class="metric-note">ปีงบประมาณ <?= $yearBE ? h($yearBE) : '-' ?></div>
    </div>
    <div class="card">
        <div class="metric-label">จัดสรร 4 ฝ่าย</div>
        <div class="metric-value"><?= h(qa_money($totalDivisionAllocated)) ?></div>
        <div class="metric-note">รวมทุกแหล่งเงิน</div>
    </div>
    <div class="card">
        <div class="metric-label">งบส่วนกลาง</div>
        <div class="metric-value"><?= h(qa_money($centralAllocated)) ?></div>
        <div class="metric-note">รวมทุกแหล่งเงิน</div>
    </div>
    <div class="card">
        <div class="metric-label">ยังไม่ได้จัดสรร</div>
        <div class="metric-value <?= $totalUnallocated < -0.005 ? 'text-danger' : '' ?>"><?= h(qa_money($totalUnallocated)) ?></div>
        <div class="metric-note"><?= $totalUnallocated < -0.005 ? 'กรุณาตรวจสอบยอดจัดสรร' : 'วงเงินที่ยังสามารถแบ่งได้' ?></div>
    </div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <div class="section-heading">
            <div>
                <h2>ภาพรวมการแบ่งงบประมาณ</h2>
                <p>สรุปวงเงินจากทุกก้อนงบประมาณรวมกัน</p>
            </div>
        </div>

        <?php if ($totalAllocated <= 0): ?>
            <div class="placeholder-chart">
                ยังไม่มีข้อมูลการจัดสรรงบประมาณ<br>
                <small>เลือกก้อนงบด้านขวาและกำหนดวงเงินให้แต่ละฝ่าย</small>
            </div>
        <?php else: ?>
            <div class="allocation-summary-list">
                <?php foreach ($divisionSummary as $summary): ?>
                    <?php
                    $pct = $totalBudget > 0 ? ((float) $summary['amount'] / $totalBudget) * 100 : 0;
                    if ($pct > 100) { $pct = 100; }
                    ?>
                    <div class="progress-row">
                        <div class="progress-head">
                            <span><?= h($summary['division_name']) ?></span>
                            <strong><?= h(qa_money($summary['amount'])) ?> บาท (<?= h(number_format($pct, 2)) ?>%)</strong>
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
                        <strong><?= h(qa_money($centralAllocated)) ?> บาท (<?= h(number_format($centralPct, 2)) ?>%)</strong>
                    </div>
                    <div class="progress"><span style="width:<?= h(number_format($centralPct, 2, '.', '')) ?>%"></span></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="section-heading">
            <div>
                <h2>เลือกก้อนงบประมาณ</h2>
                <p>ระบบจัดสรรแยกตามแหล่งเงิน เพื่อให้ตรวจสอบย้อนกลับได้ว่าเงินแต่ละฝ่ายมาจากงบใด</p>
            </div>
        </div>

        <?php if (empty($budgetPools)): ?>
            <div class="notice">
                ยังไม่มีรายการงบประมาณ กรุณาเพิ่มรายการที่เมนู “งบประมาณประจำปี” ก่อน
            </div>
            <a class="btn btn-primary" href="<?= h(qa_url('budget/index.php#budget-form')) ?>">＋ เพิ่มรายการงบประมาณ</a>
        <?php else: ?>
            <form method="get" action="<?= h(qa_url('budget/allocation.php')) ?>">
                <div class="form-group">
                    <label for="pool">แหล่งเงิน / ก้อนงบประมาณ</label>
                    <select name="pool" id="pool" onchange="this.form.submit()">
                        <?php foreach ($budgetPools as $pool): ?>
                            <option value="<?= h($pool['budget_pool_id']) ?>" <?= (int) $pool['budget_pool_id'] === $selectedPoolId ? 'selected' : '' ?>>
                                <?= h($pool['source_name'] . ' — ' . $pool['pool_name'] . ' (' . qa_money($pool['initial_amount']) . ' บาท)') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <?php if ($selectedPool): ?>
                <div class="pool-info-box">
                    <div>
                        <span>วงเงินก้อนนี้</span>
                        <strong><?= h(qa_money($selectedPool['initial_amount'])) ?> บาท</strong>
                    </div>
                    <div>
                        <span>จัดสรรแล้ว</span>
                        <strong><?= h(qa_money($selectedCurrentAllocated)) ?> บาท</strong>
                    </div>
                    <div>
                        <span>คงเหลือก้อนนี้</span>
                        <strong><?= h(qa_money((float) $selectedPool['initial_amount'] - $selectedCurrentAllocated)) ?> บาท</strong>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($selectedPool): ?>
<div class="card" id="allocation-form" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>กำหนดวงเงิน — <?= h($selectedPool['source_name']) ?></h2>
            <p><?= h($selectedPool['pool_name']) ?> • วงเงิน <?= h(qa_money($selectedPool['initial_amount'])) ?> บาท</p>
        </div>
        <?php if (!$canManage): ?>
            <span class="badge badge-gray">ดูข้อมูลอย่างเดียว</span>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= h(qa_url('budget/allocation.php?pool=' . $selectedPoolId)) ?>" id="allocationForm">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="save_allocation">
        <input type="hidden" name="budget_pool_id" value="<?= h($selectedPoolId) ?>">
        <input type="hidden" id="poolAmount" value="<?= h(number_format((float) $selectedPool['initial_amount'], 2, '.', '')) ?>">

        <div class="allocation-input-grid">
            <?php foreach ($divisions as $division): ?>
                <?php
                $divisionId = (int) $division['division_id'];
                $amount = isset($selectedBaseAmounts[$divisionId]) ? (float) $selectedBaseAmounts[$divisionId] : 0;
                ?>
                <div class="allocation-input-card">
                    <label for="division_<?= h($divisionId) ?>"><?= h($division['division_name']) ?></label>
                    <input
                        class="allocation-amount"
                        type="number"
                        min="0"
                        step="0.01"
                        id="division_<?= h($divisionId) ?>"
                        name="division_<?= h($divisionId) ?>"
                        value="<?= h(number_format($amount, 2, '.', '')) ?>"
                        <?= $canManage ? '' : 'readonly' ?>>
                    <div class="allocation-percent" data-for="division_<?= h($divisionId) ?>">0.00%</div>
                </div>
            <?php endforeach; ?>

            <div class="allocation-input-card central">
                <label for="central_amount">งบส่วนกลาง / เงินสำรอง</label>
                <input
                    class="allocation-amount"
                    type="number"
                    min="0"
                    step="0.01"
                    id="central_amount"
                    name="central_amount"
                    value="<?= h(number_format($selectedCentralBase, 2, '.', '')) ?>"
                    <?= $canManage ? '' : 'readonly' ?>>
                <div class="allocation-percent" data-for="central_amount">0.00%</div>
            </div>
        </div>

        <div class="allocation-total-box" id="allocationTotalBox">
            <div><span>รวมที่กำหนด</span><strong id="allocationTotal">0.00</strong></div>
            <div><span>คงเหลือ</span><strong id="allocationRemain">0.00</strong></div>
            <div><span>สถานะ</span><strong id="allocationStatus">-</strong></div>
        </div>

        <?php if ($canManage): ?>
            <div class="action-bar" style="margin-top:16px; margin-bottom:0">
                <button class="btn btn-primary" type="submit" id="saveAllocationBtn">บันทึกการจัดสรร</button>
                <span class="help-text">ยอดรวมของ 4 ฝ่าย + งบส่วนกลาง ต้องไม่เกินวงเงินของก้อนงบประมาณนี้</span>
            </div>
        <?php endif; ?>
    </form>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>รายละเอียด 4 ฝ่าย</h2>
            <p>ยอดด้านล่างเป็นผลรวมจากทุกแหล่งเงินในปีงบประมาณ <?= $yearBE ? h($yearBE) : '-' ?></p>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>ฝ่าย</th>
                <th class="text-right">วงเงินจัดสรร</th>
                <th class="text-right">% ของงบทั้งหมด</th>
                <th>รายละเอียด</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($divisionSummary as $summary): ?>
                <?php $pct = $totalBudget > 0 ? ((float) $summary['amount'] / $totalBudget) * 100 : 0; ?>
                <tr>
                    <td><strong><?= h($summary['division_name']) ?></strong></td>
                    <td class="text-right money-cell"><?= h(qa_money($summary['amount'])) ?></td>
                    <td class="text-right"><?= h(number_format($pct, 2)) ?>%</td>
                    <td>
                        <a class="btn btn-sm" href="<?= h(qa_url('budget/allocation-detail.php?division=' . urlencode($summary['division_code']))) ?>">
                            ดูฝ่าย
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr class="table-total-row">
                <td><strong>งบส่วนกลาง / เงินสำรอง</strong></td>
                <td class="text-right"><strong><?= h(qa_money($centralAllocated)) ?></strong></td>
                <td class="text-right"><strong><?= h(number_format($totalBudget > 0 ? ($centralAllocated / $totalBudget) * 100 : 0, 2)) ?>%</strong></td>
                <td>-</td>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('allocationForm');
    if (!form) return;

    var poolAmount = parseFloat(document.getElementById('poolAmount').value || '0');
    var inputs = form.querySelectorAll('.allocation-amount');
    var totalEl = document.getElementById('allocationTotal');
    var remainEl = document.getElementById('allocationRemain');
    var statusEl = document.getElementById('allocationStatus');
    var totalBox = document.getElementById('allocationTotalBox');
    var saveBtn = document.getElementById('saveAllocationBtn');

    function money(value) {
        return value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function recalc() {
        var total = 0;

        for (var i = 0; i < inputs.length; i++) {
            var value = parseFloat(inputs[i].value || '0');
            if (!isFinite(value) || value < 0) value = 0;
            total += value;

            var pct = poolAmount > 0 ? (value / poolAmount) * 100 : 0;
            var percentEl = form.querySelector('.allocation-percent[data-for="' + inputs[i].id + '"]');
            if (percentEl) {
                percentEl.innerHTML = pct.toFixed(2) + '%';
            }
        }

        var remain = poolAmount - total;
        totalEl.innerHTML = money(total);
        remainEl.innerHTML = money(remain);

        if (total > poolAmount + 0.005) {
            statusEl.innerHTML = 'เกินวงเงิน';
            totalBox.className = 'allocation-total-box danger';
            if (saveBtn) saveBtn.disabled = true;
        } else if (Math.abs(remain) <= 0.005) {
            statusEl.innerHTML = 'จัดสรรครบ';
            totalBox.className = 'allocation-total-box success';
            if (saveBtn) saveBtn.disabled = false;
        } else {
            statusEl.innerHTML = 'ยังเหลือ ' + money(remain) + ' บาท';
            totalBox.className = 'allocation-total-box';
            if (saveBtn) saveBtn.disabled = false;
        }
    }

    for (var i = 0; i < inputs.length; i++) {
        inputs[i].addEventListener('input', recalc);
    }

    recalc();
})();
</script>

<?php require QA_ROOT . '/includes/footer.php'; ?>
