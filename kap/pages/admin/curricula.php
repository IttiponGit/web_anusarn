<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/admin/load.php';
use Kap\Admin\{Security,View,CurriculumCatalog,CurriculumTypeService};
use Kap\Teacher\{Bootstrap,WebError};
try {
    Bootstrap::start();
    $db=Bootstrap::db();
    $actor=Security::actor($db,'users');
    $service=new CurriculumCatalog($db,$actor);
    $service->precheck();
    require_once dirname(__DIR__,2).'/app/admin/CurriculumTypeReference.php';
    require_once dirname(__DIR__,2).'/app/admin/CurriculumTypeService.php';
    $typeService=new CurriculumTypeService($db,$actor);
    $tab=(string)($_GET['tab']??'curricula');
    if(!in_array($tab,['curricula','sections','cohorts'],true))$tab='curricula';
    $all=$service->curricula();
    $refs=$typeService->typeReferences();
    // Preserve the same year-descending order for option groups as for the main table.
    $orderedRefs=[];
    foreach($all as $curriculumRow){
        $key=(int)$curriculumRow['Curriculum_id'];
        if(isset($refs[$key]))$orderedRefs[$key]=$refs[$key];
    }
    $refs=$orderedRefs;
    $filterCurriculum=isset($_GET['curriculum']) && (string)$_GET['curriculum']!=='' ? Security::positive($_GET['curriculum'],'curriculum','หลักสูตรสถานศึกษา'):null;
    $editCurriculum=isset($_GET['edit_curriculum'])?$service->curriculum(Security::positive($_GET['edit_curriculum'],'edit_curriculum','หลักสูตรสถานศึกษา')):null;
    if(isset($_GET['edit_curriculum'])&&!$editCurriculum)throw new WebError(404,'ไม่พบหลักสูตรสถานศึกษาที่ต้องการแก้ไข');
    $editSection=isset($_GET['edit_section'])?$service->section(Security::positive($_GET['edit_section'],'edit_section','ประเภทรายวิชา')):null;
    if(isset($_GET['edit_section'])&&!$editSection)throw new WebError(404,'ไม่พบประเภทรายวิชาที่ต้องการแก้ไข');
    if($editSection){$filterCurriculum=(int)$editSection['Curriculum_id'];$tab='sections';}
    if($editCurriculum)$tab='curricula';
    $query=is_string($_GET['q']??'')?(string)($_GET['q']??''):'';
    $sections=$tab==='sections'?$service->sections($filterCurriculum,$query):[];
    $years=$tab==='cohorts'?$service->mappingYears():[];
    $yearId=isset($_GET['year_id']) && (string)$_GET['year_id']!=='' ? Security::positive($_GET['year_id'],'year_id','ปีการศึกษา'):null;
    $mappings=$tab==='cohorts'?$service->cohortMappings($yearId):['ready'=>false,'rows'=>[]];
    $comparison=$tab==='sections' && $filterCurriculum!==null && isset($refs[$filterCurriculum])
      ?$typeService->compare($filterCurriculum,$refs[$filterCurriculum]['types']):[];
    $primaryRows=[];$legacyRows=[];
    foreach($sections as $entry){
        $cid=(int)$entry['Curriculum_id'];
        $names=$refs[$cid]['types']??[];
        if($entry['ParentSection_id']===null && (!$names || in_array($entry['SectionName'],$names,true)))$primaryRows[]=$entry;
        else $legacyRows[]=$entry;
    }
    $v=static fn(mixed $s):string=>View::e($s);
    $base=View::path('/pages/admin/curricula.php');
    $api=View::path('/api/admin/curricula.php');
    $typesApi=View::path('/api/admin/curriculum-types.php');
    $cv=$editCurriculum??['Curriculum_id'=>null,'Curriculum_name'=>'','Notes'=>'','IsActive'=>0];
    $sc=$editSection??['CurriculumSection_id'=>null,'Curriculum_id'=>$filterCurriculum,'ParentSection_id'=>null,'SectionCode'=>'','SectionName'=>'','SortOrder'=>0,'IsActive'=>0];
    // Retain the newer Nov 3 display summaries whenever deployed on Hosting.
    $displayCurricula=[];$displayLinks=[];$displaySectionGrades=[];
    $displayClass=dirname(__DIR__,2).'/app/CurriculumDisplayTables.php';
    if(is_file($displayClass)){
        require_once $displayClass;
        if(class_exists('\\KAP\\CurriculumDisplayTables')){
            if($tab==='curricula')$displayCurricula=\KAP\CurriculumDisplayTables::curricula($db);
            if($tab==='sections'){
                $displayLinks=\KAP\CurriculumDisplayTables::linksBySection($db);
                $displaySectionGrades=\KAP\CurriculumDisplayTables::gradesBySection($db);
            }
        }
    }
    View::header('หลักสูตรสถานศึกษาและประเภทรายวิชา','curricula',$actor);
    if(is_file(dirname(__DIR__,2).'/assets/kap-curriculum-display.css'))
      echo '<link rel="stylesheet" href="'.$v(View::path('/assets/kap-curriculum-display.css?v=20261103')).'">';
?>
<div class="kap-card mb-3">
  <strong><i class="bi bi-shield-check text-primary"></i> จัดการหลักสูตรสถานศึกษาและประเภทรายวิชาจาก MariaDB</strong>
  <div class="kap-admin-hint mt-1">ชื่อประเภทอ้างอิงจาก Google Sheets ต้นฉบับของแต่ละรุ่นหลักสูตรสถานศึกษา · รหัสภายในถูกจัดการโดยระบบ · การซิงค์ต้องยืนยันเอง ไม่ย้าย/ลบความสัมพันธ์รายวิชาที่บันทึกแล้ว</div>
</div>
<div class="kap-card mb-3">
  <div class="d-flex flex-wrap gap-2" role="navigation" aria-label="งานหลักสูตรสถานศึกษา">
    <?php foreach(['curricula'=>'ทะเบียนหลักสูตรสถานศึกษา','sections'=>'ประเภทรายวิชา','cohorts'=>'หลักสูตรสถานศึกษาที่ใช้ตามปีและระดับชั้น'] as $id=>$label): ?>
      <a class="btn <?= $tab===$id?'btn-primary':'btn-outline-primary' ?>" href="<?= $v($base.'?tab='.$id) ?>" <?= $tab===$id?'aria-current="page"':'' ?>><?= $v($label) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php if($tab==='curricula'): ?>
<div class="kap-card mb-3" id="curriculum-form">
 <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="kap-admin-title mb-0"><?= $editCurriculum?'แก้ไขหลักสูตรสถานศึกษา #'.(int)$cv['Curriculum_id']:'เพิ่มหลักสูตรสถานศึกษา' ?></h2>
 <?php if($editCurriculum): ?><a class="btn btn-sm btn-outline-secondary" href="<?= $v($base) ?>">ยกเลิกการแก้ไข</a><?php endif; ?></div>
 <?php if($editCurriculum && (int)$cv['IsActive']===1): ?><div class="alert alert-warning">หลักสูตรสถานศึกษานี้เปิดใช้งานอยู่ ต้องปิดใช้งานก่อนแก้รายละเอียด และตรวจความสัมพันธ์ที่เกี่ยวข้อง</div><?php endif; ?>
 <form method="post" action="<?= $v($api) ?>" class="kap-admin-form row g-3" data-confirm="<?= $editCurriculum?'ยืนยันการแก้ไขข้อมูลหลักสูตรสถานศึกษา?':'ยืนยันเพิ่มหลักสูตรสถานศึกษาเป็นฉบับร่าง?' ?>">
 <?= View::csrf() ?><input type="hidden" name="action" value="<?= $editCurriculum?'update-curriculum':'create-curriculum' ?>">
 <?php if($editCurriculum): ?><input type="hidden" name="curriculum_id" value="<?= (int)$cv['Curriculum_id'] ?>"><?php endif; ?>
 <div class="col-12 col-lg-6"><label class="form-label" for="curriculumName">ชื่อหลักสูตรสถานศึกษา <span class="text-danger">*</span></label><input class="form-control" id="curriculumName" name="curriculum_name" required maxlength="255" value="<?= $v($cv['Curriculum_name']) ?>" placeholder="ชื่อจากเอกสารหลักสูตรสถานศึกษาจริง"></div>
 <div class="col-12 col-lg-6"><label class="form-label" for="curriculumNotes">หมายเหตุ / หลักฐานการตรวจสอบ</label><textarea class="form-control" id="curriculumNotes" name="notes" rows="2" maxlength="5000"><?= $v($cv['Notes']) ?></textarea></div>
 <div class="col-12"><button type="submit" class="btn btn-primary" <?= $editCurriculum && (int)$cv['IsActive']===1?'disabled':'' ?>><i class="bi bi-save"></i> <?= $editCurriculum?'บันทึกการแก้ไข':'บันทึกร่างหลักสูตรสถานศึกษา' ?></button></div>
 </form>
</div>
<div class="kap-card">
 <h2 class="kap-admin-title">หลักสูตรสถานศึกษาในระบบ <span class="kap-admin-hint">(<?= count($all) ?> รายการ)</span></h2>
 <div class="table-responsive"><table class="table kap-table align-middle"><thead><tr><th>หลักสูตรสถานศึกษา</th><th>ช่วงรุ่นหลักสูตรสถานศึกษา</th><th>จำนวนประเภท</th><th>รายการประเภทรายวิชา</th><th>สถานะ / จัดการ</th></tr></thead><tbody>
 <?php foreach($all as $c): $cid=(int)$c['Curriculum_id']; $extra=[]; if(!empty($displayCurricula))foreach($displayCurricula as $dc)if((int)$dc['Curriculum_id']===$cid){$extra=$dc;break;} ?>
 <tr>
  <td><strong><?= $v(View::curriculumLabel($c['Curriculum_name'])) ?></strong><small class="d-block text-muted">#<?= $cid ?></small><a class="btn btn-sm btn-soft mt-1" href="<?= $v($base.'?edit_curriculum='.$cid.'#curriculum-form') ?>"><i class="bi bi-pencil"></i> แก้ไข</a></td>
  <td><?php if(isset($refs[$cid])): ?><?= $v($refs[$cid]['start'].'–'.$refs[$cid]['end']) ?> <small class="d-block text-muted">ประถมศึกษา</small>
  <?php elseif(!empty($extra['periods'])): ?><?php foreach($extra['periods'] as $period=>$gradeLabels): ?><div><?= $v($period) ?> <small><?= $v(implode(', ',array_keys($gradeLabels))) ?></small></div><?php endforeach; ?>
  <?php else: ?><span class="text-muted">ยังไม่ผูกช่วงรุ่น</span><?php endif; ?></td>
  <td><?= (int)$c['SectionCount'] ?> รายการในฐาน <small class="d-block text-muted">สถานะร่าง <?= (int)$c['DraftCount'] ?></small>
    <?php if(isset($refs[$cid])): ?><small class="d-block">ต้นฉบับมี <?= count($refs[$cid]['types']) ?> ประเภทหลัก</small><?php endif; ?>
    <a class="btn btn-sm btn-outline-primary mt-1" href="<?= $v($base.'?tab=sections&curriculum='.$cid) ?>">ดูประเภทรายวิชา</a></td>
  <td>
   <?php if(isset($refs[$cid])): foreach($refs[$cid]['types'] as $typeLabel): ?><div class="small mb-1">• <?= $v($typeLabel) ?></div><?php endforeach; ?>
   <?php elseif(!empty($extra['sections'])): foreach($extra['sections'] as $section): ?><div class="small mb-1"><?= $v($section['SectionName']) ?></div><?php endforeach; ?>
   <?php else: ?><span class="text-muted">ยังไม่มีรายการต้นฉบับสำหรับรุ่นนี้</span><?php endif; ?>
  </td>
  <td><?= (int)$c['IsActive']===1?'<span class="kap-tag ok">เปิดใช้งาน</span>':'<span class="kap-tag wait">ร่าง / ปิดใช้งาน</span>' ?>
    <form method="post" action="<?= $v($api) ?>" class="kap-admin-form mt-2" data-confirm="<?= (int)$c['IsActive']===1?'ยืนยันปิดใช้งานหลักสูตรสถานศึกษานี้?':'ตรวจเอกสารหลักสูตรสถานศึกษาแล้ว ยืนยันเปิดใช้งาน?' ?>">
      <?= View::csrf() ?><input type="hidden" name="action" value="curriculum-status"><input type="hidden" name="curriculum_id" value="<?= $cid ?>"><input type="hidden" name="is_active" value="<?= (int)$c['IsActive']===1?'0':'1' ?>">
      <?php if((int)$c['IsActive']===0): ?><label class="kap-small-check"><input type="checkbox" name="review_confirm" value="yes" required> ตรวจเอกสารแล้ว</label><?php endif; ?>
      <button type="submit" class="btn btn-sm <?= (int)$c['IsActive']===1?'btn-outline-secondary':'btn-outline-success' ?>"><?= (int)$c['IsActive']===1?'ปิดใช้งาน':'เปิดใช้งาน' ?></button>
    </form></td>
 </tr><?php endforeach; ?>
 <?php if(!$all): ?><tr><td colspan="5" class="text-center text-muted">ยังไม่มีข้อมูลหลักสูตรสถานศึกษา</td></tr><?php endif; ?>
 </tbody></table></div>
</div>
<?php elseif($tab==='sections'): ?>
<div class="kap-card mb-3">
 <h2 class="kap-admin-title">เลือกหลักสูตรสถานศึกษาเพื่อตรวจประเภทรายวิชา</h2>
 <form class="row g-2 align-items-end" method="get"><input type="hidden" name="tab" value="sections">
  <div class="col-12 col-md-7"><label class="form-label" for="filterCurriculum">หลักสูตรสถานศึกษา</label><select class="form-select" id="filterCurriculum" name="curriculum" required><option value="">เลือกหลักสูตรสถานศึกษา</option>
    <?php foreach($all as $c): $cid=(int)$c['Curriculum_id']; ?><option value="<?= $cid ?>" <?= $filterCurriculum===$cid?'selected':'' ?>><?= $v(View::curriculumLabel($c['Curriculum_name']).(isset($refs[$cid])?' ('.$refs[$cid]['start'].'–'.$refs[$cid]['end'].')':'')) ?></option><?php endforeach; ?>
  </select></div>
  <div class="col-12 col-md-3"><button class="btn btn-primary w-100" type="submit">แสดงประเภท</button></div>
 </form>
</div>
<?php if($filterCurriculum!==null && isset($refs[$filterCurriculum])): ?>
<div class="kap-card mb-3">
 <h2 class="kap-admin-title">ประเภทรายวิชาตามต้นฉบับ หลักสูตรสถานศึกษา <?= $v($refs[$filterCurriculum]['start'].'–'.$refs[$filterCurriculum]['end']) ?></h2>
 <div class="kap-admin-hint mb-2">เปรียบเทียบกับ Google Sheets ต้นฉบับ · การเปลี่ยนชื่อเก่าจะคงรหัสและ ID เดิม · รายการที่ไม่ตรงต้นฉบับและหมวดย่อยไม่ถูกลบหรือย้ายรายวิชาโดยอัตโนมัติ</div>
 <div class="table-responsive"><table class="table kap-table align-middle"><thead><tr><th>ชื่อประเภทจากต้นฉบับ</th><th>ข้อมูลใน MariaDB</th><th>สถานะการเทียบ</th></tr></thead><tbody>
 <?php foreach($comparison as $item): ?><tr><td><strong><?= $v($item['name']) ?></strong></td>
  <td><?= $item['found']===null?'<span class="text-muted">ยังไม่มีประเภทตรงกัน</span>':'#'.(int)$item['found']['CurriculumSection_id'].' · '.$v($item['found']['SectionName']) ?></td>
  <td><?php if($item['status']==='exact'): ?><span class="kap-tag ok">ชื่อตรงแล้ว</span><?php elseif($item['status']==='needs_name_review'): ?><span class="kap-tag wait">ควรปรับชื่อ</span><?php else: ?><span class="kap-tag wait">ยังไม่มี</span><?php endif; ?></td></tr><?php endforeach; ?>
 </tbody></table></div>
 <form method="post" action="<?= $v($typesApi) ?>" class="kap-admin-form mt-3" data-confirm="ยืนยันปรับประเภทรายวิชาตามต้นฉบับ? ระบบจะเพิ่มประเภทที่ขาดหรือปรับชื่อที่ใช้รหัสตรงกัน โดยเก็บ ID เดิม ไม่ย้ายรายวิชาและไม่ลบข้อมูลเดิม">
  <?= View::csrf() ?><input type="hidden" name="action" value="sync-source-types"><input type="hidden" name="curriculum_id" value="<?= $filterCurriculum ?>">
  <label class="kap-small-check mb-2"><input type="checkbox" name="review_confirm" value="yes" required> ตรวจเทียบประเภทกับ Google Sheets ต้นฉบับแล้ว</label>
  <button class="btn btn-outline-primary" type="submit"><i class="bi bi-arrow-repeat"></i> ปรับรายการประเภทตามต้นฉบับ</button>
 </form>
</div>
<?php endif; ?>
<div class="kap-card mb-3" id="section-form">
 <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="kap-admin-title mb-0"><?= $editSection?'แก้ไข'.($sc['ParentSection_id']===null?'ประเภทรายวิชา':'ข้อมูลประเภทย่อย').' #'.(int)$sc['CurriculumSection_id']:'เพิ่มประเภทรายวิชา' ?></h2>
  <?php if($editSection): ?><a class="btn btn-sm btn-outline-secondary" href="<?= $v($base.'?tab=sections&curriculum='.(int)$sc['Curriculum_id']) ?>">ยกเลิกการแก้ไข</a><?php endif; ?>
 </div>
 <?php if($editSection && (int)$sc['IsActive']===1): ?><div class="alert alert-warning">ประเภทที่เปิดใช้งานต้องปิดใช้งานก่อนแก้ไขรายละเอียด</div><?php endif; ?>
 <?php if($editSection && $sc['ParentSection_id']!==null): ?><div class="alert alert-info">รายการนี้เป็นประเภทย่อยเดิมของระบบ ไม่ใช่ประเภทหลักตาม Google Sheets การแก้ไขจะคงความสัมพันธ์กับหมวดแม่ไว้ ไม่เปลี่ยนเป็นประเภทหลัก</div><?php endif; ?>
 <form method="post" action="<?= $v($typesApi) ?>" class="kap-admin-form row g-3" data-confirm="<?= $editSection?'ยืนยันการแก้ไขชื่อประเภท? ถ้ามีรายวิชาเชื่อมอยู่ ชื่อที่แสดงของรายวิชาเหล่านั้นจะเปลี่ยนตาม':'ยืนยันเพิ่มประเภทรายวิชาเป็นฉบับร่าง?' ?>">
  <?= View::csrf() ?><input type="hidden" name="action" value="<?= $editSection?'update-section':'create-section' ?>">
  <?php if($editSection): ?><input type="hidden" name="section_id" value="<?= (int)$sc['CurriculumSection_id'] ?>"><?php endif; ?>
  <div class="col-12 col-lg-6"><label class="form-label" for="sectionCurriculum">หลักสูตรสถานศึกษา <span class="text-danger">*</span></label>
   <select class="form-select" name="curriculum_id" id="sectionCurriculum" required <?= $editSection?'disabled':'' ?>><option value="">เลือกหลักสูตรสถานศึกษา</option>
     <?php foreach($all as $c): $cid=(int)$c['Curriculum_id']; ?><option value="<?= $cid ?>" <?= (string)$sc['Curriculum_id']===(string)$cid?'selected':'' ?>><?= $v(View::curriculumLabel($c['Curriculum_name']).(isset($refs[$cid])?' ('.$refs[$cid]['start'].'–'.$refs[$cid]['end'].')':'')) ?></option><?php endforeach; ?>
   </select><?php if($editSection): ?><input type="hidden" name="curriculum_id" value="<?= (int)$sc['Curriculum_id'] ?>"><?php endif; ?>
  </div>
  <div class="col-12 col-lg-6"><label class="form-label" for="sectionName">ชื่อประเภท <span class="text-danger">*</span></label>
   <?php if(!$editSection || $sc['ParentSection_id']===null): ?>
    <select class="form-select" name="section_name" id="sectionName" required data-current-name="<?= $v($sc['SectionName']) ?>"><option value="">เลือกประเภทรายวิชาตามหลักสูตรสถานศึกษา</option>
      <?php foreach($refs as $cid=>$spec): ?><optgroup label="หลักสูตรสถานศึกษา <?= $v($spec['start'].'–'.$spec['end']) ?>" data-type-curriculum="<?= (int)$cid ?>">
        <?php foreach($spec['types'] as $typeName): ?><option data-type-curriculum="<?= (int)$cid ?>" value="<?= $v($typeName) ?>" <?= $typeName===$sc['SectionName'] && (int)$sc['Curriculum_id']===(int)$cid?'selected':'' ?>><?= $v($typeName) ?></option><?php endforeach; ?>
      </optgroup><?php endforeach; ?>
      <?php if($editSection && $sc['SectionName']!=='' && (!isset($refs[(int)$sc['Curriculum_id']]) || !in_array($sc['SectionName'],$refs[(int)$sc['Curriculum_id']]['types'],true))): ?>
       <option value="<?= $v($sc['SectionName']) ?>" selected data-legacy="1" data-type-curriculum="<?= (int)$sc['Curriculum_id'] ?>">ชื่อเดิม: <?= $v($sc['SectionName']) ?> (รอตรวจ)</option>
      <?php endif; ?>
    </select>
    <div class="kap-admin-hint">หากเป็นหลักสูตรสถานศึกษาที่ยังไม่มีต้นฉบับประเภทในระบบ กรุณาตรวจข้อมูลก่อนสร้างรายการใหม่</div>
   <?php else: ?><input type="text" class="form-control" id="sectionName" name="section_name" required maxlength="255" value="<?= $v($sc['SectionName']) ?>"><?php endif; ?>
  </div>
  <div class="col-12 col-lg-3"><label class="form-label" for="sectionSort">ลำดับแสดงผล</label><input class="form-control" type="number" id="sectionSort" name="sort_order" min="0" max="9999999" value="<?= (int)$sc['SortOrder'] ?>"></div>
  <div class="col-12 col-lg-9 d-flex align-items-end"><span class="kap-admin-hint">รหัสภายใน เช่น GUIDANCE และ BASIC จะคงไว้โดยระบบ ไม่ต้องกรอกหรือแก้ไข · ประเภทใหม่จะยังเป็นฉบับร่าง</span></div>
  <div class="col-12"><button type="submit" class="btn btn-primary" <?= $editSection && (int)$sc['IsActive']===1?'disabled':'' ?>><i class="bi bi-save"></i> <?= $editSection?'บันทึกการแก้ไขประเภท':'บันทึกร่างประเภทรายวิชา' ?></button></div>
 </form>
</div>
<div class="kap-card">
 <h2 class="kap-admin-title">ประเภทรายวิชาตามหลักสูตรสถานศึกษา <span class="kap-admin-hint">(<?= count($primaryRows) ?> รายการ)</span></h2>
 <div class="kap-admin-hint mb-2">แสดงเฉพาะประเภทหลักที่ตรงกับต้นฉบับของหลักสูตรสถานศึกษา รายการเดิมและหมวดย่อยอยู่ในส่วนตรวจสอบด้านล่าง</div>
 <div class="table-responsive"><table class="table kap-table align-middle"><thead><tr><th>หลักสูตรสถานศึกษา</th><th>ชื่อประเภท / รายการย่อย</th><th>รายวิชาที่เชื่อม</th><th>สถานะ / จัดการ</th></tr></thead><tbody>
 <?php foreach($primaryRows as $s):
   $sectionId=(int)$s['CurriculumSection_id'];$linkedItems=$displayLinks[$sectionId]??[];$gradeNames=[];
   foreach($linkedItems as $item)$gradeNames[(string)$item['GradeName']]=true;
   foreach(array_keys($displaySectionGrades[$sectionId]??[]) as $g)$gradeNames[(string)$g]=true;
   $expected=$refs[(int)$s['Curriculum_id']]['types']??[];
   $known=$s['ParentSection_id']!==null||in_array($s['SectionName'],$expected,true);
 ?>
 <tr>
  <td><?= $v(View::curriculumLabel($s['Curriculum_name'])) ?></td>
  <td><strong><?= $v($s['SectionName']) ?></strong><small class="d-block text-muted"><?= $s['ParentSection_id']!==null?'ประเภทย่อยเดิม · ภายใต้ '.$v($s['ParentName']??'ไม่พบหมวดแม่'):'ประเภทหลัก' ?> · #<?= $sectionId ?></small>
    <?php if(!$known): ?><small class="text-warning">ชื่อเดิมยังไม่ตรงประเภทจากต้นฉบับ</small><?php endif; ?>
    <a class="btn btn-sm btn-soft mt-1" href="<?= $v($base.'?tab=sections&edit_section='.$sectionId.'#section-form') ?>"><i class="bi bi-pencil"></i> แก้ไข</a></td>
  <td><?php if($linkedItems): ?><?php foreach($linkedItems as $item): ?><div class="small"><?= $v(trim((string)($item['Subject_code']??''))!==''?$item['Subject_code'].' · ':'') ?><?= $v($item['Subject_name']) ?></div><?php endforeach; ?>
      <?php else: ?><span class="text-muted">ยังไม่ได้เชื่อมรายวิชา</span><?php endif; ?>
      <?php if($gradeNames): ?><small class="d-block text-muted">ระดับชั้น: <?= $v(implode(', ',array_keys($gradeNames))) ?></small><?php endif; ?></td>
  <td><?= (int)$s['IsActive']===1?'<span class="kap-tag ok">เปิดใช้งาน</span>':'<span class="kap-tag wait">ร่าง / ปิดใช้งาน</span>' ?>
   <form method="post" action="<?= $v($typesApi) ?>" class="kap-admin-form mt-2" data-confirm="<?= (int)$s['IsActive']===1?'ยืนยันปิดใช้งานรายการนี้?':'ตรวจเอกสารแล้ว ยืนยันเปิดใช้งานประเภทนี้?' ?>">
     <?= View::csrf() ?><input type="hidden" name="action" value="section-status"><input type="hidden" name="section_id" value="<?= $sectionId ?>"><input type="hidden" name="is_active" value="<?= (int)$s['IsActive']===1?'0':'1' ?>">
     <?php if((int)$s['IsActive']===0): ?><label class="kap-small-check"><input type="checkbox" name="review_confirm" value="yes" required> ตรวจเอกสารแล้ว</label><?php endif; ?>
     <button class="btn btn-sm <?= (int)$s['IsActive']===1?'btn-outline-secondary':'btn-outline-success' ?>"><?= (int)$s['IsActive']===1?'ปิดใช้งาน':'เปิดใช้งาน' ?></button>
   </form></td>
 </tr>
 <?php endforeach; ?>
 <?php if(!$primaryRows): ?><tr><td colspan="4" class="text-center text-muted">ยังไม่มีประเภทที่ตรงกับต้นฉบับ เลือกหลักสูตรสถานศึกษาและตรวจรายการด้านบนก่อน</td></tr><?php endif; ?>
 </tbody></table></div>
</div>
<?php if($legacyRows): ?>
<details class="kap-card mt-3"><summary><strong>ตรวจสอบประเภทเดิมและรายการย่อย (<?= count($legacyRows) ?> รายการ)</strong> <span class="kap-admin-hint">ไม่ถูกลบหรือย้ายรายวิชาอัตโนมัติ</span></summary>
 <div class="table-responsive mt-3"><table class="table kap-table"><thead><tr><th>หลักสูตรสถานศึกษา</th><th>ชื่อเดิม / รายการย่อย</th><th>ความสัมพันธ์</th><th>จัดการ</th></tr></thead><tbody>
 <?php foreach($legacyRows as $s): $sid=(int)$s['CurriculumSection_id']; $links=$displayLinks[$sid]??[]; ?>
 <tr><td><?= $v(View::curriculumLabel($s['Curriculum_name'])) ?></td><td><strong><?= $v($s['SectionName']) ?></strong> <small class="d-block text-muted">#<?= $sid ?></small></td>
 <td><?= $s['ParentSection_id']===null?'ประเภทหลักชื่อเดิม':'รายการย่อยภายใต้ '.$v($s['ParentName']??'ไม่พบหมวดแม่') ?>
 <?php if($links): ?><small class="d-block text-muted">เชื่อมรายวิชา <?= count($links) ?> รายการ (จากข้อมูลแสดงผล)</small><?php endif; ?></td>
 <td><a class="btn btn-sm btn-soft" href="<?= $v($base.'?tab=sections&edit_section='.$sid.'#section-form') ?>">แก้ไข</a></td></tr>
 <?php endforeach; ?></tbody></table></div>
</details>
<?php endif; ?>
<?php else: ?>
<div class="kap-card mb-3"><h2 class="kap-admin-title">ตรวจการใช้หลักสูตรสถานศึกษาตามปีและระดับชั้น</h2>
 <div class="kap-admin-hint mb-3">อ่านจาก CurriculumGradeYear เท่านั้น ไม่แก้การผูกชั้น/รุ่นหลักสูตรสถานศึกษาหรือข้อมูลเดิมโดยอัตโนมัติ</div>
 <form class="row g-2 align-items-end" method="get"><input type="hidden" name="tab" value="cohorts">
  <div class="col-12 col-md-5"><label class="form-label" for="mappingYear">ปีการศึกษา</label><select class="form-select" name="year_id" id="mappingYear"><option value="">ทุกปีการศึกษา</option><?php foreach($years as $yr): ?><option value="<?= (int)$yr['AcademicYear_id'] ?>" <?= (string)$yr['AcademicYear_id']===(string)$yearId?'selected':'' ?>><?= $v($yr['AcademicYear_name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-12 col-md-3"><button class="btn btn-primary w-100">แสดงข้อมูล</button></div>
 </form>
</div>
<div class="kap-card">
 <?php if(!$mappings['ready']): ?><div class="alert alert-warning">ไม่พบ CurriculumGradeYear หรือคอลัมน์ที่ต้องใช้ กรุณาตรวจฐานข้อมูล</div>
 <?php else: ?><div class="table-responsive"><table class="table kap-table"><thead><tr><th>ปีการศึกษา</th><th>ระดับชั้น</th><th>หลักสูตรสถานศึกษา</th><th>ช่วงรุ่นหลักสูตรสถานศึกษา</th><th>การยืนยัน</th></tr></thead><tbody>
 <?php foreach($mappings['rows'] as $m): ?><tr><td><?= $v($m['AcademicYear_name']) ?></td><td><?= $v($m['GradeName']) ?> <small><?= $v($m['GradeCode']) ?></small></td><td><?= $v(View::curriculumLabel($m['Curriculum_name'])) ?> <?= (int)$m['CurriculumActive']===0?'<span class="kap-tag wait">ร่าง</span>':'' ?></td><td><?= isset($m['CohortStartYearBE'])?$v($m['CohortStartYearBE']).'–'.$v($m['CohortEndYearBE']??''):'ไม่ระบุ' ?></td><td><?= $v($m['VerificationStatus']??'ยังไม่ตรวจ') ?></td></tr><?php endforeach; ?>
 <?php if(!$mappings['rows']): ?><tr><td colspan="5" class="text-center text-muted">ยังไม่มีการจับคู่หลักสูตรสถานศึกษาตามเงื่อนไข</td></tr><?php endif; ?>
 </tbody></table></div><?php endif; ?>
</div>
<?php endif; ?>
<script src="<?= $v(View::path('/assets/js/kap-curriculum-types.js?v=20261103')) ?>" defer></script>
<?php View::footer();
}catch(\Throwable $e){\Kap\Teacher\Bootstrap::fail($e);}
