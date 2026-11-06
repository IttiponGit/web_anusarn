<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/config/bootstrap.php';
use Kap\Teacher\{Bootstrap,WebError,PlanRules};
try {
    Bootstrap::start();
    header('Content-Type: application/json; charset=UTF-8');
    if (($_SERVER['REQUEST_METHOD']??'GET') !== 'POST') throw new WebError(405,'POST เท่านั้น');
    if (Bootstrap::mode() !== 'demo') throw new WebError(403,'ยังไม่อนุญาตแก้แผนคะแนนฐานข้อมูลจริงขณะที่ไม่มี Login');
    if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json')) throw new WebError(415,'ต้องส่ง application/json');
    $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
    if($origin!==''){
        $scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
        $host=(string)($_SERVER['HTTP_HOST']??'');
        if($origin!==$scheme.'://'.$host) throw new WebError(403,'Origin ไม่ตรง');
    }
    $raw=file_get_contents('php://input');
    if(!is_string($raw)||strlen($raw)>120000) throw new WebError(413,'ข้อมูลใหญ่เกินไป');
    try {$payload=json_decode($raw,true,30,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new WebError(422,'JSON ไม่ถูกต้อง');}
    if(!is_array($payload)) throw new WebError(422,'ข้อมูลไม่ถูกต้อง');
    Bootstrap::verifyCsrf(is_string($payload['csrf']??null)?$payload['csrf']:null);
    $classId=Bootstrap::id($payload['subject_class_id']??null);
    $draft=Bootstrap::repo()->savePlanDraft($classId,$payload);
    $checked=PlanRules::validate($payload);
    echo json_encode(['ok'=>true,'revision'=>$draft['draft_revision'],'saved_at'=>$draft['saved_at'],'balanced'=>$checked['balanced'],'total'=>$checked['total'],'message'=>'บันทึกร่างใน Session สำเร็จ (ไม่แก้ฐานข้อมูลจริง)'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(\Throwable $e){ Bootstrap::fail($e,true); }
