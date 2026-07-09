<?php

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
	http_response_code(405);
	echo json_encode([
		'success' => false,
		'message' => 'Method not allowed'
	], JSON_UNESCAPED_UNICODE);
	exit;
}

require_once __DIR__ . '/db.php';

const SITE_SETTING_KEYS = [
	'personnel_count',
	'student_count',
	'classroom_count',
	'academic_year',
	'level_group_count',
];

function setting_table_exists(PDO $pdo, string $table): bool
{
	$stmt = $pdo->prepare(
		'SELECT COUNT(*)
		 FROM information_schema.TABLES
		 WHERE TABLE_SCHEMA = DATABASE()
		   AND TABLE_NAME = :table_name'
	);
	$stmt->execute([':table_name' => $table]);
	return (int) $stmt->fetchColumn() > 0;
}

function setting_level_group_count(PDO $pdo, ?string $academicYear): int
{
	if (!setting_table_exists($pdo, 'info_students')) {
		return 4;
	}

	$where = 'WHERE is_active = 1';
	$params = [];
	if ($academicYear !== null && preg_match('/^\d{4}$/', $academicYear)) {
		$where .= ' AND academic_year = :academic_year';
		$params[':academic_year'] = $academicYear;
	}

	$stmt = $pdo->prepare(
		"SELECT COUNT(DISTINCT level_group)
		 FROM (
		     SELECT CASE
		         WHEN level_name LIKE 'อนุบาล%' THEN 'kindergarten'
		         WHEN level_name LIKE 'ประถม%' THEN 'primary'
		         WHEN level_name LIKE 'มัธยมศึกษาปีที่ 1%' OR level_name LIKE 'มัธยมศึกษาปีที่ 2%' OR level_name LIKE 'มัธยมศึกษาปีที่ 3%' THEN 'lower_secondary'
		         WHEN level_name LIKE 'มัธยมศึกษาปีที่ 4%' OR level_name LIKE 'มัธยมศึกษาปีที่ 5%' OR level_name LIKE 'มัธยมศึกษาปีที่ 6%' THEN 'upper_secondary'
		         ELSE NULL
		     END AS level_group
		     FROM info_students
		     {$where}
		 ) grouped_levels
		 WHERE level_group IS NOT NULL"
	);
	$stmt->execute($params);
	$count = (int) $stmt->fetchColumn();

	return $count > 0 ? $count : 4;
}

try {
	$stmt = $pdo->query('SELECT setting_key, setting_value FROM site_settings');
	$rows = $stmt->fetchAll();

	$allSettings = [];
	foreach ($rows as $row) {
		$key = isset($row['setting_key']) ? (string) $row['setting_key'] : '';
		if ($key === '') {
			continue;
		}
		$allSettings[$key] = isset($row['setting_value']) ? (string) $row['setting_value'] : '';
	}

	$stats = [];
	foreach (SITE_SETTING_KEYS as $key) {
		$value = array_key_exists($key, $allSettings) ? trim((string) $allSettings[$key]) : '';
		$stats[$key] = $value !== '' ? $value : null;
	}

	$levelGroupCount = is_numeric($stats['level_group_count'])
		? (int) $stats['level_group_count']
		: setting_level_group_count($pdo, $stats['academic_year']);

	echo json_encode([
		'success' => true,
		'message' => 'Settings loaded successfully',
		'data' => [
			'personnel_count' => is_numeric($stats['personnel_count']) ? (int) $stats['personnel_count'] : null,
			'student_count' => is_numeric($stats['student_count']) ? (int) $stats['student_count'] : null,
			'classroom_count' => is_numeric($stats['classroom_count']) ? (int) $stats['classroom_count'] : null,
			'academic_year' => $stats['academic_year'] !== null ? (string) $stats['academic_year'] : null,
			'level_group_count' => $levelGroupCount,
		],
	], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'message' => 'Failed to load settings'
	], JSON_UNESCAPED_UNICODE);
}
