<?php
/** KAP Phase 2.6 additive admin screens; no schema changes. PHP 8.4 / MariaDB 10.6. */
declare(strict_types=1);

namespace KAP\Phase26;

use KAP\Auth;
use KAP\Session;
use PDO;
use RuntimeException;

final class Core
{
    public static function boot(): array
    {
        $root = dirname(__DIR__);
        require_once $root . '/config/bootstrap.php';
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/kap/pages/admin/phase26-curricula.php');
        $base = rtrim(str_replace('\\', '/', dirname($script, 3)), '/');
        if ($base === '' || $base === '.') { $base = '/kap'; }
        Session::start($base . '/');
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: DENY');
        header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        header('Cache-Control: no-store');

        $pdo = \kap_db();
        if (!($pdo instanceof PDO)) { throw new RuntimeException('Invalid PDO instance'); }
        $user = Auth::user($pdo);
        if (!is_array($user) || empty($user['SystemUser_id'])) {
            header('Location: ' . $base . '/login', true, 303);
            exit;
        }
        $roles = Auth::activeRoles($pdo, (int) $user['SystemUser_id']);
        $allowed = false;
        foreach ($roles as $role) {
            if (($role['RoleCode'] ?? '') === 'SUBJECT_CATALOG_ADMIN') { $allowed = true; break; }
        }
        if (!$allowed) {
            http_response_code(403);
            exit('403: ไม่มีสิทธิ์จัดการหลักสูตรสถานศึกษา');
        }
        return [$pdo, $user, $base];
    }

