<?php
declare(strict_types=1);
namespace Kap\Teacher;

final class Ui
{
    public static function esc(mixed $v): string {return Bootstrap::escape($v);}
    public static function link(string $page, ?int $id=null): string
    {
        $routes = Bootstrap::config()['routes'] ?? [];
        if (!isset($routes[$page]) || !is_string($routes[$page])) {
            throw new \InvalidArgumentException('Unknown KAP route: '.$page);
        }
        return $routes[$page].($id !== null ? '?subject_class_id='.$id : '');
    }

    public static function page(string $title, array $actor, string $active, string $content): void
    {
        require_once dirname(__DIR__, 2) . '/ui/Theme.php';
        \Kap\Ui\Theme::start($title, $active, $actor, false);
        echo $content;
        \Kap\Ui\Theme::end();
    }

    public static function headline(string $title, string $sub): string
    {
        return '<div class="page-head"><h1>'.self::esc($title).'</h1><p>'.self::esc($sub).'</p></div>';
    }

    public static function courseName(array $r): string
    {
        return (string)$r['Subject_code'].' · '.(string)$r['Subject_name'];
    }

    public static function dashboard(array $subjects, array $years=[], ?int $selectedYear=null): string
    {
        $hours=array_sum(array_map(static fn($r)=>(float)($r['TotalInstructionalHours']??0),$subjects));
        $courseCount=count(array_unique(array_map(static fn($r)=>(int)$r['SubjectOffering_id'],$subjects)));
        $ready=count(array_filter($subjects,static fn($r)=>$r['Ready']));
        $totalDone=array_sum(array_map(static fn($r)=>(int)$r['Progress']['filled'],$subjects));
        $totalCells=array_sum(array_map(static fn($r)=>(int)$r['Progress']['total'],$subjects));
        $progress=$totalCells ? (int)floor($totalDone/$totalCells*100) : null;
        $yearName='';
        foreach ($years as $year){
            if((int)$year['AcademicYear_id']===$selectedYear){$yearName=(string)$year['AcademicYear_name'];break;}
        }
        // Place the academic year selector first, before all Dashboard metrics.
        $html='<section class="kap-year-toolbar mb-3" aria-label="เลือกปีการศึกษา">'
            .'<form action="'.self::esc(self::link('dashboard')).'" method="get" id="kapYearForm" class="row align-items-end g-3">'
            .'<div class="col-sm-auto"><label class="form-label fw-bold mb-1" for="kapAcademicYear"><i class="bi bi-calendar3 me-1"></i> ปีการศึกษา</label>'
            .'<select class="form-select" name="academic_year_id" id="kapAcademicYear"'.($years?'':' disabled').'>'.($years?'':'<option>ไม่มีข้อมูลปีการศึกษา</option>');
        foreach ($years as $year){
            $id=(int)$year['AcademicYear_id'];
            $html.='<option value="'.$id.'"'.($selectedYear===$id?' selected':'').'>'.self::esc($year['AcademicYear_name']).'</option>';
        }
        $html.='</select></div><div class="col-sm-auto"><button type="submit" class="btn btn-primary"'.($years?'':' disabled').'>แสดงข้อมูล</button></div>'
            .'<div class="col kap-year-note">ข้อมูลใน Dashboard จะแสดงเฉพาะปีการศึกษาที่เลือก</div></form></section>';
        $html.=self::headline('Dashboard ครูผู้สอน', 'ปีการศึกษา '.($yearName!==''?self::esc($yearName):'—').' · รายวิชาที่ได้รับมอบหมาย '.count($subjects).' กลุ่มเรียน และความคืบหน้าการกรอกคะแนน');
        $stats=[['journal-bookmark',$courseCount,'จำนวนรายวิชาที่สอน','blue'],['clock-history',number_format($hours,0).' ชม.','ชั่วโมงตามรายวิชาที่มอบหมาย','green'],['check-circle',$ready,'กลุ่มที่พร้อมกรอก K/A/P','purple'],['clipboard-data', $progress===null?'—':$progress.'%', 'ความคืบหน้าคะแนนทั้งหมด','yellow']];
        $html.='<div class="row g-3 mb-4">';
        foreach ($stats as [$icon,$value,$caption,$color]) $html.='<div class="col-md-6 col-xl-3"><div class="kap-card stat"><div class="stat-icon '.$color.'"><i class="bi bi-'.$icon.'"></i></div><div><div class="stat-number">'.self::esc($value).'</div><small>'.self::esc($caption).'</small></div></div></div>';
        $html.='</div><div class="kap-card mb-4"><div class="section-title"><div><h2>ความคืบหน้ารายวิชา</h2><p>นับช่องคะแนนที่มีค่าจริงทั้งหมดในแผนปัจจุบัน รวมคะแนน 0 แต่ไม่นับ NULL</p></div></div><div class="row g-3">';
        if (!$subjects) $html.='<div class="col-12"><div class="kap-empty">'.($selectedYear===null?'ยังไม่มีข้อมูลปีการศึกษาในระบบ':'ยังไม่มีรายวิชาที่ได้รับมอบหมายในปีการศึกษา '.self::esc($yearName)).'</div></div>';
        foreach ($subjects as $s) {
            $pct=$s['Progress']['percent'];
            $html.='<div class="col-md-6 col-xl-4"><div class="progress-course"><div class="d-flex justify-content-between gap-2"><strong>'.self::esc(self::courseName($s)).'</strong><span>'.($pct===null?'—':$pct.'%').'</span></div><div class="kap-meta mb-2">'.self::esc($s['GradeName']).' · '.self::esc($s['ClassGroupName']?:'กลุ่ม #'.$s['SubjectClass_id']).'</div><div class="progress"><div class="progress-bar" style="width:'.($pct ?? 0).'%"></div></div><small>'.(int)$s['Progress']['filled'].' / '.(int)$s['Progress']['total'].' ช่องคะแนน</small></div></div>';
        }
        $html.='</div></div><div class="kap-card"><div class="section-title"><div><h2>รายวิชาที่สอนทั้งหมด</h2><p>เลือกไอคอนตั้งค่าเพื่อดูหน่วยเรียนรู้ หรือเปิดหน้ากรอกคะแนน</p></div></div><div class="table-responsive"><table class="table kap-table align-middle"><thead><tr><th>ลำดับ</th><th>รหัสวิชา</th><th class="text-start">ชื่อวิชา</th><th>ระดับชั้น</th><th>ปี / ภาคเรียน</th><th>กลุ่มเรียน</th><th>ชั่วโมง</th><th>คะแนน</th><th>สถานะ</th><th>เมนูย่อย</th></tr></thead><tbody>';
        foreach ($subjects as $i=>$s) {
            $id=(int)$s['SubjectClass_id']; $ready=$s['Ready'];
            $html.='<tr><td>'.($i+1).'</td><td>'.self::esc($s['Subject_code']).'</td><td class="text-start fw-semibold">'.self::esc($s['Subject_name']).'</td><td>'.self::esc($s['GradeName']).'</td><td>'.self::esc(($s['AcademicYear_name']??'').'/'.($s['Semester']??'รายปี')).'</td><td>'.self::esc($s['ClassGroupName']?:'#'.$id).'</td><td>'.self::esc($s['TotalInstructionalHours']).'</td><td>'.($s['Progress']['percent']===null?'—':$s['Progress']['percent'].'%').'</td><td><span class="kap-tag '.($ready?'ok':'wait').'">'.($ready?'พร้อมกรอก':'รอตั้งค่า').'</span></td><td class="text-nowrap"><a title="ดูหน่วยและคะแนน" class="btn btn-sm btn-soft" href="'.self::link('plan',$id).'"><i class="bi bi-gear"></i></a> <a title="กรอกคะแนน KAP" class="btn btn-sm '.($ready?'btn-primary':'btn-outline-secondary').'" href="'.self::link('score-kap',$id).'"><i class="bi bi-pencil-square"></i></a></td></tr>';
        }
        if (!$subjects) $html.='<tr><td colspan="10">ไม่พบข้อมูล</td></tr>';
        return $html.'</tbody></table></div><p class="footnote">ชั่วโมงรวมคำนวณจาก TotalInstructionalHours ของกลุ่มที่ได้รับมอบหมาย ไม่ใช่ชั่วโมงสอนที่เกิดขึ้นจริง และอาจนับหลายกลุ่มของวิชาเดียวกัน</p></div>';
    }

