<?php

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/db.php';

const CONTACT_SETTING_KEYS = [
    'school_name',
    'address',
    'phone',
    'fax',
    'email',
    'website',
    'facebook_url',
    'working_hours',
    'map_url',
    'map_embed_url',
];

function contact_json_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function contact_table_has_column(PDO $pdo, string $columnName): bool
{
    static $columnCache = null;

    if ($columnCache === null) {
        $stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_settings'");
        $columnCache = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
    }

    return isset($columnCache[$columnName]);
}

function contact_fetch_setting_rows(PDO $pdo): array
{
    $hasSettingGroup = contact_table_has_column($pdo, 'setting_group');
    $hasIsPublic = contact_table_has_column($pdo, 'is_public');

    $selectSql = 'SELECT setting_key, setting_value';
    if ($hasSettingGroup) {
        $selectSql .= ', setting_group';
    }

    $selectSql .= ' FROM site_settings';

    $conditions = [];
    if ($hasSettingGroup) {
        $conditions[] = "(setting_group = 'school' AND setting_key = 'school_name')";
        $conditions[] = "(setting_group = 'contact' AND setting_key IN ('address', 'phone', 'fax', 'email', 'website', 'facebook_url', 'working_hours', 'map_url', 'map_embed_url'))";
    } else {
        $conditions[] = "setting_key = 'school_name'";
        $conditions[] = "setting_key IN ('address', 'phone', 'fax', 'email', 'website', 'facebook_url', 'working_hours', 'map_url', 'map_embed_url')";
    }

    $whereSql = '(' . implode(' OR ', $conditions) . ')';
    if ($hasIsPublic) {
        $whereSql .= ' AND is_public = 1';
    }

    $stmt = $pdo->query($selectSql . ' WHERE ' . $whereSql . ' ORDER BY setting_key ASC');
    return $stmt->fetchAll();
}

try {
    $rows = contact_fetch_setting_rows($pdo);

    $data = array_fill_keys(CONTACT_SETTING_KEYS, '');
    foreach ($rows as $row) {
        $key = isset($row['setting_key']) ? (string) $row['setting_key'] : '';
        if ($key === '' || !array_key_exists($key, $data)) {
            continue;
        }

        $data[$key] = isset($row['setting_value']) ? (string) $row['setting_value'] : '';
    }

    contact_json_response(200, [
        'success' => true,
        'data' => $data,
    ]);
} catch (Throwable $e) {
    contact_json_response(500, [
        'success' => false,
        'error' => 'Failed to load contact information'
    ]);
}