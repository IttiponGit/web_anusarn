<?php
declare(strict_types=1);
// Shared KAP bootstrap must load both Teacher and Auth before Admin controllers.
// Dashboard already uses this chain; bypassing it caused HTTP 500 on Admin pages.
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_once __DIR__ . '/FieldError.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/CitizenIdentity.php';
require_once __DIR__ . '/Directory.php';
require_once __DIR__ . '/Actions.php';
require_once __DIR__ . '/View.php';
require_once __DIR__ . '/SubjectRules.php';
require_once __DIR__ . '/SubjectCatalog.php';
require_once __DIR__ . '/CurriculumCatalog.php';
