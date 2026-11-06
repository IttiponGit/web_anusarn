<?php
declare(strict_types=1);
namespace Kap\Teacher;

/** Avoid float persistence; validate scores as exact two-decimal strings. */
final class ScoreRules
{
    public static function canonical(mixed $score, string $max): ?string
    {
        if ($score === null || $score === '') return null;
        if (!(is_string($score) || is_int($score)) || !preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/D', (string)$score)) {
            throw new WebError(422, 'คะแนนต้องเป็นตัวเลขไม่ติดลบและมีทศนิยมไม่เกิน 2 ตำแหน่ง');
        }
        $s = self::cents((string)$score);
        $maxCents = self::cents($max);
        if ($s > $maxCents) throw new WebError(422, 'คะแนนมากกว่าคะแนนเต็ม');
        return sprintf('%d.%02d', intdiv($s, 100), $s % 100);
    }

    public static function cents(string $value): int
    {
        if (!preg_match('/^([0-9]{1,8})(?:\.([0-9]{1,2}))?$/D', $value, $m)) {
            throw new WebError(422, 'รูปแบบคะแนนเต็มไม่ถูกต้อง');
        }
        return ((int)$m[1]) * 100 + (int)str_pad($m[2] ?? '', 2, '0');
    }
}
