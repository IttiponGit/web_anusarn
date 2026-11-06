<?php
$pageTitle = 'Dashboard';
$pageHeading = 'Dashboard ภาพรวมระบบประกันคุณภาพ';
$pageDescription = 'ภาพรวมงบประมาณ โครงการ ผลการดำเนินงาน และความครอบคลุมด้านคุณภาพของปีงบประมาณที่ใช้งาน';
$activeMenu = 'dashboard';
require_once __DIR__ . '/includes/bootstrap.php';
qa_require_login();
qa_require_permission('dashboard.view');

$db = qa_db();
$currentYear = qa_current_year_row();
$yearId = $currentYear ? (int)$currentYear['year_id'] : 0;
$yearBE = $currentYear ? (int)$currentYear['fiscal_year_be'] : 0;

$user = qa_current_user();
$userId = $user ? (int)$user['user_id'] : 0;
$projectScope = qa_project_scope_condition('p');

$dashboardViewCode = 'viewer';
$dashboardViewLabel = 'มุมมองข้อมูล';
$dashboardViewNote = 'ข้อมูลตามสิทธิ์ที่ได้รับ';

if (qa_user_can('dashboard.executive')) {
    $dashboardViewCode = 'executive';
    $dashboardViewLabel = 'มุมมองผู้บริหาร';
    $dashboardViewNote = 'ภาพรวมทั้งสถานศึกษา';
} elseif (qa_user_can('project.approve.plan')) {
    $dashboardViewCode = 'plan';
    $dashboardViewLabel = 'มุมมองงานแผน';
    $dashboardViewNote = 'เน้นโครงการรอตรวจ แผน และตัวชี้วัด';
} elseif (qa_user_can('project.approve.budget')) {
    $dashboardViewCode = 'budget';
    $dashboardViewLabel = 'มุมมองงานงบประมาณ';
    $dashboardViewNote = 'เน้นวงเงินและโครงการรอตรวจงบ';
} elseif (qa_user_can('monitoring.finance')) {
    $dashboardViewCode = 'finance';
    $dashboardViewLabel = 'มุมมองการเงิน';
    $dashboardViewNote = 'เน้นการเบิกจ่ายและงบคงเหลือ';
} elseif (qa_user_can('project.view_division')) {
    $dashboardViewCode = 'division';
    $dashboardViewLabel = 'มุมมองหัวหน้าฝ่าย';
    $dashboardViewNote = 'แสดงข้อมูลเฉพาะฝ่ายหลักที่รับผิดชอบ';
} elseif (qa_user_can('project.view_own')) {
    $dashboardViewCode = 'personal';
    $dashboardViewLabel = 'มุมมองงานของฉัน';
    $dashboardViewNote = 'แสดงโครงการและกิจกรรมที่เกี่ยวข้องกับบัญชีนี้';
}

$showGlobalBudget = qa_user_can_any(array(
    'dashboard.executive',
    'budget.view',
    'budget.allocation.view'
));
$pageHeading = 'Dashboard ภาพรวมระบบประกันคุณภาพ';
$pageDescription = $dashboardViewNote . ' • ปีงบประมาณ ' . ($yearBE ? $yearBE : '-');

function qa_dash_pct($value, $base)
{
    $value = (float)$value;
    $base = (float)$base;
    if ($base <= 0) return 0;
    $pct = ($value / $base) * 100;
    if ($pct < 0) $pct = 0;
    if ($pct > 100) $pct = 100;
    return $pct;
}

function qa_dash_donut_gradient($rows, $total)
{
    if ($total <= 0 || empty($rows)) {
        return 'background:#e8eff7;';
    }

    $palette = array(
        '#2b67d9','#16a085','#f59e0b','#8b5cf6','#ef6c57',
        '#0ea5a4','#64748b','#ec4899','#84a63d','#d97706','#475569'
    );

    $parts = array();
    $start = 0.0;
    $i = 0;
    foreach ($rows as $row) {
        $count = (int)$row['total'];
        if ($count <= 0) continue;
        $end = $start + (($count / $total) * 100);
        $color = $palette[$i % count($palette)];
        $parts[] = $color . ' ' . number_format($start, 2, '.', '') . '% ' . number_format($end, 2, '.', '') . '%';
        $start = $end;
        $i++;
    }
    if (empty($parts)) return 'background:#e8eff7;';
    return 'background:conic-gradient(' . implode(',', $parts) . ');';
}

$totalBudget = $yearId > 0 ? (float)qa_db_scalar(
    "SELECT COALESCE(SUM(initial_amount),0) FROM qa_budget_pools WHERE year_id=".$yearId, 0
) : 0;

$allocated = $yearId > 0 ? (float)qa_db_scalar(
    "SELECT COALESCE(SUM(current_allocation),0) FROM vw_qa_budget_allocation_summary WHERE year_id=".$yearId, 0
) : 0;

$approvedProjects = $yearId > 0 ? (float)qa_db_scalar(
    "SELECT COALESCE(SUM(p.approved_budget),0)
     FROM qa_projects p
     WHERE p.year_id=".$yearId."
       AND p.status_code IN ('APPROVED','IN_PROGRESS','WAITING_REPORT','COMPLETED','CLOSED')
       AND (".$projectScope.")", 0
) : 0;

