<?php
$pageTitle = 'ล้างข้อมูลโครงการทดสอบ';
$pageHeading = 'ล้างข้อมูลโครงการทดสอบ';
$pageDescription = 'เครื่องมือสำหรับล้างข้อมูลธุรกรรมจากโครงการทดสอบทั้งหมด โดยเก็บ Master Data แผน งบประมาณ การจัดสรร ผู้ใช้ และตัวชี้วัดไว้';
$activeMenu = 'settings';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

if (!qa_user_has_role('admin')) {
    http_response_code(403);
    die('เฉพาะผู้ดูแลระบบ (admin) เท่านั้นที่ใช้เครื่องมือนี้ได้');
}

$db = qa_db();
$user = qa_current_user();
$userId = $user ? (int)$user['user_id'] : 0;

$breadcrumbs = array(
    array('label'=>'ตั้งค่าระบบ','url'=>qa_url('settings/index.php')),
    array('label'=>'ล้างข้อมูลโครงการทดสอบ')
);

function cleanup_scalar($sql, $defaultValue)
{
    return qa_db_scalar($sql, $defaultValue);
}

function cleanup_remove_empty_dirs($baseDir)
{
    if (!is_dir($baseDir)) return;

    $items = scandir($baseDir);
    if (!is_array($items)) return;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $baseDir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            cleanup_remove_empty_dirs($path);
        }
    }

    $items = scandir($baseDir);
    if (is_array($items) && count($items) <= 2) {
        @rmdir($baseDir);
    }
}

function cleanup_delete_safe_evidence_file($relativePath)
{
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '') return false;

    $base = QA_ROOT . '/uploads/evidences';
    if (!is_dir($base)) return false;

    $baseReal = realpath($base);
    if ($baseReal === false) return false;

    $candidate = QA_ROOT . '/' . ltrim($relativePath, '/\\');
    $candidateReal = realpath($candidate);

    if ($candidateReal === false || !is_file($candidateReal)) {
        return false;
    }

    $prefix = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($candidateReal, $prefix) !== 0) {
        return false;
    }

    return @unlink($candidateReal);
}

function cleanup_flash($type, $text)
{
    $_SESSION['qa_cleanup_flash'] = array(
        'type'=>$type,
        'text'=>$text
    );
}

function cleanup_redirect()
{
    header('Location: ' . qa_url('settings/test-data-cleanup.php'));
    exit;
}

/* Preview */
$projectCount = (int)cleanup_scalar("SELECT COUNT(*) FROM qa_projects", 0);
$requestedTotal = (float)cleanup_scalar("SELECT COALESCE(SUM(requested_budget),0) FROM qa_projects", 0);
$approvedTotal = (float)cleanup_scalar("SELECT COALESCE(SUM(approved_budget),0) FROM qa_projects", 0);
$actualTotal = (float)cleanup_scalar("SELECT COALESCE(SUM(amount),0) FROM qa_expenditures WHERE payment_status='paid'", 0);
$progressCount = (int)cleanup_scalar("SELECT COUNT(*) FROM qa_progress_reports", 0);
$kpiCount = (int)cleanup_scalar("SELECT COUNT(*) FROM qa_project_kpis", 0);
$kpiResultCount = (int)cleanup_scalar("SELECT COUNT(*) FROM qa_project_kpi_results", 0);
$evidenceCount = (int)cleanup_scalar("SELECT COUNT(*) FROM qa_evidences WHERE project_id IS NOT NULL", 0);
$expenseCount = (int)cleanup_scalar("SELECT COUNT(*) FROM qa_expenditures", 0);
$resultCount = (int)cleanup_scalar("SELECT COUNT(*) FROM qa_project_results", 0);
$approvalCount = (int)cleanup_scalar("SELECT COUNT(*) FROM qa_project_approvals", 0);
$changeRequestCount = qa_project_change_table_ready()
    ? (int)cleanup_scalar("SELECT COUNT(*) FROM qa_project_change_requests",0)
    : 0;
