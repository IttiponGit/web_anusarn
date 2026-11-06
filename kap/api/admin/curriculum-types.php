<?php
declare(strict_types=1);
ob_start();
try{
    require_once dirname(__DIR__,2).'/app/admin/load.php';
    require_once dirname(__DIR__,2).'/app/admin/CurriculumTypeReference.php';
    require_once dirname(__DIR__,2).'/app/admin/CurriculumTypeService.php';
    \Kap\Teacher\Bootstrap::start();
    \Kap\Admin\Security::requirePost();
    $db=\Kap\Teacher\Bootstrap::db();
    $actor=\Kap\Admin\Security::actor($db,'users');
    $service=new \Kap\Admin\CurriculumTypeService($db,$actor);
    $result=$service->save((string)($_POST['action']??''),$_POST);
    ob_end_clean();
    \Kap\Admin\Security::json(['ok'=>true]+$result);
}catch(\Throwable $e){
    if(ob_get_level()>0)ob_end_clean();
    $status=$e instanceof \Kap\Teacher\WebError?$e->status:500;
    $ref=bin2hex(random_bytes(6));
    $stage=isset($service)?$service->currentStage():'type-api-init';
    $state=null;$errno=null;
    if($e instanceof \PDOException){
        $candidate=$e->errorInfo[0]??null;
        if(is_string($candidate)&&preg_match('/^[A-Z0-9]{5}$/D',$candidate))$state=$candidate;
        $candidate=$e->errorInfo[1]??null;
        if(is_numeric($candidate))$errno=(int)$candidate;
    }
    if($status===500)error_log('KAP CourseTypes ref='.$ref.' stage='.$stage.' type='.get_class($e).' state='.($state??'-').' errno='.($errno??'-'));
    $output=['ok'=>false,'message'=>$status===500?'ไม่สามารถบันทึกประเภทรายวิชาได้ (รหัสอ้างอิง '.$ref.')':$e->getMessage()];
    if($e instanceof \Kap\Admin\FieldError){$output['field']=$e->field;$output['hint']=$e->hint;}
    else $output['hint']=$status===500?'แจ้งผู้ดูแลระบบพร้อมรหัสอ้างอิง เพื่อตรวจ PHP Error Log':'ตรวจข้อมูลและสิทธิ์แล้วลองใหม่';
    if($status===500 && isset($actor) && in_array('SYSTEM_ADMIN',$actor['Roles']??[],true)){
        $output['stage']=$stage;
        if($state!==null)$output['sqlstate']=$state;
        if($errno!==null)$output['db_errno']=$errno;
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($output,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;
}