$actualExpense = $yearId > 0 ? (float)qa_db_scalar(
    "SELECT COALESCE(SUM(e.amount),0)
     FROM qa_expenditures e
     INNER JOIN qa_projects p ON p.project_id=e.project_id
     WHERE p.year_id=".$yearId."
       AND e.payment_status='paid'
       AND (".$projectScope.")", 0
) : 0;

$balance = $totalBudget - $actualExpense;
$unallocated = $totalBudget - $allocated;
if ($unallocated < 0) $unallocated = 0;
$allocatedNotApproved = $allocated - $approvedProjects;
if ($allocatedNotApproved < 0) $allocatedNotApproved = 0;

$totalProjects = $yearId > 0 ? (int)qa_db_scalar(
    "SELECT COUNT(*) FROM qa_projects p WHERE p.year_id=".$yearId." AND (".$projectScope.")", 0
) : 0;

$totalEvidence = $yearId > 0 ? (int)qa_db_scalar(
    "SELECT COUNT(*)
     FROM qa_evidences ev
     INNER JOIN qa_projects p ON p.project_id=ev.project_id
     WHERE p.year_id=".$yearId." AND (".$projectScope.")", 0
) : 0;

$coveredIndicators = $yearId > 0 ? (int)qa_db_scalar(
    "SELECT COUNT(DISTINCT pil.indicator_id)
     FROM qa_project_indicator_links pil
     INNER JOIN qa_projects p ON p.project_id=pil.project_id
     INNER JOIN qa_indicators i ON i.indicator_id=pil.indicator_id
     INNER JOIN qa_standards s ON s.standard_id=i.standard_id
     INNER JOIN qa_quality_frameworks f ON f.framework_id=s.framework_id
     WHERE f.framework_code='ONESQA'
       AND p.year_id=".$yearId."
       AND p.status_code NOT IN ('DRAFT','REVISION','REJECTED','CANCELLED')
       AND (".$projectScope.")", 0
) : 0;

$totalIndicators = (int)qa_db_scalar(
    "SELECT COUNT(*)
     FROM qa_indicators i
     INNER JOIN qa_standards s ON s.standard_id=i.standard_id
     INNER JOIN qa_quality_frameworks f ON f.framework_id=s.framework_id
     WHERE f.framework_code='ONESQA' AND i.is_active=1 AND s.is_active=1", 0
);

$evidenceCoveredIndicators = $yearId > 0 ? (int)qa_db_scalar(
    "SELECT COUNT(DISTINCT eil.indicator_id)
     FROM qa_evidence_indicator_links eil
     INNER JOIN qa_evidences ev ON ev.evidence_id=eil.evidence_id
     INNER JOIN qa_projects p ON p.project_id=ev.project_id
     INNER JOIN qa_indicators i ON i.indicator_id=eil.indicator_id
     INNER JOIN qa_standards s ON s.standard_id=i.standard_id
     INNER JOIN qa_quality_frameworks f ON f.framework_id=s.framework_id
     WHERE f.framework_code='ONESQA' AND p.year_id=".$yearId."
       AND (".$projectScope.")", 0
) : 0;

/* Division chart: allocated vs approved projects vs actual paid */
$divisionRows = array();
if ($yearId > 0 && $showGlobalBudget) {
    $sql = "SELECT
                d.division_id,d.division_code,d.division_name,d.sort_order,
                COALESCE(a.allocated_amount,0) AS allocated_amount,
                COALESCE(pr.approved_amount,0) AS approved_amount,
                COALESCE(ex.actual_amount,0) AS actual_amount
            FROM qa_divisions d
            LEFT JOIN (
                SELECT division_id,SUM(current_allocation) AS allocated_amount
                FROM vw_qa_budget_allocation_summary
                WHERE year_id=".$yearId." AND allocation_type='division'
                GROUP BY division_id
            ) a ON a.division_id=d.division_id
            LEFT JOIN (
                SELECT division_id,SUM(approved_budget) AS approved_amount
                FROM qa_projects
                WHERE year_id=".$yearId."
                  AND status_code IN ('APPROVED','IN_PROGRESS','WAITING_REPORT','COMPLETED','CLOSED')
                GROUP BY division_id
            ) pr ON pr.division_id=d.division_id
            LEFT JOIN (
                SELECT p.division_id,SUM(e.amount) AS actual_amount
                FROM qa_expenditures e
                INNER JOIN qa_projects p ON p.project_id=e.project_id
                WHERE p.year_id=".$yearId." AND e.payment_status='paid'
                GROUP BY p.division_id
            ) ex ON ex.division_id=d.division_id
            WHERE d.is_active=1
            ORDER BY d.sort_order,d.division_id";
    $result=$db->query($sql);
    if($result){
        while($row=$result->fetch_assoc())$divisionRows[]=$row;
        $result->free();
    }
}

$centralAllocated = $yearId > 0 ? (float)qa_db_scalar(
    "SELECT COALESCE(SUM(current_allocation),0)
     FROM vw_qa_budget_allocation_summary
     WHERE year_id=".$yearId." AND allocation_type='central'", 0
) : 0;

