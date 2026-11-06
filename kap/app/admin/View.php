<?php
declare(strict_types=1);
namespace Kap\Admin;

use Kap\Teacher\Bootstrap;

final class View
{
    public static function e(mixed $v): string {return Bootstrap::escape($v);}
    /** Present curriculum names consistently without rewriting Curriculum_name in the database. */
    public static function curriculumLabel(mixed $value): string
    {
        $raw=trim((string)($value??''));
        if($raw==='')return '';
        $rest=preg_replace('/^หลักสูตร(?:สถานศึกษา)?\s*/u','',$raw);
        return 'หลักสูตรสถานศึกษา'.($rest!==''?' '.trim($rest):'');
    }
    public static function path(string $route): string {return rtrim(Bootstrap::config()['base_path'],'/') . $route;}
    public static function header(string $title,string $active, array $actor): void
    {
        require_once dirname(__DIR__, 2) . '/ui/Theme.php';
        \Kap\Ui\Theme::start($title, $active, $actor, true);
        echo '<div class="page-head"><h1>'.self::e($title).'</h1><p>ข้อมูลบันทึกลงฐานข้อมูล KAP เฉพาะผู้มีสิทธิ์</p></div>';
    }
    public static function footer(): void {\Kap\Ui\Theme::end();}
    public static function options(array $rows, string $id, string $label, mixed $selected=null): string
    {
        $out='';
        foreach($rows as $row) $out.='<option value="'.self::e($row[$id]).'"'.((string)$row[$id]===(string)$selected?' selected':'').'>'.self::e($row[$label]).'</option>';
        return $out;
    }
    public static function csrf():string {return '<input type="hidden" name="csrf" value="'.self::e(Bootstrap::csrf()).'">';}
}
