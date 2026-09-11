<?php
$pageTitle = 'เสนอโครงการใหม่';
$pageHeading = 'เสนอโครงการ / งาน / กิจกรรมใหม่';
$pageDescription = 'เชื่อมวงเงินของฝ่าย แผนพัฒนาคุณภาพ จุดเน้น เป้าหมาย และตัวชี้วัด สมศ. ตั้งแต่ต้นทาง';
$activeMenu = 'project-create';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

$db = qa_db();
$user = qa_current_user();
$currentYear = qa_current_year_row();
$yearId = $currentYear ? (int) $currentYear['year_id'] : 0;
$yearBE = $currentYear ? (int) $currentYear['fiscal_year_be'] : 0;

$breadcrumbs = array(
    array('label' => 'โครงการทั้งหมด', 'url' => qa_url('projects/index.php')),
    array('label' => 'เสนอโครงการใหม่')
);

$canCreate = (
    qa_user_has_role('admin') ||
    qa_user_has_role('director') ||
    qa_user_has_role('plan') ||
    qa_user_has_role('budget') ||
    qa_user_has_role('division_head') ||
    qa_user_has_role('project_owner')
);

if (!$canCreate) {
    http_response_code(403);
    die('บัญชีของคุณไม่มีสิทธิ์สร้างโครงการ');
}

function project_clean($value)
{
    return trim((string) $value);
}

function project_amount($value)
{
    $value = str_replace(',', '', trim((string) $value));
    if ($value === '') return 0.0;
    return is_numeric($value) ? (float) $value : null;
}

function project_date($value)
{
    $value = trim((string) $value);
    if ($value === '') return '';
    $p = explode('-', $value);
    if (count($p) !== 3) return false;
    return checkdate((int)$p[1], (int)$p[2], (int)$p[0]) ? $value : false;
}

function project_id_array($value)
{
    $out = array();
    if (!is_array($value)) return $out;
    foreach ($value as $item) {
        $id = (int) $item;
        if ($id > 0) $out[$id] = $id;
    }
    return array_values($out);
}

function project_get_user_profile($userId)
{
    $db = qa_db();
    $stmt = $db->prepare(
        "SELECT user_id, username, prefix, first_name, last_name, position_name, primary_division_id
         FROM qa_users
         WHERE user_id=? LIMIT 1"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if ($result) $result->free();
    $stmt->close();
    return $row;
}

function project_make_code($yearId, $yearBE)
{
    $db = qa_db();
    $stmt = $db->prepare(
        "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(project_code,'-',-1) AS UNSIGNED)),0)+1
         FROM qa_projects WHERE year_id=?"
    );
    $stmt->bind_param('i', $yearId);
    $stmt->execute();
    $stmt->bind_result($nextNo);
    $stmt->fetch();
    $stmt->close();

    $nextNo = (int) $nextNo;
    if ($nextNo <= 0) $nextNo = 1;
    return 'PRJ-' . $yearBE . '-' . str_pad($nextNo, 3, '0', STR_PAD_LEFT);
}

function project_audit_create($projectId, $data)
{
    $db = qa_db();
    $user = qa_current_user();
    $userId = $user ? (int)$user['user_id'] : 0;
    $newJson = json_encode($data, JSON_UNESCAPED_UNICODE);
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '';

    $stmt = $db->prepare(
        "INSERT INTO qa_audit_logs
        (user_id, action_name, module_name, record_table, record_id, new_data, ip_address, user_agent)
        VALUES (NULLIF(?,0), 'create_project', 'projects', 'qa_projects', ?, ?, ?, ?)"
    );
    if ($stmt) {
        $recordId = (string)$projectId;
        $stmt->bind_param('issss', $userId, $recordId, $newJson, $ip, $ua);
        $stmt->execute();
        $stmt->close();
    }
}

function project_belongs_to_plan($table, $idField, $id, $planId)
{
    $db = qa_db();
    $allowed = array(
        'qa_missions' => 'mission_id',
        'qa_strategies' => 'strategy_id',
        'qa_focus_areas' => 'focus_id',
        'qa_plan_targets' => 'target_id'
    );
    if (!isset($allowed[$table]) || $allowed[$table] !== $idField) return false;

    $sql = "SELECT COUNT(*) FROM " . $table . " WHERE " . $idField . "=? AND plan_id=?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $id, $planId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int)$count === 1;
}

function project_valid_indicator($indicatorId)
{
    $db = qa_db();
    $stmt = $db->prepare(
        "SELECT COUNT(*)
         FROM qa_indicators i
         INNER JOIN qa_standards s ON s.standard_id=i.standard_id
         INNER JOIN qa_quality_frameworks f ON f.framework_id=s.framework_id
         WHERE i.indicator_id=? AND f.framework_code='ONESQA' AND i.is_active=1"
    );
    $stmt->bind_param('i', $indicatorId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int)$count === 1;
}

function project_pool_info($poolId, $divisionId, $yearId)
{
    $db = qa_db();
    $stmt = $db->prepare(
        "SELECT
            p.budget_pool_id,
            p.pool_name,
            p.initial_amount,
            s.source_name,
            v.current_allocation,
            COALESCE((
                SELECT SUM(
                    CASE
                      WHEN bi.approved_amount > 0
                           AND pr.status_code IN ('PENDING_APPROVAL','APPROVED','IN_PROGRESS','WAITING_REPORT','COMPLETED','CLOSED')
                      THEN bi.approved_amount
                      ELSE bi.requested_amount
                    END
                )
                FROM qa_project_budget_items bi
                INNER JOIN qa_projects pr ON pr.project_id=bi.project_id
                WHERE bi.budget_pool_id=p.budget_pool_id
                  AND pr.year_id=?
                  AND pr.division_id=?
                  AND pr.status_code NOT IN ('DRAFT','REJECTED','CANCELLED')
            ),0) AS reserved_amount
         FROM qa_budget_pools p
         INNER JOIN qa_budget_sources s ON s.source_id=p.source_id
         INNER JOIN vw_qa_budget_allocation_summary v
            ON v.budget_pool_id=p.budget_pool_id
           AND v.year_id=?
           AND v.allocation_type='division'
           AND v.division_id=?
         WHERE p.budget_pool_id=? AND p.year_id=?
         LIMIT 1"
    );
    $stmt->bind_param('iiiiii', $yearId, $divisionId, $yearId, $divisionId, $poolId, $yearId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if ($result) $result->free();
    $stmt->close();

    if ($row) {
        $row['available_amount'] = (float)$row['current_allocation'] - (float)$row['reserved_amount'];
    }
    return $row;
}

$userProfile = project_get_user_profile((int)$user['user_id']);
$ownerName = $user ? $user['display_name'] : '';
$primaryDivisionId = $userProfile && !empty($userProfile['primary_division_id'])
    ? (int)$userProfile['primary_division_id']
    : 0;

$divisions = array();
$result = $db->query(
    "SELECT division_id, division_code, division_name, short_name
     FROM qa_divisions WHERE is_active=1 ORDER BY sort_order, division_id"
);
if ($result) {
    while ($row = $result->fetch_assoc()) $divisions[] = $row;
    $result->free();
}

$plans = array();
$result = $db->query(
    "SELECT plan_id, plan_code, plan_name, plan_type, start_year_be, end_year_be
     FROM qa_plans
     WHERE status='active'
     ORDER BY
       CASE
         WHEN start_year_be IS NOT NULL AND end_year_be IS NOT NULL
              AND " . (int)$yearBE . " BETWEEN start_year_be AND end_year_be
         THEN 0 ELSE 1
       END,
       plan_id DESC"
);
if ($result) {
    while ($row = $result->fetch_assoc()) $plans[] = $row;
    $result->free();
}

$selectedPlanId = isset($_POST['plan_id'])
    ? (int)$_POST['plan_id']
    : (isset($_GET['plan']) ? (int)$_GET['plan'] : 0);

if ($selectedPlanId <= 0 && !empty($plans)) {
    $selectedPlanId = (int)$plans[0]['plan_id'];
}

$selectedDivisionId = isset($_POST['division_id'])
    ? (int)$_POST['division_id']
    : (isset($_GET['division']) ? (int)$_GET['division'] : $primaryDivisionId);

if ($selectedDivisionId <= 0 && !empty($divisions)) {
    $selectedDivisionId = (int)$divisions[0]['division_id'];
}

/* Master Data ของแผน */
$missions = array();
$strategies = array();
$focusAreas = array();
$planTargets = array();
$schoolValues = array();

if ($selectedPlanId > 0) {
    $stmt = $db->prepare("SELECT mission_id, mission_code, mission_text FROM qa_missions WHERE plan_id=? AND is_active=1 ORDER BY sort_order, mission_id");
    $stmt->bind_param('i', $selectedPlanId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) $missions[] = $row;
        $result->free();
    }
    $stmt->close();

    $stmt = $db->prepare(
        "SELECT s.strategy_id, s.strategy_code, s.strategy_name, s.mission_id, m.mission_code
         FROM qa_strategies s
         LEFT JOIN qa_missions m ON m.mission_id=s.mission_id
         WHERE s.plan_id=? AND s.is_active=1
         ORDER BY s.sort_order, s.strategy_id"
    );
    $stmt->bind_param('i', $selectedPlanId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) $strategies[] = $row;
        $result->free();
    }
    $stmt->close();

    $stmt = $db->prepare("SELECT focus_id, focus_code, focus_name FROM qa_focus_areas WHERE plan_id=? AND is_active=1 ORDER BY sort_order, focus_id");
    $stmt->bind_param('i', $selectedPlanId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) $focusAreas[] = $row;
        $result->free();
    }
    $stmt->close();

    $stmt = $db->prepare(
        "SELECT target_id, target_code, target_name, target_value, target_unit
         FROM qa_plan_targets
         WHERE plan_id=? AND is_active=1
         ORDER BY sort_order, target_id"
    );
    $stmt->bind_param('i', $selectedPlanId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) $planTargets[] = $row;
        $result->free();
    }
    $stmt->close();

    $stmt = $db->prepare("SELECT value_id, value_code, value_name FROM qa_school_values WHERE plan_id=? AND is_active=1 ORDER BY sort_order, value_id");
    $stmt->bind_param('i', $selectedPlanId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) $schoolValues[] = $row;
        $result->free();
    }
    $stmt->close();
}

