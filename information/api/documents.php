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

require_once __DIR__ . "/../../api/db.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Database connection is not available"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "error" => "Method not allowed"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function documentsResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT
            document_id,
            document_title,
            document_category,
            academic_year,
            file_url,
            file_type,
            file_size,
            description,
            display_order,
            is_active,
            created_at,
            updated_at
         FROM info_documents
         WHERE is_active = :is_active
         ORDER BY academic_year DESC, display_order ASC, document_id DESC"
    );
    $stmt->execute([":is_active" => 1]);

    $items = [];
    $categories = [];
    $fileTypes = [];
    $latestUpdatedAt = "";

    foreach ($stmt->fetchAll() as $row) {
        $updatedAt = $row["updated_at"] ?: $row["created_at"] ?: "";
        if ($updatedAt && (!$latestUpdatedAt || strcmp($updatedAt, $latestUpdatedAt) > 0)) {
            $latestUpdatedAt = $updatedAt;
        }

        $category = $row["document_category"] ?? "";
        $fileType = $row["file_type"] ?? "";
        if ($category !== "") {
            $categories[$category] = true;
        }
        if ($fileType !== "") {
            $fileTypes[$fileType] = true;
        }

        $items[] = [
            "document_id" => (int)$row["document_id"],
            "document_title" => $row["document_title"] ?? "",
            "document_category" => $category,
            "academic_year" => $row["academic_year"] ?? "",
            "file_url" => $row["file_url"] ?? "",
            "file_type" => $fileType,
            "file_size" => $row["file_size"] ?? "",
            "description" => $row["description"] ?? "",
            "display_order" => (int)($row["display_order"] ?? 0),
            "created_at" => $row["created_at"] ?? null,
            "updated_at" => $row["updated_at"] ?? null
        ];
    }

    documentsResponse(200, [
        "success" => true,
        "summary" => [
            "total" => count($items),
            "categories" => array_keys($categories),
            "file_types" => array_keys($fileTypes),
            "latest_updated_at" => $latestUpdatedAt
        ],
        "data" => $items
    ]);
} catch (Throwable $e) {
    documentsResponse(500, [
        "success" => false,
        "error" => "Cannot load documents data"
    ]);
}
