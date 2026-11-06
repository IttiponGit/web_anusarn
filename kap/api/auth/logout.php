<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/config/bootstrap.php';
use Kap\Auth\Auth;
use Kap\Teacher\{Bootstrap,WebError};
try {
    if(Bootstrap::mode()!=='database')throw new WebError(403,'โหมดนี้ไม่มี Login');
    Auth::startSession();Auth::verifyPost();Auth::logout();
    header('Location: '.Auth::loginUrl(),true,303);exit;
} catch(\Throwable $e){Bootstrap::fail($e);}
