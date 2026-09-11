<?php
$pageTitle = 'คลังหลักฐาน';
$pageHeading = 'คลังหลักฐานเชิงประจักษ์';
$pageDescription = 'ค้นหาหลักฐานจากโครงการ ตัวชี้วัด และปีงบประมาณ เพื่อรองรับการประกันคุณภาพและการประเมินภายนอก';
$activeMenu = 'evidences';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

$db=qa_db();
$currentYear=qa_current_year_row();
$yearId=$currentYear?(int)$currentYear['year_id']:0;
$yearBE=$currentYear?(int)$currentYear['fiscal_year_be']:0;

$breadcrumbs=array(array('label'=>'คลังหลักฐาน'));

$type=isset($_GET['type'])?trim($_GET['type']):'';
$indicatorId=isset($_GET['indicator'])?(int)$_GET['indicator']:0;
$search=isset($_GET['q'])?trim($_GET['q']):'';

$types=array();
$result=$db->query(
    "SELECT DISTINCT evidence_type FROM qa_evidences
     WHERE evidence_type<>'' ORDER BY evidence_type"
);
if($result){while($r=$result->fetch_assoc())$types[]=$r['evidence_type'];$result->free();}

$indicators=array();
$result=$db->query(
    "SELECT i.indicator_id,i.indicator_code,i.indicator_name
     FROM qa_indicators i
     INNER JOIN qa_standards s ON s.standard_id=i.standard_id
     INNER JOIN qa_quality_frameworks f ON f.framework_id=s.framework_id
     WHERE f.framework_code='ONESQA' AND i.is_active=1
     ORDER BY i.indicator_code"
);
if($result){while($r=$result->fetch_assoc())$indicators[]=$r;$result->free();}

$where=array();
$where[]="p.year_id=".(int)$yearId;

if($type!==''){
    $where[]="ev.evidence_type='".$db->real_escape_string($type)."'";
}
if($indicatorId>0){
    $where[]="EXISTS(SELECT 1 FROM qa_evidence_indicator_links x WHERE x.evidence_id=ev.evidence_id AND x.indicator_id=".$indicatorId.")";
}
if($search!==''){
    $esc=$db->real_escape_string($search);
    $where[]="(ev.evidence_code LIKE '%".$esc."%' OR ev.title LIKE '%".$esc."%' OR p.project_code LIKE '%".$esc."%' OR p.project_name LIKE '%".$esc."%')";
}

$rows=array();
$sql="SELECT ev.*,p.project_code,p.project_name,d.division_name,
            GROUP_CONCAT(i.indicator_code ORDER BY i.indicator_code SEPARATOR ', ') AS indicator_codes
      FROM qa_evidences ev
      INNER JOIN qa_projects p ON p.project_id=ev.project_id
      INNER JOIN qa_divisions d ON d.division_id=p.division_id
      LEFT JOIN qa_evidence_indicator_links l ON l.evidence_id=ev.evidence_id
      LEFT JOIN qa_indicators i ON i.indicator_id=l.indicator_id
      WHERE ".implode(' AND ',$where)."
      GROUP BY ev.evidence_id
      ORDER BY ev.evidence_date DESC,ev.evidence_id DESC";
$result=$db->query($sql);
if($result){while($r=$result->fetch_assoc())$rows[]=$r;$result->free();}

$totalEvidence=(int)qa_db_scalar(
    "SELECT COUNT(*) FROM qa_evidences ev INNER JOIN qa_projects p ON p.project_id=ev.project_id WHERE p.year_id=".(int)$yearId,0
);
$linkedCount=(int)qa_db_scalar(
    "SELECT COUNT(DISTINCT ev.evidence_id)
     FROM qa_evidences ev
     INNER JOIN qa_projects p ON p.project_id=ev.project_id
     INNER JOIN qa_evidence_indicator_links l ON l.evidence_id=ev.evidence_id
     WHERE p.year_id=".(int)$yearId,0
);

