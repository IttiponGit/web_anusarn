<?php
$pageTitle='กิจกรรมที่รับผิดชอบ';
$pageHeading='กิจกรรมที่รับผิดชอบ';
$pageDescription='แสดงเฉพาะกิจกรรมที่บัญชีนี้ได้รับมอบหมายโดยตรง';
$activeMenu='my-activities';

require_once __DIR__.'/../includes/bootstrap.php';
qa_require_login();
qa_require_any_permission(array('activity.view_all','activity.view_division','activity.view_own'));

$db=qa_db();
$user=qa_current_user();
$userId=$user?(int)$user['user_id']:0;
$currentYear=qa_current_year_row();
$yearId=$currentYear?(int)$currentYear['year_id']:0;
$breadcrumbs=array(
    array('label'=>'โครงการ / งาน / กิจกรรม','url'=>qa_url('projects/index.php')),
    array('label'=>'กิจกรรมที่รับผิดชอบ')
);

if(!qa_activity_tables_ready()){
    require QA_ROOT.'/includes/header.php';
    ?><div class="alert alert-danger">กรุณา Import <strong>anusarn_qa_v1.4.6_phase4.2.2_activity_hierarchy.sql</strong> ก่อนใช้งาน</div><?php
    require QA_ROOT.'/includes/footer.php';exit;
}

$status=trim(qa_request_scalar($_GET,'status'));
$q=trim(qa_request_scalar($_GET,'q'));

/* เมนูนี้เป็น My Activities เสมอ ไม่ใช้ activity.view_all เพื่อดึงงานคนอื่น */
$where=array('p.year_id='.(int)$yearId,'a.responsible_user_id='.(int)$userId);

if(in_array($status,array('planned','in_progress','completed','cancelled'),true)){
    $where[]="a.status='".$db->real_escape_string($status)."'";
}else $status='';

if($q!==''){
    $esc=$db->real_escape_string($q);
    $where[]="(a.activity_name LIKE '%".$esc."%' OR pa.activity_name LIKE '%".$esc."%' OR p.project_name LIKE '%".$esc."%' OR p.project_code LIKE '%".$esc."%')";
}

