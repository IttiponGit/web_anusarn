<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (qa_is_logged_in()) {
    header('Location: ' . qa_url('dashboard.php'));
    exit;
}

$error = '';
$username = '';
$returnTo = isset($_GET['return']) ? $_GET['return'] : '';
if (isset($_POST['return'])) {
    $returnTo = $_POST['return'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $csrf = isset($_POST['csrf']) ? $_POST['csrf'] : '';

    if (!qa_verify_csrf($csrf)) {
        $error = 'เซสชันไม่ถูกต้อง กรุณาลองเข้าสู่ระบบใหม่';
    } elseif ($username === '' || $password === '') {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $db = qa_db();
        $stmt = $db->prepare("SELECT user_id, username, password_hash, prefix, first_name, last_name, status FROM qa_users WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($dbUserId, $dbUsername, $dbPasswordHash, $dbPrefix, $dbFirstName, $dbLastName, $dbStatus);
        $user = null;
        if ($stmt->fetch()) {
            $user = array(
                'user_id' => $dbUserId,
                'username' => $dbUsername,
                'password_hash' => $dbPasswordHash,
                'prefix' => $dbPrefix,
                'first_name' => $dbFirstName,
                'last_name' => $dbLastName,
                'status' => $dbStatus
            );
        }
        $stmt->close();

        if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        } else {
            $roles = array();
            $roleStmt = $db->prepare("SELECT r.role_code FROM qa_user_roles ur INNER JOIN qa_roles r ON r.role_id = ur.role_id WHERE ur.user_id = ? AND r.is_active = 1");
            $uid = (int) $user['user_id'];
            $roleStmt->bind_param('i', $uid);
            $roleStmt->execute();
            $roleStmt->bind_result($roleCode);
            while ($roleStmt->fetch()) {
                $roles[] = $roleCode;
            }
            $roleStmt->close();

            qa_login_user($user, $roles);
            $db->query("UPDATE qa_users SET last_login_at = NOW() WHERE user_id = " . $uid);

            // ป้องกัน open redirect: อนุญาตเฉพาะ path ภายใน /qa/
            if ($returnTo !== '' && strpos($returnTo, QA_BASE_URL . '/') === 0) {
                header('Location: ' . $returnTo);
            } else {
                header('Location: ' . qa_url('dashboard.php'));
            }
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>เข้าสู่ระบบ | <?= h(QA_APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= h(qa_url('assets/css/app.css')) ?>">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <div class="login-brand">
            <div class="logo">QA</div>
            <h1><?= h(QA_APP_NAME) ?></h1>
            <p><?= h(QA_SCHOOL_NAME) ?><br>ระบบบริหารแผน งบประมาณ โครงการ ผลลัพธ์ และหลักฐานคุณภาพ</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger" style="margin-bottom:14px"><?= h($error) ?></div>
        <?php endif; ?>

        <form action="<?= h(qa_url('login.php')) ?>" method="post" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= h(qa_csrf_token()) ?>">
            <input type="hidden" name="return" value="<?= h($returnTo) ?>">
            <div class="form-group">
                <label for="username">ชื่อผู้ใช้</label>
                <input id="username" name="username" placeholder="ชื่อผู้ใช้" value="<?= h($username) ?>" autocomplete="username" required>
            </div>
            <div class="form-group" style="margin-top:12px">
                <label for="password">รหัสผ่าน</label>
                <input id="password" name="password" type="password" placeholder="รหัสผ่าน" autocomplete="current-password" required>
            </div>
            <button class="btn btn-primary" type="submit" style="width:100%; margin-top:18px">เข้าสู่ระบบ</button>
        </form>
        <div class="login-note">บัญชีผู้ใช้และรหัสผ่านตรวจสอบจากฐานข้อมูล <strong>anusarn_qa</strong></div>
    </div>
</div>
</body>
</html>
