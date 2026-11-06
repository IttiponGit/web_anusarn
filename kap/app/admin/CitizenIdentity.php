<?php
declare(strict_types=1);
namespace Kap\Admin;

require_once __DIR__ . '/FieldError.php';

use Kap\Teacher\WebError;

/** Thai 13-digit identity: canonical 13-digit Teacher_id primary key after reviewed migration. */
final class CitizenIdentity
{
    public static function validate(mixed $value, string $field = 'teacher_id'): string
    {
        if (!is_string($value) || !preg_match('/^[0-9]{13}$/D', $value)) {
            throw new FieldError($field, 'เลขบัตรประชาชนไม่ครบ 13 หลักหรือมีอักขระอื่น', 'กรอกตัวเลข 0–9 ให้ครบ 13 หลัก ห้ามเว้นวรรคหรือใส่ขีด');
        }
        if (preg_match('/^(.)\1{12}$/D', $value)) {
            throw new FieldError($field, 'เลขบัตรประชาชนไม่ถูกต้อง', 'ตรวจตัวเลขทั้ง 13 หลักกับบัตรประชาชนก่อนบันทึก');
        }
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += ((int)$value[$i]) * (13 - $i);
        }
        $check = (11 - ($sum % 11)) % 10;
        if ($check !== (int)$value[12]) {
            throw new FieldError($field, 'เลขบัตรประชาชนไม่ผ่านการตรวจเลขหลักสุดท้าย', 'ตรวจตัวเลขทั้ง 13 หลักจากต้นฉบับอีกครั้ง โดยเฉพาะหลักสุดท้าย');
        }
        return $value;
    }

    public static function mask(?string $value): string
    {
        if ($value === null || strlen($value) !== 13) return 'ยังไม่ได้ระบุ';
        return '*********' . substr($value, -4);
    }
}
