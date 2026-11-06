<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/admin/load.php';
use Kap\Teacher\{Bootstrap,WebError};
use Kap\Admin\{Security,SubjectCatalog,View};
try {
    Bootstrap::start();$db=Bootstrap::db();$actor=Security::actor($db,'users');
    $svc=new SubjectCatalog($db,$actor);$subjectSchema=$svc->precheck();
    $typeRequired=($subjectSchema['columns']['Subject']['TypeofCourse_id']??'YES')!=='YES';
    $refs=$svc->references();$listing=$svc->listing($_GET);
    // Keep the current search context when opening or closing edit mode.
    $returnParams=array_merge($listing['filter'],['page'=>$listing['page']]);
    $gradeMappingReady=$svc->gradeMappingReady();
    $editing=null;
    if(isset($_GET['edit'])){
        $editing=$svc->detail(Security::positive($_GET['edit'],'edit','รายการรายวิชา'));
        if(!$editing)throw new WebError(404,'ไม่พบรายการที่ต้องการแก้ไข');
    }
    $selectedGradeIds=$editing?$svc->catalogGradeIds((int)$editing['Subject_id']):[];
    $linkedGradeLabels=$editing?$svc->linkedGradeLabels((int)$editing['Subject_id']):[];
    $allGradeIds=$editing?$svc->allSubjectGradeIds((int)$editing['Subject_id']):[];
    $legacyMultiGrade=$editing && count($allGradeIds)>1;
    $selectedSingleGrade=count($allGradeIds)===1?$allGradeIds[0]:null;
    $curriculumHours=$editing?$svc->curriculumHours((int)$editing['Subject_id']):[];
    $editableHourCount=0;
    foreach($curriculumHours as $record){
        if((string)$record['ReviewStatus']==='DRAFT'&&(int)$record['IsActive']===0&&(int)$record['OfferingCountForGrade']===0)$editableHourCount++;
    }
    $canEditMaster=!$editing || (!$legacyMultiGrade && $gradeMappingReady && (int)$editing['IsActive']===0);
    $editAction=$editing?($canEditMaster?'update-subject-with-hours':'save-curriculum-hours'):'create-subject';
    $canSubmit=$editing?($canEditMaster || $editableHourCount>0):$gradeMappingReady;
    $v=static fn(mixed $x):string=>View::e($x);
    $url=View::path('/pages/admin/subjects.php');
    $returnUrl=$url.'?'.http_build_query($returnParams);
    $api=View::path('/api/admin/subjects.php');
    $choice=$editing?:['Subject_id'=>null,'Subject_code'=>'','Subject_name'=>'','SubjectKind'=>'COURSE',
        'Learning_Area_id'=>null,'TypeofCourse_id'=>null,'IsActive'=>0];
    View::header('ทะเบียนรายวิชาและกิจกรรม','subjects',$actor);
?>
<div class="kap-card mb-3">
    <div class="d-flex align-items-start gap-2"><i class="bi bi-info-circle text-primary mt-1"></i>
    <div><strong>ระยะ Admin 3.0: ทะเบียนรายวิชากลาง</strong>
    <div class="kap-admin-hint">รายการใหม่จะบันทึกเป็นร่าง (IsActive=0) เสมอ · ไม่สร้างหลักสูตรสถานศึกษา ชั่วโมง หน่วยกิต กลุ่มเรียน หรือรายชื่อนักเรียนอัตโนมัติ · การเปิดใช้งานต้องยืนยันการตรวจข้อมูลต้นทางด้วยตนเอง</div></div></div>
</div>
<div class="kap-card mb-3" id="subjectForm">
    <div class="d-flex justify-content-between flex-wrap gap-2 mb-3 align-items-center">
      <h2 class="kap-admin-title mb-0"><?= $editing?'แก้ไขทะเบียนรายวิชา/กิจกรรม #'.(int)$editing['Subject_id']:'เพิ่มทะเบียนรายวิชา/กิจกรรม' ?></h2>
    </div>
    <?php if(!$gradeMappingReady): ?>
      <div class="alert alert-warning" role="alert">ยังไม่พบตาราง <code>SubjectGradeLevel</code> สำหรับกำหนดระดับชั้น กรุณาสำรองฐานข้อมูล แล้วให้ผู้ดูแลสร้างตาราง <code>SubjectGradeLevel</code> ในฐาน <code>anusarn_kap</code> ผ่าน phpMyAdmin ก่อนบันทึกแบบฟอร์มนี้ ข้อมูลเดิมยังไม่ถูกเปลี่ยน</div>
    <?php endif; ?>
    <?php if($legacyMultiGrade): ?>
      <div class="alert alert-warning" role="alert"><strong>ทะเบียนเดิมเชื่อมมากกว่าหนึ่งระดับชั้น</strong> (<?= $v(implode(', ', $linkedGradeLabels)) ?>) ไม่สามารถบันทึกการแก้ไขโดยเลือกระดับเดียวแล้วตัดระดับอื่นทิ้งได้ เพราะอาจมีข้อมูลหลักสูตรสถานศึกษาหรือแผนเปิดสอนอ้างอิงอยู่ ต้องตรวจและแยกรายวิชาตามชั้นก่อน ข้อมูลเดิมยังคงอยู่ครบ</div>
    <?php endif; ?>
    <?php if($editing && (int)$editing['IsActive']===1): ?>
      <div class="alert alert-warning">รายการนี้เปิดใช้งานแล้ว หากต้องแก้รายละเอียด ต้องปิดใช้งานก่อน เพื่อป้องกันการเปลี่ยนข้อมูลต้นทางที่ถูกอ้างอิงอยู่</div>
    <?php endif; ?>
    <form method="post" class="kap-admin-form row g-3" action="<?= $v($api) ?>" data-confirm="<?= $v($editing?'ยืนยันบันทึกการแก้ไขทะเบียนรายวิชา/กิจกรรมและชั่วโมงเรียนทั้งหมด?':'ยืนยันเพิ่มทะเบียนรายวิชา/กิจกรรมเป็นร่าง?') ?>">
      <?= View::csrf() ?>
      <input type="hidden" name="action" value="<?= $v($editAction) ?>">
      <?php if($editing): ?><input type="hidden" name="subject_id" value="<?= (int)$editing['Subject_id'] ?>"><?php endif; ?>
      <input type="hidden" name="grade_selection_present" value="1">
      <fieldset class="col-12 p-0 m-0 border-0" <?= $canEditMaster?'':'disabled' ?>>
        <div class="row g-3">
      <div class="col-12 col-lg-3">
        <label class="form-label" for="subjectKind">รายการ <span class="text-danger">*</span></label>
        <select class="form-select" name="subject_kind" id="subjectKind" required>
          <option value="COURSE" <?= $choice['SubjectKind']==='COURSE'?'selected':'' ?>>รายวิชา</option>
          <option value="ACTIVITY" <?= $choice['SubjectKind']==='ACTIVITY'?'selected':'' ?>>กิจกรรม</option>
        </select>
      </div>
      <div class="col-12 col-lg-3">
        <label class="form-label" for="subjectCode">รหัสรายวิชา/กิจกรรม</label>
        <input class="form-control" id="subjectCode" name="subject_code" maxlength="80" value="<?= $v($choice['Subject_code']) ?>" placeholder="เว้นว่างได้หากยังไม่มีรหัส">
      </div>
      <div class="col-12 col-lg-6">
        <label class="form-label" for="subjectName">ชื่อรายวิชา/กิจกรรม <span class="text-danger">*</span></label>
        <input class="form-control" id="subjectName" name="subject_name" maxlength="255" value="<?= $v($choice['Subject_name']) ?>" required placeholder="กรอกชื่อจากเอกสารหลักสูตรสถานศึกษาจริง">
      </div>
      <div class="col-12 col-lg-6">
        <label class="form-label" for="areaId">กลุ่มสาระการเรียนรู้</label>
        <select class="form-select" name="learning_area_id" id="areaId">
          <option value="">ยังไม่ระบุ / ไม่เกี่ยวข้องกับกิจกรรม</option>
          <?php foreach($refs['areas'] as $r): ?>
            <?php if((int)$r['IsActive']===1 || (string)$r['LearningArea_id']===(string)$choice['Learning_Area_id']): ?>
              <option value="<?= (int)$r['LearningArea_id'] ?>" <?= (string)$r['LearningArea_id']===(string)$choice['Learning_Area_id']?'selected':'' ?>><?= $v($r['LearningArea_name']) ?><?= (int)$r['IsActive']===0?' (ปิดใช้งาน)':'' ?></option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-lg-6">
        <label class="form-label" for="courseTypeId">ประเภทรายวิชา/กิจกรรม</label>
        <select class="form-select" name="course_type_id" id="courseTypeId" <?= $typeRequired?'required':'' ?>>
          <option value=""><?= $typeRequired?'กรุณาเลือกประเภทตามโครงสร้างฐานข้อมูล':'ยังไม่ระบุ (บันทึกร่างได้)' ?></option>
          <?php foreach($refs['types'] as $r): ?>
            <?php if((int)$r['IsActive']===1 || (string)$r['TypeofCourse_id']===(string)$choice['TypeofCourse_id']): ?>
              <option value="<?= (int)$r['TypeofCourse_id'] ?>" <?= (string)$r['TypeofCourse_id']===(string)$choice['TypeofCourse_id']?'selected':'' ?>><?= $v($r['TypeofCourse_name']) ?><?= (int)$r['IsActive']===0?' (ปิดใช้งาน)':'' ?></option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-lg-6" id="subjectGradeLevelFieldset">
        <label for="subjectGradeLevel" class="form-label fw-semibold">ระดับชั้นที่ใช้สอน <span class="text-danger">*</span></label>
        <select id="subjectGradeLevel" class="form-select" name="grade_level_id" required <?= $legacyMultiGrade || ($editing && (int)$editing['IsActive']===1)?'disabled':'' ?>>
          <option value="">กรุณาเลือกระดับชั้น (เลือกได้ 1 ชั้น)</option>
          <?php foreach($refs['grades'] as $grade): ?>
            <?php $gradeId=(int)$grade['GradeLevel_id'];$selected=$selectedSingleGrade===$gradeId; ?>
            <?php if((int)$grade['IsActive']===1 || $selected): ?>
              <option value="<?= $gradeId ?>" <?= $selected?'selected':'' ?>><?= $v(SubjectCatalog::shortGradeName((string)$grade['GradeCode'],(string)$grade['GradeName'])) ?><?= (int)$grade['IsActive']===0?' (ปิดใช้งาน)':'' ?></option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
        <p class="kap-admin-hint mt-2 mb-0">ทะเบียน 1 รายการต้องมีระดับชั้นเดียว ไม่ว่าจะมีรหัสวิชาหรือไม่ หากใช้ชื่อ/รหัสเดิมในชั้นอื่น ให้เพิ่มเป็นทะเบียนอีกรายการ ไม่สร้างหลักสูตรสถานศึกษาหรือแผนเปิดสอนให้อัตโนมัติ</p>
        <?php if($linkedGradeLabels): ?>
          <p class="kap-admin-hint mt-2 mb-0"><strong>ระดับชั้นจากหลักสูตรสถานศึกษา/แผนเปิดสอน:</strong> <?= $v(implode(', ',$linkedGradeLabels)) ?> · หากมีความสัมพันธ์แล้ว ต้องจัดการในโมดูลต้นทางก่อนเปลี่ยนชั้น</p>
        <?php endif; ?>
        <?php if(!$refs['grades']): ?><p class="text-danger mt-2 mb-0">ไม่พบข้อมูลระดับชั้นใน GradeLevel กรุณาตรวจฐานข้อมูล</p><?php endif; ?>
      </div>
        </div>
      </fieldset>
      <?php if($editing): ?>
      <div class="col-12 mt-4 pt-3 border-top" id="curriculumHours">
        <h3 class="kap-admin-title">หน่วยกิตและเวลาเรียนตามหลักสูตรสถานศึกษา</h3>
        <p class="kap-admin-hint">ค่าหน่วยกิตและชั่วโมงเรียนบันทึกแยกตาม <code>CurriculumSubject_id</code> ของแต่ละหลักสูตรสถานศึกษาและระดับชั้น</p>
        <?php if(!$canEditMaster): ?>
          <div class="alert alert-warning">ข้อมูลทะเบียนกลางถูกล็อกตามเงื่อนไขความปลอดภัยเดิม<?= $editableHourCount>0?' แต่ยังสามารถแก้หน่วยกิตและชั่วโมงเรียนของรายการร่างด้านล่างได้':'' ?> โดยไม่เปลี่ยนระดับชั้นและความสัมพันธ์เดิม</div>
        <?php endif; ?>
        <?php if(!$curriculumHours): ?>
          <div class="alert alert-info">ยังไม่มีรายวิชานี้เชื่อมกับหลักสูตรสถานศึกษา จึงไม่มีข้อมูลหน่วยกิตหรือชั่วโมงเรียนให้แก้ไข</div>
        <?php else: ?>
      <div class="table-responsive">
      <table class="table kap-table align-middle">
        <thead><tr><th>หลักสูตรสถานศึกษา / ระดับชั้น</th><th>หน่วยกิต</th><th>เวลาเรียน/สัปดาห์ (ชม.)</th><th>เวลาเรียน/ปี (ชม.)</th><th>สถานะ</th></tr></thead>
        <tbody>
        <?php foreach($curriculumHours as $record): ?>
          <?php
            $cid=(int)$record['CurriculumSubject_id'];
            $editable=(string)$record['ReviewStatus']==='DRAFT'&&(int)$record['IsActive']===0&&(int)$record['OfferingCountForGrade']===0;
            $fmt=static fn(mixed $n):string=>number_format((float)$n,2,'.','');
          ?>
          <tr>
            <td>
              <strong><?= $v(View::curriculumLabel($record['Curriculum_name'])) ?></strong>
              <span class="d-block"><?= $v(SubjectCatalog::shortGradeName((string)$record['GradeCode'],(string)$record['GradeName'])) ?> · #<?= $cid ?></span>
              <small class="text-muted">ช่วง <?= $v($record['InstructionalPeriod']) ?><?= $record['Semester']!==null?' · ภาคเรียน '.$v($record['Semester']):'' ?><?= $record['SelectionGroupCode']!==null?' · กลุ่มเลือก '.$v($record['SelectionGroupCode']):'' ?></small>
            </td>
            <?php foreach(['credit'=>'Credit','weekly'=>'WeeklyHours','annual'=>'TotalInstructionalHours'] as $field=>$column): ?>
              <td style="min-width:140px">
              <?php if($editable): ?>
                <input type="hidden" name="hours[<?= $cid ?>][expected_<?= $field ?>]" value="<?= $v($fmt($record[$column])) ?>">
                <input type="number" class="form-control form-control-sm" name="hours[<?= $cid ?>][<?= $field ?>]" min="<?= $field==='credit'?'0':'0.01' ?>" step="0.01" max="<?= $field==='credit'?'99999.99':($field==='weekly'?'9999.99':'999999.99') ?>" required value="<?= $v($fmt($record[$column])) ?>" aria-label="<?= $v(($field==='credit'?'หน่วยกิต':($field==='weekly'?'ชั่วโมงต่อสัปดาห์':'ชั่วโมงต่อปี')).' ของ '.View::curriculumLabel($record['Curriculum_name']).' '.SubjectCatalog::shortGradeName((string)$record['GradeCode'],(string)$record['GradeName'])) ?>">
              <?php else: ?>
                <?= $v($fmt($record[$column])) ?>
              <?php endif; ?>
              </td>
            <?php endforeach; ?>
            <td>
              <?= $editable?'<span class="kap-tag wait">ร่าง · แก้ไขได้</span>':'<span class="kap-tag ok">ล็อกการแก้ไข</span>' ?>
              <?php if((int)$record['OfferingCountForGrade']>0): ?><small class="d-block text-muted">มีข้อมูลเปิดสอนในชั้นนี้แล้ว</small><?php endif; ?>
              <?php if((float)$record['WeeklyHours']*40!==(float)$record['TotalInstructionalHours']): ?>
                <small class="d-block text-warning">โปรดตรวจ: ชั่วโมง/ปีต่างจาก 40 × ชั่วโมง/สัปดาห์ (อาจตั้งใจตามแผน)</small>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="col-12 pt-2">
        <p class="kap-admin-hint mb-3">รหัสว่างบันทึกเป็น SQL NULL · ไม่เปิดรายวิชาหรือหลักสูตรสถานศึกษาอัตโนมัติ · รายการที่แก้ไขจะบันทึก AuditLog</p>
        <div class="d-flex justify-content-end align-items-center gap-2 flex-wrap kap-edit-actions">
          <?php if($editing): ?><a class="btn btn-outline-secondary" href="<?= $v($returnUrl) ?>"><i class="bi bi-x-circle"></i> ยกเลิกการแก้ไข</a><?php endif; ?>
          <button type="submit" class="btn btn-primary" <?= $canSubmit?'':'disabled' ?>><i class="bi bi-save"></i> <?= $editing?'บันทึกการแก้ไขทั้งหมด':'บันทึกร่างรายวิชา/กิจกรรม' ?></button>
        </div>
      </div>
    </form>
</div>
<div class="kap-card mb-3">
  <h2 class="kap-admin-title">ค้นหาและกรองทะเบียน</h2>
  <form method="get" class="row g-3 align-items-end" id="subjectRegistryFilterForm">
    <div class="col-12 col-lg-6">
      <label class="form-label" for="searchSubjects">ค้นหาชื่อหรือรหัส</label>
      <input class="form-control" id="searchSubjects" name="q" value="<?= $v($listing['filter']['q']) ?>" placeholder="ค้นหารายวิชาหรือกิจกรรม">
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <label class="form-label" for="subjectCurriculumFilter">หลักสูตรสถานศึกษา</label>
      <select class="form-select" id="subjectCurriculumFilter" name="curriculum_id">
        <option value="">ทุกหลักสูตรสถานศึกษา</option>
        <?php foreach($refs['curricula'] as $curriculum): ?>
          <?php $curriculumId=(int)$curriculum['Curriculum_id']; ?>
          <option value="<?= $curriculumId ?>" <?= (string)$curriculumId===$listing['filter']['curriculum_id']?'selected':'' ?>><?= $v(View::curriculumLabel($curriculum['Curriculum_name'])) ?><?= (int)$curriculum['IsActive']===0?' (ปิดใช้งาน)':'' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-6 col-lg-3">
      <label class="form-label" for="subjectGradeFilter">ระดับชั้น</label>
      <select class="form-select" id="subjectGradeFilter" name="grade_id">
        <option value="">ทุกระดับชั้น</option>
        <?php foreach($refs['grades'] as $grade): ?>
          <?php $gradeId=(int)$grade['GradeLevel_id'];$availableCurricula=$refs['curriculumGrades'][$gradeId]??[]; ?>
          <option value="<?= $gradeId ?>" data-curricula="<?= $v(implode(',',array_unique($availableCurricula))) ?>" <?= (string)$gradeId===$listing['filter']['grade_id']?'selected':'' ?>><?= $v(SubjectCatalog::shortGradeName((string)$grade['GradeCode'],(string)$grade['GradeName'])) ?><?= (int)$grade['IsActive']===0?' (ปิดใช้งาน)':'' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-lg-3">
      <label class="form-label" for="courseTypeFilter">ประเภทรายวิชา</label>
      <select class="form-select" id="courseTypeFilter" name="course_type_id">
        <option value="">ทุกประเภทรายวิชา/กิจกรรม</option>
        <?php foreach($refs['types'] as $type): ?>
          <?php $courseTypeId=(int)$type['TypeofCourse_id']; ?>
          <option value="<?= $courseTypeId ?>" <?= (string)$courseTypeId===$listing['filter']['course_type_id']?'selected':'' ?>><?= $v($type['TypeofCourse_name']) ?><?= (int)$type['IsActive']===0?' (ปิดใช้งาน)':'' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-lg-3">
      <label class="form-label" for="statusFilter">สถานะ</label>
      <select class="form-select" id="statusFilter" name="status">
        <?php foreach([''=>'ทั้งหมด','0'=>'ร่าง / ปิดใช้งาน','1'=>'เปิดใช้งาน'] as $value=>$label): ?><option value="<?= $v($value) ?>" <?= $listing['filter']['status']===(string)$value?'selected':'' ?>><?= $v($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-lg-6 d-flex gap-2">
      <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-search"></i> ค้นหา</button>
      <a class="btn btn-outline-secondary" href="<?= $v($url) ?>">ล้างตัวกรอง</a>
    </div>
    <div class="col-12"><p class="kap-admin-hint mb-0">กรองได้หลายเงื่อนไขพร้อมกัน · หลักสูตรสถานศึกษาและระดับชั้นต้องสัมพันธ์กันในโครงสร้างรายวิชาเดียวกัน · หากเลือกเฉพาะระดับชั้น ระบบค้นจากทะเบียนระดับชั้น หลักสูตรสถานศึกษา และแผนเปิดสอน</p></div>
  </form>
</div>
<div class="kap-card">
  <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="kap-admin-title mb-0">รายการในทะเบียน</h2><span class="kap-admin-hint">ทั้งหมด <?= number_format($listing['total']) ?> รายการ</span></div>
  <p class="kap-admin-hint mb-2">
    <?php if($listing['filter']['grade_id']!==''): ?>
      แสดงเฉพาะระดับชั้นที่เลือกในผลการค้นหา<?= $listing['filter']['curriculum_id']!==''?' และอยู่ในหลักสูตรสถานศึกษาที่เลือก':'' ?> · ข้อมูลระดับชั้นอื่นของรายวิชายังคงอยู่ตามเดิม
    <?php elseif($listing['filter']['curriculum_id']!==''): ?>
      แสดงระดับชั้นที่รายวิชาเชื่อมโยงกับหลักสูตรสถานศึกษาที่เลือกเท่านั้น · ข้อมูลจากหลักสูตรสถานศึกษาอื่นยังคงอยู่ตามเดิม
    <?php else: ?>
      ระดับชั้นที่ใช้สอนมาจากทะเบียนระดับชั้นของรายวิชา รวมกับความสัมพันธ์ที่มีอยู่ในหลักสูตรสถานศึกษาและแผนเปิดสอน หากไม่มีข้อมูลจะแสดง “ยังไม่กำหนด”
    <?php endif; ?>
  </p>
  <div class="table-responsive"><table class="table kap-table align-middle kap-subject-table"><thead><tr><th>รหัสวิชา</th><th>ชื่อวิชา</th><th title="ระดับชั้นที่กำหนดในทะเบียน หลักสูตรสถานศึกษา หรือแผนเปิดสอน">ระดับชั้น</th><th>ประเภทรายวิชา/กิจกรรม</th><th>กลุ่มสาระ</th><th>สถานะ</th></tr></thead><tbody>
    <?php foreach($listing['rows'] as $r): ?>
      <tr>
        <td><?= $v($r['Subject_code']??'')!==''?$v($r['Subject_code']):'<span class="text-muted">ยังไม่มี</span>' ?></td>
        <td>
          <strong><?= $v($r['Subject_name']) ?></strong>
          <a class="btn btn-sm btn-soft mt-1" href="<?= $v($url.'?'.http_build_query(array_merge($returnParams,['edit'=>(int)$r['Subject_id']])).'#subjectForm') ?>"><i class="bi bi-pencil"></i> แก้ไข</a>
        </td>
        <td>
          <?php if(!empty($r['TeachingGrades'])): ?>
            <?= $v(implode(', ', $r['TeachingGrades'])) ?>
            <?php if(count($r['TeachingGrades'])>1 && $listing['filter']['grade_id']===''): ?><span class="d-block text-warning-emphasis small">ทะเบียนเดิมหลายระดับชั้น: ควรแยกรายการ</span><?php endif; ?>
          <?php else: ?>
            <span class="text-muted" title="ยังไม่มีการผูกระดับชั้นในโครงสร้างรายวิชาหรือแผนเปิดสอน">ยังไม่กำหนด</span>
          <?php endif; ?>
        </td>
        <td><?= $v($r['TypeofCourse_name']??'ยังไม่ระบุ') ?></td>
        <td><?= $v($r['LearningArea_name']??'ยังไม่ระบุ') ?></td>
        <td>
          <div class="d-flex flex-column align-items-start gap-2">
            <?= (int)$r['IsActive']===1?'<span class="kap-tag ok">เปิดใช้งาน</span>':'<span class="kap-tag wait">ร่าง/ปิดใช้งาน</span>' ?>
            <?php if((int)$r['IsActive']===1): ?>
              <form class="kap-admin-form" method="post" data-danger="1" data-confirm="ยืนยันปิดใช้งานรายการนี้? ข้อมูลเดิมจะไม่ถูกลบ" action="<?= $v($api) ?>">
                <?= View::csrf() ?><input type="hidden" name="action" value="set-subject-status"><input type="hidden" name="subject_id" value="<?= (int)$r['Subject_id'] ?>"><input type="hidden" name="is_active" value="0"><button type="submit" class="btn btn-sm btn-outline-secondary">ปิดใช้งาน</button>
              </form>
            <?php else: ?>
              <form class="kap-admin-form kap-activate-form" method="post" data-confirm="ตรวจสอบชื่อ ประเภท กลุ่มสาระ และเอกสารต้นทางเรียบร้อยแล้ว ยืนยันเปิดใช้งาน?" action="<?= $v($api) ?>">
                <?= View::csrf() ?><input type="hidden" name="action" value="set-subject-status"><input type="hidden" name="subject_id" value="<?= (int)$r['Subject_id'] ?>"><input type="hidden" name="is_active" value="1">
                <label class="form-check-label kap-small-check"><input type="checkbox" name="review_confirm" value="yes" required> ตรวจแล้ว</label>
                <button type="submit" class="btn btn-sm btn-outline-success">เปิดใช้งาน</button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$listing['rows']): ?><tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีรายการที่ตรงกับตัวกรอง ไม่ได้สร้างข้อมูลตัวอย่างให้อัตโนมัติ</td></tr><?php endif; ?>
  </tbody></table></div>
  <?php if($listing['pages']>1): ?><nav class="d-flex justify-content-between mt-3" aria-label="หน้าทะเบียนรายวิชา">
      <?php $params=$listing['filter']; ?>
      <a class="btn btn-sm btn-outline-secondary <?= $listing['page']===1?'disabled':'' ?>" href="<?= $v($url.'?'.http_build_query(array_merge($params,['page'=>max(1,$listing['page']-1)]))) ?>">ก่อนหน้า</a>
      <span class="kap-admin-hint align-self-center">หน้า <?= (int)$listing['page'] ?> / <?= (int)$listing['pages'] ?></span>
      <a class="btn btn-sm btn-outline-secondary <?= $listing['page']===$listing['pages']?'disabled':'' ?>" href="<?= $v($url.'?'.http_build_query(array_merge($params,['page'=>min($listing['pages'],$listing['page']+1)]))) ?>">ถัดไป</a>
  </nav><?php endif; ?>
</div>
<script>
// On this page only: update available grade choices from the real
// CurriculumSubject relationships already read by the PHP controller.
(() => {
  'use strict';
  const curriculum=document.getElementById('subjectCurriculumFilter');
  const grade=document.getElementById('subjectGradeFilter');
  if(!curriculum || !grade)return;
  const refresh=() => {
    const id=curriculum.value;
    for(const option of grade.options){
      if(option.value==='')continue;
      const allowed=(option.dataset.curricula||'').split(',');
      option.disabled=Boolean(id) && !allowed.includes(id);
      option.hidden=option.disabled;
    }
    if(grade.selectedOptions.length && grade.selectedOptions[0].disabled)grade.value='';
  };
  curriculum.addEventListener('change',refresh);
  refresh();
})();
</script>
<?php
    View::footer();
}catch(\Throwable $e){\Kap\Teacher\Bootstrap::fail($e);}
