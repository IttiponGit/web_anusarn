<?php
/** KAP registry actions: authenticated SYSTEM_ADMIN and same-origin CSRF-protected POST only. */
declare(strict_types=1);
ob_start();
try {
    require_once dirname(__DIR__,2).'/config/bootstrap.php';
    require_once dirname(__DIR__,2).'/app/admin/FieldError.php';
    require_once dirname(__DIR__,2).'/app/admin/Security.php';
    require_once dirname(__DIR__,2).'/app/teacher/StudentRegistry.php';
    \Kap\Teacher\Bootstrap::start();
    \Kap\Admin\Security::requirePost();
    $db=\Kap\Teacher\Bootstrap::db();
    $actor=\Kap\Auth\Auth::requireLogin(true);
    if(!in_array('SYSTEM_ADMIN',$actor['Roles']??[],true))throw new \Kap\Teacher\WebError(403,'เฉพาะผู้ดูแลระบบจึงแก้ไขทะเบียนนักเรียนได้');
    $registry=new \Kap\Teacher\StudentRegistry($db,$actor);
    $result=$registry->save((string)($_POST['action']??''),$_POST);
    ob_end_clean();\Kap\Admin\Security::json(['ok'=>true]+$result);
}catch(\Throwable $e){
    if(ob_get_level()>0)ob_end_clean();
    $status=$e instanceof \Kap\Teacher\WebError?$e->status:500;
    $ref=bin2hex(random_bytes(6));$stage=isset($registry)?$registry->stage():'registry-api-initialize';
    $sqlstate=null;$errno=null;
    if($e instanceof \PDOException){
        $code=$e->errorInfo[0]??null;if(is_string($code)&&preg_match('/^[A-Z0-9]{5}$/D',$code))$sqlstate=$code;
        $n=$e->errorInfo[1]??null;if(is_numeric($n))$errno=(int)$n;
    }
    if($status===500)error_log('KAP student registry ref='.$ref.' stage='.$stage.' class='.get_class($e).' state='.($sqlstate??'-').' errno='.($errno??'-').' location='.basename($e->getFile()).':'.$e->getLine());
    http_response_code($status);header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
    $body=['ok'=>false,'message'=>$status===500?'ไม่สามารถบันทึกทะเบียนนักเรียนได้ (รหัสอ้างอิง '.$ref.')':$e->getMessage()];
    if($e instanceof \Kap\Admin\FieldError){$body['field']=$e->field;$body['hint']=$e->hint;}
    elseif($status===500){$body['hint']='แจ้งผู้ดูแลระบบพร้อมรหัสอ้างอิง เพื่อตรวจ PHP Error Log';}
    elseif($status===419){$body['hint']='โหลดหน้าใหม่แล้วลองบันทึกอีกครั้ง';}
    else{$body['hint']='ตรวจข้อมูลและสิทธิ์ผู้ใช้งานก่อนดำเนินการใหม่';}
    if($status===500 && isset($actor) && in_array('SYSTEM_ADMIN',$actor['Roles']??[],true)){
        $body['stage']=$stage;
        if($sqlstate!==null)$body['sqlstate']=$sqlstate;
        if($errno!==null)$body['db_errno']=$errno;
    }
    echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;
}
