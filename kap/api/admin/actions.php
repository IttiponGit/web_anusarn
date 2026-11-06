<?php
/** KAP Admin JSON API. Keep this actual file at /kap/api/admin/actions.php. */
declare(strict_types=1);

// Protect JSON output from incidental HTML/warnings generated during bootstrap.
ob_start();
try {
    require_once dirname(__DIR__, 2).'/app/admin/load.php';
    \Kap\Teacher\Bootstrap::start();
    // Request verification and admin role checks are deliberately server-side.
    \Kap\Admin\Security::requirePost();
    $db=\Kap\Teacher\Bootstrap::db();
    $action=(string)($_POST['action']??'');
    $capability=in_array($action,['assign-teacher','disable-assignment'],true)?'assignments':'users';
    $actor=\Kap\Admin\Security::actor($db,$capability);
    $handler=new \Kap\Admin\Actions($db,$actor);
    $result=$handler->run($action,$_POST);
    ob_end_clean();
    \Kap\Admin\Security::json(['ok'=>true]+$result);
} catch (\Throwable $e) {
    // Errors occurring even while loading classes must return JSON, not an HTML page.
    if(ob_get_level()>0) ob_end_clean();
    $isWebError=$e instanceof \Kap\Teacher\WebError;
    $status=$isWebError ? $e->status : 500;
    // Do not leak PDO error messages, SQL, identities or credentials to the browser.
    // The reference and stage make it possible to correlate with the PHP error log.
    $ref=bin2hex(random_bytes(6));
    $phase=isset($handler) ? $handler->currentStage() : 'initialize-backend';
    $sqlState=null;
    $dbErrno=null;
    if($e instanceof \PDOException) {
        $state=$e->errorInfo[0]??$e->getCode();
        if(is_string($state) && preg_match('/^[A-Z0-9]{5}$/D',$state)) $sqlState=$state;
        $dbCode=$e->errorInfo[1]??null;
        if(is_int($dbCode) || (is_string($dbCode)&&ctype_digit($dbCode))) $dbErrno=(int)$dbCode;
    }
    if(!$isWebError) {
        error_log(sprintf('KAP admin API ref=%s stage=%s exception=%s sqlstate=%s db_errno=%s source=%s:%d',
            $ref,$phase,get_class($e),$sqlState??'-',$dbErrno??'-',basename($e->getFile()),$e->getLine()));
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    $message=$isWebError ? $e->getMessage() : 'เกิดข้อผิดพลาดใน Backend (รหัสอ้างอิง '.$ref.')';
    $response=['ok'=>false,'message'=>$message];
    if($e instanceof \Kap\Admin\FieldError){
        // Stable, non-sensitive form-field identifier and actionable guidance.
        $response['field']=$e->field;
        $response['hint']=$e->hint;
    } elseif($isWebError) {
        $response['hint']=match($status){
            401=>'เข้าสู่ระบบใหม่ด้วยบัญชีที่ได้รับอนุญาต แล้วลองอีกครั้ง',
            403=>'ให้ผู้ดูแลระบบตรวจบทบาทและสิทธิ์ก่อนทำรายการ',
            404=>'โหลดหน้าข้อมูลใหม่ แล้วตรวจว่ารายการที่เลือกยังมีอยู่',
            409=>'ตรวจรายการที่มีอยู่ก่อนแล้วแก้ไขข้อมูลเดิม แทนการบันทึกซ้ำ',
            419=>'โหลดหน้านี้ใหม่แล้วส่งข้อมูลอีกครั้ง',
            422=>'ตรวจช่องข้อมูลที่ยังว่างหรือกรอกไม่ครบ แล้วลองใหม่',
            default=>'โหลดหน้าใหม่แล้วลองอีกครั้ง หากยังผิดพลาดให้ติดต่อผู้ดูแลระบบ',
        };
    } else {
        $response['hint']='ระบบยังไม่สามารถบันทึกข้อมูลได้ กรุณาอย่ากดซ้ำหลายครั้ง ให้ผู้ดูแลตรวจบันทึกข้อผิดพลาดโดยใช้รหัสอ้างอิงข้างต้น';
    }
    // Only an already-verified SYSTEM_ADMIN may see the non-sensitive diagnostic codes.
    if(!$isWebError && isset($actor) && in_array('SYSTEM_ADMIN',$actor['Roles']??[],true)) {
        $response['stage']=$phase;
        if($sqlState!==null) $response['sqlstate']=$sqlState;
        if($dbErrno!==null) $response['db_errno']=$dbErrno;
        $response['message'].=' [ขั้นตอน: '.$phase.($sqlState!==null?', SQLSTATE '.$sqlState:'').($dbErrno!==null?', MariaDB '.$dbErrno:'').']';
    }
    echo json_encode($response,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
