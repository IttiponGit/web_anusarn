<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/config/bootstrap.php';
use Kap\Teacher\{Bootstrap,WebError};
try {
    Bootstrap::start();
    header('Content-Type: application/json; charset=UTF-8');
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')throw new WebError(405,'ต้องใช้ POST');
    if(!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json')) throw new WebError(415,'ต้องส่ง JSON');
    $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
    if($origin!==''){
        $scheme=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http';
        if($origin!==$scheme.'://'.(string)($_SERVER['HTTP_HOST']??''))throw new WebError(403,'Origin ไม่ตรง');
    }
    $raw=file_get_contents('php://input');
    if(!is_string($raw)||strlen($raw)>110000)throw new WebError(413,'ข้อมูลรายการใหญ่เกินไป');
    try{$data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new WebError(422,'JSON ไม่ถูกต้อง');}
    if(!is_array($data))throw new WebError(422,'ข้อมูลไม่ถูกต้อง');
    Bootstrap::verifyCsrf(is_string($data['csrf']??null)?$data['csrf']:null);
    $classId=Bootstrap::id($data['subject_class_id']??null);
    if(Bootstrap::mode()==='demo') {
        $draft=Bootstrap::repo()->saveRosterDraft($classId,$data);
        $result=['ok'=>true,'revision'=>$draft['draft_revision'], 'selected_count'=>count($draft['selected_ids']), 'saved_at'=>$draft['saved_at'], 'message'=>'บันทึกรายชื่อนักเรียนฉบับร่างใน Session แล้ว'];
    } else {
        $actor=Bootstrap::rosterWriterActor(Bootstrap::db());
        $result=['ok'=>true]+Bootstrap::repo()->saveRoster($classId,$data,$actor);
        $result['message']='เพิ่มนักเรียนเข้ารายวิชาจริงเรียบร้อยแล้ว (ไม่ลบทะเบียนเดิม)';
        $result['revision']=0;
    }
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch(\Throwable $e){Bootstrap::fail($e,true);}
