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

function normalize_download_payload(array $input): array
{
    $displayOrder = filter_var($input['display_order'] ?? null, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 0,
        ]
    ]);

    $uploadId = filter_var($input['upload_id'] ?? null, FILTER_VALIDATE_INT);

    return [
        'title' => trim((string)($input['title'] ?? '')),
        'description' => trim((string)($input['description'] ?? '')),
        'category' => trim((string)($input['category'] ?? '')),
        'file_url' => trim((string)($input['file_url'] ?? '')),
        'button_text' => trim((string)($input['button_text'] ?? '')),
        'display_order' => $displayOrder,
        'status' => trim((string)($input['status'] ?? 'active')),
        'upload_id' => ($uploadId !== false && $uploadId !== null && $uploadId > 0) ? (int) $uploadId : null,
    ];
}

function apply_download_button_text_fallback(array $payload): array
{
    $fileUrl = trim((string)($payload['file_url'] ?? ''));
    $buttonText = trim((string)($payload['button_text'] ?? ''));

    if ($fileUrl !== '' && ($buttonText === '' || $buttonText === 'รอเพิ่มไฟล์')) {
        $payload['button_text'] = 'ดาวน์โหลด';
        return $payload;
    }

    if ($buttonText === '') {
        $payload['button_text'] = 'ดาวน์โหลดเอกสาร';
    }

    return $payload;
}

function validate_download_payload(array $payload): ?string
{
    if ($payload['title'] === '') {
        return 'Missing required field: title';
    }

    if ($payload['category'] === '') {
        return 'Missing required field: category';
    }

    if ($payload['file_url'] === '') {
        return 'Missing required field: file_url';
    }

    if ($payload['display_order'] === false || $payload['display_order'] === null) {
        return 'Invalid display_order';
    }

    if (!in_array($payload['status'], ['active', 'inactive'], true)) {
        return 'Invalid status';
    }

    return null;
}

function link_upload_to_download(PDO $pdo, int $downloadId, ?int $uploadId, string $filePath): void
{
    $filePath = trim($filePath);
    if ($filePath === '') {
        return;
    }

    if ($uploadId !== null) {
        $stmt = $pdo->prepare(
            'UPDATE uploads
             SET related_type = :related_type,
                 related_id = :related_id
             WHERE id = :id'
        );
        $stmt->execute([
            ':related_type' => 'downloads',
            ':related_id' => $downloadId,
            ':id' => $uploadId,
        ]);
        return;
    }

    if (strpos($filePath, 'uploads/') !== 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE uploads
         SET related_type = :related_type,
             related_id = :related_id
         WHERE file_path = :file_path
         ORDER BY id DESC
         LIMIT 1'
    );

    $stmt->execute([
        ':related_type' => 'downloads',
        ':related_id' => $downloadId,
        ':file_path' => $filePath,
    ]);
}

function cleanup_replaced_download_file(PDO $pdo, int $downloadId, string $oldPath, string $newPath): void
{
    $oldPath = trim($oldPath);
    $newPath = trim($newPath);

    if ($oldPath === '' || $oldPath === $newPath) {
        return;
    }

    if (strpos($oldPath, 'uploads/') !== 0) {
        return;
    }

    $downloadRefStmt = $pdo->prepare('SELECT COUNT(*) FROM downloads WHERE file_url = :file_url AND id <> :id');
    $downloadRefStmt->execute([
        ':file_url' => $oldPath,
        ':id' => $downloadId,
    ]);
    $downloadRefCount = (int) $downloadRefStmt->fetchColumn();

    if ($downloadRefCount > 0) {
        return;
    }

    $newsRefStmt = $pdo->prepare('SELECT COUNT(*) FROM news WHERE image = :image');
    $newsRefStmt->execute([
        ':image' => $oldPath,
    ]);
    $newsRefCount = (int) $newsRefStmt->fetchColumn();

    if ($newsRefCount > 0) {
        return;
    }

    $projectRoot = dirname(__DIR__, 2);
    $absolutePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldPath);

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }

    $deleteUploadStmt = $pdo->prepare('DELETE FROM uploads WHERE file_path = :file_path');
    $deleteUploadStmt->execute([
        ':file_path' => $oldPath,
    ]);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    json_response(405, [
        'success' => false,
        'error' => 'Method not allowed'
    ]);
}

