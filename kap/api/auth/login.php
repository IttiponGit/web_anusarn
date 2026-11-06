<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/config/bootstrap.php';
use Kap\Auth\Auth;
use Kap\Teacher\{Bootstrap,WebError};
try {
    if(Bootstrap::mode()!=='database')throw new WebError(403,'เปิด Login ได้เฉพาะโหมด database');
    Auth::startSession();Auth::verifyPost();
    if((int)($_SERVER['CONTENT_LENGTH']??0)>4096)throw new WebError(413,'ข้อมูลใหญ่เกินไป');
    $next=Auth::safeNext($_POST['next']??null);
    $name=$_POST['username']??null;$pass=$_POST['password']??null;
    if(!is_string($name)||!is_string($pass))throw new WebError(422,'รูปแบบข้อมูลไม่ถูกต้อง');
    Auth::login(Bootstrap::db(),trim($name),$pass);
    header('Location: '.$next,true,303);exit;
} catch(WebError $e){
    if(session_status()===PHP_SESSION_ACTIVE){$_SESSION['kap_login_error']=$e->getMessage();}
    $status=$e->status===429?429:303;
    header('Location: '.Auth::loginUrl(),true,$status===429?303:$status);exit;
} catch(\Throwable $e){error_log('KAP login failure: '.get_class($e));Bootstrap::fail($e);}
