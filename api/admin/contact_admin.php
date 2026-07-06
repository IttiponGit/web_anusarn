<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

$admin = $_SESSION['admin_user'] ?? null;
if (!$admin) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../db.php';

const CONTACT_ADMIN_KEYS = [
    'school_name' => ['group' => 'school', 'required' => true],
    'address' => ['group' => 'contact', 'required' => true],
    'phone' => ['group' => 'contact', 'required' => false],
    'fax' => ['group' => 'contact', 'required' => false],
    'email' => ['group' => 'contact', 'required' => false],
    'website' => ['group' => 'contact', 'required' => false],
    'facebook_url' => ['group' => 'contact', 'required' => false],
    'working_hours' => ['group' => 'contact', 'required' => false],
    'map_url' => ['group' => 'contact', 'required' => false],
    'map_embed_url' => ['group' => 'contact', 'required' => false],
];

function json_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function parse_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function parse_request_body(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }

    return parse_json_body();
}

function has_site_settings_column(PDO $pdo, string $columnName): bool
{
    static $columnCache = null;

    if ($columnCache === null) {
        $stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_settings'");
        $columnCache = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
    }

    return isset($columnCache[$columnName]);
}

function normalize_contact_value(string $key, $rawValue): string
{
    $value = trim((string) $rawValue);

    if ($key === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid email format');
    }

    if (in_array($key, ['website', 'facebook_url', 'map_url', 'map_embed_url'], true) && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('Invalid URL format');
    }

    return $value;
}

function fetch_contact_settings(PDO $pdo): array
{
    $hasSettingGroup = has_site_settings_column($pdo, 'setting_group');

    $selectSql = 'SELECT setting_key, setting_value';
    if ($hasSettingGroup) {
        $selectSql .= ', setting_group';
    }

    $selectSql .= ' FROM site_settings WHERE ';
    if ($hasSettingGroup) {
        $selectSql .= "((setting_group = 'school' AND setting_key = 'school_name') OR (setting_group = 'contact' AND setting_key IN ('address', 'phone', 'fax', 'email', 'website', 'facebook_url', 'working_hours', 'map_url', 'map_embed_url')))";
    } else {
        $selectSql .= "(setting_key = 'school_name' OR setting_key IN ('address', 'phone', 'fax', 'email', 'website', 'facebook_url', 'working_hours', 'map_url', 'map_embed_url'))";
    }

    $selectSql .= ' ORDER BY setting_key ASC';

    $stmt = $pdo->query($selectSql);
    $rows = $stmt->fetchAll();

    $data = [];
    foreach (array_keys(CONTACT_ADMIN_KEYS) as $key) {
        $data[$key] = '';
    }

    foreach ($rows as $row) {
        $key = isset($row['setting_key']) ? (string) $row['setting_key'] : '';
        if ($key === '' || !array_key_exists($key, $data)) {
            continue;
        }

        $data[$key] = isset($row['setting_value']) ? (string) $row['setting_value'] : '';
    }

    return $data;
}

function validate_contact_payload(array $payload): ?string
{
    foreach (CONTACT_ADMIN_KEYS as $key => $meta) {
        $value = $payload[$key] ?? '';
        if ($meta['required'] && trim((string) $value) === '') {
            return 'Missing required field: ' . $key;
        }
    }

    return null;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST', 'PUT'], true)) {
    json_response(405, [
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}

try {
    if ($method === 'GET') {
        json_response(200, [
            'success' => true,
            'data' => fetch_contact_settings($pdo),
        ]);
    }

    $input = parse_request_body();
    $payload = [];

    foreach (CONTACT_ADMIN_KEYS as $key => $meta) {
        try {
            $payload[$key] = normalize_contact_value($key, $input[$key] ?? '');
        } catch (InvalidArgumentException $exception) {
            json_response(400, [
                'success' => false,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    $validationError = validate_contact_payload($payload);
    if ($validationError !== null) {
        json_response(400, [
            'success' => false,
            'error' => $validationError,
        ]);
    }

    $hasSettingGroup = has_site_settings_column($pdo, 'setting_group');
    $hasIsPublic = has_site_settings_column($pdo, 'is_public');

    $columns = ['setting_key', 'setting_value'];
    $placeholders = [':setting_key', ':setting_value'];

    if ($hasSettingGroup) {
        $columns[] = 'setting_group';
        $placeholders[] = ':setting_group';
    }

    if ($hasIsPublic) {
        $columns[] = 'is_public';
        $placeholders[] = ':is_public';
    }

    $updateSql = 'setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP';
    if ($hasSettingGroup) {
        $updateSql = 'setting_group = VALUES(setting_group), ' . $updateSql;
    }
    if ($hasIsPublic) {
        $updateSql = 'is_public = VALUES(is_public), ' . $updateSql;
    }

    $upsertSql = sprintf(
        'INSERT INTO site_settings (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
        implode(', ', $columns),
        implode(', ', $placeholders),
        $updateSql
    );

    $stmt = $pdo->prepare($upsertSql);
    foreach (CONTACT_ADMIN_KEYS as $key => $meta) {
        $params = [
            ':setting_key' => $key,
            ':setting_value' => $payload[$key],
        ];

        if ($hasSettingGroup) {
            $params[':setting_group'] = $meta['group'];
        }

        if ($hasIsPublic) {
            $params[':is_public'] = 1;
        }

        $stmt->execute($params);
    }

    json_response(200, [
        'success' => true,
        'data' => $payload,
    ]);
} catch (Throwable $e) {
    json_response(500, [
        'success' => false,
        'error' => 'Failed to save contact information'
    ]);
}