require QA_ROOT . '/includes/header.php';
?>

<div class="grid grid-3">
    <div class="card"><div class="metric-label">หลักฐานทั้งหมด</div><div class="metric-value"><?= h($totalEvidence) ?></div><div class="metric-note">ปีงบประมาณ <?= h($yearBE) ?></div></div>
    <div class="card"><div class="metric-label">เชื่อมตัวชี้วัดแล้ว</div><div class="metric-value"><?= h($linkedCount) ?></div><div class="metric-note">พร้อมใช้ตอบประเด็นประเมิน</div></div>
    <div class="card"><div class="metric-label">ผลการค้นหา</div><div class="metric-value"><?= h(count($rows)) ?></div><div class="metric-note">ตามตัวกรองปัจจุบัน</div></div>
</div>

<div class="card" style="margin-top:16px">
    <form method="get" class="form-grid">
        <div class="form-group">
            <label>ประเภทหลักฐาน</label>
            <select name="type"><option value="">ทั้งหมด</option><?php foreach($types as $t): ?><option value="<?= h($t) ?>" <?= $t===$type?'selected':'' ?>><?= h($t) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group">
            <label>ตัวชี้วัด สมศ.</label>
            <select name="indicator"><option value="0">ทั้งหมด</option><?php foreach($indicators as $i): ?><option value="<?= h($i['indicator_id']) ?>" <?= (int)$i['indicator_id']===$indicatorId?'selected':'' ?>><?= h($i['indicator_code'].' — '.$i['indicator_name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group">
            <label>ค้นหา</label>
            <input name="q" value="<?= h($search) ?>" placeholder="รหัสหลักฐาน / โครงการ / ชื่อหลักฐาน">
        </div>
        <div class="form-group" style="align-self:end">
            <button class="btn btn-primary" type="submit">ค้นหา</button>
            <a class="btn" href="<?= h(qa_url('evidences/index.php')) ?>">ล้าง</a>
        </div>
    </form>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>รายการหลักฐาน</h2><p>หลักฐานจะเพิ่มจากหน้าติดตามโครงการแต่ละโครงการ</p></div>
        <span class="badge badge-blue"><?= h(count($rows)) ?> รายการ</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>รหัส</th><th>ชื่อหลักฐาน</th><th>ประเภท</th><th>โครงการ / ฝ่าย</th><th>ตัวชี้วัด</th><th>วันที่</th><th>เปิด</th></tr></thead>
            <tbody>
            <?php if(empty($rows)): ?>
                <tr><td colspan="7" class="empty-cell">ยังไม่มีหลักฐานตามเงื่อนไขที่เลือก</td></tr>
            <?php else: ?>
                <?php foreach($rows as $r): ?>
                <tr>
                    <td><strong><?= h($r['evidence_code']) ?></strong></td>
                    <td><?= h($r['title']) ?></td>
                    <td><?= h($r['evidence_type']) ?></td>
                    <td><strong><?= h($r['project_code']) ?></strong> <?= h($r['project_name']) ?><div class="subtle"><?= h($r['division_name']) ?></div></td>
                    <td><?= $r['indicator_codes']?h($r['indicator_codes']):'-' ?></td>
                    <td><?= $r['evidence_date']?h($r['evidence_date']):'-' ?></td>
                    <td>
                        <a class="btn btn-sm" href="<?= h(qa_url('monitoring/project.php?id='.$r['project_id'].'#evidence')) ?>">โครงการ</a>
                        <?php if($r['file_path']): ?><a class="btn btn-sm" target="_blank" href="<?= h(qa_url($r['file_path'])) ?>">ไฟล์</a><?php endif; ?>
                        <?php if($r['external_url']): ?><a class="btn btn-sm" target="_blank" rel="noopener" href="<?= h($r['external_url']) ?>">URL</a><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require QA_ROOT . '/includes/footer.php'; ?>
