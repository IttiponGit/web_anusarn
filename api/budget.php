<?php

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
	http_response_code(405);
	echo json_encode([
		'success' => false,
		'message' => 'Method not allowed',
		'data' => null,
	], JSON_UNESCAPED_UNICODE);
	exit;
}

require_once __DIR__ . '/db.php';

const INFO_BUDGET_DEFAULT_YEAR = 2569;
const INFO_BUDGET_TEACHING_PARENT = 'SUBSIDY_TEACHING_MANAGEMENT';

function budget_debug_enabled(): bool
{
	return isset($_GET['debug']) && (string) $_GET['debug'] === '1';
}

function budget_response(bool $success, string $message, $data = null, int $status = 200): void
{
	http_response_code($status);
	echo json_encode([
		'success' => $success,
		'message' => $message,
		'data' => $data,
	], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

function budget_error_data(Throwable $e, array $context = []): ?array
{
	if (!budget_debug_enabled()) {
		return null;
	}

	return [
		'error' => $e->getMessage(),
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'context' => $context,
	];
}

function budget_columns(PDO $pdo, string $view): array
{
	$stmt = $pdo->prepare(
		'SELECT COLUMN_NAME
		 FROM information_schema.COLUMNS
		 WHERE TABLE_SCHEMA = DATABASE()
		   AND TABLE_NAME = :view_name'
	);
	$stmt->execute([':view_name' => $view]);

	$columns = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$columns[(string) $row['COLUMN_NAME']] = true;
	}

	return $columns;
}

function budget_pick(array $row, array $keys, $default = null)
{
	foreach ($keys as $key) {
		if (array_key_exists($key, $row) && $row[$key] !== null && trim((string) $row[$key]) !== '') {
			return $row[$key];
		}
	}

	return $default;
}

function budget_number($value): ?float
{
	if ($value === null || $value === '') {
		return null;
	}

	$normalized = str_replace([',', '%'], '', (string) $value);
	return is_numeric($normalized) ? (float) $normalized : null;
}

function budget_string($value, string $default = ''): string
{
	return $value === null ? $default : trim((string) $value);
}

function budget_order_clause(array $columns): string
{
	$orderColumns = [];
	foreach (['display_order', 'sort_order', 'api_sort_order', 'category_order', 'item_order', 'month_no', 'month_number', 'fiscal_month', 'id'] as $column) {
		if (isset($columns[$column])) {
			$orderColumns[] = "`{$column}` ASC";
		}
	}

	return $orderColumns ? ' ORDER BY ' . implode(', ', $orderColumns) : '';
}

function budget_fetch_rows(PDO $pdo, string $view, int $year, array $filters = []): array
{
	$columns = budget_columns($pdo, $view);
	if (!$columns) {
		throw new RuntimeException("Missing view: {$view}");
	}

	$where = [];
	$params = [];

	foreach (['fiscal_year', 'budget_year', 'year', 'fiscal_year_be', 'page_year'] as $yearColumn) {
		if (isset($columns[$yearColumn])) {
			$where[] = "`{$yearColumn}` = :year";
			$params[':year'] = $year;
			break;
		}
	}

	foreach ($filters as $column => $value) {
		if (isset($columns[$column])) {
			$param = ':' . $column;
			$where[] = "`{$column}` = {$param}";
			$params[$param] = $value;
		}
	}

	$sql = "SELECT * FROM `{$view}`";
	if ($where) {
		$sql .= ' WHERE ' . implode(' AND ', $where);
	}
	$sql .= budget_order_clause($columns);

	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);

	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function budget_normalize_overview(array $rows, int $year): array
{
	$row = $rows[0] ?? [];

	return [
		'fiscalYear' => (string) budget_pick($row, ['fiscal_year', 'budget_year', 'year', 'fiscal_year_be', 'page_year'], $year),
		'totalBudget' => budget_number(budget_pick($row, ['total_budget', 'budget_total', 'total_amount', 'amount'], 0)) ?? 0,
		'categoryCount' => (int) (budget_number(budget_pick($row, ['category_count', 'categories_count', 'total_categories'], 0)) ?? 0),
		'itemCount' => (int) (budget_number(budget_pick($row, ['item_count', 'items_count', 'total_items'], 0)) ?? 0),
		'electricityMonthsRecorded' => (int) (budget_number(budget_pick($row, ['electricity_months_recorded', 'months_recorded', 'recorded_months'], 0)) ?? 0),
		'electricityTotalAmount' => budget_number(budget_pick($row, ['electricity_total_amount', 'electricity_amount', 'utility_total_amount'], 0)) ?? 0,
		'raw' => $row,
	];
}

function budget_normalize_card(array $row): array
{
	return [
		'key' => budget_string(budget_pick($row, ['card_key', 'metric_key', 'api_key', 'key'])),
		'label' => budget_string(budget_pick($row, ['card_label', 'card_title', 'metric_label', 'label', 'title', 'name'], 'ไม่ระบุ')),
		'value' => budget_pick($row, ['card_value', 'card_value_text', 'metric_value', 'value', 'amount', 'count_value']),
		'valueNumber' => budget_number(budget_pick($row, ['card_value_number', 'value_number', 'metric_value_number'], null)),
		'unit' => budget_string(budget_pick($row, ['card_unit', 'unit', 'value_unit', 'suffix'])),
		'note' => budget_string(budget_pick($row, ['note', 'description', 'summary'])),
		'raw' => $row,
	];
}

function budget_normalize_category(array $row): array
{
	$amount = budget_number(budget_pick($row, ['amount', 'total_amount', 'budget_amount', 'allocated_amount', 'plan_amount'], 0)) ?? 0;
	$percent = budget_number(budget_pick($row, ['percent', 'percentage', 'budget_percent', 'amount_percent', 'percent_calculated', 'percent_reported', 'progress_percent'], 0)) ?? 0;

	return [
		'code' => budget_string(budget_pick($row, ['category_code', 'code', 'api_category_code'])),
		'category' => budget_string(budget_pick($row, ['category_name', 'category', 'name', 'label'], 'ไม่ระบุหมวด')),
		'amount' => $amount,
		'percent' => $percent,
		'raw' => $row,
	];
}

function budget_normalize_item(array $row): array
{
	$amount = budget_number(budget_pick($row, ['amount', 'total_amount', 'budget_amount', 'allocated_amount', 'plan_amount'], 0)) ?? 0;
	$percent = budget_number(budget_pick($row, ['percent', 'percentage', 'budget_percent', 'amount_percent', 'percent_calculated', 'percent_reported', 'progress_percent'], 0)) ?? 0;

	return [
		'code' => budget_string(budget_pick($row, ['item_code', 'code', 'api_item_code'])),
		'parentCode' => budget_string(budget_pick($row, ['parent_item_code', 'parent_code'])),
		'level' => budget_string(budget_pick($row, ['api_item_level', 'item_level', 'level'])),
		'category' => budget_string(budget_pick($row, ['category_name', 'category'])),
		'name' => budget_string(budget_pick($row, ['item_name', 'name', 'label', 'title'], 'ไม่ระบุรายการ')),
		'amount' => $amount,
		'percent' => $percent,
		'raw' => $row,
	];
}

function budget_normalize_electricity(array $row): array
{
	$amount = budget_number(budget_pick($row, ['amount', 'total_amount', 'electricity_amount', 'utility_amount'], null));
	$status = budget_string(budget_pick($row, ['status', 'data_status', 'amount_status']));

	return [
		'month' => budget_string(budget_pick($row, ['month_label', 'month_label_th', 'month_name', 'month_text', 'period_label', 'label'], 'ไม่ระบุเดือน')),
		'monthNo' => (int) (budget_number(budget_pick($row, ['month_no', 'month_number', 'fiscal_month', 'fiscal_month_no'], 0)) ?? 0),
		'amount' => $amount,
		'status' => $amount === null ? 'ยังไม่มีข้อมูล' : ($status !== '' ? $status : 'มีข้อมูล'),
		'raw' => $row,
	];
}

function budget_normalize_project(array $row): array
{
	return [
		'code' => budget_string(budget_pick($row, ['project_code', 'code'])),
		'name' => budget_string(budget_pick($row, ['project_name', 'name', 'title'], 'ไม่ระบุโครงการ')),
		'department' => budget_string(budget_pick($row, ['department_name', 'department'])),
		'amount' => budget_number(budget_pick($row, ['amount', 'total_amount', 'funding_amount', 'budget_amount'], 0)) ?? 0,
		'raw' => $row,
	];
}

function budget_normalize_note(array $row): array
{
	return [
		'title' => budget_string(budget_pick($row, ['note_title', 'title', 'label'], 'หมายเหตุ')),
		'note' => budget_string(budget_pick($row, ['note', 'note_text', 'content', 'description', 'detail'])),
		'raw' => $row,
	];
}

function budget_filter_items(array $items, array $filters): array
{
	return array_values(array_filter($items, static function (array $item) use ($filters): bool {
		foreach ($filters as $key => $value) {
			if ((string) ($item[$key] ?? '') !== (string) $value) {
				return false;
			}
		}

		return true;
	}));
}

function budget_try_action(PDO $pdo, string $action, int $year, array &$warnings)
{
	try {
		return budget_fetch_action($pdo, $action, $year);
	} catch (Throwable $e) {
		$warnings[] = [
			'action' => $action,
			'message' => $e->getMessage(),
		];

		return [];
	}
}

function budget_fetch_action(PDO $pdo, string $action, int $year)
{
	switch ($action) {
		case 'overview':
			return budget_normalize_overview(budget_fetch_rows($pdo, 'vw_info_budget_api_dashboard_overview', $year), $year);
		case 'cards':
			return array_map('budget_normalize_card', budget_fetch_rows($pdo, 'vw_info_budget_api_dashboard_cards', $year));
		case 'sources':
			return budget_fetch_rows($pdo, 'vw_info_budget_api_sources', $year);
		case 'categories':
			return array_map('budget_normalize_category', budget_fetch_rows($pdo, 'vw_info_budget_api_categories', $year));
		case 'items':
			return array_map('budget_normalize_item', budget_fetch_rows($pdo, 'vw_info_budget_api_items', $year));
		case 'main_items':
			return budget_filter_items(budget_fetch_action($pdo, 'items', $year), ['level' => 'MAIN']);
		case 'child_items':
			$parent = isset($_GET['parent_item_code']) && $_GET['parent_item_code'] !== ''
				? (string) $_GET['parent_item_code']
				: INFO_BUDGET_TEACHING_PARENT;
			return array_map('budget_normalize_item', budget_fetch_rows($pdo, 'vw_info_budget_api_items', $year, ['parent_item_code' => $parent]));
		case 'teaching_management':
			return array_map('budget_normalize_item', budget_fetch_rows($pdo, 'vw_info_budget_api_items', $year, ['parent_item_code' => INFO_BUDGET_TEACHING_PARENT]));
		case 'electricity':
			return array_map('budget_normalize_electricity', budget_fetch_rows($pdo, 'vw_info_budget_api_electricity_monthly', $year));
		case 'utility_yearly':
			return budget_fetch_rows($pdo, 'vw_info_budget_api_utility_yearly', $year);
		case 'projects':
			return array_map('budget_normalize_project', budget_fetch_rows($pdo, 'vw_info_budget_api_projects', $year));
		case 'actual_sources':
			return budget_fetch_rows($pdo, 'vw_info_budget_api_actual_sources', $year);
		case 'notes':
			return array_map('budget_normalize_note', budget_fetch_rows($pdo, 'vw_info_budget_api_notes', $year));
		default:
			throw new InvalidArgumentException('Unsupported action');
	}
}

try {
	$action = isset($_GET['action']) ? strtolower(trim((string) $_GET['action'])) : 'all';
	$year = isset($_GET['year']) && preg_match('/^\d{4}$/', (string) $_GET['year'])
		? (int) $_GET['year']
		: INFO_BUDGET_DEFAULT_YEAR;

	$allowedActions = [
		'overview',
		'cards',
		'sources',
		'categories',
		'items',
		'main_items',
		'child_items',
		'teaching_management',
		'electricity',
		'utility_yearly',
		'projects',
		'actual_sources',
		'notes',
		'all',
	];

	if (!in_array($action, $allowedActions, true)) {
		budget_response(false, 'Invalid budget action', null, 400);
	}

	if ($action === 'all') {
		$overview = budget_fetch_action($pdo, 'overview', $year);
		$categories = budget_fetch_action($pdo, 'categories', $year);
		$warnings = [];

		$data = [
			'overview' => $overview,
			'cards' => budget_try_action($pdo, 'cards', $year, $warnings),
			'sources' => budget_try_action($pdo, 'sources', $year, $warnings),
			'categories' => $categories,
			'items' => budget_fetch_action($pdo, 'items', $year),
			'mainItems' => budget_fetch_action($pdo, 'main_items', $year),
			'teachingManagement' => budget_fetch_action($pdo, 'teaching_management', $year),
			'electricity' => budget_fetch_action($pdo, 'electricity', $year),
			'utilityYearly' => budget_try_action($pdo, 'utility_yearly', $year, $warnings),
			'projects' => budget_try_action($pdo, 'projects', $year, $warnings),
			'actualSources' => budget_try_action($pdo, 'actual_sources', $year, $warnings),
			'notes' => budget_try_action($pdo, 'notes', $year, $warnings),
		];
		$data['budget'] = [
			'fiscalYear' => $overview['fiscalYear'],
			'total' => $overview['totalBudget'],
			'items' => $categories,
		];
		if ($warnings) {
			$data['warnings'] = $warnings;
		}

		budget_response(true, 'Budget information loaded successfully', $data);
	}

	$data = budget_fetch_action($pdo, $action, $year);
	budget_response(true, 'Budget information loaded successfully', $data);
} catch (InvalidArgumentException $e) {
	budget_response(false, $e->getMessage(), budget_error_data($e, [
		'action' => $_GET['action'] ?? null,
		'year' => $_GET['year'] ?? null,
	]), 400);
} catch (Throwable $e) {
	budget_response(false, 'Failed to load budget information', budget_error_data($e, [
		'action' => $_GET['action'] ?? null,
		'year' => $_GET['year'] ?? null,
	]), 500);
}
