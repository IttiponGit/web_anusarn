<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/config/bootstrap.php';
use Kap\Teacher\{Bootstrap,WebError};
try {
 Bootstrap::start();header('Content-Type: application/json; charset=UTF-8');
 if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')throw new WebError(405,'POST เท่านั้น');
 if(!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json'))throw new WebError(415,'ต้องส่ง JSON');
 $databaseMode=Bootstrap::mode()==='database';
 if($databaseMode && !in_array('SYSTEM_ADMIN',(array)(Bootstrap::actor()['Roles']??[]),true))
     throw new WebError(403,'ในระยะนี้เฉพาะ SYSTEM_ADMIN เท่านั้นที่บันทึกคะแนนจริงได้');
 if($databaseMode){
     $site=strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE']??''));
     if(in_array($site,['cross-site','none'],true))throw new WebError(403,'คำขอมาจากเว็บไซต์อื่น');
     $host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
     foreach(['HTTP_ORIGIN','HTTP_REFERER'] as $header){
         $value=(string)($_SERVER[$header]??'');if($value==='')continue;
         $url=parse_url($value);
         if(!is_array($url)||strtolower((string)($url['host']??''))!==$host||strtolower((string)($url['scheme']??''))!=='https')
             throw new WebError(403,'คำขอมาจากเว็บไซต์อื่น');
     }
 } else {
     $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
     if($origin!==''){$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';$host=(string)($_SERVER['HTTP_HOST']??'');if($origin!==$scheme.'://'.$host)throw new WebError(403,'Origin ไม่ตรง');}
 }
 $input=file_get_contents('php://input');if(!is_string($input)||strlen($input)>220000)throw new WebError(413,'ข้อมูลใหญ่เกินไป');
 try{$data=json_decode($input,true,32,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw new WebError(422,'JSON ไม่ถูกต้อง');}
 if(!is_array($data)||!isset($data['changes'])||!is_array($data['changes']))throw new WebError(422,'ข้อมูลไม่ถูกต้อง');
 Bootstrap::verifyCsrf(is_string($data['csrf']??null)?$data['csrf']:null);
 $repo=Bootstrap::repo();
 $saved=$databaseMode
     ? $repo->save(Bootstrap::id($data['subject_class_id']??null),Bootstrap::id($data['score_plan_id']??null),$data['changes'],is_string($data['reason']??null)?$data['reason']:'')
     : $repo->save(Bootstrap::id($data['subject_class_id']??null),Bootstrap::id($data['score_plan_id']??null),$data['changes']);
 echo json_encode(['ok'=>true,'saved'=>$saved,'message'=>$databaseMode?'บันทึกคะแนนลง MariaDB พร้อม AuditLog สำเร็จ '.$saved.' ช่อง':'บันทึกคะแนนตัวอย่างใน Session สำเร็จ '.$saved.' ช่อง (ไม่ได้แก้ฐานข้อมูลจริง)'],JSON_UNESCAPED_UNICODE);
} catch(\Throwable $e){Bootstrap::fail($e,true);}
