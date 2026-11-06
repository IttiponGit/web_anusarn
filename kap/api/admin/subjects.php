<?php
/** KAP Admin / Subject catalog JSON API: authenticated System Admin only. */
declare(strict_types=1);
ob_start();
try {
    require_once dirname(__DIR__,2).'/app/admin/load.php';
    \Kap\Teacher\Bootstrap::start();
    \Kap\Admin\Security::requirePost();
    $db=\Kap\Teacher\Bootstrap::db();
    $actor=\Kap\Admin\Security::actor($db,'users');
    $catalog=new \Kap\Admin\SubjectCatalog($db,$actor);
    $action=(string)($_POST['action']??'');
    $result=match($action){
        'save-curriculum-hours'=>$catalog->saveCurriculumHours($_POST),
        'update-subject-with-hours'=>$catalog->saveUnifiedSubject($_POST),
        default=>$catalog->save($action,$_POST),
    };
    ob_end_clean();
    \Kap\Admin\Security::json(['ok'=>true]+$result);
}catch(\Throwable $e){
    if(ob_get_level()>0)ob_end_clean();
    $status=$e instanceof \Kap\Teacher\WebError?$e->status:500;
    $ref=bin2hex(random_bytes(6));
    $stage=isset($catalog)?$catalog->currentStage():'subject-api-initialize';
    $state=null;$errno=null;
    if($e instanceof \PDOException){
        $candidate=$e->errorInfo[0]??null;
        if(is_string($candidate)&&preg_match('/^[A-Z0-9]{5}$/D',$candidate))$state=$candidate;
        $candidate=$e->errorInfo[1]??null;
        if(is_numeric($candidate))$errno=(int)$candidate;
    }
    if($status===500)error_log('KAP subject API ref='.$ref.' stage='.$stage.' error='.get_class($e).' state='.($state??'-').' errno='.($errno??'-'));
    $res=['ok'=>false,'message'=>$status===500?'ไม่สามารถบันทึกทะเบียนรายวิชาได้ (รหัสอ้างอิง '.$ref.')':$e->getMessage()];
    if($e instanceof \Kap\Admin\FieldError){$res['field']=$e->field;$res['hint']=$e->hint;}
    else $res['hint']=$status===500?'แจ้งผู้ดูแลระบบพร้อมรหัสอ้างอิง เพื่อตรวจ PHP Error Log':($status===419?'โหลดหน้านี้ใหม่แล้วลองอีกครั้ง':'ตรวจสิทธิ์และข้อมูลรายการ แล้วลองใหม่');
    if($status===500 && isset($actor)&&in_array('SYSTEM_ADMIN',$actor['Roles']??[],true)){
        $res['stage']=$stage;
        if($state!==null)$res['sqlstate']=$state;
        if($errno!==null)$res['db_errno']=$errno;
    }
    http_response_code($status);header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
    echo json_encode($res,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;
}