/* Project status donut */
$statusRows = array();
if ($yearId > 0) {
    $sql = "SELECT p.status_code,COALESCE(ps.status_name,p.status_code) AS status_name,COUNT(*) AS total
            FROM qa_projects p
            LEFT JOIN qa_project_statuses ps ON ps.status_code=p.status_code
            WHERE p.year_id=".$yearId."
              AND (".$projectScope.")
            GROUP BY p.status_code,ps.status_name
            ORDER BY total DESC,p.status_code";
    $result=$db->query($sql);
    if($result){
        while($row=$result->fetch_assoc())$statusRows[]=$row;
        $result->free();
    }
}
$projectGradient = qa_dash_donut_gradient($statusRows,$totalProjects);

/* Standard coverage chart */
$standardRows = array();
$sql = "SELECT s.standard_id,s.standard_code,s.standard_name,
               COUNT(DISTINCT i.indicator_id) AS indicator_count";
if ($yearId > 0) {
    $sql .= ",
        COUNT(DISTINCT CASE WHEN pc.indicator_id IS NOT NULL THEN i.indicator_id END) AS project_covered,
        COUNT(DISTINCT CASE WHEN ec.indicator_id IS NOT NULL THEN i.indicator_id END) AS evidence_covered";
} else {
    $sql .= ",0 AS project_covered,0 AS evidence_covered";
}
$sql .= " FROM qa_standards s
          INNER JOIN qa_quality_frameworks f ON f.framework_id=s.framework_id
          LEFT JOIN qa_indicators i ON i.standard_id=s.standard_id AND i.is_active=1";
if ($yearId > 0) {
    $sql .= " LEFT JOIN (
                SELECT DISTINCT pil.indicator_id
                FROM qa_project_indicator_links pil
                INNER JOIN qa_projects p ON p.project_id=pil.project_id
                WHERE p.year_id=".$yearId."
                  AND p.status_code NOT IN ('DRAFT','REVISION','REJECTED','CANCELLED')
                  AND (".$projectScope.")
              ) pc ON pc.indicator_id=i.indicator_id
              LEFT JOIN (
                SELECT DISTINCT eil.indicator_id
                FROM qa_evidence_indicator_links eil
                INNER JOIN qa_evidences ev ON ev.evidence_id=eil.evidence_id
                INNER JOIN qa_projects p ON p.project_id=ev.project_id
                WHERE p.year_id=".$yearId."
                  AND (".$projectScope.")
              ) ec ON ec.indicator_id=i.indicator_id";
}
$sql .= " WHERE f.framework_code='ONESQA' AND s.is_active=1
          GROUP BY s.standard_id,s.standard_code,s.standard_name,s.sort_order
          ORDER BY s.sort_order,s.standard_id";
$result=$db->query($sql);
if($result){
    while($row=$result->fetch_assoc())$standardRows[]=$row;
    $result->free();
}


/* งานที่ต้องดำเนินการ — แสดงตาม Permission ของผู้ใช้ */
$actionItems = array();

function qa_dash_approval_queue_count($db,$yearId,$stage)
{
    $changeBlock='';
    if(function_exists('qa_project_change_table_ready') && qa_project_change_table_ready()){
        $changeBlock=" AND NOT EXISTS (
            SELECT 1 FROM qa_project_change_requests cr
            WHERE cr.project_id=p.project_id AND cr.request_status='pending'
        ) ";
    }

    $stmt=$db->prepare(
        "SELECT COUNT(*)
         FROM qa_project_approvals a
         INNER JOIN qa_projects p ON p.project_id=a.project_id
         WHERE p.year_id=?
           AND a.approval_stage=?
           AND a.decision_status='pending'
           AND p.status_code NOT IN ('DRAFT','REVISION','REJECTED','CANCELLED','APPROVED','COMPLETED','CLOSED')
           ".$changeBlock."
           AND NOT EXISTS (
               SELECT 1 FROM qa_project_approvals prev
               WHERE prev.project_id=a.project_id
                 AND prev.sequence_no<a.sequence_no
                 AND prev.decision_status<>'approved'
           )"
    );
    if(!$stmt)return 0;
    $stmt->bind_param('is',$yearId,$stage);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int)$count;
}

