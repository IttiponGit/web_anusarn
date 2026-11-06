<?php
declare(strict_types=1);
/** Legacy link kept only for compatibility: โครงสร้างรายวิชาตามหลักสูตรสถานศึกษา must use the unified KAP system.
 * No writes are accepted here. The old V2 controller/extra template has been retired.
 */
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
use Kap\Teacher\{Bootstrap,WebError};
try {
    Bootstrap::start();
    $actor=\Kap\Auth\Auth::requireLogin();
    if (!in_array('SYSTEM_ADMIN', (array)($actor['Roles']??[]), true))
        throw new WebError(403,'บัญชีไม่มีสิทธิ์เปิดหน้าแอดมิน');
    if (($_SERVER['REQUEST_METHOD']??'GET') !== 'GET')
        throw new WebError(405,'หน้านี้เลิกใช้แล้ว กรุณาบันทึกผ่านหน้าใหม่');
    header('Location: '.rtrim(Bootstrap::config()['base_path'],'/').'/pages/admin/index.php',true,303);
    exit;
} catch (\Throwable $e) {Bootstrap::fail($e);}
