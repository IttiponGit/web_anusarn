<?php
declare(strict_types=1);
namespace KAP;

use PDO;
use InvalidArgumentException;
use RuntimeException;

/** Keeps master catalog, review and curriculum blueprints independent. */
final class SubjectAdminV2
{
    public static function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    private static function len(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s,'UTF-8') : (preg_match_all('/./us',$s) ?: 0); }
    public static function types(PDO $db): array { return $db->query('SELECT TypeofCourse_id id, TypeofCourse_name name FROM TypeofCourse WHERE IsActive=1 ORDER BY TypeofCourse_id')->fetchAll(); }
    public static function areas(PDO $db): array { return $db->query('SELECT LearningArea_id id, LearningArea_name name FROM LearningArea WHERE IsActive=1 ORDER BY LearningArea_id')->fetchAll(); }
    public static function list(PDO $db, string $q, int $limit = 60): array {
        $limit = min(100, max(1, $limit));
        $q = trim($q);
        if (self::len($q) > 100) { throw new InvalidArgumentException('คำค้นยาวเกิน 100 ตัวอักษร'); }
        $sql = 'SELECT s.Subject_id, s.Subject_code, s.Subject_name, s.SubjectKind, s.TypeofCourse_id, s.Learning_Area_id, s.IsActive, (EXISTS (SELECT 1 FROM CurriculumSubject cs WHERE cs.Subject_id=s.Subject_id) OR EXISTS (SELECT 1 FROM SubjectOffering so WHERE so.Subject_id=s.Subject_id)) AS IsLinked, '
             . "COALESCE(rv.ReviewStatus,'DRAFT') ReviewStatus FROM Subject s LEFT JOIN SubjectCatalogReview rv ON rv.Subject_id=s.Subject_id";
        $params=[];
        if ($q !== '') {
            $sql .= ' WHERE s.Subject_code LIKE ? OR s.Subject_name LIKE ?';
            $params = ['%'.$q.'%','%'.$q.'%'];
        }
        $sql .= ' ORDER BY s.Subject_id DESC LIMIT ' . $limit;
        $stmt = $db->prepare($sql); $stmt->execute($params);
        $data = $stmt->fetchAll();
        foreach ($data as &$row) { $row['fingerprint'] = self::fingerprint($row); }
        unset($row);
        return $data;
    }
    public static function find(PDO $db, int $id, bool $lock=false): ?array {
        $sql = 'SELECT s.Subject_id,s.Subject_code,s.Subject_name,s.SubjectKind,s.TypeofCourse_id,s.Learning_Area_id,s.IsActive,'
             . "COALESCE(r.ReviewStatus,'DRAFT') ReviewStatus FROM Subject s LEFT JOIN SubjectCatalogReview r ON r.Subject_id=s.Subject_id WHERE s.Subject_id=?";
        if ($lock) { $sql .= ' FOR UPDATE'; }
        $stmt=$db->prepare($sql); $stmt->execute([$id]); return $stmt->fetch() ?: null;
    }
    private static function fingerprint(array $r): string {
        $fields=[];
        foreach (['Subject_code','Subject_name','SubjectKind','TypeofCourse_id','Learning_Area_id','IsActive','ReviewStatus'] as $field) {
            $fields[] = $r[$field] === null ? null : (string)$r[$field];
        }
        return hash('sha256', json_encode($fields, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    private static function id(mixed $value, string $name, bool $nullable=false): ?int {
        if ($nullable && ($value === null || $value === '')) return null;
        $str = (string)$value;
        if (!ctype_digit($str) || (int)$str <= 0) throw new InvalidArgumentException('ข้อมูล ' . $name . ' ไม่ถูกต้อง');
        return (int)$str;
    }
    private static function checkSubject(PDO $db, array $data, ?int $currentId): array {
        $name=trim((string)($data['name'] ?? ''));
        $code=trim((string)($data['code'] ?? ''));
        $kind=(string)($data['kind'] ?? '');
        $type=self::id($data['type'] ?? '', 'ประเภท');
        $area=self::id($data['area'] ?? '', 'กลุ่มสาระ', true);
        if ($name==='' || self::len($name)>255 || preg_match('/[\x00-\x1F\x7F]/u',$name)) throw new InvalidArgumentException('ต้องระบุชื่อรายวิชา/กิจกรรมที่ถูกต้อง ไม่เกิน 255 ตัวอักษร');
        if (self::len($code)>255 || preg_match('/[\s\x00-\x1F\x7F]/u',$code)) throw new InvalidArgumentException('รหัสวิชาห้ามมีช่องว่างหรืออักขระควบคุม');
        if (!in_array($kind,['COURSE','ACTIVITY'],true)) throw new InvalidArgumentException('ชนิดรายการไม่ถูกต้อง');
        $st=$db->prepare('SELECT TypeofCourse_name FROM TypeofCourse WHERE TypeofCourse_id=? AND IsActive=1');
        $st->execute([$type]); $typeName=$st->fetchColumn();
        if ($typeName===false) throw new InvalidArgumentException('ไม่พบประเภทวิชาที่เปิดใช้');
        if (($kind==='ACTIVITY') !== ($typeName==='กิจกรรม')) throw new InvalidArgumentException('ชนิดรายวิชา/กิจกรรมไม่ตรงกับประเภทที่เลือก');
        if ($area!==null) { $st=$db->prepare('SELECT COUNT(*) FROM LearningArea WHERE LearningArea_id=? AND IsActive=1');$st->execute([$area]);if (!(int)$st->fetchColumn()) throw new InvalidArgumentException('ไม่พบกลุ่มสาระที่เปิดใช้'); }
        if ($code!=='') {
            $st=$db->prepare('SELECT Subject_id FROM Subject WHERE Subject_code=? AND Subject_id<>? LIMIT 1');
            $st->execute([$code,$currentId??0]); if ($st->fetchColumn()!==false) throw new InvalidArgumentException('รหัสวิชาซ้ำในทะเบียน: '.$code);
        }
        return ['Subject_code'=>$code===''?null:$code,'Subject_name'=>$name,'SubjectKind'=>$kind,'TypeofCourse_id'=>$type,'Learning_Area_id'=>$area];
    }
    private static function referenced(PDO $db,int $id): bool {
        foreach (['CurriculumSubject','SubjectOffering'] as $table) {
            $st=$db->prepare('SELECT 1 FROM `'.$table.'` WHERE Subject_id=? LIMIT 1');$st->execute([$id]);if ($st->fetchColumn()!==false) return true;
        }
        return false;
    }
    private static function audit(PDO $db,int $actor,int $id,string $action,array $before,array $after,string $reason): void {
        $sql='INSERT INTO AuditLog (EntityName,EntityId,ActionType,OldValuesJson,NewValuesJson,ActorUser_id,ChangedAt) VALUES (?,?,?,?,?,?,CURRENT_TIMESTAMP)';
        $db->prepare($sql)->execute(['Subject',(string)$id,$action,json_encode($before,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode(['values'=>$after,'reason'=>$reason],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$actor]);
    }
    /** DB transaction contains validation, row locks, changes, review reset and audit; all or nothing. */
    public static function saveRows(PDO $db,int $actor,array $rows,string $reason): int {
        if (!$rows || count($rows)>100) throw new InvalidArgumentException('บันทึกได้ครั้งละ 1–100 รายการ');
        $reason=trim($reason);
        if (self::len($reason)>500) throw new InvalidArgumentException('เหตุผลยาวเกิน 500 ตัวอักษร');
        $ids=[];
        foreach ($rows as $row) { $id=self::id($row['id']??'','รหัสรายการ');if(isset($ids[$id])) throw new InvalidArgumentException('รหัสรายการซ้ำในชุดบันทึก');$ids[$id]=true; }
        // Sort row lock acquisition to avoid deadlocks when multiple users save concurrently.
        usort($rows,static fn($a,$b)=>(int)$a['id']<=>(int)$b['id']);
        $changed=0; $db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $id=(int)$row['id']; $old=self::find($db,$id,true);
                if (!$old) throw new InvalidArgumentException('ไม่พบรายการ #'.$id);
                if (!hash_equals(self::fingerprint($old),(string)($row['fingerprint']??''))) throw new InvalidArgumentException('รายการ #'.$id.' ถูกแก้ไขโดยผู้อื่น กรุณาโหลดหน้าใหม่');
                $new=self::checkSubject($db,$row,$id);
                $different=false; foreach ($new as $k=>$v) if (($old[$k]===null?null:(string)$old[$k])!==($v===null?null:(string)$v)) {$different=true;break;}
                if (!$different) continue;
                if ((int)$old['IsActive']===1 || self::referenced($db,$id)) throw new InvalidArgumentException('รายการ #'.$id.' เปิดใช้งานหรือมีการอ้างอิงแล้ว ห้ามแก้ตรงจากตาราง ให้สร้างกระบวนการปรับปรุงฉบับก่อน');
                $db->prepare('UPDATE Subject SET Subject_code=?,Subject_name=?,SubjectKind=?,TypeofCourse_id=?,Learning_Area_id=? WHERE Subject_id=?')->execute([$new['Subject_code'],$new['Subject_name'],$new['SubjectKind'],$new['TypeofCourse_id'],$new['Learning_Area_id'],$id]);
                $db->prepare("INSERT INTO SubjectCatalogReview (Subject_id,ReviewStatus,ReviewedByUser_id,ReviewedAt,Notes) VALUES (?,'DRAFT',NULL,NULL,NULL) ON DUPLICATE KEY UPDATE ReviewStatus='DRAFT',ReviewedByUser_id=NULL,ReviewedAt=NULL")->execute([$id]);
                self::audit($db,$actor,$id,'SUBJECT_EDIT_V2',$old,$new,$reason ?: 'bulk_editor'); $changed++;
            }
            $db->commit(); return $changed;
        } catch (\Throwable $e) { if($db->inTransaction()) $db->rollBack(); throw $e; }
    }
    public static function create(PDO $db,int $actor,array $row,string $reason): int {
        $db->beginTransaction();try {
            $new=self::checkSubject($db,$row,null);
            $db->prepare('INSERT INTO Subject (Subject_code,Subject_name,SubjectKind,TypeofCourse_id,Learning_Area_id,IsActive) VALUES (?,?,?,?,?,0)')->execute([$new['Subject_code'],$new['Subject_name'],$new['SubjectKind'],$new['TypeofCourse_id'],$new['Learning_Area_id']]);
            $id=(int)$db->lastInsertId();
            $db->prepare("INSERT INTO SubjectCatalogReview (Subject_id,ReviewStatus) VALUES (?,'DRAFT')")->execute([$id]);
            self::audit($db,$actor,$id,'SUBJECT_CREATE_V2',[],$new,trim($reason));
            $db->commit();return $id;
        } catch(\Throwable $e) {if($db->inTransaction())$db->rollBack();throw $e;}
    }
    public static function review(PDO $db,int $actor,int $id,string $operation,string $reason): void {
        if(!in_array($operation,['review','approve','activate','deactivate','reset'],true))throw new InvalidArgumentException('คำสั่งสถานะไม่ถูกต้อง');
        $reason=trim($reason); if($reason===''||self::len($reason)>500)throw new InvalidArgumentException('กรุณาระบุเหตุผล (ไม่เกิน 500 ตัวอักษร)');
        $db->beginTransaction();try {
            $old=self::find($db,$id,true); if(!$old)throw new InvalidArgumentException('ไม่พบรายการที่เลือก');
            $status=$old['ReviewStatus'];$active=(int)$old['IsActive'];
            if($operation==='review' && $status!=='DRAFT')throw new InvalidArgumentException('ตรวจรับรองเบื้องต้นได้เฉพาะฉบับร่าง');
            if($operation==='approve' && $status!=='REVIEWED')throw new InvalidArgumentException('ต้องตรวจทานก่อนอนุมัติ');
            if($operation==='activate' && ($status!=='APPROVED'||$active===1))throw new InvalidArgumentException('เปิดใช้ได้เฉพาะรายการอนุมัติแล้วและยังไม่เปิดใช้');
            if($operation==='deactivate' && $active!==1)throw new InvalidArgumentException('รายการยังไม่ได้เปิดใช้งาน');
            if(in_array($operation,['deactivate','reset'],true) && self::referenced($db,$id))throw new InvalidArgumentException('รายการนี้ผูกกับหลักสูตรสถานศึกษา/เปิดสอนอยู่ จึงไม่สามารถลดสถานะแบบตรงได้');
            if($operation==='reset' && ($active===1 || $status==='DRAFT'))throw new InvalidArgumentException('ไม่สามารถคืนเป็นฉบับร่างในสถานะนี้');
            if($operation==='review')$status='REVIEWED';if($operation==='approve')$status='APPROVED';if($operation==='reset')$status='DRAFT';
            if($operation==='activate')$active=1;if($operation==='deactivate')$active=0;
            $db->prepare('UPDATE Subject SET IsActive=? WHERE Subject_id=?')->execute([$active,$id]);
            $db->prepare('INSERT INTO SubjectCatalogReview (Subject_id,ReviewStatus,ReviewedByUser_id,ReviewedAt,Notes) VALUES (?,?,?,IF(?=1,NULL,CURRENT_TIMESTAMP),?) ON DUPLICATE KEY UPDATE ReviewStatus=VALUES(ReviewStatus),ReviewedByUser_id=VALUES(ReviewedByUser_id),ReviewedAt=VALUES(ReviewedAt),Notes=VALUES(Notes)')->execute([$id,$status,$operation==='reset'?null:$actor,$operation==='reset'?1:0,$reason]);
            self::audit($db,$actor,$id,'SUBJECT_'.strtoupper($operation).'_V2',$old,['ReviewStatus'=>$status,'IsActive'=>$active],$reason);
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    public static function history(PDO $db, int $limit=40): array {
        $limit=min(100,max(1,$limit));
        return $db->query("SELECT a.AuditLog_id,a.EntityId,a.ActionType,a.OldValuesJson,a.NewValuesJson,a.ChangedAt,u.Username FROM AuditLog a LEFT JOIN SystemUser u ON u.SystemUser_id=a.ActorUser_id WHERE a.EntityName='Subject' ORDER BY a.AuditLog_id DESC LIMIT ".$limit)->fetchAll();
    }
    public static function blueprints(PDO $db): array {
        return $db->query('SELECT c.CurriculumSubject_id,s.Subject_code,s.Subject_name,c.Curriculum_id,c.GradeLevel_id,c.InstructionalPeriod,c.Semester,c.Credit,c.TotalInstructionalHours,c.WeeklyHours,c.ReviewStatus,c.IsActive,cu.Curriculum_name,g.GradeName FROM CurriculumSubject c JOIN Subject s ON s.Subject_id=c.Subject_id JOIN Curriculum cu ON cu.Curriculum_id=c.Curriculum_id JOIN GradeLevel g ON g.GradeLevel_id=c.GradeLevel_id ORDER BY c.CurriculumSubject_id DESC LIMIT 80')->fetchAll();
    }
    public static function blueprintOptions(PDO $db): array {
        return [
            'curricula'=>$db->query('SELECT Curriculum_id id,Curriculum_name name,IsActive FROM Curriculum ORDER BY Curriculum_id DESC')->fetchAll(),
            'grades'=>$db->query('SELECT GradeLevel_id id,GradeName name FROM GradeLevel ORDER BY SortOrder')->fetchAll(),
            'subjects'=>$db->query('SELECT Subject_id id,Subject_code code,Subject_name name,SubjectKind kind FROM Subject ORDER BY Subject_name, Subject_id')->fetchAll(),
            'sections'=>$db->query('SELECT CurriculumSection_id id,Curriculum_id curriculum,SectionName name FROM CurriculumSection ORDER BY Curriculum_id,SortOrder')->fetchAll(),
            'tracks'=>$db->query('SELECT ProgramTrack_id id,Curriculum_id curriculum,ProgramTrackName name FROM ProgramTrack ORDER BY Curriculum_id,ProgramTrackName')->fetchAll(),
        ];
    }
    private static function decimal(mixed $value,string $field,int $max,bool $nullable=false): ?string {
        $raw=trim((string)$value);
        if($nullable&&$raw==='')return null;
        if(!preg_match('/^\d{1,5}(?:\.\d{1,2})?$/D',$raw)|| (float)$raw>$max)throw new InvalidArgumentException('กรุณาระบุ'.$field.'เป็นตัวเลขไม่ติดลบ มีทศนิยมไม่เกิน 2 ตำแหน่ง');
        return number_format((float)$raw,2,'.','');
    }
    public static function createBlueprint(PDO $db,int $actor,array $form): int {
        $cid=self::id($form['curriculum_id']??'','หลักสูตรสถานศึกษา');$sid=self::id($form['subject_id']??'','รายวิชา');$gid=self::id($form['grade_id']??'','ระดับชั้น');
        $section=self::id($form['section_id']??'','หมวดหลักสูตรสถานศึกษา',true);$track=self::id($form['track_id']??'','แผนการเรียน',true);
        $period=(string)($form['period']??'');if(!in_array($period,['TERM','YEAR'],true))throw new InvalidArgumentException('รูปแบบการสอนไม่ถูกต้อง');
        $semester=$period==='TERM'?self::id($form['semester']??'','ภาคเรียน'):null;
        if($period==='TERM'&&!in_array($semester,[1,2],true))throw new InvalidArgumentException('ภาคเรียนต้องเป็น 1 หรือ 2');
        $credit=self::decimal($form['credit']??'', 'หน่วยกิต',9999);
        $hours=self::decimal($form['hours']??'', 'ชั่วโมงรวม',9999);
        $weekly=self::decimal($form['weekly']??'', 'ชั่วโมงต่อสัปดาห์',9999,true);
        if((float)$hours<=0)throw new InvalidArgumentException('ชั่วโมงรวมต้องมากกว่า 0');
        if($weekly!==null&&(float)$weekly<=0)throw new InvalidArgumentException('ชั่วโมงต่อสัปดาห์ต้องมากกว่า 0');
        $required=(string)($form['required_type']??'REQUIRED');if(!in_array($required,['REQUIRED','ELECTIVE'],true))throw new InvalidArgumentException('ประเภทการเลือกเรียนไม่ถูกต้อง');
        $note=trim((string)($form['notes']??''));if(self::len($note)>1000)throw new InvalidArgumentException('หมายเหตุยาวเกิน 1,000 ตัวอักษร');
        $db->beginTransaction();try {
            // Acquire one parent lock per curriculum to serialize new blueprints using this module.
            $st=$db->prepare('SELECT Curriculum_id FROM Curriculum WHERE Curriculum_id=? FOR UPDATE');$st->execute([$cid]);if($st->fetchColumn()===false)throw new InvalidArgumentException('ไม่พบหลักสูตรสถานศึกษา');
            $st=$db->prepare('SELECT Subject_id FROM Subject WHERE Subject_id=?');$st->execute([$sid]);if($st->fetchColumn()===false)throw new InvalidArgumentException('ไม่พบรายวิชา');
            $st=$db->prepare('SELECT GradeLevel_id FROM GradeLevel WHERE GradeLevel_id=?');$st->execute([$gid]);if($st->fetchColumn()===false)throw new InvalidArgumentException('ไม่พบระดับชั้น');
            foreach ([['CurriculumSection','CurriculumSection_id',$section],['ProgramTrack','ProgramTrack_id',$track]] as [$table,$key,$val]) {
                if($val===null)continue;
                $st=$db->prepare('SELECT COUNT(*) FROM `'.$table.'` WHERE `'.$key.'`=? AND Curriculum_id=?');$st->execute([$val,$cid]);if(!(int)$st->fetchColumn())throw new InvalidArgumentException('หมวดหลักสูตรสถานศึกษาหรือแผนการเรียนไม่อยู่ในหลักสูตรสถานศึกษาที่เลือก');
            }
            $st=$db->prepare('SELECT CurriculumSubject_id FROM CurriculumSubject WHERE Curriculum_id=? AND Subject_id=? AND GradeLevel_id=? AND ProgramTrack_id <=> ? AND InstructionalPeriod=? AND Semester <=> ? LIMIT 1');
            $st->execute([$cid,$sid,$gid,$track,$period,$semester]);if($st->fetchColumn()!==false)throw new InvalidArgumentException('รายวิชานี้ผูกกับหลักสูตรสถานศึกษา ชั้น และภาคเรียนดังกล่าวแล้ว');
            $sql='INSERT INTO CurriculumSubject (Curriculum_id,Subject_id,GradeLevel_id,ProgramTrack_id,InstructionalPeriod,Semester,Credit,TotalInstructionalHours,WeeklyHours,CurriculumSection_id,RequiredType,SelectionGroupCode,ReviewStatus,IsActive,Notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,NULL,\'DRAFT\',0,?)';
            $db->prepare($sql)->execute([$cid,$sid,$gid,$track,$period,$semester,$credit,$hours,$weekly,$section,$required,$note]);
            $id=(int)$db->lastInsertId();
            $db->prepare('INSERT INTO AuditLog (EntityName,EntityId,ActionType,OldValuesJson,NewValuesJson,ActorUser_id,ChangedAt) VALUES (?,?,?,?,?,?,CURRENT_TIMESTAMP)')->execute(['CurriculumSubject',(string)$id,'CURRICULUM_SUBJECT_CREATE_V2','{}',json_encode(['Curriculum_id'=>$cid,'Subject_id'=>$sid,'GradeLevel_id'=>$gid,'Period'=>$period,'Semester'=>$semester,'Credit'=>$credit,'Hours'=>$hours,'WeeklyHours'=>$weekly,'Section'=>$section,'Track'=>$track],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$actor]);
            $db->commit();return $id;
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