    public static function selectCourse(array $all, ?int $selected, bool $adminMode=false): string
    {
        $h='<form action="'.self::link('score-kap').'" method="GET" class="kap-card filter-card" id="kapScoreCourseForm">';
        if($adminMode){
            $years=[];$grades=[];
            foreach($all as $entry){
                $years[(string)($entry['AcademicYear_name']??'—')]=true;
                $grades[(string)($entry['GradeName']??'—')]=true;
            }
            $h.='<div class="row g-3 mb-2"><div class="col-md-6"><label class="form-label fw-bold" for="kapScoreYearFilter">ปีการศึกษา</label><select class="form-select" id="kapScoreYearFilter"><option value="">ทุกปีการศึกษา</option>';
            foreach(array_keys($years) as $y)$h.='<option value="'.self::esc($y).'">'.self::esc($y).'</option>';
            $h.='</select></div><div class="col-md-6"><label class="form-label fw-bold" for="kapScoreGradeFilter">ระดับชั้น</label><select class="form-select" id="kapScoreGradeFilter"><option value="">ทุกระดับชั้น</option>';
            foreach(array_keys($grades) as $g)$h.='<option value="'.self::esc($g).'">'.self::esc($g).'</option>';
            $h.='</select></div></div>';
        }
        $h.='<div class="row g-3 align-items-end"><div class="col-lg-9"><label class="form-label fw-bold" for="kapSubject">เลือกรายวิชา / กลุ่มเรียน</label><select class="form-select" id="kapSubject" name="subject_class_id" required><option value="">เลือก...</option>';
        foreach($all as $entry){
            $id=(int)$entry['SubjectClass_id'];
            $year=(string)($entry['AcademicYear_name']??'—');$grade=(string)($entry['GradeName']??'—');
            $h.='<option data-year="'.self::esc($year).'" data-grade="'.self::esc($grade).'" '.($selected===$id?'selected':'').' value="'.$id.'">'.self::esc(self::courseName($entry).' · '.$grade.' · '.($entry['ClassGroupName']?:'#'.$id).' ('.$year.'/'.($entry['Semester']??'รายปี').')').'</option>';
        }
        return $h.'</select></div><div class="col-lg-3"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> เรียกข้อมูล</button></div></div></form>';
    }

