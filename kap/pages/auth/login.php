<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/config/bootstrap.php';
use Kap\Auth\Auth;
use Kap\Teacher\{Bootstrap,WebError};
try {
    if(Bootstrap::mode()!=='database') {header('Location: '.Bootstrap::config()['routes']['dashboard'],true,302);exit;}
    Auth::startSession();
    if(Auth::current()) {header('Location: '.Auth::safeNext($_GET['next']??null),true,303);exit;}
    $next=Auth::safeNext($_GET['next']??null);
    $error=(string)($_SESSION['kap_login_error']??'');unset($_SESSION['kap_login_error']);
    $e=static fn($s)=>Bootstrap::escape($s);
    $base=Auth::base();
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>เข้าสู่ระบบ KAP</title><link rel="stylesheet" href="'.$e($base.'/assets/css/kap-theme.css').'"><link rel="stylesheet" href="'.$e($base.'/assets/css/kap-auth.css?v=1').'"></head><body class="kap-auth-bg"><main class="kap-auth-card"><div class="kap-auth-mark">K</div><h1>เข้าสู่ระบบ KAP</h1><p>School Academic Platform · โรงเรียนโสตศึกษาอนุสารสุนทร</p>';
    if($error!=='')echo '<div class="kap-auth-error" role="alert">'.$e($error).'</div>';
    echo '<form method="post" action="'.$e($base.'/api/auth/login.php').'" autocomplete="on"><input type="hidden" name="csrf" value="'.$e(Auth::csrf()).'"><input type="hidden" name="next" value="'.$e($next).'"><label for="username">ชื่อผู้ใช้งาน</label><input id="username" name="username" autocomplete="username" maxlength="80" required autofocus><label for="password">รหัสผ่าน</label><input id="password" type="password" name="password" autocomplete="current-password" required><button type="submit">เข้าสู่ระบบ</button></form><small>สำหรับบัญชีครูและผู้ดูแลที่ได้รับสิทธิ์เท่านั้น</small></main></body></html>';
} catch(\Throwable $e){Bootstrap::fail($e);}
