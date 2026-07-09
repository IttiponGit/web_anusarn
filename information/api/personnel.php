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

require_once __DIR__ . '/../../api/db.php';

const INFO_PERSONNEL_PAGE_YEAR = 2569;

function info_personnel_columns(PDO $pdo, string $table): array
{
	$stmt = $pdo->prepare(
		"SELECT COLUMN_NAME
		 FROM information_schema.COLUMNS
		 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name"
	);
	$stmt->execute([':table_name' => $table]);

	$columns = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$columns[(string) $row['COLUMN_NAME']] = true;
	}

	return $columns;
}

function info_personnel_pick(array $row, array $keys, $default = ''): string
{
	foreach ($keys as $key) {
		if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') {
			return trim((string) $row[$key]);
		}
	}

	return (string) $default;
}

function info_personnel_pick_int(array $row, array $keys): int
{
	$value = info_personnel_pick($row, $keys, '0');
	return is_numeric($value) ? (int) $value : 0;
}

function info_personnel_pick_float(array $row, array $keys, ?float $fallback = null): ?float
{
	$value = info_personnel_pick($row, $keys, '');
	$value = str_replace('%', '', $value);
	return is_numeric($value) ? (float) $value : $fallback;
}

function info_personnel_percent_display(array $row, float $percent): string
{
	$display = info_personnel_pick($row, ['percent_display']);
	if ($display !== '') {
		return strpos($display, '%') !== false ? $display : $display . '%';
	}

	return number_format($percent, 2, '.', '') . '%';
}

function info_personnel_calculated_percent(array $row, int $count, string $sectionKey = ''): float
{
	$denominatorKeys = $sectionKey === 'teacher_exam_passed'
		? ['denominator_value', 'total_count']
		: ['total_count', 'denominator_value'];
	$denominator = info_personnel_pick_float($row, $denominatorKeys, null);

	if ($denominator !== null && $denominator > 0) {
		return round(($count / $denominator) * 100, 2);
	}

	return info_personnel_pick_float($row, ['percent_calculated', 'percent', 'percentage', 'ratio'], 0) ?? 0;
}

function info_personnel_order_clause(array $columns): string
{
	$orderColumns = [];
	foreach (['display_order', 'sort_order', 'section_order', 'item_order', 'id'] as $column) {
		if (isset($columns[$column])) {
			$orderColumns[] = "`{$column}` ASC";
		}
	}

	return $orderColumns ? ' ORDER BY ' . implode(', ', $orderColumns) : '';
}

