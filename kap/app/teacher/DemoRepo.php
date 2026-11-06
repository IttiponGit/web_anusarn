<?php
declare(strict_types=1);
namespace Kap\Teacher;
final class DemoRepo {
    private function baseSubjects():array {return [
        ['SubjectClass_id'=>101,'ClassGroupName'=>'ห้อง 1-2','SetupStatus'=>'ACTIVE','ClassIsActive'=>1,'SubjectOffering_id'=>1001,'AcademicYear_id'=>1,'TotalInstructionalHours'=>60,'Credit'=>'1.5','Subject_code'=>'ค22101','Subject_name'=>'คณิตศาสตร์ 3 (ตัวอย่าง)','GradeName'=>'มัธยมศึกษาปีที่ 2','AcademicYear_name'=>'2569','Semester'=>'1','StudentCount'=>5,'CurrentPlanCount'=>1,'ScorePlan_id'=>100],
        ['SubjectClass_id'=>102,'ClassGroupName'=>'ห้อง 1','SetupStatus'=>'DRAFT','ClassIsActive'=>1,'SubjectOffering_id'=>1002,'AcademicYear_id'=>1,'TotalInstructionalHours'=>40,'Credit'=>'1.0','Subject_code'=>'ว22101','Subject_name'=>'วิทยาศาสตร์ 3 (ตัวอย่าง)','GradeName'=>'มัธยมศึกษาปีที่ 2','AcademicYear_name'=>'2569','Semester'=>'1','StudentCount'=>0,'CurrentPlanCount'=>0,'ScorePlan_id'=>null]
    ];}
    public function academicYears(): array {
        return [
            ['AcademicYear_id'=>2, 'AcademicYear_name'=>'2570', 'HasAssignedSubjects'=>0],
            ['AcademicYear_id'=>1, 'AcademicYear_name'=>'2569', 'HasAssignedSubjects'=>1],
        ];
    }
    public function subjects(?int $academicYearId=null):array {
        $rows=$this->baseSubjects();
        if($academicYearId!==null)$rows=array_values(array_filter($rows,static fn($s)=>(int)$s['AcademicYear_id']===$academicYearId));
        foreach ($rows as &$r) {
            $classId=(int)$r['SubjectClass_id'];
            $yearId=(int)$r['AcademicYear_id'];
            $members=$this->enrollments($classId,$yearId); // Same selected roster as the score grid.
            $r['StudentCount']=count($members);
            if ($members) {
                $rooms=array_values(array_unique(array_map(static fn(array $m)=>(string)$m['RoomName'],$members)));
                sort($rooms,SORT_NATURAL);
                $r['ClassGroupName']='ห้อง '.implode(', ',$rooms);
            }
            $ready=$r['SetupStatus']==='ACTIVE';
            $r['Ready']=$ready;
            $plan=$ready ? $this->currentPlan($classId) : null;
            $items=$plan ? $this->scoreItems($classId,(int)$plan['ScorePlan_id']) : [];
            $total=count($items)*count($members);
            $filled=0;
            if ($total>0) {
                $allowedEnrollments=array_fill_keys(array_map(static fn(array $m)=>(int)$m['SubjectEnrollment_id'],$members),true);
                $allowedItems=array_fill_keys(array_map(static fn(array $item)=>(int)$item['AssessmentItem_id'],$items),true);
                foreach ($this->scores($classId,(int)$plan['ScorePlan_id']) as $key=>$value) {
                    if ($value===null) continue; // Score 0 is a completed cell, NULL is not.
                    $parts=explode(':',(string)$key,2);
                    if (count($parts)!==2) continue;
                    if (isset($allowedEnrollments[(int)$parts[0]],$allowedItems[(int)$parts[1]])) ++$filled;
                }
            }
            $r['Progress']=['filled'=>$filled,'total'=>$total,'percent'=>$total?(int)floor($filled/$total*100):null];
        }
        unset($r);return $rows;
    }
    public function subject(int $id):array {foreach($this->baseSubjects() as $s)if((int)$s['SubjectClass_id']===$id)return $s;throw new WebError(403,'ไม่พบกลุ่มเรียน');}
    public function currentPlan(int $id):?array{return $id===101?['ScorePlan_id'=>100,'SubjectClass_id'=>101,'PlanStatus'=>'ACTIVE','IsCurrent'=>1,'TargetTotalScore'=>'100.00','VersionNumber'=>1,'CreatedAt'=>'2026-10-30']:null;}
    public function allocations(int $id):array {return $id===100?[['AssessmentTypeCode'=>'UNIT_K','AssessmentTypeName'=>'ความรู้','AllocatedMaxScore'=>'30.00'],['AssessmentTypeCode'=>'UNIT_A','AssessmentTypeName'=>'เจตคติ','AllocatedMaxScore'=>'10.00'],['AssessmentTypeCode'=>'UNIT_P','AssessmentTypeName'=>'การปฏิบัติ','AllocatedMaxScore'=>'20.00'],['AssessmentTypeCode'=>'MIDTERM','AssessmentTypeName'=>'กลางภาค','AllocatedMaxScore'=>'20.00'],['AssessmentTypeCode'=>'FINAL_EXAM','AssessmentTypeName'=>'ปลายภาค','AllocatedMaxScore'=>'20.00']]:[];}
    public function scoreItems(int $id,int $plan,bool $all=false):array {
        if($id!==101||$plan!==100)return [];
        $items=[];$number=500;
        foreach([['UNIT_K','K',[5,5,10,5,5]],['UNIT_A','A',[1,2,3,2,2]],['UNIT_P','P',[2,2,8,4,4]]] as $block){foreach($block[2] as $i=>$max)$items[]=['AssessmentItem_id'=>++$number,'AssessmentTypeCode'=>$block[0],'AssessmentTypeName'=>$block[1],'ItemName'=>'คะแนน'.$block[1].' หน่วย '.($i+1),'MaxScore'=>number_format($max,2,'.',''),'UnitName'=>'หน่วยที่ '.($i+1),'UnitOrder'=>$i+1];}
        if($all){$items[]=['AssessmentItem_id'=>900,'AssessmentTypeCode'=>'MIDTERM','ItemName'=>'กลางภาค','MaxScore'=>'20.00','UnitName'=>null];$items[]=['AssessmentItem_id'=>901,'AssessmentTypeCode'=>'FINAL_EXAM','ItemName'=>'ปลายภาค','MaxScore'=>'20.00','UnitName'=>null];}
        return $items;
    }
    /** The score grid reads the SAVED roster draft for this group and session.
     *  Enrollment IDs are stable demo-only IDs: Student_id + 200. Never remove
     *  previously stored demo scores when a student is deselected.
     */
    public function enrollments(int $id,int $year):array {
        if ($id!==101 || $year!==1) return [];
        $roster=$this->studentRosterData($id);
        $selected=array_fill_keys(array_map('intval',$roster['selected_ids']),true);
        $out=[];
        foreach ($roster['students'] as $student) {
            $studentId=(int)$student['Student_id'];
            if (!isset($selected[$studentId])) continue;
            $out[]=[
                'SubjectEnrollment_id'=>200+$studentId,
                'Student_id'=>$studentId,
                'StudentCode'=>(string)$student['StudentCode'],
                'StudentName'=>(string)$student['StudentName'],
                'RoomName'=>(string)$student['RoomName'],
                'EnrollmentStatus'=>'ACTIVE',
            ];
        }
        return $out;
    }
    public function scores(int $id,int $plan):array {
        if($id!==101||$plan!==100)return [];
        if(!isset($_SESSION['kap_demo_scores'])){
            $m=[];foreach(range(201,205) as $enr)foreach($this->scoreItems(101,100) as $i=>$it){if($i<5)$m[$enr.':'.$it['AssessmentItem_id']]=(string)min((int)$it['MaxScore'],($i+$enr)%5);}
            $_SESSION['kap_demo_scores']=$m;
        }
        return $_SESSION['kap_demo_scores'];
    }
    /** An editable DRAFT separate from the published sample plan; never rewrites ScorePlan_id=100. */
    public function planEditorData(int $classId): array
    {
        $this->subject($classId); // class scope check
        $draft = $_SESSION['kap_demo_plan_drafts'][$classId] ?? null;
        if (is_array($draft)) return $draft;
        $current = $this->currentPlan($classId);
        $units = [];
        if ($current) {
            foreach ($this->scoreItems($classId, (int)$current['ScorePlan_id']) as $item) {
                $num = (int)$item['UnitOrder'];
                if (!isset($units[$num])) $units[$num] = ['name'=>(string)$item['UnitName'], 'learning_content'=>'', 'hours'=>'', 'k'=>'0.00', 'a'=>'0.00', 'p'=>'0.00'];
                $type = ['UNIT_K'=>'k','UNIT_A'=>'a','UNIT_P'=>'p'][(string)$item['AssessmentTypeCode']] ?? null;
                if ($type !== null) $units[$num][$type] = number_format((float)$units[$num][$type]+(float)$item['MaxScore'],2,'.','');
            }
            ksort($units);
        }
        if (!$units) $units = [['name'=>'หน่วยที่ 1','learning_content'=>'','hours'=>'','k'=>'0.00','a'=>'0.00','p'=>'0.00']];
        return [
            'subject_class_id'=>$classId,
            'score_plan_id'=>$current ? (int)$current['ScorePlan_id'] : null,
            'base_version'=>$current ? (int)$current['VersionNumber'] : 0,
            'draft_version'=>$current ? (int)$current['VersionNumber']+1 : 1,
            'draft_revision'=>0,
            'status'=>'DRAFT',
            'target'=>$current?(string)$current['TargetTotalScore']:'100.00',
            'midterm'=>'20.00', 'final_exam'=>'20.00',
            'units'=>array_values($units),
            'saved_at'=>null,
        ];
    }

