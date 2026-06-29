<?php
header("Content-Type: application/json; charset=utf-8");
require_once "db.php";

$sql = "SELECT * FROM news WHERE status = 'show' ORDER BY created_at DESC";
$stmt = $pdo->query($sql);
$data = $stmt->fetchAll();

echo json_encode([
    "success" => true,
    "data" => $data
], JSON_UNESCAPED_UNICODE);