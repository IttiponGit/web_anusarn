<?php
$pageTitle='พิมพ์ SAR';
$activeMenu='sar-builder';
require_once __DIR__.'/../../includes/bootstrap.php';
qa_require_login();
qa_require_permission('sar.view');

$db=qa_db();
$reportId=isset($_GET['id'])?(int)$_GET['id']:0;
$report=qa_sar_report_by_id($reportId);
if(!$report){http_response_code(404);die('ไม่พบ SAR');}

$standards=array();
$result=$db->query(
    "SELECT standard_id,standard_code,standard_name,description
     FROM qa_standards
     WHERE framework_id=".(int)$report['framework_id']." AND is_active=1
     ORDER BY sort_order,standard_id"
);
if($result){while($row=$result->fetch_assoc())$standards[]=$row;$result->free();}

$standardEntries=array();
$result=$db->query("SELECT * FROM qa_sar_builder_standard_entries WHERE report_id=".$reportId);
if($result){while($row=$result->fetch_assoc())$standardEntries[(int)$row['standard_id']]=$row;$result->free();}

$indicatorEntries=array();
$result=$db->query("SELECT * FROM qa_sar_builder_indicator_entries WHERE report_id=".$reportId);
if($result){while($row=$result->fetch_assoc())$indicatorEntries[(int)$row['indicator_id']]=$row;$result->free();}

$indicators=array();
$result=$db->query(
    "SELECT i.indicator_id,i.indicator_code,i.indicator_name,i.standard_id
     FROM qa_indicators i
     INNER JOIN qa_standards s ON s.standard_id=i.standard_id
     WHERE s.framework_id=".(int)$report['framework_id']." AND i.is_active=1
     ORDER BY s.sort_order,s.standard_id,i.sort_order,i.indicator_id"
);
if($result){while($row=$result->fetch_assoc()){$sid=(int)$row['standard_id'];if(!isset($indicators[$sid]))$indicators[$sid]=array();$indicators[$sid][]=$row;}$result->free();}

$pageHeading=$report['report_title'];
$pageDescription='ฉบับพิมพ์จาก SAR Builder';
$breadcrumbs=array(array('label'=>'SAR Builder','url'=>qa_url('reports/sar/index.php')),array('label'=>'ฉบับพิมพ์'));
require QA_ROOT.'/includes/header.php';
?>

<div class="action-bar no-print">
    <a class="btn" href="<?= h(qa_url('reports/sar/edit.php?id='.$reportId)) ?>">← กลับ SAR Builder</a>
    <button class="btn btn-primary" type="button" onclick="window.print()">🖨 พิมพ์ / บันทึกเป็น PDF</button>
</div>

<div class="card sar-print-document">
    <div class="sar-print-cover">
        <h1>รายงานการประเมินตนเองของสถานศึกษา (SAR)</h1>
        <h2>โรงเรียนโสตศึกษาอนุสารสุนทร</h2>
        <h3>ปีงบประมาณ พ.ศ. <?= h($report['fiscal_year_be']) ?></h3>
        <p><?= h($report['framework_name']) ?></p>
        <p>สถานะ: <?= h(qa_sar_status_label($report['status_code'])) ?></p>
    </div>

    <?php foreach($standards as $std):
        $sid=(int)$std['standard_id'];
        $se=isset($standardEntries[$sid])?$standardEntries[$sid]:null;
    ?>
    <section class="sar-print-section">
        <h2>มาตรฐาน <?= h($std['standard_code']) ?> <?= h($std['standard_name']) ?></h2>
        <?php if($se && $se['overview_text']): ?><h3>บทสรุปภาพรวม</h3><div class="sar-print-text"><?= nl2br(h($se['overview_text'])) ?></div><?php endif; ?>
        <?php if($se && $se['strengths_text']): ?><h3>จุดเด่น</h3><div class="sar-print-text"><?= nl2br(h($se['strengths_text'])) ?></div><?php endif; ?>
        <?php if($se && $se['improvement_text']): ?><h3>ประเด็นพัฒนา</h3><div class="sar-print-text"><?= nl2br(h($se['improvement_text'])) ?></div><?php endif; ?>

        <?php if(isset($indicators[$sid])): foreach($indicators[$sid] as $ind):
            $iid=(int)$ind['indicator_id'];
            $entry=isset($indicatorEntries[$iid])?$indicatorEntries[$iid]:null;
            $metrics=qa_sar_indicator_metrics((int)$report['year_id'],$iid);
            $auto=qa_sar_auto_summary((int)$report['fiscal_year_be'],$ind,$metrics);
        ?>
        <article class="sar-print-indicator">
            <h3><?= h($ind['indicator_code']) ?> — <?= h($ind['indicator_name']) ?></h3>
            <p class="sar-print-auto"><?= h($auto) ?></p>
            <?php if($entry && $entry['analysis_text']): ?><h4>บทวิเคราะห์</h4><div class="sar-print-text"><?= nl2br(h($entry['analysis_text'])) ?></div><?php endif; ?>
            <?php if($entry && $entry['strengths_text']): ?><h4>จุดเด่น</h4><div class="sar-print-text"><?= nl2br(h($entry['strengths_text'])) ?></div><?php endif; ?>
            <?php if($entry && $entry['improvement_text']): ?><h4>ประเด็นพัฒนา</h4><div class="sar-print-text"><?= nl2br(h($entry['improvement_text'])) ?></div><?php endif; ?>
            <?php if($entry && $entry['evidence_note']): ?><h4>หลักฐานสำคัญ</h4><div class="sar-print-text"><?= nl2br(h($entry['evidence_note'])) ?></div><?php endif; ?>
        </article>
        <?php endforeach; endif; ?>
    </section>
    <?php endforeach; ?>

    <div class="sar-print-signature">
        <p>ลงชื่อ ............................................................ ผู้รับรองรายงาน</p>
        <p>(............................................................)</p>
        <p>ผู้อำนวยการโรงเรียนโสตศึกษาอนุสารสุนทร</p>
    </div>
</div>

<?php require QA_ROOT.'/includes/footer.php'; ?>