try {
    $columns = $pdo
        ->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'downloads'")
        ->fetchAll(PDO::FETCH_COLUMN);

    $columnMap = array_fill_keys($columns, true);

    $hasStatusColumn = isset($columnMap['status']);
    $hasButtonTextColumn = isset($columnMap['button_text']);
    $hasDisplayOrderColumn = isset($columnMap['display_order']);
    $hasCreatedAtColumn = isset($columnMap['created_at']);
    $hasUpdatedAtColumn = isset($columnMap['updated_at']);

    $statusSelect = $hasStatusColumn ? 'd.status' : "'active' AS status";
    $buttonTextSelect = $hasButtonTextColumn ? 'd.button_text' : "'ดาวน์โหลดเอกสาร' AS button_text";
    $displayOrderSelect = $hasDisplayOrderColumn ? 'd.display_order' : 'd.id AS display_order';
    $createdAtSelect = $hasCreatedAtColumn ? 'd.created_at' : 'NULL AS created_at';
    $updatedAtSelect = $hasUpdatedAtColumn ? 'd.updated_at' : 'NULL AS updated_at';

    if ($method === 'GET') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $idProvided = isset($_GET['id']);

        if ($idProvided && $id === false) {
            json_response(400, [
                'success' => false,
                'error' => 'Invalid id'
            ]);
        }

        $baseSql = "SELECT
            d.id,
            d.title,
            d.description,
            d.category,
            d.file_url,
            {$buttonTextSelect},
            {$displayOrderSelect},
            {$statusSelect},
            {$createdAtSelect},
            {$updatedAtSelect},
            (
                SELECT u.file_type
                FROM uploads u
                WHERE (u.related_type = 'downloads' AND u.related_id = d.id)
                   OR u.file_path = d.file_url
                ORDER BY u.id DESC
                LIMIT 1
            ) AS upload_file_type,
            (
                SELECT u.mime_type
                FROM uploads u
                WHERE (u.related_type = 'downloads' AND u.related_id = d.id)
                   OR u.file_path = d.file_url
                ORDER BY u.id DESC
                LIMIT 1
            ) AS upload_mime_type,
            (
                SELECT u.file_size
                FROM uploads u
                WHERE (u.related_type = 'downloads' AND u.related_id = d.id)
                   OR u.file_path = d.file_url
                ORDER BY u.id DESC
                LIMIT 1
            ) AS upload_file_size
            FROM downloads d";

        if ($idProvided) {
            $stmt = $pdo->prepare($baseSql . ' WHERE d.id = :id LIMIT 1');
            $stmt->execute([':id' => (int) $id]);
            $item = $stmt->fetch();

            if (!$item) {
                json_response(404, [
                    'success' => false,
                    'error' => 'Download not found'
                ]);
            }

            json_response(200, [
                'success' => true,
                'data' => $item
            ]);
        }

        $orderBy = $hasDisplayOrderColumn ? 'd.display_order ASC, d.id DESC' : 'd.id DESC';
        $stmt = $pdo->prepare($baseSql . ' ORDER BY ' . $orderBy);
        $stmt->execute();
        $list = $stmt->fetchAll();

        json_response(200, [
            'success' => true,
            'data' => $list
        ]);
    }

    if ($method === 'POST') {
        $input = parse_json_body();
        $payload = apply_download_button_text_fallback(normalize_download_payload($input));
        $validationError = validate_download_payload($payload);

        if ($validationError !== null) {
            json_response(400, [
                'success' => false,
                'error' => $validationError
            ]);
        }

        $columns = ['title', 'description', 'category', 'file_url'];
        $values = [':title', ':description', ':category', ':file_url'];
        $params = [
            ':title' => $payload['title'],
            ':description' => $payload['description'],
            ':category' => $payload['category'],
            ':file_url' => $payload['file_url'],
        ];

        if ($hasButtonTextColumn) {
            $columns[] = 'button_text';
            $values[] = ':button_text';
            $params[':button_text'] = $payload['button_text'];
        }

        if ($hasDisplayOrderColumn) {
            $columns[] = 'display_order';
            $values[] = ':display_order';
            $params[':display_order'] = (int) $payload['display_order'];
        }

        if ($hasStatusColumn) {
            $columns[] = 'status';
            $values[] = ':status';
            $params[':status'] = $payload['status'];
        }

        $insertSql = 'INSERT INTO downloads (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute($params);

        $downloadId = (int) $pdo->lastInsertId();
        link_upload_to_download($pdo, $downloadId, $payload['upload_id'], $payload['file_url']);

        json_response(201, [
            'success' => true,
            'data' => [
                'id' => $downloadId,
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

        $payload = apply_download_button_text_fallback(normalize_download_payload($input));
        $validationError = validate_download_payload($payload);

        if ($validationError !== null) {
            json_response(400, [
                'success' => false,
                'error' => $validationError
            ]);
        }

        $oldFileStmt = $pdo->prepare('SELECT file_url FROM downloads WHERE id = :id LIMIT 1');
        $oldFileStmt->execute([':id' => (int) $id]);
        $existingDownload = $oldFileStmt->fetch();

        if (!$existingDownload) {
            json_response(404, [
                'success' => false,
                'error' => 'Download not found'
            ]);
        }

        $oldFilePath = (string) ($existingDownload['file_url'] ?? '');

        $setClauses = [
            'title = :title',
            'description = :description',
            'category = :category',
            'file_url = :file_url',
        ];
        $params = [
            ':id' => (int) $id,
            ':title' => $payload['title'],
            ':description' => $payload['description'],
            ':category' => $payload['category'],
            ':file_url' => $payload['file_url'],
        ];

        if ($hasButtonTextColumn) {
            $setClauses[] = 'button_text = :button_text';
            $params[':button_text'] = $payload['button_text'];
        }

        if ($hasDisplayOrderColumn) {
            $setClauses[] = 'display_order = :display_order';
            $params[':display_order'] = (int) $payload['display_order'];
        }

        if ($hasStatusColumn) {
            $setClauses[] = 'status = :status';
            $params[':status'] = $payload['status'];
        }

        $updateSql = 'UPDATE downloads SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute($params);

        if ($updateStmt->rowCount() === 0) {
            $existsStmt = $pdo->prepare('SELECT id FROM downloads WHERE id = :id LIMIT 1');
            $existsStmt->execute([':id' => (int) $id]);

            if (!$existsStmt->fetchColumn()) {
                json_response(404, [
                    'success' => false,
                    'error' => 'Download not found'
                ]);
            }
        }

        link_upload_to_download($pdo, (int) $id, $payload['upload_id'], $payload['file_url']);
        cleanup_replaced_download_file($pdo, (int) $id, $oldFilePath, $payload['file_url']);

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

    if ($hasStatusColumn) {
        $deleteStmt = $pdo->prepare('UPDATE downloads SET status = :status WHERE id = :id');
        $deleteStmt->execute([
            ':status' => 'inactive',
            ':id' => (int) $id,
        ]);
    } else {
        $deleteStmt = $pdo->prepare('DELETE FROM downloads WHERE id = :id');
        $deleteStmt->execute([
            ':id' => (int) $id,
        ]);
    }

    if ($deleteStmt->rowCount() === 0) {
        $existsStmt = $pdo->prepare('SELECT id FROM downloads WHERE id = :id LIMIT 1');
        $existsStmt->execute([':id' => (int) $id]);

        if (!$existsStmt->fetchColumn()) {
            json_response(404, [
                'success' => false,
                'error' => 'Download not found'
            ]);
        }
    }

    json_response(200, [
        'success' => true
    ]);
} catch (Throwable $e) {
    json_response(500, [
        'success' => false,
        'error' => 'Failed to manage downloads'
    ]);
}