/* สมศ. 16 ตัวชี้วัด */
$onesqaIndicators = array();
$result = $db->query(
    "SELECT i.indicator_id, i.indicator_code, i.indicator_name,
            s.standard_code, s.standard_name
     FROM qa_indicators i
     INNER JOIN qa_standards s ON s.standard_id=i.standard_id
     INNER JOIN qa_quality_frameworks f ON f.framework_id=s.framework_id
     WHERE f.framework_code='ONESQA' AND i.is_active=1
     ORDER BY CAST(s.standard_code AS UNSIGNED), i.sort_order"
);
if ($result) {
    while ($row = $result->fetch_assoc()) $onesqaIndicators[] = $row;
    $result->free();
}

/* ก้อนงบของฝ่าย */
$divisionPools = array();
if ($yearId > 0 && $selectedDivisionId > 0) {
    $stmt = $db->prepare(
        "SELECT
            p.budget_pool_id,
            p.pool_name,
            s.source_name,
            v.current_allocation,
            COALESCE((
                SELECT SUM(
                    CASE
                      WHEN bi.approved_amount > 0
                           AND pr.status_code IN ('PENDING_APPROVAL','APPROVED','IN_PROGRESS','WAITING_REPORT','COMPLETED','CLOSED')
                      THEN bi.approved_amount
                      ELSE bi.requested_amount
                    END
                )
                FROM qa_project_budget_items bi
                INNER JOIN qa_projects pr ON pr.project_id=bi.project_id
                WHERE bi.budget_pool_id=p.budget_pool_id
                  AND pr.year_id=?
                  AND pr.division_id=?
                  AND pr.status_code NOT IN ('DRAFT','REJECTED','CANCELLED')
            ),0) AS reserved_amount
         FROM qa_budget_pools p
         INNER JOIN qa_budget_sources s ON s.source_id=p.source_id
         INNER JOIN vw_qa_budget_allocation_summary v
            ON v.budget_pool_id=p.budget_pool_id
           AND v.year_id=?
           AND v.allocation_type='division'
           AND v.division_id=?
         WHERE p.year_id=? AND v.current_allocation > 0
         ORDER BY p.budget_pool_id"
    );
    $stmt->bind_param('iiiii', $yearId, $selectedDivisionId, $yearId, $selectedDivisionId, $yearId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['available_amount'] = (float)$row['current_allocation'] - (float)$row['reserved_amount'];
            $divisionPools[] = $row;
        }
        $result->free();
    }
    $stmt->close();
}

$divisionAllocated = 0.0;
$divisionReserved = 0.0;
foreach ($divisionPools as $pool) {
    $divisionAllocated += (float)$pool['current_allocation'];
    $divisionReserved += (float)$pool['reserved_amount'];
}
$divisionAvailable = $divisionAllocated - $divisionReserved;

