<?php
declare(strict_types=1);
namespace Kap\Admin;

use Kap\Teacher\Bootstrap;
use Kap\Teacher\WebError;
use PDO;

final class Security
{
    public static function actor(PDO $db, string $capability = 'users'): array
    {
        if(Bootstrap::mode()!=='database')throw new WebError(403,'ต้องใช้บัญชีผู้ดูแลระบบจริง');
        $user=\Kap\Auth\Auth::requireLogin(true);
        $allowed=$capability==='assignments' ? ['SYSTEM_ADMIN'] : ['SYSTEM_ADMIN'];
        if(!array_intersect($user['Roles'],$allowed))throw new WebError(403,'ไม่มีสิทธิ์จัดการข้อมูลส่วนนี้');
        return $user;
    }
    public static function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') throw new WebError(405, 'ต้องส่งข้อมูลด้วย POST');
        \Kap\Auth\Auth::verifyPost();
    }
    public static function positive(mixed $value, string $field = 'id', string $label = 'รหัสรายการ'): int
    {
        $missing = $value === null || $value === '';
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)
            || strlen((string)$value) > 19 || filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new FieldError(
                $field,
                $missing ? "กรุณาเลือก{$label}" : "{$label}ไม่ถูกต้อง",
                $missing ? "เปิดรายการ{$label}แล้วเลือกข้อมูลก่อนกดบันทึก" : "เลือกรายการ{$label}ใหม่จากตัวเลือกในหน้าเว็บ หากไม่พบรายการให้โหลดหน้าใหม่",
            );
        }
        return (int)$value;
    }
    public static function text(
        mixed $value, int $max = 150, bool $required = true,
        string $field = '', string $label = 'ข้อความ'
    ): string {
        if (!is_string($value)) throw new FieldError($field, "รูปแบบ{$label}ไม่ถูกต้อง", "กรอก{$label}เป็นข้อความธรรมดาแล้วลองอีกครั้ง");
        $value = trim($value);
        if (($required && $value === '') || !preg_match('//u',$value)
            || (function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : preg_match_all('/./us', $value)) > $max
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
            throw new FieldError($field, "{$label}ว่างหรือมีรูปแบบไม่ถูกต้อง", "กรอก{$label}ให้ครบและไม่เกิน {$max} ตัวอักษร โดยไม่ใส่อักขระควบคุม");
        }
        return $value;
    }
    public static function username(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9._-]{3,80}$/D', $value)) {
            throw new FieldError('username', 'Username ไม่ถูกต้อง', 'กรอกชื่อผู้ใช้งาน 3–80 ตัวอักษร ใช้เฉพาะ A–Z, a–z, 0–9, จุด (.), ขีดกลาง (-) หรือขีดล่าง (_)');
        }
        return $value;
    }
    public static function password(mixed $value): string
    {
        if (!is_string($value) || strlen($value) < 8 || strlen($value) > 200 || str_contains($value, "\0")) {
            throw new FieldError('password', 'รหัสผ่านไม่ผ่านเงื่อนไข', 'ตั้งรหัสผ่านอย่างน้อย 8 ตัวอักษร โดยต้องไม่เกิน 200 ไบต์ แล้วกรอกใหม่');
        }
        return $value;
    }
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
