<?php
declare(strict_types=1);
namespace Kap\Admin;

use Kap\Teacher\WebError;
use PDO;

/** Production CRUD for Curriculum and CurriculumSection; cohort maps are read-only. */
final class CurriculumCatalog
{
    private string $stage = 'curriculum-init';
    private array $columns = [];
    public function __construct(private PDO $db, private array $actor) {}
    public function currentStage(): string { return $this->stage; }
    private function stage(string $name): void { $this->stage = $name; }
    private function execute(string $sql, array $params = []): \PDOStatement {
        $q = $this->db->prepare($sql);
        $q->execute($params);
        return $q;
    }
    private function tableColumns(string $name): array {
        if (array_key_exists($name, $this->columns)) return $this->columns[$name];
        // Only constant, hard-coded table names may reach this method.
        if (!in_array($name, ['Curriculum','CurriculumSection','CurriculumGradeYear','AcademicYear','GradeLevel'], true))
            throw new WebError(422, 'ชื่อตารางสำหรับตรวจสอบไม่ถูกต้อง');
        try {
            $columns=[];
            foreach ($this->db->query('SHOW COLUMNS FROM `'.$name.'`')->fetchAll(PDO::FETCH_ASSOC) as $r)
                $columns[(string)$r['Field']] = (string)$r['Null'];
            return $this->columns[$name]=$columns;
        } catch (\PDOException $e) {
            if (in_array((int)($e->errorInfo[1]??0), [1146], true)) return $this->columns[$name]=[];
            throw $e;
        }
    }
    public function precheck(): void {
        $required = [
            'Curriculum'=>['Curriculum_id','Curriculum_name','Notes','IsActive'],
            'CurriculumSection'=>['CurriculumSection_id','Curriculum_id','ParentSection_id','SectionCode','SectionName','SortOrder','IsActive'],
            'AcademicYear'=>['AcademicYear_id','AcademicYear_name'],
            'GradeLevel'=>['GradeLevel_id','GradeCode','GradeName','SortOrder'],
        ];
        $missing=[];
        foreach ($required as $table=>$cols) {
            $found=$this->tableColumns($table);
            foreach($cols as $col) if(!array_key_exists($col,$found))$missing[]=$table.'.'.$col;
        }
        if($missing) throw new WebError(409, 'โครงสร้างหลักสูตรสถานศึกษายังไม่ครบ: '.implode(', ',$missing).' กรุณาตรวจ Schema ใน phpMyAdmin');
    }
    public function curricula(): array {
        return $this->db->query('SELECT c.Curriculum_id,c.Curriculum_name,c.Notes,c.IsActive,
             (SELECT COUNT(*) FROM CurriculumSection s WHERE s.Curriculum_id=c.Curriculum_id) AS SectionCount,
             (SELECT COUNT(*) FROM CurriculumSection s WHERE s.Curriculum_id=c.Curriculum_id AND s.IsActive=0) AS DraftCount
             FROM Curriculum c ORDER BY CAST(RIGHT(TRIM(c.Curriculum_name),4) AS UNSIGNED) DESC, c.Curriculum_id DESC')->fetchAll(PDO::FETCH_ASSOC);
    }
    public function curriculum(int $id): ?array {
        $r=$this->execute('SELECT Curriculum_id,Curriculum_name,Notes,IsActive FROM Curriculum WHERE Curriculum_id=?',[$id])->fetch(PDO::FETCH_ASSOC);
        return $r?:null;
    }
    public function sections(?int $filterId = null, string $search = ''): array {
        $where=[];$args=[];
        if($filterId!==null){$where[]='s.Curriculum_id=?';$args[]=$filterId;}
        $search=trim($search);
        if($search!==''){
            if(strlen($search)>120)throw new FieldError('q','คำค้นหายาวเกินไป','ลดคำค้นหาให้ไม่เกิน 120 ไบต์');
            $where[]='(s.SectionName LIKE ? OR s.SectionCode LIKE ?)';
            $args[]='%'.$search.'%';$args[]='%'.$search.'%';
        }
        $sql='SELECT s.CurriculumSection_id,s.Curriculum_id,s.ParentSection_id,s.SectionCode,s.SectionName,s.SortOrder,s.IsActive,
              c.Curriculum_name,p.SectionName AS ParentName
              FROM CurriculumSection s
              INNER JOIN Curriculum c ON c.Curriculum_id=s.Curriculum_id
              LEFT JOIN CurriculumSection p ON p.CurriculumSection_id=s.ParentSection_id'
              .($where?' WHERE '.implode(' AND ',$where):'')
              .' ORDER BY CAST(RIGHT(TRIM(c.Curriculum_name),4) AS UNSIGNED) DESC, c.Curriculum_id DESC, COALESCE(s.ParentSection_id,s.CurriculumSection_id),s.ParentSection_id IS NOT NULL,s.SortOrder,s.CurriculumSection_id LIMIT 1000';
        return $this->execute($sql,$args)->fetchAll(PDO::FETCH_ASSOC);
    }
    public function section(int $id): ?array {
        $r=$this->execute('SELECT CurriculumSection_id,Curriculum_id,ParentSection_id,SectionCode,SectionName,SortOrder,IsActive FROM CurriculumSection WHERE CurriculumSection_id=?',[$id])->fetch(PDO::FETCH_ASSOC);
        return $r?:null;
    }
    public function mappingYears():array {
        return $this->db->query('SELECT AcademicYear_id,AcademicYear_name FROM AcademicYear ORDER BY AcademicYear_name DESC')->fetchAll(PDO::FETCH_ASSOC);
    }
    /** Existing verified year/grade curriculum associations: strictly read-only. */
    public function cohortMappings(?int $yearId): array {
        $cols=$this->tableColumns('CurriculumGradeYear');
        $required=['Curriculum_id','AcademicYear_id','GradeLevel_id'];
        foreach($required as $c) if(!isset($cols[$c])) return ['ready'=>false,'rows'=>[]];
        $extra=[];
        foreach(['CohortStartYearBE','CohortEndYearBE','DurationYears','VerificationStatus'] as $column)
            if(isset($cols[$column]))$extra[]='cg.`'.$column.'`';
        $sql='SELECT ay.AcademicYear_name,g.GradeCode,g.GradeName,c.Curriculum_name,c.IsActive AS CurriculumActive'
             .($extra?', '.implode(', ',$extra):'').'
             FROM CurriculumGradeYear cg
             INNER JOIN Curriculum c ON c.Curriculum_id=cg.Curriculum_id
             INNER JOIN AcademicYear ay ON ay.AcademicYear_id=cg.AcademicYear_id
             INNER JOIN GradeLevel g ON g.GradeLevel_id=cg.GradeLevel_id'
             .($yearId!==null?' WHERE cg.AcademicYear_id=?':'')
             .' ORDER BY CAST(ay.AcademicYear_name AS UNSIGNED) DESC, CAST(RIGHT(TRIM(c.Curriculum_name),4) AS UNSIGNED) DESC, g.SortOrder,g.GradeLevel_id LIMIT 500';
        return ['ready'=>true,'rows'=>$this->execute($sql,$yearId===null?[]:[$yearId])->fetchAll(PDO::FETCH_ASSOC)];
    }
    private function audit(string $entity, int $id, string $action, ?array $before, array $after):void {
        $this->stage('write-audit');
        $this->execute('INSERT INTO AuditLog (EntityName,EntityId,ActionType,OldValuesJson,NewValuesJson,ActorUser_id,ChangedAt) VALUES (?,?,?,?,?,?,NOW())',[
            $entity,(string)$id,$action,
            json_encode($before??new \stdClass(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
            json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
            (int)$this->actor['SystemUser_id']
        ]);
    }
    private function verifyActor():void {
        if(!in_array('SYSTEM_ADMIN',$this->actor['Roles']??[],true))throw new WebError(403,'เฉพาะผู้ดูแลระบบเท่านั้นที่แก้ไขหลักสูตรสถานศึกษาได้');
    }
    private function optionalText(mixed $raw,int $max,string $field,string $label):?string {
        if($raw===null||$raw==='')return null;
        return Security::text($raw,$max,false,$field,$label);
    }
    private function exactFlag(mixed $value):int {
        if($value==='0'||$value===0)return 0;
        if($value==='1'||$value===1)return 1;
        throw new FieldError('is_active','สถานะไม่ถูกต้อง','เลือกเปิดหรือปิดใช้งานผ่านปุ่มในตาราง');
    }
    private function idOrNull(mixed $raw,string $field,string $label):?int {
        if($raw===null||$raw==='')return null;
        return Security::positive($raw,$field,$label);
    }
    private function verifyReview(array $input):void {
        if(($input['review_confirm']??null)!=='yes')throw new FieldError('review_confirm','ยังไม่ได้ยืนยันตรวจเอกสารต้นทาง','ทำเครื่องหมายตรวจแล้วหลังตรวจข้อมูลกับฝ่ายวิชาการ');
    }
    public function save(string $action,array $input):array {
        $this->verifyActor();
        $actions=['create-curriculum','update-curriculum','curriculum-status','create-section','update-section','section-status'];
        if(!in_array($action,$actions,true))throw new WebError(422,'ไม่รู้จักคำสั่งจัดการหลักสูตรสถานศึกษา');
        $this->precheck();
        $this->stage('begin-transaction');
        $this->db->beginTransaction();
        try {
            $result=match($action){
                'create-curriculum','update-curriculum'=>$this->writeCurriculum($action,$input),
                'curriculum-status'=>$this->changeCurriculumStatus($input),
                'create-section','update-section'=>$this->writeSection($action,$input),
                'section-status'=>$this->changeSectionStatus($input),
            };
            $this->stage('commit');
            $this->db->commit();
            return $result;
        }catch(\Throwable $e){ if($this->db->inTransaction())$this->db->rollBack();throw $e; }
    }
    private function writeCurriculum(string $action,array $input):array {
        $name=Security::text($input['curriculum_name']??null,255,true,'curriculum_name','ชื่อหลักสูตรสถานศึกษา');
        $notes=$this->optionalText($input['notes']??null,5000,'notes','หมายเหตุ');
        $id=$action==='update-curriculum'?Security::positive($input['curriculum_id']??null,'curriculum_id','หลักสูตรสถานศึกษา'):null;
        $this->stage('validate-curriculum');
        $old=null;
        if($id!==null){
            $old=$this->execute('SELECT Curriculum_id,Curriculum_name,Notes,IsActive FROM Curriculum WHERE Curriculum_id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
            if(!$old)throw new WebError(404,'ไม่พบหลักสูตรสถานศึกษาที่ต้องการแก้ไข');
            if((int)$old['IsActive']===1)throw new FieldError('curriculum_name','หลักสูตรสถานศึกษากำลังเปิดใช้งาน','ปิดใช้งานหลักสูตรสถานศึกษาก่อนแก้รายละเอียด โดยตรวจผลกระทบกับปีและรายวิชาที่ผูกไว้');
        }
        $duplicate=$this->execute('SELECT Curriculum_id FROM Curriculum WHERE Curriculum_name=? AND Curriculum_id<>? LIMIT 1',[$name,$id??0])->fetchColumn();
        if($duplicate)throw new FieldError('curriculum_name','ชื่อหลักสูตรสถานศึกษาซ้ำ','ใช้ชื่อหลักสูตรสถานศึกษาอื่นหรือเลือกแก้ไขรายการที่มีอยู่');
        $this->stage('write-curriculum');
        if($id===null){
            $this->execute('INSERT INTO Curriculum (Curriculum_name,Notes,IsActive) VALUES (?,?,0)',[$name,$notes]);
            $id=(int)$this->db->lastInsertId();
        } else $this->execute('UPDATE Curriculum SET Curriculum_name=?,Notes=? WHERE Curriculum_id=?',[$name,$notes,$id]);
        $new=$this->curriculum($id);
        $this->audit('Curriculum',$id,$old===null?'CREATE':'UPDATE',$old,$new??[]);
        return ['message'=>'บันทึกหลักสูตรสถานศึกษาเป็นร่างเรียบร้อย','id'=>$id];
    }
    private function changeCurriculumStatus(array $input):array {
        $id=Security::positive($input['curriculum_id']??null,'curriculum_id','หลักสูตรสถานศึกษา');
        $active=$this->exactFlag($input['is_active']??null);
        if($active===1)$this->verifyReview($input);
        $this->stage('validate-curriculum-status');
        $old=$this->execute('SELECT Curriculum_id,Curriculum_name,Notes,IsActive FROM Curriculum WHERE Curriculum_id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
        if(!$old)throw new WebError(404,'ไม่พบหลักสูตรสถานศึกษา');
        if((int)$old['IsActive']===$active)throw new WebError(409,'หลักสูตรสถานศึกษามีสถานะนี้อยู่แล้ว ให้โหลดหน้าใหม่');
        // Deactivate only after linked active categories have been reviewed.
        if($active===0){
            $count=(int)$this->execute('SELECT COUNT(*) FROM CurriculumSection WHERE Curriculum_id=? AND IsActive=1',[$id])->fetchColumn();
            if($count)throw new WebError(409,'มีหมวดหลักสูตรสถานศึกษาเปิดใช้งานอยู่ '.number_format($count).' รายการ กรุณาตรวจและปิดใช้งานหมวดก่อน');
        }
        $this->stage('write-curriculum-status');
        $this->execute('UPDATE Curriculum SET IsActive=? WHERE Curriculum_id=?',[$active,$id]);
        $this->audit('Curriculum',$id,$active?'ACTIVATE':'DEACTIVATE',$old,$this->curriculum($id)??[]);
        return ['message'=>$active?'เปิดใช้งานหลักสูตรสถานศึกษาแล้ว':'ปิดใช้งานหลักสูตรสถานศึกษาแล้ว'];
    }
    private function writeSection(string $action,array $input):array {
        $id=$action==='update-section'?Security::positive($input['section_id']??null,'section_id','หมวดหลักสูตรสถานศึกษา'):null;
        $curriculumId=Security::positive($input['curriculum_id']??null,'curriculum_id','หลักสูตรสถานศึกษา');
        $parentId=$this->idOrNull($input['parent_section_id']??null,'parent_section_id','หมวดหลัก');
        $name=Security::text($input['section_name']??null,255,true,'section_name','ชื่อหมวดหลักสูตรสถานศึกษา');
        $code=$this->optionalText($input['section_code']??null,100,'section_code','รหัสหมวด');
        if($code===null && ($this->tableColumns('CurriculumSection')['SectionCode']??'NO')!=='YES')
            throw new FieldError('section_code','รหัสหมวดจำเป็นตามโครงสร้างฐานข้อมูล','กรอกรหัสหมวดจากเอกสารหลักสูตรสถานศึกษาก่อนบันทึก');
        $sortRaw=$input['sort_order']??'0';
        if(!is_scalar($sortRaw)||!preg_match('/^[0-9]{1,7}$/D',(string)$sortRaw))
            throw new FieldError('sort_order','ลำดับไม่ถูกต้อง','กรอกจำนวนเต็มตั้งแต่ 0 ถึง 9999999');
        $sort=(int)$sortRaw;
        $this->stage('validate-section');
        $curriculum=$this->execute('SELECT Curriculum_id,IsActive FROM Curriculum WHERE Curriculum_id=? FOR UPDATE',[$curriculumId])->fetch(PDO::FETCH_ASSOC);
        if(!$curriculum)throw new FieldError('curriculum_id','ไม่พบหลักสูตรสถานศึกษาที่เลือก','เลือกหลักสูตรสถานศึกษาใหม่จากรายการ');
        $old=null;
        if($id!==null){
            $old=$this->execute('SELECT CurriculumSection_id,Curriculum_id,ParentSection_id,SectionCode,SectionName,SortOrder,IsActive FROM CurriculumSection WHERE CurriculumSection_id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
            if(!$old)throw new WebError(404,'ไม่พบหมวดหลักสูตรสถานศึกษา');
            if((int)$old['IsActive']===1)throw new WebError(409,'หมวดที่เปิดใช้งานต้องปิดใช้งานก่อนแก้ไข');
            if((int)$old['Curriculum_id']!==$curriculumId)throw new FieldError('curriculum_id','ไม่อนุญาตย้ายหมวดไปหลักสูตรสถานศึกษาอื่น','สร้างหมวดใหม่ในหลักสูตรสถานศึกษาปลายทางเพื่อรักษาความสัมพันธ์ของรายวิชา');
        }
        if($parentId!==null){
            if($id!==null && $parentId===$id)throw new FieldError('parent_section_id','หมวดไม่สามารถเป็นหมวดแม่ของตนเอง','เลือกหมวดหลักอื่น');
            $parent=$this->execute('SELECT CurriculumSection_id,Curriculum_id,ParentSection_id,IsActive FROM CurriculumSection WHERE CurriculumSection_id=?',[$parentId])->fetch(PDO::FETCH_ASSOC);
            if(!$parent||(int)$parent['Curriculum_id']!==$curriculumId)
                throw new FieldError('parent_section_id','หมวดหลักไม่ได้อยู่ในหลักสูตรสถานศึกษาเดียวกัน','เลือกหมวดหลักของหลักสูตรสถานศึกษานี้');
            // Prevent cross-tree cycles including a parent chain longer than two levels.
            $walk=$parent;
            for($i=0;$walk!==false && $walk!==null && $i<1000;$i++){
                if($id!==null && (int)$walk['CurriculumSection_id']===$id)
                    throw new FieldError('parent_section_id','หมวดหลักทำให้ลำดับเป็นวงวน','เลือกหมวดหลักที่ไม่ใช่หมวดย่อยของหมวดนี้');
                if($walk['ParentSection_id']===null)break;
                $walk=$this->execute('SELECT CurriculumSection_id,ParentSection_id FROM CurriculumSection WHERE CurriculumSection_id=?',[$walk['ParentSection_id']])->fetch(PDO::FETCH_ASSOC);
            }
            if($i>=1000)throw new WebError(409,'ตรวจพบโครงสร้างหมวดซับซ้อนผิดปกติ');
        }
        $sameName=$this->execute('SELECT CurriculumSection_id FROM CurriculumSection WHERE Curriculum_id=? AND SectionName=? AND CurriculumSection_id<>? AND ParentSection_id <=> ? LIMIT 1',[$curriculumId,$name,$id??0,$parentId])->fetchColumn();
        if($sameName)throw new FieldError('section_name','ชื่อหมวดซ้ำภายใต้หมวดหลักเดียวกัน','เปิดรายการหมวดที่มีอยู่แล้วเพื่อแก้ไข แทนการสร้างซ้ำ');
        if($code!==null){
            $duplicate=$this->execute('SELECT CurriculumSection_id FROM CurriculumSection WHERE Curriculum_id=? AND SectionCode=? AND CurriculumSection_id<>? LIMIT 1',[$curriculumId,$code,$id??0])->fetchColumn();
            if($duplicate)throw new FieldError('section_code','รหัสหมวดซ้ำในหลักสูตรสถานศึกษาเดียวกัน','ตรวจชื่อและรหัสหมวดของหลักสูตรสถานศึกษานี้ก่อนบันทึก');
        }
        $this->stage('write-section');
        if($id===null){
            $this->execute('INSERT INTO CurriculumSection (Curriculum_id,ParentSection_id,SectionCode,SectionName,SortOrder,IsActive) VALUES (?,?,?,?,?,0)',[$curriculumId,$parentId,$code,$name,$sort]);
            $id=(int)$this->db->lastInsertId();
        } else {
            $this->execute('UPDATE CurriculumSection SET ParentSection_id=?,SectionCode=?,SectionName=?,SortOrder=? WHERE CurriculumSection_id=?',[$parentId,$code,$name,$sort,$id]);
        }
        $this->audit('CurriculumSection',$id,$old===null?'CREATE':'UPDATE',$old,$this->section($id)??[]);
        return ['message'=>'บันทึกหมวดหลักสูตรสถานศึกษาเป็นร่างแล้ว','id'=>$id];
    }
    private function changeSectionStatus(array $input):array {
        $id=Security::positive($input['section_id']??null,'section_id','หมวดหลักสูตรสถานศึกษา');
        $active=$this->exactFlag($input['is_active']??null);
        if($active===1)$this->verifyReview($input);
        $this->stage('validate-section-status');
        $old=$this->execute('SELECT CurriculumSection_id,Curriculum_id,ParentSection_id,SectionCode,SectionName,SortOrder,IsActive FROM CurriculumSection WHERE CurriculumSection_id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
        if(!$old)throw new WebError(404,'ไม่พบหมวดหลักสูตรสถานศึกษา');
        if((int)$old['IsActive']===$active)throw new WebError(409,'หมวดมีสถานะนี้อยู่แล้ว');
        if($active===1){
            $curriculum=$this->execute('SELECT IsActive FROM Curriculum WHERE Curriculum_id=?',[$old['Curriculum_id']])->fetchColumn();
            if((int)$curriculum!==1)throw new WebError(409,'ยังไม่เปิดใช้งานหลักสูตรสถานศึกษาต้นทาง กรุณาตรวจและเปิดใช้งานหลักสูตรสถานศึกษาก่อน');
            if($old['ParentSection_id']!==null){
                $parent=$this->execute('SELECT IsActive FROM CurriculumSection WHERE CurriculumSection_id=?',[$old['ParentSection_id']])->fetchColumn();
                if((int)$parent!==1)throw new WebError(409,'หมวดหลักยังไม่เปิดใช้งาน กรุณาตรวจหมวดหลักก่อน');
            }
        }else{
            $children=(int)$this->execute('SELECT COUNT(*) FROM CurriculumSection WHERE ParentSection_id=? AND IsActive=1',[$id])->fetchColumn();
            if($children)throw new WebError(409,'ยังมีหมวดย่อยที่เปิดใช้งานอยู่ กรุณาปิดใช้งานหมวดย่อยก่อน');
        }
        $this->stage('write-section-status');
        $this->execute('UPDATE CurriculumSection SET IsActive=? WHERE CurriculumSection_id=?',[$active,$id]);
        $this->audit('CurriculumSection',$id,$active?'ACTIVATE':'DEACTIVATE',$old,$this->section($id)??[]);
        return ['message'=>$active?'เปิดใช้งานหมวดหลักสูตรสถานศึกษาแล้ว':'ปิดใช้งานหมวดหลักสูตรสถานศึกษาแล้ว'];
    }
}