$formErrors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!qa_verify_csrf($token)) {
        http_response_code(400);
        die('CSRF token ไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }

    $action = isset($_POST['action']) ? $_POST['action'] : 'save_draft';
    $isSubmit = $action === 'submit_project';

    $planId = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0;
    $divisionId = isset($_POST['division_id']) ? (int)$_POST['division_id'] : 0;
    $projectType = project_clean(isset($_POST['project_type']) ? $_POST['project_type'] : 'project');
    $projectName = project_clean(isset($_POST['project_name']) ? $_POST['project_name'] : '');
    $locationText = project_clean(isset($_POST['location_text']) ? $_POST['location_text'] : '');
    $startDate = project_date(isset($_POST['start_date']) ? $_POST['start_date'] : '');
    $endDate = project_date(isset($_POST['end_date']) ? $_POST['end_date'] : '');
    $principleReason = project_clean(isset($_POST['principle_reason']) ? $_POST['principle_reason'] : '');
    $needProblem = project_clean(isset($_POST['need_problem']) ? $_POST['need_problem'] : '');
    $expectedOutput = project_clean(isset($_POST['expected_output']) ? $_POST['expected_output'] : '');
    $expectedOutcome = project_clean(isset($_POST['expected_outcome']) ? $_POST['expected_outcome'] : '');

    $beneficiaryType = project_clean(isset($_POST['beneficiary_type']) ? $_POST['beneficiary_type'] : '');
    $beneficiaryDetail = project_clean(isset($_POST['beneficiary_detail']) ? $_POST['beneficiary_detail'] : '');
    $beneficiaryCount = isset($_POST['beneficiary_count']) ? (int)$_POST['beneficiary_count'] : 0;
    $beneficiaryUnit = project_clean(isset($_POST['beneficiary_unit']) ? $_POST['beneficiary_unit'] : 'คน');

    $objectivesText = project_clean(isset($_POST['objectives_text']) ? $_POST['objectives_text'] : '');
    $objectiveLines = preg_split('/\r\n|\r|\n/', $objectivesText);
    $objectives = array();
    foreach ($objectiveLines as $line) {
        $line = trim($line);
        $line = preg_replace('/^\s*\d+[\.\)]\s*/u', '', $line);
        if ($line !== '') $objectives[] = $line;
    }

    $missionIds = project_id_array(isset($_POST['mission_ids']) ? $_POST['mission_ids'] : array());
    $strategyIds = project_id_array(isset($_POST['strategy_ids']) ? $_POST['strategy_ids'] : array());
    $focusIds = project_id_array(isset($_POST['focus_ids']) ? $_POST['focus_ids'] : array());
    $targetIds = project_id_array(isset($_POST['target_ids']) ? $_POST['target_ids'] : array());
    $indicatorIds = project_id_array(isset($_POST['indicator_ids']) ? $_POST['indicator_ids'] : array());

    if ($yearId <= 0) $formErrors[] = 'ยังไม่ได้กำหนดปีงบประมาณที่ใช้งาน';
    if ($planId <= 0) $formErrors[] = 'กรุณาเลือกแผนพัฒนาคุณภาพ';
    if ($divisionId <= 0) $formErrors[] = 'กรุณาเลือกฝ่ายรับผิดชอบ';
    if (!in_array($projectType, array('project','work','activity'), true)) $formErrors[] = 'ประเภทโครงการไม่ถูกต้อง';
    if ($projectName === '') $formErrors[] = 'กรุณาระบุชื่อโครงการ / งาน / กิจกรรม';
    if ($startDate === false || $endDate === false) $formErrors[] = 'วันที่ดำเนินการไม่ถูกต้อง';
    if ($startDate && $endDate && $endDate < $startDate) $formErrors[] = 'วันที่สิ้นสุดต้องไม่น้อยกว่าวันที่เริ่ม';

    if ($isSubmit) {
        if ($principleReason === '') $formErrors[] = 'กรุณาระบุหลักการและเหตุผล';
        if (empty($objectives)) $formErrors[] = 'กรุณาระบุวัตถุประสงค์อย่างน้อย 1 ข้อ';
        if (!empty($missions) && empty($missionIds)) $formErrors[] = 'กรุณาเลือกพันธกิจอย่างน้อย 1 ข้อ';
        if (!empty($strategies) && empty($strategyIds)) $formErrors[] = 'กรุณาเลือกกลยุทธ์อย่างน้อย 1 ข้อ';
        if (!empty($focusAreas) && empty($focusIds)) $formErrors[] = 'กรุณาเลือกจุดเน้นโรงเรียนอย่างน้อย 1 ข้อ';
        if (!empty($planTargets) && empty($targetIds)) $formErrors[] = 'กรุณาเลือกเป้าหมายของแผนอย่างน้อย 1 ข้อ';
        if (empty($indicatorIds)) $formErrors[] = 'กรุณาเลือกตัวชี้วัด สมศ. อย่างน้อย 1 ตัว';
        if ($expectedOutput === '') $formErrors[] = 'กรุณาระบุ Output ที่คาดว่าจะได้รับ';
        if ($expectedOutcome === '') $formErrors[] = 'กรุณาระบุ Outcome ที่คาดว่าจะเกิดขึ้น';
    }

    foreach ($missionIds as $id) {
        if (!project_belongs_to_plan('qa_missions','mission_id',$id,$planId)) $formErrors[] = 'พบพันธกิจที่ไม่อยู่ในแผนที่เลือก';
    }
    foreach ($strategyIds as $id) {
        if (!project_belongs_to_plan('qa_strategies','strategy_id',$id,$planId)) $formErrors[] = 'พบกลยุทธ์ที่ไม่อยู่ในแผนที่เลือก';
    }
    foreach ($focusIds as $id) {
        if (!project_belongs_to_plan('qa_focus_areas','focus_id',$id,$planId)) $formErrors[] = 'พบจุดเน้นที่ไม่อยู่ในแผนที่เลือก';
    }
    foreach ($targetIds as $id) {
        if (!project_belongs_to_plan('qa_plan_targets','target_id',$id,$planId)) $formErrors[] = 'พบเป้าหมายที่ไม่อยู่ในแผนที่เลือก';
    }
    foreach ($indicatorIds as $id) {
        if (!project_valid_indicator($id)) $formErrors[] = 'พบตัวชี้วัด สมศ. ที่ไม่ถูกต้อง';
    }

    /* Budget lines */
    $activityNames = isset($_POST['activity_name']) && is_array($_POST['activity_name']) ? $_POST['activity_name'] : array();
    $expenseCategories = isset($_POST['expense_category']) && is_array($_POST['expense_category']) ? $_POST['expense_category'] : array();
    $itemNames = isset($_POST['item_name']) && is_array($_POST['item_name']) ? $_POST['item_name'] : array();
    $quantities = isset($_POST['quantity']) && is_array($_POST['quantity']) ? $_POST['quantity'] : array();
    $unitNames = isset($_POST['unit_name']) && is_array($_POST['unit_name']) ? $_POST['unit_name'] : array();
    $unitPrices = isset($_POST['unit_price']) && is_array($_POST['unit_price']) ? $_POST['unit_price'] : array();
    $poolIds = isset($_POST['budget_pool_id']) && is_array($_POST['budget_pool_id']) ? $_POST['budget_pool_id'] : array();

    $budgetLines = array();
    $requestedBudget = 0.0;
    $requestedByPool = array();
    $maxRows = max(count($activityNames), count($itemNames), count($unitPrices), count($poolIds));

    for ($i=0; $i<$maxRows; $i++) {
        $activityName = project_clean(isset($activityNames[$i]) ? $activityNames[$i] : '');
        $expenseCategory = project_clean(isset($expenseCategories[$i]) ? $expenseCategories[$i] : '');
        $itemName = project_clean(isset($itemNames[$i]) ? $itemNames[$i] : '');
        $quantity = project_amount(isset($quantities[$i]) ? $quantities[$i] : '1');
        $unitName = project_clean(isset($unitNames[$i]) ? $unitNames[$i] : '');
        $unitPrice = project_amount(isset($unitPrices[$i]) ? $unitPrices[$i] : '0');
        $poolId = isset($poolIds[$i]) ? (int)$poolIds[$i] : 0;

        if ($activityName === '' && $itemName === '' && (!$unitPrice || $unitPrice == 0)) continue;

        if ($quantity === null || $quantity <= 0) {
            $formErrors[] = 'จำนวนในรายการงบประมาณต้องมากกว่า 0';
            continue;
        }
        if ($unitPrice === null || $unitPrice < 0) {
            $formErrors[] = 'ราคาต่อหน่วยในรายการงบประมาณไม่ถูกต้อง';
            continue;
        }
        if ($activityName === '') $formErrors[] = 'กรุณาระบุชื่อกิจกรรมของรายการงบประมาณ';
        if ($itemName === '') $formErrors[] = 'กรุณาระบุรายการค่าใช้จ่าย';
        if ($poolId <= 0) $formErrors[] = 'กรุณาเลือกแหล่งเงินของรายการงบประมาณ';

        $amount = (float)$quantity * (float)$unitPrice;
        if ($amount <= 0) continue;

        $budgetLines[] = array(
            'activity_name'=>$activityName,
            'expense_category'=>$expenseCategory,
            'item_name'=>$itemName,
            'quantity'=>(float)$quantity,
            'unit_name'=>$unitName,
            'unit_price'=>(float)$unitPrice,
            'budget_pool_id'=>$poolId,
            'amount'=>$amount
        );
        $requestedBudget += $amount;
        if (!isset($requestedByPool[$poolId])) $requestedByPool[$poolId] = 0.0;
        $requestedByPool[$poolId] += $amount;
    }

    if ($isSubmit && $requestedBudget <= 0) {
        $formErrors[] = 'กรุณาระบุงบประมาณที่ขออย่างน้อย 1 รายการ';
    }

    foreach ($requestedByPool as $poolId => $amount) {
        $poolInfo = project_pool_info((int)$poolId, $divisionId, $yearId);
        if (!$poolInfo) {
            $formErrors[] = 'พบแหล่งเงินที่ไม่ได้จัดสรรให้ฝ่ายนี้';
            continue;
        }
        if ($isSubmit && $amount > (float)$poolInfo['available_amount'] + 0.005) {
            $formErrors[] = 'วงเงินที่ขอจาก "' . $poolInfo['source_name'] . ' — ' . $poolInfo['pool_name'] .
                '" เกินวงเงินพร้อมใช้ ' . qa_money($poolInfo['available_amount']) . ' บาท';
        }
    }

    /* KPIs */
    $kpiNames = isset($_POST['kpi_name']) && is_array($_POST['kpi_name']) ? $_POST['kpi_name'] : array();
    $kpiTypes = isset($_POST['kpi_type']) && is_array($_POST['kpi_type']) ? $_POST['kpi_type'] : array();
    $kpiTargets = isset($_POST['kpi_target']) && is_array($_POST['kpi_target']) ? $_POST['kpi_target'] : array();
    $kpiUnits = isset($_POST['kpi_unit']) && is_array($_POST['kpi_unit']) ? $_POST['kpi_unit'] : array();
    $kpiMethods = isset($_POST['kpi_method']) && is_array($_POST['kpi_method']) ? $_POST['kpi_method'] : array();

    $kpis = array();
    $kpiRows = max(count($kpiNames), count($kpiTargets));
    for ($i=0; $i<$kpiRows; $i++) {
        $name = project_clean(isset($kpiNames[$i]) ? $kpiNames[$i] : '');
        $type = project_clean(isset($kpiTypes[$i]) ? $kpiTypes[$i] : 'quantitative');
        $target = project_amount(isset($kpiTargets[$i]) ? $kpiTargets[$i] : '');
        $unit = project_clean(isset($kpiUnits[$i]) ? $kpiUnits[$i] : '');
        $method = project_clean(isset($kpiMethods[$i]) ? $kpiMethods[$i] : '');

        if ($name === '' && ($target === 0.0 || $target === null)) continue;
        if ($name === '') {
            $formErrors[] = 'กรุณาระบุข้อความ KPI';
            continue;
        }
        if ($target === null) {
            $formErrors[] = 'ค่าเป้าหมาย KPI ต้องเป็นตัวเลข';
            continue;
        }
        if (!in_array($type, array('quantitative','qualitative'), true)) $type = 'quantitative';

        $kpis[] = array(
            'name'=>$name, 'type'=>$type, 'target'=>$target,
            'unit'=>$unit, 'method'=>$method
        );
    }

    if ($isSubmit && empty($kpis)) $formErrors[] = 'กรุณาระบุ KPI อย่างน้อย 1 ข้อ';

    if (empty($formErrors)) {
        $db->autocommit(false);
        try {
            $projectCode = project_make_code($yearId, $yearBE);
            $statusCode = $isSubmit ? 'SUBMITTED' : 'DRAFT';
            $ownerUserId = (int)$user['user_id'];
            $submittedAt = $isSubmit ? date('Y-m-d H:i:s') : '';
            $startDateDb = $startDate ? $startDate : '';
            $endDateDb = $endDate ? $endDate : '';

            $stmt = $db->prepare(
                "INSERT INTO qa_projects
                (project_code, year_id, plan_id, division_id, project_type, project_name,
                 principle_reason, need_problem, expected_output, expected_outcome,
                 location_text, start_date, end_date, owner_user_id,
                 requested_budget, approved_budget, status_code, submitted_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), NULLIF(?,''),
                        NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), ?, ?, 0, ?, NULLIF(?,''), ?)"
            );
            if (!$stmt) throw new Exception('prepare qa_projects failed');

            $stmt->bind_param(
                'siiisssssssssidssi',
                $projectCode, $yearId, $planId, $divisionId, $projectType, $projectName,
                $principleReason, $needProblem, $expectedOutput, $expectedOutcome,
                $locationText, $startDateDb, $endDateDb, $ownerUserId,
                $requestedBudget, $statusCode, $submittedAt, $ownerUserId
            );
            if (!$stmt->execute()) throw new Exception('insert qa_projects failed: ' . $stmt->error);
            $projectId = $stmt->insert_id;
            $stmt->close();

            foreach ($objectives as $index => $objective) {
                $sort = $index + 1;
                $stmt = $db->prepare("INSERT INTO qa_project_objectives (project_id, objective_text, sort_order) VALUES (?, ?, ?)");
                $stmt->bind_param('isi', $projectId, $objective, $sort);
                if (!$stmt->execute()) throw new Exception('insert objective failed');
                $stmt->close();
            }

            if ($beneficiaryType !== '' || $beneficiaryDetail !== '' || $beneficiaryCount > 0) {
                $stmt = $db->prepare(
                    "INSERT INTO qa_project_beneficiaries
                    (project_id, group_type, group_detail, target_count, unit_name)
                    VALUES (?, ?, NULLIF(?,''), NULLIF(?,0), ?)"
                );
                $stmt->bind_param('issis', $projectId, $beneficiaryType, $beneficiaryDetail, $beneficiaryCount, $beneficiaryUnit);
                if (!$stmt->execute()) throw new Exception('insert beneficiary failed');
                $stmt->close();
            }

            foreach ($missionIds as $id) {
                $stmt = $db->prepare("INSERT INTO qa_project_mission_links (project_id, mission_id) VALUES (?, ?)");
                $stmt->bind_param('ii', $projectId, $id);
                if (!$stmt->execute()) throw new Exception('insert mission link failed');
                $stmt->close();
            }
            foreach ($strategyIds as $id) {
                $stmt = $db->prepare("INSERT INTO qa_project_strategy_links (project_id, strategy_id) VALUES (?, ?)");
                $stmt->bind_param('ii', $projectId, $id);
                if (!$stmt->execute()) throw new Exception('insert strategy link failed');
                $stmt->close();
            }
            foreach ($focusIds as $id) {
                $stmt = $db->prepare("INSERT INTO qa_project_focus_links (project_id, focus_id) VALUES (?, ?)");
                $stmt->bind_param('ii', $projectId, $id);
                if (!$stmt->execute()) throw new Exception('insert focus link failed');
                $stmt->close();
            }
            foreach ($targetIds as $id) {
                $stmt = $db->prepare("INSERT INTO qa_project_target_links (project_id, target_id) VALUES (?, ?)");
                $stmt->bind_param('ii', $projectId, $id);
                if (!$stmt->execute()) throw new Exception('insert target link failed');
                $stmt->close();
            }
            foreach ($indicatorIds as $index => $id) {
                $primary = $index === 0 ? 1 : 0;
                $stmt = $db->prepare("INSERT INTO qa_project_indicator_links (project_id, indicator_id, is_primary) VALUES (?, ?, ?)");
                $stmt->bind_param('iii', $projectId, $id, $primary);
                if (!$stmt->execute()) throw new Exception('insert indicator link failed');
                $stmt->close();
            }

            $activityMap = array();
            $activitySort = 0;
            foreach ($budgetLines as $index => $line) {
                $activityKey = trim($line['activity_name']);
                if (!isset($activityMap[$activityKey])) {
                    $activitySort++;
                    $stmt = $db->prepare(
                        "INSERT INTO qa_project_activities
                        (project_id, activity_no, activity_name, start_date, end_date, responsible_user_id, status, sort_order)
                        VALUES (?, ?, ?, NULLIF(?,''), NULLIF(?,''), ?, 'planned', ?)"
                    );
                    $stmt->bind_param(
                        'iisssii',
                        $projectId, $activitySort, $activityKey, $startDateDb, $endDateDb,
                        $ownerUserId, $activitySort
                    );
                    if (!$stmt->execute()) throw new Exception('insert activity failed');
                    $activityMap[$activityKey] = $stmt->insert_id;
                    $stmt->close();
                }

                $activityId = (int)$activityMap[$activityKey];
                $sort = $index + 1;
                $stmt = $db->prepare(
                    "INSERT INTO qa_project_budget_items
                    (project_id, activity_id, budget_pool_id, expense_category, item_name,
                     quantity, unit_name, unit_price, requested_amount, approved_amount, sort_order)
                    VALUES (?, ?, ?, NULLIF(?,''), ?, ?, NULLIF(?,''), ?, ?, 0, ?)"
                );
                $stmt->bind_param(
                    'iiissdsddi',
                    $projectId, $activityId, $line['budget_pool_id'], $line['expense_category'],
                    $line['item_name'], $line['quantity'], $line['unit_name'],
                    $line['unit_price'], $line['amount'], $sort
                );
                if (!$stmt->execute()) throw new Exception('insert budget item failed');
                $stmt->close();
            }

            foreach ($kpis as $index => $kpi) {
                $sort = $index + 1;
                $kpiCode = 'KPI-' . $sort;
                $operator = '>=';
                $stmt = $db->prepare(
                    "INSERT INTO qa_project_kpis
                    (project_id, kpi_code, kpi_name, kpi_type, target_value, target_operator,
                     target_unit, measurement_method, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, NULLIF(?,''), NULLIF(?,''), ?)"
                );
                $stmt->bind_param(
                    'isssdsssi',
                    $projectId, $kpiCode, $kpi['name'], $kpi['type'], $kpi['target'],
                    $operator, $kpi['unit'], $kpi['method'], $sort
                );
                if (!$stmt->execute()) throw new Exception('insert KPI failed');
                $stmt->close();
            }

            $comment = $isSubmit ? 'สร้างและส่งโครงการเข้าสู่กระบวนการตรวจสอบ' : 'บันทึกร่างโครงการ';
            $stmt = $db->prepare(
                "INSERT INTO qa_project_status_history
                (project_id, from_status_code, to_status_code, changed_by, comment_text)
                VALUES (?, NULL, ?, ?, ?)"
            );
            $stmt->bind_param('isis', $projectId, $statusCode, $ownerUserId, $comment);
            if (!$stmt->execute()) throw new Exception('insert history failed');
            $stmt->close();

            if ($isSubmit) {
                $approvalStages = array(
                    array(1, 'plan'),
                    array(2, 'budget'),
                    array(3, 'director')
                );
                foreach ($approvalStages as $stage) {
                    $seq = (int)$stage[0];
                    $stageCode = $stage[1];
                    $stmt = $db->prepare(
                        "INSERT INTO qa_project_approvals
                        (project_id, sequence_no, approval_stage, decision_status)
                        VALUES (?, ?, ?, 'pending')"
                    );
                    $stmt->bind_param('iis', $projectId, $seq, $stageCode);
                    if (!$stmt->execute()) throw new Exception('insert approval stage failed');
                    $stmt->close();
                }
            }

            $db->commit();
            $db->autocommit(true);

            project_audit_create($projectId, array(
                'project_code'=>$projectCode,
                'status_code'=>$statusCode,
                'division_id'=>$divisionId,
                'plan_id'=>$planId,
                'requested_budget'=>$requestedBudget
            ));

            $_SESSION['qa_project_flash'] = array(
                'type'=>'success',
                'text'=>$isSubmit
                    ? 'สร้างและส่งโครงการเรียบร้อยแล้ว'
                    : 'บันทึกร่างโครงการเรียบร้อยแล้ว'
            );

            header('Location: ' . qa_url('projects/view.php') . '?id=' . $projectId);
            exit;

        } catch (Exception $e) {
            $db->rollback();
            $db->autocommit(true);
            $formErrors[] = 'ไม่สามารถบันทึกโครงการได้: ' . $e->getMessage();
        }
    }
}

