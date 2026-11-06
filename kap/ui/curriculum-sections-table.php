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
    $kapLinkedSubjects = CT::linksBySection($kapTableDb);
    $kapSectionGrades = CT::gradesBySection($kapTableDb);
} catch (Throwable $e) {
    error_log('[KAP curriculum sections table] ' . $e->getMessage());
    echo '<p class="kct-error" role="alert">ไม่สามารถอ่านข้อมูลหมวดหลักสูตรสถานศึกษาได้ กรุณาตรวจ PHP error log</p>';
    return;
}
$kapSelectedId = filter_var($_GET['curriculum_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$kapSelectedId = $kapSelectedId === false ? null : $kapSelectedId;
?>
<link rel="stylesheet" href="/kap/assets/kap-curriculum-display.css">
<form method="get" class="kct-filter">
  <label for="kct-selected-curriculum">เลือกหลักสูตรสถานศึกษา</label>
  <select name="curriculum_id" id="kct-selected-curriculum">
    <option value="">ทุกหลักสูตรสถานศึกษา</option>
    <?php foreach ($kapCurriculaRows as $curr): ?>
    <option value="<?=(int)$curr['Curriculum_id']?>" <?=($kapSelectedId===(int)$curr['Curriculum_id'])?'selected':''?>><?=CT::escape(CT::curriculumLabel($curr['Curriculum_name']))?></option>
    <?php endforeach; ?>
  </select><button type="submit">แสดงข้อมูล</button>
</form>
<div class="kct-overflow">
<table class="kct-table" aria-label="หมวดหลักสูตรสถานศึกษา">
<thead><tr>
    <th>หลักสูตรสถานศึกษา</th>
    <th>รายการหมวดหลักสูตรสถานศึกษา</th>
    <th>รายวิชา</th>
    <th>ระดับชั้น</th>
</tr></thead>
<tbody>
<?php $kapDisplayed=0; foreach ($kapCurriculaRows as $curr):
    if ($kapSelectedId !== null && $kapSelectedId !== (int)$curr['Curriculum_id']) { continue; }
    foreach ($curr['sections'] as $sec):
        $kapSid=(int)$sec['CurriculumSection_id'];
        $kapItems=$kapLinkedSubjects[$kapSid] ?? [];
        if (!$kapItems) { $kapItems=[null]; }
        foreach ($kapItems as $item): $kapDisplayed++;
?>
<tr>
  <td><?=CT::escape(CT::curriculumLabel($curr['Curriculum_name']))?></td>
  <td><div class="kct-category" style="--kct-depth:<?=(int)$sec['_depth']?>">
      <?php if ((int)$sec['_depth']>0): ?><span aria-hidden="true">↳ </span><?php endif; ?>
      <?=CT::escape($sec['SectionName'])?>
      <?php if (isset($sec['_warning'])): ?><span class="kct-warning">ตรวจหมวดแม่</span><?php endif; ?>
      </div></td>
  <td>
    <?php if ($item): ?>
      <?php if (trim((string)($item['Subject_code']??'')) !== ''): ?><span class="kct-code"><?=CT::escape($item['Subject_code'])?></span><?php endif; ?>
      <?=CT::escape($item['Subject_name'])?>
    <?php else: ?><span class="kct-muted">ยังไม่ได้เชื่อมรายวิชา</span><?php endif; ?>
  </td>
  <td>
    <?php if ($item): ?><?=CT::escape($item['GradeName'])?>
    <?php elseif (!empty($kapSectionGrades[$kapSid])): ?><?=CT::escape(implode(', ', array_keys($kapSectionGrades[$kapSid])))?>
    <?php else: ?><span class="kct-muted">ยังไม่กำหนดระดับชั้น</span><?php endif; ?>
  </td>
</tr>
<?php endforeach; endforeach; endforeach; ?>
<?php if ($kapDisplayed===0): ?><tr><td colspan="4">ยังไม่มีหมวดหลักสูตรสถานศึกษาที่ตรงกับตัวกรอง</td></tr><?php endif; ?>
</tbody>
</table>
</div>
