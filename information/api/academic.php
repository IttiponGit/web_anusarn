<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function academic_public_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function academic_legacy_data(): array
{
    $path = __DIR__ . '/../data/academic.json';
    $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    return is_array($decoded) ? $decoded : [];
}

function academic_table_exists(PDO $pdo): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name');
    $stmt->execute([':table_name' => 'academic_items']);
    return (int) $stmt->fetchColumn() > 0;
}

function academic_empty_result(string $year): array
{
    return [
        'academicYear' => $year,
        'curriculum' => [], 'learningAreas' => [], 'curriculumActivities' => [],
        'subjectsByLevel' => [], 'achievementPrimary' => [], 'achievementSecondary' => [],
        'onetSubjects' => [], 'onetByLevel' => [], 'ntDomains' => [], 'ntTrend' => [],
        'ntCompare2568' => [], 'onet' => [], 'nt' => [], 'graduation' => [],
    ];
}

try {
    $configFile = __DIR__ . '/../../api/config.php';
    if (is_file($configFile)) {
        require $configFile;
    } else {
        $host = '127.0.0.1'; $port = 3306; $dbname = 'anu_sarn_db'; $username = 'root'; $password = '';
    }
    $dsn = 'mysql:host=' . $host . (!empty($port) ? ';port=' . $port : '') . ';dbname=' . $dbname . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 3,
    ]);

    if (!academic_table_exists($pdo)) {
        academic_public_response(200, ['success' => true, 'data' => academic_legacy_data(), 'source' => 'legacy']);
    }

    $count = (int) $pdo->query("SELECT COUNT(*) FROM academic_items WHERE status = 'published'")->fetchColumn();
    if ($count === 0) {
        academic_public_response(200, ['success' => true, 'data' => academic_legacy_data(), 'source' => 'legacy']);
    }

    $requestedYear = trim((string) ($_GET['year'] ?? ''));
    if ($requestedYear !== '' && !preg_match('/^\d{4}$/', $requestedYear)) {
        academic_public_response(422, ['success' => false, 'error' => 'ปีการศึกษาไม่ถูกต้อง']);
    }

    if ($requestedYear === '') {
        $yearStmt = $pdo->query("SELECT MAX(academic_year) FROM academic_items WHERE status = 'published'");
        $requestedYear = (string) ($yearStmt->fetchColumn() ?: date('Y'));
    }

    $stmt = $pdo->prepare(
        "SELECT id, title, category, academic_year, details, display_order
         FROM academic_items
         WHERE status = 'published' AND (academic_year = :year OR category IN ('onet', 'nt'))
         ORDER BY category, academic_year, display_order, id"
    );
    $stmt->execute([':year' => $requestedYear]);
    $result = academic_empty_result($requestedYear);
    $onetSubjects = [];
    $ntDomains = [];

    foreach ($stmt->fetchAll() as $row) {
        $details = json_decode((string) $row['details'], true);
        $details = is_array($details) ? $details : [];
        $title = (string) $row['title'];
        $category = (string) $row['category'];
        $year = (string) $row['academic_year'];

        if ($category === 'curriculum') {
            $result['curriculum'][] = ['name' => $title, 'level' => (string) ($details['level'] ?? ''), 'summary' => (string) ($details['description'] ?? '')];
        } elseif ($category === 'learning_area') {
            $result['learningAreas'][] = $title;
        } elseif ($category === 'curriculum_activity') {
            $result['curriculumActivities'][] = $title;
        } elseif ($category === 'subject_by_level') {
            $result['subjectsByLevel'][] = ['level' => (string) ($details['level'] ?? $title), 'items' => array_values((array) ($details['items'] ?? []))];
        } elseif ($category === 'achievement_primary' || $category === 'achievement_secondary') {
            $target = $category === 'achievement_primary' ? 'achievementPrimary' : 'achievementSecondary';
            $result[$target][] = ['grade' => (string) ($details['level'] ?? $title), 'average' => (float) ($details['value'] ?? 0)];
        } elseif ($category === 'graduation') {
            $result['graduation'][] = ['level' => (string) ($details['level'] ?? $title), 'graduates' => (int) ($details['value'] ?? 0), 'percent' => (float) ($details['value2'] ?? 0)];
        } elseif ($category === 'onet') {
            $levelKey = (string) ($details['level_key'] ?? '');
            $subjectKey = (string) ($details['key'] ?? '');
            if ($levelKey === '' || $subjectKey === '') continue;
            $levelLabel = (string) ($details['level'] ?? $levelKey);
            $shortLabel = (string) ($details['short_label'] ?? $levelLabel);
            $onetSubjects[$subjectKey] = ['key' => $subjectKey, 'label' => $title];
            if (!isset($result['onetByLevel'][$levelKey])) {
                $result['onetByLevel'][$levelKey] = ['label' => $levelLabel, 'shortLabel' => $shortLabel, 'years' => []];
            }
            $result['onetByLevel'][$levelKey]['years'][$year][$subjectKey] = (float) ($details['value'] ?? 0);
        } elseif ($category === 'nt') {
            $scope = (string) ($details['scope'] ?? 'trend');
            $key = (string) ($details['key'] ?? '');
            if ($key === '') continue;
            if ($scope === 'trend') {
                $ntDomains[$key] = ['key' => $key, 'label' => $title];
                $result['ntTrend'][$year][$key] = (float) ($details['value'] ?? 0);
            } elseif ($scope === 'comparison') {
                $entity = (string) ($details['entity_key'] ?? 'school');
                if (!isset($result['ntCompare2568'][$entity])) {
                    $result['ntCompare2568'][$entity] = ['label' => (string) ($details['level'] ?? $entity)];
                }
                $result['ntCompare2568'][$entity][$key] = (float) ($details['value'] ?? 0);
            }
        }
    }

    $result['onetSubjects'] = array_values($onetSubjects);
    $result['ntDomains'] = array_values($ntDomains);
    foreach ($result['onetByLevel'] as $level) {
        $scores = $level['years'][$requestedYear] ?? [];
        if ($scores) $result['onet'][] = ['level' => $level['shortLabel']] + $scores;
    }
    if (isset($result['ntTrend'][$requestedYear])) {
        $result['nt'][] = ['level' => 'ป.3'] + $result['ntTrend'][$requestedYear];
    }

    academic_public_response(200, ['success' => true, 'data' => $result, 'source' => 'database']);
} catch (Throwable $error) {
    // The public page must remain available during database maintenance.
    academic_public_response(200, ['success' => true, 'data' => academic_legacy_data(), 'source' => 'legacy']);
}
