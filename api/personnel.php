<?php

$allowedOrigins = [
	"http://localhost",
	"http://127.0.0.1",
	"http://localhost:5500",
	"http://127.0.0.1:5500"
];

$origin = $_SERVER["HTTP_ORIGIN"] ?? "";
if (in_array($origin, $allowedOrigins, true)) {
	header("Access-Control-Allow-Origin: {$origin}");
	header("Vary: Origin");
}

header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
	http_response_code(204);
	exit;
}

require_once __DIR__ . "/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
	http_response_code(405);
	echo json_encode([
		"success" => false,
		"error" => "Method not allowed"
	], JSON_UNESCAPED_UNICODE);
	exit;
}

function personnel_columns(PDO $pdo): array
{
	$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel'");
	return array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
}

function personnel_has(array $columns, string $column): bool
{
	return isset($columns[$column]);
}

function personnel_role_key(string $position): string
{
	if (preg_match('/ผู้อำนวยการ|รองผู้อำนวยการ/u', $position)) {
		return 'administrator';
	}

	if (preg_match('/ครู/u', $position)) {
		return 'teacher';
	}

	return 'support';
}

function personnel_select_sql(array $columns): string
{
	$select = [
		"id",
		"name",
		personnel_has($columns, "position") ? "position" : "'' AS position",
		personnel_has($columns, "department") ? "department" : "'' AS department",
		personnel_has($columns, "phone") ? "phone" : "'' AS phone",
		personnel_has($columns, "email") ? "email" : "'' AS email",
		personnel_has($columns, "image") ? "image" : "'' AS image",
		personnel_has($columns, "display_order") ? "display_order" : "id AS display_order"
	];

	if (personnel_has($columns, "group_name")) {
		$select[] = "group_name";
		$select[] = "group_name AS group_type";
	} elseif (personnel_has($columns, "department")) {
		$select[] = "department AS group_name";
		$select[] = "department AS group_type";
	} else {
		$select[] = "'' AS group_name";
		$select[] = "'' AS group_type";
	}

	return implode(", ", $select);
}

function personnel_active_filter(array $columns, array &$params): string
{
	if (personnel_has($columns, "is_active")) {
		$params[":is_active"] = 1;
		return " WHERE is_active = :is_active";
	} elseif (personnel_has($columns, "status")) {
		$params[":status"] = "active";
		return " WHERE status = :status";
	}

	return "";
}

function personnel_site_settings(PDO $pdo): array
{
	$stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
	$settings = [];

	foreach ($stmt->fetchAll() as $row) {
		$key = isset($row["setting_key"]) ? (string)$row["setting_key"] : "";
		if ($key === "") {
			continue;
		}
		$settings[$key] = isset($row["setting_value"]) ? trim((string)$row["setting_value"]) : "";
	}

	return $settings;
}

function personnel_setting_int(array $settings, string $key): ?int
{
	if (!array_key_exists($key, $settings) || $settings[$key] === "" || !is_numeric($settings[$key])) {
		return null;
	}

	return (int)$settings[$key];
}

function personnel_summary_from_settings(array $settings, array $positionSummary): array
{
	$keys = [
		"personnel_count" => "totalPersonnel",
		"administrator_count" => "administratorCount",
		"teacher_count" => "teacherCount",
		"support_count" => "supportCount"
	];
	$summary = [
		"totalPersonnel" => 0,
		"administratorCount" => 0,
		"teacherCount" => 0,
		"supportCount" => 0,
		"byPosition" => $positionSummary["byPosition"] ?? [],
		"missingSettingKeys" => []
	];

	foreach ($keys as $settingKey => $summaryKey) {
		$value = personnel_setting_int($settings, $settingKey);
		if ($value === null) {
			$summary["missingSettingKeys"][] = $settingKey;
			continue;
		}
		$summary[$summaryKey] = $value;
	}

	return $summary;
}

function personnel_build_summary(array $items): array
{
	$total = count($items);
	$roleCounts = [
		"administrator" => 0,
		"teacher" => 0,
		"support" => 0
	];
	$positions = [];

	foreach ($items as $item) {
		$position = (string)($item["position"] ?? "");
		$role = personnel_role_key($position);
		$roleCounts[$role]++;

		$positionKey = $position !== "" ? $position : "-";
		if (!isset($positions[$positionKey])) {
			$positions[$positionKey] = [
				"position" => $positionKey,
				"count" => 0,
				"percent" => 0
			];
		}
		$positions[$positionKey]["count"]++;
	}

	foreach ($positions as &$positionSummary) {
		$positionSummary["percent"] = $total > 0 ? round(($positionSummary["count"] / $total) * 100, 2) : 0;
	}
	unset($positionSummary);

	return [
		"totalPersonnel" => $total,
		"administratorCount" => $roleCounts["administrator"],
		"teacherCount" => $roleCounts["teacher"],
		"supportCount" => $roleCounts["support"],
		"byPosition" => array_values($positions)
	];
}

try {
	$columns = personnel_columns($pdo);
	$selectSql = personnel_select_sql($columns);
	$orderBy = personnel_has($columns, "display_order") ? "display_order ASC, id ASC" : "id ASC";

	$params = [];
	$where = personnel_active_filter($columns, $params);
	$sql = "SELECT {$selectSql} FROM personnel{$where} ORDER BY {$orderBy}";
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$items = $stmt->fetchAll();

	$activeSummary = personnel_build_summary($items);
	$settings = personnel_site_settings($pdo);

	echo json_encode([
		"success" => true,
		"data" => $items,
		"summary" => personnel_summary_from_settings($settings, $activeSummary),
		"activeSummary" => $activeSummary
	], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode([
		"success" => false,
		"error" => $e->getMessage()
	], JSON_UNESCAPED_UNICODE);
}
