<?php
/** KAP Phase 2.6.1: grade-scoped subject listing with a unified edit link. */
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Phase26Core.php';
use KAP\Phase26\Core as C;
try {
    [$pdo, $user, $base] = C::boot();
    $years = C::years($pdo);
    $ay = C::academicYear($pdo);
    $yearId = (int)$ay['AcademicYear_id'];
    $curricula = C::curricula($pdo, $yearId); // newest year DESC
    $curriculumId = C::positiveId($_GET['curriculum_id'] ?? null);
    $validIds = array_map(static fn($c) => (int)$c['Curriculum_id'], $curricula);
    if (!in_array($curriculumId, $validIds, true)) {
        $curriculumId = 0;
        foreach ($curricula as $c) {
            if ((int)$c['GradeCount'] > 0) { $curriculumId = (int)$c['Curriculum_id']; break; }
        }
    }
    $grades = $curriculumId ? C::grades($pdo, $yearId, $curriculumId) : [];
    $gradeId = C::positiveId($_GET['grade_id'] ?? null);
    $gradeIds = array_map(static fn($g) => (int)$g['GradeLevel_id'], $grades);
    if (!in_array($gradeId, $gradeIds, true)) { $gradeId = $gradeIds[0] ?? 0; }
    $sectionId = C::positiveId($_GET['section_id'] ?? null);
    $selectedCurriculum = null;
    foreach ($curricula as $c) {
        if ((int)$c['Curriculum_id'] === $curriculumId) { $selectedCurriculum = $c; break; }
    }
    $selectedGrade = null;
    foreach ($grades as $g) {
        if ((int)$g['GradeLevel_id'] === $gradeId) { $selectedGrade = $g; break; }
    }
    $rows = [];
    if ($curriculumId && $gradeId) {
        $sql = 'SELECT cs.CurriculumSubject_id,cs.Subject_id,cs.Credit,cs.WeeklyHours,
                       cs.TotalInstructionalHours,cs.ReviewStatus,cs.IsActive,
                       s.Subject_code,s.Subject_name,sec.SectionName,sec.SortOrder AS SectionSortOrder
                  FROM `CurriculumSubject` cs
                  JOIN `Subject` s ON s.Subject_id=cs.Subject_id
                  LEFT JOIN `CurriculumSection` sec ON sec.CurriculumSection_id=cs.CurriculumSection_id
                 WHERE cs.Curriculum_id=? AND cs.GradeLevel_id=?';
        $args = [$curriculumId,$gradeId];
        if ($sectionId) { $sql .= ' AND cs.CurriculumSection_id=?'; $args[] = $sectionId; }
        $sql .= ' ORDER BY sec.SortOrder,sec.CurriculumSection_id,s.Subject_name,cs.CurriculumSubject_id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($args);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $drafts = count(array_filter($rows, static fn($r) => $r['ReviewStatus']==='DRAFT' && (int)$r['IsActive']===0));
} catch (Throwable $e) {
    error_log('[KAP P26 subjects] '.$e->getMessage());
    http_response_code(503);
    exit('ไม่สามารถแสดงรายวิชาตามหลักสูตรสถานศึกษาได้ กรุณาตรวจสอบบันทึกระบบ');
}
C::renderStart('รายวิชาตามหลักสูตรสถานศึกษา', 'subjects', $base, $ay, $years);
?>
<div class="kap-card"><h2>ตัวกรองรายการรายวิชา</h2>
  <form method="get" class="kap-form">
    <input type="hidden" name="year_id" value="<?= $yearId ?>">
    <div class="kap-field"><label for="curriculum_id">หลักสูตรสถานศึกษา</label><select id="curriculum_id" name="curriculum_id">
      <?php foreach ($curricula as $c): ?><option value="<?= (int)$c['Curriculum_id'] ?>" <?= $curriculumId===(int)$c['Curriculum_id']?'selected':'' ?>><?= C::h(C::title($c['Curriculum_name'])) ?></option><?php endforeach; ?>
    </select></div>
    <div class="kap-field"><label for="grade_id">ระดับชั้น</label><select id="grade_id" name="grade_id">
      <?php if (!$grades): ?><option value="">ยังไม่กำหนดระดับชั้น</option><?php endif; ?>
      <?php foreach ($grades as $g): ?><option value="<?= (int)$g['GradeLevel_id'] ?>" <?= $gradeId===(int)$g['GradeLevel_id']?'selected':'' ?>><?= C::h($g['GradeName']) ?></option><?php endforeach; ?>
    </select></div>
    <div><button class="kap-btn" type="submit">แสดงรายวิชา</button></div>
  </form>
</div>
<?php if (!$selectedGrade): ?>
  <?php C::note('ไม่มีระดับชั้นที่ผูกกับหลักสูตรสถานศึกษานี้ในปีที่เลือก'); ?>
<?php else: ?>
  <div class="kap-summary"><div class="kap-stat"><strong><?= count($rows) ?></strong><small>รายวิชา/กิจกรรมในตัวกรอง</small></div><div class="kap-stat"><strong><?= $drafts ?></strong><small>รายการรอตรวจรับรอง DRAFT</small></div></div>
  <div class="kap-card"><h2><?= C::h(C::title($selectedCurriculum['Curriculum_name'] ?? '')) ?> · <?= C::h($selectedGrade['GradeName']) ?></h2>
    <p class="kap-muted">คลิก “แก้ไข” เพื่อแก้ทะเบียนรายวิชาและชั่วโมงเรียนในหนึ่งการ์ด โดยใช้ปุ่มบันทึกเพียงปุ่มเดียว</p>
    <div class="kap-table-wrap"><table><thead><tr><th>ชื่อวิชา / กิจกรรม</th><th>รหัสวิชา</th><th>หมวดหลักสูตรสถานศึกษา</th><th>หน่วยกิต</th><th>ชม./สัปดาห์</th><th>ชม./ปี</th><th>สถานะ</th><th>ดำเนินการ</th></tr></thead><tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><span class="kap-strong"><?= C::h($r['Subject_name']) ?></span><br><span class="kap-dim">CurriculumSubject_id #<?= (int)$r['CurriculumSubject_id'] ?></span></td>
          <td><?= trim((string)($r['Subject_code']??''))!=='' ? C::h($r['Subject_code']) : '<span class="kap-dim">ไม่มีรหัส</span>' ?></td>
          <td><?= C::h($r['SectionName'] ?: 'ไม่ระบุหมวด') ?></td>
          <td class="kap-num"><?= C::h($r['Credit']) ?></td><td class="kap-num"><?= C::h($r['WeeklyHours']) ?></td><td class="kap-num"><?= C::h($r['TotalInstructionalHours']) ?></td>
          <td><span class="kap-pill<?= (int)$r['IsActive']===1?' kap-pill--on':'' ?>"><?= C::h($r['ReviewStatus']) ?></span></td>
          <td><a href="<?= C::h(rtrim($base,'/') . '/pages/admin/phase26-unified-subject-edit.php?' . http_build_query([
                  'subject_id'=>(int)$r['Subject_id'], 'year_id'=>$yearId,
                  'curriculum_id'=>$curriculumId,'grade_id'=>$gradeId,
                  'section_id'=>$sectionId ?: null
                ], '', '&', PHP_QUERY_RFC3986)) ?>">แก้ไข</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8">ยังไม่มีรายการรายวิชาในตัวกรองนี้</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
<?php endif; ?>
<?php C::renderEnd(); ?>
