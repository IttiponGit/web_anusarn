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
} catch (Throwable $e) {
    error_log('[KAP P26 curricula] ' . $e->getMessage());
    http_response_code(503);
    exit('ไม่สามารถแสดงข้อมูลหลักสูตรสถานศึกษาได้ กรุณาตรวจสอบบันทึกระบบ');
}
C::renderStart('ทะเบียนหลักสูตรสถานศึกษา', 'curricula', $base, $ay, $years);
?>
<div class="kap-summary">
    <div class="kap-stat"><strong><?= count($curricula) ?></strong><small>หลักสูตรสถานศึกษาทั้งหมดในทะเบียน</small></div>
    <div class="kap-stat"><strong><?= array_sum(array_map(static fn($x) => (int)$x['GradeCount'], $curricula)) ?></strong><small>ความสัมพันธ์หลักสูตรสถานศึกษากับระดับชั้น ในปี <?= C::h($ay['AcademicYear_name']) ?></small></div>
</div>
<div class="kap-card">
    <h2>ทะเบียนหลักสูตรสถานศึกษา</h2>
    <p class="kap-muted">เรียงปีหลักสูตรสถานศึกษาจากใหม่ไปเก่า · 1 หลักสูตรสถานศึกษาแสดงเพียง 1 แถว แม้ใช้ร่วมกันหลายระดับชั้น</p>
    <div class="kap-table-wrap"><table>
        <thead><tr><th>หลักสูตรสถานศึกษา</th><th>ระดับชั้นที่ใช้ในปี <?= C::h($ay['AcademicYear_name']) ?></th><th>จำนวนระดับชั้น</th><th>จำนวนหมวดทั้งหมด</th><th>สถานะทะเบียน</th><th>ดำเนินการ</th></tr></thead>
        <tbody>
            <?php foreach ($curricula as $c): ?>
                <tr>
                    <td class="kap-strong"><?= C::h(C::title($c['Curriculum_name'])) ?></td>
                    <td><?= $c['GradeNames'] !== null ? C::h($c['GradeNames']) : '<span class="kap-dim">ยังไม่มีการผูกระดับชั้นในปีที่เลือก</span>' ?></td>
                    <td class="kap-num"><?= (int)$c['GradeCount'] ?></td>
                    <td class="kap-num"><?= (int)$c['SectionCount'] ?></td>
                    <td><span class="kap-pill<?= (int)$c['IsActive'] === 1 ? ' kap-pill--on' : '' ?>"><?= (int)$c['IsActive'] === 1 ? 'เปิดใช้ทะเบียน' : 'ยังไม่เปิดใช้ทะเบียน' ?></span></td>
                    <td><a href="<?= C::h(C::withParams('sections', ['year_id' => $yearId, 'curriculum_id' => (int)$c['Curriculum_id']], $base)) ?>">ดูหมวดและรายวิชา</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>
    <p class="kap-muted">จำนวนหมวดข้างต้นนับหมวดแม่และหมวดย่อยที่อยู่ในทะเบียนของหลักสูตรสถานศึกษา ไม่ใช่จำนวนหมวดของแต่ละระดับชั้น</p>
</div>
<?php C::renderEnd(); ?>
