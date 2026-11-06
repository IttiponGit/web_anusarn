<?php
declare(strict_types=1);
namespace Kap\Admin;

use Kap\Teacher\WebError;
use PDO;

final class Actions
{
    /** Fixed, non-sensitive stage labels for production error correlation. */
    private string $currentStage = 'start';
    public function __construct(private PDO $db, private array $actor) {}
    public function currentStage(): string { return $this->currentStage; }
    private function stage(string $stage): void { $this->currentStage = $stage; }

    public function run(string $action, array $input): array
    {
        if (!in_array($action, ['create-user','update-user','update-teacher','reset-password','assign-teacher','disable-assignment'], true)) {
            throw new WebError(422,'ไม่รู้จักคำสั่งที่ส่งมา');
        }
        if (in_array($action,['create-user','update-user','update-teacher','reset-password'],true)
            && !in_array('SYSTEM_ADMIN', $this->actor['Roles'],true)) throw new WebError(403,'เฉพาะ SYSTEM_ADMIN เท่านั้นที่แก้ไขผู้ใช้ได้');
        if (in_array($action,['assign-teacher','disable-assignment'],true)
            && !array_intersect($this->actor['Roles'], ['SYSTEM_ADMIN','ACADEMIC_ADMIN'])) throw new WebError(403,'ไม่มีสิทธิ์มอบหมายครู');
        $this->stage('start-transaction');
        $this->db->beginTransaction();
        $this->stage('execute-action');
        try {
            $result=match($action) {
                'create-user' => $this->createUser($input),
                'update-user' => $this->updateUser($input),
                'update-teacher' => $this->updateTeacher($input),
                'reset-password' => $this->resetPassword($input),
                'assign-teacher' => $this->assignTeacher($input),
                'disable-assignment' => $this->disableAssignment($input),
            };
            $this->stage('commit-transaction');
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            if($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function one(string $sql,array $params=[]): ?array
    {
        $s=$this->db->prepare($sql);$s->execute($params);return $s->fetch(PDO::FETCH_ASSOC)?:null;
    }
    private function query(string $sql,array $params=[]): void
    {
        $s=$this->db->prepare($sql);$s->execute($params);
    }
    private function requireActiveRoleIds(array $input): array
    {
        $ids=$input['role_ids']??null;
        if(!is_array($ids) || !$ids) throw new FieldError('role_ids[]','ยังไม่ได้เลือกบทบาทผู้ใช้งาน','ทำเครื่องหมายหน้าบทบาทอย่างน้อย 1 รายการก่อนกดบันทึก');
        if(count($ids)>20) throw new FieldError('role_ids[]','เลือกบทบาทผู้ใช้งานมากเกินกว่าที่ระบบรองรับ','ลดจำนวนบทบาทที่เลือกแล้วลองอีกครั้ง');
        $valid=[];
        foreach ($ids as $v) $valid[]=Security::positive($v, 'role_ids[]', 'บทบาทผู้ใช้งาน');
        $valid=array_values(array_unique($valid));
        if(!$valid) throw new FieldError('role_ids[]','ยังไม่ได้เลือกบทบาทผู้ใช้งาน','ทำเครื่องหมายหน้าบทบาทอย่างน้อย 1 รายการ');
        $marks=implode(',',array_fill(0,count($valid),'?'));
        $s=$this->db->prepare("SELECT Role_id FROM Role WHERE IsActive=1 AND Role_id IN ($marks)");
        $s->execute($valid);
        $found=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));sort($found);sort($valid);
        if($valid!==$found)throw new FieldError('role_ids[]','บทบาทผู้ใช้งานที่เลือกไม่มีอยู่หรือถูกปิดใช้งาน','โหลดหน้าจัดการผู้ใช้งานใหม่ แล้วเลือกบทบาทที่เปิดใช้งาน');
        return $valid;
    }
    private function rolesForUser(int $id): array
    {
        $s=$this->db->prepare('SELECT r.RoleCode FROM SystemUserRole sur JOIN Role r ON r.Role_id=sur.Role_id WHERE sur.SystemUser_id=? AND sur.IsActive=1 AND r.IsActive=1');
        $s->execute([$id]);return $s->fetchAll(PDO::FETCH_COLUMN);
    }
    private function roleCodes(array $ids): array
    {
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $s=$this->db->prepare("SELECT RoleCode FROM Role WHERE Role_id IN ($marks)");$s->execute($ids);return $s->fetchAll(PDO::FETCH_COLUMN);
    }
    private function replaceRoles(int $userId, array $wanted): void
    {
        $s=$this->db->prepare('SELECT SystemUserRole_id,Role_id,IsActive FROM SystemUserRole WHERE SystemUser_id=? FOR UPDATE');
        $s->execute([$userId]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
        $existing=[];
        foreach($rows as $r) $existing[(int)$r['Role_id']]=$r;
        foreach($existing as $roleId=>$r){
            $enabled=in_array($roleId,$wanted,true)?1:0;
            if ((int)$r['IsActive'] !== $enabled) $this->query('UPDATE SystemUserRole SET IsActive=? WHERE SystemUserRole_id=?',[$enabled,$r['SystemUserRole_id']]);
        }
        foreach($wanted as $roleId) if(!isset($existing[$roleId]))
            $this->query('INSERT INTO SystemUserRole (SystemUser_id,Role_id,IsActive) VALUES (?,?,1)',[$userId,$roleId]);
    }
    /** Resolve a learning area only for staff who actually teach. */
    private function learningAreaFromInput(mixed $value, bool $required): ?int
    {
        if ($value === null || $value === '') {
            if ($required) {
                throw new FieldError('learning_area_id', 'ครูประจำวิชาต้องเลือกกลุ่มสาระการเรียนรู้', 'เลือกกลุ่มสาระการเรียนรู้ที่เปิดใช้งานก่อนบันทึก หากเป็นผู้บริหารที่ไม่ได้สอน ให้ยกเลิกบทบาทครูประจำวิชา');
            }
            return null;
        }
        $area = Security::positive($value, 'learning_area_id', 'กลุ่มสาระการเรียนรู้');
        if (!$this->one('SELECT LearningArea_id FROM LearningArea WHERE LearningArea_id=? AND IsActive=1', [$area])) {
            throw new FieldError('learning_area_id', 'ไม่พบกลุ่มสาระการเรียนรู้ที่เลือก', 'โหลดหน้าใหม่แล้วเลือกกลุ่มสาระจากรายการที่เปิดใช้งาน หรือเว้นว่างสำหรับผู้บริหารที่ไม่ได้สอน');
        }
        return $area;
    }
    private function teacherIdFromInput(array $in, bool $requiresTeachingArea=false): ?string
    {
        $mode=(string)($in['teacher_mode']??'existing');
        if($mode==='none') return null;
        if($mode==='existing') {
            if(($in['teacher_citizen_id']??'')==='') throw new FieldError('teacher_citizen_id','ยังไม่ได้เลือกบุคลากรสำหรับเชื่อมบัญชี','เปิดรายการบุคลากร แล้วเลือกชื่อที่ต้องการเชื่อมกับบัญชีผู้ใช้งาน');
            $citizen=CitizenIdentity::validate($in['teacher_citizen_id']??null, 'teacher_citizen_id');
            $row=$this->one('SELECT Teacher_id,LearningArea_id FROM Teacher WHERE Teacher_id=? AND IsActive=1 FOR UPDATE',[$citizen]);
            if(!$row)throw new FieldError('teacher_citizen_id','ไม่พบบุคลากรที่เลือกหรือข้อมูลถูกปิดใช้งาน','กลับไปที่ช่องรายชื่อบุคลากร แล้วเลือกบุคลากรที่เปิดใช้งาน');
            if($requiresTeachingArea && empty($row['LearningArea_id'])) throw new FieldError('teacher_citizen_id','บุคลากรที่เลือกยังไม่ได้กำหนดกลุ่มสาระสำหรับครูประจำวิชา','แก้ไขข้อมูลบุคลากรให้มีกลุ่มสาระการเรียนรู้ก่อนเลือกบทบาทครูประจำวิชา');
            return (string)$row['Teacher_id'];
        }
        if($mode==='new') {
            $citizen=CitizenIdentity::validate($in['teacher_id']??null, 'teacher_id');
            if($this->one('SELECT Teacher_id FROM Teacher WHERE Teacher_id=?',[$citizen])) throw new FieldError('teacher_id','เลขบัตรประชาชนนี้มีอยู่ในทะเบียนบุคลากรแล้ว','เปลี่ยนวิธีเชื่อมข้อมูลเป็น “เลือกบุคลากรที่มีอยู่แล้ว” แทนการเพิ่มใหม่',409);
            $prefix=Security::text($in['prefix']??'',50,false,'prefix','คำนำหน้า');
            $first=Security::text($in['first_name']??'',255,true,'first_name','ชื่อจริง');
            $last=Security::text($in['last_name']??'',255,true,'last_name','นามสกุล');
            $area=$this->learningAreaFromInput($in['learning_area_id']??'', $requiresTeachingArea);
            if(!(new Directory($this->db))->hasTeacherPrefix()) throw new WebError(409,'ต้องปรับโครงสร้าง TeacherPrefix ตามคู่มือก่อนเพิ่มข้อมูลคำนำหน้าแยกฟิลด์');
            $this->query('INSERT INTO Teacher (Teacher_id,TeacherPrefix,Teacher_name,Teacher_Lastname,LearningArea_id,IsActive) VALUES (?,?,?,?,?,1)',[$citizen,$prefix,$first,$last,$area]);
            return $citizen;
        }
        throw new FieldError('teacher_mode','วิธีเชื่อมข้อมูลบุคลากรไม่ถูกต้อง','เลือกวิธีเชื่อมข้อมูลบุคลากรใหม่อีกครั้ง');
    }
    private function preventLinkedTeacher(?string $id, ?int $except=null): void
    {
        if($id===null)return;
        $row=$this->one('SELECT SystemUser_id FROM SystemUser WHERE Teacher_id=?'.($except!==null?' AND SystemUser_id<>?':'').' LIMIT 1', $except===null?[$id]:[$id,$except]);
        if($row)throw new FieldError('teacher_citizen_id','บุคลากรที่เลือกมีบัญชีผู้ใช้งานอยู่แล้ว','เปิดตารางบัญชีผู้ใช้งานแล้วใช้ปุ่มแก้ไขของบัญชีเดิม แทนการสร้างบัญชีซ้ำ',409);
    }
    private static function redactIdentity(array $row): array
    {
        foreach ($row as $key => $value) {
            if (in_array(strtolower((string)$key), ['teacher_id','citizenid','teacher_citizen_id'], true)) {
                $row[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $row[$key] = self::redactIdentity($value);
            }
        }
        return $row;
    }
    private function audit(string $entity, int $entityId, string $type, ?array $old, array $new): void
    {
        // A newly created entity (or a password reset) has no prior snapshot.
        // Save the valid JSON object {} rather than SQL NULL: some deployed
        // AuditLog schemas declare OldValuesJson NOT NULL (MariaDB 1048).
        // Keep the audit insert mandatory inside the caller's transaction.
        $oldJson = $old === null
            ? '{}'
            : json_encode(self::redactIdentity($old), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $newJson = json_encode(self::redactIdentity($new), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->query('INSERT INTO AuditLog (EntityName,EntityId,ActionType,OldValuesJson,NewValuesJson,ActorUser_id,ChangedAt) VALUES (?,?,?,?,?,?,NOW())',
            [$entity,(string)$entityId,$type,$oldJson,$newJson,$this->actor['SystemUser_id']]);
    }
    private function createUser(array $in): array
    {
        $this->stage('validate-new-account');
        $name=Security::username($in['username']??null);
        $pass=Security::password($in['password']??null);
        $ids=$this->requireActiveRoleIds($in);
        if($this->one('SELECT SystemUser_id FROM SystemUser WHERE Username=?',[$name])) throw new FieldError('username','Username นี้มีผู้ใช้งานแล้ว','เปลี่ยน Username ให้ไม่ซ้ำ หรือแก้ไขบัญชีผู้ใช้งานเดิม',409);
        $this->stage('find-teacher');
        $isTeachingRole=in_array('SUBJECT_TEACHER', $this->roleCodes($ids), true);
        $teacherId=$this->teacherIdFromInput($in, $isTeachingRole);
        $this->preventLinkedTeacher($teacherId);
        if(in_array('SUBJECT_TEACHER',$this->roleCodes($ids),true) && $teacherId===null)throw new FieldError('teacher_mode','บัญชีที่มีบทบาทครูประจำวิชาต้องเชื่อมกับข้อมูลบุคลากร','เลือกบุคลากรที่มีอยู่แล้ว หรือเพิ่มข้อมูลบุคลากรใหม่ให้ครบก่อนบันทึก');
        $this->stage('insert-system-user');
        $this->query('INSERT INTO SystemUser (Username,Teacher_id,PasswordHash,IsActive,CreatedAt) VALUES (?,?,?,?,NOW())',
           [$name,$teacherId,password_hash($pass,PASSWORD_DEFAULT),1]);
        $id=(int)$this->db->lastInsertId();
        $this->stage('insert-user-roles');
        $this->replaceRoles($id,$ids);
        $this->stage('insert-audit-log');
        $this->audit('SystemUser',$id,'CREATE',null,['Username'=>$name,'Teacher_id'=>$teacherId,'RoleIds'=>$ids,'IsActive'=>1]);
        return ['id'=>$id,'message'=>'เพิ่มผู้ใช้งานสำเร็จ'];
    }
    private function updateUser(array $in): array
    {
        $id=Security::positive($in['user_id']??'','user_id','บัญชีผู้ใช้งาน');
        $row=$this->one('SELECT SystemUser_id,Username,Teacher_id,IsActive FROM SystemUser WHERE SystemUser_id=? FOR UPDATE',[$id]);
        if(!$row)throw new WebError(404,'ไม่พบบัญชี');
        $name=Security::username($in['username']??null);
        $ids=$this->requireActiveRoleIds($in);
        $active=isset($in['is_active']) && (string)$in['is_active']==='1' ? 1:0;
        $collision=$this->one('SELECT SystemUser_id FROM SystemUser WHERE Username=? AND SystemUser_id<>?',[$name,$id]);
        if($collision)throw new FieldError('username','Username นี้มีผู้ใช้งานแล้ว','เปลี่ยน Username ให้ไม่ซ้ำ หรือใช้หน้าแก้ไขบัญชีเดิม',409);
        // Existing user can change linking, but never create new teacher on an edit path.
        if(($in['teacher_mode']??'existing')==='new')throw new WebError(422,'เพิ่มครูใหม่ผ่านหน้าเพิ่มบัญชีเท่านั้น');
        $newCodes=$this->roleCodes($ids);
        $teacherId=$this->teacherIdFromInput($in, in_array('SUBJECT_TEACHER', $newCodes, true));
        $this->preventLinkedTeacher($teacherId,$id);
        if(in_array('SUBJECT_TEACHER',$newCodes,true) && $teacherId===null)throw new FieldError('teacher_mode','บัญชีครูประจำวิชาต้องเชื่อมกับข้อมูลบุคลากร','เลือกข้อมูลบุคลากรที่มีอยู่แล้วก่อนบันทึก');
        $oldCodes=$this->rolesForUser($id);
        if($id===(int)$this->actor['SystemUser_id'] && (!$active||!in_array('SYSTEM_ADMIN',$newCodes,true)))
            throw new WebError(409,'ห้ามปิดบัญชีหรือลดสิทธิ์ SYSTEM_ADMIN ของตัวเอง');
        if((int)$row['IsActive']===1 && in_array('SYSTEM_ADMIN',$oldCodes,true) && (!$active||!in_array('SYSTEM_ADMIN',$newCodes,true))) {
            // Serialize removal/disable of admin roles, preventing concurrent last-admin changes.
            $this->db->query("SELECT Role_id FROM Role WHERE RoleCode='SYSTEM_ADMIN' FOR UPDATE")->fetchColumn();
            $s=$this->db->query("SELECT COUNT(DISTINCT u.SystemUser_id) FROM SystemUser u JOIN SystemUserRole sur ON sur.SystemUser_id=u.SystemUser_id JOIN Role r ON r.Role_id=sur.Role_id WHERE u.IsActive=1 AND sur.IsActive=1 AND r.IsActive=1 AND r.RoleCode='SYSTEM_ADMIN'");
            if((int)$s->fetchColumn()<2)throw new WebError(409,'ห้ามปิด SYSTEM_ADMIN คนสุดท้าย');
        }
        $this->query('UPDATE SystemUser SET Username=?,Teacher_id=?,IsActive=? WHERE SystemUser_id=?',[$name,$teacherId,$active,$id]);
        $this->replaceRoles($id,$ids);
        $this->audit('SystemUser',$id,'UPDATE',['Username'=>$row['Username'],'Teacher_id'=>$row['Teacher_id'],'IsActive'=>$row['IsActive'],'Roles'=>$oldCodes],['Username'=>$name,'Teacher_id'=>$teacherId,'IsActive'=>$active,'Roles'=>$newCodes]);
        return ['id'=>$id,'message'=>'แก้ไขบัญชีและสิทธิ์สำเร็จ'];
    }
    private function updateTeacher(array $in): array
    {
        $citizen=CitizenIdentity::validate($in['teacher_citizen_id']??null, 'teacher_citizen_id');
        $old=$this->one('SELECT Teacher_id,Teacher_name,Teacher_Lastname,LearningArea_id,IsActive FROM Teacher WHERE Teacher_id=? FOR UPDATE',[$citizen]);
        $id=$old ? (string)$old['Teacher_id'] : null;
        if(!$old)throw new WebError(404,'ไม่พบข้อมูลบุคลากร');
        $prefix=Security::text($in['prefix']??'',50,false,'prefix','คำนำหน้า');
        $first=Security::text($in['first_name']??'',255,true,'first_name','ชื่อจริง');
        $last=Security::text($in['last_name']??'',255,true,'last_name','นามสกุล');
        // A linked SUBJECT_TEACHER must retain a learning area. Leadership-only
        // profiles may keep LearningArea_id = NULL (the schema permits NULL).
        $assignedTeachingRole=$this->one("SELECT u.SystemUser_id FROM SystemUser u JOIN SystemUserRole ur ON ur.SystemUser_id=u.SystemUser_id JOIN Role r ON r.Role_id=ur.Role_id WHERE u.Teacher_id=? AND u.IsActive=1 AND ur.IsActive=1 AND r.IsActive=1 AND r.RoleCode='SUBJECT_TEACHER' LIMIT 1",[$citizen]);
        $area=$this->learningAreaFromInput($in['learning_area_id']??'', $assignedTeachingRole!==null);
        if(!(new Directory($this->db))->hasTeacherPrefix())throw new WebError(409,'ต้องเพิ่ม TeacherPrefix ตามคู่มือติดตั้งก่อน');
        $this->query('UPDATE Teacher SET TeacherPrefix=?,Teacher_name=?,Teacher_Lastname=?,LearningArea_id=? WHERE Teacher_id=?',[$prefix,$first,$last,$area,$id]);
        $this->audit('Teacher',0,'UPDATE',$old,['TeacherPrefix'=>$prefix,'Teacher_name'=>$first,'Teacher_Lastname'=>$last,'LearningArea_id'=>$area]);
        return ['message'=>'แก้ไขข้อมูลบุคลากรเรียบร้อย'];
    }
    private function resetPassword(array $in): array
    {
        $id=Security::positive($in['user_id']??'','user_id','บัญชีผู้ใช้งาน');
        $pass=Security::password($in['password']??'');
        if(!$this->one('SELECT SystemUser_id FROM SystemUser WHERE SystemUser_id=? FOR UPDATE',[$id]))throw new WebError(404,'ไม่พบบัญชี');
        $this->query('UPDATE SystemUser SET PasswordHash=? WHERE SystemUser_id=?',[password_hash($pass,PASSWORD_DEFAULT),$id]);
        $this->audit('SystemUser',$id,'PASSWORD_RESET',null,['PasswordChanged'=>true]);
        return ['message'=>'รีเซ็ตรหัสผ่านสำเร็จ'];
    }
    private function assignTeacher(array $in): array
    {
        $classId=Security::positive($in['subject_class_id']??'','subject_class_id','กลุ่มเรียนรายวิชา');
        $citizen=CitizenIdentity::validate($in['teacher_citizen_id']??null, 'teacher_citizen_id');
        $teacher=$this->one('SELECT Teacher_id FROM Teacher WHERE Teacher_id=? AND IsActive=1 FOR UPDATE',[$citizen]);
        if(!$teacher) throw new FieldError('teacher_citizen_id','ไม่พบบุคลากรที่เลือกหรือข้อมูลถูกปิดใช้งาน','กลับไปที่ช่องรายชื่อบุคลากร แล้วเลือกบุคลากรที่เปิดใช้งาน');
        $teacherId=(string)$teacher['Teacher_id'];
        if(!$this->one('SELECT sc.SubjectClass_id FROM SubjectClass sc JOIN SubjectOffering so ON so.SubjectOffering_id=sc.SubjectOffering_id WHERE sc.SubjectClass_id=? AND sc.IsActive=1 AND so.IsActive=1 FOR UPDATE',[$classId]))throw new WebError(422,'ไม่พบกลุ่มเรียนเปิดสอน');
        $before=$this->one('SELECT SubjectTeacher_id,TeachingRole,IsActive FROM SubjectTeacher WHERE SubjectClass_id=? AND Teacher_id=? FOR UPDATE',[$classId,$teacherId]);
        $role=Security::text($in['teaching_role']??'',80);
        if($before) {
            $this->query('UPDATE SubjectTeacher SET TeachingRole=?,IsActive=1 WHERE SubjectTeacher_id=?',[$role,$before['SubjectTeacher_id']]);
            $id=(int)$before['SubjectTeacher_id'];$type='REASSIGN';
        } else {
            $this->query('INSERT INTO SubjectTeacher (SubjectClass_id,Teacher_id,TeachingRole,IsActive) VALUES (?,?,?,1)',[$classId,$teacherId,$role]);
            $id=(int)$this->db->lastInsertId();$type='ASSIGN';
        }
        $this->audit('SubjectTeacher',$id,$type,$before,['SubjectClass_id'=>$classId,'Teacher_id'=>$teacherId,'TeachingRole'=>$role,'IsActive'=>1]);
        return ['message'=>'บันทึกการมอบหมายครูสำเร็จ'];
    }
    private function disableAssignment(array $in): array
    {
        $id=Security::positive($in['assignment_id']??'','assignment_id','รายการมอบหมายรายวิชา');
        $pre=$this->one('SELECT SubjectClass_id FROM SubjectTeacher WHERE SubjectTeacher_id=?',[$id]);
        if(!$pre)throw new WebError(404,'ไม่พบข้อมูลการมอบหมาย');
        $this->one('SELECT SubjectClass_id FROM SubjectClass WHERE SubjectClass_id=? FOR UPDATE',[$pre['SubjectClass_id']]);
        $before=$this->one('SELECT st.SubjectTeacher_id,st.SubjectClass_id,st.Teacher_id,st.IsActive,sc.SetupStatus
           FROM SubjectTeacher st JOIN SubjectClass sc ON sc.SubjectClass_id=st.SubjectClass_id WHERE st.SubjectTeacher_id=? FOR UPDATE',[$id]);
        if(!$before)throw new WebError(404,'ไม่พบข้อมูลการมอบหมาย');
        if((int)$before['IsActive']!==1)throw new WebError(409,'การมอบหมายนี้ปิดอยู่แล้ว');
        if($before['SetupStatus']==='ACTIVE') {
            $s=$this->db->prepare('SELECT COUNT(*) FROM SubjectTeacher WHERE SubjectClass_id=? AND IsActive=1');
            $s->execute([$before['SubjectClass_id']]);
            if((int)$s->fetchColumn()<2)throw new WebError(409,'กลุ่มเรียน ACTIVE ต้องมีครูผู้สอนอย่างน้อยหนึ่งคน');
        }
        $this->query('UPDATE SubjectTeacher SET IsActive=0 WHERE SubjectTeacher_id=?',[$id]);
        $this->audit('SubjectTeacher',$id,'DISABLE',$before,['IsActive'=>0]);
        return ['message'=>'ปิดการมอบหมายเรียบร้อย โดยรักษาประวัติเดิม'];
    }
}
