<?php
/**
 * KAP Phase 2.6.1. Single-card Subject master + per-CurriculumSubject values.
 * Safe add-on screen. It does not change any schema or activation state.
 */
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Phase26Core.php';
use KAP\Phase26\Core as C;
use KAP\Session;

function p26Subject(PDO $pdo, int $subjectId, bool $lock = false): ?array {
    $q = $pdo->prepare('SELECT s.Subject_id,s.Subject_code,s.Subject_name,s.SubjectKind,
                              s.Learning_Area_id,s.TypeofCourse_id,s.IsActive
                         FROM `Subject` s WHERE s.Subject_id=?'.($lock ? ' FOR UPDATE' : ''));
    $q->execute([$subjectId]);
    $v = $q->fetch(PDO::FETCH_ASSOC);
    return $v ?: null;
}

function p26Links(PDO $pdo, int $subjectId, int $yearId, bool $lock = false): array {
    // A subject can be linked to two grades. Row identity is CurriculumSubject_id,
    // never Subject_id or Curriculum_id alone. Limit editable links to the chosen year.
    $sql = 'SELECT cs.CurriculumSubject_id,cs.Subject_id,cs.Curriculum_id,cs.GradeLevel_id,
                   cs.Credit,cs.WeeklyHours,cs.TotalInstructionalHours,cs.ReviewStatus,cs.IsActive,
                   c.Curriculum_name,g.GradeName,g.GradeCode,g.SortOrder AS GradeSortOrder
              FROM `CurriculumSubject` cs
              JOIN `Curriculum` c ON c.Curriculum_id=cs.Curriculum_id
              JOIN `GradeLevel` g ON g.GradeLevel_id=cs.GradeLevel_id
             WHERE cs.Subject_id=? AND EXISTS (
                SELECT 1 FROM `CurriculumGradeYear` cgy
                 WHERE cgy.Curriculum_id=cs.Curriculum_id
                   AND cgy.GradeLevel_id=cs.GradeLevel_id AND cgy.AcademicYear_id=?
             )
             ORDER BY CAST(RIGHT(TRIM(c.Curriculum_name),4) AS UNSIGNED) DESC,
                      g.SortOrder,cs.CurriculumSubject_id';
    // Add FOR UPDATE only for transactional locking. Avoid FOR UPDATE for display queries.
    $stmt = $pdo->prepare($sql . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$subjectId, $yearId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function p26Snapshot(array $subject, array $links): string {
    $master = [
        (int)$subject['Subject_id'],
        (string)($subject['Subject_code'] ?? ''),
        (string)$subject['Subject_name'],
        (string)$subject['SubjectKind'],
        (string)($subject['Learning_Area_id'] ?? ''),
        (string)($subject['TypeofCourse_id'] ?? ''),
        (int)$subject['IsActive'],
    ];
    $children = [];
    foreach ($links as $l) {
        $children[] = [
            (int)$l['CurriculumSubject_id'], (int)$l['Curriculum_id'],
            (int)$l['GradeLevel_id'], (string)$l['ReviewStatus'], (int)$l['IsActive'],
            (string)$l['Credit'], (string)$l['WeeklyHours'], (string)$l['TotalInstructionalHours'],
        ];
    }
    return hash('sha256', json_encode([$master, $children], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function p26EnsureLookup(PDO $pdo, string $table, string $column, int $id): void {
    if ($id < 1) { throw new RuntimeException('ต้องเลือกข้อมูลให้ครบถ้วน'); }
    $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s`=?', $table, $column);
    $q = $pdo->prepare($sql);
    $q->execute([$id]);
    if ((int)$q->fetchColumn() !== 1) { throw new RuntimeException('รายการตัวเลือกไม่ถูกต้อง'); }
}

try {
    [$pdo,$user,$base] = C::boot();
    $years = C::years($pdo);
    $ay = C::academicYear($pdo);
    $yearId = (int)$ay['AcademicYear_id'];
    $id = C::positiveId($_GET['subject_id'] ?? $_POST['subject_id'] ?? null);
    if ($id < 1) { http_response_code(400); exit('ต้องระบุ subject_id'); }
    $subject = p26Subject($pdo, $id);
    if (!$subject) { http_response_code(404); exit('ไม่พบทะเบียนรายวิชา'); }
    $links = p26Links($pdo, $id, $yearId);
    $areas = $pdo->query('SELECT Learning_Area_id,Learning_Area_name FROM `LearningArea` ORDER BY Learning_Area_name,Learning_Area_id')->fetchAll(PDO::FETCH_ASSOC);
    $types = $pdo->query('SELECT TypeofCourse_id,TypeofCourse_name FROM `TypeofCourse` ORDER BY TypeofCourse_id')->fetchAll(PDO::FETCH_ASSOC);

    $returnParams = ['year_id'=>$yearId];
    $wantCurr = C::positiveId($_GET['curriculum_id'] ?? null);
    $wantGrade = C::positiveId($_GET['grade_id'] ?? null);
    $wantSec = C::positiveId($_GET['section_id'] ?? null);
    $chosenLink = null;
    foreach ($links as $candidate) {
        if ((int)$candidate['Curriculum_id'] === $wantCurr && (int)$candidate['GradeLevel_id'] === $wantGrade) {
            $chosenLink = $candidate; break;
        }
    }
    if ($chosenLink === null) { $chosenLink = $links[0] ?? null; }
    if ($chosenLink) {
        $returnParams['curriculum_id'] = (int)$chosenLink['Curriculum_id'];
        $returnParams['grade_id'] = (int)$chosenLink['GradeLevel_id'];
        if ($wantSec) { $returnParams['section_id'] = $wantSec; }
    }
    $back = C::withParams('subjects', $returnParams, $base);
    $error = null;
    $saved = isset($_GET['saved']) && $_GET['saved'] === '1';
    $linkedGrades = [];
    foreach ($links as $r) { $linkedGrades[(int)$r['GradeLevel_id']] = $r['GradeName']; }
    $allGradeQ = $pdo->prepare('SELECT DISTINCT g.GradeLevel_id,g.GradeName,g.SortOrder FROM `CurriculumSubject` cs JOIN `GradeLevel` g ON g.GradeLevel_id=cs.GradeLevel_id WHERE cs.Subject_id=? ORDER BY g.SortOrder');
    $allGradeQ->execute([$id]);
    $allGradeRows = $allGradeQ->fetchAll(PDO::FETCH_ASSOC);
    $allGradeNames = array_column($allGradeRows, 'GradeName');
    $isShared = count($allGradeRows) > 1;
    $hasNonDraft = count(array_filter($links, static fn($r) => $r['ReviewStatus'] !== 'DRAFT' || (int)$r['IsActive'] !== 0)) > 0;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        try {
            Session::verifyCsrf((string)($_POST['csrf'] ?? ''));
        } catch (Throwable $e) { http_response_code(403); exit('CSRF verification failed'); }
        try {
            if ($hasNonDraft) { throw new RuntimeException('มีการใช้/รับรองแล้ว: การแก้แม่บทต้องผ่านกระบวนการที่รักษาประวัติ'); }
            $newName = trim((string)($_POST['subject_name'] ?? ''));
            $newCode = trim((string)($_POST['subject_code'] ?? ''));
            $newKind = (string)($_POST['subject_kind'] ?? '');
            $newArea = C::positiveId($_POST['learning_area_id'] ?? null);
            $newType = C::positiveId($_POST['type_id'] ?? null);
            if ($newName === '' || (preg_match_all('/./us', $newName) ?: 0) > 255) { throw new RuntimeException('ชื่อรายวิชาต้องมี 1-255 ตัวอักษร'); }
            if ((preg_match_all('/./us', $newCode) ?: 0) > 255) { throw new RuntimeException('รหัสรายวิชาต้องไม่เกิน 255 ตัวอักษร'); }
            if (!in_array($newKind, ['COURSE', 'ACTIVITY'], true)) { throw new RuntimeException('ประเภทรายการไม่ถูกต้อง'); }
            if ($newArea) { p26EnsureLookup($pdo, 'LearningArea', 'Learning_Area_id', $newArea); }
            p26EnsureLookup($pdo, 'TypeofCourse', 'TypeofCourse_id', $newType);

            $postLinks = $_POST['links'] ?? null;
            if (!is_array($postLinks) || count($postLinks) !== count($links)) {
                throw new RuntimeException('ข้อมูลแถวหน่วยกิต/ชั่วโมงเรียนไม่ครบ กรุณาโหลดหน้าใหม่');
            }
            $submitted = [];
            foreach ($links as $link) {
                $csid = (int)$link['CurriculumSubject_id'];
                $key = (string)$csid;
                if (!isset($postLinks[$key]) || !is_array($postLinks[$key])) {
                    throw new RuntimeException('ข้อมูลของ CurriculumSubject_id #' . $csid . ' ไม่ครบ');
                }
                if ($link['ReviewStatus'] !== 'DRAFT' || (int)$link['IsActive'] !== 0) {
                    throw new RuntimeException('ไม่สามารถบันทึกข้อมูลที่ไม่ใช่ DRAFT');
                }
                $values = $postLinks[$key];
                $submitted[$csid] = [
                    C::decimal($values['credit'] ?? null, 5, 'หน่วยกิต'),
                    C::decimal($values['weekly'] ?? null, 5, 'เวลาเรียน/สัปดาห์'),
                    C::decimal($values['annual'] ?? null, 6, 'เวลาเรียน/ปี'),
                ];
            }
            if (count($postLinks) !== count($submitted)) {
                throw new RuntimeException('พบรายการชั่วโมงเรียนที่ไม่ได้อยู่ในทะเบียนที่แสดง');
            }
            $postedSnapshot = (string)($_POST['snapshot'] ?? '');
            if (!preg_match('/^[a-f0-9]{64}$/D', $postedSnapshot)) {
                throw new RuntimeException('ข้อมูลการแก้ไขไม่สมบูรณ์');
            }
            if ($isShared && !isset($_POST['confirm_shared'])) {
                $masterChange = $newName !== (string)$subject['Subject_name']
                    || $newCode !== (string)($subject['Subject_code'] ?? '')
                    || $newKind !== (string)$subject['SubjectKind']
                    || $newArea !== (int)$subject['Learning_Area_id']
                    || $newType !== (int)$subject['TypeofCourse_id'];
                if ($masterChange) {
                    throw new RuntimeException('รายวิชานี้ใช้ร่วมหลายชั้น การแก้ไขทะเบียนกลางมีผลทุกชั้น กรุณาติ๊กยืนยันก่อนบันทึก');
                }
            }

            $pdo->beginTransaction();
            $lockedSubject = p26Subject($pdo, $id, true);
            $lockedLinks = p26Links($pdo, $id, $yearId, true);
            if (!$lockedSubject || !hash_equals($postedSnapshot, p26Snapshot($lockedSubject, $lockedLinks))) {
                throw new RuntimeException('มีผู้เปลี่ยนข้อมูลหลังจากเปิดหน้านี้ กรุณายกเลิกแล้วเปิดแก้ไขใหม่');
            }
            if (count(array_filter($lockedLinks, static fn($r) => $r['ReviewStatus'] !== 'DRAFT' || (int)$r['IsActive'] !== 0)) > 0) {
                throw new RuntimeException('มีรายการเปลี่ยนเป็นรับรอง/เปิดใช้งานแล้ว');
            }
            // Also protect master updates when links outside the selected academic year are active.
            $activeQ = $pdo->prepare('SELECT COUNT(*) FROM `CurriculumSubject` WHERE Subject_id=? AND (ReviewStatus<>\'DRAFT\' OR IsActive<>0)');
            $activeQ->execute([$id]);
            if ((int)$activeQ->fetchColumn() > 0) { throw new RuntimeException('ทะเบียนแม่บทมีรายการที่รับรอง/เปิดใช้ในปีอื่นแล้ว'); }

            // Check duplicate code only if the code actually changed. Blank code is supported.
            if ($newCode !== '' && $newCode !== (string)($lockedSubject['Subject_code'] ?? '')) {
                $dup = $pdo->prepare('SELECT COUNT(*) FROM `Subject` WHERE Subject_code=? AND Subject_id<>?');
                $dup->execute([$newCode,$id]);
                if ((int)$dup->fetchColumn() > 0) { throw new RuntimeException('รหัสรายวิชาซ้ำกับทะเบียนอื่น'); }
            }

            $updateSubject = $pdo->prepare('UPDATE `Subject`
                  SET Subject_code=?,Subject_name=?,SubjectKind=?,Learning_Area_id=?,TypeofCourse_id=?
                  WHERE Subject_id=?');
            $updateSubject->execute([$newCode === '' ? null : $newCode,$newName,$newKind,$newArea ?: null,$newType,$id]);

            $updateLink = $pdo->prepare('UPDATE `CurriculumSubject`
                     SET Credit=?,WeeklyHours=?,TotalInstructionalHours=?
                     WHERE CurriculumSubject_id=? AND Subject_id=? AND Curriculum_id=? AND GradeLevel_id=?
                           AND ReviewStatus=\'DRAFT\' AND IsActive=0');
            foreach ($lockedLinks as $link) {
                $csid = (int)$link['CurriculumSubject_id'];
                [$credit,$weekly,$annual] = $submitted[$csid];
                $updateLink->execute([$credit,$weekly,$annual,$csid,$id,(int)$link['Curriculum_id'],(int)$link['GradeLevel_id']]);
            }
            $pdo->commit();
            error_log(sprintf('[KAP Phase26 unified save] user_id=%d subject_id=%d cs_ids=%s',
                (int)$user['SystemUser_id'], $id, implode(',', array_keys($submitted))));
            $url = rtrim($base, '/') . '/pages/admin/phase26-unified-subject-edit.php?'
                . http_build_query(['subject_id'=>$id,'year_id'=>$yearId,'saved'=>1] + $returnParams, '', '&', PHP_QUERY_RFC3986);
            header('Location: '.$url, true, 303);
            exit;
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = $e->getMessage();
            error_log('[KAP Phase26 unified validation] ' . $e->getMessage());
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = 'ไม่สามารถบันทึกข้อมูลได้ กรุณาตรวจสอบ server log';
            error_log('[KAP Phase26 unified save error] ' . get_class($e) . ': ' . $e->getMessage());
        }
    }
} catch (Throwable $e) {
    error_log('[KAP Phase26 unified load error] '.get_class($e).': '.$e->getMessage());
    http_response_code(503);
    exit('ไม่สามารถเปิดหน้าแก้ไขรายวิชาได้ กรุณาตรวจสอบ log');
}

C::renderStart('แก้ไขทะเบียนรายวิชาและกิจกรรม', 'subjects', $base, $ay, $years, ['subject_id'=>$id]);
if ($saved) { C::note('บันทึกทะเบียนรายวิชาและหน่วยกิต/ชั่วโมงเรียนในครั้งเดียวแล้ว', 'success'); }
if ($error !== null) { C::note($error, 'error'); }
?>
<div class="kap-card kap-unified-card">
  <div class="kap-edit-title"><div>
    <h2>แก้ไขทะเบียนรายวิชาและกิจกรรม #<?= $id ?></h2>
    <p class="kap-muted">ข้อมูลทะเบียนกลางและข้อมูลชั่วโมงเรียนตามหลักสูตรสถานศึกษา อยู่ในแบบฟอร์มเดียวกัน</p>
  </div></div>
  <?php if ($isShared): ?>
    <?php C::note('รายวิชานี้เชื่อมกับหลายระดับชั้น (' . implode(', ', $allGradeNames) . ') การแก้ไขข้อมูลทะเบียนกลางจะมีผลต่อทุกชั้น แต่ชั่วโมงเรียนและหน่วยกิตแยกตาม CurriculumSubject_id', 'info'); ?>
  <?php endif; ?>
  <?php if ($hasNonDraft): ?>
    <?php C::note('มีรายการที่ได้รับรองหรือเปิดใช้งานแล้ว จึงปิดการแก้ไขผ่านแบบฟอร์มนี้เพื่อรักษาประวัติ', 'error'); ?>
  <?php endif; ?>
  <form method="post" action="<?= C::h(rtrim($base, '/') . '/pages/admin/phase26-unified-subject-edit.php?' . http_build_query(['subject_id'=>$id]+$returnParams, '', '&', PHP_QUERY_RFC3986)) ?>">
    <input type="hidden" name="csrf" value="<?= C::h(Session::csrf()) ?>">
    <input type="hidden" name="subject_id" value="<?= $id ?>">
    <input type="hidden" name="snapshot" value="<?= C::h(p26Snapshot($subject, $links)) ?>">
    <h3>ข้อมูลทะเบียนรายวิชาและกิจกรรม</h3>
    <div class="kap-edit-grid">
       <div class="kap-field"><label for="subject_kind">รายการ *</label><select id="subject_kind" name="subject_kind" required <?= $hasNonDraft?'disabled':'' ?>>
        <option value="COURSE" <?= ($subject['SubjectKind']==='COURSE'?'selected':'') ?>>รายวิชา</option>
        <option value="ACTIVITY" <?= ($subject['SubjectKind']==='ACTIVITY'?'selected':'') ?>>กิจกรรม</option>
       </select></div>
       <div class="kap-field"><label for="subject_code">รหัสรายวิชา / กิจกรรม</label><input id="subject_code" name="subject_code" value="<?= C::h($subject['Subject_code']) ?>" maxlength="255" placeholder="ปล่อยว่างหากไม่มีรหัส" <?= $hasNonDraft?'disabled':'' ?>></div>
       <div class="kap-field kap-wide"><label for="subject_name">ชื่อรายวิชา / กิจกรรม *</label><input id="subject_name" name="subject_name" value="<?= C::h($subject['Subject_name']) ?>" maxlength="255" required <?= $hasNonDraft?'disabled':'' ?>></div>
       <div class="kap-field"><label for="learning_area_id">กลุ่มสาระการเรียนรู้</label><select id="learning_area_id" name="learning_area_id" <?= $hasNonDraft?'disabled':'' ?>>
         <option value="">ไม่ระบุ</option>
         <?php foreach ($areas as $area): ?><option value="<?= (int)$area['Learning_Area_id'] ?>" <?= (int)$subject['Learning_Area_id']===(int)$area['Learning_Area_id']?'selected':'' ?>><?= C::h($area['Learning_Area_name']) ?></option><?php endforeach; ?>
       </select></div>
       <div class="kap-field"><label for="type_id">ประเภทรายวิชา / กิจกรรม *</label><select id="type_id" name="type_id" required <?= $hasNonDraft?'disabled':'' ?>>
         <?php foreach ($types as $type): ?><option value="<?= (int)$type['TypeofCourse_id'] ?>" <?= (int)$subject['TypeofCourse_id']===(int)$type['TypeofCourse_id']?'selected':'' ?>><?= C::h($type['TypeofCourse_name']) ?></option><?php endforeach; ?>
       </select></div>
       <div class="kap-field kap-wide"><label>ระดับชั้นที่ใช้สอน</label><div class="kap-readonly"><?= $allGradeNames ? C::h(implode(', ', $allGradeNames)) : 'ไม่มีการเชื่อมระดับชั้นในปีที่เลือก' ?></div>
          <p class="kap-muted kap-small">การย้ายหรือแยกรายวิชาตามชั้นต้องทำในโมดูลแยกเพื่อไม่ให้กระทบข้อมูลหลักสูตรสถานศึกษาเดิม</p></div>
    </div>
    <h3>หน่วยกิตและเวลาเรียนตามหลักสูตรสถานศึกษา</h3>
    <p class="kap-muted kap-small">ข้อมูลแต่ละแถวแก้ไขเฉพาะ `CurriculumSubject_id` ของแถวนั้น ไม่แก้ทั้งระดับชั้นโดยอัตโนมัติ</p>
    <div class="kap-table-wrap"><table><thead><tr><th>หลักสูตรสถานศึกษา / ระดับชั้น</th><th>หน่วยกิต</th><th>เวลาเรียน/สัปดาห์ (ชม.)</th><th>เวลาเรียน/ปี (ชม.)</th><th>สถานะ</th></tr></thead><tbody>
       <?php foreach ($links as $l): $csid=(int)$l['CurriculumSubject_id']; ?>
         <tr><td><strong><?= C::h(C::title($l['Curriculum_name'])) ?></strong><br><?= C::h($l['GradeName']) ?> · #<?= $csid ?></td>
           <td><input aria-label="หน่วยกิตของ #<?= $csid ?>" type="number" step="0.01" min="0" max="99999.99" required name="links[<?= $csid ?>][credit]" value="<?= C::h($l['Credit']) ?>" <?= $hasNonDraft?'disabled':'' ?>></td>
           <td><input aria-label="ชั่วโมงต่อสัปดาห์ของ #<?= $csid ?>" type="number" step="0.01" min="0" max="99999.99" required name="links[<?= $csid ?>][weekly]" value="<?= C::h($l['WeeklyHours']) ?>" <?= $hasNonDraft?'disabled':'' ?>></td>
           <td><input aria-label="ชั่วโมงต่อปีของ #<?= $csid ?>" type="number" step="0.01" min="0" max="999999.99" required name="links[<?= $csid ?>][annual]" value="<?= C::h($l['TotalInstructionalHours']) ?>" <?= $hasNonDraft?'disabled':'' ?>></td>
           <td><span class="kap-pill"><?= C::h($l['ReviewStatus']) ?></span></td>
         </tr>
       <?php endforeach; ?>
       <?php if (!$links): ?><tr><td colspan="5">ยังไม่มีการเชื่อมรายวิชากับหลักสูตรสถานศึกษาในปีนี้</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php if ($isShared && !$hasNonDraft): ?><label class="kap-shared-confirm"><input type="checkbox" name="confirm_shared" value="1"> รับทราบว่าการแก้ชื่อ/รหัส/ประเภททะเบียนกลางจะมีผลกับทุกระดับชั้นที่ใช้รายวิชานี้</label><?php endif; ?>
    <div class="kap-actions kap-unified-actions">
      <button type="submit" class="kap-btn" <?= ($hasNonDraft||!$links)?'disabled':'' ?>>บันทึกการแก้ไขทั้งหมด</button>
      <a class="kap-btn secondary" href="<?= C::h($back) ?>">ยกเลิกการแก้ไข</a>
    </div>
  </form>
</div>
<?php C::renderEnd(); ?>
