<?php

header("Content-Type: application/json; charset=utf-8");
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
    $statusColumnExists = (bool) $pdo
        ->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'news' AND COLUMN_NAME = 'status'")
        ->fetchColumn();

    $selectColumns = "id, title, summary, content, `date`, category, image, created_at, updated_at";

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

        if ($statusColumnExists) {
            $sql .= " AND status = :status";
            $params[":status"] = "published";
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

    if ($statusColumnExists) {
        $sql .= " WHERE status = :status";
        $params[":status"] = "published";
    }

    $sql .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $newsList = $stmt->fetchAll();

    if (!$newsList) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "error" => "News not found"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

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