<?php
// ระบบประกันคุณภาพสถานศึกษา - Bootstrap สำหรับ PHP 5.6+

define('QA_ROOT', dirname(__DIR__));
define('QA_BASE_URL', '/qa');
define('QA_APP_NAME', 'ระบบประกันคุณภาพสถานศึกษา');
define('QA_SCHOOL_NAME', 'โรงเรียนโสตศึกษาอนุสารสุนทร');
date_default_timezone_set('Asia/Bangkok');

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
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/project_workflow.php';
require_once __DIR__ . '/project_change.php';
require_once __DIR__ . '/obec.php';
require_once __DIR__ . '/sar.php';
require_once __DIR__ . '/activity.php';

function qa_request_scalar($source, $key, $defaultValue = '')
{
    if (!is_array($source) || !array_key_exists($key, $source)) {
        return $defaultValue;
    }

    $value = $source[$key];
    return is_scalar($value) ? (string) $value : $defaultValue;
}

function qa_uploaded_file_entry($files, $key)
{
    if (!is_array($files) || !isset($files[$key]) || !is_array($files[$key])) {
        return null;
    }

    $file = $files[$key];
    foreach (array('error', 'name', 'tmp_name', 'size') as $field) {
        if (!array_key_exists($field, $file) || !is_scalar($file[$field])) {
            return null;
        }
    }

    return $file;
}

function qa_upload_mime_allowed($extension, $mimeType)
{
    $allowed = array(
        'jpg' => array('image/jpeg'),
        'jpeg' => array('image/jpeg'),
        'png' => array('image/png'),
        'webp' => array('image/webp'),
        'pdf' => array('application/pdf'),
        'doc' => array('application/msword', 'application/x-ole-storage', 'application/octet-stream'),
        'docx' => array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'),
        'xls' => array('application/vnd.ms-excel', 'application/x-ole-storage', 'application/octet-stream'),
        'xlsx' => array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream')
    );

    return isset($allowed[$extension]) && in_array($mimeType, $allowed[$extension], true);
}

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

function qa_division_theme_class($divisionCode)
{
    $divisionCode = strtoupper(trim((string) $divisionCode));
    $map = array(
        'ACADEMIC' => 'division-academic',
        'BUDGET' => 'division-budget',
        'PERSONNEL' => 'division-personnel',
        'GENERAL' => 'division-general',
        'STUDENT_AFFAIRS' => 'division-student-affairs'
    );
    return isset($map[$divisionCode]) ? $map[$divisionCode] : 'division-neutral';
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
