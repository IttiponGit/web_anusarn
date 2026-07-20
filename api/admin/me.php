<?php

require_once __DIR__ . '/../../includes/auth.php';

startAuthSession();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$admin = currentUser();

if (!$admin) {
    session_write_close();
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'logged_in' => false
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

session_write_close();

echo json_encode([
    'success' => true,
    'logged_in' => true,
    'data' => [
        'user_id' => $admin['user_id'],
        'username' => $admin['username'],
        'full_name' => $admin['full_name'],
        'role' => $admin['role'],
        'status' => $admin['status'],
    ]
], JSON_UNESCAPED_UNICODE);
