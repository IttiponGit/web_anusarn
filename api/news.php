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

if (function_exists("mysqli_report")) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

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
        ->query("SHOW COLUMNS FROM news")
        ->fetchAll(PDO::FETCH_COLUMN, 0);

    $hasStatusColumn = in_array("status", $columns, true);
    $hasIsPublishedColumn = in_array("is_published", $columns, true);

    $selectParts = ["id", "title", "summary", "content", "`date`", "category", "image"];
    if (in_array("created_at", $columns, true)) {
        $selectParts[] = "created_at";
    }
    if (in_array("updated_at", $columns, true)) {
        $selectParts[] = "updated_at";
    }

    $selectColumns = implode(", ", $selectParts);

    $id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
    $idParamExists = isset($_GET["id"]);

    if ($idParamExists && $id === false) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => "Invalid id"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($idParamExists) {
        $sql = "SELECT {$selectColumns} FROM news WHERE id = :id";
        $params = [":id" => $id];

        if ($hasStatusColumn) {
            $sql .= " AND status = :status";
            $params[":status"] = "published";
        } elseif ($hasIsPublishedColumn) {
            $sql .= " AND is_published = :is_published";
            $params[":is_published"] = 1;
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $news = $stmt->fetch();

        if (!$news) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "error" => "News not found"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            "success" => true,
            "data" => $news
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "SELECT {$selectColumns} FROM news";
    $params = [];

    if ($hasStatusColumn) {
        $sql .= " WHERE status = :status";
        $params[":status"] = "published";
    } elseif ($hasIsPublishedColumn) {
        $sql .= " WHERE is_published = :is_published";
        $params[":is_published"] = 1;
    }

    $sql .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $newsList = $stmt->fetchAll();

    echo json_encode([
        "success" => true,
        "data" => $newsList
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}