require QA_ROOT . '/includes/header.php';
?>

<?php if (!empty($formErrors)): ?>
<div class="alert alert-danger" style="margin-bottom:16px">
    <strong>กรุณาตรวจสอบข้อมูล</strong>
    <ul style="margin:8px 0 0 18px">
        <?php foreach (array_unique($formErrors) as $error): ?>
            <li><?= h($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="stepper">
    <div class="step current">1 ข้อมูลทั่วไป</div>
    <div class="step">2 หลักการและวัตถุประสงค์</div>
    <div class="step">3 ความสอดคล้อง</div>
    <div class="step">4 กิจกรรมและงบประมาณ</div>
    <div class="step">5 KPI / Output / Outcome</div>
    <div class="step">6 ตรวจสอบและส่งเสนอ</div>
</div>

<div class="grid grid-3" style="margin-bottom:16px">
    <div class="card">
        <div class="metric-label">วงเงินฝ่ายที่ได้รับ</div>
        <div class="metric-value" id="divisionAllocatedCard"><?= h(qa_money($divisionAllocated)) ?></div>
        <div class="metric-note">รวมทุกแหล่งเงินของฝ่ายที่เลือก</div>
    </div>
    <div class="card">
        <div class="metric-label">ถูกกันไว้แล้ว</div>
        <div class="metric-value" id="divisionReservedCard"><?= h(qa_money($divisionReserved)) ?></div>
        <div class="metric-note">โครงการที่พ้นสถานะร่างแล้ว</div>
    </div>
    <div class="card">
        <div class="metric-label">พร้อมสำหรับโครงการใหม่</div>
        <div class="metric-value" id="divisionAvailableCard"><?= h(qa_money($divisionAvailable)) ?></div>
        <div class="metric-note">ใช้ตรวจวงเงินเมื่อกดส่งเสนอ</div>
    </div>
</div>

<?php if (empty($plans)): ?>
<div class="alert alert-danger">
    ยังไม่มีแผนพัฒนาคุณภาพสถานศึกษาที่ใช้งาน กรุณาเพิ่มข้อมูลที่
    <a href="<?= h(qa_url('plans/index.php')) ?>">แผนพัฒนาคุณภาพ</a> ก่อน
</div>
<?php endif; ?>

<form method="post" id="projectForm">
<input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">

<div class="card">
    <div class="section-heading">
        <div><h2>1. ข้อมูลทั่วไป</h2><p>ระบุหน่วยงานรับผิดชอบ แผน และข้อมูลพื้นฐานของโครงการ</p></div>
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label>ปีงบประมาณ</label>
            <input value="<?= $yearBE ? h($yearBE) : '-' ?>" readonly>
        </div>

        <div class="form-group">
            <label for="plan_id">แผนพัฒนาคุณภาพ *</label>
            <select name="plan_id" id="plan_id" required>
                <option value="">-- เลือกแผน --</option>
                <?php foreach ($plans as $plan): ?>
                <option value="<?= h($plan['plan_id']) ?>" <?= (int)$plan['plan_id'] === $selectedPlanId ? 'selected' : '' ?>>
                    <?= h($plan['plan_code'] . ' — ' . $plan['plan_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <div class="help-text">หากเปลี่ยนแผน ระบบจะโหลดพันธกิจ/กลยุทธ์/จุดเน้นของแผนนั้นใหม่</div>
        </div>

        <div class="form-group">
            <label for="division_id">ฝ่ายรับผิดชอบ *</label>
            <select name="division_id" id="division_id" required>
                <?php foreach ($divisions as $division): ?>
                <option value="<?= h($division['division_id']) ?>" <?= (int)$division['division_id'] === $selectedDivisionId ? 'selected' : '' ?>>
                    <?= h($division['division_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>ประเภท *</label>
            <?php $postType = isset($_POST['project_type']) ? $_POST['project_type'] : 'project'; ?>
            <select name="project_type" required>
                <option value="project" <?= $postType === 'project' ? 'selected' : '' ?>>โครงการ</option>
                <option value="work" <?= $postType === 'work' ? 'selected' : '' ?>>งาน</option>
                <option value="activity" <?= $postType === 'activity' ? 'selected' : '' ?>>กิจกรรม</option>
            </select>
        </div>

        <div class="form-group">
            <label>ผู้รับผิดชอบหลัก</label>
            <input value="<?= h($ownerName) ?>" readonly>
        </div>

        <div class="form-group">
            <label>สถานที่ดำเนินการ</label>
            <input name="location_text" maxlength="255"
                   value="<?= isset($_POST['location_text']) ? h($_POST['location_text']) : '' ?>"
                   placeholder="เช่น โรงเรียนโสตศึกษาอนุสารสุนทร">
        </div>

        <div class="form-group full">
            <label>ชื่อโครงการ / งาน / กิจกรรม *</label>
            <input name="project_name" required maxlength="255"
                   value="<?= isset($_POST['project_name']) ? h($_POST['project_name']) : '' ?>"
                   placeholder="ระบุชื่อโครงการ">
        </div>

        <div class="form-group">
            <label>วันที่เริ่ม</label>
            <input type="date" name="start_date" value="<?= isset($_POST['start_date']) ? h($_POST['start_date']) : '' ?>">
        </div>

        <div class="form-group">
            <label>วันที่สิ้นสุด</label>
            <input type="date" name="end_date" value="<?= isset($_POST['end_date']) ? h($_POST['end_date']) : '' ?>">
        </div>
    </div>

    <h3 style="margin-top:18px">กลุ่มเป้าหมาย</h3>
    <div class="form-grid">
        <div class="form-group">
            <label>ประเภทกลุ่มเป้าหมาย</label>
            <select name="beneficiary_type">
                <?php
                $bt = isset($_POST['beneficiary_type']) ? $_POST['beneficiary_type'] : 'นักเรียน';
                foreach (array('นักเรียน','ครู','บุคลากร','ผู้ปกครอง','ชุมชน','อื่น ๆ') as $opt):
                ?>
                <option value="<?= h($opt) ?>" <?= $bt === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>รายละเอียดกลุ่มเป้าหมาย</label>
            <input name="beneficiary_detail"
                   value="<?= isset($_POST['beneficiary_detail']) ? h($_POST['beneficiary_detail']) : '' ?>"
                   placeholder="เช่น นักเรียนชั้น ม.1-6">
        </div>
        <div class="form-group">
            <label>จำนวนเป้าหมาย</label>
            <input type="number" min="0" name="beneficiary_count"
                   value="<?= isset($_POST['beneficiary_count']) ? h($_POST['beneficiary_count']) : '' ?>"
                   placeholder="0">
        </div>
        <div class="form-group">
            <label>หน่วย</label>
            <input name="beneficiary_unit"
                   value="<?= isset($_POST['beneficiary_unit']) ? h($_POST['beneficiary_unit']) : 'คน' ?>">
        </div>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>2. หลักการ เหตุผล และวัตถุประสงค์</h2><p>เชื่อมปัญหา/ความจำเป็นไปสู่สิ่งที่โครงการต้องการเปลี่ยนแปลง</p></div>
    </div>

    <div class="form-grid">
        <div class="form-group full">
            <label>หลักการและเหตุผล *</label>
            <textarea name="principle_reason" placeholder="อธิบายเหตุผล ความจำเป็น บริบท หรือข้อมูลที่สนับสนุนการดำเนินโครงการ"><?= isset($_POST['principle_reason']) ? h($_POST['principle_reason']) : '' ?></textarea>
        </div>
        <div class="form-group full">
            <label>ปัญหา / ความต้องการจำเป็น</label>
            <textarea name="need_problem" placeholder="ระบุปัญหา Gap หรือความต้องการจำเป็นที่โครงการตอบสนอง"><?= isset($_POST['need_problem']) ? h($_POST['need_problem']) : '' ?></textarea>
        </div>
        <div class="form-group full">
            <label>วัตถุประสงค์ *</label>
            <textarea name="objectives_text" placeholder="1. เพื่อ...&#10;2. เพื่อ..."><?= isset($_POST['objectives_text']) ? h($_POST['objectives_text']) : '' ?></textarea>
            <div class="help-text">พิมพ์ 1 วัตถุประสงค์ต่อ 1 บรรทัด</div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>3. ความสอดคล้องกับแผนและการประกันคุณภาพ</h2><p>เลือกได้หลายรายการ ระบบจะเก็บเป็นความสัมพันธ์เพื่อใช้ทำ Dashboard และตอบคำถามผู้ประเมิน</p></div>
    </div>

    <?php if (!empty($schoolValues)): ?>
    <div class="notice">
        <strong>ค่านิยมของสถานศึกษา:</strong>
        <?php foreach ($schoolValues as $index => $value): ?>
            <?= $index > 0 ? ' • ' : '' ?><?= h($value['value_code'] . ' ' . $value['value_name']) ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="alignment-grid">
        <div class="alignment-box">
            <h3>พันธกิจ</h3>
            <?php if (empty($missions)): ?><div class="help-text">ยังไม่มีข้อมูล</div><?php endif; ?>
            <?php foreach ($missions as $mission): ?>
            <label class="check-row">
                <input type="checkbox" name="mission_ids[]" value="<?= h($mission['mission_id']) ?>"
                    <?= isset($_POST['mission_ids']) && in_array((string)$mission['mission_id'], (array)$_POST['mission_ids'], true) ? 'checked' : '' ?>>
                <span><strong><?= h($mission['mission_code']) ?></strong> <?= h($mission['mission_text']) ?></span>
            </label>
            <?php endforeach; ?>
        </div>

        <div class="alignment-box">
            <h3>กลยุทธ์</h3>
            <?php if (empty($strategies)): ?><div class="help-text">ยังไม่มีข้อมูล</div><?php endif; ?>
            <?php foreach ($strategies as $strategy): ?>
            <label class="check-row">
                <input type="checkbox" name="strategy_ids[]" value="<?= h($strategy['strategy_id']) ?>"
                    <?= isset($_POST['strategy_ids']) && in_array((string)$strategy['strategy_id'], (array)$_POST['strategy_ids'], true) ? 'checked' : '' ?>>
                <span><strong><?= h($strategy['strategy_code']) ?></strong> <?= h($strategy['strategy_name']) ?>
                    <?php if ($strategy['mission_code']): ?><small>พันธกิจ <?= h($strategy['mission_code']) ?></small><?php endif; ?>
                </span>
            </label>
            <?php endforeach; ?>
        </div>

        <div class="alignment-box">
            <h3>จุดเน้นโรงเรียน</h3>
            <?php if (empty($focusAreas)): ?><div class="help-text">ยังไม่มีข้อมูล</div><?php endif; ?>
            <?php foreach ($focusAreas as $focus): ?>
            <label class="check-row">
                <input type="checkbox" name="focus_ids[]" value="<?= h($focus['focus_id']) ?>"
                    <?= isset($_POST['focus_ids']) && in_array((string)$focus['focus_id'], (array)$_POST['focus_ids'], true) ? 'checked' : '' ?>>
                <span><strong><?= h($focus['focus_code']) ?></strong> <?= h($focus['focus_name']) ?></span>
            </label>
            <?php endforeach; ?>
        </div>

        <div class="alignment-box">
            <h3>เป้าหมายของแผน</h3>
            <?php if (empty($planTargets)): ?><div class="help-text">ยังไม่มีข้อมูล</div><?php endif; ?>
            <?php foreach ($planTargets as $target): ?>
            <label class="check-row">
                <input type="checkbox" name="target_ids[]" value="<?= h($target['target_id']) ?>"
                    <?= isset($_POST['target_ids']) && in_array((string)$target['target_id'], (array)$_POST['target_ids'], true) ? 'checked' : '' ?>>
                <span><strong><?= h($target['target_code'] ? $target['target_code'] : '-') ?></strong> <?= h($target['target_name']) ?>
                    <?php if ($target['target_value'] !== null): ?><small>เป้าหมาย <?= h(number_format((float)$target['target_value'],2)) ?> <?= h($target['target_unit']) ?></small><?php endif; ?>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="alignment-box" style="margin-top:14px">
        <h3>ตัวชี้วัด สมศ.</h3>
        <div class="indicator-grid">
            <?php foreach ($onesqaIndicators as $indicator): ?>
            <label class="check-row">
                <input type="checkbox" name="indicator_ids[]" value="<?= h($indicator['indicator_id']) ?>"
                    <?= isset($_POST['indicator_ids']) && in_array((string)$indicator['indicator_id'], (array)$_POST['indicator_ids'], true) ? 'checked' : '' ?>>
                <span>
                    <strong><?= h($indicator['indicator_code']) ?></strong>
                    <?= h($indicator['indicator_name']) ?>
                    <small>มาตรฐาน <?= h($indicator['standard_code']) ?> <?= h($indicator['standard_name']) ?></small>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>4. กิจกรรมและงบประมาณ</h2><p>เลือกเฉพาะก้อนงบที่จัดสรรให้ฝ่ายนี้ ระบบตรวจวงเงินอีกครั้งเมื่อกด “ส่งเสนอ”</p></div>
        <button class="btn btn-sm" type="button" id="addBudgetRow">＋ เพิ่มรายการ</button>
    </div>

    <?php if (empty($divisionPools)): ?>
    <div class="alert alert-danger">
        ฝ่ายที่เลือกยังไม่มีวงเงินจัดสรร หรือไม่มีวงเงินพร้อมใช้ กรุณาตรวจสอบหน้า
        <a href="<?= h(qa_url('budget/allocation.php')) ?>">จัดสรรงบ 4 ฝ่าย</a>
    </div>
    <?php else: ?>
    <div class="pool-budget-list">
        <?php foreach ($divisionPools as $pool): ?>
        <div>
            <span><?= h($pool['source_name'] . ' — ' . $pool['pool_name']) ?></span>
            <strong>
                จัดสรร <?= h(qa_money($pool['current_allocation'])) ?> /
                พร้อมใช้ <?= h(qa_money($pool['available_amount'])) ?> บาท
            </strong>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="table-wrap">
        <table id="budgetTable">
            <thead>
            <tr>
                <th>กิจกรรม</th>
                <th>หมวด</th>
                <th>รายการค่าใช้จ่าย</th>
                <th style="width:95px">จำนวน</th>
                <th style="width:95px">หน่วย</th>
                <th style="width:130px">ราคาต่อหน่วย</th>
                <th>แหล่งเงิน</th>
                <th class="text-right">รวม</th>
                <th></th>
            </tr>
            </thead>
            <tbody id="budgetRows">
            <?php
            $postedActivities = isset($_POST['activity_name']) && is_array($_POST['activity_name']) ? $_POST['activity_name'] : array('');
            $rowCount = max(1, count($postedActivities));
            for ($i=0; $i<$rowCount; $i++):
            ?>
            <tr class="budget-row">
                <td><input name="activity_name[]" value="<?= isset($_POST['activity_name'][$i]) ? h($_POST['activity_name'][$i]) : '' ?>" placeholder="กิจกรรมที่ 1"></td>
                <td><input name="expense_category[]" value="<?= isset($_POST['expense_category'][$i]) ? h($_POST['expense_category'][$i]) : '' ?>" placeholder="วัสดุ/อาหาร/..."></td>
                <td><input name="item_name[]" value="<?= isset($_POST['item_name'][$i]) ? h($_POST['item_name'][$i]) : '' ?>" placeholder="รายละเอียดค่าใช้จ่าย"></td>
                <td><input class="budget-qty" type="number" name="quantity[]" min="0.01" step="0.01" value="<?= isset($_POST['quantity'][$i]) ? h($_POST['quantity'][$i]) : '1' ?>"></td>
                <td><input name="unit_name[]" value="<?= isset($_POST['unit_name'][$i]) ? h($_POST['unit_name'][$i]) : 'รายการ' ?>"></td>
                <td><input class="budget-price" type="number" name="unit_price[]" min="0" step="0.01" value="<?= isset($_POST['unit_price'][$i]) ? h($_POST['unit_price'][$i]) : '0.00' ?>"></td>
                <td>
                    <select name="budget_pool_id[]">
                        <option value="">-- เลือก --</option>
                        <?php foreach ($divisionPools as $pool): ?>
                        <option value="<?= h($pool['budget_pool_id']) ?>"
                            <?= isset($_POST['budget_pool_id'][$i]) && (int)$_POST['budget_pool_id'][$i] === (int)$pool['budget_pool_id'] ? 'selected' : '' ?>>
                            <?= h($pool['source_name'] . ' / ' . $pool['pool_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="text-right money-cell budget-line-total">0.00</td>
                <td><button class="btn btn-danger btn-sm remove-budget-row" type="button">ลบ</button></td>
            </tr>
            <?php endfor; ?>
            </tbody>
            <tfoot>
            <tr class="table-total-row">
                <td colspan="7"><strong>งบประมาณที่ขอรวม</strong></td>
                <td class="text-right"><strong id="requestedBudgetTotal">0.00</strong></td>
                <td></td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>5. KPI / Output / Outcome</h2><p>กำหนดตัวชี้วัดความสำเร็จและผลที่คาดว่าจะเกิดขึ้นตั้งแต่ก่อนดำเนินงาน</p></div>
        <button class="btn btn-sm" type="button" id="addKpiRow">＋ เพิ่ม KPI</button>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>KPI</th><th style="width:150px">ประเภท</th><th style="width:130px">ค่าเป้าหมาย</th><th style="width:110px">หน่วย</th><th>วิธีวัด</th><th></th></tr>
            </thead>
            <tbody id="kpiRows">
            <?php
            $postedKpis = isset($_POST['kpi_name']) && is_array($_POST['kpi_name']) ? $_POST['kpi_name'] : array('');
            $kpiCount = max(1, count($postedKpis));
            for ($i=0; $i<$kpiCount; $i++):
                $kpiTypePost = isset($_POST['kpi_type'][$i]) ? $_POST['kpi_type'][$i] : 'quantitative';
            ?>
            <tr class="kpi-row">
                <td><input name="kpi_name[]" value="<?= isset($_POST['kpi_name'][$i]) ? h($_POST['kpi_name'][$i]) : '' ?>" placeholder="เช่น ผู้เข้าร่วมไม่น้อยกว่าร้อยละ 90"></td>
                <td>
                    <select name="kpi_type[]">
                        <option value="quantitative" <?= $kpiTypePost === 'quantitative' ? 'selected' : '' ?>>เชิงปริมาณ</option>
                        <option value="qualitative" <?= $kpiTypePost === 'qualitative' ? 'selected' : '' ?>>เชิงคุณภาพ</option>
                    </select>
                </td>
                <td><input type="number" step="0.01" name="kpi_target[]" value="<?= isset($_POST['kpi_target'][$i]) ? h($_POST['kpi_target'][$i]) : '' ?>"></td>
                <td><input name="kpi_unit[]" value="<?= isset($_POST['kpi_unit'][$i]) ? h($_POST['kpi_unit'][$i]) : '%' ?>"></td>
                <td><input name="kpi_method[]" value="<?= isset($_POST['kpi_method'][$i]) ? h($_POST['kpi_method'][$i]) : '' ?>" placeholder="แบบประเมิน/แบบทดสอบ/..."></td>
                <td><button class="btn btn-danger btn-sm remove-kpi-row" type="button">ลบ</button></td>
            </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <div class="form-grid" style="margin-top:16px">
        <div class="form-group full">
            <label>Output ที่คาดว่าจะได้รับ *</label>
            <textarea name="expected_output" placeholder="ผลผลิตโดยตรง เช่น นักเรียน 50 คนได้รับการอบรม..."><?= isset($_POST['expected_output']) ? h($_POST['expected_output']) : '' ?></textarea>
        </div>
        <div class="form-group full">
            <label>Outcome ที่คาดว่าจะเกิดขึ้น *</label>
            <textarea name="expected_outcome" placeholder="ผลลัพธ์หรือการเปลี่ยนแปลง เช่น ผู้เรียนสามารถ..."><?= isset($_POST['expected_outcome']) ? h($_POST['expected_outcome']) : '' ?></textarea>
        </div>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>6. ตรวจสอบและส่งเสนอ</h2><p>“บันทึกร่าง” เก็บข้อมูลไว้ก่อน ส่วน “ส่งเสนอ” จะตรวจความครบถ้วนและวงเงินอย่างเข้มงวด</p></div>
    </div>

    <div class="project-submit-summary">
        <div><span>งบประมาณที่ขอ</span><strong id="submitBudgetSummary">0.00 บาท</strong></div>
        <div><span>ตัวชี้วัด สมศ. ที่เลือก</span><strong id="indicatorCountSummary">0 ตัว</strong></div>
        <div><span>สถานะเมื่อบันทึก</span><strong>ร่าง หรือ ส่งเสนอแล้ว</strong></div>
    </div>

    <div class="action-bar" style="margin-top:16px;margin-bottom:0">
        <button class="btn" type="submit" name="action" value="save_draft">บันทึกร่าง</button>
        <button class="btn btn-primary" type="submit" name="action" value="submit_project"
                onclick="return confirm('ยืนยันส่งโครงการเข้าสู่กระบวนการตรวจสอบ?');">
            ส่งเสนอ
        </button>
    </div>
</div>

</form>

<template id="budgetRowTemplate">
<tr class="budget-row">
    <td><input name="activity_name[]" placeholder="กิจกรรม"></td>
    <td><input name="expense_category[]" placeholder="หมวดค่าใช้จ่าย"></td>
    <td><input name="item_name[]" placeholder="รายการค่าใช้จ่าย"></td>
    <td><input class="budget-qty" type="number" name="quantity[]" min="0.01" step="0.01" value="1"></td>
    <td><input name="unit_name[]" value="รายการ"></td>
    <td><input class="budget-price" type="number" name="unit_price[]" min="0" step="0.01" value="0.00"></td>
    <td>
        <select name="budget_pool_id[]">
            <option value="">-- เลือก --</option>
            <?php foreach ($divisionPools as $pool): ?>
            <option value="<?= h($pool['budget_pool_id']) ?>"><?= h($pool['source_name'] . ' / ' . $pool['pool_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </td>
    <td class="text-right money-cell budget-line-total">0.00</td>
    <td><button class="btn btn-danger btn-sm remove-budget-row" type="button">ลบ</button></td>
</tr>
</template>

<template id="kpiRowTemplate">
<tr class="kpi-row">
    <td><input name="kpi_name[]" placeholder="ระบุ KPI"></td>
    <td><select name="kpi_type[]"><option value="quantitative">เชิงปริมาณ</option><option value="qualitative">เชิงคุณภาพ</option></select></td>
    <td><input type="number" step="0.01" name="kpi_target[]"></td>
    <td><input name="kpi_unit[]" value="%"></td>
    <td><input name="kpi_method[]" placeholder="วิธีวัด"></td>
    <td><button class="btn btn-danger btn-sm remove-kpi-row" type="button">ลบ</button></td>
</tr>
</template>

<script>
(function () {
    var planSelect = document.getElementById('plan_id');
    var divisionSelect = document.getElementById('division_id');

    function reloadMaster() {
        var plan = planSelect ? planSelect.value : '';
        var division = divisionSelect ? divisionSelect.value : '';
        var url = '<?= h(qa_url('projects/create.php')) ?>?plan=' + encodeURIComponent(plan) + '&division=' + encodeURIComponent(division);
        window.location.href = url;
    }

    if (planSelect) planSelect.addEventListener('change', reloadMaster);
    if (divisionSelect) divisionSelect.addEventListener('change', reloadMaster);

    var budgetRows = document.getElementById('budgetRows');
    var budgetTemplate = document.getElementById('budgetRowTemplate');
    var addBudgetRow = document.getElementById('addBudgetRow');
    var totalEl = document.getElementById('requestedBudgetTotal');
    var submitTotalEl = document.getElementById('submitBudgetSummary');

    function money(v) {
        return Number(v || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    }

    function recalcBudget() {
        var total = 0;
        var rows = budgetRows ? budgetRows.querySelectorAll('.budget-row') : [];
        for (var i=0; i<rows.length; i++) {
            var qtyEl = rows[i].querySelector('.budget-qty');
            var priceEl = rows[i].querySelector('.budget-price');
            var lineEl = rows[i].querySelector('.budget-line-total');
            var qty = parseFloat(qtyEl ? qtyEl.value : '0');
            var price = parseFloat(priceEl ? priceEl.value : '0');
            if (!isFinite(qty) || qty < 0) qty = 0;
            if (!isFinite(price) || price < 0) price = 0;
            var line = qty * price;
            total += line;
            if (lineEl) lineEl.innerHTML = money(line);
        }
        if (totalEl) totalEl.innerHTML = money(total);
        if (submitTotalEl) submitTotalEl.innerHTML = money(total) + ' บาท';
    }

    function bindBudgetRow(row) {
        var inputs = row.querySelectorAll('.budget-qty, .budget-price');
        for (var i=0; i<inputs.length; i++) inputs[i].addEventListener('input', recalcBudget);
        var remove = row.querySelector('.remove-budget-row');
        if (remove) remove.addEventListener('click', function () {
            var rows = budgetRows.querySelectorAll('.budget-row');
            if (rows.length > 1) row.parentNode.removeChild(row);
            else {
                var controls = row.querySelectorAll('input');
                for (var j=0; j<controls.length; j++) {
                    if (controls[j].className.indexOf('budget-qty') >= 0) controls[j].value = '1';
                    else if (controls[j].className.indexOf('budget-price') >= 0) controls[j].value = '0.00';
                    else controls[j].value = '';
                }
                var selects = row.querySelectorAll('select');
                for (var k=0; k<selects.length; k++) selects[k].selectedIndex = 0;
            }
            recalcBudget();
        });
    }

    if (budgetRows) {
        var initialBudgetRows = budgetRows.querySelectorAll('.budget-row');
        for (var i=0; i<initialBudgetRows.length; i++) bindBudgetRow(initialBudgetRows[i]);
    }
    if (addBudgetRow && budgetTemplate) {
        addBudgetRow.addEventListener('click', function () {
            var fragment = document.importNode(budgetTemplate.content, true);
            var row = fragment.querySelector('.budget-row');
            budgetRows.appendChild(fragment);
            bindBudgetRow(row);
            recalcBudget();
        });
    }

    var kpiRows = document.getElementById('kpiRows');
    var kpiTemplate = document.getElementById('kpiRowTemplate');
    var addKpiRow = document.getElementById('addKpiRow');

    function bindKpiRow(row) {
        var remove = row.querySelector('.remove-kpi-row');
        if (remove) remove.addEventListener('click', function () {
            var rows = kpiRows.querySelectorAll('.kpi-row');
            if (rows.length > 1) row.parentNode.removeChild(row);
            else {
                var inputs = row.querySelectorAll('input');
                for (var i=0; i<inputs.length; i++) inputs[i].value = '';
                var unit = row.querySelector('input[name="kpi_unit[]"]');
                if (unit) unit.value = '%';
            }
        });
    }

    if (kpiRows) {
        var initialKpis = kpiRows.querySelectorAll('.kpi-row');
        for (var j=0; j<initialKpis.length; j++) bindKpiRow(initialKpis[j]);
    }
    if (addKpiRow && kpiTemplate) {
        addKpiRow.addEventListener('click', function () {
            var fragment = document.importNode(kpiTemplate.content, true);
            var row = fragment.querySelector('.kpi-row');
            kpiRows.appendChild(fragment);
            bindKpiRow(row);
        });
    }

    var indicatorSummary = document.getElementById('indicatorCountSummary');
    var indicatorChecks = document.querySelectorAll('input[name="indicator_ids[]"]');
    function updateIndicatorCount() {
        var count = 0;
        for (var i=0; i<indicatorChecks.length; i++) if (indicatorChecks[i].checked) count++;
        if (indicatorSummary) indicatorSummary.innerHTML = count + ' ตัว';
    }
    for (var q=0; q<indicatorChecks.length; q++) indicatorChecks[q].addEventListener('change', updateIndicatorCount);

    recalcBudget();
    updateIndicatorCount();
})();
</script>

<?php require QA_ROOT . '/includes/footer.php'; ?>
