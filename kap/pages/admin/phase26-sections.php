<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Phase26Core.php';
use KAP\Phase26\Core as C;
try {
    [$pdo, $user, $base] = C::boot();
    $years = C::years($pdo);
    $ay = C::academicYear($pdo);
    $yearId = (int) $ay['AcademicYear_id'];
    $curricula = C::curricula($pdo, $yearId);
    $curriculumId = C::positiveId($_GET['curriculum_id'] ?? null);
    $ids = array_map(static fn($r) => (int)$r['Curriculum_id'], $curricula);
    if ($curriculumId && !in_array($curriculumId, $ids, true)) { $curriculumId = 0; }
    if (!$curriculumId && $curricula) {
        foreach ($curricula as $c) { if ((int)$c['GradeCount'] > 0) { $curriculumId=(int)$c['Curriculum_id']; break; } }
    }
    $grades = $curriculumId ? C::grades($pdo, $yearId, $curriculumId) : [];
    $gradeId = C::positiveId($_GET['grade_id'] ?? null);
    $gradeIds = array_map(static fn($r) => (int)$r['GradeLevel_id'], $grades);
    if ($gradeId && !in_array($gradeId, $gradeIds, true)) { $gradeId = 0; }
    if (!$gradeId && $grades) { $gradeId = (int)$grades[0]['GradeLevel_id']; }
    $selectedGrade = null;
    foreach ($grades as $grade) { if ((int)$grade['GradeLevel_id'] === $gradeId) { $selectedGrade = $grade; break; } }
    $selectedCurriculum = null;
    foreach ($curricula as $c) { if ((int)$c['Curriculum_id'] === $curriculumId) { $selectedCurriculum = $c; break; } }
    $rows = [];
    if ($curriculumId && $gradeId) {
        $stmt = $pdo->prepare('SELECT sec.CurriculumSection_id, sec.ParentSection_id,sec.SectionCode,sec.SectionName,sec.SortOrder,sec.IsActive,
                      sg.SortOrder AS GradeSortOrder
                 FROM `CurriculumSectionGrade` sg
                 JOIN `CurriculumSection` sec ON sec.CurriculumSection_id=sg.CurriculumSection_id
                WHERE sec.Curriculum_id=? AND sg.GradeLevel_id=? AND sg.IsActive=1
                ORDER BY sg.SortOrder,sec.SortOrder,sec.CurriculumSection_id');
        $stmt->execute([$curriculumId,$gradeId]);
        $direct = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare('SELECT CurriculumSection_id,ParentSection_id,SectionCode,SectionName,SortOrder,IsActive
                                 FROM `CurriculumSection` WHERE Curriculum_id=? ORDER BY SortOrder,CurriculumSection_id');
        $stmt->execute([$curriculumId]);
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $children = [];
        foreach ($all as $r) { $children[(int)($r['ParentSection_id'] ?? 0)][] = $r; }
        $seen = [];
        $append = function(array $r, int $depth, int $baseSort) use (&$append, &$seen, &$rows, $children): void {
            $id = (int) $r['CurriculumSection_id'];
            if (isset($seen[$id]) || $depth > 15) { return; }
            $seen[$id] = true;
            $r['Depth'] = $depth;
            $r['GradeSortOrder'] = $baseSort;
            $rows[] = $r;
            foreach ($children[$id] ?? [] as $child) { $append($child, $depth + 1, $baseSort); }
        };
        foreach ($direct as $r) { $append($r, 0, (int)$r['GradeSortOrder']); }
    }
    $subjectCounts = [];
    if ($curriculumId && $gradeId) {
        $stmt = $pdo->prepare('SELECT CurriculumSection_id,COUNT(*) AS n
                     FROM `CurriculumSubject` WHERE Curriculum_id=? AND GradeLevel_id=?
                     GROUP BY CurriculumSection_id');
        $stmt->execute([$curriculumId,$gradeId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $subjectCounts[(int)($r['CurriculumSection_id'] ?? 0)] = (int)$r['n']; }
    }
} catch (Throwable $e) {
    error_log('[KAP P26 sections] ' . $e->getMessage());
    http_response_code(503);
    exit('ไม่สามารถแสดงหมวดหลักสูตรสถานศึกษาได้ กรุณาตรวจสอบบันทึกระบบ');
}
C::renderStart('หมวดหลักสูตรสถานศึกษา', 'sections', $base, $ay, $years);
?>
<div class="kap-card">
    <h2>เลือกหลักสูตรสถานศึกษาและระดับชั้น</h2>
    <form method="get" class="kap-form">
        <input type="hidden" name="year_id" value="<?= $yearId ?>">
        <div class="kap-field"><label for="curriculum_id">หลักสูตรสถานศึกษา</label>
            <select name="curriculum_id" id="curriculum_id" required>
                <?php foreach ($curricula as $c): ?><option value="<?= (int)$c['Curriculum_id'] ?>" <?= $curriculumId === (int)$c['Curriculum_id'] ? 'selected' : '' ?>><?= C::h(C::title($c['Curriculum_name'])) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="kap-field"><label for="grade_id">ระดับชั้น</label>
            <select name="grade_id" id="grade_id">
                <?php if (!$grades): ?><option value="">ยังไม่ได้กำหนดระดับชั้นในปีนี้</option><?php endif; ?>
                <?php foreach ($grades as $grade): ?><option value="<?= (int)$grade['GradeLevel_id'] ?>" <?= $gradeId === (int)$grade['GradeLevel_id'] ? 'selected' : '' ?>><?= C::h($grade['GradeName']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div><button class="kap-btn" type="submit">แสดงข้อมูล</button></div>
    </form>
</div>
<?php if (!$selectedGrade): ?>
    <?php C::note('ปีการศึกษาที่เลือกยังไม่มีการเชื่อมระดับชั้นกับหลักสูตรสถานศึกษานี้ ระบบจะไม่สร้างข้อมูลจำลอง'); ?>
<?php else: ?>
    <div class="kap-card">
        <h2><?= C::h(C::title($selectedCurriculum['Curriculum_name'] ?? '')) ?> · <?= C::h($selectedGrade['GradeName']) ?></h2>
        <p class="kap-muted">หมวดที่ใช้ในชั้นนี้อ้างอิง CurriculumSectionGrade และหมวดย่อยตาม ParentSection_id; รายวิชาอ้างอิง CurriculumSubject_id แยกตามระดับชั้น</p>
        <div class="kap-table-wrap"><table><thead><tr><th>หมวดหลักสูตรสถานศึกษา</th><th>รหัสหมวด</th><th>ระดับชั้น</th><th>จำนวนรายการรายวิชาในหมวด</th><th>ดำเนินการ</th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><span class="kap-indent kap-indent--<?= min(10,(int)$r['Depth']) ?>"><?= (int)$r['Depth'] ? '↳ ' : '' ?></span><?= C::h($r['SectionName']) ?></td>
                <td><?= C::h($r['SectionCode']) ?></td>
                <td><?= C::h($selectedGrade['GradeName']) ?></td>
                <td class="kap-num"><?= (int)($subjectCounts[(int)$r['CurriculumSection_id']] ?? 0) ?></td>
                <td><a href="<?= C::h(C::withParams('subjects', ['year_id'=>$yearId,'curriculum_id'=>$curriculumId,'grade_id'=>$gradeId,'section_id'=>(int)$r['CurriculumSection_id']], $base)) ?>">ดูรายวิชา</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="kap-muted">ยังไม่มีหมวดที่เชื่อมกับระดับชั้นนี้</td></tr><?php endif; ?>
        </tbody></table></div>
        <div class="kap-actions"><a class="kap-btn secondary" href="<?= C::h(C::withParams('subjects',['year_id'=>$yearId,'curriculum_id'=>$curriculumId,'grade_id'=>$gradeId],$base)) ?>">ดูรายวิชาทั้งหมดของระดับชั้น</a></div>
    </div>
<?php endif; ?>
<?php C::renderEnd(); ?>
