<?php
declare(strict_types=1);
namespace KAP;
use PDO;
final class SubjectV2Gate {
    public static function init(): array {
        Session::start('/kap');
        $db=\kap_db();
        $user=Auth::user($db);
        if(!$user){http_response_code(401);exit('กรุณาเข้าสู่ระบบ KAP ก่อน <a href="/kap/">หน้าแรก</a>');}
        $id=(int)$user['SystemUser_id'];
        if(!Authorization::hasRole($db,$id,'SUBJECT_CATALOG_ADMIN')){http_response_code(403);exit('403: บัญชีนี้ไม่มีสิทธิ์จัดการทะเบียนรายวิชา');}
        $roles=array_column(Auth::activeRoles($db,$id),'RoleCode');
        $st=$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='SubjectCatalogReview'");
        if((int)$st->fetchColumn()!==1){http_response_code(503);exit('กรุณารัน migration 001_subject_catalog_review.sql ก่อนเปิดหน้ารายวิชา V2');}
        return [$db,$user,$roles];
    }
    public static function flash(string $message): void { $_SESSION['kap_sub_v2_flash']=$message; }
    public static function getFlash(): string { $s=(string)($_SESSION['kap_sub_v2_flash']??'');unset($_SESSION['kap_sub_v2_flash']);return $s; }
    public static function redirect(string $url): never { header('Location: '.$url,true,303);exit; }
    public static function start(string $page,array $user,array $roles): void {
        require_once dirname(__DIR__).'/ui/Layout.php';
        \kap_ui_start([
            'title'=>$page==='curriculum'?'เชื่อมรายวิชากับหลักสูตรสถานศึกษา':'ทะเบียนรายวิชาและกิจกรรม',
            'subtitle'=>'KAP Admin V2 • ข้อมูลใหม่บันทึกเป็นฉบับร่างก่อนเสมอ',
            'username'=>(string)$user['Username'],
            'active'=>$page==='curriculum'?'curriculum_subjects':'subject_catalog',
            'roleCodes'=>$roles,
            'visibleKeys'=>['subject_catalog','curriculum_subjects'],
            'stylesheets'=>['/assets/subjects-v2.css'],
            // UI navigation targets; replace routes.php with actual routes for other existing pages.
            'routes'=>array_replace(require dirname(__DIR__).'/ui/routes.php',[
                'subject_catalog'=>'/pages/admin/subjects-v2.php',
                'curriculum_subjects'=>'/pages/admin/curriculum-subjects-v2.php',
            ]),
        ]);
        echo '<nav class="sub-v2-tabs"><a href="/kap/pages/admin/subjects-v2.php">ทะเบียนรายวิชา</a><a href="/kap/pages/admin/curriculum-subjects-v2.php">เชื่อมหลักสูตรสถานศึกษา</a><a href="/kap/">หน้าแรก KAP</a></nav>';
    }
    public static function end(): void { echo '<script src="/kap/assets/subjects-v2.js" defer></script>'; \kap_ui_end(); }
}
