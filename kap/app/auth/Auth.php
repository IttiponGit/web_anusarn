<?php
declare(strict_types=1);
namespace Kap\Auth;

use Kap\Teacher\Bootstrap;
use Kap\Teacher\WebError;
use PDO;

/** Database-backed sign-in for KAP; never treats a teacher_id in testing.php as authentication. */
final class Auth
{
    private const IDLE_SECONDS = 1800;
    private const ABSOLUTE_SECONDS = 43200;
    private const ROTATE_SECONDS = 900;
    private static bool $loaded = false;
    private static ?array $user = null;

    public static function base(): string {return rtrim((string)(Bootstrap::config()['base_path']??'/kap'), '/');}
    public static function loginUrl(): string {return self::base().'/pages/auth/login.php';}
    public static function logoutUrl(): string {return self::base().'/api/auth/logout.php';}
    private static function key(): string {
        $key=(string)(Bootstrap::config()['authenticated_user_session_key']??'kap_authenticated_user_id');
        if(!preg_match('/^[A-Za-z_][A-Za-z_0-9]{0,63}$/D',$key)) throw new WebError(503,'ตั้งค่าชื่อ Session ไม่ถูกต้อง');
        return $key;
    }
    private static function https(): bool {
        if(!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS'])!=='off')return true;
        if((int)($_SERVER['SERVER_PORT']??0)===443)return true;
        // Enable only when HTTPS terminates at a proxy controlled by the host administrator.
        if((Bootstrap::config()['https_terminated_at_proxy']??false)===true){
            return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))==='https';
        }
        return false;
    }
    public static function startSession(): void {
        if(session_status()!==PHP_SESSION_ACTIVE){
            if(Bootstrap::mode()==='database' && !self::https()) throw new WebError(403,'ต้องเปิดระบบ Login ผ่าน HTTPS เท่านั้น (หาก Hosting ใช้ HTTPS Proxy ให้ตรวจค่า https_terminated_at_proxy)');
            ini_set('session.use_strict_mode','1');
            ini_set('session.use_only_cookies','1');
            ini_set('session.use_trans_sid','0');
            session_name(Bootstrap::mode()==='database'?'KAP_AUTH_SESSION':'KAP_TEST_SESSION');
            session_set_cookie_params([
                'lifetime'=>0, 'path'=>self::base().'/', 'secure'=>Bootstrap::mode()==='database',
                'httponly'=>true, 'samesite'=>'Strict'
            ]);
            session_start();
        }
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Content-Security-Policy: frame-ancestors \'none\'; base-uri \'self\'; form-action \'self\'');
        if(Bootstrap::mode()==='database')header('Strict-Transport-Security: max-age=31536000');
        if(Bootstrap::mode()!=='database') return;
        $id=$_SESSION[self::key()]??null;
        if($id===null) return;
        $now=time();
        $created=$_SESSION['kap_login_at']??0;
        $seen=$_SESSION['kap_last_seen']??0;
        if(!is_int($created)||!is_int($seen)||$created<1||$seen<1||
           $now-$created>self::ABSOLUTE_SECONDS||$now-$seen>self::IDLE_SECONDS||
           !hash_equals((string)($_SESSION['kap_ua']??''),hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??'')))) {
            self::clear();return;
        }
        $_SESSION['kap_last_seen']=$now;
        if($now-(int)($_SESSION['kap_last_rotate']??0)>self::ROTATE_SECONDS){
            session_regenerate_id(true);
            $_SESSION['kap_last_rotate']=$now;
        }
    }
    public static function csrf(): string {return Bootstrap::csrf();}
    public static function verifyPost(): void {
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')throw new WebError(405,'ต้องใช้ POST');
        $site=strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE']??''));
        if(in_array($site,['cross-site','none'],true))throw new WebError(403,'คำขอมาจากเว็บไซต์อื่น');
        $host=strtolower((string)($_SERVER['HTTP_HOST']??''));
        $host=explode(':',$host)[0];
        if(!preg_match('/^[a-z0-9.-]+$/D',$host))throw new WebError(403,'Host ไม่ถูกต้อง');
        foreach(['HTTP_ORIGIN','HTTP_REFERER'] as $name){
            $source=(string)($_SERVER[$name]??'');
            if($source==='')continue;
            $parts=parse_url($source);
            if(!is_array($parts)||strtolower((string)($parts['host']??''))!==$host||strtolower((string)($parts['scheme']??''))!=='https') {
                throw new WebError(403,'คำขอมาจากเว็บไซต์อื่น');
            }
        }
        Bootstrap::verifyCsrf(is_string($_POST['csrf']??null)?$_POST['csrf']:null);
    }
    public static function current(?PDO $db=null): ?array {
        if(self::$loaded)return self::$user;
        self::$loaded=true;
        $id=$_SESSION[self::key()]??null;
        if(!(is_int($id)||(is_string($id)&&ctype_digit($id)))||(int)$id<1)return null;
        $pdo=$db??Bootstrap::db();
        $stmt=$pdo->prepare('SELECT SystemUser_id, Username, Teacher_id, IsActive FROM SystemUser WHERE SystemUser_id=? LIMIT 1');
        $stmt->execute([(int)$id]);
        $user=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$user||(int)$user['IsActive']!==1){self::clear();return null;}
        $stmt=$pdo->prepare('SELECT DISTINCT r.RoleCode FROM SystemUserRole ur JOIN Role r ON r.Role_id=ur.Role_id WHERE ur.SystemUser_id=? AND ur.IsActive=1 AND r.IsActive=1');
        $stmt->execute([(int)$user['SystemUser_id']]);
        $user['Roles']=$stmt->fetchAll(PDO::FETCH_COLUMN);
        if(!$user['Roles']){self::clear();return null;}
        $citizen=$user['Teacher_id'];
        if($citizen!==null){
            $citizen=(string)$citizen;
            if(!preg_match('/^[0-9]{13}$/D',$citizen)){self::clear();return null;}
            $stmt=$pdo->prepare('SELECT IsActive FROM Teacher WHERE Teacher_id=? LIMIT 1');
            $stmt->execute([$citizen]);
            if((int)$stmt->fetchColumn()!==1){
                // A user with only a teacher role cannot access KAP without an active Teacher.
                if(!in_array('SYSTEM_ADMIN',$user['Roles'],true)){self::clear();return null;}
                $citizen=null;
            }
        }
        if($citizen===null && !array_intersect($user['Roles'],['SYSTEM_ADMIN','DIRECTOR','DEPUTY_ACADEMICS','HEAD_ASSESSMENT'])){
            self::clear();return null;
        }
        $user['Teacher_id']=$citizen;
        return self::$user=$user;
    }
    public static function requireLogin(bool $json=false): array {
        $user=self::current();
        if($user)return $user;
        if($json||in_array($_SERVER['REQUEST_METHOD']??'GET',['POST','PUT','PATCH','DELETE'],true))throw new WebError(401,'กรุณาเข้าสู่ระบบ KAP');
        $from=(string)($_SERVER['REQUEST_URI']??self::base().'/');
        $next=self::safeNext($from);
        header('Location: '.self::loginUrl().'?next='.rawurlencode($next),true,303);
        exit;
    }
    public static function safeNext(mixed $value): string {
        $fallback=self::base().'/';
        if(!is_string($value)||strlen($value)>1024||str_contains($value,"\r")||str_contains($value,"\n")||str_contains($value,'\\')||str_contains($value,'//'))return $fallback;
        $base=self::base();
        if(!($value===$base||str_starts_with($value,$base.'/')))return $fallback;
        if(str_contains($value,'/api/')||str_contains($value,'/auth/')||str_contains($value,'..'))return $fallback;
        return $value;
    }
    public static function login(PDO $db, string $username, string $password): array {
        if(!preg_match('/^[A-Za-z0-9._-]{3,80}$/D',$username)||strlen($password)<1||strlen($password)>512)
            throw new WebError(401,'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
        $now=time();
        $failed=array_values(array_filter($_SESSION['kap_login_failures']??[],static fn($t)=>is_int($t)&&$t>$now-900));
        if(count($failed)>=5)throw new WebError(429,'เข้าสู่ระบบผิดหลายครั้ง กรุณาลองอีกครั้งภายหลัง');
        $stmt=$db->prepare('SELECT SystemUser_id, Username, Teacher_id, PasswordHash, IsActive FROM SystemUser WHERE Username=? LIMIT 1');
        $stmt->execute([$username]);
        $user=$stmt->fetch(PDO::FETCH_ASSOC);
        $valid=$user && (int)$user['IsActive']===1 && is_string($user['PasswordHash']) && password_verify($password,$user['PasswordHash']);
        if(!$valid){usleep(250000);$failed[]=$now;$_SESSION['kap_login_failures']=$failed;throw new WebError(401,'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');}
        $stmt=$db->prepare('SELECT DISTINCT r.RoleCode FROM SystemUserRole ur JOIN Role r ON r.Role_id=ur.Role_id WHERE ur.SystemUser_id=? AND ur.IsActive=1 AND r.IsActive=1');
        $stmt->execute([(int)$user['SystemUser_id']]);
        $roles=$stmt->fetchAll(PDO::FETCH_COLUMN);
        if(!$roles)throw new WebError(403,'บัญชียังไม่มีบทบาทที่เปิดใช้งาน');
        if(!array_intersect($roles,['SYSTEM_ADMIN','DIRECTOR','DEPUTY_ACADEMICS','HEAD_ASSESSMENT'])){
            if(!is_string($user['Teacher_id'])||!preg_match('/^[0-9]{13}$/D',$user['Teacher_id']))throw new WebError(403,'บัญชียังไม่ได้ผูกกับครู');
            $stmt=$db->prepare('SELECT IsActive FROM Teacher WHERE Teacher_id=? LIMIT 1');
            $stmt->execute([$user['Teacher_id']]);
            if((int)$stmt->fetchColumn()!==1)throw new WebError(403,'บัญชีครูไม่ได้เปิดใช้งาน');
        }
        session_regenerate_id(true);
        $_SESSION=[];
        $_SESSION[self::key()]=(int)$user['SystemUser_id'];
        $_SESSION['kap_login_at']=$_SESSION['kap_last_seen']=$_SESSION['kap_last_rotate']=$now;
        $_SESSION['kap_ua']=hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??''));
        $_SESSION['kap_test_csrf']=bin2hex(random_bytes(32));
        self::$loaded=false;self::$user=null;
        return ['SystemUser_id'=>(int)$user['SystemUser_id'],'Username'=>(string)$user['Username'],'Teacher_id'=>$user['Teacher_id'],'Roles'=>$roles];
    }
    private static function clear(): void {
        foreach([self::key(),'kap_login_at','kap_last_seen','kap_last_rotate','kap_ua','kap_test_csrf'] as $k)unset($_SESSION[$k]);
        self::$loaded=false;self::$user=null;
    }
    public static function logout(): void {
        self::clear();$_SESSION=[];
        if(session_status()===PHP_SESSION_ACTIVE){
            $params=session_get_cookie_params();
            setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>$params['path'],'secure'=>$params['secure'],'httponly'=>true,'samesite'=>'Strict']);
            session_destroy();
        }
    }
}
