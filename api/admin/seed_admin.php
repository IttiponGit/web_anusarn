<?php

header('Content-Type: application/json; charset=utf-8');

// หลังสร้าง admin คนแรกแล้ว ควรลบหรือปิดไฟล์นี้ทันทีเพื่อความปลอดภัย

require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawInput = file_get_contents('php://input');
$jsonInput = null;

if (is_string($rawInput) && trim($rawInput) !== '') {
    $decodedInput = json_decode($rawInput, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedInput) && !empty($decodedInput)) {
        $jsonInput = $decodedInput;
    }
}

$inputData = $jsonInput ?? $_POST;

$missingFields = [];

foreach (['username', 'password', 'full_name'] as $field) {
    if (!isset($inputData[$field]) || trim((string) $inputData[$field]) === '') {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required fields',
        'missing_fields' => $missingFields
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$username = trim((string) $inputData['username']);
$password = (string) $inputData['password'];
$fullName = trim((string) $inputData['full_name']);

try {
    $tableExists = (bool) $pdo
        ->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users'")
        ->fetchColumn();

    if (!$tableExists) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'admin_users table does not exist'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $existingCount = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();

    if ($existingCount > 0) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Admin already exists. Seed is allowed only once.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $existingUsernameStmt = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE username = :username');
    $existingUsernameStmt->execute([
        ':username' => $username,
    ]);

    if ((int) $existingUsernameStmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Username already exists'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    if ($passwordHash === false) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Unable to hash password'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO admin_users (username, password_hash, full_name, role, status)
         VALUES (:username, :password_hash, :full_name, :role, :status)'
    );

    $stmt->execute([
        ':username' => $username,
        ':password_hash' => $passwordHash,
        ':full_name' => $fullName,
        ':role' => 'admin',
        ':status' => 'active',
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Admin created successfully'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Seed failed'
    ], JSON_UNESCAPED_UNICODE);
}
