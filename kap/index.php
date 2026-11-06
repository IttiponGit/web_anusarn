<?php
declare(strict_types=1);
// Only the root KAP index is the application's main entry point.
// Real database mode requires verified SystemUser login in pages/dashboard.php via Bootstrap::start().
require __DIR__ . '/pages/dashboard.php';
