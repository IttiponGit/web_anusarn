<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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

try {
    $tables = [
        'news_count' => 'news',
        'personnel_count' => 'personnel',
        'downloads_count' => 'downloads',
        'admin_count' => 'admin_users',
    ];

    $stats = [];

    foreach ($tables as $key => $table) {
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM {$table}");
        $stats[$key] = (int) $stmt->fetchColumn();
    }

    echo json_encode([
        'success' => true,
        'data' => $stats,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load dashboard stats'
    ], JSON_UNESCAPED_UNICODE);
}