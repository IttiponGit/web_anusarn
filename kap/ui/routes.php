<?php
declare(strict_types=1);
/**
 * URLs are relative to /kap. Fill them ONLY after checking the actual files.
 * null disables the link rather than guessing a filename and producing a 404.
 * The ONE confirmed file URL from the November 1 screenshots is subject_catalog.
 * Never put credentials or access-control rules here.
 */
return [
    'teacher_dashboard'   => null, // Locate the existing teacher Dashboard PHP file
    'score_entry'         => null, // Locate the existing K/A/P score entry PHP file
    'students'            => null, // Locate the existing 2569 roster PHP file
    'admin_dashboard'     => null, // Locate existing admin overview
    'subject_catalog'     => '/pages/admin/subjects.php', // VERIFIED in screenshot
    'curriculum_subjects'  => '/pages/admin/curriculum-subjects-v2.php',
    'teaching_assignment'=> null, // Locate existing teacher-to-subject assignment PHP
    'users'               => null, // Locate existing user-management PHP
    'attendance'          => null,
    'traits'              => null,
    'competencies'        => null,
    'reading'             => null,
    'scores_overview'     => null,
    'quality'             => null,
    'pp5'                 => null,
];
