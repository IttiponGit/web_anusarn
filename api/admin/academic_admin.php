<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../includes/auth.php';

$user = requireAnyRole(['academic']);

const ACADEMIC_CATEGORIES = [
    'curriculum', 'learning_area', 'curriculum_activity', 'subject_by_level',
    'achievement_primary', 'achievement_secondary', 'onet', 'nt', 'graduation',
];

function academic_admin_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function academic_admin_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : [];
}

function academic_csrf_token(): string
{
    startAuthSession();
    if (!isset($_SESSION['academic_csrf']) || !is_string($_SESSION['academic_csrf'])) {
        $_SESSION['academic_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['academic_csrf'];
}

function require_academic_csrf(): void
{
    $provided = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($provided === '' || !hash_equals(academic_csrf_token(), $provided)) {
        academic_admin_response(403, ['success' => false, 'error' => 'CSRF token ไม่ถูกต้อง']);
    }
}

function academic_payload(array $input): array
{
    $items = $input['items'] ?? [];
    if (is_string($items)) $items = preg_split('/\R/u', $items) ?: [];
    $items = array_values(array_filter(array_map(static fn($value) => trim((string) $value), is_array($items) ? $items : []), static fn($value) => $value !== ''));
    return [
        'title' => trim((string) ($input['title'] ?? '')),
        'category' => trim((string) ($input['category'] ?? '')),
        'academic_year' => trim((string) ($input['academic_year'] ?? '')),
        'status' => trim((string) ($input['status'] ?? 'draft')),
        'display_order' => filter_var($input['display_order'] ?? 0, FILTER_VALIDATE_INT),
        'details' => [
            'level' => trim((string) ($input['level'] ?? '')),
            'description' => trim((string) ($input['description'] ?? '')),
            'items' => $items,
            'key' => trim((string) ($input['key'] ?? '')),
            'level_key' => trim((string) ($input['level_key'] ?? '')),
            'short_label' => trim((string) ($input['short_label'] ?? '')),
            'scope' => trim((string) ($input['scope'] ?? '')),
            'entity_key' => trim((string) ($input['entity_key'] ?? '')),
            'value' => $input['value'] ?? null,
            'value2' => $input['value2'] ?? null,
        ],
    ];
}

function academic_validation(array &$payload): ?string
{
    if ($payload['title'] === '' || mb_strlen($payload['title']) > 255) return 'กรุณาระบุหัวข้อไม่เกิน 255 ตัวอักษร';
    if (!in_array($payload['category'], ACADEMIC_CATEGORIES, true)) return 'หมวดหมู่ไม่ถูกต้อง';
    if (!preg_match('/^\d{4}$/', $payload['academic_year'])) return 'ปีการศึกษาต้องเป็นตัวเลข 4 หลัก';
    if (!in_array($payload['status'], ['published', 'draft'], true)) return 'สถานะไม่ถูกต้อง';
    if ($payload['display_order'] === false || $payload['display_order'] < 0 || $payload['display_order'] > 99999) return 'ลำดับต้องอยู่ระหว่าง 0 ถึง 99999';
    if (mb_strlen($payload['details']['level']) > 150 || mb_strlen($payload['details']['description']) > 5000) return 'ระดับหรือรายละเอียดยาวเกินกำหนด';
    if (count($payload['details']['items']) > 100) return 'รายการย่อยต้องไม่เกิน 100 รายการ';
    foreach (['value', 'value2'] as $key) {
        $value = $payload['details'][$key];
        if ($value === '' || $value === null) { $payload['details'][$key] = null; continue; }
        if (!is_numeric($value) || (float) $value < 0 || (float) $value > 1000000) return 'ค่าตัวเลขไม่ถูกต้อง';
        $payload['details'][$key] = (float) $value;
    }
    if ($payload['category'] === 'onet' && ($payload['details']['level_key'] === '' || $payload['details']['key'] === '' || $payload['details']['value'] === null)) return 'O-NET ต้องระบุรหัสระดับ รหัสวิชา และคะแนน';
    if ($payload['category'] === 'nt' && ($payload['details']['key'] === '' || $payload['details']['value'] === null)) return 'NT ต้องระบุรหัสด้านและคะแนน';
    return null;
}

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) academic_admin_response(405, ['success' => false, 'error' => 'Method not allowed']);

try {
    if ($method === 'GET') {
        $where = [];
        $params = [];
        foreach (['category', 'academic_year', 'status'] as $field) {
            $value = trim((string) ($_GET[$field] ?? ''));
            if ($value !== '') { $where[] = "{$field} = :{$field}"; $params[":{$field}"] = $value; }
        }
        $search = trim((string) ($_GET['search'] ?? ''));
        if ($search !== '') { $where[] = '(title LIKE :search OR details LIKE :search)'; $params[':search'] = '%' . $search . '%'; }
        $sql = 'SELECT id, title, category, academic_year, details, status, display_order, created_at, updated_at FROM academic_items';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY category, academic_year DESC, display_order, id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        foreach ($items as &$item) $item['details'] = json_decode((string) $item['details'], true) ?: [];
        unset($item);
        academic_admin_response(200, ['success' => true, 'data' => $items, 'user' => $user, 'csrf_token' => academic_csrf_token()]);
    }

    require_academic_csrf();
    $input = academic_admin_body();
    if ($method === 'POST' || $method === 'PUT') {
        $payload = academic_payload($input);
        $error = academic_validation($payload);
        if ($error !== null) academic_admin_response(422, ['success' => false, 'error' => $error]);
        $details = json_encode($payload['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($method === 'POST') {
            $stmt = $pdo->prepare('INSERT INTO academic_items (title, category, academic_year, details, status, display_order, created_by, updated_by) VALUES (:title, :category, :year, :details, :status, :sort, :user_id, :user_id)');
            $stmt->execute([':title'=>$payload['title'], ':category'=>$payload['category'], ':year'=>$payload['academic_year'], ':details'=>$details, ':status'=>$payload['status'], ':sort'=>$payload['display_order'], ':user_id'=>$user['id']]);
            academic_admin_response(201, ['success' => true, 'data' => ['id' => (int) $pdo->lastInsertId()]]);
        }
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id === null || $id < 1) academic_admin_response(422, ['success' => false, 'error' => 'รหัสรายการไม่ถูกต้อง']);
        $stmt = $pdo->prepare('UPDATE academic_items SET title=:title, category=:category, academic_year=:year, details=:details, status=:status, display_order=:sort, updated_by=:user_id WHERE id=:id');
        $stmt->execute([':title'=>$payload['title'], ':category'=>$payload['category'], ':year'=>$payload['academic_year'], ':details'=>$details, ':status'=>$payload['status'], ':sort'=>$payload['display_order'], ':user_id'=>$user['id'], ':id'=>$id]);
        academic_admin_response(200, ['success' => true]);
    }
    $id = filter_var($input['id'] ?? ($_GET['id'] ?? null), FILTER_VALIDATE_INT);
    if ($id === false || $id === null || $id < 1) academic_admin_response(422, ['success' => false, 'error' => 'รหัสรายการไม่ถูกต้อง']);
    $stmt = $pdo->prepare('DELETE FROM academic_items WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) academic_admin_response(404, ['success' => false, 'error' => 'ไม่พบรายการ']);
    academic_admin_response(200, ['success' => true]);
} catch (Throwable $error) {
    academic_admin_response(500, ['success' => false, 'error' => 'ไม่สามารถจัดการข้อมูลวิชาการได้']);
}
