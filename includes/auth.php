<?php

declare(strict_types=1);

const AUTH_SESSION_KEY = 'admin_user';
const AUTH_LOGIN_PATH = '/admin/login.html';
const AUTH_ALLOWED_ROLES = [
    'admin',
    'students',
    'academic',
    'personnel',
    'budget',
    'general',
    'plan',
];

function startAuthSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function authPdo(): PDO
{
    global $pdo;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        require_once __DIR__ . '/../api/db.php';
    }

    return $pdo;
}

function clearAuthSession(): void
{
    startAuthSession();
    unset($_SESSION[AUTH_SESSION_KEY]);
}

function currentUser(): ?array
{
    static $resolved = false;
    static $user = null;

    if ($resolved) {
        return $user;
    }

    $resolved = true;
    startAuthSession();

    $sessionUser = $_SESSION[AUTH_SESSION_KEY] ?? null;
    $userId = is_array($sessionUser) ? filter_var($sessionUser['id'] ?? null, FILTER_VALIDATE_INT) : false;

    if ($userId === false || $userId < 1) {
        return null;
    }

    $stmt = authPdo()->prepare(
        'SELECT id, username, full_name, role, status
         FROM admin_users
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $userId]);
    $databaseUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$databaseUser || $databaseUser['status'] !== 'active') {
        clearAuthSession();
        return null;
    }

    $role = strtolower(trim((string) $databaseUser['role']));
    if (!in_array($role, AUTH_ALLOWED_ROLES, true)) {
        clearAuthSession();
        return null;
    }

    $user = [
        'id' => (int) $databaseUser['id'],
        'username' => (string) $databaseUser['username'],
        'full_name' => $databaseUser['full_name'],
        'role' => $role,
        'status' => (string) $databaseUser['status'],
    ];

    // Refresh non-sensitive session data from the database. Never store a password hash.
    $_SESSION[AUTH_SESSION_KEY] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
        'status' => $user['status'],
    ];

    return $user;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function isAdmin(): bool
{
    $user = currentUser();
    return $user !== null && $user['role'] === 'admin';
}

function isApiRequest(): bool
{
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

    return preg_match('#(^|/)api(/|$)#i', parse_url($requestUri, PHP_URL_PATH) ?: '') === 1
        || strpos($accept, 'application/json') !== false
        || $requestedWith === 'xmlhttprequest';
}

function denyAuthentication(int $statusCode, string $error): void
{
    if (isApiRequest()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => $error,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($statusCode === 401) {
        $returnTo = (string) ($_SERVER['REQUEST_URI'] ?? '/information/admin/');
        header('Location: ' . AUTH_LOGIN_PATH . '?return=' . rawurlencode($returnTo), true, 302);
        exit;
    }

    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

function requireLogin(): array
{
    $user = currentUser();
    if ($user === null) {
        denyAuthentication(401, 'Unauthorized');
    }

    return $user;
}

function requireRole(string $role): array
{
    return requireAnyRole([$role]);
}

function requireAnyRole(array $roles): array
{
    $user = requireLogin();
    if ($user['role'] === 'admin') {
        return $user;
    }

    $normalizedRoles = [];
    foreach ($roles as $role) {
        if (is_string($role)) {
            $normalizedRole = strtolower(trim($role));
            if ($normalizedRole !== '') {
                $normalizedRoles[] = $normalizedRole;
            }
        }
    }

    if (!in_array($user['role'], array_unique($normalizedRoles), true)) {
        denyAuthentication(403, 'Forbidden');
    }

    return $user;
}
