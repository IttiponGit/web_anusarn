<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';

$user = requireAnyRole([
    'students',
    'academic',
    'personnel',
    'budget',
    'general',
    'plan',
]);

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการข้อมูลสารสนเทศ</title>
</head>
<body>
    <main>
        <h1>จัดการข้อมูลสารสนเทศ</h1>
        <p>เข้าสู่ระบบเป็น <?= htmlspecialchars((string) ($user['full_name'] ?: $user['username']), ENT_QUOTES, 'UTF-8') ?></p>
        <p>บทบาท: <?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?></p>
    </main>
</body>
</html>
