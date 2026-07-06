<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

$admin = $_SESSION['admin_user'] ?? null;
if (!$admin) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../db.php';

const SITE_SETTING_DEFAULTS = [
    'personnel_count' => '85',
    'student_count' => '196',
    'classroom_count' => '29',
    'academic_year' => '2569',
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

function parse_post_body(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }

    return parse_json_body();
}

function build_homepage_stats(array $settings): array
{
    $result = [];

    foreach (SITE_SETTING_DEFAULTS as $key => $defaultValue) {
        $value = array_key_exists($key, $settings) ? trim((string) $settings[$key]) : '';
        $result[$key] = $value !== '' ? $value : $defaultValue;
    }

    return $result;
}

function fetch_all_settings(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM site_settings');
    $rows = $stmt->fetchAll();

    $settings = [];
    foreach ($rows as $row) {
        $key = isset($row['setting_key']) ? (string) $row['setting_key'] : '';
        if ($key === '') {
            continue;
        }

        $settings[$key] = isset($row['setting_value']) ? (string) $row['setting_value'] : '';
    }

    return $settings;
}

function normalize_non_negative_int($raw): ?int
{
    if (is_int($raw)) {
        return $raw >= 0 ? $raw : null;
    }

    if (!is_string($raw) && !is_numeric($raw)) {
        return null;
    }

    $value = trim((string) $raw);
    if ($value === '' || !preg_match('/^\d+$/', $value)) {
        return null;
    }

    return (int) $value;
}

function normalize_academic_year($raw): ?string
{
    $value = trim((string) $raw);
    if (!preg_match('/^\d{4}$/', $value)) {
        return null;
    }

    return $value;
}

function normalize_payload(array $input): array
{
    $personnel = normalize_non_negative_int($input['personnel_count'] ?? null);
    $student = normalize_non_negative_int($input['student_count'] ?? null);
    $classroom = normalize_non_negative_int($input['classroom_count'] ?? null);
    $academicYear = normalize_academic_year($input['academic_year'] ?? '');

    return [
        'personnel_count' => $personnel,
        'student_count' => $student,
        'classroom_count' => $classroom,
        'academic_year' => $academicYear,
    ];
}

function validate_payload(array $payload): ?string
{
    if (!is_int($payload['personnel_count']) || $payload['personnel_count'] < 0) {
        return 'personnel_count must be a number greater than or equal to 0';
    }

    if (!is_int($payload['student_count']) || $payload['student_count'] < 0) {
        return 'student_count must be a number greater than or equal to 0';
    }

    if (!is_int($payload['classroom_count']) || $payload['classroom_count'] < 0) {
        return 'classroom_count must be a number greater than or equal to 0';
    }

    if (!is_string($payload['academic_year']) || !preg_match('/^\d{4}$/', $payload['academic_year'])) {
        return 'academic_year must be a 4-digit number';
    }

    return null;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST', 'PUT'], true)) {
    json_response(405, [
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}

try {
    if ($method === 'GET') {
        $allSettings = fetch_all_settings($pdo);
        $stats = build_homepage_stats($allSettings);

        json_response(200, [
            'success' => true,
            'message' => 'Homepage stats loaded successfully',
            'data' => [
                'personnel_count' => (int) $stats['personnel_count'],
                'student_count' => (int) $stats['student_count'],
                'classroom_count' => (int) $stats['classroom_count'],
                'academic_year' => $stats['academic_year'],
            ]
        ]);
    }

    $input = $method === 'POST' ? parse_post_body() : parse_json_body();
    $payload = normalize_payload($input);
    $validationError = validate_payload($payload);

    if ($validationError !== null) {
        json_response(400, [
            'success' => false,
            'message' => $validationError
        ]);
    }

    $upsertStmt = $pdo->prepare(
        'INSERT INTO site_settings (setting_key, setting_value)
         VALUES (:setting_key, :setting_value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );

    foreach ($payload as $settingKey => $settingValue) {
        $upsertStmt->execute([
            ':setting_key' => $settingKey,
            ':setting_value' => (string) $settingValue,
        ]);
    }

    json_response(200, [
        'success' => true,
        'message' => 'Homepage stats updated successfully',
        'data' => [
            'personnel_count' => $payload['personnel_count'],
            'student_count' => $payload['student_count'],
            'classroom_count' => $payload['classroom_count'],
            'academic_year' => $payload['academic_year'],
        ]
    ]);
} catch (Throwable $e) {
    json_response(500, [
        'success' => false,
        'message' => 'Failed to process homepage stats'
    ]);
}