    public static function scorePage(array $all, ?array $sub, ?array $plan, array $items=[],array $students=[],array $saved=[],?string $warning=null, bool $adminMode=false, bool $canWrite=false, ?string $readOnlyReason=null): string
    {
        $html=self::headline('กรอกคะแนน K/A/P','ตารางคะแนนพุทธิพิสัย (K) จิตพิสัย (A) และทักษะพิสัย (P) ตามแผนคะแนนที่เลือก');
        $html.=self::selectCourse($all,$sub?(int)$sub['SubjectClass_id']:null,$adminMode);
        if($adminMode)$html.='<div class="alert alert-info mt-3">โหมดช่วยกรอกคะแนนของผู้ดูแลระบบ: เลือกได้ทุกกลุ่มเรียนที่เปิดสอนจริง ไม่ต้องได้รับมอบหมายเป็นครู แต่การบันทึกต้องมีเหตุผลและประวัติแก้ไข</div>';
        if (!$sub) return $html.'<div class="kap-empty mt-3">'.($adminMode?'เลือกกลุ่มเรียนที่เปิดสอนในระบบ เพื่อกรอกหรือแก้ไขคะแนนแทนครู':'เลือกรายวิชาที่ได้รับมอบหมายเพื่อเริ่มกรอกคะแนน').'</div>';
        if ($warning) return $html.'<div class="alert alert-warning mt-3"><i class="bi bi-exclamation-triangle"></i> '.self::esc($warning).($adminMode?' · กรุณาตรวจการเปิดกลุ่มเรียน ทะเบียนนักเรียน และแผนคะแนน ACTIVE':' <a href="'.self::link('plan',(int)$sub['SubjectClass_id']).'">ดูการตั้งค่า</a>').'</div>';
        $groups=['UNIT_K'=>['K','k'], 'UNIT_A'=>['A','a'], 'UNIT_P'=>['P','p']];
        $by=[];
        foreach ($items as $it) $by[$it['AssessmentTypeCode']][]=$it;
        $html.='<div class="alert alert-info mt-3">รายวิชา <strong>'.self::esc(self::courseName($sub)).'</strong> · '.self::esc($sub['GradeName']).' · แผนคะแนนเวอร์ชัน '.self::esc($plan['VersionNumber']).' · <strong>นักเรียน '.count($students).' คน</strong><br><small>ช่องว่างหมายถึงยังไม่กรอก (NULL) ส่วน 0 เป็นคะแนนศูนย์จริง ระบบจะบันทึกเฉพาะช่องที่เปลี่ยนแปลง</small>';
        if (Bootstrap::mode()==='demo') $html.='<br><small><i class="bi bi-people"></i> รายชื่อนักเรียนอ้างอิงจากรายการที่บันทึกไว้ในหน้า <a href="'.self::esc(self::link('plan',(int)$sub['SubjectClass_id'])).'">ตั้งค่ารายวิชาและเลือกนักเรียน</a> ของ Session นี้ · ไม่เปลี่ยนทะเบียนจริง</small>';
        $html.='</div>';
        if($readOnlyReason!==null)$html.='<div class="alert alert-warning mt-3">'.self::esc($readOnlyReason).'</div>';
        $html.='<div class="kap-card"><div class="section-title"><div><h2>ตารางกรอกคะแนน K / A / P</h2><p>รองรับคัดลอกจากตารางและวางแบบหลายช่อง; หัวคอลัมน์สร้างจากรายการ AssessmentItem ของแผน</p></div><span id="kapDirtyStatus" class="kap-tag">ไม่มีการเปลี่ยนแปลง</span></div>';
        $html.='<div class="score-scroll"><table id="kapScoreTable" class="table kap-table score-table" data-subject="'.(int)$sub['SubjectClass_id'].'" data-plan="'.(int)$plan['ScorePlan_id'].'" data-csrf="'.self::esc(Bootstrap::csrf()).'" data-save-url="'.self::esc(Bootstrap::config()['score_save_url']).'"><thead><tr><th rowspan="3" class="freeze-id">เลขประจำตัว</th><th rowspan="3" class="freeze-name">ชื่อ-นามสกุล</th><th rowspan="3" class="freeze-room">ห้อง</th>';
        foreach ($groups as $code=>[$label,$suffix]) {
            $num=count($by[$code]??[]);
            if ($num) $html.='<th colspan="'.($num+1).'" class="group-'.$suffix.'">คะแนนรวมพิสัย '.$label.'</th>';
        }
        $html.='</tr><tr>';
        foreach ($groups as $code=>[$label,$suffix]) {
            foreach ($by[$code]??[] as $it) $html.='<th class="group-'.$suffix.'" title="'.self::esc($it['ItemName']).'">'.self::esc((string)($it['UnitName'] ?: 'ไม่ระบุหน่วย')).'</th>';
            if (isset($by[$code])) $html.='<th class="sum-col">รวม '.$label.'</th>';
        }
        $html.='</tr><tr>';
        foreach ($groups as $code=>[$label,$suffix]) {
            $max=0;
            foreach ($by[$code]??[] as $it) {$max+=(float)$it['MaxScore'];$html.='<th class="group-'.$suffix.'">'.self::esc($it['MaxScore']).'</th>';}
            if (isset($by[$code])) $html.='<th class="sum-col">'.self::esc(number_format($max,2,'.','')).'</th>';
        }
        $html.='</tr></thead><tbody>';
        foreach ($students as $st) {
            $html.='<tr data-student="'.(int)$st['SubjectEnrollment_id'].'"><td class="freeze-id">'.self::esc($st['StudentCode']).'</td><td class="freeze-name text-start">'.self::esc($st['StudentName']).'</td><td class="freeze-room">'.self::esc($st['RoomName']??'—').'</td>';
            foreach ($groups as $code=>[$label,$suffix]) {
                foreach ($by[$code]??[] as $it) {
                    $key=$st['SubjectEnrollment_id'].':'.$it['AssessmentItem_id'];
                    $value=$saved[$key]??'';
                    $html.='<td><input type="text" inputmode="decimal" autocomplete="off" class="score-input" data-score data-group="'.$suffix.'" data-item="'.(int)$it['AssessmentItem_id'].'" data-enrollment="'.(int)$st['SubjectEnrollment_id'].'" data-max="'.self::esc($it['MaxScore']).'" data-original="'.self::esc($value).'" value="'.self::esc($value).'" aria-label="'.self::esc($st['StudentName'].' '.$it['ItemName']).'"></td>';
                }
                if (isset($by[$code])) $html.='<td class="sum-col" data-total="'.$suffix.'">—</td>';
            }
            $html.='</tr>';
        }
        if (!$students) $html.='<tr><td colspan="'.(3+count($items)+count($by)).'">ไม่มีนักเรียนลงทะเบียน</td></tr>';
        $html.='</tbody></table></div>';
        if($adminMode && $canWrite)$html.='<div class="mt-3"><label for="kapScoreReason" class="form-label fw-bold">เหตุผลการกรอก/แก้ไขคะแนนโดยผู้ดูแลระบบ <span class="text-danger">*</span></label><textarea class="form-control" id="kapScoreReason" rows="2" minlength="5" maxlength="500" placeholder="เช่น ช่วยกรอกคะแนนตามเอกสารที่ครูส่ง หรือแก้คะแนนตามคำร้องที่ได้รับอนุมัติ" required></textarea><small class="text-muted">5–500 ตัวอักษร บันทึกเหตุผลและผู้ดำเนินการลง AuditLog ทุกครั้ง</small></div>';
        $html.='<div class="action-bar"><div id="kapFeedback" role="status" aria-live="polite" class="small"></div><div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-primary" id="kapSave"'.((Bootstrap::mode()==='database' && !$canWrite)?' disabled data-readonly="1" title="บัญชีนี้หรือกลุ่มเรียนยังไม่อนุญาตบันทึกคะแนน"':'').'><i class="bi bi-floppy"></i> บันทึกข้อมูล</button><button type="button" class="btn btn-outline-primary" id="kapReload"><i class="bi bi-arrow-clockwise"></i> เรียกข้อมูลใหม่</button><button type="button" class="btn btn-outline-danger" id="kapClear"'.((Bootstrap::mode()==='database' && !$canWrite)?' disabled':'').'><i class="bi bi-eraser"></i> ล้างข้อมูลในหน้าจอ</button></div></div><p class="footnote mt-3">การล้างข้อมูลเป็นการล้างเฉพาะหน้าจอ และจะเปลี่ยนข้อมูลในฐานเมื่อกดบันทึกและยืนยันอีกครั้ง</p></div>';
        return $html;
    }

