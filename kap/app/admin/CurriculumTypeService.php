<?php
declare(strict_types=1);
namespace Kap\Admin;

use Kap\Teacher\WebError;
use PDO;

/** Independent opt-in course-type editor. Deliberately does not replace
 * CurriculumCatalog, CurriculumSubject, or other Phase 2.5 backend logic.
 */
final class CurriculumTypeService
{
    private string $stage='types-init';
    public function __construct(private PDO $db, private array $actor){}
    public function currentStage():string{return $this->stage;}
    private function stage(string $name):void{$this->stage=$name;}
    private function q(string $sql,array $parameters=[]):\PDOStatement{
        $q=$this->db->prepare($sql);$q->execute($parameters);return $q;
    }
    private function actorAllowed():void{
        if(!in_array('SYSTEM_ADMIN',$this->actor['Roles']??[],true))throw new WebError(403,'เฉพาะผู้ดูแลระบบเท่านั้นที่จัดการประเภทรายวิชาได้');
    }
    public function typeReferences():array{
        $rows=$this->db->query("SELECT cg.Curriculum_id,cg.CohortStartYearBE,cg.CohortEndYearBE,g.GradeCode
            FROM CurriculumGradeYear cg
            INNER JOIN GradeLevel g ON g.GradeLevel_id=cg.GradeLevel_id
            WHERE g.GradeCode IN ('P1','P2','P3','P4','P5','P6')
            ORDER BY cg.CurriculumGradeYear_id")->fetchAll(PDO::FETCH_ASSOC);
        $refs=[];
        foreach($rows as $row){
            $types=CurriculumTypeReference::primary((int)$row['CohortStartYearBE'],(int)$row['CohortEndYearBE'],(string)$row['GradeCode']);
            if($types)$refs[(int)$row['Curriculum_id']]=[
                'start'=>(int)$row['CohortStartYearBE'],
                'end'=>(int)$row['CohortEndYearBE'],
                'types'=>$types,
            ];
        }
        return $refs;
    }
    public function compare(int $curriculumId,array $expected):array{
        $rows=$this->q('SELECT CurriculumSection_id,SectionCode,SectionName,IsActive FROM CurriculumSection WHERE Curriculum_id=? AND ParentSection_id IS NULL',[$curriculumId])->fetchAll(PDO::FETCH_ASSOC);
        $out=[];
        foreach($expected as $code=>$name){
            $match=null;
            foreach($rows as $r)if((string)$r['SectionName']===$name){$match=$r;break;}
            if($match===null)foreach($rows as $r)if((string)$r['SectionCode']===$code){$match=$r;break;}
            $out[]=['code'=>$code,'name'=>$name,'found'=>$match,
                    'status'=>$match===null?'missing':((string)$match['SectionName']===$name?'exact':'needs_name_review')];
        }
        return $out;
    }
    private function audit(int $id,string $action,?array $before,array $after):void{
        $this->stage('insert-audit');
        $this->q('INSERT INTO AuditLog (EntityName,EntityId,ActionType,OldValuesJson,NewValuesJson,ActorUser_id,ChangedAt) VALUES (?,?,?,?,?,?,NOW())',[
            'CurriculumSection',(string)$id,$action,
            json_encode($before??new \stdClass(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
            json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
            (int)$this->actor['SystemUser_id'],
        ]);
    }
    private function getSection(int $id):?array{
        $r=$this->q('SELECT CurriculumSection_id,Curriculum_id,ParentSection_id,SectionCode,SectionName,SortOrder,IsActive FROM CurriculumSection WHERE CurriculumSection_id=?',[$id])->fetch(PDO::FETCH_ASSOC);
        return $r?:null;
    }
    private function positive(mixed $v,string $field,string $name):int{return Security::positive($v,$field,$name);}
    private function flag(mixed $value):int{
        if($value==='0'||$value===0)return 0;
        if($value==='1'||$value===1)return 1;
        throw new FieldError('is_active','สถานะไม่ถูกต้อง','เลือกสถานะผ่านปุ่มในตาราง');
    }
    private function reviewed(array $input):void{
        if(($input['review_confirm']??null)!=='yes')
            throw new FieldError('review_confirm','ยังไม่ได้ยืนยันตรวจเอกสารหลักสูตรสถานศึกษา','ทำเครื่องหมายตรวจแล้วหลังเทียบกับต้นฉบับ');
    }
    private function newCode(int $curriculumId):string{
        for($i=0;$i<8;$i++){
            $code='TYPE_'.$curriculumId.'_'.strtoupper(bin2hex(random_bytes(5)));
            if(!$this->q('SELECT 1 FROM CurriculumSection WHERE Curriculum_id=? AND SectionCode=? LIMIT 1',[$curriculumId,$code])->fetchColumn())return $code;
        }
        throw new WebError(409,'รหัสภายในซ้ำ กรุณาลองใหม่');
    }
    public function save(string $action,array $input):array{
        $this->actorAllowed();
        if(!in_array($action,['create-section','update-section','section-status','sync-source-types'],true))
            throw new WebError(422,'ไม่รู้จักคำสั่งจัดการประเภทรายวิชา');
        $this->stage('begin-transaction');$this->db->beginTransaction();
        try{
            $result=match($action){
                'create-section','update-section'=>$this->writeType($action,$input),
                'section-status'=>$this->setStatus($input),
                'sync-source-types'=>$this->syncTypes($input),
            };
            $this->stage('commit');$this->db->commit();return $result;
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    private function writeType(string $action,array $input):array{
        $id=$action==='update-section'?$this->positive($input['section_id']??null,'section_id','ประเภทรายวิชา'):null;
        $curriculumId=$this->positive($input['curriculum_id']??null,'curriculum_id','หลักสูตรสถานศึกษา');
        $name=Security::text($input['section_name']??null,255,true,'section_name','ชื่อประเภท');
        $sortRaw=$input['sort_order']??'0';
        if(!is_scalar($sortRaw)||!preg_match('/^[0-9]{1,7}$/D',(string)$sortRaw))
            throw new FieldError('sort_order','ลำดับไม่ถูกต้อง','กรอกตัวเลขจำนวนเต็มตั้งแต่ 0 ถึง 9999999');
        $sort=(int)$sortRaw;
        $this->stage('validate-type');
        if(!$this->q('SELECT 1 FROM Curriculum WHERE Curriculum_id=? FOR UPDATE',[$curriculumId])->fetchColumn())
            throw new FieldError('curriculum_id','ไม่พบหลักสูตรสถานศึกษา','กรุณาเลือกหลักสูตรสถานศึกษาจากรายการ');
        $old=null;$parentId=null;
        if($id!==null){
            $old=$this->q('SELECT CurriculumSection_id,Curriculum_id,ParentSection_id,SectionCode,SectionName,SortOrder,IsActive FROM CurriculumSection WHERE CurriculumSection_id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
            if(!$old)throw new WebError(404,'ไม่พบประเภทรายวิชาที่ต้องการแก้ไข');
            if((int)$old['Curriculum_id']!==$curriculumId)throw new FieldError('curriculum_id','ไม่สามารถย้ายประเภทข้ามหลักสูตรสถานศึกษา','สร้างรายการใหม่แทนการย้ายเพื่อรักษาความสัมพันธ์');
            if((int)$old['IsActive']===1)throw new WebError(409,'ต้องปิดใช้งานประเภทก่อนแก้ไข');
            $parentId=$old['ParentSection_id']===null?null:(int)$old['ParentSection_id'];
        }
        if($parentId===null){
            $refs=$this->typeReferences();
            if(isset($refs[$curriculumId])&&!in_array($name,$refs[$curriculumId]['types'],true) && ($old===null || $name!==(string)$old['SectionName']))
                throw new FieldError('section_name','ชื่อประเภทไม่ตรงกับ Google Sheets ต้นฉบับ','เลือกชื่อประเภทของรุ่นหลักสูตรสถานศึกษานี้ หรือใช้คำสั่งตรวจเทียบด้านบน');
        }
        $duplicate=$this->q('SELECT CurriculumSection_id FROM CurriculumSection WHERE Curriculum_id=? AND SectionName=? AND CurriculumSection_id<>? AND ParentSection_id <=> ? LIMIT 1',[$curriculumId,$name,$id??0,$parentId])->fetchColumn();
        if($duplicate)throw new FieldError('section_name','ชื่อประเภทซ้ำ','ใช้รายการประเภทเดิมที่มีอยู่');
        $this->stage('write-type');
        if($id===null){
            $ref=$this->typeReferences()[$curriculumId]['types']??[];
            $sourceCode=array_search($name,$ref,true);
            $code=$sourceCode!==false && !$this->q('SELECT 1 FROM CurriculumSection WHERE Curriculum_id=? AND SectionCode=? LIMIT 1',[$curriculumId,$sourceCode])->fetchColumn()
                ?(string)$sourceCode:$this->newCode($curriculumId);
            $this->q('INSERT INTO CurriculumSection (Curriculum_id,ParentSection_id,SectionCode,SectionName,SortOrder,IsActive) VALUES (?,NULL,?,?,?,0)',[$curriculumId,$code,$name,$sort]);
            $id=(int)$this->db->lastInsertId();
        }else{
            // SectionCode and ParentSection_id are immutable here.
            $this->q('UPDATE CurriculumSection SET SectionName=?,SortOrder=? WHERE CurriculumSection_id=?',[$name,$sort,$id]);
        }
        $this->audit($id,$old===null?'CREATE':'UPDATE',$old,$this->getSection($id)??[]);
        return ['message'=>'บันทึกประเภทรายวิชาเป็นฉบับร่างแล้ว','id'=>$id];
    }
    private function setStatus(array $input):array{
        $id=$this->positive($input['section_id']??null,'section_id','ประเภท');
        $active=$this->flag($input['is_active']??null);
        if($active===1)$this->reviewed($input);
        $old=$this->q('SELECT CurriculumSection_id,Curriculum_id,ParentSection_id,SectionCode,SectionName,SortOrder,IsActive FROM CurriculumSection WHERE CurriculumSection_id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
        if(!$old)throw new WebError(404,'ไม่พบประเภท');
        if((int)$old['IsActive']===$active)throw new WebError(409,'มีสถานะนี้อยู่แล้ว กรุณาโหลดหน้าใหม่');
        $curriculumId=(int)$old['Curriculum_id'];
        if($active===1){
            if($old['ParentSection_id']===null){
                $refs=$this->typeReferences();
                if(isset($refs[$curriculumId])&&!in_array((string)$old['SectionName'],$refs[$curriculumId]['types'],true))
                    throw new WebError(409,'ชื่อประเภทไม่ตรงต้นฉบับของหลักสูตรสถานศึกษา กรุณาตรวจเทียบก่อนเปิดใช้งาน');
            }
            if((int)$this->q('SELECT IsActive FROM Curriculum WHERE Curriculum_id=?',[$curriculumId])->fetchColumn()!==1)
                throw new WebError(409,'ต้องตรวจและเปิดใช้งานหลักสูตรสถานศึกษาก่อน');
            if($old['ParentSection_id']!==null && (int)$this->q('SELECT IsActive FROM CurriculumSection WHERE CurriculumSection_id=?',[$old['ParentSection_id']])->fetchColumn()!==1)
                throw new WebError(409,'ประเภทย่อยยังไม่มีประเภทแม่ที่เปิดใช้งาน');
        }else{
            $count=(int)$this->q('SELECT COUNT(*) FROM CurriculumSection WHERE ParentSection_id=? AND IsActive=1',[$id])->fetchColumn();
            if($count)throw new WebError(409,'มีรายการย่อยที่เปิดใช้งานอยู่ '.$count.' รายการ กรุณาปิดรายการย่อยก่อน');
        }
        $this->stage('write-type-status');
        $this->q('UPDATE CurriculumSection SET IsActive=? WHERE CurriculumSection_id=?',[$active,$id]);
        $this->audit($id,$active?'ACTIVATE':'DEACTIVATE',$old,$this->getSection($id)??[]);
        return ['message'=>$active?'เปิดใช้งานประเภทรายวิชาแล้ว':'ปิดใช้งานประเภทรายวิชาแล้ว'];
    }
    private function syncTypes(array $input):array{
        $curriculumId=$this->positive($input['curriculum_id']??null,'curriculum_id','หลักสูตรสถานศึกษา');
        $this->reviewed($input);
        $refs=$this->typeReferences();
        if(!isset($refs[$curriculumId]))throw new FieldError('curriculum_id','ไม่พบรายการประเภทอ้างอิงสำหรับหลักสูตรสถานศึกษานี้','ตรวจ CurriculumGradeYear และเลือกหลักสูตรสถานศึกษาประถมที่มีต้นฉบับยืนยันแล้ว');
        if(!$this->q('SELECT 1 FROM Curriculum WHERE Curriculum_id=? FOR UPDATE',[$curriculumId])->fetchColumn())throw new WebError(404,'ไม่พบหลักสูตรสถานศึกษา');
        $created=0;$renamed=0;$unchanged=0;$skipped=[];$sort=0;
        foreach($refs[$curriculumId]['types'] as $code=>$name){
            $sort+=10;
            $rows=$this->q('SELECT CurriculumSection_id,Curriculum_id,ParentSection_id,SectionCode,SectionName,SortOrder,IsActive FROM CurriculumSection WHERE Curriculum_id=? AND ParentSection_id IS NULL AND (SectionName=? OR SectionCode=?) FOR UPDATE',[$curriculumId,$name,$code])->fetchAll(PDO::FETCH_ASSOC);
            $exact=null;$byCode=null;
            foreach($rows as $r){if((string)$r['SectionName']===$name)$exact=$r;if((string)$r['SectionCode']===$code)$byCode=$r;}
            if($exact!==null){$unchanged++;continue;}
            if($byCode!==null){
                if((int)$byCode['IsActive']===1){$skipped[]=$name.' (ใช้งานอยู่)';continue;}
                $this->stage('normalize-type-name');
                $this->q('UPDATE CurriculumSection SET SectionName=? WHERE CurriculumSection_id=?',[$name,(int)$byCode['CurriculumSection_id']]);
                $this->audit((int)$byCode['CurriculumSection_id'],'SOURCE_NAME_NORMALIZE',$byCode,$this->getSection((int)$byCode['CurriculumSection_id'])??[]);
                $renamed++;continue;
            }
            $used=$this->q('SELECT 1 FROM CurriculumSection WHERE Curriculum_id=? AND SectionCode=? LIMIT 1',[$curriculumId,$code])->fetchColumn();
            $newCode=$used?$this->newCode($curriculumId):$code;
            $this->stage('create-source-type');
            $this->q('INSERT INTO CurriculumSection (Curriculum_id,ParentSection_id,SectionCode,SectionName,SortOrder,IsActive) VALUES (?,NULL,?,?,?,0)',[$curriculumId,$newCode,$name,$sort]);
            $id=(int)$this->db->lastInsertId();
            $this->audit($id,'SOURCE_TYPE_CREATE',null,$this->getSection($id)??[]);
            $created++;
        }
        return ['message'=>'ปรับประเภทรายวิชาตามต้นฉบับ: เพิ่ม '.$created.' · ปรับชื่อ '.$renamed.' · เดิมตรงแล้ว '.$unchanged.
                ($skipped?' · ข้ามรายการที่ต้องตรวจเอง: '.implode(', ',$skipped):''),
                'created'=>$created,'renamed'=>$renamed,'unchanged'=>$unchanged,'skipped'=>$skipped];
    }
}
