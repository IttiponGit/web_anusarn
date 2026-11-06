<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Phase26Core.php';
use KAP\Phase26\Core as C;
try {
    [$pdo, $user, $base] = C::boot();
    $years = C::years($pdo);
    $ay = C::academicYear($pdo);
    $yearId = (int)$ay['AcademicYear_id'];
    $curricula = C::curricula($pdo, $yearId);
    $stmt = $pdo->query(
        'SELECT cs.CurriculumSubject_id,cs.Subject_id,cs.Curriculum_id,cs.GradeLevel_id,
                cs.CurriculumSection_id,cs.ReviewStatus,cs.IsActive,
                cs.Credit,cs.WeeklyHours,cs.TotalInstructionalHours,
                c.Curriculum_name,g.GradeName,g.GradeCode,g.SortOrder AS GradeSortOrder,
                s.Subject_name,s.Subject_code,sec.SectionName,
                sec.Curriculum_id AS SectionOwnerCurriculum_id
           FROM `CurriculumSubject` cs
           JOIN `Curriculum` c ON c.Curriculum_id=cs.Curriculum_id
           JOIN `GradeLevel` g ON g.GradeLevel_id=cs.GradeLevel_id
           JOIN `Subject` s ON s.Subject_id=cs.Subject_id
           LEFT JOIN `CurriculumSection` sec ON sec.CurriculumSection_id=cs.CurriculumSection_id
          ORDER BY CAST(RIGHT(TRIM(c.Curriculum_name),4) AS UNSIGNED) DESC,
                   g.SortOrder,sec.SortOrder,s.Subject_name,cs.CurriculumSubject_id'
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($rows);
    $draftCount = count(array_filter($rows, static fn($r) => $r['ReviewStatus']==='DRAFT' && (int)$r['IsActive']===0));
    $activeCount = count(array_filter($rows, static fn($r) => (int)$r['IsActive']===1));
    $statusFilter = trim((string)($_GET['status'] ?? 'DRAFT'));
    if (!in_array($statusFilter,['DRAFT','ALL'],true)) { $statusFilter = 'DRAFT'; }
    $curriculumId = C::positiveId($_GET['curriculum_id'] ?? null);
    $gradeCode = trim((string)($_GET['grade_code'] ?? ''));
    if (!in_array($gradeCode, ['','P1','P2','P3','P4','P5','P6'],true)) { $gradeCode=''; }
    $duplicates = [];
    foreach ($rows as $r) {
        // Duplicate name is a review signal, not an automatic error (parallel subjects may be legitimate).
        $key = (int)$r['Curriculum_id'].'|'.(int)$r['GradeLevel_id'].'|'.trim((string)$r['Subject_name']);
        $duplicates[$key] = ($duplicates[$key] ?? 0) + 1;
    }
    $visible = [];
    foreach ($rows as $r) {
        if ($statusFilter==='DRAFT' && ($r['ReviewStatus']!=='DRAFT' || (int)$r['IsActive']!==0)) { continue; }
        if ($curriculumId && (int)$r['Curriculum_id']!==$curriculumId) { continue; }
        if ($gradeCode !== '' && $r['GradeCode']!==$gradeCode) { continue; }
        $key = (int)$r['Curriculum_id'].'|'.(int)$r['GradeLevel_id'].'|'.trim((string)$r['Subject_name']);
        $flags=[];
        if (trim((string)$r['Subject_name'])==='') { $flags[]='ชื่อวิชาว่าง'; }
        if ((float)$r['WeeklyHours']<=0) { $flags[]='ชั่วโมงต่อสัปดาห์เป็นศูนย์'; }
        if ((float)$r['TotalInstructionalHours']<=0) { $flags[]='ชั่วโมงต่อปีเป็นศูนย์'; }
        if (!empty($r['CurriculumSection_id']) && (int)$r['SectionOwnerCurriculum_id']!==(int)$r['Curriculum_id']) { $flags[]='หมวดไม่ตรงหลักสูตรสถานศึกษา'; }
        if (($duplicates[$key] ?? 0)>1) { $flags[]='ชื่อวิชาซ้ำในระดับชั้น (ให้ฝ่ายวิชาการตรวจ)'; }
        $r['Flags']=$flags;
        $visible[]=$r;
    }
} catch (Throwable $e) {
    error_log('[KAP P26 review] ' . $e->getMessage());
    http_response_code(503);
    exit('ไม่สามารถแสดงรายการตรวจรับรองรายวิชาได้ กรุณาตรวจสอบบันทึกระบบ');
}
C::renderStart('ตรวจรับรองรายวิชา', 'review', $base, $ay, $years);
?>
<div class="kap-summary">
    <div class="kap-stat"><strong><?= $total ?></strong><small>รายวิชาตามหลักสูตรสถานศึกษาทั้งหมด</small></div>
    <div class="kap-stat"><strong><?= $draftCount ?></strong><small>DRAFT ยังไม่เปิดใช้</small></div>
    <div class="kap-stat"><strong><?= $activeCount ?></strong><small>เปิดใช้งานอยู่</small></div>
</div>
<?php C::note('บัญชีตรวจสอบนี้ครอบคลุมรายวิชาทุกระดับชั้นในฐานข้อมูล ไม่จำกัดตามปีการศึกษาที่เลือกบนหัวหน้า · หน้านี้เป็นบัญชีตรวจสอบเท่านั้น: ไม่อนุมัติและไม่เปลี่ยน IsActive อัตโนมัติ การรับรองต้องยืนยันเกณฑ์และสิทธิ์กับฝ่ายวิชาการก่อน'); ?>
<div class="kap-card"><h2>กรองรายการที่รอตรวจรับรอง</h2>
    <form method="get" class="kap-form">
        <input type="hidden" name="year_id" value="<?= $yearId ?>">
        <div class="kap-field"><label for="status">สถานะ</label><select id="status" name="status"><option value="DRAFT" <?= $statusFilter==='DRAFT'?'selected':'' ?>>ร่างที่ยังไม่เปิดใช้</option><option value="ALL" <?= $statusFilter==='ALL'?'selected':'' ?>>ทุกสถานะ</option></select></div>
        <div class="kap-field"><label for="curriculum_id">หลักสูตรสถานศึกษา</label><select id="curriculum_id" name="curriculum_id"><option value="">ทุกหลักสูตรสถานศึกษา</option>
        <?php foreach ($curricula as $c): ?><option value="<?= (int)$c['Curriculum_id'] ?>" <?= $curriculumId === (int)$c['Curriculum_id']?'selected':'' ?>><?= C::h(C::title($c['Curriculum_name'])) ?></option><?php endforeach; ?>
        </select></div>
        <div class="kap-field"><label for="grade_code">ระดับชั้น</label><select id="grade_code" name="grade_code"><option value="">ทุกระดับชั้น</option>
        <?php for ($i=1;$i<=6;$i++): ?><option value="P<?= $i ?>" <?= $gradeCode==='P'.$i?'selected':'' ?>>ป.<?= $i ?></option><?php endfor; ?>
        </select></div>
        <div><button class="kap-btn" type="submit">แสดงข้อมูล</button></div>
    </form>
</div>
<div class="kap-card"><h2>รายการตรวจรับรอง (<?= count($visible) ?> รายการ)</h2>
    <div class="kap-table-wrap"><table><thead><tr><th>หลักสูตรสถานศึกษา</th><th>ระดับชั้น</th><th>ชื่อวิชา / กิจกรรม</th><th>หน่วยกิต</th><th>ชั่วโมง/สัปดาห์</th><th>ชั่วโมง/ปี</th><th>สถานะ</th><th>จุดที่ควรตรวจ</th><th>ดำเนินการ</th></tr></thead><tbody>
    <?php foreach ($visible as $r): ?>
        <tr>
            <td><?= C::h(C::title($r['Curriculum_name'])) ?></td>
            <td><?= C::h($r['GradeName']) ?></td>
            <td><?= C::h($r['Subject_name']) ?><br><span class="kap-dim">#<?= (int)$r['CurriculumSubject_id'] ?> · <?= C::h($r['SectionName'] ?: 'ไม่ระบุหมวด') ?></span></td>
            <td class="kap-num"><?= C::h($r['Credit']) ?></td>
            <td class="kap-num"><?= C::h($r['WeeklyHours']) ?></td>
            <td class="kap-num"><?= C::h($r['TotalInstructionalHours']) ?></td>
            <td><span class="kap-pill<?= (int)$r['IsActive']===1?' kap-pill--on':'' ?>"><?= C::h($r['ReviewStatus']) ?></span></td>
            <td><?= $r['Flags'] ? C::h(implode(' · ', $r['Flags'])) : '<span class="kap-dim">ไม่พบสัญญาณผิดปกติจากการตรวจเบื้องต้น</span>' ?></td>
            <td><a href="<?= C::h(rtrim($base,'/') . '/pages/admin/phase26-unified-subject-edit.php?' . http_build_query([
                    'subject_id'=>(int)$r['Subject_id'],'year_id'=>$yearId,'curriculum_id'=>(int)$r['Curriculum_id'],
                    'grade_id'=>(int)$r['GradeLevel_id']
                ],'', '&', PHP_QUERY_RFC3986)) ?>">ดู / แก้ไขร่าง</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$visible): ?><tr><td colspan="9">ไม่มีรายการตามตัวกรอง</td></tr><?php endif; ?>
    </tbody></table></div>
    <p class="kap-muted">ชื่อวิชาซ้ำเป็นข้อสังเกตให้ตรวจ ไม่ใช่ข้อสรุปว่าซ้ำผิดพลาด · รายวิชาไม่มีรหัสถือเป็นข้อมูลที่รองรับได้ · หน่วยกิต 0.00 ไม่ได้แปลว่าผิดทุกกรณี</p>
</div>
<?php C::renderEnd(); ?>
