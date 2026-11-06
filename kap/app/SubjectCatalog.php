<?php
declare(strict_types=1);
namespace KAP;

use DomainException;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

/** School subject/activity master registry only; does not assign courses to a curriculum. */
final class SubjectCatalog
{
    public static function types(PDO $db): array
    {
        return $db->query('SELECT `TypeofCourse_id`,`TypeofCourse_name`,`IsActive` FROM `TypeofCourse` ORDER BY `TypeofCourse_id`')->fetchAll();
    }

    public static function areas(PDO $db): array
    {
        return $db->query('SELECT `LearningArea_id`,`LearningArea_name`,`IsActive` FROM `LearningArea` ORDER BY `LearningArea_id`')->fetchAll();
    }

    public static function stats(PDO $db): array
    {
        return $db->query("SELECT COUNT(*) total, COALESCE(SUM(`SubjectKind`='COURSE'),0) courses, COALESCE(SUM(`SubjectKind`='ACTIVITY'),0) activities, COALESCE(SUM(`IsActive`=0),0) drafts, COALESCE(SUM(`IsActive`=1),0) active FROM `Subject`")->fetch() ?: [];
    }

    public static function find(PDO $db, int $id): ?array
    {
        $s = $db->prepare('SELECT `Subject_id`,`Subject_code`,`Subject_name`,`Learning_Area_id`,`TypeofCourse_id`,`SubjectKind`,`IsActive` FROM `Subject` WHERE `Subject_id` = ? LIMIT 1');
        $s->execute([$id]);
        $row = $s->fetch();
        return $row ?: null;
    }