if($yearId>0){
    if(qa_user_can('project.approve.plan')){
        $count=qa_dash_approval_queue_count($db,$yearId,'plan');
        $actionItems[]=array('label'=>'รอตรวจโดยงานแผน','count'=>$count,'url'=>'approvals/index.php','tone'=>'plan','note'=>'โครงการที่ถึงขั้นงานแผน');
    }
    if(qa_user_can('project.approve.budget')){
        $count=qa_dash_approval_queue_count($db,$yearId,'budget');
        $actionItems[]=array('label'=>'รอตรวจงบประมาณ','count'=>$count,'url'=>'approvals/index.php','tone'=>'budget','note'=>'โครงการที่ผ่านงานแผนแล้ว');
    }
    if(qa_user_can('project.approve.final')){
        $count=qa_dash_approval_queue_count($db,$yearId,'director');
        $actionItems[]=array('label'=>'รออนุมัติขั้นสุดท้าย','count'=>$count,'url'=>'approvals/index.php','tone'=>'director','note'=>'พร้อมให้ผู้อำนวยการพิจารณา');
    }
    if(qa_user_can('project.change.review') && qa_project_change_table_ready()){
        $count=(int)qa_db_scalar(
            "SELECT COUNT(*)
             FROM qa_project_change_requests cr
             INNER JOIN qa_projects p ON p.project_id=cr.project_id
             WHERE p.year_id=".$yearId." AND cr.request_status='pending'",0
        );
        $actionItems[]=array('label'=>'คำขอแก้ไข/ยกเลิก','count'=>$count,'url'=>'projects/changes.php','tone'=>'change','note'=>'รอพิจารณาคำขอ');
    }
    if(qa_user_can('project.edit_own')){
        $count=(int)qa_db_scalar(
            "SELECT COUNT(*) FROM qa_projects p
             WHERE p.year_id=".$yearId."
               AND p.owner_user_id=".$userId."
               AND p.status_code IN ('DRAFT','REVISION')",0
        );
        $actionItems[]=array('label'=>'โครงการที่ต้องแก้ไข/ส่ง','count'=>$count,'url'=>'projects/index.php','tone'=>'own','note'=>'ร่างหรือถูกส่งกลับแก้ไข');
    }
    if(qa_user_can('activity.report.context')){
        $count=(int)qa_db_scalar(
            "SELECT COUNT(*)
             FROM qa_project_activities a
             INNER JOIN qa_projects p ON p.project_id=a.project_id
             WHERE p.year_id=".$yearId."
               AND a.responsible_user_id=".$userId."
               AND a.status IN ('planned','in_progress')
               AND p.status_code IN ('APPROVED','IN_PROGRESS','WAITING_REPORT')",0
        );
        $actionItems[]=array('label'=>'กิจกรรมที่ต้องรายงาน','count'=>$count,'url'=>'activities/index.php','tone'=>'activity','note'=>'กิจกรรมที่ได้รับมอบหมาย');
    }
    if(qa_user_can('monitoring.manage.context')){
        $count=(int)qa_db_scalar(
            "SELECT COUNT(*) FROM qa_projects p
             WHERE p.year_id=".$yearId."
               AND p.status_code='WAITING_REPORT'
               AND (".$projectScope.")",0
        );
        $actionItems[]=array('label'=>'โครงการรอสรุปผล','count'=>$count,'url'=>'monitoring/index.php?status=WAITING_REPORT','tone'=>'report','note'=>'พร้อมจัดทำรายงานผล');
    }
    if(qa_user_can_any(array('monitoring.view_all','monitoring.view_division','monitoring.view_own'))){
        $count=(int)qa_db_scalar(
            "SELECT COUNT(*) FROM qa_projects p
             WHERE p.year_id=".$yearId."
               AND p.end_date IS NOT NULL
               AND p.end_date<CURDATE()
               AND p.status_code IN ('APPROVED','IN_PROGRESS','WAITING_REPORT')
               AND (".$projectScope.")",0
        );
        $actionItems[]=array('label'=>'โครงการเลยกำหนด','count'=>$count,'url'=>'monitoring/index.php','tone'=>'overdue','note'=>'ควรตรวจสอบสถานะหรือสรุปผล');
    }
}

$pendingActionItems=array();
$totalPendingActions=0;
foreach($actionItems as $actionItem){
    if((int)$actionItem['count']>0){
        $pendingActionItems[]=$actionItem;
        $totalPendingActions+=(int)$actionItem['count'];
    }
}

$budgetPct = qa_dash_pct($actualExpense,$totalBudget);
$allocationPct = qa_dash_pct($allocated,$totalBudget);
$approvedPct = qa_dash_pct($approvedProjects,$totalBudget);
$indicatorPct = qa_dash_pct($coveredIndicators,$totalIndicators);
$evidencePct = qa_dash_pct($evidenceCoveredIndicators,$totalIndicators);

require QA_ROOT . '/includes/header.php';
?>

<div class="action-bar dashboard-actions dashboard-actions-compact">
    <?php if(qa_user_can('project.create')): ?>
        <a class="btn btn-primary" href="<?= h(qa_url('projects/create.php')) ?>">＋ เสนอโครงการใหม่</a>
    <?php endif; ?>
    <?php if(qa_user_can_any(array('project.approve.plan','project.approve.budget','project.approve.final'))): ?>
        <a class="btn" href="<?= h(qa_url('approvals/index.php')) ?>">ตรวจสอบ / อนุมัติ</a>
    <?php endif; ?>
    <?php if(qa_user_can_any(array('activity.view_all','activity.view_division','activity.view_own'))): ?>
        <a class="btn" href="<?= h(qa_url('activities/index.php')) ?>">กิจกรรมที่รับผิดชอบ</a>
    <?php endif; ?>
    <?php if(qa_user_can_any(array('monitoring.view_all','monitoring.view_division','monitoring.view_own'))): ?>
        <a class="btn" href="<?= h(qa_url('monitoring/index.php')) ?>">ติดตามโครงการ</a>
    <?php endif; ?>
