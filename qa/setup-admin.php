<?php
require_once __DIR__ . '/includes/bootstrap.php';

$db = qa_db();
$adminCount = (int) qa_db_scalar("SELECT COUNT(*) FROM qa_user_roles ur INNER JOIN qa_roles r ON r.role_id = ur.role_id WHERE r.role_code = 'admin'", 0);
$message = '';
$error = '';

if ($adminCount > 0) {
    $error = 'ระบบมีบัญชีผู้ดูแล (admin) แล้ว เพื่อความปลอดภัยหน้านี้จะไม่สร้างบัญชีเพิ่ม กรุณาลบไฟล์ setup-admin.php ออกจากเซิร์ฟเวอร์';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $setupKey = isset($_POST['setup_key']) ? $_POST['setup_key'] : '';
    $csrf = isset($_POST['csrf']) ? $_POST['csrf'] : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password2 = isset($_POST['password2']) ? $_POST['password2'] : '';
    $prefix = isset($_POST['prefix']) ? trim($_POST['prefix']) : '';
    $firstName = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $lastName = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $position = isset($_POST['position_name']) ? trim($_POST['position_name']) : '';

    if (!qa_verify_csrf($csrf)) {
        $error = 'เซสชันไม่ถูกต้อง กรุณาโหลดหน้าใหม่';
    } elseif (!defined('QA_SETUP_KEY') || QA_SETUP_KEY === 'CHANGE-THIS-TO-A-LONG-RANDOM-SECRET') {
        $error = 'กรุณากำหนด QA_SETUP_KEY ใน includes/config.php ให้เป็นข้อความสุ่มยาวก่อน';
    } elseif ($setupKey !== QA_SETUP_KEY) {
        $error = 'Setup Key ไม่ถูกต้อง';
    } elseif ($username === '' || $firstName === '' || $lastName === '' || $password === '') {
        $error = 'กรุณากรอกข้อมูลที่จำเป็นให้ครบ';
    } elseif (strlen($password) < 10) {
        $error = 'รหัสผ่านต้องมีอย่างน้อย 10 ตัวอักษร';
    } elseif ($password !== $password2) {
        $error = 'ยืนยันรหัสผ่านไม่ตรงกัน';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->begin_transaction();
        try {
            $stmt = $db->prepare("INSERT INTO qa_users (username, password_hash, prefix, first_name, last_name, position_name, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param('ssssss', $username, $hash, $prefix, $firstName, $lastName, $position);
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            $userId = $stmt->insert_id;
            $stmt->close();

            $roleId = (int) qa_db_scalar("SELECT role_id FROM qa_roles WHERE role_code = 'admin' LIMIT 1", 0);
            if ($roleId <= 0) {
                throw new Exception('ไม่พบ role admin ใน qa_roles');
            }

            $roleStmt = $db->prepare("INSERT INTO qa_user_roles (user_id, role_id) VALUES (?, ?)");
            $roleStmt->bind_param('ii', $userId, $roleId);
            if (!$roleStmt->execute()) {
                throw new Exception($roleStmt->error);
            }
            $roleStmt->close();
            $db->commit();
            $message = 'สร้างบัญชีผู้ดูแลเรียบร้อยแล้ว กรุณาลบไฟล์ setup-admin.php ออกจากเซิร์ฟเวอร์ แล้วเข้าสู่ระบบ';
        } catch (Exception $e) {
            $db->rollback();
            $error = 'ไม่สามารถสร้างบัญชีได้: ' . $e->getMessage();
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
    <title>สร้างผู้ดูแลระบบ | <?= h(QA_APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= h(qa_url('assets/css/app.css')) ?>">
</head>
<body>
<div class="login-page">
    <div class="login-card" style="max-width:620px">
        <div class="login-brand">
            <div class="logo">QA</div>
            <h1>สร้างบัญชีผู้ดูแลครั้งแรก</h1>
            <p><?= h(QA_SCHOOL_NAME) ?></p>
        </div>

        <?php if ($error !== ''): ?><div class="alert alert-danger" style="margin-bottom:14px"><?= h($error) ?></div><?php endif; ?>
        <?php if ($message !== ''): ?><div class="alert alert-success" style="margin-bottom:14px"><?= h($message) ?></div><?php endif; ?>

        <?php if ($adminCount === 0 && $message === ''): ?>
        <form method="post" action="<?= h(qa_url('setup-admin.php')) ?>">
            <input type="hidden" name="csrf" value="<?= h(qa_csrf_token()) ?>">
            <div class="form-group"><label>Setup Key</label><input type="password" name="setup_key" required></div>
            <div class="form-group" style="margin-top:12px"><label>ชื่อผู้ใช้</label><input name="username" value="admin" required></div>
            <div class="grid grid-2" style="margin-top:12px">
                <div class="form-group"><label>คำนำหน้า</label><input name="prefix" placeholder="นาย / นาง / นางสาว"></div>
                <div class="form-group"><label>ตำแหน่ง</label><input name="position_name" value="ผู้ดูแลระบบ"></div>
            </div>
            <div class="grid grid-2" style="margin-top:12px">
                <div class="form-group"><label>ชื่อ</label><input name="first_name" required></div>
                <div class="form-group"><label>นามสกุล</label><input name="last_name" required></div>
            </div>
            <div class="grid grid-2" style="margin-top:12px">
                <div class="form-group"><label>รหัสผ่าน</label><input type="password" name="password" minlength="10" required></div>
                <div class="form-group"><label>ยืนยันรหัสผ่าน</label><input type="password" name="password2" minlength="10" required></div>
            </div>
            <button class="btn btn-primary" type="submit" style="width:100%;margin-top:18px">สร้างบัญชี Admin</button>
        </form>
        <?php endif; ?>

        <div class="login-note">เพื่อความปลอดภัย ให้ลบ <strong>setup-admin.php</strong> ทันทีหลังสร้างบัญชีสำเร็จ</div>
    </div>
</div>
</body>
</html>