$auditCount = (int)cleanup_scalar(
    "SELECT COUNT(*) FROM qa_audit_logs
     WHERE module_name='projects'
        OR record_table IN (
            'qa_projects','qa_project_approvals','qa_progress_reports',
            'qa_expenditures','qa_evidences','qa_project_results'
        )",
    0
);

$statusRows = array();
$result = $db->query(
    "SELECT p.status_code, COALESCE(ps.status_name,p.status_code) AS status_name, COUNT(*) AS total
     FROM qa_projects p
     LEFT JOIN qa_project_statuses ps ON ps.status_code=p.status_code
     GROUP BY p.status_code, ps.status_name
     ORDER BY total DESC, p.status_code"
);
if ($result) {
    while ($row=$result->fetch_assoc()) $statusRows[]=$row;
    $result->free();
}

$message='';
$messageType='success';
if (isset($_SESSION['qa_cleanup_flash']) && is_array($_SESSION['qa_cleanup_flash'])) {
    $messageType = isset($_SESSION['qa_cleanup_flash']['type']) ? $_SESSION['qa_cleanup_flash']['type'] : 'success';
    $message = isset($_SESSION['qa_cleanup_flash']['text']) ? $_SESSION['qa_cleanup_flash']['text'] : '';
    unset($_SESSION['qa_cleanup_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!qa_verify_csrf($token)) {
        http_response_code(400);
        die('CSRF token ไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }

    $confirmText = isset($_POST['confirm_text']) ? trim($_POST['confirm_text']) : '';
    $password = isset($_POST['current_password']) ? (string)$_POST['current_password'] : '';
    $acknowledge = isset($_POST['acknowledge']) ? $_POST['acknowledge'] : '';

    $requiredPhrase = 'ล้างข้อมูลโครงการทดสอบทั้งหมด';

    if ($projectCount <= 0) {
        cleanup_flash('danger','ไม่มีข้อมูลโครงการให้ล้าง');
        cleanup_redirect();
    }

    if ($acknowledge !== '1') {
        cleanup_flash('danger','กรุณายืนยันว่าข้อมูลโครงการทั้งหมดในระบบเป็นข้อมูลทดสอบ');
        cleanup_redirect();
    }

    if ($confirmText !== $requiredPhrase) {
        cleanup_flash('danger','ข้อความยืนยันไม่ตรง กรุณาพิมพ์ “'.$requiredPhrase.'” ให้ตรงทุกตัวอักษร');
        cleanup_redirect();
    }

    if ($password === '') {
        cleanup_flash('danger','กรุณากรอกรหัสผ่านของบัญชีผู้ดูแลระบบ');
        cleanup_redirect();
    }

    $passwordHash='';
    $stmt=$db->prepare("SELECT password_hash FROM qa_users WHERE user_id=? AND status='active' LIMIT 1");
    $stmt->bind_param('i',$userId);
    $stmt->execute();
    $stmt->bind_result($passwordHash);
    $found=$stmt->fetch();
    $stmt->close();

    if (!$found || !$passwordHash || !password_verify($password,$passwordHash)) {
        cleanup_flash('danger','รหัสผ่านผู้ดูแลระบบไม่ถูกต้อง จึงยังไม่มีการลบข้อมูล');
        cleanup_redirect();
    }

    /* Collect only files that belong to project evidence before deleting DB rows. */
    $filePaths=array();
    $result=$db->query(
        "SELECT file_path
         FROM qa_evidences
         WHERE project_id IS NOT NULL
           AND file_path IS NOT NULL
           AND TRIM(file_path)<>''"
    );
    if ($result) {
        while ($row=$result->fetch_assoc()) {
            $filePaths[]=$row['file_path'];
        }
        $result->free();
    }

    $before = array(
        'projects'=>$projectCount,
        'requested_budget'=>$requestedTotal,
        'approved_budget'=>$approvedTotal,
        'actual_expense'=>$actualTotal,
        'evidences'=>$evidenceCount,
        'expenditures'=>$expenseCount,
        'progress_reports'=>$progressCount,
        'project_results'=>$resultCount
    );

    $db->autocommit(false);

    try {
        /*
         * qa_evidences uses ON DELETE SET NULL for project_id,
         * so evidence rows must be deleted before qa_projects.
         */
        if (!$db->query(
            "DELETE eil
             FROM qa_evidence_indicator_links eil
             INNER JOIN qa_evidences ev ON ev.evidence_id=eil.evidence_id
             WHERE ev.project_id IS NOT NULL"
        )) {
            throw new Exception('ไม่สามารถลบความสัมพันธ์หลักฐานกับตัวชี้วัดได้');
        }

        if (!$db->query("DELETE FROM qa_evidences WHERE project_id IS NOT NULL")) {
            throw new Exception('ไม่สามารถลบหลักฐานของโครงการได้');
        }

        /*
         * Child tables of qa_projects use ON DELETE CASCADE.
         * This removes objectives, beneficiaries, links, activities,
         * budget items, KPI, KPI results, status history, approvals,
         * comments, commitments, expenditures, progress and final results.
         */
        if (!$db->query("DELETE FROM qa_projects")) {
            throw new Exception('ไม่สามารถลบโครงการได้: '.$db->error);
        }

        /* Remove test-project audit trail, then create one new cleanup audit after commit. */
        if (!$db->query(
            "DELETE FROM qa_audit_logs
             WHERE module_name='projects'
                OR record_table IN (
                    'qa_projects','qa_project_approvals','qa_progress_reports',
                    'qa_expenditures','qa_evidences','qa_project_results'
                )"
        )) {
            throw new Exception('ไม่สามารถล้าง Audit Log ของข้อมูลทดสอบได้');
        }

        $db->commit();
        $db->autocommit(true);

        /* Physical files are removed only after DB commit. */
        $deletedFiles=0;
        $failedFiles=0;
        foreach ($filePaths as $filePath) {
            if (cleanup_delete_safe_evidence_file($filePath)) {
                $deletedFiles++;
            } else {
                $candidate=QA_ROOT.'/'.ltrim($filePath,'/\\');
                if (file_exists($candidate)) $failedFiles++;
            }
        }

        $evidenceRoot = QA_ROOT . '/uploads/evidences';
        if (is_dir($evidenceRoot)) {
            cleanup_remove_empty_dirs($evidenceRoot);
        }

        qa_project_audit_action(
            'cleanup_test_project_data',
            'qa_projects',
            0,
            $before,
            array(
                'projects_after'=>(int)cleanup_scalar("SELECT COUNT(*) FROM qa_projects",0),
                'deleted_files'=>$deletedFiles,
                'failed_files'=>$failedFiles
            )
        );

        $text='ล้างข้อมูลโครงการทดสอบสำเร็จ: ลบโครงการ '.$before['projects'].' รายการ';
        if ($deletedFiles>0) $text.=', ลบไฟล์หลักฐาน '.$deletedFiles.' ไฟล์';
        if ($failedFiles>0) $text.=' (มีไฟล์ '.$failedFiles.' ไฟล์ที่ลบไม่ได้ กรุณาตรวจสอบโฟลเดอร์ uploads/evidences)';
        $text.=' โดย Master Data แผน งบประมาณ การจัดสรร ผู้ใช้ และตัวชี้วัดยังคงอยู่';

        cleanup_flash('success',$text);
        cleanup_redirect();

    } catch (Exception $e) {
        $db->rollback();
        $db->autocommit(true);
        cleanup_flash('danger','ยกเลิกการล้างข้อมูลทั้งหมด เนื่องจากเกิดข้อผิดพลาด: '.$e->getMessage());
        cleanup_redirect();
    }
}

require QA_ROOT . '/includes/header.php';
?>

<?php if($message!==''): ?>
<div class="alert <?= $messageType==='danger'?'alert-danger':'alert-success' ?>" style="margin-bottom:16px">
    <?= h($message) ?>
</div>
<?php endif; ?>

<div class="danger-zone">
    <div class="danger-zone-head">
        <div>
            <h2>Danger Zone — ล้างข้อมูลโครงการทดสอบทั้งหมด</h2>
            <p>ใช้เฉพาะช่วงพัฒนาระบบ เมื่อยืนยันแล้วว่าข้อมูลโครงการทั้งหมดเป็นข้อมูลสมมุติ</p>
        </div>
        <span class="badge badge-red">ADMIN ONLY</span>
    </div>

    <div class="alert alert-danger">
        <strong>คำสั่งนี้ย้อนกลับไม่ได้</strong><br>
        ระบบจะลบโครงการทุกสถานะ รวม Workflow, KPI, ผล KPI, ความก้าวหน้า, การเบิกจ่าย,
        Output/Outcome, หลักฐานของโครงการ และ Audit Log ที่เกิดจากโครงการทดสอบ
    </div>
</div>

<div class="grid grid-4" style="margin-top:16px">
    <div class="card"><div class="metric-label">โครงการที่จะถูกลบ</div><div class="metric-value"><?= h($projectCount) ?></div><div class="metric-note">ทุกสถานะ</div></div>
    <div class="card"><div class="metric-label">งบอนุมัติในโครงการ</div><div class="metric-value"><?= h(qa_money($approvedTotal)) ?></div><div class="metric-note">ข้อมูลธุรกรรมที่จะหาย</div></div>
    <div class="card"><div class="metric-label">เบิกจ่ายจริง</div><div class="metric-value"><?= h(qa_money($actualTotal)) ?></div><div class="metric-note"><?= h($expenseCount) ?> รายการ</div></div>
    <div class="card"><div class="metric-label">หลักฐานโครงการ</div><div class="metric-value"><?= h($evidenceCount) ?></div><div class="metric-note">DB + ไฟล์ที่อัปโหลด</div></div>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <h2>ข้อมูลที่จะถูกลบ</h2>
        <div class="cleanup-list">
            <div><span>โครงการ</span><strong><?= h($projectCount) ?></strong></div>
            <div><span>Workflow / Approval</span><strong><?= h($approvalCount) ?></strong></div>
            <div><span>รายงานความก้าวหน้า</span><strong><?= h($progressCount) ?></strong></div>
            <div><span>KPI / ผล KPI</span><strong><?= h($kpiCount) ?> / <?= h($kpiResultCount) ?></strong></div>
            <div><span>การเบิกจ่าย</span><strong><?= h($expenseCount) ?></strong></div>
            <div><span>สรุปผลโครงการ</span><strong><?= h($resultCount) ?></strong></div>
            <div><span>หลักฐานโครงการ</span><strong><?= h($evidenceCount) ?></strong></div>
            <div><span>คำขอแก้ไข / ยกเลิก</span><strong><?= h($changeRequestCount) ?></strong></div>
            <div><span>Audit Log ฝั่งโครงการ</span><strong><?= h($auditCount) ?></strong></div>
        </div>

        <?php if(!empty($statusRows)): ?>
        <h3 style="margin-top:16px">โครงการแยกตามสถานะ</h3>
        <div class="cleanup-statuses">
            <?php foreach($statusRows as $row): ?>
                <span class="badge badge-blue"><?= h($row['status_name']) ?> <?= h($row['total']) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>ข้อมูลที่จะเก็บไว้</h2>
        <div class="cleanup-preserve">
            <div>✓ ปีงบประมาณ / ปีการศึกษา</div>
            <div>✓ ผู้ใช้งาน / Roles / สิทธิ์</div>
            <div>✓ 4 กลุ่มบริหาร</div>
            <div>✓ แผนพัฒนาคุณภาพ</div>
            <div>✓ วิสัยทัศน์ / พันธกิจ / ค่านิยม</div>
            <div>✓ กลยุทธ์ / จุดเน้น / เป้าหมาย</div>
            <div>✓ มาตรฐานและตัวชี้วัด สมศ.</div>
            <div>✓ แหล่งเงิน / ก้อนงบประมาณ</div>
            <div>✓ การจัดสรรงบประมาณ 4 ฝ่าย</div>
        </div>
    </div>
</div>

<div class="card cleanup-confirm-card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>ยืนยันการล้างข้อมูล</h2>
            <p>ตั้งใจเพิ่มหลายชั้นเพื่อป้องกันการคลิกผิดหรือรันคำสั่ง SQL ผิดตาราง</p>
        </div>
    </div>

    <?php if($projectCount<=0): ?>
        <div class="notice">
            ไม่มีข้อมูลโครงการในระบบแล้ว จึงไม่มีสิ่งที่ต้องล้าง
        </div>
    <?php else: ?>
    <form method="post" id="cleanupForm" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">

        <label class="cleanup-ack">
            <input type="checkbox" name="acknowledge" value="1" id="acknowledge">
            <span>
                ฉันตรวจสอบแล้วว่า <strong>ข้อมูลโครงการทั้งหมด <?= h($projectCount) ?> รายการเป็นข้อมูลทดสอบ/สมมุติ</strong>
                และต้องการล้างข้อมูลธุรกรรมของโครงการทั้งหมด
            </span>
        </label>

        <div class="form-grid" style="margin-top:16px">
            <div class="form-group full">
                <label>พิมพ์ข้อความยืนยันให้ตรงทุกตัวอักษร *</label>
                <input
                    id="confirmText"
                    name="confirm_text"
                    required
                    autocomplete="off"
                    placeholder="ล้างข้อมูลโครงการทดสอบทั้งหมด">
                <div class="help-text">ข้อความที่ต้องพิมพ์: <strong>ล้างข้อมูลโครงการทดสอบทั้งหมด</strong></div>
            </div>

            <div class="form-group full">
                <label>รหัสผ่านบัญชีผู้ดูแลระบบปัจจุบัน *</label>
                <input
                    type="password"
                    id="currentPassword"
                    name="current_password"
                    required
                    autocomplete="current-password">
                <div class="help-text">ระบบจะตรวจด้วย password_verify() ก่อนเริ่ม Transaction</div>
            </div>
        </div>

        <div class="cleanup-final-warning">
            หลังจากกดปุ่มด้านล่าง ระบบจะใช้ Database Transaction
            หากคำสั่งฐานข้อมูลขั้นใดผิดพลาด จะ Rollback และไม่ลบข้อมูลโครงการ
        </div>

        <button
            class="btn btn-danger"
            id="cleanupButton"
            type="submit"
            disabled
            onclick="return confirm('ยืนยันขั้นสุดท้าย: ล้างข้อมูลโครงการทดสอบทั้งหมดออกจากระบบ? การดำเนินการนี้ย้อนกลับไม่ได้');">
            ล้างข้อมูลโครงการทดสอบทั้งหมด
        </button>
        <a class="btn" href="<?= h(qa_url('settings/index.php')) ?>">ยกเลิก</a>
    </form>
    <?php endif; ?>
</div>

<script>
(function(){
    var form=document.getElementById('cleanupForm');
    if(!form)return;

    var ack=document.getElementById('acknowledge');
    var text=document.getElementById('confirmText');
    var pass=document.getElementById('currentPassword');
    var button=document.getElementById('cleanupButton');
    var phrase='ล้างข้อมูลโครงการทดสอบทั้งหมด';

    function update(){
        button.disabled=!(ack.checked && text.value===phrase && pass.value.length>0);
    }

    ack.addEventListener('change',update);
    text.addEventListener('input',update);
    pass.addEventListener('input',update);
    update();
})();
</script>

<?php require QA_ROOT . '/includes/footer.php'; ?>