</div>

<section class="card dashboard-action-card dashboard-action-card-compact" style="margin-top:14px">
    <div class="dashboard-action-heading">
        <div>
            <h2>งานที่ต้องดำเนินการ</h2>
            <p>แสดงเฉพาะรายการที่มีงานค้างตามสิทธิ์ของบัญชีนี้</p>
        </div>
        <?php if($totalPendingActions>0): ?><span class="badge badge-blue"><?= h($totalPendingActions) ?> งาน</span><?php endif; ?>
    </div>
    <?php if(empty($pendingActionItems)): ?>
        <div class="dashboard-all-clear dashboard-all-clear-compact">✓ ไม่มีงานค้างที่ต้องดำเนินการ</div>
    <?php else: ?>
        <div class="dashboard-action-grid dashboard-action-grid-compact">
            <?php foreach($pendingActionItems as $item): ?>
            <a class="dashboard-action-item dashboard-action-item-compact action-<?= h($item['tone']) ?>" href="<?= h(qa_url($item['url'])) ?>">
                <span class="dashboard-action-count"><?= h($item['count']) ?></span>
                <span class="dashboard-action-text"><strong><?= h($item['label']) ?></strong><small><?= h($item['note']) ?></small></span>
                <span class="dashboard-action-arrow">›</span>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if($showGlobalBudget): ?>
<section class="grid grid-4 dashboard-metrics dashboard-metrics-compact">
    <div class="card metric-card metric-card-budget"><div class="metric-label">งบประมาณประจำปี</div><div class="metric-value"><?= h(qa_money($totalBudget)) ?></div><div class="metric-note">จัดสรรแล้ว <?= h(qa_money($allocated)) ?> (<?= h(number_format($allocationPct,1)) ?>%)</div></div>
    <div class="card metric-card"><div class="metric-label">อนุมัติโครงการแล้ว</div><div class="metric-value"><?= h(qa_money($approvedProjects)) ?></div><div class="metric-note">เฉพาะโครงการที่อนุมัติขั้นสุดท้าย</div></div>
    <div class="card metric-card"><div class="metric-label">เบิกจ่ายจริง</div><div class="metric-value"><?= h(qa_money($actualExpense)) ?></div><div class="metric-note"><?= h(number_format($budgetPct,1)) ?>% ของงบประมาณประจำปี</div></div>
    <div class="card metric-card metric-card-balance"><div class="metric-label">คงเหลือหลังเบิกจ่าย</div><div class="metric-value"><?= h(qa_money($balance)) ?></div><div class="metric-note">งบทั้งหมด − ยอดเบิกจ่ายจริง</div></div>
</section>
<?php else: ?>
<section class="grid grid-4 dashboard-metrics dashboard-metrics-compact">
    <div class="card metric-card"><div class="metric-label">โครงการในขอบเขตของคุณ</div><div class="metric-value"><?= h($totalProjects) ?></div><div class="metric-note"><?= h($dashboardViewNote) ?></div></div>
    <div class="card metric-card"><div class="metric-label">งบโครงการที่อนุมัติ</div><div class="metric-value"><?= h(qa_money($approvedProjects)) ?></div><div class="metric-note">รวมเฉพาะโครงการที่มีสิทธิ์เห็น</div></div>
    <div class="card metric-card"><div class="metric-label">เบิกจ่ายจริง</div><div class="metric-value"><?= h(qa_money($actualExpense)) ?></div><div class="metric-note">จากโครงการในขอบเขตของคุณ</div></div>
    <div class="card metric-card metric-card-balance"><div class="metric-label">หลักฐาน</div><div class="metric-value"><?= h($totalEvidence) ?></div><div class="metric-note">หลักฐานของโครงการที่เกี่ยวข้อง</div></div>
</section>
<?php endif; ?>