    /** Demo Session only, never mutate SubjectScorePlan / LearningUnit in MariaDB. */
    public function savePlanDraft(int $classId, array $payload): array
    {
        $current = $this->planEditorData($classId);
        if ((int)($payload['revision'] ?? -1) !== (int)$current['draft_revision']) {
            throw new WebError(409,'มีการบันทึกร่างใหม่แล้ว กรุณาเรียกข้อมูลใหม่');
        }
        $validated = PlanRules::validate($payload);
        $current['target']=$validated['target'];
        $current['midterm']=$validated['midterm'];
        $current['final_exam']=$validated['final_exam'];
        $current['units']=$validated['units'];
        $current['draft_revision']++;
        $current['saved_at']=date('Y-m-d H:i:s');
        $_SESSION['kap_demo_plan_drafts'][$classId]=$current;
        return $current;
    }
    /** Synthetic school-year roster for interface testing. Never use real student data in public demo. */
    private function demoRosterSource(int $classId): array
    {
        $this->subject($classId);
        $rooms=[
            ['ClassRoom_id'=>301,'RoomName'=>'1'],
            ['ClassRoom_id'=>302,'RoomName'=>'2'],
            ['ClassRoom_id'=>303,'RoomName'=>'3'],
        ];
        $students=[];
        for($i=1;$i<=12;$i++){
            $room=$i<=3?301:($i<=8?302:303);
            $students[]=[
                'Student_id'=>$i,
                'StudentCode'=>'TEST-'.str_pad((string)$i,3,'0',STR_PAD_LEFT),
                'StudentName'=>'นักเรียนตัวอย่าง '.str_pad((string)$i,2,'0',STR_PAD_LEFT),
                'ClassRoom_id'=>$room,
                'RoomName'=>(string)($room===301?'1':($room===302?'2':'3')),
                'ClassStatus'=>'CONFIRMED',
            ];
        }
        return ['rooms'=>$rooms,'students'=>$students];
    }

