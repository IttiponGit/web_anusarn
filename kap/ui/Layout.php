<?php
declare(strict_types=1);
/**
 * Shared presentational shell ONLY. Does not log in users or authorize requests.
 * Existing pages MUST check session and permissions before calling kap_ui_start().
 * Integration: include once, then kap_ui_start([...]); page contents; kap_ui_end().
 */

function kap_ui_h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Return a deterministic normalized path, without query/fragment. */
function kap_ui_path(string $url): string {
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') { return '/'; }
    $path = preg_replace('~/+~', '/', $path) ?? '/';
    return $path === '/' ? '/' : rtrim($path, '/');
}

function kap_ui_base(): string {
    // If installed elsewhere, set KAP_UI_BASE to the real path prefix before this include.
    if (defined('KAP_UI_BASE')) {
        return rtrim((string)KAP_UI_BASE, '/');
    }
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/kap/index.php'));
    $pos = strpos($script, '/pages/');
    if ($pos !== false) { return rtrim(substr($script, 0, $pos), '/'); }
    $base = dirname($script);
    return $base === '/' || $base === '.' ? '' : rtrim($base, '/');
}

/** Verify route targets have exactly one local URL prefix; no external/javascript links. */
function kap_ui_href(?string $route, string $base): ?string {
    if ($route === null || $route === '' || $route[0] !== '/' || str_starts_with($route, '//')) {
        return null;
    }
    if (preg_match('/[\\x00-\\x20\\x7F\\?\\#\\\\]/', $route) || str_contains($route, '..')) {
        return null;
    }
    return rtrim($base, '/') . $route;
}

/** Registry is common to ALL pages; active key comes from current file rather than CSS selectors. */
function kap_ui_groups(): array {
    return [
        ['label'=>'ภาพรวม', 'items'=>[
            ['key'=>'teacher_dashboard','label'=>'Dashboard ครู','icon'=>'dashboard'],
            ['key'=>'admin_dashboard','label'=>'ภาพรวมแอดมิน','icon'=>'grid'],
        ]],
        ['label'=>'ข้อมูลพื้นฐาน', 'items'=>[
            ['key'=>'subject_catalog','label'=>'ทะเบียนรายวิชาและกิจกรรม','icon'=>'book'],
            ['key'=>'curriculum_subjects','label'=>'รายวิชาตามหลักสูตรสถานศึกษา','icon'=>'book'],
            ['key'=>'students','label'=>'ทะเบียนนักเรียน','icon'=>'users'],
        ]],
        ['label'=>'การจัดการเรียนการสอน', 'items'=>[
            ['key'=>'score_entry','label'=>'กรอกคะแนน K/A/P','icon'=>'scores'],
            ['key'=>'teaching_assignment','label'=>'มอบหมายรายวิชา','icon'=>'assignment'],
        ]],
        ['label'=>'ผู้ใช้และทะเบียน', 'items'=>[
            ['key'=>'users','label'=>'จัดการผู้ใช้งาน','icon'=>'user-cog'],
        ]],
        ['label'=>'กำลังพัฒนา', 'items'=>[
            ['key'=>'attendance','label'=>'บันทึกเวลาเรียน','icon'=>'clock','future'=>true],
            ['key'=>'traits','label'=>'คุณลักษณะ','icon'=>'check','future'=>true],
            ['key'=>'competencies','label'=>'สมรรถนะ','icon'=>'check','future'=>true],
            ['key'=>'reading','label'=>'อ่าน คิด เขียน','icon'=>'book','future'=>true],
            ['key'=>'scores_overview','label'=>'ภาพรวมคะแนน','icon'=>'grid','future'=>true],
            ['key'=>'quality','label'=>'คุณภาพการศึกษา','icon'=>'check','future'=>true],
            ['key'=>'pp5','label'=>'พิมพ์ ปพ.5','icon'=>'book','future'=>true],
        ]],
    ];
}

function kap_ui_icon(string $name): string {
    $paths = [
      'dashboard' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M7 15a5 5 0 0 1 10 0M12 15l3-3"/>',
      'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
      'book' => '<path d="M4 4h6a3 3 0 0 1 3 3v14a3 3 0 0 0-3-3H4zM20 4h-4a3 3 0 0 0-3 3v14a3 3 0 0 1 3-3h4z"/>',
      'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 5a3 3 0 0 1 0 6M17 14a5 5 0 0 1 4 5v1"/>',
      'scores' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 10h18M10 3v18M15 15h3M16.5 13.5v3"/>',
      'assignment' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5h6M9 11h6M9 15h6"/>',
      'user-cog' => '<circle cx="9" cy="8" r="3"/><path d="M3 19a6 6 0 0 1 10-4"/><circle cx="18" cy="18" r="3"/>',
      'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
      'check' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="m7 12 3 3 6-6"/>',
    ];
    $path = $paths[$name] ?? $paths['grid'];
    return '<svg aria-hidden="true" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
}

/**
 * Options:
 *  title, subtitle, username, active (stable key), roleCodes (verified from RBAC),
 *  visibleKeys (EXPLICIT keys authorized by existing page/backend; null to use safe defaults),
 *  routes (map from checked existing PHP files), logoutAction (POST path), csrfToken,
 *  stylesheets (legacy page CSS paths; loaded before shared layout CSS), scripts (legacy local JS paths).
 * All feature permissions MUST still be enforced in backend PHP, not this view.
 */
