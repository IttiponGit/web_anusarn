<?php

$allowedOrigins = [
    "http://localhost",
    "http://127.0.0.1",
    "http://localhost:5500",
    "http://127.0.0.1:5500"
];

$origin = $_SERVER["HTTP_ORIGIN"] ?? "";
if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Vary: Origin");
}

header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

require_once __DIR__ . "/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "error" => "Method not allowed"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $columns = $pdo
        ->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'downloads'")
        ->fetchAll(PDO::FETCH_COLUMN);

    $columnMap = array_fill_keys($columns, true);

    $fileUrlSelect = isset($columnMap["file_url"]) ? "file_url" : (isset($columnMap["file"]) ? "file AS file_url" : "'' AS file_url");
    $buttonTextSelect = isset($columnMap["button_text"]) ? "button_text" : "'ดาวน์โหลดเอกสาร' AS button_text";
    $displayOrderSelect = isset($columnMap["display_order"]) ? "display_order" : "id AS display_order";
    $orderBy = isset($columnMap["display_order"]) ? "display_order ASC" : "id ASC";

    $sql = "SELECT id, title, description, category, {$fileUrlSelect}, {$buttonTextSelect}, {$displayOrderSelect} FROM downloads";
    $params = [];

    if (isset($columnMap["status"])) {
        $sql .= " WHERE status = :status";
        $params[":status"] = "active";
    }

    $sql .= " ORDER BY {$orderBy}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "data" => $items
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