    private static function filters(array $input): array
    {
        $q = trim((string)($input['q'] ?? ''));
        if (self::length($q) > 100) { $q = self::slice($q, 100); }
        $conditions = [];
        $params = [];
        if ($q !== '') {
            $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
            $conditions[] = '(`s`.`Subject_code` LIKE ? OR `s`.`Subject_name` LIKE ?)';
            $params[] = '%' . $like . '%';
            $params[] = '%' . $like . '%';
        }
        $kind = (string)($input['kind'] ?? '');
        if (in_array($kind, ['COURSE','ACTIVITY'], true)) { $conditions[] = '`s`.`SubjectKind` = ?'; $params[] = $kind; }
        $status = (string)($input['status'] ?? '');
        if (in_array($status, ['0','1'], true)) { $conditions[] = '`s`.`IsActive` = ?'; $params[] = (int)$status; }
        $type = filter_var($input['type'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($type !== false) { $conditions[] = '`s`.`TypeofCourse_id` = ?'; $params[] = $type; }
        $area = (string)($input['area'] ?? '');
        if ($area === 'none') { $conditions[] = '`s`.`Learning_Area_id` IS NULL'; }
        else {
            $areaId = filter_var($area, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if ($areaId !== false) { $conditions[] = '`s`.`Learning_Area_id` = ?'; $params[] = $areaId; }
        }
        if (($input['nocode'] ?? '') === '1') { $conditions[] = '(`s`.`Subject_code` IS NULL OR `s`.`Subject_code` = \'\')'; }
        return [$conditions ? ' WHERE ' . implode(' AND ', $conditions) : '', $params];
    }

    private static function execute(PDO $db, string $sql, array $params): \PDOStatement
    {
        $stmt = $db->prepare($sql);
        foreach (array_values($params) as $i => $v) {
            $stmt->bindValue($i + 1, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function page(PDO $db, array $input): array
    {
        [$where, $params] = self::filters($input);
        $total = (int)self::execute($db, 'SELECT COUNT(*) FROM `Subject` s' . $where, $params)->fetchColumn();
        $perPage = 20;
        $maxPage = max(1, (int)ceil($total / $perPage));
        $page = filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 1;
        $page = min($page, $maxPage);
        $sql = 'SELECT s.`Subject_id`,s.`Subject_code`,s.`Subject_name`,s.`SubjectKind`,s.`IsActive`,t.`TypeofCourse_name`,a.`LearningArea_name` '
            . 'FROM `Subject` s JOIN `TypeofCourse` t ON t.`TypeofCourse_id`=s.`TypeofCourse_id` '
            . 'LEFT JOIN `LearningArea` a ON a.`LearningArea_id`=s.`Learning_Area_id` '
            . $where . ' ORDER BY s.`Subject_id` DESC LIMIT ? OFFSET ?';
        $rows = self::execute($db, $sql, [...$params, $perPage, ($page - 1) * $perPage])->fetchAll();
        return ['total'=>$total,'page'=>$page,'maxPage'=>$maxPage,'rows'=>$rows];
    }

    public static function export(PDO $db, array $input): void
    {
        [$where,$params] = self::filters($input);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="kap-subjects-' . date('Ymd') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'wb');
        if ($out === false) { throw new RuntimeException('Unable to open CSV output'); }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['รหัสภายใน','รหัสวิชา','ชื่อวิชา/กิจกรรม','ชนิด','ประเภท','กลุ่มสาระ','สถานะ']);
        $stmt = self::execute($db, 'SELECT s.`Subject_id`,s.`Subject_code`,s.`Subject_name`,s.`SubjectKind`,t.`TypeofCourse_name`,a.`LearningArea_name`,s.`IsActive` '
            . 'FROM `Subject` s JOIN `TypeofCourse` t ON t.`TypeofCourse_id`=s.`TypeofCourse_id` '
            . 'LEFT JOIN `LearningArea` a ON a.`LearningArea_id`=s.`Learning_Area_id` '
            . $where . ' ORDER BY s.`Subject_id`', $params);
        while ($row = $stmt->fetch()) {
            $cells = [(string)$row['Subject_id'], (string)($row['Subject_code'] ?? ''), (string)$row['Subject_name'],
                $row['SubjectKind']==='ACTIVITY'?'กิจกรรม':'รายวิชา', (string)$row['TypeofCourse_name'],
                (string)($row['LearningArea_name']??''), (int)$row['IsActive']===1?'เปิดใช้งาน':'ฉบับร่าง'];
            foreach ($cells as &$cell) {
                // Neutralize spreadsheet-formula prefixes on untrusted user-supplied text.
                if (preg_match('/^[\x00-\x20]*[=+\-@]/u', $cell)) { $cell = "'" . $cell; }
            }
            unset($cell);
            fputcsv($out, $cells);
        }
        fclose($out);
    }

    public static function codeIndexInstalled(PDO $db): bool
    {
        // A single-column UNIQUE index ensures no duplicate official code can be raced into the DB.
        $sql = "SELECT COUNT(*) FROM (SELECT INDEX_NAME, MIN(NON_UNIQUE) nonunique, COUNT(*) columns_count, SUM(COLUMN_NAME='Subject_code') code_count FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Subject' GROUP BY INDEX_NAME HAVING nonunique=0 AND columns_count=1 AND code_count=1) x";
        return (int)$db->query($sql)->fetchColumn() > 0;
    }

    private static function length(string $s): int
    {
        if (function_exists('mb_strlen')) { return mb_strlen($s, 'UTF-8'); }
        return preg_match_all('/./us', $s) ?: 0;
    }
    private static function slice(string $s, int $size): string
    {
        if (function_exists('mb_substr')) { return mb_substr($s, 0, $size, 'UTF-8'); }
        return implode('', array_slice(preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, $size));
    }

    /** @throws InvalidArgumentException when form input is inconsistent. */
    private static function validate(PDO $db, array $in): array
    {
        $name = trim((string)($in['subject_name'] ?? ''));
        $code = trim((string)($in['subject_code'] ?? ''));
        $kind = (string)($in['subject_kind'] ?? '');
        $type = filter_var($in['course_type_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $areaRaw = (string)($in['learning_area_id'] ?? '');
        $area = $areaRaw === '' ? null : filter_var($areaRaw, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $active = ($in['is_active'] ?? '') === '1' ? 1 : 0;
        if ($name === '' || self::length($name) > 255 || preg_match('/[\x00-\x1F\x7F]/', $name)) { throw new InvalidArgumentException('กรุณาระบุชื่อวิชา 1–255 ตัวอักษร โดยไม่มีอักขระควบคุม'); }
        if (self::length($code) > 255 || preg_match('/[\s\x00-\x1F\x7F]/u', $code)) { throw new InvalidArgumentException('รหัสวิชาต้องไม่ยาวเกิน 255 ตัวอักษรและไม่มีช่องว่าง'); }
        if (!in_array($kind, ['COURSE','ACTIVITY'], true)) { throw new InvalidArgumentException('กรุณาเลือกชนิดรายวิชาหรือกิจกรรม'); }
        if ($type === false || $type === null) { throw new InvalidArgumentException('กรุณาเลือกประเภทวิชา'); }
        if ($areaRaw !== '' && $area === false) { throw new InvalidArgumentException('กลุ่มสาระไม่ถูกต้อง'); }
        $typeStmt = $db->prepare('SELECT `TypeofCourse_name` FROM `TypeofCourse` WHERE `TypeofCourse_id` = ? LIMIT 1');
        $typeStmt->execute([$type]);
        $typeName = $typeStmt->fetchColumn();
        if ($typeName === false) { throw new InvalidArgumentException('ไม่พบประเภทวิชาที่เลือก'); }
        if (($typeName === 'กิจกรรม' && $kind !== 'ACTIVITY') ||
            (in_array($typeName, ['พื้นฐาน','เพิ่มเติม'], true) && $kind !== 'COURSE')) {
            throw new InvalidArgumentException('ชนิดและประเภทวิชาไม่สอดคล้องกัน');
        }
        if ($area !== null) {
            $check = $db->prepare('SELECT 1 FROM `LearningArea` WHERE `LearningArea_id` = ?');
            $check->execute([$area]);
            if ($check->fetchColumn() === false) { throw new InvalidArgumentException('ไม่พบกลุ่มสาระที่เลือก'); }
        }
        if ($active === 1 && ($in['confirm_activate'] ?? '') !== '1') {
            throw new InvalidArgumentException('กรุณายืนยันการตรวจทานก่อนเปิดใช้งานรายการ');
        }
        return ['Subject_code'=>$code === '' ? null : $code, 'Subject_name'=>$name, 'Learning_Area_id'=>$area,
            'TypeofCourse_id'=>$type, 'SubjectKind'=>$kind, 'IsActive'=>$active];
    }

    public static function save(PDO $db, int $actorId, ?int $id, array $form): int
    {
        if (!self::codeIndexInstalled($db)) { throw new DomainException('ยังไม่มี UNIQUE INDEX สำหรับ Subject_code กรุณาติดตั้ง SQL ขั้นที่ 02 ก่อนเปิดใช้งานการแก้ไข'); }
        $data = self::validate($db, $form);
        $db->beginTransaction();
        try {
            $before = null;
            if ($id !== null) {
                $stmt = $db->prepare('SELECT `Subject_id`,`Subject_code`,`Subject_name`,`Learning_Area_id`,`TypeofCourse_id`,`SubjectKind`,`IsActive` FROM `Subject` WHERE `Subject_id` = ? FOR UPDATE');
                $stmt->execute([$id]);
                $before = $stmt->fetch();
                if (!$before) { throw new InvalidArgumentException('ไม่พบรายการที่ต้องการแก้ไข'); }
            }
            if ($data['Subject_code'] !== null) {
                $st = $db->prepare('SELECT `Subject_id` FROM `Subject` WHERE `Subject_code` = ? AND `Subject_id` <> ? LIMIT 1');
                $st->execute([$data['Subject_code'], $id ?? 0]);
                if ($st->fetchColumn() !== false) { throw new InvalidArgumentException('รหัสวิชานี้มีอยู่แล้วในทะเบียน'); }
            }
            if ($id === null) {
                $s = $db->prepare('INSERT INTO `Subject` (`Subject_code`,`Subject_name`,`Learning_Area_id`,`TypeofCourse_id`,`SubjectKind`,`IsActive`) VALUES (?,?,?,?,?,?)');
                $s->execute(array_values($data));
                $id = (int)$db->lastInsertId();
            } else {
                $s = $db->prepare('UPDATE `Subject` SET `Subject_code`=?,`Subject_name`=?,`Learning_Area_id`=?,`TypeofCourse_id`=?,`SubjectKind`=?,`IsActive`=? WHERE `Subject_id`=?');
                $s->execute([...array_values($data), $id]);
            }
            // Existing Audit service records metadata in NewValuesJson. Before/after kept inside changes payload.
            Audit::record($db, 'Subject', (string)$id, $before===null?'SUBJECT_CREATE':'SUBJECT_UPDATE', $actorId, [
                'before'=>$before, 'after'=>$data, 'reason'=>'admin_catalog_form'
            ]);
            $db->commit();
            return $id;
        } catch (PDOException $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            if ((string)$e->getCode() === '23000') { throw new InvalidArgumentException('ข้อมูลซ้ำหรือความสัมพันธ์ไม่ถูกต้อง โปรดตรวจรหัสวิชาและประเภท'); }
            throw $e;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            throw $e;
        }
    }
}
