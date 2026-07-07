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

require_once __DIR__ . "/../../api/db.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Database connection is not available"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "error" => "Method not allowed"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function awardsJsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function getStringParam(string $key): ?string
{
    $value = trim((string)($_GET[$key] ?? ""));
    return $value === "" ? null : $value;
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name"
    );
    $stmt->execute([":table_name" => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function getColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query("SHOW COLUMNS FROM `" . str_replace("`", "``", $table) . "`");
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

function hasRequiredColumns(array $columns, array $requiredColumns): bool
{
    $columnMap = array_fill_keys($columns, true);
    foreach ($requiredColumns as $column) {
        if (!isset($columnMap[$column])) {
            return false;
        }
    }

    return true;
}

function awardGroupMap(): array
{
    return [
        "student" => "รางวัลนักเรียน",
        "staff" => "รางวัลครูและบุคลากร",
        "institution" => "รางวัลสถานศึกษา"
    ];
}

function normalizeAwardGroup(?string $awardType): ?string
{
    foreach (awardGroupMap() as $key => $label) {
        if ($awardType === $key || $awardType === $label) {
            return $key;
        }
    }

    return null;
}

try {
    if (!tableExists($pdo, "info_awards")) {
        awardsJsonResponse(500, [
            "success" => false,
            "error" => "info_awards table not found"
        ]);
    }

    $awardColumns = getColumns($pdo, "info_awards");
    $requiredAwardColumns = [
        "award_id",
        "award_type",
        "award_list",
        "award_agency",
        "award_level",
        "award_year",
        "display_order",
        "is_active",
        "created_at",
        "updated_at"
    ];

    if (!hasRequiredColumns($awardColumns, $requiredAwardColumns)) {
        awardsJsonResponse(500, [
            "success" => false,
            "error" => "info_awards schema is not compatible"
        ]);
    }

    $resultColumns = tableExists($pdo, "info_awards_results") ? getColumns($pdo, "info_awards_results") : [];
    $recipientColumns = tableExists($pdo, "info_awards_recipients") ? getColumns($pdo, "info_awards_recipients") : [];

    $hasResults = hasRequiredColumns($resultColumns, [
        "result_id",
        "award_id",
        "award_result",
        "result_rank",
        "result_detail",
        "result_type",
        "team_name",
        "result_count",
        "note",
        "display_order",
        "is_active"
    ]);
    $hasRecipients = hasRequiredColumns($recipientColumns, [
        "recipient_id",
        "award_id",
        "result_id",
        "award_result",
        "result_rank",
        "result_detail",
        "recipient_name"
    ]);
    $recipientHasActiveColumn = in_array("is_active", $recipientColumns, true);

    $groupMap = awardGroupMap();
    $group = getStringParam("group");
    if ($group !== null && !array_key_exists($group, $groupMap)) {
        awardsJsonResponse(400, [
            "success" => false,
            "error" => "Invalid group"
        ]);
    }

    $page = filter_input(INPUT_GET, "page", FILTER_VALIDATE_INT, [
        "options" => ["default" => 1, "min_range" => 1]
    ]);
    $limit = filter_input(INPUT_GET, "limit", FILTER_VALIDATE_INT, [
        "options" => ["default" => 10, "min_range" => 1, "max_range" => 100]
    ]);
    $page = $page ?: 1;
    $limit = $limit ?: 10;
    $offset = ($page - 1) * $limit;

    $filters = ["a.is_active = :is_active"];
    $params = [":is_active" => 1];

    if ($group !== null) {
        $filters[] = "(a.award_type = :award_type_key OR a.award_type = :award_type_label)";
        $params[":award_type_key"] = $group;
        $params[":award_type_label"] = $groupMap[$group];
    }

    $year = getStringParam("year");
    if ($year !== null) {
        $filters[] = "a.award_year = :award_year";
        $params[":award_year"] = $year;
    }

    $level = getStringParam("level");
    if ($level !== null) {
        $filters[] = "a.award_level = :award_level";
        $params[":award_level"] = $level;
    }

    $search = getStringParam("search");
    if ($search !== null) {
        $searchConditions = [
            "a.award_list LIKE :search_award_list",
            "a.award_agency LIKE :search_award_agency",
            "a.award_level LIKE :search_award_level"
        ];
        $params[":search_award_list"] = "%" . $search . "%";
        $params[":search_award_agency"] = "%" . $search . "%";
        $params[":search_award_level"] = "%" . $search . "%";

        if ($hasRecipients && $hasResults) {
            $searchConditions[] = "EXISTS (
                SELECT 1
                FROM info_awards_recipients sr
                JOIN info_awards_results sres
                  ON sres.result_id = sr.result_id
                WHERE sr.award_id = a.award_id
                  AND sres.is_active = 1
                  AND COALESCE(sres.result_type, '') <> 'summary'
                  " . ($recipientHasActiveColumn ? "AND sr.is_active = 1" : "") . "
                  AND (
                    sr.recipient_name LIKE :search_recipient_name
                    OR sr.award_result LIKE :search_award_result
                    OR sr.result_detail LIKE :search_result_detail
                  )
            )";
            $params[":search_recipient_name"] = "%" . $search . "%";
            $params[":search_award_result"] = "%" . $search . "%";
            $params[":search_result_detail"] = "%" . $search . "%";
        }

        if ($hasResults) {
            $searchConditions[] = "EXISTS (
                SELECT 1
                FROM info_awards_results rs
                WHERE rs.award_id = a.award_id
                  AND rs.is_active = 1
                  AND (
                    rs.award_result LIKE :search_result_award_result
                    OR rs.result_detail LIKE :search_result_detail_text
                    OR rs.team_name LIKE :search_team_name
                    OR rs.note LIKE :search_result_note
                  )
            )";
            $params[":search_result_award_result"] = "%" . $search . "%";
            $params[":search_result_detail_text"] = "%" . $search . "%";
            $params[":search_team_name"] = "%" . $search . "%";
            $params[":search_result_note"] = "%" . $search . "%";
        }

        $filters[] = "(" . implode(" OR ", $searchConditions) . ")";
    }

    $whereSql = "WHERE " . implode(" AND ", $filters);

    $summary = [
        "student" => 0,
        "staff" => 0,
        "institution" => 0,
        "total" => 0
    ];

    $summaryStmt = $pdo->prepare(
        "SELECT award_type, COUNT(*) AS total
         FROM info_awards
         WHERE is_active = :is_active
         GROUP BY award_type"
    );
    $summaryStmt->execute([":is_active" => 1]);

    foreach ($summaryStmt->fetchAll() as $row) {
        $normalizedGroup = normalizeAwardGroup($row["award_type"] ?? null);
        $count = (int)$row["total"];

        if ($normalizedGroup !== null) {
            $summary[$normalizedGroup] += $count;
        }
        $summary["total"] += $count;
    }

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM info_awards a
         {$whereSql}"
    );
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $recipientCountSelect = $hasRecipients && $hasResults
        ? "COALESCE(rc.recipient_count, 0) AS recipient_count"
        : "0 AS recipient_count";
    $awardReceivedCountSelect = $hasResults
        ? "COALESCE(gc.award_received_count, 0) AS award_received_count"
        : "0 AS award_received_count";
    $resultSummarySelect = $hasResults
        ? "COALESCE(gc.result_summary, '') AS result_summary"
        : "'' AS result_summary";

    $recipientActiveSql = $recipientHasActiveColumn ? "AND r.is_active = 1" : "";
    $recipientJoinSql = $hasRecipients && $hasResults
        ? "LEFT JOIN (
            SELECT
                r.award_id,
                COUNT(r.recipient_id) AS recipient_count
            FROM info_awards_recipients r
            JOIN info_awards_results res
              ON res.result_id = r.result_id
            WHERE res.is_active = 1
              AND COALESCE(res.result_type, '') <> 'summary'
              {$recipientActiveSql}
            GROUP BY r.award_id
        ) rc ON rc.award_id = a.award_id"
        : "";

    $resultJoinSql = $hasResults
        ? "LEFT JOIN (
            SELECT
                award_id,
                SUM(COALESCE(result_count, 0)) AS award_received_count,
                GROUP_CONCAT(
                    CASE
                        WHEN result_type = 'summary'
                        THEN CONCAT(COALESCE(award_result, ''), ' ', COALESCE(result_count, 0), ' รายการ')
                        ELSE NULL
                    END
                    ORDER BY display_order ASC, result_id ASC
                    SEPARATOR ' / '
                ) AS result_summary
            FROM info_awards_results
            WHERE is_active = 1
            GROUP BY award_id
        ) gc ON gc.award_id = a.award_id"
        : "";

    $groupCaseSql = "CASE
        WHEN a.award_type = 'student' OR a.award_type = :student_label_select THEN 'student'
        WHEN a.award_type = 'staff' OR a.award_type = :staff_label_select THEN 'staff'
        WHEN a.award_type = 'institution' OR a.award_type = :institution_label_select THEN 'institution'
        ELSE a.award_type
    END AS award_group";

    $stmt = $pdo->prepare(
        "SELECT
            a.award_id AS id,
            a.award_list AS title,
            a.award_type AS award_type_label,
            {$groupCaseSql},
            a.award_year AS award_year,
            a.award_agency AS organizer,
            a.award_level AS level,
            a.display_order AS sort_order,
            a.is_active AS is_active,
            a.created_at AS created_at,
            a.updated_at AS updated_at,
            {$recipientCountSelect},
            {$awardReceivedCountSelect},
            {$resultSummarySelect}
         FROM info_awards a
         {$recipientJoinSql}
         {$resultJoinSql}
         {$whereSql}
         ORDER BY a.award_year DESC, a.display_order ASC, a.award_id ASC
         LIMIT :limit OFFSET :offset"
    );

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(":student_label_select", $groupMap["student"]);
    $stmt->bindValue(":staff_label_select", $groupMap["staff"]);
    $stmt->bindValue(":institution_label_select", $groupMap["institution"]);
    $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();

    $items = array_map(function (array $item): array {
        $item["recipient_count"] = (int)$item["recipient_count"];
        $item["award_received_count"] = (int)$item["award_received_count"];
        return $item;
    }, $stmt->fetchAll());

    echo json_encode([
        "success" => true,
        "summary" => $summary,
        "data" => $items,
        "pagination" => [
            "page" => $page,
            "limit" => $limit,
            "total" => $total,
            "totalPages" => (int)ceil($total / $limit)
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Cannot load awards data"
    ], JSON_UNESCAPED_UNICODE);
}
