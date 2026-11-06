<?php
/** Menu paths shared across KAP. Verify session format and enrolled status on live hosting. */
declare(strict_types=1);
return [
    'session_name' => null,
    'actor_session_key' => 'SystemUser_id', // Match the current Login session, not a user-supplied value.
    'login_url' => '/kap/login',
    'routes' => [
        'dashboard' => '/kap/pages/dashboard.php',
        'score-kap' => '/kap/pages/scores/kap.php',
        'plan' => '/kap/pages/scores/plan.php',
    ],
    'score_save_url' => '/kap/api/scores/save.php',
    'assets' => [
        'css' => '/kap/assets/css/kap-theme.css',
        'js' => '/kap/assets/js/kap-app.js',
    ],
    // Provisional. Use SELECT DISTINCT EnrollmentStatus ... to verify on real DB.
    'allowed_enrollment_statuses' => ['ACTIVE', 'ENROLLED'],
];
