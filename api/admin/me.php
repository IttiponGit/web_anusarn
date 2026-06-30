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
        'logged_in' => false
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'logged_in' => true,
    'data' => $admin
], JSON_UNESCAPED_UNICODE);
