<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';

startAuthSession();
$pdo = authPdo();

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

function logLoginFailure(string $reason, string $username): void
{
    $safeUsername = preg_replace('/[^a-zA-Z0-9_.@-]/', '?', $username) ?? '';
    $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    error_log(sprintf(
        '[auth] login_failed reason=%s username=%s ip=%s',
        $reason,
        $safeUsername,
        $ipAddress
    ));
}

function invalidCredentialsResponse(): void
{
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid username or password'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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

    if (!$admin) {
        logLoginFailure('username_not_found', $username);
        invalidCredentialsResponse();
    }

    if ($admin['status'] !== 'active') {
        logLoginFailure('account_inactive', $username);
        invalidCredentialsResponse();
    }

    if (!password_verify($password, (string) $admin['password_hash'])) {
        logLoginFailure('password_mismatch', $username);
        invalidCredentialsResponse();
    }

    session_regenerate_id(true);
    $_SESSION[AUTH_SESSION_KEY] = [
        'id' => (int) $admin['id'],
        'username' => $admin['username'],
        'full_name' => $admin['full_name'],
        'role' => $admin['role'],
        'status' => $admin['status'],
    ];

    echo json_encode([
        'success' => true
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log(sprintf(
        '[auth] login_error type=%s message=%s',
        get_class($e),
        $e->getMessage()
    ));
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Login failed'
    ], JSON_UNESCAPED_UNICODE);
}