function info_personnel_fetch_rows(PDO $pdo, string $table, array $filters = []): array
{
	$columns = info_personnel_columns($pdo, $table);
	if (!$columns) {
		throw new RuntimeException("Missing table or view: {$table}");
	}

	$where = [];
	$params = [];

	foreach ($filters as $column => $value) {
		if (isset($columns[$column])) {
			$where[] = "`{$column}` = :{$column}";
			$params[":{$column}"] = $value;
		}
	}

	$sql = "SELECT * FROM `{$table}`";
	if ($where) {
		$sql .= ' WHERE ' . implode(' AND ', $where);
	}
	$sql .= info_personnel_order_clause($columns);

	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);

	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function info_personnel_fetch_executive_history(PDO $pdo): array
{
	$stmt = $pdo->query(
		'SELECT info_personnel_executive_history_id, display_order, full_name, term_period_text, duration_text, is_current
		 FROM info_personnel_executive_history
		 WHERE is_active = 1
		 ORDER BY display_order'
	);

	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function info_personnel_fetch_position_summary(PDO $pdo, int $pageYear): array
{
	$stmt = $pdo->prepare(
		'SELECT
		     position_name,
		     total_count,
		     male_count,
		     female_count,
		     total_personnel,
		     percent_calculated
		 FROM vw_info_personnel_position_summary
		 WHERE page_year = :page_year
		 ORDER BY display_order'
	);
	$stmt->execute([':page_year' => $pageYear]);

	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function info_personnel_fetch_admin_current(PDO $pdo, int $pageYear): array
{
	$stmt = $pdo->prepare(
		'SELECT
		     info_personnel_admin_current_id,
		     full_name,
		     role_title,
		     phone,
		     email,
		     highest_education,
		     start_work_text,
		     responsibility,
		     image
		 FROM info_personnel_admin_current
		 WHERE page_year = :page_year
		   AND is_active = 1
		 ORDER BY display_order'
	);
	$stmt->execute([':page_year' => $pageYear]);

	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function info_personnel_normalize_kpi(array $row): array
{
	return [
		'key' => info_personnel_pick($row, ['kpi_key', 'metric_key', 'item_key', 'slug']),
		'label' => info_personnel_pick($row, ['kpi_label', 'label', 'title', 'name', 'metric_name'], 'ไม่มีข้อมูล'),
		'value' => info_personnel_pick_int($row, ['kpi_value', 'value', 'count_value', 'total', 'count']),
		'unit' => info_personnel_pick($row, ['unit', 'kpi_unit'], 'คน'),
		'note' => info_personnel_pick($row, ['note', 'description', 'summary']),
	];
}

function info_personnel_normalize_stat(array $row): array
{
	$count = info_personnel_pick_int($row, ['count_value', 'total', 'count', 'value', 'personnel_count']);
	$sectionKey = info_personnel_pick($row, ['section_key']);
	$percent = info_personnel_calculated_percent($row, $count, $sectionKey);
	$denominator = $sectionKey === 'teacher_exam_passed'
		? info_personnel_pick_float($row, ['denominator_value', 'total_count'], null)
		: info_personnel_pick_float($row, ['total_count', 'denominator_value'], null);

	return [
		'sectionKey' => $sectionKey,
		'label' => info_personnel_pick($row, ['item_label', 'label', 'name', 'title', 'position', 'position_name', 'category'], 'ไม่มีข้อมูล'),
		'count' => $count,
		'totalCount' => $denominator !== null ? (int) $denominator : null,
		'percent' => $percent,
		'percentDisplay' => info_personnel_percent_display($row, $percent),
		'note' => info_personnel_pick($row, ['note', 'description', 'summary']),
	];
}

function info_personnel_normalize_position(array $row): array
{
	$count = info_personnel_pick_int($row, ['total_count']);
	$maleCount = info_personnel_pick_int($row, ['male_count']);
	$femaleCount = info_personnel_pick_int($row, ['female_count']);
	$totalPersonnel = info_personnel_pick_int($row, ['total_personnel']);
	$percent = info_personnel_pick_float($row, ['percent_calculated'], 0) ?? 0;

	return [
		'position' => info_personnel_pick($row, ['position_name'], 'ไม่มีข้อมูล'),
		'count' => $count,
		'maleCount' => $maleCount,
		'femaleCount' => $femaleCount,
		'totalPersonnel' => $totalPersonnel,
		'percentCalculated' => $percent,
		'percent' => $percent,
		'percentDisplay' => info_personnel_percent_display($row, $percent),
		'note' => '',
	];
}

function info_personnel_normalize_admin(array $row): array
{
	return [
		'id' => info_personnel_pick_int($row, ['info_personnel_admin_current_id']),
		'fullName' => info_personnel_pick($row, ['full_name'], 'ไม่มีข้อมูล'),
		'roleTitle' => info_personnel_pick($row, ['role_title'], 'ไม่มีข้อมูล'),
		'phone' => info_personnel_pick($row, ['phone']),
		'email' => info_personnel_pick($row, ['email']),
		'highestEducation' => info_personnel_pick($row, ['highest_education'], 'ไม่มีข้อมูล'),
		'startWorkText' => info_personnel_pick($row, ['start_work_text'], 'ไม่มีข้อมูล'),
		'responsibility' => info_personnel_pick($row, ['responsibility'], 'ไม่มีข้อมูล'),
		'image' => info_personnel_pick($row, ['image']),
	];
}

function info_personnel_normalize_history(array $row): array
{
	return [
		'id' => info_personnel_pick_int($row, ['info_personnel_executive_history_id', 'id']),
		'name' => info_personnel_pick($row, ['full_name', 'name', 'person_name'], 'ไม่มีข้อมูล'),
		'position' => 'ผู้บริหารสถานศึกษา',
		'termPeriodText' => info_personnel_pick($row, ['term_period_text'], 'ไม่มีข้อมูล'),
		'durationText' => info_personnel_pick($row, ['duration_text']),
		'isCurrent' => info_personnel_pick_int($row, ['is_current']) === 1,
	];
}

function info_personnel_map_section(array $items, string $labelKey): array
{
	return array_map(static function (array $item) use ($labelKey): array {
		return [
			$labelKey => $item['label'],
			'count' => $item['count'],
			'percent' => $item['percent'],
			'note' => $item['note'],
		];
	}, $items);
}

function info_personnel_cards_from_stats(array $items, string $titlePrefix): array
{
	return array_map(static function (array $item) use ($titlePrefix): array {
		return [
			'year' => (string) INFO_PERSONNEL_PAGE_YEAR,
			'title' => $item['label'],
			'provider' => $titlePrefix,
			'participants' => $item['count'],
			'summary' => $item['note'],
			'recipient' => $item['label'],
			'organization' => $titlePrefix,
		];
	}, $items);
}

function info_personnel_position_stats(array $items): array
{
	return array_map(static function (array $item): array {
		return [
			'position' => $item['label'] ?? 'ไม่มีข้อมูล',
			'count' => $item['count'] ?? 0,
			'percent' => $item['percent'] ?? 0,
			'percentDisplay' => $item['percentDisplay'] ?? number_format((float) ($item['percent'] ?? 0), 2, '.', '') . '%',
			'note' => $item['note'] ?? '',
		];
	}, $items);
}

function info_personnel_sum(array $items, string $key): int
{
	return array_reduce($items, static function (int $total, array $item) use ($key): int {
		return $total + (int) ($item[$key] ?? 0);
	}, 0);
}

try {
	$pageYear = isset($_GET['page_year']) && preg_match('/^\d{4}$/', (string) $_GET['page_year'])
		? (int) $_GET['page_year']
		: INFO_PERSONNEL_PAGE_YEAR;

	$kpis = array_map(
		'info_personnel_normalize_kpi',
		info_personnel_fetch_rows($pdo, 'info_personnel_kpi', [
			'page_year' => $pageYear,
			'is_active' => 1,
		])
	);

	$statsRows = info_personnel_fetch_rows($pdo, 'vw_info_personnel_stats', [
		'page_year' => $pageYear,
		'is_active' => 1,
	]);
	$stats = [];
	foreach ($statsRows as $row) {
		$item = info_personnel_normalize_stat($row);
		$sectionKey = $item['sectionKey'];
		if ($sectionKey === '') {
			continue;
		}
		$stats[$sectionKey][] = $item;
	}

	$positionSummary = array_map(
		'info_personnel_normalize_position',
		info_personnel_fetch_position_summary($pdo, $pageYear)
	);

	$executives = array_map(
		'info_personnel_normalize_admin',
		info_personnel_fetch_admin_current($pdo, $pageYear)
	);

	$executiveDirectory = array_map(
		'info_personnel_normalize_history',
		info_personnel_fetch_executive_history($pdo)
	);

	$kpiByKey = [];
	foreach ($kpis as $kpi) {
		if ($kpi['key'] !== '') {
			$kpiByKey[$kpi['key']] = $kpi['value'];
		}
	}

	$byPosition = $positionSummary;
	$testSummary = [
		'kpiTotal' => $kpiByKey['total_personnel'] ?? ($kpis[0]['value'] ?? null),
		'genderTotal' => info_personnel_sum($stats['gender'] ?? [], 'count'),
		'positionMaleTotal' => info_personnel_sum($positionSummary, 'maleCount'),
		'positionFemaleTotal' => info_personnel_sum($positionSummary, 'femaleCount'),
		'positionTotal' => info_personnel_sum($positionSummary, 'count'),
		'administratorTotal' => count($executives),
		'executiveHistoryTotal' => count($executiveDirectory),
	];

	echo json_encode([
		'success' => true,
		'message' => 'Personnel information loaded successfully',
		'data' => [
			'pageYear' => $pageYear,
			'kpis' => $kpis,
			'totalPersonnel' => $kpiByKey['total_personnel'] ?? ($kpis[0]['value'] ?? null),
			'teacherCount' => $kpiByKey['teacher_count'] ?? ($kpis[1]['value'] ?? null),
			'supportCount' => $kpiByKey['support_count'] ?? ($kpis[2]['value'] ?? null),
			'administratorCount' => $kpiByKey['administrator_count'] ?? ($kpis[3]['value'] ?? null),
			'stats' => $stats,
			'byPosition' => $byPosition,
			'positionSummary' => $positionSummary,
			'executives' => $executives,
			'byGender' => info_personnel_map_section($stats['gender'] ?? [], 'gender'),
			'byAgeRange' => info_personnel_map_section($stats['generation'] ?? [], 'range'),
			'byEducation' => info_personnel_map_section($stats['education'] ?? [], 'level'),
			'byAcademicStanding' => info_personnel_map_section($stats['academic_standing'] ?? [], 'standing'),
			'byExperience' => info_personnel_map_section($stats['work_experience'] ?? [], 'range'),
			'byGovernmentServiceYears' => info_personnel_map_section($stats['civil_service_age'] ?? [], 'range'),
			'professionalDevelopment' => info_personnel_cards_from_stats($stats['training_activity'] ?? [], 'กิจกรรมพัฒนาบุคลากร'),
			'personnelAwards' => info_personnel_cards_from_stats($stats['award_agency'] ?? [], 'หน่วยงานรางวัล'),
			'executiveDirectory' => $executiveDirectory,
			'testSummary' => $testSummary,
		],
	], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		'success' => false,
		'message' => 'Failed to load personnel information',
	], JSON_UNESCAPED_UNICODE);
}
