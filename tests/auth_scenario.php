<?php

declare(strict_types=1);

$scenario = $argv[1] ?? 'allowed';
$userRole = $argv[2] ?? 'students';
$requiredRole = $argv[3] ?? 'students';
$status = $argv[4] ?? 'active';

$_SERVER['REQUEST_URI'] = '/api/test';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
ini_set('session.save_path', __DIR__ . '/.sessions');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(
    'CREATE TABLE admin_users (
        id INTEGER PRIMARY KEY,
        username TEXT NOT NULL,
        password_hash TEXT NOT NULL,
        full_name TEXT NULL,
        role TEXT NOT NULL,
        status TEXT NOT NULL
    )'
);

$stmt = $pdo->prepare(
    'INSERT INTO admin_users (id, username, password_hash, full_name, role, status)
     VALUES (1, :username, :password_hash, :full_name, :role, :status)'
);
$stmt->execute([
    ':username' => 'test-user',
    ':password_hash' => password_hash('test-password', PASSWORD_DEFAULT),
    ':full_name' => 'Test User',
    ':role' => $userRole,
    ':status' => $status,
]);

require_once __DIR__ . '/../includes/auth.php';

startAuthSession();
if ($scenario !== 'guest') {
    $_SESSION[AUTH_SESSION_KEY] = ['id' => 1];
}

$user = requireRole($requiredRole);
echo json_encode([
    'success' => true,
    'role' => $user['role'],
    'status' => $user['status'],
    'is_admin' => isAdmin(),
    'password_in_session' => array_key_exists('password_hash', $_SESSION[AUTH_SESSION_KEY]),
], JSON_UNESCAPED_UNICODE);
