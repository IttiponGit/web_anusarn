<?php
declare(strict_types=1);
// Include only from an authenticated, authorized admin page.
if (!defined('KAP_CURRICULUM_VIEW_AUTHORIZED') || KAP_CURRICULUM_VIEW_AUTHORIZED !== true) {
    http_response_code(403);
    exit('Forbidden');
}
require_once dirname(__DIR__) . '/app/CurriculumDisplayTables.php';

use KAP\CurriculumDisplayTables as CT;

try {
    $kapTableDb = isset($db) && $db instanceof PDO ? $db :
                  (isset($pdo) && $pdo instanceof PDO ? $pdo : \kap_db());
    $kapCurriculaRows = CT::curricula($kapTableDb);
} catch (Throwable $e) {
    error_log('[KAP curricula table] ' . $e->getMessage());
    echo '<p class="kct-error" role="alert">ไม่สามารถอ่านข้อมูลทะเบียนหลักสูตรสถานศึกษาได้ กรุณาตรวจ PHP error log</p>';
    return;
}
?>
<link rel="stylesheet" href="/kap/assets/kap-curriculum-display.css">
<div class="kct-overflow">
<table class="kct-table" aria-label="ทะเบียนหลักสูตรสถานศึกษา">
<thead><tr>
    <th>หลักสูตรสถานศึกษา</th>
    <th>ช่วงหลักสูตรสถานศึกษา</th>
    <th>จำนวนหมวดหลักสูตรสถานศึกษา</th>
    <th>รายการหมวดหลักสูตรสถานศึกษา</th>
    <th>สถานะ/ดำเนินการ</th>
</tr></thead>
<tbody>
<?php foreach ($kapCurriculaRows as $curr): ?>
<tr>
<td><strong><?=CT::escape(CT::curriculumLabel($curr['Curriculum_name']))?></strong><span class="kct-muted">#<?=(int)$curr['Curriculum_id']?></span></td>
<td>
<?php if (empty($curr['periods'])): ?><span class="kct-muted">ยังไม่ผูกช่วงหลักสูตรสถานศึกษา</span>
<?php else: foreach ($curr['periods'] as $span=>$grades): ?>
    <div class="kct-period"><strong><?=CT::escape($span)?></strong>
      <small><?=CT::escape(implode(', ', array_keys($grades)))?></small></div>
<?php endforeach; endif; ?>
</td>
<td><strong><?=count($curr['sections'])?> รายการ</strong>
<span class="kct-muted"><?=(int)$curr['rootCount']?> หมวดหลัก / <?=(int)$curr['childCount']?> หมวดย่อย</span></td>
<td>
<?php if (!$curr['sections']): ?><span class="kct-muted">ยังไม่มีหมวดในฐานข้อมูล</span><?php endif; ?>
<?php foreach ($curr['sections'] as $sec): ?>
    <div class="kct-category" style="--kct-depth:<?=(int)$sec['_depth']?>">
        <?php if ((int)$sec['_depth']>0): ?><span aria-hidden="true">↳ </span><?php endif; ?>
        <?=CT::escape($sec['SectionName'])?>
        <?php if (isset($sec['_warning'])): ?><span class="kct-warning">ตรวจหมวดแม่</span><?php endif; ?>
    </div>
<?php endforeach; ?>
</td>
<td>
    <span class="kct-badge <?=(int)$curr['IsActive']===1?'kct-active':'kct-inactive'?>"><?=(int)$curr['IsActive']===1?'เปิดใช้งาน':'ร่าง/ปิดใช้งาน'?></span>
    <?php if (is_file(dirname(__DIR__) . '/pages/admin/curriculum-sections.php')): ?>
      <a class="kct-action-link" href="/kap/pages/admin/curriculum-sections.php?curriculum_id=<?=(int)$curr['Curriculum_id']?>">ดูหมวด</a>
    <?php endif; ?>
    <?php
      // Existing edit/enable/disable controls can be retained via this callback.
      // Callback signature: function(array $curriculumRow): void { ...existing actions... }
      if (isset($kapCurriculumActions) && is_callable($kapCurriculumActions)) {
          $kapCurriculumActions($curr);
      }
    ?>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$kapCurriculaRows): ?><tr><td colspan="5">ยังไม่มีหลักสูตรสถานศึกษา</td></tr><?php endif; ?>
</tbody>
</table>
</div>