<section class="grid grid-2 dashboard-chart-grid" style="margin-top:16px">
    <?php if($showGlobalBudget): ?>
    <div class="card dashboard-chart-card">
        <div class="section-heading">
            <div>
                <h2>ภาพรวมการใช้งบประมาณ</h2>
                <p>แยกความหมาย “จัดสรร”, “อนุมัติโครงการ” และ “เบิกจ่ายจริง” ให้ชัดเจน</p>
            </div>
            <a class="btn btn-sm" href="<?= h(qa_url('budget/index.php')) ?>">ดูงบประมาณ</a>
        </div>

        <?php if($totalBudget<=0): ?>
            <div class="placeholder-chart">ยังไม่มีงบประมาณประจำปี</div>
        <?php else: ?>
            <div class="budget-flow-chart">
                <div class="chart-bar-row">
                    <div class="chart-bar-label"><span>งบประมาณประจำปีทั้งหมด</span><strong><?= h(qa_money($totalBudget)) ?></strong></div>
                    <div class="chart-bar-track"><span class="chart-bar budget-total" style="width:100%"></span></div>
                </div>
                <div class="chart-bar-row">
                    <div class="chart-bar-label"><span>จัดสรรให้ฝ่าย/ส่วนกลางแล้ว</span><strong><?= h(qa_money($allocated)) ?></strong></div>
                    <div class="chart-bar-track"><span class="chart-bar budget-allocated" style="width:<?= h(number_format($allocationPct,2,'.','')) ?>%"></span></div>
                </div>
                <div class="chart-bar-row">
                    <div class="chart-bar-label"><span>อนุมัติให้โครงการแล้ว</span><strong><?= h(qa_money($approvedProjects)) ?></strong></div>
                    <div class="chart-bar-track"><span class="chart-bar budget-approved" style="width:<?= h(number_format($approvedPct,2,'.','')) ?>%"></span></div>
                </div>
                <div class="chart-bar-row">
                    <div class="chart-bar-label"><span>เบิกจ่ายจริงแล้ว</span><strong><?= h(qa_money($actualExpense)) ?></strong></div>
                    <div class="chart-bar-track"><span class="chart-bar budget-paid" style="width:<?= h(number_format($budgetPct,2,'.','')) ?>%"></span></div>
                </div>
            </div>

            <div class="dashboard-budget-summary">
                <div class="dashboard-budget-summary-row">
                    <span>ยังไม่จัดสรร</span>
                    <strong><?= h(qa_money($unallocated)) ?></strong>
                    <small>งบทั้งหมด − ยอดจัดสรร</small>
                </div>
                <div class="dashboard-budget-summary-row">
                    <span>ส่วนกลาง / เงินสำรอง</span>
                    <strong><?= h(qa_money($centralAllocated)) ?></strong>
                    <small>วงเงินที่กันไว้เป็นส่วนกลาง</small>
                </div>
                <div class="dashboard-budget-summary-row">
                    <span>วงเงินที่ยังไม่ผูกกับโครงการ</span>
                    <strong><?= h(qa_money($allocatedNotApproved)) ?></strong>
                    <small>จัดสรรแล้ว แต่ยังไม่อนุมัติให้โครงการ</small>
                </div>
                <div class="dashboard-budget-summary-row">
                    <span>คงเหลือหลังเบิกจ่าย</span>
                    <strong><?= h(qa_money($balance)) ?></strong>
                    <small>งบทั้งหมด − ยอดเบิกจ่ายจริง</small>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="card dashboard-chart-card">
        <div class="section-heading">
            <div>
                <h2>งบของโครงการในขอบเขตของคุณ</h2>
                <p>ไม่แสดงงบประมาณทั้งโรงเรียนสำหรับ Role ที่ไม่มีสิทธิ์ดูงบส่วนกลาง</p>
            </div>
            <?php if(qa_menu_can('projects')): ?><a class="btn btn-sm" href="<?= h(qa_url('projects/index.php')) ?>">เปิดโครงการ</a><?php endif; ?>
        </div>
        <?php $scopeBudgetPct=qa_dash_pct($actualExpense,$approvedProjects); ?>
        <?php if($approvedProjects<=0): ?>
            <div class="placeholder-chart">ยังไม่มีวงเงินโครงการที่อนุมัติในขอบเขตของคุณ</div>
        <?php else: ?>
            <div class="budget-flow-chart">
                <div class="chart-bar-row"><div class="chart-bar-label"><span>งบโครงการที่อนุมัติ</span><strong><?= h(qa_money($approvedProjects)) ?></strong></div><div class="chart-bar-track"><span class="chart-bar budget-approved" style="width:100%"></span></div></div>
                <div class="chart-bar-row"><div class="chart-bar-label"><span>เบิกจ่ายจริง</span><strong><?= h(qa_money($actualExpense)) ?></strong></div><div class="chart-bar-track"><span class="chart-bar budget-paid" style="width:<?= h(number_format($scopeBudgetPct,2,'.','')) ?>%"></span></div></div>
            </div>
            <div class="chart-summary-pills"><span>คงเหลือในโครงการ <strong><?= h(qa_money(max(0,$approvedProjects-$actualExpense))) ?></strong></span></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<div class="card dashboard-chart-card">
        <div class="section-heading">
            <div>
                <h2>สถานะโครงการ</h2>
                <p>จำนวนโครงการแยกตามสถานะปัจจุบัน</p>
            </div>
            <a class="btn btn-sm" href="<?= h(qa_url('projects/index.php')) ?>">โครงการทั้งหมด</a>
        </div>

        <?php if($totalProjects<=0): ?>
            <div class="placeholder-chart">ยังไม่มีโครงการในปีงบประมาณนี้</div>
        <?php else: ?>
            <div class="donut-layout">
                <div class="donut-chart" style="<?= h($projectGradient) ?>">
                    <div class="donut-hole">
                        <strong><?= h($totalProjects) ?></strong>
                        <span>โครงการ</span>
                    </div>
                </div>
                <div class="donut-legend">
                    <?php
                    $legendColors=array('#2b67d9','#16a085','#f59e0b','#8b5cf6','#ef6c57','#0ea5a4','#64748b','#ec4899','#84a63d','#d97706','#475569');
                    $legendIndex=0;
                    foreach($statusRows as $row):
                        $legendColor=$legendColors[$legendIndex % count($legendColors)];
                        $legendIndex++;
                    ?>
                    <div class="legend-row">
                        <span class="legend-dot" style="background:<?= h($legendColor) ?>"></span>
                        <span class="legend-name"><?= h($row['status_name']) ?></span>
                        <strong><?= h($row['total']) ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</section>

