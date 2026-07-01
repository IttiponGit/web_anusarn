<?php

// โหลดค่าการเชื่อมต่อจาก config.php
// ถ้าไม่มีไฟล์ config.php ให้ใช้ค่า default สำหรับ local development
$configFile = __DIR__ . '/config.php';

if (file_exists($configFile)) {
    include $configFile;
} else {
    // ค่า default สำหรับ local development
    $host = '127.0.0.1';
    $port = 3306;
    $dbname = 'anu_sarn_db';
    $username = 'root';
    $password = '';
}

try {
    $dsn = "mysql:host={$host}";
    if (!empty($port)) {
        $dsn .= ";port={$port}";
    }
    $dsn .= ";dbname={$dbname};charset=utf8mb4";

    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Database connection failed"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}