    public static function h(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function title(mixed $raw): string
    {
        $raw = trim((string) ($raw ?? ''));
        if (preg_match('/([0-9]{4})\s*$/u', $raw, $m) === 1) {
            return 'หลักสูตรสถานศึกษา ' . $m[1];
        }
        $raw = preg_replace('/^หลักสูตร(?:สถานศึกษา)?\s*/u', '', $raw) ?? $raw;
        return 'หลักสูตรสถานศึกษา' . ($raw === '' ? '' : ' ' . $raw);
    }

    public static function year(mixed $raw): int
    {
        return preg_match('/([0-9]{4})\s*$/u', (string) $raw, $m) ? (int) $m[1] : 0;
    }

    public static function positiveId(mixed $value): int
    {
        if (!is_scalar($value)) { return 0; }
        $id = filter_var((string) $value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? 0 : (int) $id;
    }

    public static function years(PDO $pdo): array
    {
        return $pdo->query('SELECT AcademicYear_id, AcademicYear_name FROM `AcademicYear` ORDER BY CAST(AcademicYear_name AS UNSIGNED) DESC, AcademicYear_id DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function academicYear(PDO $pdo): array
    {
        $years = self::years($pdo);
        if (!$years) { throw new RuntimeException('AcademicYear is empty'); }
        $wanted = self::positiveId($_GET['year_id'] ?? null);
        foreach ($years as $y) {
            if ($wanted && (int) $y['AcademicYear_id'] === $wanted) { return $y; }
        }
        foreach ($years as $y) {
            if ((string) $y['AcademicYear_name'] === '2569') { return $y; }
        }
        return $years[0];
    }

    public static function grades(PDO $pdo, int $yearId, int $curriculumId): array
    {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT g.GradeLevel_id, g.GradeCode, g.GradeName, g.SortOrder
             FROM `CurriculumGradeYear` cg
             JOIN `GradeLevel` g ON g.GradeLevel_id=cg.GradeLevel_id
             WHERE cg.AcademicYear_id=? AND cg.Curriculum_id=?
             ORDER BY g.SortOrder, g.GradeLevel_id'
        );
        $stmt->execute([$yearId, $curriculumId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function curricula(PDO $pdo, int $yearId): array
    {
        // Curriculum is the grouping key, not the grade: one row per Curriculum_id.
        $stmt = $pdo->prepare(
            'SELECT c.Curriculum_id,c.Curriculum_name,c.IsActive,
                    (SELECT COUNT(*) FROM `CurriculumSection` sec
                      WHERE sec.Curriculum_id=c.Curriculum_id) AS SectionCount,
                    GROUP_CONCAT(DISTINCT g.GradeName ORDER BY g.SortOrder SEPARATOR \', \') AS GradeNames,
                    COUNT(DISTINCT g.GradeLevel_id) AS GradeCount
               FROM `Curriculum` c
               LEFT JOIN `CurriculumGradeYear` cg ON cg.Curriculum_id=c.Curriculum_id
                 AND cg.AcademicYear_id=?
               LEFT JOIN `GradeLevel` g ON g.GradeLevel_id=cg.GradeLevel_id
              GROUP BY c.Curriculum_id,c.Curriculum_name,c.IsActive
              ORDER BY CAST(RIGHT(TRIM(c.Curriculum_name),4) AS UNSIGNED) DESC,
                       c.Curriculum_id DESC'
        );
        $stmt->execute([$yearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function dropdownCurricula(array $curricula): array
    {
        // SELECT already ordered by curriculum year DESC (including years unused in selected AY).
        return $curricula;
    }

    public static function withParams(string $page, array $params = [], string $base = '/kap'): string
    {
        $valid = ['curricula'=>'phase26-curricula.php','sections'=>'phase26-sections.php',
                  'subjects'=>'phase26-subjects.php','review'=>'phase26-review.php'];
        $file = $valid[$page] ?? $valid['curricula'];
        $filtered = array_filter($params, static fn ($value) => $value !== null && $value !== '');
        $query = $filtered ? '?' . http_build_query($filtered, '', '&', PHP_QUERY_RFC3986) : '';
        return rtrim($base, '/') . '/pages/admin/' . $file . $query;
    }

    public static function renderStart(string $heading, string $active, string $base, array $year, array $years, array $extraQuery = []): void
    {
        $h = [self::class, 'h'];
        $items = ['curricula' => 'ทะเบียนหลักสูตรสถานศึกษา', 'sections' => 'หมวดหลักสูตรสถานศึกษา',
                  'subjects' => 'รายวิชาตามหลักสูตรสถานศึกษา', 'review' => 'ตรวจรับรองรายวิชา'];
        $yearId = (int) $year['AcademicYear_id'];
        echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . $h($heading) . ' | KAP</title>';
        echo '<link rel="stylesheet" href="' . $h($base . '/assets/phase26.css') . '"></head><body>';
        echo '<header class="kap-top"><div class="kap-logo">KAP</div><div><strong>หลักสูตรสถานศึกษา</strong><small>Phase 2.6 · งานแอดมิน</small></div><a class="kap-back" href="' . $h($base . '/dashboard') . '">แดชบอร์ด</a></header>';
        echo '<main class="kap-content"><nav class="kap-nav" aria-label="เมนูหลักสูตรสถานศึกษา">';
        foreach ($items as $key => $title) {
            $href = self::withParams($key, ['year_id' => $yearId], $base);
            echo '<a class="' . ($active === $key ? 'is-active' : '') . '" href="' . $h($href) . '">' . $h($title) . '</a>';
        }
        echo '</nav><section class="kap-main"><div class="kap-heading"><div><div class="kap-eyebrow">KAP · Phase 2.6</div><h1>' . $h($heading) . '</h1></div>';
        echo '<form method="get" class="kap-year">';
        foreach ($extraQuery as $param => $value) {
            if (is_string($param) && preg_match('/^[a-z_]+$/D', $param) && is_scalar($value)) {
                echo '<input type="hidden" name="' . $h($param) . '" value="' . $h($value) . '">';
            }
        }
        echo '<label for="year_id">ปีการศึกษา</label><select id="year_id" name="year_id">';
        foreach ($years as $y) {
            $selected = $yearId === (int) $y['AcademicYear_id'] ? ' selected' : '';
            echo '<option value="' . $h($y['AcademicYear_id']) . '"' . $selected . '>' . $h($y['AcademicYear_name']) . '</option>';
        }
        echo '</select><button class="kap-btn secondary" type="submit">เปลี่ยนปี</button></form></div>';
    }

    public static function renderEnd(): void
    {
        echo '</section></main><footer class="kap-footer">ข้อมูลอ่านจากฐาน KAP จริง · ไม่มีการสร้างหลักสูตรสถานศึกษาซ้ำ · รายการร่างยังไม่เปิดใช้งานอัตโนมัติ</footer></body></html>';
    }

    public static function note(string $text, string $class = 'info'): void
    {
        echo '<div class="kap-alert kap-alert--' . self::h($class) . '">' . self::h($text) . '</div>';
    }

    public static function decimal(mixed $raw, int $maxWholeDigits, string $field): string
    {
        if (!is_scalar($raw)) { throw new RuntimeException('ค่า ' . $field . ' ไม่ถูกต้อง'); }
        $text = trim((string) $raw);
        if (preg_match('/^\d{1,' . $maxWholeDigits . '}(?:\.\d{1,2})?$/D', $text) !== 1) {
            throw new RuntimeException('ค่า ' . $field . ' ต้องเป็นเลขไม่ติดลบและทศนิยมไม่เกิน 2 ตำแหน่ง');
        }
        return number_format((float) $text, 2, '.', '');
    }
}