function kap_ui_start(array $options = []): void {
    $routes = $options['routes'] ?? require __DIR__ . '/routes.php';
    if (!is_array($routes)) { $routes = []; }
    $base = kap_ui_base();
    $roles = array_values(array_filter((array)($options['roleCodes'] ?? []), 'is_string'));
    $visible = $options['visibleKeys'] ?? ['teacher_dashboard','score_entry','students'];
    if (!is_array($visible)) { $visible = []; }
    if (in_array('SUBJECT_CATALOG_ADMIN', $roles, true)) { $visible[] = 'subject_catalog'; $visible[] = 'curriculum_subjects'; }
    $visible = array_unique($visible);
    $validKeys = [];
    foreach (kap_ui_groups() as $group) {
        foreach ($group['items'] as $item) { $validKeys[$item['key']] = true; }
    }
    $active = (string)($options['active'] ?? '');
    if (!isset($validKeys[$active])) {
        $current = kap_ui_path((string)($_SERVER['REQUEST_URI'] ?? ''));
        $active = '';
        foreach ($routes as $key => $route) {
            $link = kap_ui_href(is_string($route) ? $route : null, $base);
            if ($link !== null && kap_ui_path($link) === $current && isset($validKeys[$key])) {
                $active = (string)$key;
                break;
            }
        }
    }
    $title = (string)($options['title'] ?? 'KAP');
    $subtitle = (string)($options['subtitle'] ?? 'ระบบบริหารงานวิชาการ');
    $username = (string)($options['username'] ?? '');
    $logout = kap_ui_href(isset($options['logoutAction']) ? (string)$options['logoutAction'] : null, $base);
    $csrf = (string)($options['csrfToken'] ?? '');
    $css = kap_ui_h(rtrim($base, '/') . '/assets/kap-unified-layout.css');
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . kap_ui_h($title) . ' | KAP</title>';
    foreach ((array)($options['stylesheets'] ?? []) as $resource) {
        $href = is_string($resource) ? kap_ui_href($resource, $base) : null;
        if ($href !== null) { echo '<link rel="stylesheet" href="' . kap_ui_h($href) . '">'; }
    }
    echo '<link rel="stylesheet" href="' . $css . '"></head><body class="kap-ui-body">';
    echo '<div class="kap-ui-layout"><aside class="kap-ui-sidebar" id="kap-ui-sidebar">';
    echo '<div class="kap-ui-brand"><div class="kap-ui-logo">K</div><div><strong>KAP</strong><small>School Academic Platform</small></div></div>';
    echo '<nav class="kap-ui-nav" aria-label="เมนูหลัก">';
    foreach (kap_ui_groups() as $group) {
        $html = '';
        foreach ($group['items'] as $item) {
            $key = $item['key'];
            $future = (bool)($item['future'] ?? false);
            if (!$future && !in_array($key, $visible, true)) { continue; }
            $link = $future ? null : kap_ui_href(isset($routes[$key]) && is_string($routes[$key]) ? $routes[$key] : null, $base);
            $selected = $key === $active;
            $label = kap_ui_h($item['label']);
            $icon = kap_ui_icon($item['icon']);
            if ($link === null) {
                $html .= '<span class="kap-ui-nav-item kap-ui-disabled" title="' . ($future ? 'อยู่ระหว่างพัฒนา' : 'ยังไม่กำหนดเส้นทางไฟล์จริง') . '" aria-disabled="true">' . $icon . '<span>' . $label . '</span></span>';
            } else {
                $html .= '<a class="kap-ui-nav-item' . ($selected ? ' kap-ui-active' : '') . '" href="' . kap_ui_h($link) . '"' . ($selected ? ' aria-current="page"' : '') . '>' . $icon . '<span>' . $label . '</span></a>';
            }
        }
        if ($html !== '') { echo '<div class="kap-ui-nav-group"><p class="kap-ui-nav-heading">' . kap_ui_h($group['label']) . '</p>' . $html . '</div>'; }
    }
    echo '</nav><div class="kap-ui-sidebar-foot">ยืนยันตัวตนผ่านระบบ KAP</div></aside>';
    echo '<div class="kap-ui-right"><header class="kap-ui-topbar"><button class="kap-ui-toggle" type="button" aria-label="เปิดหรือปิดเมนู" aria-controls="kap-ui-sidebar" aria-expanded="false">☰</button>';
    echo '<div class="kap-ui-school"><strong>โรงเรียนโสตศึกษาอนุสารสุนทร</strong><small>' . kap_ui_h($title) . '</small></div>';
    echo '<div class="kap-ui-account"><span class="kap-ui-user">' . kap_ui_icon('user-cog') . kap_ui_h($username) . '</span>';
    if ($logout !== null && $csrf !== '') {
        echo '<form action="' . kap_ui_h($logout) . '" method="post"><input type="hidden" name="csrf" value="' . kap_ui_h($csrf) . '"><button type="submit" class="kap-ui-logout">ออกจากระบบ</button></form>';
    }
    echo '</div></header><main id="main-content" class="kap-ui-main"><div class="kap-ui-heading"><h1>' . kap_ui_h($title) . '</h1>';
    if ($subtitle !== '') { echo '<p>' . kap_ui_h($subtitle) . '</p>'; }
    echo '</div>';
}
function kap_ui_end(array $options = []): void {
    echo '</main></div></div>';
    $base = kap_ui_base();
    foreach ((array)($options['scripts'] ?? []) as $resource) {
        $src = is_string($resource) ? kap_ui_href($resource, $base) : null;
        if ($src !== null) { echo '<script src="' . kap_ui_h($src) . '" defer></script>'; }
    }
    echo '<script>(function(){const b=document.querySelector(".kap-ui-toggle");const s=document.querySelector(".kap-ui-sidebar");if(!b||!s)return;b.addEventListener("click",function(){const open=s.classList.toggle("kap-ui-open");b.setAttribute("aria-expanded",open?"true":"false")});})();</script></body></html>';
}
