<?php
declare(strict_types=1);
// KAP Login: database mode uses SystemUser session rather than IP or teacher preview.
// Never put credentials or public write-bypass tokens here.
return [
    'mode' => 'database',              // no TEST-xxx; no demo fallback
    'base_path' => '/kap',
    'allowed_class_statuses' => ['CONFIRMED'], // match imported StudentClassEnrollment.Status
    'allowed_enrollment_statuses' => ['ACTIVE','ENROLLED'],
    'allow_real_roster_writes' => false, // require working login/session adapter + explicit opt-in
    'https_terminated_at_proxy' => false, // only if HTTPS is terminated by a trusted host-controlled proxy
    'authenticated_user_session_key' => 'kap_authenticated_user_id',
];
