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

function normalize_personnel_payload(array $input): array
{
    $displayOrder = filter_var($input['display_order'] ?? null, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 0,
        ]
    ]);

    return [
        'name' => trim((string)($input['name'] ?? '')),
        'position' => trim((string)($input['position'] ?? '')),
        'department' => trim((string)($input['department'] ?? '')),
        'group_name' => trim((string)($input['group_name'] ?? '')),
        'image' => trim((string)($input['image'] ?? '')),
        'display_order' => $displayOrder,
        'status' => trim((string)($input['status'] ?? 'active')),
    ];
}

function validate_personnel_payload(array $payload): ?string
{
    $requiredFields = ['name', 'position', 'department', 'group_name', 'image', 'status'];

    foreach ($requiredFields as $field) {
        if ($payload[$field] === '') {
            return 'Missing required field: ' . $field;
        }
    }

    if ($payload['display_order'] === false || $payload['display_order'] === null) {
        return 'Invalid display_order';
    }

    if (!in_array($payload['status'], ['active', 'inactive'], true)) {
        return 'Invalid status';
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
    if ($method === 'GET') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $idProvided = isset($_GET['id']);

        if ($idProvided && $id === false) {
            json_response(400, [
                'success' => false,
                'error' => 'Invalid id'
            ]);
        }

        $baseSql = 'SELECT id, name, position, department, group_name, image, display_order, status, created_at, updated_at FROM personnel';

        if ($idProvided) {
            $stmt = $pdo->prepare($baseSql . ' WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => (int) $id]);
            $item = $stmt->fetch();

            if (!$item) {
                json_response(404, [
                    'success' => false,
                    'error' => 'Personnel not found'
                ]);
            }

            json_response(200, [
                'success' => true,
                'data' => $item
            ]);
        }

        $stmt = $pdo->prepare($baseSql . ' ORDER BY display_order ASC, id DESC');
        $stmt->execute();
        $list = $stmt->fetchAll();

        json_response(200, [
            'success' => true,
            'data' => $list
        ]);
    }

    if ($method === 'POST') {
        $input = parse_json_body();
        $payload = normalize_personnel_payload($input);
        $validationError = validate_personnel_payload($payload);

        if ($validationError !== null) {
            json_response(400, [
                'success' => false,
                'error' => $validationError
            ]);
        }

        $insertStmt = $pdo->prepare(
            'INSERT INTO personnel (name, position, department, group_name, image, display_order, status)
             VALUES (:name, :position, :department, :group_name, :image, :display_order, :status)'
        );
        $insertStmt->execute([
            ':name' => $payload['name'],
            ':position' => $payload['position'],
            ':department' => $payload['department'],
            ':group_name' => $payload['group_name'],
            ':image' => $payload['image'],
            ':display_order' => (int) $payload['display_order'],
            ':status' => $payload['status'],
        ]);

        json_response(201, [
            'success' => true,
            'data' => [
                'id' => (int) $pdo->lastInsertId()
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

        $payload = normalize_personnel_payload($input);
        $validationError = validate_personnel_payload($payload);

        if ($validationError !== null) {
            json_response(400, [
                'success' => false,
                'error' => $validationError
            ]);
        }

        $updateStmt = $pdo->prepare(
            'UPDATE personnel
             SET name = :name,
                 position = :position,
                 department = :department,
                 group_name = :group_name,
                 image = :image,
                 display_order = :display_order,
                 status = :status
             WHERE id = :id'
        );
        $updateStmt->execute([
            ':id' => (int) $id,
            ':name' => $payload['name'],
            ':position' => $payload['position'],
            ':department' => $payload['department'],
            ':group_name' => $payload['group_name'],
            ':image' => $payload['image'],
            ':display_order' => (int) $payload['display_order'],
            ':status' => $payload['status'],
        ]);

        if ($updateStmt->rowCount() === 0) {
            $existsStmt = $pdo->prepare('SELECT id FROM personnel WHERE id = :id LIMIT 1');
            $existsStmt->execute([':id' => (int) $id]);
            $exists = $existsStmt->fetchColumn();

            if (!$exists) {
                json_response(404, [
                    'success' => false,
                    'error' => 'Personnel not found'
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

    $deactivateStmt = $pdo->prepare('UPDATE personnel SET status = :status WHERE id = :id');
    $deactivateStmt->execute([
        ':status' => 'inactive',
        ':id' => (int) $id,
    ]);

    if ($deactivateStmt->rowCount() === 0) {
        $existsStmt = $pdo->prepare('SELECT id FROM personnel WHERE id = :id LIMIT 1');
        $existsStmt->execute([':id' => (int) $id]);
        $exists = $existsStmt->fetchColumn();

        if (!$exists) {
            json_response(404, [
                'success' => false,
                'error' => 'Personnel not found'
            ]);
        }
    }

    json_response(200, [
        'success' => true
    ]);
} catch (Throwable $e) {
    json_response(500, [
        'success' => false,
        'error' => 'Failed to manage personnel'
    ]);
}