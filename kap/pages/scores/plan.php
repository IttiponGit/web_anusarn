<?php
declare(strict_types=1);
/**
 * KAP score-plan editor: /kap/pages/scores/plan.php?subject_class_id=101
 * Demo mode saves a separate draft in PHP Session, never changes active plan/student scores.
 * Database mode is deliberately READ ONLY while KAP runs without login.
 */
$bootstrapFile = dirname(__DIR__, 2).'/config/bootstrap.php';
if (!is_readable($bootstrapFile)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    error_log('KAP_PLAN_MISSING_BOOTSTRAP: config/bootstrap.php missing or not readable');
    exit('<!doctype html><html lang="th"><meta charset="utf-8"><title>KAP</title><h2>ไฟล์ระบบ KAP ไม่ครบ</h2><p>ต้องติดตั้งชุด KAP No-Login ฉบับรวม ไม่ใช่เฉพาะ plan.php</p></html>');
}
require_once $bootstrapFile;
use Kap\Teacher\{Bootstrap,Ui};

try {
    Bootstrap::start();
    $repo=Bootstrap::repo();
    if (!method_exists($repo, 'planEditorData') || !method_exists($repo, 'studentRosterData')) {
        throw new \Kap\Teacher\WebError(503, 'ไฟล์ KAP เวอร์ชันไม่ตรงกัน: ต้องอัปเดต Repo.php, DemoRepo.php, RosterRules.php, load.php และไฟล์หน้าตั้งค่าพร้อมกัน');
    }
    $actor=Bootstrap::mode()==='demo' ? Bootstrap::actor() : Bootstrap::actor(Bootstrap::db());
    $all=$repo->subjects();
    $id=isset($_GET['subject_class_id']) ? Bootstrap::id($_GET['subject_class_id']) : (isset($all[0]['SubjectClass_id']) ? (int)$all[0]['SubjectClass_id'] : null);
    $readonly=Bootstrap::mode()!=='demo';
    $e=static fn(mixed $v):string=>Bootstrap::escape($v);
    $html=Ui::headline('ตั้งค่ารายวิชา', '1. กำหนดหน่วยการเรียนรู้และคะแนน K / A / P  ·  2. เลือกนักเรียนเข้ารายวิชา');
    if($id===null){
        Ui::page('ตั้งค่าหน่วยและคะแนน',$actor,'dashboard',$html.'<div class="kap-card">ยังไม่มีรายวิชา/กลุ่มเรียนที่เข้าถึงได้</div>');
        exit;
    }
    $subject=$repo->subject($id);
    $draft=$repo->planEditorData($id);
    $roster=$repo->studentRosterData($id);
    $choices='<option value="">เลือกรายวิชา</option>';
    foreach($all as $row){
        $cid=(int)$row['SubjectClass_id'];
        $label=Ui::courseName($row).' · '.$row['GradeName'].' · '.($row['ClassGroupName'] ?: '#'.$cid);
        $choices.='<option value="'.$cid.'"'.($cid===$id?' selected':'').'>'.$e($label).'</option>';
    }
    $html.='<div class="kap-card mb-3"><div class="row g-3 align-items-end"><div class="col-lg-7"><label class="form-label fw-bold" for="planSubject">รายวิชา / กลุ่มเรียน</label><select id="planSubject" class="form-select">'.$choices.'</select></div>';
    $html.='<div class="col-lg-5 text-lg-end"><a href="'.$e(Ui::link('dashboard')).'" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> กลับ Dashboard</a> <a class="btn btn-outline-primary" href="'.$e(Ui::link('score-kap',$id)).'"><i class="bi bi-grid-3x3-gap"></i> กรอกคะแนน KAP</a></div></div></div>';
    $html.='<div class="alert '.($readonly?'alert-warning':'alert-info').'" role="status">'
       .($readonly
         ? '<i class="bi bi-shield-lock"></i> <strong>โหมดฐานข้อมูลจริง (อ่านอย่างเดียว)</strong> ขณะไม่มี Login จึงยังไม่อนุญาตบันทึกการเปลี่ยนแผนคะแนนหรือรายชื่อนักเรียนจริง'
         : '<i class="bi bi-info-circle"></i> <strong>โหมดทดสอบ</strong> ปุ่มบันทึกจะบันทึกเป็นร่างใน Session เท่านั้น ไม่เปลี่ยนแผน ACTIVE หรือลบคะแนนนักเรียนเดิม')
       .'</div>';
    $html.='<div class="kap-card mb-3"><div class="row g-3 align-items-center">'
        .'<div class="col-lg-8"><h2 class="h5 fw-bold mb-2">'.$e(Ui::courseName($subject)).'</h2><div class="kap-meta">'.$e($subject['GradeName']).' · ปีการศึกษา '.$e($subject['AcademicYear_name']??'—').' / '. $e($subject['Semester']??'รายปี').' · กลุ่มเรียน '.$e($subject['ClassGroupName']?:'#'.$id).'</div></div>'
        .'<div class="col-lg-4 text-lg-end"><span class="kap-tag">SetupStatus: '.$e($subject['SetupStatus']).'</span> <span class="kap-tag">'.($readonly?'แผนที่อ่าน':'ร่างเวอร์ชัน').' '.(int)$draft['draft_version'].'</span></div></div></div>';
    $html.='<section class="kap-card" id="kapPlanRoot" data-readonly="'.($readonly?'1':'0').'" data-subject="'.$id.'" data-csrf="'.$e(Bootstrap::csrf()).'" data-save-url="'.$e(rtrim(Bootstrap::config()['base_path'],'/').'/api/scores/plan-save.php').'">';
    $html.='<div class="section-title"><div><h2><i class="bi bi-journal-check me-2"></i>ขั้นตอนที่ 1 · ตั้งค่าหน่วยการเรียนรู้และคะแนน</h2><p>เพิ่มหรือลดหน่วย ระบุสาระการเรียนรู้ และกำหนดคะแนน K / A / P ของแต่ละหน่วย</p></div><span class="kap-tag ok" id="planDirty">ยังไม่มีการเปลี่ยนแปลง</span></div>';
    $disabled=$readonly?' disabled':'';
    $html.='<div class="row g-3 align-items-end mb-3"><div class="col-sm-5 col-md-3"><label for="planCount" class="form-label">จำนวนหน่วยการเรียนรู้</label><div class="input-group"><input id="planCount" class="form-control" type="number" min="1" max="50" step="1" value="'.count($draft['units']).'"'.$disabled.'><button type="button" id="planApplyCount" class="btn btn-outline-primary"'.$disabled.'>ปรับจำนวน</button></div></div><div class="col-sm-7 col-md-9 d-flex justify-content-sm-end"><button type="button" id="planAdd" class="btn btn-soft"'.$disabled.'><i class="bi bi-plus-circle"></i> เพิ่มหน่วย</button></div></div>';
    $html.='<div class="table-responsive plan-scroll"><table class="table kap-table align-middle plan-table" id="kapPlanTable"><colgroup><col class="plan-col-order"><col class="plan-col-name"><col class="plan-col-content"><col class="plan-col-hours"><col class="plan-col-k"><col class="plan-col-a"><col class="plan-col-p"><col class="plan-col-total"><col class="plan-col-actions"></colgroup><thead><tr><th>ลำดับ</th><th class="text-start">ชื่อหน่วยการเรียนรู้</th><th class="text-start">สาระการเรียนรู้</th><th>ชั่วโมง</th><th class="group-k">K</th><th class="group-a">A</th><th class="group-p">P</th><th class="sum-col">รวมหน่วย</th><th>จัดการ</th></tr></thead><tbody id="planRows"></tbody><tfoot><tr><th colspan="4" class="text-end">รวมคะแนนทุกหน่วย</th><th id="planKSum">0.00</th><th id="planASum">0.00</th><th id="planPSum">0.00</th><th id="planUnitsSum">0.00</th><th></th></tr></tfoot></table></div>';
    $html.='<div class="row g-3 mt-2"><div class="col-sm-6 col-lg-4"><label class="form-label fw-bold" for="planMidterm">คะแนนกลางภาค</label><input id="planMidterm" type="number" step="0.01" min="0" max="9999.99" class="form-control" value="'.$e($draft['midterm']).'"'.$disabled.'></div><div class="col-sm-6 col-lg-4"><label class="form-label fw-bold" for="planFinal">คะแนนปลายภาค</label><input id="planFinal" type="number" step="0.01" min="0" max="9999.99" class="form-control" value="'.$e($draft['final_exam']).'"'.$disabled.'></div><div class="col-sm-6 col-lg-4"><label class="form-label fw-bold" for="planTarget">คะแนนเต็มตามแผน</label><input id="planTarget" type="number" step="0.01" min="0.01" max="9999.99" class="form-control" value="'.$e($draft['target']).'"'.$disabled.'></div></div>';
    $html.='<div class="section-title mt-4"><div><h2>ภาพรวมคะแนนทั้งหมด</h2><p>สรุปคะแนนอัตโนมัติจากตารางหน่วยเรียนและคะแนนสอบ</p></div></div><div class="table-responsive"><table class="table kap-table plan-summary"><thead><tr><th>หมวดคะแนน</th><th>คะแนนเต็ม</th></tr></thead><tbody>';
    foreach([['UNIT_K','ความรู้','planSummaryK'],['UNIT_A','เจตคติ','planSummaryA'],['UNIT_P','การปฏิบัติ','planSummaryP'],['MIDTERM','กลางภาค','planSummaryMid'],['FINAL_EXAM','ปลายภาค','planSummaryFinal']] as [$code,$desc,$elem]) $html.='<tr><td>'.$e($code).' · '.$e($desc).'</td><td id="'.$elem.'" class="fw-semibold">0.00</td></tr>';
    $html.='<tr class="table-light"><th>รวมคะแนนทุกหมวด</th><th id="planGrandTotal">0.00</th></tr><tr><th>เทียบกับคะแนนเต็มเป้าหมาย</th><th><span id="planBalance" class="kap-tag">กำลังคำนวณ</span></th></tr></tbody></table></div>';
    $html.='<div id="planFeedback" role="status" aria-live="polite" class="small mt-3"></div>';
    $html.='<div class="action-bar mt-3"><p class="footnote mb-2">บันทึกเป็นร่างได้แม้ยอดคะแนนยังไม่ครบ แต่ต้องรวมเท่าคะแนนเป้าหมายและผ่านการตรวจข้อมูลทั้งหมดก่อนเปิดใช้จริง</p><div class="d-flex gap-2 flex-wrap justify-content-end"><button id="planReset" type="button" class="btn btn-outline-secondary"'.$disabled.'><i class="bi bi-arrow-counterclockwise"></i> คืนค่าที่บันทึกไว้</button><button id="planSave" type="button" class="btn btn-primary"'.$disabled.'><i class="bi bi-floppy"></i> บันทึกข้อมูล</button></div></div>';
    $html.='</section>';
    $json=json_encode($draft,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
    $html.='<script type="application/json" id="kapPlanData">'.$json.'</script>';
    $html.='<script src="'.$e(rtrim(Bootstrap::config()['base_path'],'/') .'/assets/js/kap-plan.js?v=content-20261030-1').'" defer></script>';
    // Student roster selection, separate draft from the score plan and existing enrollment snapshot.
    $html.='<section class="kap-card mt-3 mb-3" id="kapRosterRoot" data-readonly="'.($readonly?'1':'0').'" data-subject="'.$id.'" data-csrf="'.$e(Bootstrap::csrf()).'" data-save-url="'.$e(rtrim(Bootstrap::config()['base_path'],'/').'/api/scores/roster-save.php').'">';
    $html.='<div class="section-title"><div><h2><i class="bi bi-people me-2"></i>ขั้นตอนที่ 2 · เลือกนักเรียนเข้ารายวิชา</h2><p>เลือกนักเรียนทั้งระดับชั้น เลือกเฉพาะห้อง หรือเลือกรายบุคคล แล้วตรวจรายชื่อก่อนบันทึก</p></div><span class="kap-tag" id="rosterDirty">ยังไม่มีการเปลี่ยนแปลง</span></div>';
    if((int)($roster['protected_current_count']??0)>0)$html.='<div class="alert alert-warning">มีทะเบียนผู้เรียนเดิม '.(int)$roster['protected_current_count'].' คนที่ไม่อยู่ในรายชื่อปี/ระดับชั้นปัจจุบัน ระบบจะไม่ลบหรือแก้ไขรายการเหล่านั้นอัตโนมัติ</div>';
    $html.='<div class="row g-3 mb-3"><div class="col-lg-4"><label for="rosterMode" class="form-label fw-bold">รูปแบบการเลือกนักเรียน</label><select id="rosterMode" class="form-select"'.($readonly?' disabled':'').'><option value="ALL_GRADE">ทั้งระดับชั้น</option><option value="SELECTED_ROOMS">เฉพาะห้องที่เลือก</option><option value="INDIVIDUAL">รายบุคคล</option></select></div>';
    $html.='<div class="col-lg-8"><label class="form-label fw-bold">ระดับชั้น / ปีการศึกษา</label><div class="roster-context">'.$e($subject['GradeName']).' · ปีการศึกษา '.$e($subject['AcademicYear_name']??'—').' · ภาคเรียน '.$e($subject['Semester']??'รายปี').'</div></div></div>';
    $html.='<div class="roster-room-panel mb-3" id="rosterRoomPanel" hidden><div class="fw-semibold mb-2">เลือกห้องเรียนได้มากกว่า 1 ห้อง</div><div id="rosterRoomChoices" class="d-flex flex-wrap gap-2"></div></div>';
    $html.='<div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-2"><div><strong>ตรวจสอบรายชื่อนักเรียน</strong><div class="kap-meta" id="rosterCount" aria-live="polite">กำลังโหลดรายชื่อ</div></div><div><label class="form-label mb-1" for="rosterSearch">ค้นหาจากรหัส ชื่อ หรือห้อง</label><input type="search" id="rosterSearch" class="form-control" placeholder="ค้นหานักเรียน" autocomplete="off"></div></div>';
    $html.='<div class="table-responsive roster-scroll"><table class="table kap-table align-middle" id="rosterTable"><thead><tr><th style="width:60px">เลือก</th><th>รหัสนักเรียน</th><th class="text-start">ชื่อ - นามสกุล</th><th>ห้อง</th><th>สถานะทะเบียนห้อง</th></tr></thead><tbody id="rosterRows"></tbody></table></div>';
    $html.='<div class="mt-2 kap-meta" id="rosterDiff"></div><div id="rosterFeedback" role="status" aria-live="polite" class="small mt-2"></div>';
    $html.='<div class="action-bar mt-3"><p class="footnote mb-2">โหมดทดสอบบันทึกรายชื่อเป็นร่างใน Session เท่านั้น ไม่เพิ่ม/ถอน SubjectEnrollment จริง และไม่เปลี่ยนคะแนนของนักเรียนที่ลงทะเบียนอยู่แล้ว</p><div class="d-flex gap-2 flex-wrap justify-content-end"><button id="rosterReset" type="button" class="btn btn-outline-secondary"'.($readonly?' disabled':'').'><i class="bi bi-arrow-counterclockwise"></i> คืนค่าที่บันทึกไว้</button><button id="rosterSave" type="button" class="btn btn-primary"'.($readonly?' disabled':'').'><i class="bi bi-floppy"></i> บันทึกการเลือกนักเรียน</button></div></div></section>';
    $rosterJson=json_encode($roster,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
    $html.='<script type="application/json" id="kapRosterData">'.$rosterJson.'</script>';
    $html.='<script src="'.$e(rtrim(Bootstrap::config()['base_path'],'/').'/assets/js/kap-roster.js?v=roster-v2-crossroom').'" defer></script>';
    Ui::page('ตั้งค่าหน่วยและคะแนน',$actor,'dashboard',$html);
} catch(\Throwable $e){ Bootstrap::fail($e); }
