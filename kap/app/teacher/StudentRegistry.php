<?php
declare(strict_types=1);
namespace Kap\Teacher;

use PDO;
use Kap\Admin\FieldError;
use Kap\Admin\Security;

/** Student registry writer. All mutations require SYSTEM_ADMIN, a valid session and CSRF in the API.
 * Never hard-delete a student or overwrite their historical classroom membership.
 */
final class StudentRegistry
{
    private string $stage='registry-init';
    public function __construct(private PDO $db, private array $actor) {}
    public function stage(): string { return $this->stage; }
    private function setStage(string $s): void {$this->stage=$s;}
    private function one(string $sql, array $values=[]): ?array {
        $q=$this->db->prepare($sql);$q->execute($values);return $q->fetch(PDO::FETCH_ASSOC)?:null;
    }
    private function execute(string $sql,array $values=[]): void {
        $q=$this->db->prepare($sql);$q->execute($values);
    }
    public function checkSchema(): array {
        $needed=[
            'Student'=>['Student_id','StudentCode','StudentPrefix','StudentFirstName','StudentLastName','IsActive'],
            'StudentClassEnrollment'=>['StudentClassEnrollment_id','Student_id','ClassRoom_id','Status','StartDate','EndDate'],
            'ClassRoom'=>['ClassRoom_id','AcademicYear_id','GradeLevel_id','RoomName','IsActive'],
            'GradeLevel'=>['GradeLevel_id','GradeName','IsActive'],
            'AcademicYear'=>['AcademicYear_id','AcademicYear_name'],
            'AuditLog'=>['EntityName','EntityId','ActionType','OldValuesJson','NewValuesJson','ActorUser_id']
        ];
        $q=$this->db->query("SELECT TABLE_NAME,COLUMN_NAME,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('Student','StudentClassEnrollment','ClassRoom','GradeLevel','AcademicYear','AuditLog')");
        $found=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r) $found[$r['TABLE_NAME']][$r['COLUMN_NAME']]=$r['IS_NULLABLE'];
        $missing=[];
        foreach($needed as $table=>$cols)foreach($cols as $col){if(!isset($found[$table][$col]))$missing[]="$table.$col";}
        if($missing)throw new WebError(409,'โครงสร้างทะเบียนนักเรียนยังไม่ครบ: '.implode(', ',$missing).' กรุณาตรวจฐานข้อมูลก่อน');
        return $found;
    }
    public function roomOptions(string $year='2569'): array {
        $q=$this->db->prepare('SELECT cr.ClassRoom_id,cr.RoomName,cr.GradeLevel_id,gl.GradeName,gl.SortOrder,cr.IsActive AS RoomActive,gl.IsActive AS GradeActive FROM ClassRoom cr JOIN GradeLevel gl ON gl.GradeLevel_id=cr.GradeLevel_id JOIN AcademicYear ay ON ay.AcademicYear_id=cr.AcademicYear_id WHERE ay.AcademicYear_name=? ORDER BY gl.SortOrder,cr.RoomName,cr.ClassRoom_id');
        $q->execute([$year]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function student(int $id): ?array {
        return $this->one('SELECT Student_id,StudentCode,StudentPrefix,StudentFirstName,StudentLastName,IsActive FROM Student WHERE Student_id=?',[$id]);
    }
    private function year(string $name): int {
        $row=$this->one('SELECT AcademicYear_id FROM AcademicYear WHERE AcademicYear_name=?',[$name]);
        if(!$row)throw new WebError(409,'ไม่พบปีการศึกษาที่กำหนด');
        return (int)$row['AcademicYear_id'];
    }
    private function room(int $roomId,int $yearId): array {
        $r=$this->one('SELECT cr.ClassRoom_id,cr.GradeLevel_id,cr.IsActive,gl.IsActive AS GradeActive FROM ClassRoom cr JOIN GradeLevel gl ON gl.GradeLevel_id=cr.GradeLevel_id WHERE cr.ClassRoom_id=? AND cr.AcademicYear_id=?',[$roomId,$yearId]);
        if(!$r)throw new FieldError('classroom_id','ห้องเรียนไม่อยู่ในปีการศึกษาที่เลือก','เลือกระดับชั้นก่อน แล้วเลือกห้องที่อยู่ในปีการศึกษานั้น');
        if((int)$r['IsActive']!==1 || (int)$r['GradeActive']!==1)throw new FieldError('classroom_id','ห้องเรียนหรือระดับชั้นถูกปิดใช้งาน','เลือกห้องเรียนที่เปิดใช้งานก่อนบันทึก');
        return $r;
    }
    private static function date(mixed $value,string $field): string {
        if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value))throw new FieldError($field,'วันที่ไม่ถูกต้อง','กรอกวันที่รูปแบบ YYYY-MM-DD');
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if(!$date||$date->format('Y-m-d')!==$value)throw new FieldError($field,'วันที่ไม่มีอยู่จริง','ตรวจวัน เดือน และปี แล้วลองใหม่');
        return $value;
    }
    private function profile(array $input): array {
        $code=Security::text($input['student_code']??'',50,true,'student_code','รหัสนักเรียน');
        $prefix=Security::text($input['student_prefix']??'',40,false,'student_prefix','คำนำหน้า');
        $first=Security::text($input['first_name']??'',255,true,'first_name','ชื่อ');
        $last=Security::text($input['last_name']??'',255,true,'last_name','นามสกุล');
        return ['StudentCode'=>$code,'StudentPrefix'=>$prefix,'StudentFirstName'=>$first,'StudentLastName'=>$last];
    }
    private function audit(int $id,string $action,array $old,array $new): void {
        $this->setStage('audit-student');
        $this->execute('INSERT INTO AuditLog (EntityName,EntityId,ActionType,OldValuesJson,NewValuesJson,ActorUser_id,ChangedAt) VALUES (?,?,?,?,?,?,NOW())',
            ['Student',(string)$id,$action,json_encode($old?:new \stdClass(),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($new?:new \stdClass(),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),(int)$this->actor['SystemUser_id']]);
    }
    private function ensureUniqueCode(string $code,int $except=0): void {
        if($this->one('SELECT Student_id FROM Student WHERE StudentCode=? AND Student_id<>? LIMIT 1',[$code,$except]))
            throw new FieldError('student_code','รหัสนักเรียนนี้มีอยู่แล้ว','ค้นหานักเรียนตามรหัสและใช้ปุ่มแก้ไขรายการเดิม ไม่เพิ่มซ้ำ',409);
    }
    public function save(string $action,array $in): array {
        if(!in_array('SYSTEM_ADMIN',(array)($this->actor['Roles']??[]),true))throw new WebError(403,'เฉพาะ SYSTEM_ADMIN จึงแก้ไขทะเบียนนักเรียนได้');
        if(!in_array($action,['create-student','update-student','move-student','set-student-active'],true))throw new WebError(422,'คำสั่งทะเบียนนักเรียนไม่ถูกต้อง');
        $schema=$this->checkSchema();
        $yearId=$this->year('2569');
        $this->setStage('registry-transaction');
        $this->db->beginTransaction();
        try {
            if($action==='create-student'){
                $this->setStage('create-student');
                $profile=$this->profile($in);
                $this->ensureUniqueCode($profile['StudentCode']);
                $roomId=Security::positive($in['classroom_id']??null,'classroom_id','ห้องเรียน');
                $room=$this->room($roomId,$yearId);
                $gradeId=Security::positive($in['grade_level_id']??null,'grade_level_id','ระดับชั้น');
                if((int)$room['GradeLevel_id']!==$gradeId)throw new FieldError('classroom_id','ห้องเรียนไม่ตรงกับระดับชั้นที่เลือก','เลือกระดับชั้นก่อน แล้วเลือกห้องในชั้นนั้น');
                $start=self::date($in['start_date']??null,'start_date');
                if(isset($schema['Student']['StudentStatus'])){
                    $this->execute('INSERT INTO Student (StudentCode,StudentPrefix,StudentFirstName,StudentLastName,IsActive,StudentStatus) VALUES (?,?,?,?,1,?)',
                        [$profile['StudentCode'],$profile['StudentPrefix'],$profile['StudentFirstName'],$profile['StudentLastName'],'ENROLLED']);
                }else{
                    $this->execute('INSERT INTO Student (StudentCode,StudentPrefix,StudentFirstName,StudentLastName,IsActive) VALUES (?,?,?,?,1)',
                        [$profile['StudentCode'],$profile['StudentPrefix'],$profile['StudentFirstName'],$profile['StudentLastName']]);
                }
                $id=(int)$this->db->lastInsertId();
                $this->setStage('create-room-membership');
                $this->execute('INSERT INTO StudentClassEnrollment (Student_id,ClassRoom_id,Status,StartDate,EndDate) VALUES (?,?,?, ?,NULL)',[$id,$roomId,'ACTIVE',$start]);
                $this->audit($id,'CREATE',[],['year'=>'2569','room_id'=>$roomId,'start_date'=>$start,'fields'=>array_keys($profile)]);
                $message='เพิ่มนักเรียนและทะเบียนห้องสำเร็จ';
            }else{
                $id=Security::positive($in['student_id']??null,'student_id','นักเรียน');
                $this->setStage('lock-student');
                $old=$this->one('SELECT Student_id,StudentCode,StudentPrefix,StudentFirstName,StudentLastName,IsActive FROM Student WHERE Student_id=? FOR UPDATE',[$id]);
                if(!$old)throw new WebError(404,'ไม่พบข้อมูลนักเรียน กรุณาโหลดหน้าใหม่');
                if($action==='update-student'){
                    $profile=$this->profile($in);
                    $this->ensureUniqueCode($profile['StudentCode'],$id);
                    $this->setStage('update-student');
                    $this->execute('UPDATE Student SET StudentCode=?,StudentPrefix=?,StudentFirstName=?,StudentLastName=? WHERE Student_id=?',array_merge(array_values($profile),[$id]));
                    // Avoid duplicating student personal data in AuditLog.
                    $changes=[];
                    foreach($profile as $field=>$value)if((string)($old[$field]??'')!==$value)$changes[]=$field;
                    $this->audit($id,'UPDATE',[],['changed_fields'=>$changes]);
                    $message='แก้ไขข้อมูลนักเรียนสำเร็จ';
                }elseif($action==='set-student-active'){
                    if(!in_array((string)($in['is_active']??''),['0','1'],true))throw new FieldError('is_active','สถานะไม่ถูกต้อง','เลือกเปิดหรือปิดใช้งานใหม่');
                    $new=(int)$in['is_active'];
                    if((int)$old['IsActive']===$new)throw new WebError(409,'สถานะนักเรียนไม่เปลี่ยนแปลง กรุณาโหลดข้อมูลใหม่');
                    $this->setStage('set-student-active');
                    $this->execute('UPDATE Student SET IsActive=? WHERE Student_id=?',[$new,$id]);
                    $this->audit($id,'UPDATE',['IsActive'=>(int)$old['IsActive']],['IsActive'=>$new]);
                    $message=$new?'เปิดใช้งานข้อมูลนักเรียนแล้ว':'ปิดใช้งานข้อมูลนักเรียนแล้ว โดยไม่ลบประวัติ';
                }else{
                    $roomId=Security::positive($in['classroom_id']??null,'classroom_id','ห้องเรียนใหม่');
                    $room=$this->room($roomId,$yearId);
                    $gradeId=Security::positive($in['grade_level_id']??null,'grade_level_id','ระดับชั้น');
                    if((int)$room['GradeLevel_id']!==$gradeId)throw new FieldError('classroom_id','ห้องเรียนไม่ตรงกับระดับชั้นที่เลือก','เลือกห้องจากระดับชั้นที่ระบุ');
                    $start=self::date($in['start_date']??null,'start_date');
                    $this->setStage('lock-room-membership');
                    $previous=$this->one('SELECT sce.StudentClassEnrollment_id,sce.ClassRoom_id,sce.StartDate,sce.EndDate,sce.Status,cr.GradeLevel_id FROM StudentClassEnrollment sce JOIN ClassRoom cr ON cr.ClassRoom_id=sce.ClassRoom_id WHERE sce.Student_id=? AND cr.AcademicYear_id=? ORDER BY sce.StudentClassEnrollment_id DESC LIMIT 1 FOR UPDATE',[$id,$yearId]);
                    if(!$previous)throw new WebError(409,'นักเรียนยังไม่มีทะเบียนห้องในปี 2569 ต้องตรวจทะเบียนเดิมก่อนย้ายห้อง');
                    if((int)$previous['ClassRoom_id']===$roomId)throw new FieldError('classroom_id','นักเรียนอยู่ห้องนี้อยู่แล้ว','เลือกห้องปลายทางที่ต่างจากเดิม');
                    if((int)$room['GradeLevel_id']!==(int)$previous['GradeLevel_id'])throw new FieldError('classroom_id','ห้องใหม่อยู่คนละระดับชั้น','การเปลี่ยนระดับชั้นต้องใช้ขั้นตอนเลื่อนชั้นที่ตรวจหลักสูตรสถานศึกษาก่อน ไม่ใช่การย้ายห้อง');
                    if($start<=(string)$previous['StartDate'])throw new FieldError('start_date','วันที่ย้ายห้องไม่ต่อจากวันเข้าห้องเดิม','ระบุวันย้ายหลังวันที่เข้าห้องเดิมอย่างน้อยหนึ่งวัน');
                    $lastEnd=$previous['EndDate']??null;
                    $end=(new \DateTimeImmutable($start))->modify('-1 day')->format('Y-m-d');
                    if($lastEnd===null || (string)$lastEnd>=$start){
                        $this->setStage('close-old-membership');
                        $this->execute('UPDATE StudentClassEnrollment SET EndDate=?,Status=? WHERE StudentClassEnrollment_id=?',[$end,'TRANSFERRED',(int)$previous['StudentClassEnrollment_id']]);
                    }
                    $this->setStage('create-new-membership');
                    $this->execute('INSERT INTO StudentClassEnrollment (Student_id,ClassRoom_id,Status,StartDate,EndDate) VALUES (?,?,?, ?,NULL)',[$id,$roomId,'ACTIVE',$start]);
                    $this->audit($id,'UPDATE',['room_id'=>(int)$previous['ClassRoom_id']],['room_id'=>$roomId,'move_date'=>$start,'year'=>'2569']);
                    $message='ย้ายห้องเรียนสำเร็จและเก็บประวัติเดิมแล้ว';
                }
            }
            $this->db->commit();return ['id'=>$id,'message'=>$message];
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
