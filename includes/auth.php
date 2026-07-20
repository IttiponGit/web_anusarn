<?php

declare(strict_types=1);

const AUTH_SESSION_NAME = 'ANUSARNSESSID';
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
const AUTH_MANAGE_AREAS = [
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
    ini_set('session.use_only_cookies', '1');
    session_name(AUTH_SESSION_NAME);
    session_set_cookie_params([
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!session_start()) {
        throw new RuntimeException('Unable to start authentication session');
    }
}

function authSessionUser(array $databaseUser): array
{
    return [
        'user_id' => (int) $databaseUser['id'],
        'username' => (string) $databaseUser['username'],
        'full_name' => $databaseUser['full_name'],
        'role' => strtolower(trim((string) $databaseUser['role'])),
        'status' => (string) $databaseUser['status'],
        'logged_in' => true,
    ];
}

function storeAuthSessionUser(array $databaseUser): array
{
    startAuthSession();
    $sessionUser = authSessionUser($databaseUser);
    $_SESSION[AUTH_SESSION_KEY] = $sessionUser;
    return $sessionUser;
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
    $userId = is_array($sessionUser)
        ? filter_var($sessionUser['user_id'] ?? ($sessionUser['id'] ?? null), FILTER_VALIDATE_INT)
        : false;
    $loggedIn = is_array($sessionUser) && ($sessionUser['logged_in'] ?? true) === true;

    if (!$loggedIn || $userId === false || $userId < 1) {
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
        'user_id' => (int) $databaseUser['id'],
        'username' => (string) $databaseUser['username'],
        'full_name' => $databaseUser['full_name'],
        'role' => $role,
        'status' => (string) $databaseUser['status'],
    ];

    // Refresh non-sensitive session data from the database. Never store a password hash.
    storeAuthSessionUser($databaseUser);

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

function canManage(string $area, ?array $user = null): bool
{
    $normalizedArea = strtolower(trim($area));
    if (!in_array($normalizedArea, AUTH_MANAGE_AREAS, true)) {
        return false;
    }

    $user = $user ?? currentUser();
    if ($user === null || ($user['status'] ?? '') !== 'active') {
        return false;
    }

    $role = strtolower(trim((string) ($user['role'] ?? '')));
    return $role === 'admin' || $role === $normalizedArea;
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

function requireManage(string $area): array
{
    $user = requireLogin();
    if (!canManage($area, $user)) {
        denyAuthentication(403, 'Forbidden');
    }

    return $user;
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
