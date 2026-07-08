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

function emptyStudentsData(int $academicYear): array
{
    return [
        "dataDate" => "",
        "academicYear" => $academicYear,
        "semester" => 1,
        "totalStudents" => 0,
        "classroomCount" => 0,
        "byGender" => [
            "male" => 0,
            "female" => 0
        ],
        "byLevel" => [
            ["level" => "อนุบาล", "count" => 0, "classrooms" => 0],
            ["level" => "ประถมศึกษา", "count" => 0, "classrooms" => 0],
            ["level" => "มัธยมศึกษาตอนต้น", "count" => 0, "classrooms" => 0],
            ["level" => "มัธยมศึกษาตอนปลาย", "count" => 0, "classrooms" => 0]
        ],
        "residenceType" => [
            ["type" => "นักเรียนประจำ", "count" => 0],
            ["type" => "นักเรียนไป-กลับ", "count" => 0]
        ],
        "dormitories" => [
            ["name" => "เรือนนอนชาย", "count" => 0],
            ["name" => "เรือนนอนหญิง", "count" => 0]
        ],
        "byProvince" => [],
        "byEthnicity" => [],
        "byReligion" => [],
        "byImpairment" => [],
        "multipleDisabilities" => [],
        "studentActivities" => [],
        "scholarships" => []
    ];
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

function toInt($value): int
{
    return (int)($value ?? 0);
}

function getCurrentAcademicYear(PDO $pdo): int
{
    $fallbackYear = (int)date("Y") + 543;

    if (tableExists($pdo, "site_settings")) {
        $settingStmt = $pdo->prepare(
            "SELECT setting_value
             FROM site_settings
             WHERE setting_key = :setting_key
             LIMIT 1"
        );
        $settingStmt->execute([":setting_key" => "academic_year"]);
        $settingYear = trim((string)($settingStmt->fetchColumn() ?: ""));
        if (is_numeric($settingYear)) {
            return (int)$settingYear;
        }
    }

    if (tableExists($pdo, "info_students")) {
        $studentYear = $pdo->query(
            "SELECT MAX(academic_year)
             FROM info_students
             WHERE is_active = 1"
        )->fetchColumn();
        if (is_numeric($studentYear)) {
            return (int)$studentYear;
        }
    }

    return $fallbackYear;
}

try {
    $academicYear = getCurrentAcademicYear($pdo);
    $semester = 1;
    $isActive = 1;
    $students = emptyStudentsData($academicYear);

    if (!tableExists($pdo, "info_students")) {
        echo json_encode([
            "success" => true,
            "students" => $students
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $params = [
        ":academic_year" => $academicYear,
        ":semester" => $semester,
        ":is_active" => $isActive
    ];

    $summaryStmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(boarding_male + boarding_female + commuter_male + commuter_female), 0) AS total_students,
            COALESCE(SUM(boarding_male + commuter_male), 0) AS male_total,
            COALESCE(SUM(boarding_female + commuter_female), 0) AS female_total,
            COUNT(DISTINCT CONCAT(level_name, '-', classroom)) AS classroom_count,
            COALESCE(SUM(boarding_male + boarding_female), 0) AS boarding_total,
            COALESCE(SUM(commuter_male + commuter_female), 0) AS commuter_total,
            COALESCE(SUM(boarding_male), 0) AS boarding_male_total,
            COALESCE(SUM(boarding_female), 0) AS boarding_female_total
         FROM info_students
         WHERE academic_year = :academic_year
           AND semester = :semester
           AND is_active = :is_active"
    );
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch() ?: [];

    $students["totalStudents"] = toInt($summary["total_students"] ?? 0);
    $students["classroomCount"] = toInt($summary["classroom_count"] ?? 0);
    $students["byGender"] = [
        "male" => toInt($summary["male_total"] ?? 0),
        "female" => toInt($summary["female_total"] ?? 0)
    ];
    $students["residenceType"] = [
        ["type" => "นักเรียนประจำ", "count" => toInt($summary["boarding_total"] ?? 0)],
        ["type" => "นักเรียนไป-กลับ", "count" => toInt($summary["commuter_total"] ?? 0)]
    ];
    $students["dormitories"] = [
        ["name" => "เรือนนอนชาย", "count" => toInt($summary["boarding_male_total"] ?? 0)],
        ["name" => "เรือนนอนหญิง", "count" => toInt($summary["boarding_female_total"] ?? 0)]
    ];

    $levelStmt = $pdo->prepare(
        "SELECT
            CASE
                WHEN level_name LIKE 'อนุบาล%' THEN 'อนุบาล'
                WHEN level_name LIKE 'ประถม%' THEN 'ประถมศึกษา'
                WHEN level_name LIKE 'มัธยมศึกษาปีที่ 1%' OR level_name LIKE 'มัธยมศึกษาปีที่ 2%' OR level_name LIKE 'มัธยมศึกษาปีที่ 3%' THEN 'มัธยมศึกษาตอนต้น'
                WHEN level_name LIKE 'มัธยมศึกษาปีที่ 4%' OR level_name LIKE 'มัธยมศึกษาปีที่ 5%' OR level_name LIKE 'มัธยมศึกษาปีที่ 6%' THEN 'มัธยมศึกษาตอนปลาย'
                ELSE 'อื่น ๆ'
            END AS level_group,
            COALESCE(SUM(boarding_male + boarding_female + commuter_male + commuter_female), 0) AS student_count,
            COUNT(DISTINCT CONCAT(level_name, '-', classroom)) AS classroom_count
         FROM info_students
         WHERE academic_year = :academic_year
           AND semester = :semester
           AND is_active = :is_active
         GROUP BY level_group"
    );
    $levelStmt->execute($params);

    $levelMap = [];
    foreach ($levelStmt->fetchAll() as $row) {
        $levelMap[$row["level_group"]] = [
            "count" => toInt($row["student_count"] ?? 0),
            "classrooms" => toInt($row["classroom_count"] ?? 0)
        ];
    }

    $levelOrder = ["อนุบาล", "ประถมศึกษา", "มัธยมศึกษาตอนต้น", "มัธยมศึกษาตอนปลาย"];
    $students["byLevel"] = array_map(function (string $level) use ($levelMap): array {
        return [
            "level" => $level,
            "count" => $levelMap[$level]["count"] ?? 0,
            "classrooms" => $levelMap[$level]["classrooms"] ?? 0
        ];
    }, $levelOrder);

    echo json_encode([
        "success" => true,
        "students" => $students
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Cannot load students data"
    ], JSON_UNESCAPED_UNICODE);
}
