<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';

$admin = requireManage('general');

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

function parse_request_body(): array
{
    $contentType = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));

    if (strpos($contentType, 'multipart/form-data') === 0 || strpos($contentType, 'application/x-www-form-urlencoded') === 0) {
        return is_array($_POST) ? $_POST : [];
    }

    return parse_json_body();
}

function normalize_download_payload(array $input, bool $requireStatusField = false): array
{
    $displayOrder = filter_var($input['display_order'] ?? null, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 0,
        ]
    ]);

    $uploadId = filter_var($input['upload_id'] ?? null, FILTER_VALIDATE_INT);
    $hasStatusField = array_key_exists('status', $input);
    $normalizedStatus = $hasStatusField ? normalize_download_status($input['status']) : null;

    if (!$hasStatusField && !$requireStatusField) {
        $normalizedStatus = 'draft';
    }

    return [
        'title' => trim((string)($input['title'] ?? '')),
        'description' => trim((string)($input['description'] ?? '')),
        'category' => trim((string)($input['category'] ?? '')),
        'file_url' => trim((string)($input['file_url'] ?? '')),
        'button_text' => trim((string)($input['button_text'] ?? '')),
        'display_order' => $displayOrder,
        'status' => $normalizedStatus,
        'status_provided' => $hasStatusField,
        'upload_id' => ($uploadId !== false && $uploadId !== null && $uploadId > 0) ? (int) $uploadId : null,
    ];
}

function normalize_download_status($value): ?string
{
    $status = strtolower(trim((string) $value));

    return in_array($status, ['publish', 'draft', 'archived'], true) ? $status : null;
}

function get_status_select_expr(bool $hasStatusColumn): string
{
    if (!$hasStatusColumn) {
        return "'draft' AS status";
    }

    return "CASE
        WHEN d.status = 'publish' THEN 'publish'
        WHEN d.status = 'draft' THEN 'draft'
        WHEN d.status = 'archived' THEN 'archived'
        ELSE 'draft'
    END AS status";
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

function validate_download_payload(array $payload, bool $requireStatusField = false): ?string
{
    if ($payload['title'] === '') {
        return 'Missing required field: title';
    }

    if ($payload['category'] === '') {
        return 'Missing required field: category';
    }

    if ($payload['file_url'] === '' && $payload['status'] === 'publish') {
        return 'Missing required field: file_url';
    }

    if ($payload['display_order'] === false || $payload['display_order'] === null) {
        return 'Invalid display_order';
    }

    if ($requireStatusField && !$payload['status_provided']) {
        return 'Missing required field: status';
    }

    if (!is_string($payload['status']) || !in_array($payload['status'], ['publish', 'draft', 'archived'], true)) {
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

$requestBody = [];
$effectiveMethod = $method;

if ($method !== 'GET') {
    $requestBody = parse_request_body();

    if ($method === 'POST') {
        $action = strtolower(trim((string)($requestBody['action'] ?? $requestBody['method_override'] ?? $requestBody['_method'] ?? '')));

        if (in_array($action, ['update', 'put'], true)) {
            $effectiveMethod = 'PUT';
        } elseif (in_array($action, ['delete', 'remove'], true)) {
            $effectiveMethod = 'DELETE';
        } else {
            $effectiveMethod = 'POST';
        }
    }
}

try {
    $columns = $pdo
        ->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'downloads'")
        ->fetchAll(PDO::FETCH_COLUMN);

    $columnMap = array_fill_keys($columns, true);

    $hasStatusColumn = isset($columnMap['status']);
        if (!$hasStatusColumn) {
            json_response(500, [
                'success' => false,
                'error' => 'Missing downloads.status column'
            ]);
        }

    $hasButtonTextColumn = isset($columnMap['button_text']);
    $hasDisplayOrderColumn = isset($columnMap['display_order']);
    $hasCreatedAtColumn = isset($columnMap['created_at']);
    $hasUpdatedAtColumn = isset($columnMap['updated_at']);

    $statusSelect = get_status_select_expr($hasStatusColumn);
    $buttonTextSelect = $hasButtonTextColumn ? 'd.button_text' : "'ดาวน์โหลดเอกสาร' AS button_text";
    $displayOrderSelect = $hasDisplayOrderColumn ? 'd.display_order' : 'd.id AS display_order';
    $createdAtSelect = $hasCreatedAtColumn ? 'd.created_at' : 'NULL AS created_at';
    $updatedAtSelect = $hasUpdatedAtColumn ? 'd.updated_at' : 'NULL AS updated_at';

    if ($effectiveMethod === 'GET') {
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

    if ($effectiveMethod === 'POST') {
        $input = $requestBody;
        $payload = apply_download_button_text_fallback(normalize_download_payload($input, false));
        $validationError = validate_download_payload($payload, false);

        if ($validationError !== null) {
            json_response(400, [
                'success' => false,
                'error' => $validationError
            ]);
        }

        $columns = ['title', 'description', 'category', 'file_url', 'status'];
        $values = [':title', ':description', ':category', ':file_url', ':status'];
        $params = [
            ':title' => $payload['title'],
            ':description' => $payload['description'],
            ':category' => $payload['category'],
            ':file_url' => $payload['file_url'],
            ':status' => $payload['status'],
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

    if ($effectiveMethod === 'PUT') {
        $input = $requestBody;
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);

        if ($id === false || $id === null) {
            json_response(400, [
                'success' => false,
                'error' => 'Invalid id'
            ]);
        }

        $payload = apply_download_button_text_fallback(normalize_download_payload($input, true));
        $validationError = validate_download_payload($payload, true);

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
            'status = :status',
        ];
        $params = [
            ':id' => (int) $id,
            ':title' => $payload['title'],
            ':description' => $payload['description'],
            ':category' => $payload['category'],
            ':file_url' => $payload['file_url'],
            ':status' => $payload['status'],
        ];

        if ($hasButtonTextColumn) {
            $setClauses[] = 'button_text = :button_text';
            $params[':button_text'] = $payload['button_text'];
        }

        if ($hasDisplayOrderColumn) {
            $setClauses[] = 'display_order = :display_order';
            $params[':display_order'] = (int) $payload['display_order'];
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

    $input = $requestBody;
    $idFromQuery = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $idFromBody = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    $id = $idFromQuery !== null && $idFromQuery !== false ? $idFromQuery : $idFromBody;

    if ($id === false || $id === null) {
        json_response(400, [
            'success' => false,
            'error' => 'Invalid id'
        ]);
    }

    $filePathStmt = $pdo->prepare('SELECT file_url FROM downloads WHERE id = :id LIMIT 1');
    $filePathStmt->execute([':id' => (int) $id]);
    $existingDownload = $filePathStmt->fetch();

    if (!$existingDownload) {
        json_response(404, [
            'success' => false,
            'error' => 'Download not found'
        ]);
    }

    $deletedFilePath = (string) ($existingDownload['file_url'] ?? '');

    $deleteStmt = $pdo->prepare('DELETE FROM downloads WHERE id = :id');
    $deleteStmt->execute([
        ':id' => (int) $id,
    ]);

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

    cleanup_replaced_download_file($pdo, (int) $id, $deletedFilePath, '');

    json_response(200, [
        'success' => true
    ]);
} catch (Throwable $e) {
    json_response(500, [
        'success' => false,
        'error' => 'Failed to manage downloads'
    ]);
}
