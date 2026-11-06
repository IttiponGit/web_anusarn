<?php

declare(strict_types=1);

// Keep the public friendly URL while reusing the established public page markup.
$pagePath = __DIR__ . '/pages/academic.html';
$html = is_file($pagePath) ? (string) file_get_contents($pagePath) : '';
if ($html === '') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'ไม่สามารถโหลดหน้าข้อมูลวิชาการได้';
    exit;
}

$basePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/information/academic.php'))), '/');
$baseHref = htmlspecialchars($basePath . '/pages/', ENT_QUOTES, 'UTF-8');
$html = preg_replace('/<head>/', '<head>' . "\n  <base href=\"{$baseHref}\">", $html, 1) ?? $html;

header('Content-Type: text/html; charset=utf-8');
echo $html;