<?php if($showGlobalBudget): ?>
<section class="grid grid-2 dashboard-chart-grid" style="margin-top:16px">
    <div class="card dashboard-chart-card">
        <div class="section-heading">
            <div>
                <h2>งบประมาณรายฝ่าย</h2>
                <p>จัดสรร → อนุมัติโครงการ → เบิกจ่ายจริง ของ 5 กลุ่มบริหาร</p>
            </div>
            <a class="btn btn-sm" href="<?= h(qa_url('budget/allocation.php')) ?>">เปิดการจัดสรร</a>
        </div>

        <?php if(empty($divisionRows)): ?>
            <div class="placeholder-chart">ยังไม่มีข้อมูลฝ่าย</div>
        <?php else: ?>
            <div class="division-budget-chart">
                <div class="division-color-key">
                    <span class="division-academic"><i></i>วิชาการ</span>
                    <span class="division-budget"><i></i>งบประมาณ</span>
                    <span class="division-personnel"><i></i>บุคคล</span>
                    <span class="division-general"><i></i>ทั่วไป</span>
                    <span class="division-student-affairs"><i></i>กิจการนักเรียน</span>
                </div>
                <div class="division-chart-legend">
                    <span>แถบอ่อน = จัดสรร</span>
                    <span>แถบกลาง = อนุมัติโครงการ</span>
                    <span>แถบเข้ม = ใช้จริง</span>
                </div>
                <?php foreach($divisionRows as $row): ?>
                    <?php
                    $divisionAllocation=(float)$row['allocated_amount'];
                    $divisionApproved=(float)$row['approved_amount'];
                    $divisionActual=(float)$row['actual_amount'];
                    $pApproved=qa_dash_pct($divisionApproved,$divisionAllocation);
                    $pActual=qa_dash_pct($divisionActual,$divisionAllocation);
                    ?>
                    <div class="division-chart-group <?= h(qa_division_theme_class($row['division_code'])) ?>">
                        <div class="division-chart-title">
                            <a href="<?= h(qa_url('budget/allocation-detail.php?division='.urlencode($row['division_code']))) ?>">
                                <span class="division-color-dot"></span><?= h($row['division_name']) ?>
                            </a>
                            <strong><?= h(qa_money($divisionAllocation)) ?></strong>
                        </div>
                        <div class="mini-bars">
                            <div class="mini-bar-track" title="จัดสรร <?= h(qa_money($divisionAllocation)) ?>"><span class="mini-bar allocation" style="width:<?= $divisionAllocation>0?'100':'0' ?>%"></span></div>
                            <div class="mini-bar-track" title="อนุมัติโครงการ <?= h(qa_money($divisionApproved)) ?>"><span class="mini-bar approved" style="width:<?= h(number_format($pApproved,2,'.','')) ?>%"></span></div>
                            <div class="mini-bar-track" title="ใช้จริง <?= h(qa_money($divisionActual)) ?>"><span class="mini-bar paid" style="width:<?= h(number_format($pActual,2,'.','')) ?>%"></span></div>
                        </div>
                        <div class="division-chart-values">
                            <span>อนุมัติ <?= h(qa_money($divisionApproved)) ?></span>
                            <span>ใช้จริง <?= h(qa_money($divisionActual)) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card dashboard-chart-card">
        <div class="section-heading">
            <div>
                <h2>ความครอบคลุมตัวชี้วัด สมศ.</h2>
                <p>แสดงทั้งการเชื่อมโครงการและหลักฐานเชิงประจักษ์</p>
            </div>
            <a class="btn btn-sm" href="<?= h(qa_url('quality/index.php')) ?>">Coverage Matrix</a>
        </div>

        <div class="quality-gauge-grid">
            <div class="quality-gauge">
                <div class="ring-gauge" style="--gauge:<?= h(number_format($indicatorPct,2,'.','')) ?>%">
                    <div><strong><?= h(number_format($indicatorPct,0)) ?>%</strong><span><?= h($coveredIndicators) ?>/<?= h($totalIndicators) ?></span></div>
                </div>
                <strong>มีโครงการรองรับ</strong>
            </div>
            <div class="quality-gauge">
                <div class="ring-gauge evidence-ring" style="--gauge:<?= h(number_format($evidencePct,2,'.','')) ?>%">
                    <div><strong><?= h(number_format($evidencePct,0)) ?>%</strong><span><?= h($evidenceCoveredIndicators) ?>/<?= h($totalIndicators) ?></span></div>
                </div>
                <strong>มีหลักฐานรองรับ</strong>
            </div>
        </div>

        <div class="standard-coverage-list">
            <?php foreach($standardRows as $row): ?>
                <?php
                $count=(int)$row['indicator_count'];
                $pc=(int)$row['project_covered'];
                $ec=(int)$row['evidence_covered'];
                $pcPct=qa_dash_pct($pc,$count);
                $ecPct=qa_dash_pct($ec,$count);
                ?>
                <div class="standard-coverage-row">
                    <div class="standard-coverage-title">
                        <span><strong>มาตรฐาน <?= h($row['standard_code']) ?></strong> <?= h($row['standard_name']) ?></span>
                        <small><?= h($count) ?> ตัวชี้วัด</small>
                    </div>
                    <div class="coverage-pair">
                        <div><span>โครงการ <?= h($pc) ?>/<?= h($count) ?></span><div class="coverage-track"><i class="coverage-project" style="width:<?= h(number_format($pcPct,2,'.','')) ?>%"></i></div></div>
                        <div><span>หลักฐาน <?= h($ec) ?>/<?= h($count) ?></span><div class="coverage-track"><i class="coverage-evidence" style="width:<?= h(number_format($ecPct,2,'.','')) ?>%"></i></div></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</section>
