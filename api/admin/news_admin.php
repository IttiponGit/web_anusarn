<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

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

function json_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function parse_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function normalize_news_payload(array $input): array
{
    return [
        'title' => trim((string)($input['title'] ?? '')),
        'summary' => trim((string)($input['summary'] ?? '')),
        'content' => trim((string)($input['content'] ?? '')),
        'date' => trim((string)($input['date'] ?? '')),
        'category' => trim((string)($input['category'] ?? '')),
        'image' => trim((string)($input['image'] ?? '')),
        'status' => trim((string)($input['status'] ?? 'published')),
    ];
}

function validate_news_payload(array $payload): ?string
{
    $requiredFields = ['title', 'summary', 'content', 'date', 'category', 'image'];

    foreach ($requiredFields as $field) {
        if ($payload[$field] === '') {
            return 'Missing required field: ' . $field;
        }
    }

    if ($payload['status'] === '') {
        return 'Missing required field: status';
    }

    return null;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    json_response(405, [
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}

try {
    $statusColumnStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $statusColumnStmt->execute([
        ':table_name' => 'news',
        ':column_name' => 'status',
    ]);
    $hasStatusColumn = (int) $statusColumnStmt->fetchColumn() > 0;

    if ($method === 'GET') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $idProvided = isset($_GET['id']);

        if ($idProvided && $id === false) {
            json_response(400, [
                'success' => false,
                'error' => 'Invalid id'
            ]);
        }

        $statusSelect = $hasStatusColumn ? 'status' : 'NULL AS status';
        $baseSql = "SELECT id, title, summary, content, `date`, category, image, {$statusSelect}, created_at, updated_at FROM news";

        if ($idProvided) {
            $stmt = $pdo->prepare($baseSql . ' WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $item = $stmt->fetch();

            if (!$item) {
                json_response(404, [
                    'success' => false,
                    'error' => 'News not found'
                ]);
            }

            json_response(200, [
                'success' => true,
                'data' => $item
            ]);
        }

        $stmt = $pdo->prepare($baseSql . ' ORDER BY id DESC');
        $stmt->execute();
        $list = $stmt->fetchAll();

        json_response(200, [
            'success' => true,
            'data' => $list
        ]);
    }

    if ($method === 'POST') {
        $input = parse_json_body();
        $payload = normalize_news_payload($input);
        $validationError = validate_news_payload($payload);

        if ($validationError !== null) {
            json_response(400, [
                'success' => false,
                'error' => $validationError
            ]);
        }

        $nextIdStmt = $pdo->prepare('SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM news');
        $nextIdStmt->execute();
        $nextId = (int) $nextIdStmt->fetchColumn();

        if ($hasStatusColumn) {
            $insertStmt = $pdo->prepare(
                'INSERT INTO news (id, title, summary, content, `date`, category, image, status)
                 VALUES (:id, :title, :summary, :content, :date, :category, :image, :status)'
            );
            $insertStmt->execute([
                ':id' => $nextId,
                ':title' => $payload['title'],
                ':summary' => $payload['summary'],
                ':content' => $payload['content'],
                ':date' => $payload['date'],
                ':category' => $payload['category'],
                ':image' => $payload['image'],
                ':status' => $payload['status'],
            ]);
        } else {
            $insertStmt = $pdo->prepare(
                'INSERT INTO news (id, title, summary, content, `date`, category, image)
                 VALUES (:id, :title, :summary, :content, :date, :category, :image)'
            );
            $insertStmt->execute([
                ':id' => $nextId,
                ':title' => $payload['title'],
                ':summary' => $payload['summary'],
                ':content' => $payload['content'],
                ':date' => $payload['date'],
                ':category' => $payload['category'],
                ':image' => $payload['image'],
            ]);
        }

        json_response(201, [
            'success' => true,
            'data' => [
                'id' => $nextId
            ]
        ]);
    }

    if ($method === 'PUT') {
        $input = parse_json_body();
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);

        if ($id === false || $id === null) {
            json_response(400, [
                'success' => false,
                'error' => 'Invalid id'
            ]);
        }

        $payload = normalize_news_payload($input);
        $validationError = validate_news_payload($payload);

        if ($validationError !== null) {
            json_response(400, [
                'success' => false,
                'error' => $validationError
            ]);
        }

        if ($hasStatusColumn) {
            $updateStmt = $pdo->prepare(
                'UPDATE news
                 SET title = :title,
                     summary = :summary,
                     content = :content,
                     `date` = :date,
                     category = :category,
                     image = :image,
                     status = :status
                 WHERE id = :id'
            );
            $updateStmt->execute([
                ':id' => (int) $id,
                ':title' => $payload['title'],
                ':summary' => $payload['summary'],
                ':content' => $payload['content'],
                ':date' => $payload['date'],
                ':category' => $payload['category'],
                ':image' => $payload['image'],
                ':status' => $payload['status'],
            ]);
        } else {
            $updateStmt = $pdo->prepare(
                'UPDATE news
                 SET title = :title,
                     summary = :summary,
                     content = :content,
                     `date` = :date,
                     category = :category,
                     image = :image
                 WHERE id = :id'
            );
            $updateStmt->execute([
                ':id' => (int) $id,
                ':title' => $payload['title'],
                ':summary' => $payload['summary'],
                ':content' => $payload['content'],
                ':date' => $payload['date'],
                ':category' => $payload['category'],
                ':image' => $payload['image'],
            ]);
        }

        if ($updateStmt->rowCount() === 0) {
            $existsStmt = $pdo->prepare('SELECT id FROM news WHERE id = :id LIMIT 1');
            $existsStmt->execute([':id' => (int) $id]);
            $exists = $existsStmt->fetchColumn();

            if (!$exists) {
                json_response(404, [
                    'success' => false,
                    'error' => 'News not found'
                ]);
            }
        }

        json_response(200, [
            'success' => true
        ]);
    }

    $input = parse_json_body();
    $idFromQuery = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $idFromBody = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    $id = $idFromQuery !== null && $idFromQuery !== false ? $idFromQuery : $idFromBody;

    if ($id === false || $id === null) {
        json_response(400, [
            'success' => false,
            'error' => 'Invalid id'
        ]);
    }

    $deleteStmt = $pdo->prepare('DELETE FROM news WHERE id = :id');
    $deleteStmt->execute([
        ':id' => (int) $id,
    ]);

    if ($deleteStmt->rowCount() === 0) {
        json_response(404, [
            'success' => false,
            'error' => 'News not found'
        ]);
    }

    json_response(200, [
        'success' => true
    ]);
} catch (Throwable $e) {
    json_response(500, [
        'success' => false,
        'error' => 'Failed to manage news'
    ]);
}
