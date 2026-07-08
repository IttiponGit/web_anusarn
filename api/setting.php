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

function setting_value(array $settings, array $keys, $default = '')
{
	foreach ($keys as $key) {
		$value = trim((string)($settings[$key] ?? ''));
		if ($value !== '') {
			return $value;
		}
	}

	return $default;
}

function setting_int(array $settings, array $keys): int
{
	$value = setting_value($settings, $keys, '');
	return is_numeric($value) ? (int)$value : 0;
}

try {
	$stmt = $pdo->query('SELECT setting_key, setting_value FROM site_settings');
	$rows = $stmt->fetchAll();

	$allSettings = [];
	foreach ($rows as $row) {
		$key = isset($row['setting_key']) ? (string)$row['setting_key'] : '';
		if ($key === '') {
			continue;
		}
		$allSettings[$key] = isset($row['setting_value']) ? (string)$row['setting_value'] : '';
	}

	$schoolName = setting_value($allSettings, ['school_name_th', 'school_name']);
	$academicYear = setting_value($allSettings, ['academic_year']);
	$studentCount = setting_int($allSettings, ['student_count']);
	$personnelCount = setting_int($allSettings, ['personnel_count']);
	$classroomCount = setting_int($allSettings, ['classroom_count']);

	echo json_encode([
		'success' => true,
		'message' => 'Settings loaded successfully',
		'data' => [
			'schoolName' => $schoolName,
			'schoolNameTh' => $schoolName,
			'schoolNameEn' => setting_value($allSettings, ['school_name_en']),
			'academicYear' => $academicYear,
			'studentCount' => $studentCount,
			'personnelCount' => $personnelCount,
			'classroomCount' => $classroomCount,
			'levelRange' => setting_value($allSettings, ['level_range']),
			'identity' => setting_value($allSettings, ['identity']),
			'vision' => setting_value($allSettings, ['vision']),
			'contact' => [
				'address' => setting_value($allSettings, ['school_address', 'address']),
				'phone' => setting_value($allSettings, ['phone']),
				'email' => setting_value($allSettings, ['email']),
				'website' => setting_value($allSettings, ['website'])
			],
			'settings' => $allSettings,
			'personnel_count' => $personnelCount,
			'student_count' => $studentCount,
			'classroom_count' => $classroomCount,
			'academic_year' => $academicYear
		],
	], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'message' => 'Failed to load settings'
	], JSON_UNESCAPED_UNICODE);
}