    public function studentRosterData(int $classId): array
    {
        $source=$this->demoRosterSource($classId);
        $current=$classId===101?[1,2,3,4,5]:[];
        $draft=$_SESSION['kap_demo_roster_drafts'][$classId]??null;
        if (!is_array($draft)){
            $draft=['mode'=>'INDIVIDUAL','room_ids'=>[],
                    'selected_ids'=>$current,'draft_revision'=>0,'saved_at'=>null];
        }
        return $source+[
            'current_ids'=>$current,
            'protected_current_count'=>0,
            'mode'=>$draft['mode'],
            'room_ids'=>$draft['room_ids'],
            'selected_ids'=>$draft['selected_ids'],
            'draft_revision'=>$draft['draft_revision'],
            'saved_at'=>$draft['saved_at'],
        ];
    }

    /** Session-only roster proposal. Does NOT modify StudentEnrollment or scores. */
    public function saveRosterDraft(int $classId, array $payload): array
    {
        $current=$this->studentRosterData($classId);
        if(!is_int($payload['revision']??null) || $payload['revision']!==$current['draft_revision']){
            throw new WebError(409,'รายการที่เลือกเปลี่ยนไปแล้ว กรุณาโหลดหน้าใหม่');
        }
        $data=RosterRules::validate($payload,$current);
        $draft=$data+[
            'draft_revision'=>$current['draft_revision']+1,
            'saved_at'=>date('Y-m-d H:i:s'),
        ];
        $_SESSION['kap_demo_roster_drafts'][$classId]=$draft;
        return $draft;
    }

    public function requireReady(int $id,array $subject,array $plan):void {if($id!==101||$subject['SetupStatus']!=='ACTIVE'||$plan['ScorePlan_id']!==100)throw new WebError(409,'แผนคะแนนยังไม่พร้อม');}
    public function save(int $id,int $planId,array $changes):int {
        $subject=$this->subject($id);$plan=$this->currentPlan($id);if(!$plan||$planId!==(int)$plan['ScorePlan_id'])throw new WebError(409,'แผนไม่ตรง');$this->requireReady($id,$subject,$plan);
        if(!$changes||count($changes)>1500)throw new WebError(422,'จำนวนช่องไม่ถูกต้อง');
        $items=[];foreach($this->scoreItems(101,100) as $it)$items[(int)$it['AssessmentItem_id']]=$it['MaxScore'];
        $enrollments=[];foreach($this->enrollments(101,1) as $r)$enrollments[(int)$r['SubjectEnrollment_id']]=true;
        $new=$this->scores(101,100);$edits=[];
        foreach($changes as $c){
            if(!is_array($c)||!array_key_exists('score',$c))throw new WebError(422,'กรุณาระบุคะแนน');
            $item=Bootstrap::id($c['item_id']??null);$enr=Bootstrap::id($c['enrollment_id']??null);$key=$enr.':'.$item;
            if(!isset($items[$item])||!isset($enrollments[$enr]))throw new WebError(403,'รายการไม่อยู่ในกลุ่ม');
            if(array_key_exists($key,$edits))throw new WebError(422,'รายการคะแนนซ้ำ');
            $edits[$key]=ScoreRules::canonical($c['score'],$items[$item]);
        }
        foreach($edits as $key=>$score)$new[$key]=$score;
        $_SESSION['kap_demo_scores']=$new;return count($edits);
    }
}