<?php else: ?>
<section style="margin-top:16px"><div class="card dashboard-chart-card">
        <div class="section-heading">
            <div>
                <h2>ความครอบคลุมตัวชี้วัด สมศ.</h2>
                <p>แสดงทั้งการเชื่อมโครงการและหลักฐานเชิงประจักษ์</p>
            </div>
            <a class="btn btn-sm" href="<?= h(qa_url('quality/index.php')) ?>">Coverage Matrix</a>
        </div>

        <div class="quality-gauge-grid">
            <div class="quality-gauge">
                <div class="ring-gauge" style="--gauge:<?= h(number_format($indicatorPct,2,'.','')) ?>%">
                    <div><strong><?= h(number_format($indicatorPct,0)) ?>%</strong><span><?= h($coveredIndicators) ?>/<?= h($totalIndicators) ?></span></div>
                </div>
                <strong>มีโครงการรองรับ</strong>
            </div>
            <div class="quality-gauge">
                <div class="ring-gauge evidence-ring" style="--gauge:<?= h(number_format($evidencePct,2,'.','')) ?>%">
                    <div><strong><?= h(number_format($evidencePct,0)) ?>%</strong><span><?= h($evidenceCoveredIndicators) ?>/<?= h($totalIndicators) ?></span></div>
                </div>
                <strong>มีหลักฐานรองรับ</strong>
            </div>
        </div>

        <div class="standard-coverage-list">
            <?php foreach($standardRows as $row): ?>
                <?php
                $count=(int)$row['indicator_count'];
                $pc=(int)$row['project_covered'];
                $ec=(int)$row['evidence_covered'];
                $pcPct=qa_dash_pct($pc,$count);
                $ecPct=qa_dash_pct($ec,$count);
                ?>
                <div class="standard-coverage-row">
                    <div class="standard-coverage-title">
                        <span><strong>มาตรฐาน <?= h($row['standard_code']) ?></strong> <?= h($row['standard_name']) ?></span>
                        <small><?= h($count) ?> ตัวชี้วัด</small>
                    </div>
                    <div class="coverage-pair">
                        <div><span>โครงการ <?= h($pc) ?>/<?= h($count) ?></span><div class="coverage-track"><i class="coverage-project" style="width:<?= h(number_format($pcPct,2,'.','')) ?>%"></i></div></div>
                        <div><span>หลักฐาน <?= h($ec) ?>/<?= h($count) ?></span><div class="coverage-track"><i class="coverage-evidence" style="width:<?= h(number_format($ecPct,2,'.','')) ?>%"></i></div></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</section>
<?php endif; ?>

<section class="grid grid-4 dashboard-info-grid" style="margin-top:16px">
    <div class="card"><div class="metric-label">โครงการในขอบเขตที่มองเห็น</div><div class="metric-value"><?= h($totalProjects) ?></div><div class="metric-note">ปีงบประมาณ <?= h($yearBE) ?> • <?= h($dashboardViewLabel) ?></div></div>
    <div class="card"><div class="metric-label">ตัวชี้วัดที่มีโครงการ</div><div class="metric-value"><?= h($coveredIndicators) ?> / <?= h($totalIndicators) ?></div><div class="metric-note">เชื่อมกับโครงการที่เข้าสู่ Workflow แล้ว</div></div>
    <div class="card"><div class="metric-label">ตัวชี้วัดที่มีหลักฐาน</div><div class="metric-value"><?= h($evidenceCoveredIndicators) ?> / <?= h($totalIndicators) ?></div><div class="metric-note">Evidence ↔ Indicator</div></div>
    <div class="card"><div class="metric-label">หลักฐานทั้งหมด</div><div class="metric-value"><?= h($totalEvidence) ?></div><div class="metric-note">ในปีงบประมาณปัจจุบัน</div></div>
</section>

<div class="dashboard-note notice" style="margin-top:16px">
    <strong>การอ่าน Dashboard:</strong> กราฟงบประมาณใช้ข้อมูลธุรกรรมจริงจากฐานข้อมูล ส่วนกราฟความครอบคลุม สมศ. แสดงความครบของข้อมูลในระบบ ไม่ใช่ผลตัดสินผ่าน/ไม่ผ่านการประเมินภายนอก
</div>

<?php require QA_ROOT . '/includes/footer.php'; ?>
