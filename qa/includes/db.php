<?php
if (!defined('QA_DB_HOST')) {
    die('Database configuration is not loaded.');
}

function qa_db()
{
    static $db = null;

    if ($db instanceof mysqli) {
        return $db;
    }

    $db = @new mysqli(QA_DB_HOST, QA_DB_USER, QA_DB_PASS, QA_DB_NAME);
    if ($db->connect_errno) {
        http_response_code(500);
        die('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาตรวจสอบ includes/config.php');
    }

    if (!$db->set_charset('utf8mb4')) {
        $db->query("SET NAMES utf8mb4");
    }

    return $db;
}

function qa_db_scalar($sql, $defaultValue)
{
    $db = qa_db();
    $result = $db->query($sql);
    if (!$result) {
        return $defaultValue;
    }
    $row = $result->fetch_row();
    $result->free();
    return ($row && isset($row[0]) && $row[0] !== null) ? $row[0] : $defaultValue;
}
