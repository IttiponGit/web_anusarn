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
];

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

	echo json_encode([
		'success' => true,
		'message' => 'Settings loaded successfully',
		'data' => [
			'personnel_count' => is_numeric($stats['personnel_count']) ? (int) $stats['personnel_count'] : null,
			'student_count' => is_numeric($stats['student_count']) ? (int) $stats['student_count'] : null,
			'classroom_count' => is_numeric($stats['classroom_count']) ? (int) $stats['classroom_count'] : null,
			'academic_year' => $stats['academic_year'] !== null ? (string) $stats['academic_year'] : null,
		],
	], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'message' => 'Failed to load settings'
	], JSON_UNESCAPED_UNICODE);
}
