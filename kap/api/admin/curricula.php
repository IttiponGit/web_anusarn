<?php
declare(strict_types=1);
ob_start();
try {
    require_once dirname(__DIR__,2).'/app/admin/load.php';
    \Kap\Teacher\Bootstrap::start();
    \Kap\Admin\Security::requirePost();
    $db=\Kap\Teacher\Bootstrap::db();
    $actor=\Kap\Admin\Security::actor($db,'users');
    $service=new \Kap\Admin\CurriculumCatalog($db,$actor);
    $result=$service->save((string)($_POST['action']??''),$_POST);
    ob_end_clean();
    \Kap\Admin\Security::json(['ok'=>true]+$result);
}catch(\Throwable $e){
    if(ob_get_level()>0)ob_end_clean();
    $status=$e instanceof \Kap\Teacher\WebError?$e->status:500;
    $ref=bin2hex(random_bytes(6));
    $stage=isset($service)?$service->currentStage():'curriculum-api-init';
    $state=null;$errno=null;
    if($e instanceof \PDOException){
        $candidate=$e->errorInfo[0]??null;
        if(is_string($candidate)&&preg_match('/^[A-Z0-9]{5}$/D',$candidate))$state=$candidate;
        $candidate=$e->errorInfo[1]??null;
        if(is_numeric($candidate))$errno=(int)$candidate;
    }
    if($status===500)error_log('KAP Curriculum ref='.$ref.' stage='.$stage.' type='.get_class($e).' state='.($state??'-').' errno='.($errno??'-'));
    $result=['ok'=>false,'message'=>$status===500?'ไม่สามารถบันทึกข้อมูลหลักสูตรสถานศึกษาได้ (รหัสอ้างอิง '.$ref.')':$e->getMessage()];
    if($e instanceof \Kap\Admin\FieldError){$result['field']=$e->field;$result['hint']=$e->hint;}
    else $result['hint']=$status===500?'แจ้งผู้ดูแลระบบพร้อมรหัสอ้างอิงเพื่อตรวจ PHP Error Log':'ตรวจข้อมูลและสิทธิ์ จากนั้นโหลดหน้าใหม่';
    if($status===500&&isset($actor)&&in_array('SYSTEM_ADMIN',$actor['Roles']??[],true)){
        $result['stage']=$stage;
        if($state!==null)$result['sqlstate']=$state;
        if($errno!==null)$result['db_errno']=$errno;
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
