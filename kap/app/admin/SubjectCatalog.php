<?php
declare(strict_types=1);
namespace Kap\Admin;

use Kap\Teacher\WebError;
use PDO;

/** Subject-only write service. Does not create offerings, grades, curricula or student registrations. */
final class SubjectCatalog
{
    private string $stage='subject-init';
    private ?bool $gradeMappingAvailable=null;
    public function __construct(private PDO $db, private array $actor) {}
    public function currentStage(): string {return $this->stage;}
    private function stage(string $stage): void {$this->stage=$stage;}

    public function precheck(): array
    {
        $required=['Subject'=>['Subject_id','Subject_code','Subject_name','Learning_Area_id','TypeofCourse_id','SubjectKind','IsActive'],
            'LearningArea'=>['LearningArea_id','LearningArea_name','IsActive'],
            'TypeofCourse'=>['TypeofCourse_id','TypeofCourse_name','IsActive'],
            'AuditLog'=>['EntityName','EntityId','ActionType','OldValuesJson','NewValuesJson','ActorUser_id']];
        $names=array_keys($required);
        $marks=implode(',',array_fill(0,count($names),'?'));
        $stmt=$this->db->prepare("SELECT TABLE_NAME,COLUMN_NAME,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($marks)");
        $stmt->execute($names);
        $seen=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $seen[$r['TABLE_NAME']][$r['COLUMN_NAME']]=$r['IS_NULLABLE'];
        $missing=[];
        foreach($required as $table=>$cols) foreach($cols as $col)
            if(!array_key_exists($col,$seen[$table]??[]))$missing[]="$table.$col";
        if($missing) throw new WebError(409,'โครงสร้างฐานข้อมูลยังไม่รองรับทะเบียนรายวิชา: '.implode(', ',$missing).' กรุณาตรวจ Phase 2.0 ก่อน');
        if(($seen['Subject']['Subject_code']??'NO')!=='YES' || ($seen['Subject']['Learning_Area_id']??'NO')!=='YES')
            throw new WebError(409,'ฐานข้อมูลยังไม่อนุญาตรหัสวิชา/กลุ่มสาระว่างตาม Phase 2.0 โปรดตรวจ Subject ก่อน');
        return ['columns'=>$seen];
    }
    /** Optional in read-only mode, required before saving grade selections. */
    public function gradeMappingReady(): bool
    {
        if($this->gradeMappingAvailable!==null)return $this->gradeMappingAvailable;
        $stmt=$this->db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='SubjectGradeLevel' AND COLUMN_NAME IN ('Subject_id','GradeLevel_id')");
        $stmt->execute();
        $cols=$stmt->fetchAll(PDO::FETCH_COLUMN);
        return $this->gradeMappingAvailable=in_array('Subject_id',$cols,true)&&in_array('GradeLevel_id',$cols,true);
    }
    public function catalogGradeIds(int $subjectId): array
    {
        if(!$this->gradeMappingReady())return [];
        $stmt=$this->db->prepare('SELECT GradeLevel_id FROM SubjectGradeLevel WHERE Subject_id=? ORDER BY GradeLevel_id');
        $stmt->execute([$subjectId]);
        return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    }
    /** Existing curriculum/offering associations cannot be edited from the catalogue. */
    public function linkedGradeLabels(int $subjectId): array
    {
        $stmt=$this->db->prepare('SELECT gl.GradeCode,gl.GradeName FROM (SELECT GradeLevel_id FROM CurriculumSubject WHERE Subject_id=? AND GradeLevel_id IS NOT NULL UNION SELECT GradeLevel_id FROM SubjectOffering WHERE Subject_id=? AND GradeLevel_id IS NOT NULL) linked JOIN GradeLevel gl ON gl.GradeLevel_id=linked.GradeLevel_id ORDER BY gl.SortOrder,gl.GradeLevel_id');
        $stmt->execute([$subjectId,$subjectId]);
        $labels=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){
            $name=self::shortGradeName((string)($r['GradeCode']??''),(string)($r['GradeName']??''));
            if($name!==''&&!in_array($name,$labels,true))$labels[]=$name;
        }
        return $labels;
    }
    /** Direct catalog grade plus any curriculum/offering grade for the same subject. */
    public function allSubjectGradeIds(int $subjectId): array
    {
        $parts=[
            'SELECT GradeLevel_id FROM CurriculumSubject WHERE Subject_id=? AND GradeLevel_id IS NOT NULL',
            'SELECT GradeLevel_id FROM SubjectOffering WHERE Subject_id=? AND GradeLevel_id IS NOT NULL',
        ];
        $params=[$subjectId,$subjectId];
        if($this->gradeMappingReady()){
            $parts[]='SELECT GradeLevel_id FROM SubjectGradeLevel WHERE Subject_id=? AND GradeLevel_id IS NOT NULL';
            $params[]=$subjectId;
        }
        $stmt=$this->db->prepare('SELECT DISTINCT GradeLevel_id FROM ('.implode(' UNION ',$parts).') grades ORDER BY GradeLevel_id');
        $stmt->execute($params);
        return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** A subject with existing curriculum or offering links cannot switch grade here. */
    private function validateSingleGradeChange(int $subjectId,int $requestedGrade): void
    {
        $existing=$this->allSubjectGradeIds($subjectId);
        if(count($existing)>1)
            throw new FieldError('grade_level_id','ทะเบียนเดิมรายการนี้เชื่อมหลายระดับชั้น ไม่สามารถเปลี่ยนเป็นระดับเดียวโดยลบความสัมพันธ์เดิม','ต้องแยกรายการรายวิชาตามระดับชั้น และตรวจย้ายความสัมพันธ์ใน CurriculumSubject/SubjectOffering ก่อน',409);
        $stmt=$this->db->prepare('SELECT GradeLevel_id FROM CurriculumSubject WHERE Subject_id=? AND GradeLevel_id IS NOT NULL UNION SELECT GradeLevel_id FROM SubjectOffering WHERE Subject_id=? AND GradeLevel_id IS NOT NULL');
        $stmt->execute([$subjectId,$subjectId]);
        $linked=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
        if($linked && (count($linked)!==1 || $linked[0]!==$requestedGrade))
            throw new FieldError('grade_level_id','รายวิชานี้มีหลักสูตรสถานศึกษาหรือแผนเปิดสอนผูกกับระดับชั้นเดิมอยู่แล้ว','ต้องแก้ความสัมพันธ์ต้นทางให้ถูกต้องก่อน จึงจะเปลี่ยนระดับชั้นในทะเบียนได้',409);
    }

    public function references(): array
    {
        // Curriculum/grade selectors use the actual curriculum associations,
        // never inferred from a subject code or name. Keep inactive references
        // visible so historical links remain searchable.
        $links=$this->db->query('SELECT DISTINCT Curriculum_id,GradeLevel_id FROM CurriculumSubject WHERE GradeLevel_id IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC);
        $curriculumGrades=[];
        foreach($links as $link){
            $gradeId=(int)$link['GradeLevel_id'];
            $curriculumId=(int)$link['Curriculum_id'];
            $curriculumGrades[$gradeId][]=$curriculumId;
        }
        return ['areas'=>$this->db->query('SELECT LearningArea_id,LearningArea_name,IsActive FROM LearningArea ORDER BY LearningArea_name')->fetchAll(PDO::FETCH_ASSOC),
            'types'=>$this->db->query('SELECT TypeofCourse_id,TypeofCourse_name,IsActive FROM TypeofCourse ORDER BY TypeofCourse_id')->fetchAll(PDO::FETCH_ASSOC),
            'grades'=>$this->db->query('SELECT GradeLevel_id,GradeCode,GradeName,EducationLevel,SortOrder,IsActive FROM GradeLevel ORDER BY SortOrder,GradeLevel_id')->fetchAll(PDO::FETCH_ASSOC),
            'curricula'=>$this->db->query('SELECT Curriculum_id,Curriculum_name,IsActive FROM Curriculum ORDER BY CAST(RIGHT(TRIM(Curriculum_name),4) AS UNSIGNED) DESC, Curriculum_id DESC')->fetchAll(PDO::FETCH_ASSOC),
            'curriculumGrades'=>$curriculumGrades];
    }
    /** Untrusted GET filter identifiers must be positive decimal scalar strings. */
    private static function filterId(mixed $value): string
    {
        if(!is_string($value) && !is_int($value))return '';
        $value=(string)$value;
        return preg_match('/^[1-9][0-9]{0,17}$/D',$value)===1?$value:'';
    }
    public function listing(array $filter): array
    {
        $rawQuery=$filter['q']??'';
        if(!is_string($rawQuery))throw new FieldError('q','รูปแบบคำค้นหาไม่ถูกต้อง','กรอกคำค้นหาด้วยข้อความธรรมดา');
        $q=trim($rawQuery);
        if(strlen($q)>150)throw new FieldError('q','คำค้นหายาวเกินไป','ลดคำค้นหาให้สั้นกว่า 150 ตัวอักษร');
        // Filter by the actual course/activity type from TypeofCourse, not
        // SubjectKind (COURSE/ACTIVITY). Accept only positive integer IDs.
        $courseTypeId=self::filterId($filter['course_type_id']??'');
        $status=is_string($filter['status']??'')?($filter['status']??''):'';
        if(!in_array($status,['','0','1'],true))$status='';
        $curriculumId=self::filterId($filter['curriculum_id']??'');
        $gradeId=self::filterId($filter['grade_id']??'');
        $rawPage=$filter['page']??'1';
        $page=(is_string($rawPage)||is_int($rawPage))&&preg_match('/^[1-9][0-9]{0,4}$/D',(string)$rawPage)?(int)$rawPage:1;
        $page=min($page,10000);
        $where=[];$params=[];
        if($q!==''){
            $where[]='(s.Subject_name LIKE ? OR s.Subject_code LIKE ?)';
            $escaped=str_replace(['!','%','_'],['!!','!%','!_'],$q);
            // Explicit ESCAPE works consistently with MariaDB regardless of backslash SQL modes.
                        $params[]='%'.$escaped.'%';$params[]='%'.$escaped.'%';
        }
        if($courseTypeId!==''){$where[]='s.TypeofCourse_id=?';$params[]=$courseTypeId;}
        if($status!==''){$where[]='s.IsActive=?';$params[]=(int)$status;}
        if($curriculumId!=='' && $gradeId!==''){
            // Match the SAME curriculum-subject row, not two unrelated links.
            $where[]='EXISTS (SELECT 1 FROM CurriculumSubject cs WHERE cs.Subject_id=s.Subject_id AND cs.Curriculum_id=? AND cs.GradeLevel_id=?)';
            $params[]=$curriculumId;$params[]=$gradeId;
        }elseif($curriculumId!==''){
            $where[]='EXISTS (SELECT 1 FROM CurriculumSubject cs WHERE cs.Subject_id=s.Subject_id AND cs.Curriculum_id=?)';
            $params[]=$curriculumId;
        }elseif($gradeId!==''){
            // With no curriculum chosen, grade follows the combined grade labels
            // already shown in this catalogue: curriculum, offering and optional
            // direct SubjectGradeLevel mapping.
            $where[]='(EXISTS (SELECT 1 FROM CurriculumSubject cs WHERE cs.Subject_id=s.Subject_id AND cs.GradeLevel_id=?)'
                .' OR EXISTS (SELECT 1 FROM SubjectOffering so WHERE so.Subject_id=s.Subject_id AND so.GradeLevel_id=?)'
                .($this->gradeMappingReady()?' OR EXISTS (SELECT 1 FROM SubjectGradeLevel sg WHERE sg.Subject_id=s.Subject_id AND sg.GradeLevel_id=?)':'').')';
            $params[]=$gradeId;$params[]=$gradeId;
            if($this->gradeMappingReady())$params[]=$gradeId;
        }
        $sqlWhere=$where?' WHERE '.implode(' AND ',$where):'';
        if($q!=='')$sqlWhere=str_replace(' LIKE ?', " LIKE ? ESCAPE '!'",$sqlWhere);
        $ct=$this->db->prepare('SELECT COUNT(*) FROM Subject s'.$sqlWhere);$ct->execute($params);
        $total=(int)$ct->fetchColumn();$pages=max(1,(int)ceil($total/40));$page=min($page,$pages);
        $sql='SELECT s.Subject_id,s.Subject_code,s.Subject_name,s.SubjectKind,s.Learning_Area_id,s.TypeofCourse_id,s.IsActive,
            la.LearningArea_name,tc.TypeofCourse_name
            FROM Subject s LEFT JOIN LearningArea la ON la.LearningArea_id=s.Learning_Area_id
            LEFT JOIN TypeofCourse tc ON tc.TypeofCourse_id=s.TypeofCourse_id'.$sqlWhere.' ORDER BY s.Subject_id DESC LIMIT 40 OFFSET '.(($page-1)*40);
        $stmt=$this->db->prepare($sql);$stmt->execute($params);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        // A Subject is a catalogue entry. Its teaching grades are held in
        // CurriculumSubject and/or actual SubjectOffering records, not Subject.
        // Fetch ONLY the current page's grades: no N+1 query and no duplicate
        // catalogue rows when a subject is taught in several grade levels.
        $grades=$this->teachingGradesForPage($rows,$curriculumId,$gradeId);
        foreach($rows as &$row){
            $row['TeachingGrades']=$grades[(int)$row['Subject_id']]??[];
        }
        unset($row);
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages,'filter'=>['q'=>$q,'curriculum_id'=>$curriculumId,'grade_id'=>$gradeId,'course_type_id'=>$courseTypeId,'status'=>$status]];
    }
    /**
     * Display grade labels in the SAME scope as the applied search filters.
     * This changes only the list labels, never subject/grade associations.
     * - Curriculum + grade: only that grade in that curriculum.
     * - Curriculum only: only grades linked in that curriculum.
     * - Grade only: that grade, from any existing mapping source.
     * - No filter: preserve the previous union of all grade sources.
     *
     * Edit details intentionally use all actual grade associations.
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,list<string>>
     */
    private function teachingGradesForPage(array $rows,string $curriculumId='',string $gradeId=''): array
    {
        $ids=array_map(static fn(array $row):int=>(int)$row['Subject_id'],$rows);
        if(!$ids)return [];
        $placeholders=implode(',',array_fill(0,count($ids),'?'));
        $parts=[];
        $params=[];

        // Never mix grade labels from a different curriculum when a curriculum
        // filter is active. SubjectOffering and SubjectGradeLevel do not carry
        // a Curriculum_id, so they must not be unioned into this scope.
        if($curriculumId!==''){
            $part="SELECT cs.Subject_id,cs.GradeLevel_id
                   FROM CurriculumSubject cs
                   WHERE cs.Subject_id IN ($placeholders)
                     AND cs.GradeLevel_id IS NOT NULL
                     AND cs.Curriculum_id=?";
            $partParams=array_merge($ids,[$curriculumId]);
            if($gradeId!==''){
                $part.=' AND cs.GradeLevel_id=?';
                $partParams[]=$gradeId;
            }
            $parts[]=$part;
            $params=array_merge($params,$partParams);
        }else{
            // Retain all existing sources for an unscoped curriculum search.
            $sources=[['CurriculumSubject','cs'],['SubjectOffering','so']];
            if($this->gradeMappingReady())$sources[]=['SubjectGradeLevel','sg'];
            foreach($sources as [$table,$alias]){
                $part="SELECT $alias.Subject_id,$alias.GradeLevel_id
                       FROM $table $alias
                       WHERE $alias.Subject_id IN ($placeholders)
                         AND $alias.GradeLevel_id IS NOT NULL";
                $partParams=$ids;
                if($gradeId!==''){
                    $part.=" AND $alias.GradeLevel_id=?";
                    $partParams[]=$gradeId;
                }
                $parts[]=$part;
                $params=array_merge($params,$partParams);
            }
        }

        $sql='SELECT linked.Subject_id,gl.GradeCode,gl.GradeName
              FROM ('.implode(' UNION ',$parts).') linked
              INNER JOIN GradeLevel gl ON gl.GradeLevel_id=linked.GradeLevel_id
              ORDER BY linked.Subject_id,gl.SortOrder,gl.GradeLevel_id';
        $stmt=$this->db->prepare($sql);
        $stmt->execute($params);
        $result=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $linked){
            $id=(int)$linked['Subject_id'];
            $label=self::shortGradeName((string)($linked['GradeCode']??''),(string)($linked['GradeName']??''));
            if($label!=='' && !in_array($label,$result[$id]??[],true))$result[$id][]=$label;
        }
        return $result;
    }

    public static function shortGradeName(string $code,string $name):string
    {
        $code=strtoupper(trim($code));
        if(preg_match('/^([KPM])([0-9]{1,2})$/D',$code,$matches)){
            $prefix=['K'=>'อ.','P'=>'ป.','M'=>'ม.'][$matches[1]];
            return $prefix.(int)$matches[2];
        }
        // Preserve unknown local school grade names, never invent a grade.
        return trim($name);
    }

    /** Curriculum-level hours and credit belong to CurriculumSubject, not Subject. */
    public function curriculumHours(int $subjectId): array
    {
        $stmt=$this->db->prepare("SELECT cs.CurriculumSubject_id,cs.Subject_id,cs.Curriculum_id,cs.GradeLevel_id,
                    cs.InstructionalPeriod,cs.Semester,cs.ProgramTrack_id,cs.Credit,cs.WeeklyHours,
                    cs.TotalInstructionalHours,cs.SelectionGroupCode,cs.ReviewStatus,cs.IsActive,
                    c.Curriculum_name,g.GradeCode,g.GradeName,
                    (SELECT COUNT(*) FROM SubjectOffering so
                      WHERE so.Subject_id=cs.Subject_id AND so.GradeLevel_id=cs.GradeLevel_id)
                      AS OfferingCountForGrade
                FROM CurriculumSubject cs
                INNER JOIN Curriculum c ON c.Curriculum_id=cs.Curriculum_id
                INNER JOIN GradeLevel g ON g.GradeLevel_id=cs.GradeLevel_id
                WHERE cs.Subject_id=?
                ORDER BY CAST(RIGHT(TRIM(c.Curriculum_name),4) AS UNSIGNED) DESC, g.SortOrder,cs.CurriculumSubject_id");
        $stmt->execute([$subjectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Save changed draft curriculum-hour rows atomically; never mutate Subject/offerings/students. */
    public function saveCurriculumHours(array $input): array
    {
        if(!in_array('SYSTEM_ADMIN',$this->actor['Roles']??[],true))
            throw new WebError(403,'ไม่มีสิทธิ์แก้ไขชั่วโมงและหน่วยกิตของหลักสูตรสถานศึกษา');
        $subjectId=Security::positive($input['subject_id']??null,'subject_id','รายวิชา');
        $items=$input['hours']??null;
        if(!is_array($items)||count($items)<1||count($items)>100)
            throw new WebError(422,'ไม่พบรายการชั่วโมงเรียน หรือมีจำนวนรายการมากเกินไป');

        $this->stage('curriculum-hours-transaction');
        $ownsTransaction=!$this->db->inTransaction();
        if($ownsTransaction)$this->db->beginTransaction();
        try {
            $subject=$this->one('SELECT Subject_id FROM Subject WHERE Subject_id=? FOR UPDATE',[$subjectId]);
            if(!$subject)throw new WebError(404,'ไม่พบทะเบียนรายวิชานี้');
            $this->stage('curriculum-hours-lock');
            $lookup=$this->db->prepare('SELECT cs.CurriculumSubject_id,cs.Subject_id,cs.GradeLevel_id,cs.Credit,cs.WeeklyHours,cs.TotalInstructionalHours,cs.ReviewStatus,cs.IsActive FROM CurriculumSubject cs WHERE cs.CurriculumSubject_id=? AND cs.Subject_id=? FOR UPDATE');
            $offering=$this->db->prepare('SELECT COUNT(*) FROM SubjectOffering WHERE Subject_id=? AND GradeLevel_id=?');
            $update=$this->db->prepare("UPDATE CurriculumSubject SET Credit=?,WeeklyHours=?,TotalInstructionalHours=? WHERE CurriculumSubject_id=? AND Subject_id=? AND ReviewStatus='DRAFT' AND IsActive=0");
            $audit=$this->db->prepare('INSERT INTO AuditLog (EntityName,EntityId,ActionType,OldValuesJson,NewValuesJson,ActorUser_id,ChangedAt) VALUES (?,?,?,?,?,?,NOW())');
            $changed=0;
            foreach($items as $key=>$fields) {
                if(!is_array($fields)||!preg_match('/^[1-9][0-9]{0,18}$/D',(string)$key))
                    throw new WebError(422,'รหัสรายการในหลักสูตรสถานศึกษาไม่ถูกต้อง');
                $id=Security::positive((string)$key,'curriculum_subject_id','รายการในหลักสูตรสถานศึกษา');
                $lookup->execute([$id,$subjectId]);
                $existing=$lookup->fetch(PDO::FETCH_ASSOC);
                if(!$existing)throw new WebError(409,'พบรายการจากหลักสูตรสถานศึกษาอื่นหรือรายการไม่อยู่ในวิชาที่กำลังแก้ไข กรุณาโหลดหน้าใหม่');
                if((string)$existing['ReviewStatus']!=='DRAFT'||(int)$existing['IsActive']!==0)
                    throw new WebError(409,'รายการหลักสูตรสถานศึกษา #'.$id.' ไม่ใช่ฉบับร่างแล้ว จึงห้ามแก้ไขชั่วโมงย้อนหลัง');
                $offering->execute([$subjectId,(int)$existing['GradeLevel_id']]);
                if((int)$offering->fetchColumn()>0)
                    throw new WebError(409,'วิชานี้มีประวัติเปิดสอนในระดับชั้นที่เลือกแล้ว กรุณาแก้ผ่านแผนเปิดสอนหรือสร้างเวอร์ชันใหม่');
                $old=[
                    'Credit'=>self::hoursDecimal($existing['Credit'],'credit',true),
                    'WeeklyHours'=>self::hoursDecimal($existing['WeeklyHours'],'weekly',true),
                    'TotalInstructionalHours'=>self::hoursDecimal($existing['TotalInstructionalHours'],'annual',true),
                ];
                $expected=[
                    'Credit'=>self::hoursDecimal($fields['expected_credit']??null,'expected_credit',true),
                    'WeeklyHours'=>self::hoursDecimal($fields['expected_weekly']??null,'expected_weekly',true),
                    'TotalInstructionalHours'=>self::hoursDecimal($fields['expected_annual']??null,'expected_annual',true),
                ];
                if($expected!==$old)
                    throw new WebError(409,'ข้อมูลของรายการหลักสูตรสถานศึกษา #'.$id.' ถูกเปลี่ยนระหว่างเปิดหน้า กรุณาโหลดหน้าใหม่แล้วตรวจสอบก่อนบันทึก');
                $new=[
                    'Credit'=>self::hoursDecimal($fields['credit']??null,'hours['.$id.'][credit]',false),
                    'WeeklyHours'=>self::hoursDecimal($fields['weekly']??null,'hours['.$id.'][weekly]',false),
                    'TotalInstructionalHours'=>self::hoursDecimal($fields['annual']??null,'hours['.$id.'][annual]',false),
                ];
                if($new===$old)continue;
                $this->stage('curriculum-hours-write');
                $update->execute([$new['Credit'],$new['WeeklyHours'],$new['TotalInstructionalHours'],$id,$subjectId]);
                if($update->rowCount()!==1)
                    throw new WebError(409,'ข้อมูลรายการหลักสูตรสถานศึกษามีการเปลี่ยนสถานะ กรุณาโหลดหน้าใหม่');
                $this->stage('curriculum-hours-audit');
                $audit->execute(['CurriculumSubject',(string)$id,'UPDATE_HOURS_CREDIT',
                    json_encode($old,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
                    json_encode($new,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
                    (int)$this->actor['SystemUser_id']]);
                $changed++;
            }
            $this->stage('curriculum-hours-commit');
            if($ownsTransaction)$this->db->commit();
            return ['message'=>$changed?'บันทึกหน่วยกิตและชั่วโมงเรียนในหลักสูตรสถานศึกษา '. $changed .' รายการแล้ว':'ไม่มีค่าที่เปลี่ยนแปลง','subject_id'=>$subjectId,'updated_count'=>$changed];
        }catch(\Throwable $e){if($ownsTransaction && $this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    /** Validate base-10 decimals using the actual DECIMAL(7,2)/(6,2)/(8,2) limits. */
    private static function hoursDecimal(mixed $raw,string $field,bool $allowZero): string
    {
        $digits=match(true){
            str_contains($field,'credit')||$field==='credit'=>5,
            str_contains($field,'weekly')||$field==='weekly'=>4,
            default=>6,
        };
        if(!is_string($raw)&&!is_int($raw)&&!is_float($raw))
            throw new FieldError($field,'ค่าชั่วโมงหรือหน่วยกิตไม่ถูกต้อง','กรอกตัวเลขทศนิยมไม่เกิน 2 ตำแหน่ง');
        $raw=trim((string)$raw);
        $pattern='/^(?:0|[1-9][0-9]{0,'.($digits-1).'})(?:\\.[0-9]{1,2})?$/D';
        if(!preg_match($pattern,$raw))
            throw new FieldError($field,'รูปแบบตัวเลขไม่ถูกต้อง','กรอกตัวเลขไม่ติดลบ ไม่เกิน '.$digits.' หลักก่อนจุด และทศนิยมไม่เกิน 2 ตำแหน่ง');
        if(!$allowZero && (float)$raw<=0.0 && !str_contains($field,'credit') && $field!=='credit')
            throw new FieldError($field,'ชั่วโมงเรียนต้องมากกว่า 0','กรอกชั่วโมงที่มากกว่า 0 แล้วบันทึกอีกครั้ง');
        return number_format((float)$raw,2,'.','');
    }

    public function detail(int $id): ?array
    {
        $stmt=$this->db->prepare('SELECT Subject_id,Subject_code,Subject_name,Learning_Area_id,TypeofCourse_id,SubjectKind,IsActive FROM Subject WHERE Subject_id=? LIMIT 1');
        $stmt->execute([$id]);return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
    }
    private function one(string $sql,array $params=[]): ?array
    {
        $stmt=$this->db->prepare($sql);$stmt->execute($params);return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
    }
    private function query(string $sql,array $params=[]): void
    {
        $stmt=$this->db->prepare($sql);$stmt->execute($params);
    }
    private function confirmReference(?int $id,string $table,string $col,string $field,string $label): void
    {
        if($id===null)return;
        // Identifiers are constants from code, never from user input.
        if(!$this->one("SELECT $col FROM $table WHERE $col=? AND IsActive=1",[$id]))
            throw new FieldError($field,"ไม่พบ{$label}หรือรายการถูกปิดใช้งาน","โหลดหน้าใหม่แล้วเลือก{$label}ที่เปิดใช้งาน");
    }
    private function verifyType(array $data): void
    {
        $this->confirmReference($data['Learning_Area_id'],'LearningArea','LearningArea_id','learning_area_id','กลุ่มสาระ');
        $this->confirmReference($data['TypeofCourse_id'],'TypeofCourse','TypeofCourse_id','course_type_id','ประเภทรายวิชา/กิจกรรม');
        if($data['TypeofCourse_id']!==null){
            $row=$this->one('SELECT TypeofCourse_name FROM TypeofCourse WHERE TypeofCourse_id=?',[$data['TypeofCourse_id']]);
            $isActivity=trim((string)($row['TypeofCourse_name']??''))==='กิจกรรม';
            if($data['SubjectKind']==='ACTIVITY' && !$isActivity)
                throw new FieldError('course_type_id','กิจกรรมต้องเลือกประเภท “กิจกรรม”','เลือกประเภทกิจกรรมจากรายการ หรือเว้นประเภทไว้เป็นร่าง');
            if($data['SubjectKind']==='COURSE' && $isActivity)
                throw new FieldError('course_type_id','รายวิชาไม่สามารถใช้ประเภท “กิจกรรม”','เลือกประเภทพื้นฐานหรือเพิ่มเติม หรือเว้นประเภทไว้เป็นร่าง');
        }
    }
    private function avoidDuplicate(array $data, int $except, int $gradeId): void
    {
        // Same code/name in DIFFERENT grades is legitimate. Without a code,
        // same title in different grades is legitimate too. In a single grade
        // an otherwise identical subject/activity must not be duplicated.
        $sql='SELECT s.Subject_id FROM Subject s
            WHERE s.Subject_code <=> ? AND s.SubjectKind=? AND s.Subject_name=?
              AND s.Subject_id<>? AND s.Learning_Area_id <=> ? AND s.TypeofCourse_id <=> ?
              AND (EXISTS (SELECT 1 FROM SubjectGradeLevel sg WHERE sg.Subject_id=s.Subject_id AND sg.GradeLevel_id=?)
                   OR EXISTS (SELECT 1 FROM CurriculumSubject cs WHERE cs.Subject_id=s.Subject_id AND cs.GradeLevel_id=?)
                   OR EXISTS (SELECT 1 FROM SubjectOffering so WHERE so.Subject_id=s.Subject_id AND so.GradeLevel_id=?))
            LIMIT 1 FOR UPDATE';
        if($this->one($sql,[$data['Subject_code'],$data['SubjectKind'],$data['Subject_name'],$except,$data['Learning_Area_id'],$data['TypeofCourse_id'],$gradeId,$gradeId,$gradeId]))
            throw new FieldError('subject_name','มีทะเบียนรายวิชา/กิจกรรมเดียวกันสำหรับระดับชั้นนี้แล้ว','ค้นหารายการในระดับชั้นที่เลือก และแก้ไขรายการเดิมแทน',409);
    }
    /** Update only the catalogue's direct grades, not curriculum/offering history. */
    private function replaceCatalogGrades(int $subjectId,array $grades): void
    {
        if(count($grades)!==1)throw new FieldError('grade_level_id','ต้องเลือกเพียงระดับชั้นเดียว','เลือกระดับชั้นหนึ่งรายการก่อนบันทึก');
        $this->stage('subject-grade-read');
        $previous=$this->catalogGradeIds($subjectId);
        $this->stage('subject-grade-validate');
        foreach($grades as $gradeId){
            // Keep old mappings for now-disabled grade levels; only *new* links
            // must point at an active GradeLevel record.
            if(!in_array($gradeId,$previous,true)
                && !$this->one('SELECT GradeLevel_id FROM GradeLevel WHERE GradeLevel_id=? AND IsActive=1',[$gradeId]))
                throw new FieldError('grade_level_id','ระดับชั้นที่เลือกไม่มีอยู่หรือปิดใช้งาน','โหลดหน้าใหม่แล้วเลือกเฉพาะระดับชั้นที่เปิดใช้งาน');
        }
        $remove=array_values(array_diff($previous,$grades));
        $add=array_values(array_diff($grades,$previous));
        $this->stage('subject-grade-write');
        foreach($remove as $gradeId){
            $this->query('DELETE FROM SubjectGradeLevel WHERE Subject_id=? AND GradeLevel_id=?',[$subjectId,$gradeId]);
        }
        foreach($add as $gradeId){
            $this->query('INSERT INTO SubjectGradeLevel (Subject_id,GradeLevel_id) VALUES (?,?)',[$subjectId,$gradeId]);
        }
    }
    private function audit(int $id,string $action,?array $old,array $new): void
    {
        $oldJson=json_encode($old??[],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $newJson=json_encode($new,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        $this->stage('subject-audit');
        $this->query('INSERT INTO AuditLog (EntityName,EntityId,ActionType,OldValuesJson,NewValuesJson,ActorUser_id,ChangedAt) VALUES (?,?,?,?,?,?,NOW())',
            ['Subject',(string)$id,$action,$old===null?'{}':$oldJson,$newJson,(int)$this->actor['SystemUser_id']]);
    }
    /** One form, one user action, one database transaction for catalogue + grade-specific hours. */
    public function saveUnifiedSubject(array $input): array
    {
        if(!in_array('SYSTEM_ADMIN',$this->actor['Roles']??[],true))
            throw new WebError(403,'เฉพาะผู้ดูแลระบบเท่านั้นที่แก้ไขทะเบียนรายวิชาได้');
        // Never include status changes in the combined editor.
        Security::positive($input['subject_id']??null,'subject_id','รายวิชา');
        if(array_key_exists('hours',$input) && !is_array($input['hours']))
            throw new WebError(422,'รูปแบบรายการหน่วยกิตและชั่วโมงเรียนไม่ถูกต้อง');
        if($this->db->inTransaction())throw new WebError(409,'ไม่สามารถเริ่มการบันทึกซ้อนกันได้');
        $this->stage('subject-unified-transaction');
        $this->db->beginTransaction();
        try {
            $result=$this->save('update-subject',$input);
            $changed=0;
            if(!empty($input['hours'])) {
                $hoursResult=$this->saveCurriculumHours($input);
                $changed=(int)($hoursResult['updated_count']??0);
            }
            $this->stage('subject-unified-commit');
            $this->db->commit();
            $result['updated_count']=$changed;
            $result['message']='บันทึกการแก้ไขรายวิชาเรียบร้อย'.($changed?' และปรับหน่วยกิต/เวลาเรียน '.$changed.' รายการ':'');
            return $result;
        } catch(\Throwable $e) {
            if($this->db->inTransaction())$this->db->rollBack();
            throw $e;
        }
    }

    public function save(string $action,array $input): array
    {
        if(!in_array('SYSTEM_ADMIN',$this->actor['Roles']??[],true))throw new WebError(403,'เฉพาะผู้ดูแลระบบเท่านั้นที่จัดการทะเบียนรายวิชาได้');
        if(!in_array($action,['create-subject','update-subject','set-subject-status'],true))throw new WebError(422,'ไม่รู้จักคำสั่งจัดการรายวิชา');
        $schema=$this->precheck();
        $grades=$action==='set-subject-status'?null:SubjectRules::gradeSelection($input);
        if($grades!==null && !$this->gradeMappingReady())
            throw new WebError(409,'ยังไม่มีตาราง SubjectGradeLevel สำหรับบันทึกระดับชั้น กรุณาสำรองฐานข้อมูลแล้วรัน SQL Migration ที่แนบมาก่อน');
        $id=$action==='create-subject'?0:Security::positive($input['subject_id']??null,'subject_id','รายการรายวิชา');
        $this->stage('subject-transaction');
        $ownsTransaction=!$this->db->inTransaction();
        if($ownsTransaction)$this->db->beginTransaction();
        try {
            $old=null;
            if($id>0){
                $this->stage('subject-lock-row');
                $old=$this->one('SELECT Subject_id,Subject_code,Subject_name,Learning_Area_id,TypeofCourse_id,SubjectKind,IsActive FROM Subject WHERE Subject_id=? FOR UPDATE',[$id]);
                if(!$old)throw new WebError(404,'ไม่พบทะเบียนรายวิชาที่ต้องการแก้ไข กรุณาโหลดหน้าใหม่');
            }
            if($action==='set-subject-status'){
                $active=(string)($input['is_active']??'');
                if(!in_array($active,['0','1'],true))throw new FieldError('is_active','สถานะไม่ถูกต้อง','ใช้ปุ่มเปิดหรือปิดใช้งานที่หน้าเว็บ');
                if($active==='1'){
                    $attached=$this->allSubjectGradeIds($id);
                    if(count($attached)!==1)throw new FieldError('grade_level_id','ต้องกำหนดระดับชั้นเดียวก่อนเปิดใช้งาน','หากรายวิชาเดิมเชื่อมหลายระดับชั้น ต้องแยกทะเบียนโดยตรวจความสัมพันธ์ก่อน',409);
                    if(($input['review_confirm']??'')!=='yes')throw new FieldError('review_confirm','ยังไม่ได้ยืนยันการตรวจข้อมูล','ตรวจเอกสารต้นทาง แล้วทำเครื่องหมายยืนยันก่อนเปิดใช้งาน');
                    $existingData=['SubjectKind'=>(string)$old['SubjectKind'],'Subject_name'=>(string)$old['Subject_name'],
                        'Subject_code'=>$old['Subject_code'],'Learning_Area_id'=>($old['Learning_Area_id']===null?null:(int)$old['Learning_Area_id']),
                        'TypeofCourse_id'=>($old['TypeofCourse_id']===null?null:(int)$old['TypeofCourse_id'])];
                    $this->verifyType($existingData);
                    if($existingData['TypeofCourse_id']===null)throw new FieldError('course_type_id','ยังไม่ได้กำหนดประเภทรายวิชา/กิจกรรม','แก้ไขรายการให้มีประเภทที่ตรวจสอบแล้วก่อนเปิดใช้งาน');
                    if($existingData['SubjectKind']==='COURSE' && $existingData['Learning_Area_id']===null)
                        throw new FieldError('learning_area_id','รายวิชายังไม่มีกลุ่มสาระ','แก้ไขรายการให้มีกลุ่มสาระก่อนเปิดใช้งาน');
                }
                $this->stage('subject-status');
                $this->query('UPDATE Subject SET IsActive=? WHERE Subject_id=?',[(int)$active,$id]);
                $this->audit($id,$active==='1'?'ACTIVATE':'DEACTIVATE',$old,['IsActive'=>(int)$active]);
                $message=$active==='1'?'เปิดใช้งานรายการแล้ว':'ปิดใช้งานรายการแล้ว ข้อมูลเดิมยังคงอยู่';
            } else {
                $data=SubjectRules::validate($input);
                if($data['TypeofCourse_id']===null && ($schema['columns']['Subject']['TypeofCourse_id']??'YES')!=='YES')
                    throw new FieldError('course_type_id','ฐานข้อมูลกำหนดให้ต้องเลือกประเภทรายวิชา/กิจกรรม','เลือกประเภทที่ถูกต้องก่อนบันทึก แม้จะบันทึกเป็นร่าง');
                $this->verifyType($data);
                $targetGrade=$grades[0];
                $this->stage('subject-grade-lock');
                // Lock GradeLevel as a serialization point for concurrent creates
                // of an identical record in the same level.
                $gradeRow=$this->one('SELECT GradeLevel_id,IsActive FROM GradeLevel WHERE GradeLevel_id=? FOR UPDATE',[$targetGrade]);
                if(!$gradeRow)throw new FieldError('grade_level_id','ไม่พบระดับชั้นที่เลือก','เลือกระดับชั้นจากรายการที่มีอยู่จริง');
                if($id>0)$this->validateSingleGradeChange($id,$targetGrade);
                if((int)$gradeRow['IsActive']!==1 && ($id===0 || !in_array($targetGrade,$this->allSubjectGradeIds($id),true)))
                    throw new FieldError('grade_level_id','ระดับชั้นนี้ปิดใช้งานอยู่','เลือกระดับชั้นที่เปิดใช้งาน');
                $this->stage('subject-duplicates');
                $this->avoidDuplicate($data,$id,$targetGrade);
                if($action==='create-subject'){
                    $this->stage('subject-insert');
                    $this->query('INSERT INTO Subject (Subject_code,Subject_name,Learning_Area_id,TypeofCourse_id,SubjectKind,IsActive) VALUES (?,?,?,?,?,0)',
                        [$data['Subject_code'],$data['Subject_name'],$data['Learning_Area_id'],$data['TypeofCourse_id'],$data['SubjectKind']]);
                    $id=(int)$this->db->lastInsertId();
                    $this->replaceCatalogGrades($id,$grades);
                    $this->audit($id,'CREATE',null,$data+['IsActive'=>0]+['CatalogGradeLevelIds'=>$grades]);
                    $message='เพิ่มรายการเป็นร่างแล้ว ยังไม่เปิดใช้งาน';
                } else {
                    if((int)$old['IsActive']===1)
                        throw new WebError(409,'รายการที่เปิดใช้งานแล้วต้องปิดใช้งานก่อนแก้ไข เพื่อรักษาข้อมูลที่ถูกใช้อ้างอิง');
                    $previousGrades=$this->catalogGradeIds($id);
                    $this->stage('subject-update');
                    $this->query('UPDATE Subject SET Subject_code=?,Subject_name=?,Learning_Area_id=?,TypeofCourse_id=?,SubjectKind=? WHERE Subject_id=?',
                        [$data['Subject_code'],$data['Subject_name'],$data['Learning_Area_id'],$data['TypeofCourse_id'],$data['SubjectKind'],$id]);
                    $this->replaceCatalogGrades($id,$grades);
                    $oldAudit=$old;
                    $oldAudit['CatalogGradeLevelIds']=$previousGrades;
                    $this->audit($id,'UPDATE',$oldAudit,$data+['IsActive'=>0]+['CatalogGradeLevelIds'=>$grades]);
                    $message='แก้ไขรายการร่างแล้ว';
                }
            }
            $this->stage('subject-commit');
            if($ownsTransaction)$this->db->commit();
            return ['message'=>$message,'subject_id'=>$id];
        }catch(\Throwable $e){if($ownsTransaction && $this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
