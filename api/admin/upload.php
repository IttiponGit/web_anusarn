<?php

header('Content-Type: application/json; charset=utf-8');

session_start();

$admin = $_SESSION['admin_user'] ?? null;
if (!$admin) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
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

function normalize_related_type(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $allowed = ['news', 'downloads'];
    return in_array($value, $allowed, true) ? $value : null;
}

function resolve_upload_error_message(int $errorCode): string
{
    if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
        return 'File size exceeds server limit';
    }

    if ($errorCode === UPLOAD_ERR_PARTIAL) {
        return 'File was uploaded partially';
    }

    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return 'No file uploaded';
    }

    if ($errorCode === UPLOAD_ERR_NO_TMP_DIR) {
        return 'Missing temporary upload directory';
    }

    if ($errorCode === UPLOAD_ERR_CANT_WRITE) {
        return 'Cannot write file to disk';
    }

    if ($errorCode === UPLOAD_ERR_EXTENSION) {
        return 'File upload blocked by extension';
    }

    return 'Unknown upload error';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    json_response(405, [
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    json_response(400, [
        'success' => false,
        'message' => 'No file uploaded'
    ]);
}

$file = $_FILES['file'];
$errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($errorCode !== UPLOAD_ERR_OK) {
    json_response(400, [
        'success' => false,
        'message' => resolve_upload_error_message($errorCode)
    ]);
}

$maxImageSize = 5 * 1024 * 1024;
$maxDocumentSize = 12 * 1024 * 1024;

$allowedMimeByExt = [
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
    'webp' => ['image/webp'],
    'gif' => ['image/gif'],
    'pdf' => ['application/pdf'],
    'doc' => ['application/msword', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
];

$imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$documentExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

$originalName = (string) ($file['name'] ?? '');
$size = (int) ($file['size'] ?? 0);
$tmpName = (string) ($file['tmp_name'] ?? '');

if ($originalName === '' || $size <= 0 || $tmpName === '') {
    json_response(400, [
        'success' => false,
        'message' => 'Invalid upload file'
    ]);
}

$pathInfo = pathinfo($originalName);
$extension = strtolower((string) ($pathInfo['extension'] ?? ''));

if ($extension === '' || !isset($allowedMimeByExt[$extension])) {
    json_response(400, [
        'success' => false,
        'message' => 'File extension is not allowed'
    ]);
}

$isImage = in_array($extension, $imageExtensions, true);
$isDocument = in_array($extension, $documentExtensions, true);

if (!$isImage && !$isDocument) {
    json_response(400, [
        'success' => false,
        'message' => 'Unsupported file type'
    ]);
}

$maxSize = $isImage ? $maxImageSize : $maxDocumentSize;
if ($size > $maxSize) {
    json_response(400, [
        'success' => false,
        'message' => $isImage ? 'Image file is too large (max 5MB)' : 'Document file is too large (max 12MB)'
    ]);
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detectedMime = $finfo ? finfo_file($finfo, $tmpName) : false;
if ($finfo) {
    finfo_close($finfo);
}

if (!is_string($detectedMime) || $detectedMime === '') {
    json_response(400, [
        'success' => false,
        'message' => 'Cannot detect file MIME type'
    ]);
}

$allowedMimes = $allowedMimeByExt[$extension];
if (!in_array($detectedMime, $allowedMimes, true)) {
    json_response(400, [
        'success' => false,
        'message' => 'Invalid MIME type for selected extension'
    ]);
}

if ($isImage) {
    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false) {
        json_response(400, [
            'success' => false,
            'message' => 'Uploaded file is not a valid image'
        ]);
    }
}

$category = trim((string) ($_POST['category'] ?? ''));
$relatedType = normalize_related_type((string) ($_POST['related_type'] ?? ''));
$relatedId = filter_var($_POST['related_id'] ?? null, FILTER_VALIDATE_INT);
$relatedType = $relatedType ?? '';
$relatedId = ($relatedId !== false && $relatedId !== null && $relatedId > 0) ? (int) $relatedId : 0;

$targetSubdir = 'uploads/files';
if ($isImage) {
    $targetSubdir = $relatedType === 'news' || $category === 'news_image' ? 'uploads/news' : 'uploads/images';
} elseif ($relatedType === 'downloads' || $category === 'download_file') {
    $targetSubdir = 'uploads/documents';
}

$projectRoot = dirname(__DIR__, 2);
$targetDir = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetSubdir);

if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
    json_response(500, [
        'success' => false,
        'message' => 'Cannot create upload directory'
    ]);
}

if (!is_writable($targetDir)) {
    json_response(500, [
        'success' => false,
        'message' => 'Upload directory is not writable'
    ]);
}

$safeOriginalName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $originalName);
$safeOriginalName = trim((string) $safeOriginalName, '._');
if ($safeOriginalName === '') {
    $safeOriginalName = 'file.' . $extension;
}

$storedName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
$targetPath = $targetDir . DIRECTORY_SEPARATOR . $storedName;

if (!move_uploaded_file($tmpName, $targetPath)) {
    json_response(500, [
        'success' => false,
        'message' => 'Failed to move uploaded file'
    ]);
}

$filePath = str_replace('\\', '/', $targetSubdir . '/' . $storedName);
$fileType = $isImage ? 'image' : 'document';

try {
    $stmt = $pdo->prepare(
        'INSERT INTO uploads (
            original_name,
            stored_name,
            file_path,
            file_type,
            mime_type,
            file_size,
            related_type,
            related_id
        ) VALUES (
            :original_name,
            :stored_name,
            :file_path,
            :file_type,
            :mime_type,
            :file_size,
            :related_type,
            :related_id
        )'
    );

    $stmt->execute([
        ':original_name' => $safeOriginalName,
        ':stored_name' => $storedName,
        ':file_path' => $filePath,
        ':file_type' => $fileType,
        ':mime_type' => $detectedMime,
        ':file_size' => $size,
        ':related_type' => $relatedType,
        ':related_id' => $relatedId,
    ]);

    json_response(201, [
        'success' => true,
        'message' => 'File uploaded successfully',
        'upload_id' => (int) $pdo->lastInsertId(),
        'file_path' => $filePath,
        'file_name' => $storedName,
        'original_name' => $safeOriginalName,
        'mime_type' => $detectedMime,
        'file_size' => $size,
        'file_type' => $fileType,
    ]);
} catch (Throwable $e) {
    if (is_file($targetPath)) {
        @unlink($targetPath);
    }

    json_response(500, [
        'success' => false,
        'message' => 'Failed to save upload metadata'
    ]);
}
