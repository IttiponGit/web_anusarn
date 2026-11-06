<?php
declare(strict_types=1);
namespace Kap\Ui;

require_once __DIR__ . '/Menu.php';

/** Shared KAP theme/template, called by both teacher and administrator pages.
 * This is a VIEW ONLY: Login, RBAC and CSRF checks remain with the current Backend.
 */
final class Theme
{
    private static function e(mixed $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function start(string $title, string $active, array $actor, bool $admin = false): void
    {
        // In HTTP database mode, the authenticated SystemUser is the sole source of
        // Username and Roles. Never trust a role map supplied by a legacy view.
        // PHP CLI-only smoke tests may render this template with synthetic actors;
        // web requests (including PHP's cli-server SAPI) must authenticate.
        if (\Kap\Teacher\Bootstrap::mode() === 'database' && PHP_SAPI !== 'cli') {
            $actor = \Kap\Auth\Auth::requireLogin();
        }
        $base = rtrim((string)(\Kap\Teacher\Bootstrap::config()['base_path'] ?? '/kap'), '/');
        $roles = (array)($actor['Roles'] ?? []);
        if ($admin && !in_array('SYSTEM_ADMIN', $roles, true)) {
            throw new \Kap\Teacher\WebError(403, 'บัญชีไม่มีสิทธิ์เปิดหน้าผู้ดูแลระบบ');
        }
        $user = self::e($actor['Username'] ?? '');
        $logout = \Kap\Teacher\Bootstrap::mode() === 'database'
            ? \Kap\Auth\Auth::logoutUrl() : null;
        $csrf = $logout !== null ? \Kap\Teacher\Bootstrap::csrf() : '';
        $label = in_array('SYSTEM_ADMIN', $roles, true) ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน';
        $hbase = self::e($base);
        echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>'.self::e($title).' | KAP</title>';
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
        echo '<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">';
        echo '<link href="'.$hbase.'/assets/css/kap-theme.css?v=kap-unified-1" rel="stylesheet">';
        echo '<link href="'.$hbase.'/assets/css/kap-admin.css?v=kap-unified-1" rel="stylesheet">';
        echo '<link href="'.$hbase.'/assets/css/kap-shell.css?v=kap-unified-1" rel="stylesheet">';
        echo '<script src="'.$hbase.'/assets/js/kap-notify.js" defer></script>';
        echo '<script src="'.$hbase.'/assets/js/kap-app.js?v=admin-score-write-1" defer></script>';
        if ($admin) echo '<script src="'.$hbase.'/assets/js/kap-admin.js?v=7-curriculum-catalog" defer></script>';
        echo '</head><body class="kap-unified"><div class="kap-shell">';
        echo '<aside class="kap-sidebar" id="kapSidebar"><div class="kap-brand"><div class="brand-mark">K</div><div><div class="fw-bold fs-5">KAP</div><small>School Academic Platform</small></div></div>';
        echo '<nav aria-label="เมนูหลักของระบบ KAP">';
        foreach (Menu::groups() as $group) {
            $entries = [];
            foreach ($group['items'] as $item) {
                if (!Menu::visible($item, $roles)) continue;
                $icon = '<i class="bi bi-'.self::e($item['icon']).'" aria-hidden="true"></i>';
                $name = '<span>'.self::e($item['title']).'</span>';
                if ($item['path'] === null) {
                    $entries[] = '<span class="kap-nav kap-nav-pending" aria-disabled="true" title="กำลังพัฒนา">'.$icon.$name.'</span>';
                } else {
                    $url = Menu::href($base, $item['path']);
                    $on = $active === $item['id'];
                    $entries[] = '<a class="kap-nav'.($on?' active':'').'" href="'.self::e($url).'"'.($on?' aria-current="page"':'').'>'.$icon.$name.'</a>';
                }
            }
            if ($entries) {
                echo '<div class="kap-nav-caption">'.self::e($group['title']).'</div>'.implode('', $entries);
            }
        }
        echo '</nav><div class="sidebar-note">'.($logout!==null?'เข้าสู่ระบบผ่าน SystemUser':'โหมดทดลอง KAP').'<br><small>เมนูแสดงตามบทบาท · Backend ตรวจสิทธิ์จริง</small></div></aside>';
        echo '<div class="kap-main"><header class="kap-topbar">';
        echo '<div class="kap-header-ident"><button class="btn btn-light d-lg-none" type="button" id="kapSidebarBtn" aria-label="เปิดหรือปิดเมนู" aria-controls="kapSidebar" aria-expanded="false"><i class="bi bi-list"></i></button><div><strong>โรงเรียนโสตศึกษาอนุสารสุนทร</strong><div class="kap-meta">'.self::e($title).'</div></div></div>';
        echo '<div class="kap-header-account"><span class="badge rounded-pill text-bg-light">'.self::e($label).'</span><span class="user-chip"><i class="bi bi-person-circle"></i> '.$user.'</span>';
        if ($logout !== null) {
            echo '<form method="post" action="'.self::e($logout).'" class="kap-logout-form"><input type="hidden" name="csrf" value="'.self::e($csrf).'"><button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</button></form>';
        }
        echo '</div></header><main class="kap-page" id="main-content">';
    }

    public static function end(): void
    {
        echo '</main></div></div></body></html>';
    }
}
