<?php
declare(strict_types=1);
namespace Kap\Migrations;

/** Generate a one-time cutover plan for a VERIFIED schema, never execute DDL from the web. */
final class TeacherPkPlan
{
    public const CHILDREN = [
        'ClassTime', 'SubjectTeacher', 'SystemUser', 'SubjectScorePlan',
        'LearningUnit', 'AssessmentItem', 'ClassRoomAdvisor',
        'PP5SignatureRecord', 'TeacherSignatureAsset'
    ];
    public const COLS = [
        'ClassTime' => 'Teacher_id',
        'SubjectTeacher' => 'Teacher_id',
        'SystemUser' => 'Teacher_id',
        'SubjectScorePlan' => 'CreatedByTeacher_id',
        'LearningUnit' => 'CreatedByTeacher_id',
        'AssessmentItem' => 'CreatedByTeacher_id',
        'ClassRoomAdvisor' => 'Teacher_id',
        'PP5SignatureRecord' => 'SignerTeacher_id',
        'TeacherSignatureAsset' => 'Teacher_id',
    ];
    public static function q(string $name): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $name)) {
            throw new \RuntimeException('Unsafe SQL identifier in metadata');
        }
        return '`' . $name . '`';
    }
    /** @param array<string,array{nullable:bool,foreign_key:?string,delete_rule:string,update_rule:string}> $children */
    public static function sql(array $children, bool $hasCitizenId, bool $hasTeacherCode): array
    {
        foreach (self::CHILDREN as $table) {
            if (!isset($children[$table])) throw new \RuntimeException('Missing required reference table: ' . $table);
        }
        $map = '`_kap_teacher_pk_map_202610`';
        $sql = [
            '-- KAP one-time Primary Key cutover. Generated from live information_schema.',
            '-- DO NOT run on a live system without a full verified database backup and a maintenance window.',
            '-- MariaDB DDL implicitly commits. There is NO transaction rollback if interrupted.',
            '-- Data integrity must be checked using the generated verification script.',
            'CREATE TABLE ' . $map . ' (LegacyTeacherId BIGINT UNSIGNED NOT NULL PRIMARY KEY, NewTeacherId CHAR(13) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE) ENGINE=InnoDB;'
        ];
        if ($hasCitizenId) {
            $sql[] = 'INSERT INTO ' . $map . ' (LegacyTeacherId,NewTeacherId) SELECT Teacher_id,CitizenId FROM `Teacher`;';
        }
        foreach (self::CHILDREN as $table) {
            $fk = $children[$table]['foreign_key'];
            if ($fk !== null) $sql[] = 'ALTER TABLE ' . self::q($table) . ' DROP FOREIGN KEY ' . self::q($fk) . ';';
        }
        $sql[] = 'ALTER TABLE `Teacher` MODIFY COLUMN `Teacher_id` CHAR(13) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;';
        $sql[] = 'UPDATE `Teacher` t INNER JOIN ' . $map . ' m ON t.`Teacher_id`=CAST(m.LegacyTeacherId AS CHAR) SET t.`Teacher_id`=m.NewTeacherId;';
        foreach (self::CHILDREN as $table) {
            $nullable = $children[$table]['nullable'] ? 'NULL' : 'NOT NULL';
            $t = self::q($table);
            $col = self::q(self::COLS[$table]);
            $sql[] = 'ALTER TABLE ' . $t . ' MODIFY COLUMN ' . $col . ' CHAR(13) CHARACTER SET ascii COLLATE ascii_bin ' . $nullable . ';';
            $sql[] = 'UPDATE ' . $t . ' c INNER JOIN ' . $map . ' m ON c.' . $col . '=CAST(m.LegacyTeacherId AS CHAR) SET c.' . $col . '=m.NewTeacherId;';
        }
        foreach (self::CHILDREN as $table) {
            $info = $children[$table];
            $fk = $info['foreign_key'] ?? ('fk_kap_teacher_' . strtolower($table));
            $del = strtoupper($info['delete_rule']);
            $upd = strtoupper($info['update_rule']);
            foreach ([$del,$upd] as $rule) {
                if (!in_array($rule,['RESTRICT','NO ACTION','CASCADE','SET NULL'],true)) {
                    throw new \RuntimeException('Unsupported original foreign-key action');
                }
            }
            $sql[] = 'ALTER TABLE ' . self::q($table) . ' ADD CONSTRAINT ' . self::q($fk) . ' FOREIGN KEY (' . self::q(self::COLS[$table]) . ') REFERENCES `Teacher` (`Teacher_id`) ON DELETE ' . $del . ' ON UPDATE ' . $upd . ';';
        }
        $sql[] = '-- STOP. Execute the verification queries and compare counts before running cleanup.';
        $verify = [
            '-- Expect every OrphanCount = 0; compare all counts to pre-cutover snapshots.',
            'SELECT COUNT(*) AS InvalidTeacherKeys FROM `Teacher` WHERE `Teacher_id` NOT REGEXP \'^[0-9]{13}$\';',
            'SELECT COUNT(*) AS UnmappedTeacherKeys FROM `Teacher` t LEFT JOIN ' . $map . ' m ON t.`Teacher_id`=m.NewTeacherId WHERE m.NewTeacherId IS NULL;'
        ];
        foreach (self::CHILDREN as $table) {
            $t=self::q($table);
            $col = self::q(self::COLS[$table]);
            $verify[] = 'SELECT ' . "'" . $table . "'" . ' AS TableName, COUNT(*) AS OrphanCount FROM ' . $t . ' c LEFT JOIN `Teacher` t ON c.' . $col . '=t.`Teacher_id` WHERE c.' . $col . ' IS NOT NULL AND t.`Teacher_id` IS NULL;';
        }
        $verify[] = "SELECT COUNT(*) AS TeacherFkCount FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='Teacher' AND REFERENCED_COLUMN_NAME='Teacher_id'; -- expected 9";
        $cleanup = [
            '-- Execute ONLY when verification passes, complete backup exists, and application code is deployed.'
        ];
        if ($hasCitizenId) $cleanup[] = 'ALTER TABLE `Teacher` DROP COLUMN `CitizenId`;';
        if ($hasTeacherCode) $cleanup[] = 'ALTER TABLE `Teacher` DROP COLUMN `TeacherCode`;';
        $cleanup[] = '-- Keep the migration map in protected DB storage until post-deployment audit is accepted.';
        $cleanup[] = '-- Optional later: DROP TABLE ' . $map . ';';
        return [implode("\n", $sql) . "\n", implode("\n", $verify) . "\n", implode("\n", $cleanup) . "\n"];
    }
}
