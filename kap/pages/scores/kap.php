<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/config/bootstrap.php';
use Kap\Teacher\{Bootstrap,Ui,WebError};
try {
 Bootstrap::start();$repo=Bootstrap::repo();$actor=Bootstrap::mode()==='demo'?Bootstrap::actor():Bootstrap::actor(Bootstrap::db());
 $adminMode=Bootstrap::mode()==='database' && in_array('SYSTEM_ADMIN',(array)($actor['Roles']??[]),true);
 $all=Bootstrap::mode()==='database'?$repo->scoreSubjects():$repo->subjects();$id=isset($_GET['subject_class_id'])?Bootstrap::id($_GET['subject_class_id']):null;
 if(!$id){Ui::page('กรอกคะแนน K/A/P',$actor,'score-kap',Ui::scorePage($all,null,null,[],[],[],null,$adminMode,false));exit;}
 $sub=$repo->subject($id,$adminMode);$plan=$repo->currentPlan($id);$items=[];$students=[];$scores=[];$warning=null;$readOnlyReason=null;
 if(!$plan)$warning='ยังไม่มีแผนคะแนน ACTIVE สำหรับกลุ่มนี้';
 else {try{$repo->requireReady($id,$sub,$plan);}catch(WebError $e){$warning=$e->getMessage();}
 if(!$warning){if($adminMode){$readOnlyReason=$repo->scoreWriteBlockReason($id,$plan);} $items=$repo->scoreItems($id,(int)$plan['ScorePlan_id']);$students=$repo->enrollments($id,(int)$sub['AcademicYear_id']);$scores=$repo->scores($id,(int)$plan['ScorePlan_id']);if(!$items)$warning='ไม่พบรายการ K/A/P';}}
 Ui::page('กรอกคะแนน K/A/P',$actor,'score-kap',Ui::scorePage($all,$sub,$plan,$items,$students,$scores,$warning,$adminMode,$adminMode && $readOnlyReason===null,$readOnlyReason));
} catch (\Throwable $e){Bootstrap::fail($e);}
