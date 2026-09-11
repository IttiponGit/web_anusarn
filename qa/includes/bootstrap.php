<?php
// ระบบประกันคุณภาพสถานศึกษา - Bootstrap สำหรับ PHP 5.6+

define('QA_ROOT', dirname(__DIR__));
define('QA_BASE_URL', '/qa');
define('QA_APP_NAME', 'ระบบประกันคุณภาพสถานศึกษา');
define('QA_SCHOOL_NAME', 'โรงเรียนโสตศึกษาอนุสารสุนทร');

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    die('ยังไม่พบ includes/config.php กรุณาคัดลอก config.example.php เป็น config.php และกรอกค่าฐานข้อมูลก่อน');
}
require_once $configFile;
require_once __DIR__ . '/db.php';

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, QA_BASE_URL . '/', '', $secure, true);
    session_start();
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/project_workflow.php';
require_once __DIR__ . '/project_change.php';

function qa_url($path)
{
    if ($path === null) {
        $path = '';
    }
    $path = ltrim($path, '/');
    return QA_BASE_URL . ($path !== '' ? '/' . $path : '');
}

function h($value)
{
    if ($value === null) {
        $value = '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function qa_money($value)
{
    return number_format((float) $value, 2);
}

function qa_current_year_row()
{
    static $year = false;
    if ($year !== false) {
        return $year;
    }

    $db = qa_db();
    $result = $db->query("SELECT * FROM qa_years WHERE is_active = 1 ORDER BY is_current DESC, fiscal_year_be DESC LIMIT 1");
    $year = $result ? $result->fetch_assoc() : null;
    if ($result) {
        $result->free();
    }
    return $year;
}

$pageTitle = isset($pageTitle) ? $pageTitle : QA_APP_NAME;
$activeMenu = isset($activeMenu) ? $activeMenu : '';
$pageHeading = isset($pageHeading) ? $pageHeading : $pageTitle;
$pageDescription = isset($pageDescription) ? $pageDescription : '';
$breadcrumbs = isset($breadcrumbs) ? $breadcrumbs : array();
