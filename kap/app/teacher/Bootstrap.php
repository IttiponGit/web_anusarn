<?php
declare(strict_types=1);
namespace Kap\Teacher;
use PDO;
use RuntimeException;

class WebError extends RuntimeException {
    public function __construct(public int $status, string $message) {parent::__construct($message);}
}
final class Bootstrap {
    private static ?array $config=null;
    private static ?PDO $db=null;
    public static function config(): array {
        if (self::$config!==null) return self::$config;
        $cfg=require dirname(__DIR__,2).'/config/testing.php';
        if (!is_array($cfg) || !in_array($cfg['mode']??null,['demo','database'],true)) throw new RuntimeException('Invalid testing.php mode');
        $path=(string)($cfg['base_path']??'/kap');
        if(!preg_match('~^/[a-zA-Z0-9/_-]+$~D',$path))throw new RuntimeException('Invalid base_path');
        $path=rtrim($path,'/');
        $cfg['routes']=['dashboard'=>$path.'/', 'score-kap'=>$path.'/pages/scores/kap.php','plan'=>$path.'/pages/scores/plan.php','login'=>$path.'/pages/auth/login.php'];
        $cfg['score_save_url']=$path.'/api/scores/save.php';
        $cfg['assets']=['css'=>$path.'/assets/css/kap-theme.css','js'=>$path.'/assets/js/kap-app.js'];
        return self::$config=$cfg;
    }
    public static function mode(): string {return self::config()['mode'];}
    /** In database mode every browser request must hold a verified SystemUser session. */
    public static function start(): void {
        \Kap\Auth\Auth::startSession();
        if(self::mode()==='database') \Kap\Auth\Auth::requireLogin(
            str_starts_with((string)($_SERVER['SCRIPT_NAME']??''),rtrim(self::config()['base_path'],'/').'/api/')
        );
        header('Content-Type: text/html; charset=UTF-8');
    }
    public static function db(): PDO {
        if(self::mode()!=='database')throw new WebError(403,'Demo does not connect MariaDB');
        if(self::$db)return self::$db;
        $path=dirname(__DIR__,2).'/config/local.php';
        if(!is_file($path)) throw new WebError(503,'ยังไม่มี config/local.php สำหรับต่อฐานข้อมูลจริง');
        $raw=require $path;
        if(!is_array($raw))throw new RuntimeException('Invalid local.php');
        $cfg=is_array($raw['db']??null)?$raw['db']:$raw;
        foreach(['host','database','username','password'] as $key) if(!isset($cfg[$key])||!is_string($cfg[$key]))throw new RuntimeException('Invalid DB config: '.$key);
        if(!preg_match('/^[a-zA-Z0-9_.:-]+$/D',$cfg['host'])||!preg_match('/^[a-zA-Z0-9_]+$/D',$cfg['database']))throw new RuntimeException('Invalid DB host/name');
        $port=(int)($cfg['port']??3306); if($port<1||$port>65535)throw new RuntimeException('Invalid DB port');
        return self::$db=new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',$cfg['host'],$port,$cfg['database']),$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    }
    public static function actor(?PDO $db=null): array {
        if(self::mode()==='demo')return ['Teacher_id'=>null,'Username'=>'ผู้ทดสอบ DEMO','SystemUser_id'=>null,'Roles'=>[]];
        return \Kap\Auth\Auth::requireLogin();
    }
    public static function rosterWriterActor(?PDO $db=null): array {
        if(self::mode()!=='database' || (self::config()['allow_real_roster_writes']??false)!==true)
            throw new WebError(403,'ยังปิดการเขียนทะเบียนรายวิชาจริงอยู่');
        $actor=\Kap\Auth\Auth::requireLogin(true);
        if(!array_intersect($actor['Roles'],['SUBJECT_TEACHER','SYSTEM_ADMIN']))
            throw new WebError(403,'ไม่มีบทบาทสำหรับแก้ไขทะเบียนรายวิชา');
        return $actor;
    }
    public static function repo(): Repo|DemoRepo {
        if(self::mode()==='demo')return new DemoRepo();
        $db=self::db();
        return new Repo($db,\Kap\Auth\Auth::requireLogin());
    }
    public static function csrf(): string {
        if(!isset($_SESSION['kap_test_csrf']))$_SESSION['kap_test_csrf']=bin2hex(random_bytes(32));
        return (string)$_SESSION['kap_test_csrf'];
    }
    public static function verifyCsrf(?string $token):void {
        if(!$token||!hash_equals(self::csrf(),$token))throw new WebError(419,'CSRF ไม่ถูกต้อง');
    }
    public static function id(mixed $raw):int {
        if(!(is_int($raw)||is_string($raw))||!ctype_digit((string)$raw)||(int)$raw<1)throw new WebError(422,'ID ไม่ถูกต้อง');
        return (int)$raw;
    }
    public static function escape(mixed $v):string {return htmlspecialchars((string)($v??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    public static function fail(\Throwable $e,bool $json=false):never {
        $status=$e instanceof WebError?$e->status:500;
        $ref = null;
        if (!($e instanceof WebError)) {
            $ref=bin2hex(random_bytes(6));
            error_log('KAP error ref='.$ref.' class='.get_class($e).' SQLSTATE='.(($e instanceof \PDOException)?(string)($e->errorInfo[0]??'unknown'):'n/a'));
        }
        http_response_code($status);header('Cache-Control: no-store');
        $msg=$e instanceof WebError?$e->getMessage():'ระบบไม่สามารถเปิดหน้านี้ได้ (รหัสอ้างอิง '.$ref.') กรุณาแจ้งผู้ดูแลเพื่อตรวจ Server Error Log';
        if($json){header('Content-Type: application/json; charset=UTF-8');echo json_encode(['ok'=>false,'message'=>$msg],JSON_UNESCAPED_UNICODE);exit;}
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="th"><meta charset="utf-8"><title>KAP</title><body style="font-family:system-ui;padding:3rem"><h2>'.self::escape($msg).'</h2><p><a href="'.self::escape(self::config()['routes']['dashboard']).'">กลับ Dashboard</a></p></body></html>';exit;
    }
}
