<?php
declare(strict_types=1);
namespace Kap\Admin;

/** Frozen reference: school's primary curriculum Google Sheet (six tabs),
 * spreadsheet id 1mfbuwFb6RPbw1Dl2TEkPxeakaRYiQkoehM74E71SkWA.
 * Never infer type names from Subject name or a numeric Curriculum_id.
 */
final class CurriculumTypeReference
{
    private const CATEGORIES = [
        2564 => [
            'BASIC' => 'พื้นฐาน',
            'ADDITIONAL' => 'เพิ่มเติม',
            'LEARNER_DEV' => 'กิจกรรมพัฒนาผู้เรียน',
            'FREE_CHOICE_ACTIVITIES' => 'กิจกรรมอิสระตามความสนใจ',
        ],
        2565 => [
            'BASIC' => 'พื้นฐาน',
            'ADDITIONAL' => 'เพิ่มเติม',
            'LEARNER_DEV' => 'กิจกรรมพัฒนาผู้เรียน',
            'FREE_CHOICE_ACTIVITIES' => 'กิจกรรมอิสระตามความสนใจ',
        ],
        2566 => [
            'BASIC' => 'พื้นฐาน',
            'ADDITIONAL' => 'เพิ่มเติม',
            'LEARNER_DEV' => 'กิจกรรมพัฒนาผู้เรียน',
            'COMPETENCY_ENHANCEMENT' => 'กิจกรรมเสริมสร้างสมรรถนะนักเรียน',
        ],
        2567 => [
            'BASIC' => 'พื้นฐาน',
            'ADDITIONAL' => 'เพิ่มเติม',
            'LEARNER_DEV' => 'กิจกรรมพัฒนาผู้เรียน',
            'COMPETENCY_ENHANCEMENT' => 'กิจกรรมเสริมสร้างสมรรถนะนักเรียน',
        ],
        2568 => [
            'FOUNDATIONAL_LEARNING' => 'ความสามารถพื้นฐานด้านการเรียนรู้',
            'DAILY_LIFE_APPLICATION' => 'ความสามารถด้านการประยุกต์ใช้ในชีวิตประจำวัน',
            'LEARNER_DEV' => 'กิจกรรมพัฒนาผู้เรียน',
        ],
        2569 => [
            'FOUNDATIONAL_LEARNING' => 'ความสามารถพื้นฐานด้านการเรียนรู้',
            'DAILY_LIFE_APPLICATION' => 'ความสามารถด้านการประยุกต์ใช้ในชีวิตประจำวัน',
            'LEARNER_DEV' => 'กิจกรรมพัฒนาผู้เรียน',
        ],
    ];

    public static function primary(int $start, int $end, string $gradeCode): array
    {
        if ($end !== $start + 5 || !preg_match('/^P[1-6]$/D', $gradeCode)) return [];
        return self::CATEGORIES[$start] ?? [];
    }

    public static function names(array $types): array { return array_values($types); }
}
