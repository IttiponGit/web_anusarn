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

function awardsResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function awardGroupKey(?string $awardType): string
{
    $value = preg_replace('/[\s\x{200B}\x{200C}\x{200D}\x{FEFF}]+/u', ' ', (string)$awardType);
    $value = trim($value ?? "");
    $map = [
        "student" => "student",
        "รางวัลนักเรียน" => "student",
        "staff" => "staff",
        "รางวัลครูและบุคลากร" => "staff",
        "institution" => "institution",
        "school" => "institution",
        "รางวัลสถานศึกษา" => "institution",
        "สถานศึกษา" => "institution",
        "รางวัลโรงเรียน" => "institution",
        "โรงเรียน" => "institution"
    ];

    return $map[$value] ?? $value;
}

function placeholders(array $values): string
{
    return implode(",", array_fill(0, count($values), "?"));
}

try {
    $awardStmt = $pdo->prepare(
        "SELECT
            award_id,
            award_list,
            award_agency,
            award_level,
            award_year,
            award_type,
            display_order,
            created_at,
            updated_at
         FROM info_awards
         WHERE is_active = :is_active
         ORDER BY award_year DESC, display_order ASC, award_id DESC"
    );
    $awardStmt->execute([":is_active" => 1]);

    $awards = [];
    $awardIds = [];
    $summary = [
        "student" => 0,
        "staff" => 0,
        "institution" => 0,
        "total" => 0
    ];

    foreach ($awardStmt->fetchAll() as $row) {
        $awardId = (int)$row["award_id"];
        $awardIds[] = $awardId;

        $groupKey = awardGroupKey($row["award_type"] ?? "");
        if (array_key_exists($groupKey, $summary)) {
            $summary[$groupKey]++;
        }
        $summary["total"]++;

        $awards[$awardId] = [
            "award_id" => $awardId,
            "award_list" => $row["award_list"] ?? "",
            "award_agency" => $row["award_agency"] ?? "",
            "award_level" => $row["award_level"] ?? "",
            "award_year" => $row["award_year"] ?? "",
            "award_type" => $row["award_type"] ?? "",
            "award_group" => $groupKey,
            "award_total" => 0,
            "student_total" => 0,
            "display_order" => (int)($row["display_order"] ?? 0),
            "created_at" => $row["created_at"] ?? null,
            "updated_at" => $row["updated_at"] ?? null,
            "results" => [],
            "recipients" => []
        ];
    }

    if ($awardIds) {
        $inSql = placeholders($awardIds);

        $resultStmt = $pdo->prepare(
            "SELECT
                result_id,
                award_id,
                award_result,
                result_rank,
                result_detail,
                result_type,
                team_name,
                result_count,
                note,
                display_order,
                created_at,
                updated_at
             FROM info_awards_results
             WHERE is_active = 1
               AND award_id IN ({$inSql})
             ORDER BY award_id ASC, display_order ASC, result_id ASC"
        );
        $resultStmt->execute($awardIds);

        foreach ($resultStmt->fetchAll() as $row) {
            $awardId = (int)$row["award_id"];
            if (!isset($awards[$awardId])) {
                continue;
            }

            $awards[$awardId]["results"][] = [
                "result_id" => (int)$row["result_id"],
                "award_id" => $awardId,
                "award_result" => $row["award_result"] ?? "",
                "result_rank" => $row["result_rank"] ?? "",
                "result_detail" => $row["result_detail"] ?? "",
                "result_type" => $row["result_type"] ?? "",
                "team_name" => $row["team_name"] ?? "",
                "result_count" => (int)($row["result_count"] ?? 0),
                "note" => $row["note"] ?? "",
                "display_order" => (int)($row["display_order"] ?? 0),
                "created_at" => $row["created_at"] ?? null,
                "updated_at" => $row["updated_at"] ?? null
            ];
        }

        $awardTotalStmt = $pdo->prepare(
            "SELECT
                award_id,
                COALESCE(SUM(result_count), 0) AS award_total
             FROM info_awards_results
             WHERE is_active = 1
               AND award_id IN ({$inSql})
             GROUP BY award_id"
        );
        $awardTotalStmt->execute($awardIds);

        foreach ($awardTotalStmt->fetchAll() as $row) {
            $awardId = (int)$row["award_id"];
            if (!isset($awards[$awardId])) {
                continue;
            }

            $awards[$awardId]["award_total"] = (int)($row["award_total"] ?? 0);
        }

        $recipientStmt = $pdo->prepare(
            "SELECT
                recipient_id,
                award_id,
                result_id,
                award_result,
                result_rank,
                result_detail,
                recipient_name,
                recipient_detail,
                note,
                display_order,
                created_at,
                updated_at
             FROM info_awards_recipients
             WHERE award_id IN ({$inSql})
             ORDER BY award_id ASC, display_order ASC, recipient_id ASC"
        );
        $recipientStmt->execute($awardIds);

        foreach ($recipientStmt->fetchAll() as $row) {
            $awardId = (int)$row["award_id"];
            if (!isset($awards[$awardId])) {
                continue;
            }

            $awards[$awardId]["recipients"][] = [
                "recipient_id" => (int)$row["recipient_id"],
                "award_id" => $awardId,
                "result_id" => (int)($row["result_id"] ?? 0),
                "award_result" => $row["award_result"] ?? "",
                "result_rank" => $row["result_rank"] ?? "",
                "result_detail" => $row["result_detail"] ?? "",
                "recipient_name" => $row["recipient_name"] ?? "",
                "recipient_detail" => $row["recipient_detail"] ?? "",
                "note" => $row["note"] ?? "",
                "display_order" => (int)($row["display_order"] ?? 0),
                "created_at" => $row["created_at"] ?? null,
                "updated_at" => $row["updated_at"] ?? null
            ];
        }

        $studentTotalStmt = $pdo->prepare(
            "SELECT
                award_id,
                COUNT(DISTINCT recipient_id) AS student_total
             FROM info_awards_recipients
             WHERE award_id IN ({$inSql})
             GROUP BY award_id"
        );
        $studentTotalStmt->execute($awardIds);

        foreach ($studentTotalStmt->fetchAll() as $row) {
            $awardId = (int)$row["award_id"];
            if (!isset($awards[$awardId])) {
                continue;
            }

            $awards[$awardId]["student_total"] = (int)($row["student_total"] ?? 0);
        }
    }

    awardsResponse(200, [
        "success" => true,
        "summary" => $summary,
        "data" => array_values($awards)
    ]);
} catch (Throwable $e) {
    awardsResponse(500, [
        "success" => false,
        "error" => "Cannot load awards data"
    ]);
}
