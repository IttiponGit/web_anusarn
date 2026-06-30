<?php

header('Content-Type: application/json; charset=utf-8');

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Username and password are required'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT id, username, password_hash, full_name, role, status
         FROM admin_users
         WHERE username = :username
         LIMIT 1'
    );
    $stmt->execute([
        ':username' => $username,
    ]);

    $admin = $stmt->fetch();

    if (!$admin || $admin['status'] !== 'active' || !password_verify($password, $admin['password_hash'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid username or password'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['admin_user'] = [
        'id' => (int) $admin['id'],
        'username' => $admin['username'],
        'full_name' => $admin['full_name'],
        'role' => $admin['role'],
    ];

    echo json_encode([
        'success' => true
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Login failed'
    ], JSON_UNESCAPED_UNICODE);
}