$rows=array();
$sql="SELECT a.*,pa.activity_no AS parent_activity_no,pa.activity_name AS parent_activity_name,
             p.project_code,p.project_name,p.project_id,p.status_code AS project_status,d.division_name,
             CONCAT(COALESCE(u.prefix,''),COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS responsible_name,
             COALESCE((SELECT SUM(bi.approved_amount) FROM qa_project_budget_items bi WHERE bi.activity_id=a.activity_id),0) AS approved_budget,
             COALESCE((SELECT SUM(e.amount) FROM qa_expenditures e WHERE e.activity_id=a.activity_id AND e.payment_status='paid'),0) AS actual_expense,
             (SELECT COUNT(*) FROM qa_evidences ev WHERE ev.activity_id=a.activity_id) AS evidence_count,
             (SELECT COUNT(*) FROM qa_project_activities ch WHERE ch.parent_activity_id=a.activity_id) AS child_count
      FROM qa_project_activities a
      INNER JOIN qa_projects p ON p.project_id=a.project_id
      INNER JOIN qa_divisions d ON d.division_id=p.division_id
      LEFT JOIN qa_project_activities pa ON pa.activity_id=a.parent_activity_id
      LEFT JOIN qa_users u ON u.user_id=a.responsible_user_id
      WHERE ".implode(' AND ',$where)."
      ORDER BY CASE a.status WHEN 'in_progress' THEN 1 WHEN 'planned' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END,
               p.project_code,COALESCE(a.parent_activity_id,a.activity_id),a.sort_order,a.activity_id";
$result=$db->query($sql);
if($result){while($r=$result->fetch_assoc())$rows[]=$r;$result->free();}

$baseMine=" FROM qa_project_activities a INNER JOIN qa_projects p ON p.project_id=a.project_id WHERE p.year_id=".(int)$yearId." AND a.responsible_user_id=".(int)$userId;
$countMine=(int)qa_db_scalar("SELECT COUNT(*)".$baseMine,0);
$countProgress=(int)qa_db_scalar("SELECT COUNT(*)".$baseMine." AND a.status='in_progress'",0);
$countDone=(int)qa_db_scalar("SELECT COUNT(*)".$baseMine." AND a.status='completed'",0);
$countSub=(int)qa_db_scalar("SELECT COUNT(*)".$baseMine." AND a.parent_activity_id IS NOT NULL",0);

require QA_ROOT.'/includes/header.php';
?>
<div class="notice" style="margin-bottom:16px">
    <strong>สิทธิ์การมองเห็น:</strong> หน้านี้แสดงเฉพาะกิจกรรมที่มอบหมายให้คุณโดยตรงเท่านั้น การดูภาพรวมกิจกรรมของโครงการหรือฝ่ายให้เปิดจากหน้าโครงการ/ติดตามและประเมินผล
</div>

<div class="grid grid-4">
    <div class="card"><div class="metric-label">กิจกรรมที่มอบหมายให้ฉัน</div><div class="metric-value"><?= h($countMine) ?></div><div class="metric-note">ปีงบประมาณปัจจุบัน</div></div>
    <div class="card"><div class="metric-label">กิจกรรมย่อย</div><div class="metric-value"><?= h($countSub) ?></div><div class="metric-note">อยู่ภายใต้กิจกรรมหลัก</div></div>
    <div class="card"><div class="metric-label">กำลังดำเนินการ</div><div class="metric-value"><?= h($countProgress) ?></div><div class="metric-note">งานของฉัน</div></div>
    <div class="card"><div class="metric-label">เสร็จสิ้น</div><div class="metric-value"><?= h($countDone) ?></div><div class="metric-note">งานของฉัน</div></div>
</div>

<div class="card" style="margin-top:16px">
    <form method="get" class="form-grid">
        <div class="form-group"><label>สถานะ</label><select name="status">
            <option value="">ทุกสถานะ</option>
            <option value="planned" <?= $status==='planned'?'selected':'' ?>>วางแผน</option>
            <option value="in_progress" <?= $status==='in_progress'?'selected':'' ?>>กำลังดำเนินการ</option>
            <option value="completed" <?= $status==='completed'?'selected':'' ?>>เสร็จสิ้น</option>
            <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>ยกเลิก</option>
        </select></div>
        <div class="form-group"><label>ค้นหา</label><input name="q" value="<?= h($q) ?>" placeholder="ชื่อกิจกรรม / กิจกรรมหลัก / โครงการ / รหัสโครงการ"></div>
        <div class="form-group" style="align-self:end"><button class="btn btn-primary">ค้นหา</button> <a class="btn" href="<?= h(qa_url('activities/index.php')) ?>">ล้าง</a></div>
    </form>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading"><div><h2>รายการกิจกรรมของฉัน</h2><p>รายงานความก้าวหน้า ผลผลิต ผลลัพธ์ ปัญหา และหลักฐานของงานที่ได้รับมอบหมาย</p></div><span class="badge badge-blue"><?= h(count($rows)) ?> รายการ</span></div>
    <div class="table-wrap">
    <table>
        <thead><tr><th>โครงการ</th><th>กิจกรรมที่รับผิดชอบ</th><th>ระดับ</th><th>ความก้าวหน้า</th><th>งบอนุมัติ</th><th>ใช้จริง</th><th>หลักฐาน</th><th>สถานะ</th><th></th></tr></thead>
        <tbody>
        <?php if(empty($rows)): ?><tr><td colspan="9" class="empty-cell">ยังไม่มีกิจกรรมที่มอบหมายให้บัญชีนี้</td></tr>
        <?php else: foreach($rows as $r): $no=qa_activity_display_no($r); ?>
        <tr>
            <td><strong><?= h($r['project_code']) ?></strong><div class="subtle"><?= h($r['project_name']) ?></div></td>
            <td><strong><?= h($no.'. '.$r['activity_name']) ?></strong><?php if((int)$r['parent_activity_id']>0): ?><div class="subtle">ภายใต้ <?= h($r['parent_activity_no'].'. '.$r['parent_activity_name']) ?></div><?php endif; ?></td>
            <td><?php if((int)$r['parent_activity_id']>0): ?><span class="activity-level-badge sub">กิจกรรมย่อย</span><?php else: ?><span class="activity-level-badge main">กิจกรรมหลัก</span><?php endif; ?></td>
            <td style="min-width:130px"><div class="progress-head"><span></span><strong><?= h(number_format((float)$r['progress_percent'],0)) ?>%</strong></div><div class="progress"><span style="width:<?= h(number_format(min(100,max(0,(float)$r['progress_percent'])),2,'.','')) ?>%"></span></div></td>
            <td class="text-right"><?= h(qa_money($r['approved_budget'])) ?></td>
            <td class="text-right"><?= h(qa_money($r['actual_expense'])) ?></td>
            <td><?= h($r['evidence_count']) ?></td>
            <td><span class="badge <?= h(qa_activity_status_badge($r['status'])) ?>"><?= h(qa_activity_status_label($r['status'])) ?></span></td>
            <td><a class="btn btn-sm" href="<?= h(qa_url('activities/view.php?id='.$r['activity_id'])) ?>">เปิด</a></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php require QA_ROOT.'/includes/footer.php'; ?>
