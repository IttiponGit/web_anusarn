<?php
declare(strict_types=1);

namespace KAP;

use PDO;

/**
 * Read-only tabular views for KAP. No DDL, writes or login changes.
 * Include the UI templates only AFTER the existing page authorization check.
 */
final class CurriculumDisplayTables
{
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Normalized display label; never writes to Curriculum_name. */
    public static function curriculumLabel(mixed $name): string
    {
        $raw=trim((string)($name??''));
        if($raw==='')return '';
        $suffix=preg_replace('/^หลักสูตร(?:สถานศึกษา)?\s*/u','',$raw);
        return 'หลักสูตรสถานศึกษา'.($suffix!==''?' '.trim($suffix):'');
    }

    /** @return array<int, array<string,mixed>> */
    public static function curricula(PDO $db): array
    {
        $list = $db->query('SELECT Curriculum_id, Curriculum_name, IsActive
                            FROM Curriculum ORDER BY CAST(RIGHT(TRIM(Curriculum_name),4) AS UNSIGNED) DESC, Curriculum_id DESC')
                   ->fetchAll(PDO::FETCH_ASSOC);
        $indices = [];
        foreach ($list as $i => &$curr) {
            $curr['periods'] = [];
            $curr['sections'] = [];
            $curr['rootCount'] = 0;
            $curr['childCount'] = 0;
            $indices[(int)$curr['Curriculum_id']] = $i;
        }
        unset($curr);

        // The cohort range belongs to CurriculumGradeYear, not Curriculum_name.
        $periods = $db->query('SELECT cg.Curriculum_id, cg.CohortStartYearBE, cg.CohortEndYearBE,
                                     g.GradeName, a.AcademicYear_name
                              FROM CurriculumGradeYear cg
                              JOIN GradeLevel g ON g.GradeLevel_id=cg.GradeLevel_id
                              JOIN AcademicYear a ON a.AcademicYear_id=cg.AcademicYear_id
                              ORDER BY cg.Curriculum_id, cg.CohortStartYearBE,
                                       cg.CohortEndYearBE, g.SortOrder')
                      ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($periods as $period) {
            $cid = (int)$period['Curriculum_id'];
            if (!array_key_exists($cid, $indices)) { continue; }
            $range = $period['CohortStartYearBE'] . '–' . $period['CohortEndYearBE'];
            $label = $period['GradeName'] . ' (ปี ' . $period['AcademicYear_name'] . ')';
            $list[$indices[$cid]]['periods'][$range][$label] = true;
        }

        $sections = $db->query('SELECT CurriculumSection_id, Curriculum_id, SectionCode,
                                       SectionName, ParentSection_id, SortOrder, IsActive
                                FROM CurriculumSection
                                ORDER BY Curriculum_id, SortOrder, CurriculumSection_id')
                       ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sections as $row) {
            $cid = (int)$row['Curriculum_id'];
            if (!array_key_exists($cid, $indices)) { continue; }
            $list[$indices[$cid]]['sections'][] = $row;
            if ($row['ParentSection_id'] === null) {
                $list[$indices[$cid]]['rootCount']++;
            } else {
                $list[$indices[$cid]]['childCount']++;
            }
        }
        foreach ($list as &$curr) {
            $curr['sections'] = self::sortTree($curr['sections']);
        }
        unset($curr);
        return $list;
    }

    /** @return array<int, array<int, array<string,mixed>>> */
    public static function linksBySection(PDO $db): array
    {
        if (!self::hasColumn($db, 'CurriculumSubject', 'CurriculumSection_id')) {
            return [];
        }
        $rows = $db->query('SELECT cs.CurriculumSection_id, cs.Curriculum_id,
                                   cs.CurriculumSubject_id, s.Subject_code, s.Subject_name,
                                   g.GradeName, g.SortOrder AS GradeSortOrder
                            FROM CurriculumSubject cs
                            JOIN Subject s ON s.Subject_id=cs.Subject_id
                            JOIN GradeLevel g ON g.GradeLevel_id=cs.GradeLevel_id
                            JOIN CurriculumSection c
                              ON c.CurriculumSection_id=cs.CurriculumSection_id
                             AND c.Curriculum_id=cs.Curriculum_id
                            WHERE cs.CurriculumSection_id IS NOT NULL
                            ORDER BY cs.Curriculum_id, cs.CurriculumSection_id,
                                     g.SortOrder, s.Subject_name, cs.CurriculumSubject_id')
                   ->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row['CurriculumSection_id']][] = $row;
        }
        return $out;
    }

    /** Optional per-section grade assignments, if the migration already exists. */
    public static function gradesBySection(PDO $db): array
    {
        if (!self::hasTable($db, 'CurriculumSectionGrade')) { return []; }
        $rows = $db->query('SELECT sg.CurriculumSection_id, g.GradeName
                            FROM CurriculumSectionGrade sg
                            JOIN GradeLevel g ON g.GradeLevel_id=sg.GradeLevel_id
                            WHERE sg.IsActive=1
                            ORDER BY sg.CurriculumSection_id, sg.SortOrder, g.SortOrder')
                   ->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row['CurriculumSection_id']][$row['GradeName']] = true;
        }
        return $out;
    }

    public static function hasTable(PDO $db, string $table): bool
    {
        $s = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES
                           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $s->execute([$table]);
        return (int)$s->fetchColumn() > 0;
    }

    public static function hasColumn(PDO $db, string $table, string $column): bool
    {
        $s = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $s->execute([$table, $column]);
        return (int)$s->fetchColumn() > 0;
    }

    /** Sort a hierarchy without losing rows with orphan parents or cycles. */
    public static function sortTree(array $sections): array
    {
        $byId=[];
        foreach ($sections as $r) { $byId[(int)$r['CurriculumSection_id']]=$r; }
        $children=[];
        foreach ($sections as $r) {
            $p = $r['ParentSection_id'] === null ? 0 : (int)$r['ParentSection_id'];
            if ($p !== 0 && (!isset($byId[$p]) || (int)$byId[$p]['Curriculum_id'] !== (int)$r['Curriculum_id'])) {
                $r['_warning']='invalid_parent';
                $p=0;
            }
            $children[$p][]=$r;
        }
        foreach ($children as &$siblings) {
            usort($siblings, static fn($a,$b) =>
                ((int)$a['SortOrder'] <=> (int)$b['SortOrder'])
                ?: ((int)$a['CurriculumSection_id'] <=> (int)$b['CurriculumSection_id']));
        }
        unset($siblings);
        $seen=[]; $result=[];
        $walk = static function (int $parent, int $depth) use (&$walk, &$seen, &$result, $children): void {
            foreach ($children[$parent] ?? [] as $r) {
                $id=(int)$r['CurriculumSection_id'];
                if (isset($seen[$id])) { continue; }
                $seen[$id]=true;
                $r['_depth']=min(10,$depth);
                $result[]=$r;
                $walk($id, $depth+1);
            }
        };
        $walk(0,0);
        foreach ($sections as $r) {
            $id=(int)$r['CurriculumSection_id'];
            if (isset($seen[$id])) { continue; }
            $r['_depth']=0;
            $r['_warning']='cycle_or_unreachable';
            $result[]=$r;
            $seen[$id]=true;
            $walk($id,1);
        }
        return $result;
    }
}
