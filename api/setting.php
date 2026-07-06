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

const SITE_SETTING_DEFAULTS = [
	'personnel_count' => '85',
	'student_count' => '196',
	'classroom_count' => '29',
	'academic_year' => '2569',
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
	foreach (SITE_SETTING_DEFAULTS as $key => $defaultValue) {
		$value = array_key_exists($key, $allSettings) ? trim((string) $allSettings[$key]) : '';
		$stats[$key] = $value !== '' ? $value : $defaultValue;
	}

	echo json_encode([
		'success' => true,
		'message' => 'Settings loaded successfully',
		'data' => [
			'personnel_count' => (int) $stats['personnel_count'],
			'student_count' => (int) $stats['student_count'],
			'classroom_count' => (int) $stats['classroom_count'],
			'academic_year' => (string) $stats['academic_year'],
		],
	], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'message' => 'Failed to load settings'
	], JSON_UNESCAPED_UNICODE);
}
