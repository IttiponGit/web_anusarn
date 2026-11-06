<?php
declare(strict_types=1);
namespace Kap\Ui;

/** Single source of truth for KAP navigation, categories, URLs and visual permissions.
 * Controller/Backend MUST separately verify each route's permissions.
 */
final class Menu
{
    private const ADMIN = ['SYSTEM_ADMIN'];
    private const SCHOOL = ['SYSTEM_ADMIN','HEAD_ASSESSMENT','DEPUTY_ACADEMICS','DIRECTOR'];
    private const TEACHING = ['SYSTEM_ADMIN','SUBJECT_TEACHER','HEAD_ASSESSMENT','DEPUTY_ACADEMICS','DIRECTOR'];

    public static function groups(): array
    {
        return [
            ['title'=>'ภาพรวม', 'items'=>[
                ['id'=>'dashboard','title'=>'Dashboard ครูผู้สอน','icon'=>'speedometer2','path'=>'/','roles'=>self::TEACHING],
                ['id'=>'admin','title'=>'ภาพรวมแอดมิน','icon'=>'grid-1x2','path'=>'/pages/admin/index.php','roles'=>self::ADMIN],
            ]],
            ['title'=>'ข้อมูลพื้นฐาน', 'items'=>[
                ['id'=>'curricula','title'=>'หลักสูตรสถานศึกษาและประเภทรายวิชา','icon'=>'collection','path'=>'/pages/admin/curricula.php','roles'=>self::ADMIN],
                ['id'=>'subjects','title'=>'ทะเบียนรายวิชาและกิจกรรม','icon'=>'journal-text','path'=>'/pages/admin/subjects.php','roles'=>self::ADMIN],
                ['id'=>'students','title'=>'ทะเบียนนักเรียน 2569','icon'=>'people','path'=>'/pages/students/index.php','roles'=>self::SCHOOL],
            ]],
            ['title'=>'การจัดการเรียนการสอน', 'items'=>[
                ['id'=>'assignments','title'=>'มอบหมายรายวิชา','icon'=>'journal-check','path'=>'/pages/admin/assignments.php','roles'=>self::ADMIN],
                ['id'=>'score-kap','title'=>'กรอกคะแนน K/A/P','icon'=>'grid-3x3-gap','path'=>'/pages/scores/kap.php','roles'=>self::TEACHING],
            ]],
            ['title'=>'ผู้ใช้งานและสิทธิ์', 'items'=>[
                ['id'=>'users','title'=>'จัดการผู้ใช้งาน','icon'=>'person-gear','path'=>'/pages/admin/users.php','roles'=>self::ADMIN],
            ]],
            ['title'=>'กำลังพัฒนา', 'items'=>[
                ['id'=>'offerings','title'=>'แผนเปิดสอนและกลุ่มเรียน','icon'=>'calendar3','path'=>null,'roles'=>self::ADMIN],
                ['id'=>'history','title'=>'ประวัติการมอบหมาย','icon'=>'clock-history','path'=>null,'roles'=>self::ADMIN],
                ['id'=>'attendance','title'=>'บันทึกเวลาเรียน','icon'=>'calendar-check','path'=>null,'roles'=>self::TEACHING],
                ['id'=>'pp5','title'=>'พิมพ์ ปพ.5','icon'=>'printer','path'=>null,'roles'=>self::TEACHING],
            ]],
        ];
    }

    public static function visible(array $item, array $roles): bool
    {
        return (bool)array_intersect($roles, $item['roles'] ?? []);
    }

    public static function href(string $base, string $path): string
    {
        if ($path === '/') return rtrim($base, '/') . '/';
        return rtrim($base, '/') . $path;
    }
}
