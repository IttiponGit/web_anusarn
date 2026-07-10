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

	$addOrderColumn = static function (string $column) use (&$orderColumns, $columns): void {
		if (isset($columns[$column])) {
			$orderColumns[] = "`{$column}` ASC";
		}
	};

	$hasCategoryOrderContext = isset($columns['category_id'])
		|| isset($columns['category_display_order'])
		|| isset($columns['category_order'])
		|| isset($columns['category_sort_order']);
	$hasItemOrderContext = isset($columns['item_id'])
		|| isset($columns['item_display_order'])
		|| isset($columns['item_order'])
		|| isset($columns['item_sort_order']);

	if ($hasCategoryOrderContext && $hasItemOrderContext) {
		if (isset($columns['category_display_order']) && isset($columns['category_id'])) {
			$orderColumns[] = 'COALESCE(`category_display_order`, `category_id`) ASC';
		} elseif (isset($columns['category_order']) && isset($columns['category_id'])) {
			$orderColumns[] = 'COALESCE(`category_order`, `category_id`) ASC';
		} elseif (isset($columns['category_sort_order']) && isset($columns['category_id'])) {
			$orderColumns[] = 'COALESCE(`category_sort_order`, `category_id`) ASC';
		} else {
			foreach (['category_display_order', 'category_order', 'category_sort_order', 'category_id'] as $column) {
				if (isset($columns[$column])) {
					$orderColumns[] = "`{$column}` ASC";
					break;
				}
			}
		}

		if (isset($columns['item_display_order']) && isset($columns['item_id'])) {
			$orderColumns[] = 'COALESCE(`item_display_order`, `item_id`) ASC';
		} elseif (isset($columns['item_order']) && isset($columns['item_id'])) {
			$orderColumns[] = 'COALESCE(`item_order`, `item_id`) ASC';
		} elseif (isset($columns['item_sort_order']) && isset($columns['item_id'])) {
			$orderColumns[] = 'COALESCE(`item_sort_order`, `item_id`) ASC';
		} else {
			foreach (['item_display_order', 'item_order', 'item_sort_order', 'item_id'] as $column) {
				if (isset($columns[$column])) {
					$orderColumns[] = "`{$column}` ASC";
					break;
				}
			}
		}
	}

	foreach (['display_order', 'sort_order', 'api_sort_order', 'month_no', 'month_number', 'fiscal_month', 'id'] as $column) {
		$addOrderColumn($column);
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

function budget_first_column(array $columns, array $candidates): ?string
{
	foreach ($candidates as $column) {
		if (isset($columns[$column])) {
			return $column;
		}
	}

	return null;
}

function budget_year_column(array $columns): ?string
{
	return budget_first_column($columns, ['fiscal_year', 'budget_year', 'year', 'fiscal_year_be', 'page_year']);
}

function budget_status_filter_sql(array $columns): string
{
	$statusColumn = budget_first_column($columns, ['status', 'data_status', 'payment_status', 'encumbrance_status']);
	if ($statusColumn === null) {
		return '';
	}

	return " AND LOWER(COALESCE(`{$statusColumn}`, '')) NOT IN ('cancelled', 'canceled', 'void', 'inactive')";
}

function budget_sum_table_amount(PDO $pdo, string $table, int $year, array $amountColumns): ?float
{
	$columns = budget_columns($pdo, $table);
	if (!$columns) {
		return null;
	}

	$yearColumn = budget_year_column($columns);
	$amountColumn = budget_first_column($columns, $amountColumns);
	if ($yearColumn === null || $amountColumn === null) {
		return null;
	}

	$sql = "SELECT SUM(COALESCE(`{$amountColumn}`, 0)) AS total_amount FROM `{$table}` WHERE `{$yearColumn}` = :year";
	$sql .= budget_status_filter_sql($columns);
	$stmt = $pdo->prepare($sql);
	$stmt->execute([':year' => $year]);
	$value = $stmt->fetchColumn();

	return budget_number($value) ?? 0.0;
}

function budget_count_table_rows(PDO $pdo, string $table, int $year): ?int
{
	$columns = budget_columns($pdo, $table);
	if (!$columns) {
		return null;
	}

	$yearColumn = budget_year_column($columns);
	if ($yearColumn === null) {
		return null;
	}

	$sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$yearColumn}` = :year";
	$sql .= budget_status_filter_sql($columns);
	$stmt = $pdo->prepare($sql);
	$stmt->execute([':year' => $year]);

	return (int) $stmt->fetchColumn();
}

function budget_fiscal_year_value(PDO $pdo, int $year): string
{
	$columns = budget_columns($pdo, 'info_budget_fiscal_years');
	if (!$columns) {
		return (string) $year;
	}

	$yearColumn = budget_year_column($columns);
	if ($yearColumn === null) {
		return (string) $year;
	}

	$stmt = $pdo->prepare("SELECT `{$yearColumn}` FROM `info_budget_fiscal_years` WHERE `{$yearColumn}` = :year LIMIT 1");
	$stmt->execute([':year' => $year]);
	$value = $stmt->fetchColumn();

	return $value === false || $value === null || $value === '' ? (string) $year : (string) $value;
}

function budget_actual_summary(PDO $pdo, int $year): array
{
	$actualReceipts = budget_sum_table_amount($pdo, 'info_budget_receipts', $year, [
		'received_amount',
		'actual_amount',
		'receipt_amount',
		'amount',
		'total_amount',
	]);
	$actualExpenditures = budget_sum_table_amount($pdo, 'info_budget_expenditures', $year, [
		'paid_amount',
		'actual_amount',
		'expenditure_amount',
		'disbursed_amount',
		'amount',
		'total_amount',
	]);

	$canCalculate = $actualReceipts !== null && $actualExpenditures !== null;
	$actualBalance = $canCalculate ? $actualReceipts - $actualExpenditures : null;

	return [
		'actualReceipts' => $actualReceipts,
		'actual_receipts' => $actualReceipts,
		'actualExpenditures' => $actualExpenditures,
		'actual_expenditures' => $actualExpenditures,
		'actualBalance' => $actualBalance,
		'actual_balance' => $actualBalance,
	];
}

function budget_fetch_actual_summary_view(PDO $pdo, int $year): ?array
{
	$stmt = $pdo->prepare(
		'SELECT fiscal_year, actual_received, actual_paid, actual_balance
		 FROM vw_info_budget_api_actual_summary
		 WHERE fiscal_year = ?
		 LIMIT 1'
	);
	$stmt->execute([$year]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$row) {
		return null;
	}

	return [
		'fiscalYear' => (string) budget_pick($row, ['fiscal_year'], $year),
		'actualReceived' => budget_number(budget_pick($row, ['actual_received'], null)),
		'actualPaid' => budget_number(budget_pick($row, ['actual_paid'], null)),
		'actualBalance' => budget_number(budget_pick($row, ['actual_balance'], null)),
	];
}

function budget_normalize_actual_item(array $row): array
{
	$actualReceived = budget_number(budget_pick($row, ['actual_received', 'actualReceived', 'received_amount'], 0)) ?? 0;
	$actualPaid = budget_number(budget_pick($row, ['actual_paid', 'actualPaid', 'paid_amount'], 0)) ?? 0;
	$actualBalance = budget_number(budget_pick($row, ['actual_balance', 'actualBalance'], null));
	if ($actualBalance === null) {
		$actualBalance = $actualReceived - $actualPaid;
	}
	$expenditurePercent = budget_number(budget_pick($row, ['expenditure_percent', 'expenditurePercent'], null));
	if ($expenditurePercent === null) {
		$expenditurePercent = $actualReceived > 0 ? ($actualPaid / $actualReceived) * 100 : 0;
	}

	return [
		'fiscalYear' => (string) budget_pick($row, ['fiscal_year', 'fiscalYear'], INFO_BUDGET_DEFAULT_YEAR),
		'categoryName' => budget_string(budget_pick($row, ['category_name', 'categoryName', 'category'], 'ไม่ระบุหมวด')),
		'itemName' => budget_string(budget_pick($row, ['item_name', 'itemName', 'name'], 'ไม่ระบุรายการ')),
		'actualReceived' => $actualReceived,
		'actualPaid' => $actualPaid,
		'actualBalance' => $actualBalance,
		'expenditurePercent' => $expenditurePercent,
		'raw' => $row,
	];
}

function budget_fetch_actual_items(PDO $pdo, int $year): array
{
	if (budget_columns($pdo, 'vw_info_budget_api_actual_items')) {
		return array_map('budget_normalize_actual_item', budget_fetch_rows($pdo, 'vw_info_budget_api_actual_items', $year));
	}

	$itemColumns = budget_columns($pdo, 'info_budget_items');
	$categoryColumns = budget_columns($pdo, 'info_budget_categories');
	$receiptColumns = budget_columns($pdo, 'info_budget_receipts');
	$expenditureColumns = budget_columns($pdo, 'info_budget_expenditures');

	$itemId = budget_first_column($itemColumns, ['item_id', 'id']);
	$itemName = budget_first_column($itemColumns, ['item_name', 'name', 'label', 'title']);
	$itemCategoryId = budget_first_column($itemColumns, ['category_id', 'budget_category_id']);
	$categoryId = budget_first_column($categoryColumns, ['category_id', 'id']);
	$categoryName = budget_first_column($categoryColumns, ['category_name', 'name', 'label', 'title']);
	$receiptItemId = budget_first_column($receiptColumns, ['item_id', 'budget_item_id']);
	$receiptYear = budget_year_column($receiptColumns);
	$receiptAmount = budget_first_column($receiptColumns, ['received_amount', 'actual_amount', 'receipt_amount', 'amount', 'total_amount']);
	$expenditureItemId = budget_first_column($expenditureColumns, ['item_id', 'budget_item_id']);
	$expenditureYear = budget_year_column($expenditureColumns);
	$expenditureAmount = budget_first_column($expenditureColumns, ['paid_amount', 'actual_amount', 'expenditure_amount', 'disbursed_amount', 'amount', 'total_amount']);

	if (
		$itemId === null || $itemName === null || $itemCategoryId === null ||
		$categoryId === null || $categoryName === null ||
		$receiptItemId === null || $receiptYear === null || $receiptAmount === null ||
		$expenditureItemId === null || $expenditureYear === null || $expenditureAmount === null
	) {
		return [];
	}

	$categoryOrder = isset($categoryColumns['display_order'])
		? 'COALESCE(c.`display_order`, c.`' . $categoryId . '`)'
		: 'c.`' . $categoryId . '`';
	$itemOrder = isset($itemColumns['display_order'])
		? 'COALESCE(i.`display_order`, i.`' . $itemId . '`)'
		: 'i.`' . $itemId . '`';
	$itemYear = budget_year_column($itemColumns);
	$itemYearFilter = $itemYear !== null ? " AND i.`{$itemYear}` = :item_year" : '';

	$sql = "
		SELECT
			:select_year AS fiscal_year,
			c.`{$categoryId}` AS category_id,
			c.`{$categoryName}` AS category_name,
			i.`{$itemId}` AS item_id,
			i.`{$itemName}` AS item_name,
			COALESCE(r.actual_received, 0) AS actual_received,
			COALESCE(e.actual_paid, 0) AS actual_paid,
			COALESCE(r.actual_received, 0) - COALESCE(e.actual_paid, 0) AS actual_balance,
			CASE
				WHEN COALESCE(r.actual_received, 0) > 0
				THEN (COALESCE(e.actual_paid, 0) / COALESCE(r.actual_received, 0)) * 100
				ELSE 0
			END AS expenditure_percent
		FROM `info_budget_items` i
		JOIN `info_budget_categories` c
			ON c.`{$categoryId}` = i.`{$itemCategoryId}`
		LEFT JOIN (
			SELECT `{$receiptItemId}` AS item_id, SUM(COALESCE(`{$receiptAmount}`, 0)) AS actual_received
			FROM `info_budget_receipts`
			WHERE `{$receiptYear}` = :receipt_year
			GROUP BY `{$receiptItemId}`
		) r ON r.item_id = i.`{$itemId}`
		LEFT JOIN (
			SELECT `{$expenditureItemId}` AS item_id, SUM(COALESCE(`{$expenditureAmount}`, 0)) AS actual_paid
			FROM `info_budget_expenditures`
			WHERE `{$expenditureYear}` = :expenditure_year
			GROUP BY `{$expenditureItemId}`
		) e ON e.item_id = i.`{$itemId}`
		WHERE (COALESCE(r.actual_received, 0) <> 0 OR COALESCE(e.actual_paid, 0) <> 0){$itemYearFilter}
		ORDER BY {$categoryOrder} ASC, {$itemOrder} ASC, i.`{$itemId}` ASC
	";

	$stmt = $pdo->prepare($sql);
	$params = [
		':select_year' => $year,
		':receipt_year' => $year,
		':expenditure_year' => $year,
	];
	if ($itemYear !== null) {
		$params[':item_year'] = $year;
	}
	$stmt->execute($params);

	return array_map('budget_normalize_actual_item', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function budget_normalize_overview(array $rows, int $year): array
{
	$row = $rows[0] ?? [];
	$remainingBudget = budget_number(budget_pick($row, [
		'remaining_budget',
		'remainingBudget',
		'budget_remaining',
		'budgetRemaining',
		'available_budget',
		'availableBudget',
		'balance_amount',
		'remaining_amount',
	], null));

	return [
		'fiscalYear' => (string) budget_pick($row, ['fiscal_year', 'budget_year', 'year', 'fiscal_year_be', 'page_year'], $year),
		'fiscal_year' => (string) budget_pick($row, ['fiscal_year', 'budget_year', 'year', 'fiscal_year_be', 'page_year'], $year),
		'totalBudget' => budget_number(budget_pick($row, ['total_budget', 'budget_total', 'total_amount', 'amount'], 0)) ?? 0,
		'categoryCount' => (int) (budget_number(budget_pick($row, ['category_count', 'categories_count', 'total_categories'], 0)) ?? 0),
		'category_count' => (int) (budget_number(budget_pick($row, ['category_count', 'categories_count', 'total_categories'], 0)) ?? 0),
		'itemCount' => (int) (budget_number(budget_pick($row, ['item_count', 'items_count', 'total_items'], 0)) ?? 0),
		'item_count' => (int) (budget_number(budget_pick($row, ['item_count', 'items_count', 'total_items'], 0)) ?? 0),
		'electricityMonthsRecorded' => (int) (budget_number(budget_pick($row, ['electricity_months_recorded', 'months_recorded', 'recorded_months'], 0)) ?? 0),
		'electricityTotalAmount' => budget_number(budget_pick($row, ['electricity_total_amount', 'electricity_amount', 'utility_total_amount'], 0)) ?? 0,
		'remainingBudget' => $remainingBudget,
		'remaining_budget' => $remainingBudget,
		'raw' => $row,
	];
}

function budget_overview_with_remaining(PDO $pdo, int $year): array
{
	$overview = budget_normalize_overview(budget_fetch_rows($pdo, 'vw_info_budget_api_dashboard_overview', $year), $year);
	$actualSummary = budget_actual_summary($pdo, $year);
	$fiscalYear = budget_fiscal_year_value($pdo, $year);
	$categoryCount = budget_count_table_rows($pdo, 'info_budget_categories', $year);
	$itemCount = budget_count_table_rows($pdo, 'info_budget_items', $year);

	$overview['fiscalYear'] = $fiscalYear;
	$overview['fiscal_year'] = $fiscalYear;
	if ($categoryCount !== null) {
		$overview['categoryCount'] = $categoryCount;
		$overview['category_count'] = $categoryCount;
	}
	if ($itemCount !== null) {
		$overview['itemCount'] = $itemCount;
		$overview['item_count'] = $itemCount;
	}

	$overview['actualReceipts'] = $actualSummary['actualReceipts'];
	$overview['actual_receipts'] = $actualSummary['actual_receipts'];
	$overview['actualExpenditures'] = $actualSummary['actualExpenditures'];
	$overview['actual_expenditures'] = $actualSummary['actual_expenditures'];
	$overview['actualBalance'] = $actualSummary['actualBalance'];
	$overview['actual_balance'] = $actualSummary['actual_balance'];
	$overview['remainingBudget'] = $actualSummary['actualBalance'];
	$overview['remaining_budget'] = $actualSummary['actual_balance'];

	return $overview;
}

function budget_dashboard_cards(PDO $pdo, int $year): array
{
	$overview = budget_overview_with_remaining($pdo, $year);

	return [
		[
			'key' => 'fiscal_year',
			'label' => 'ปีงบประมาณ',
			'value' => $overview['fiscalYear'],
			'valueNumber' => null,
			'unit' => '',
			'note' => '',
			'raw' => [],
		],
		[
			'key' => 'category_count',
			'label' => 'หมวดงบประมาณ',
			'value' => $overview['categoryCount'],
			'valueNumber' => $overview['categoryCount'],
			'unit' => 'หมวด',
			'note' => '',
			'raw' => [],
		],
		[
			'key' => 'item_count',
			'label' => 'รายการงบประมาณ',
			'value' => $overview['itemCount'],
			'valueNumber' => $overview['itemCount'],
			'unit' => 'รายการ',
			'note' => '',
			'raw' => [],
		],
		[
			'key' => 'actual_receipts',
			'label' => 'งบประมาณรับจริง',
			'value' => $overview['actualReceipts'],
			'valueNumber' => $overview['actualReceipts'],
			'unit' => 'บาท',
			'note' => '',
			'raw' => [],
		],
		[
			'key' => 'actual_expenditures',
			'label' => 'งบประมาณจ่ายจริง',
			'value' => $overview['actualExpenditures'],
			'valueNumber' => $overview['actualExpenditures'],
			'unit' => 'บาท',
			'note' => '',
			'raw' => [],
		],
		[
			'key' => 'actual_balance',
			'label' => 'งบประมาณคงเหลือ',
			'value' => $overview['actualBalance'],
			'valueNumber' => $overview['actualBalance'],
			'unit' => 'บาท',
			'note' => '',
			'raw' => [],
		],
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
		'categoryName' => budget_string(budget_pick($row, ['category_name', 'category', 'name', 'label'], 'ไม่ระบุหมวด')),
		'amount' => $amount,
		'percent' => $percent,
		'percentCalculated' => $percent,
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
		'categoryName' => budget_string(budget_pick($row, ['category_name', 'category'])),
		'name' => budget_string(budget_pick($row, ['item_name', 'name', 'label', 'title'], 'ไม่ระบุรายการ')),
		'itemName' => budget_string(budget_pick($row, ['item_name', 'name', 'label', 'title'], 'ไม่ระบุรายการ')),
		'amount' => $amount,
		'percent' => $percent,
		'percentCalculated' => $percent,
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
			return budget_overview_with_remaining($pdo, $year);
		case 'cards':
			return budget_dashboard_cards($pdo, $year);
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
		case 'actual_items':
			return budget_fetch_actual_items($pdo, $year);
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
		'actual_items',
		'notes',
		'all',
	];

	if (!in_array($action, $allowedActions, true)) {
		budget_response(false, 'Invalid budget action', null, 400);
	}

	if ($action === 'all') {
		$overview = budget_fetch_action($pdo, 'overview', $year);
		$categories = budget_fetch_action($pdo, 'categories', $year);
		$actualSummary = budget_fetch_actual_summary_view($pdo, $year);
		$warnings = [];

		$data = [
			'overview' => $overview,
			'actualSummary' => $actualSummary,
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
			'actualItems' => budget_try_action($pdo, 'actual_items', $year, $warnings),
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