    public static function planPage(array $sub, ?array $plan,array $alloc,array $items): string
    {
        $html=self::headline('ตั้งค่าหน่วยและคะแนน', self::courseName($sub).' · '.$sub['GradeName']);
        $html.='<div class="alert alert-info">หน้านี้แสดงข้อมูลแผนคะแนนแบบอ่านอย่างเดียว เพื่อไม่แก้แผนย้อนหลังโดยไม่ได้ตั้งใจ การแก้ไข/เปิดใช้แผนคะแนนจะเป็นโมดูลถัดไป</div><div class="kap-card"><div class="section-title"><h2>สถานะกลุ่มเรียนและแผนคะแนน</h2></div><p><strong>SetupStatus:</strong> '.self::esc($sub['SetupStatus']).' · <strong>แผนปัจจุบัน:</strong> '.($plan?'#'.(int)$plan['ScorePlan_id'].' (Version '.self::esc($plan['VersionNumber']).')':'ยังไม่มีแผน ACTIVE').'</p>';
        if ($plan) {
            $html.='<h3 class="h6 mt-4">สัดส่วนคะแนน</h3><div class="table-responsive"><table class="table kap-table"><thead><tr><th>หมวดคะแนน</th><th>คะแนนเต็ม</th></tr></thead><tbody>';
            foreach($alloc as $a) $html.='<tr><td>'.self::esc($a['AssessmentTypeCode'].' · '.$a['AssessmentTypeName']).'</td><td>'.self::esc($a['AllocatedMaxScore']).'</td></tr>';
            $html.='</tbody></table></div><h3 class="h6 mt-4">รายการคะแนน / หน่วยการเรียนรู้</h3><div class="table-responsive"><table class="table kap-table"><thead><tr><th>หมวด</th><th>หน่วยการเรียนรู้</th><th>ชื่อรายการ</th><th>เต็ม</th></tr></thead><tbody>';
            foreach($items as $it) $html.='<tr><td>'.self::esc($it['AssessmentTypeCode']).'</td><td>'.self::esc($it['UnitName']??'—').'</td><td>'.self::esc($it['ItemName']).'</td><td>'.self::esc($it['MaxScore']).'</td></tr>';
            $html.='</tbody></table></div>';
        }
        return $html.'</div><div class="mt-3"><a class="btn btn-outline-primary" href="'.self::link('dashboard').'">กลับ Dashboard</a> <a class="btn btn-primary" href="'.self::link('score-kap',(int)$sub['SubjectClass_id']).'">ไปหน้ากรอกคะแนน</a></div>';
    }
}
