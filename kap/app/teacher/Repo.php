<?php
declare(strict_types=1);
namespace Kap\Teacher;

use PDO;

final class Repo
{
    public function __construct(private PDO $db, private array $actor) {}

    /** Explicit provisional status policy: do not expose or edit withdrawn enrollments. */
    private function activeEnrollmentClause(string $alias = 'se'): string
    {
        $statuses = Bootstrap::config()['allowed_enrollment_statuses'] ?? [];
        if (!is_array($statuses) || !$statuses) throw new WebError(500, 'ยังไม่ได้กำหนดสถานะการลงทะเบียนที่อนุญาต');
        $safe=[];
        foreach ($statuses as $status) {
            if (!is_string($status) || !preg_match('/^[A-Z_]{1,30}$/D', $status)) throw new WebError(500,'สถานะ EnrollmentStatus ใน settings ไม่ถูกต้อง');
            $safe[]="'".$status."'";
        }
        return $alias.'.EnrollmentStatus IN ('.implode(',',array_unique($safe)).')';
    }

    private function statement(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    private function scoreAdmin(): bool
    {
        return in_array('SYSTEM_ADMIN', (array)($this->actor['Roles'] ?? []), true)
            && (int)($this->actor['SystemUser_id'] ?? 0) > 0;
    }

    private function teacherId(): string
    {
        $id = $this->actor['Teacher_id'] ?? null;
        // Never cast the 13-digit citizen key to int: leading zeroes are significant.
        return is_string($id) && preg_match('/^[0-9]{13}$/D', $id) ? $id : '';
    }

    /** Years come from AcademicYear, including previous years without assignments. */
    public function academicYears(): array
    {
        $sql = <<<'SQL'
            SELECT ay.AcademicYear_id, ay.AcademicYear_name,
                   EXISTS (
                       SELECT 1 FROM SubjectOffering so
                       INNER JOIN SubjectClass sc ON sc.SubjectOffering_id=so.SubjectOffering_id
                       INNER JOIN SubjectTeacher st ON st.SubjectClass_id=sc.SubjectClass_id
                       WHERE so.AcademicYear_id=ay.AcademicYear_id
                         AND so.IsActive=1 AND st.Teacher_id=? AND st.IsActive=1
                   ) AS HasAssignedSubjects
            FROM AcademicYear ay
            ORDER BY ay.AcademicYear_name DESC, ay.AcademicYear_id DESC
            SQL;
        return $this->statement($sql, [$this->teacherId()])->fetchAll();
    }

    /** Teacher dashboard stays assignment-scoped, including for admins without a teaching role. */
    public function subjects(?int $academicYearId = null): array
    {
        return $this->listSubjects($academicYearId, false);
    }

    /** Score editor: an authenticated SYSTEM_ADMIN can see every existing offering/class. */
    public function scoreSubjects(?int $academicYearId = null): array
    {
        return $this->listSubjects($academicYearId, $this->scoreAdmin());
    }

    private function listSubjects(?int $academicYearId, bool $adminAll): array
    {
        $source = $adminAll
            ? 'FROM SubjectClass sc'
            : 'FROM SubjectTeacher st INNER JOIN SubjectClass sc ON sc.SubjectClass_id = st.SubjectClass_id';
        $filter = $adminAll ? 'WHERE so.IsActive = 1' : 'WHERE st.Teacher_id = ? AND st.IsActive = 1 AND so.IsActive = 1';
        $sql = "SELECT sc.SubjectClass_id, sc.ClassGroupName, sc.SetupStatus, sc.IsActive AS ClassIsActive,
                       so.SubjectOffering_id, so.AcademicYear_id, so.TotalInstructionalHours, so.Credit,
                       sub.Subject_code, sub.Subject_name, gl.GradeName,
                       ay.AcademicYear_name, t.Semester,
                       (SELECT COUNT(*) FROM SubjectEnrollment se JOIN Student s ON s.Student_id=se.Student_id WHERE se.SubjectClass_id = sc.SubjectClass_id AND s.IsActive=1 AND {$this->activeEnrollmentClause('se')}) AS StudentCount,
                       (SELECT COUNT(*) FROM SubjectScorePlan sp WHERE sp.SubjectClass_id = sc.SubjectClass_id AND sp.IsCurrent = 1 AND sp.PlanStatus = 'ACTIVE') AS CurrentPlanCount,
                       (SELECT sp.ScorePlan_id FROM SubjectScorePlan sp WHERE sp.SubjectClass_id = sc.SubjectClass_id AND sp.IsCurrent = 1 AND sp.PlanStatus = 'ACTIVE' ORDER BY sp.ScorePlan_id DESC LIMIT 1) AS ScorePlan_id
                {$source}
                INNER JOIN SubjectOffering so ON so.SubjectOffering_id = sc.SubjectOffering_id
                INNER JOIN Subject sub ON sub.Subject_id = so.Subject_id
                INNER JOIN GradeLevel gl ON gl.GradeLevel_id = so.GradeLevel_id
                LEFT JOIN AcademicTerm t ON t.AcademicTerm_id = so.AcademicTerm_id
                LEFT JOIN AcademicYear ay ON ay.AcademicYear_id = so.AcademicYear_id
                {$filter}
                ".($academicYearId === null ? '' : 'AND so.AcademicYear_id = ? ')."
                ORDER BY ay.AcademicYear_name DESC, t.Semester DESC, gl.SortOrder ASC, sub.Subject_code ASC, sc.SubjectClass_id ASC";
        $params = $adminAll ? [] : [$this->teacherId()];
        if ($academicYearId !== null) $params[] = $academicYearId;
        $rows = $this->statement($sql, $params)->fetchAll();
        foreach ($rows as &$row) {
            $row['Ready'] = (int)$row['ClassIsActive'] === 1 && $row['SetupStatus'] === 'ACTIVE'
                && (int)$row['CurrentPlanCount'] === 1 && (int)$row['StudentCount'] > 0;
            $row['Progress'] = !$adminAll && $row['ScorePlan_id'] && (int)$row['CurrentPlanCount'] === 1
                ? $this->progress((int)$row['SubjectClass_id'], (int)$row['ScorePlan_id'], (int)$row['StudentCount'])
                : ['filled'=>0,'total'=>0,'percent'=>null];
        }
        unset($row);
        return $rows;
    }

    public function subject(int $classId, bool $adminScoreAccess = false): array
    {
        $all = $adminScoreAccess && $this->scoreAdmin();
        $source = $all ? 'FROM SubjectClass sc'
            : 'FROM SubjectTeacher st INNER JOIN SubjectClass sc ON sc.SubjectClass_id = st.SubjectClass_id';
        $where = $all ? 'WHERE sc.SubjectClass_id = ? AND so.IsActive = 1'
            : 'WHERE st.Teacher_id = ? AND st.IsActive = 1 AND sc.SubjectClass_id = ? AND so.IsActive = 1';
        $sql = "SELECT sc.SubjectClass_id, sc.ClassGroupName, sc.SetupStatus, sc.IsActive AS ClassIsActive,
                       so.SubjectOffering_id, so.AcademicYear_id, so.TotalInstructionalHours, so.Credit,
                       sub.Subject_code, sub.Subject_name, gl.GradeName, ay.AcademicYear_name, t.Semester
                {$source}
                INNER JOIN SubjectOffering so ON so.SubjectOffering_id = sc.SubjectOffering_id
                INNER JOIN Subject sub ON sub.Subject_id = so.Subject_id
                INNER JOIN GradeLevel gl ON gl.GradeLevel_id = so.GradeLevel_id
                LEFT JOIN AcademicTerm t ON t.AcademicTerm_id = so.AcademicTerm_id
                LEFT JOIN AcademicYear ay ON ay.AcademicYear_id = so.AcademicYear_id
                {$where} LIMIT 1";
        $params = $all ? [$classId] : [$this->teacherId(), $classId];
        $row = $this->statement($sql, $params)->fetch();
        if (!$row) throw new WebError(403, 'ไม่มีสิทธิ์เข้าถึงกลุ่มเรียนที่เลือก หรือกลุ่มเรียนไม่ได้เปิดสอน');
        return $row;
    }

    public function currentPlan(int $classId): ?array
    {
        $plans = $this->statement("SELECT ScorePlan_id, SubjectClass_id, PlanStatus, IsCurrent, TargetTotalScore, VersionNumber, CreatedAt, LockedAt FROM SubjectScorePlan WHERE SubjectClass_id = ? AND IsCurrent = 1 AND PlanStatus = 'ACTIVE'", [$classId])->fetchAll();
        if (count($plans) > 1) throw new WebError(409, 'พบแผนคะแนน ACTIVE ซ้ำ ต้องให้ฝ่ายวิชาการตรวจสอบ');
        return $plans[0] ?? null;
    }

    public function allocations(int $planId): array
    {
        return $this->statement("SELECT at.AssessmentTypeCode, at.AssessmentTypeName, a.AllocatedMaxScore
            FROM ScorePlanAllocation a JOIN AssessmentType at ON at.AssessmentType_id = a.AssessmentType_id
            WHERE a.ScorePlan_id = ? AND a.IsActive = 1 ORDER BY a.SortOrder, a.ScorePlanAllocation_id", [$planId])->fetchAll();
    }

    public function scoreItems(int $classId, int $planId, bool $all = false): array
    {
        $filter = $all ? '' : "AND at.AssessmentTypeCode IN ('UNIT_K', 'UNIT_A', 'UNIT_P')";
        return $this->statement("SELECT ai.AssessmentItem_id, ai.ItemName, ai.MaxScore, ai.AssessmentType_id,
                    at.AssessmentTypeCode, at.AssessmentTypeName, at.AssessmentDomain_id AS TypeDomain_id,
                    ai.AssessmentDomain_id AS ItemDomain_id, ai.LearningUnit_id, lu.UnitName, lu.UnitOrder, lu.ScorePlan_id AS UnitPlan_id
            FROM AssessmentItem ai JOIN AssessmentType at ON at.AssessmentType_id = ai.AssessmentType_id
            LEFT JOIN LearningUnit lu ON lu.LearningUnit_id = ai.LearningUnit_id
            WHERE ai.SubjectClass_id = ? AND ai.ScorePlan_id = ? AND ai.IsActive = 1 AND at.IsActive = 1 {$filter}
            ORDER BY FIELD(at.AssessmentTypeCode, 'UNIT_K', 'UNIT_A','UNIT_P', 'MIDTERM', 'FINAL_EXAM', 'OTHER'), lu.UnitOrder, ai.SortOrder, ai.AssessmentItem_id", [$classId, $planId])->fetchAll();
    }

    /** Read existing plans for rendering. Real DB changes stay disabled until proper authentication. */
    public function planEditorData(int $classId): array
    {
        $this->subject($classId); // enforce SubjectTeacher scoping
        $sql="SELECT ScorePlan_id, SubjectClass_id, PlanStatus, VersionNumber, TargetTotalScore, IsCurrent
              FROM SubjectScorePlan WHERE SubjectClass_id=?
              ORDER BY CASE WHEN PlanStatus='DRAFT' THEN 0 WHEN IsCurrent=1 THEN 1 ELSE 2 END, VersionNumber DESC LIMIT 1";
        $plan=$this->statement($sql,[$classId])->fetch();
        $units=[];$mid='0.00';$final='0.00';
        if($plan){
            $planId=(int)$plan['ScorePlan_id'];
            $rows=$this->statement('SELECT LearningUnit_id, UnitOrder, UnitName, TeachingHours FROM LearningUnit WHERE SubjectClass_id=? AND ScorePlan_id=? AND IsActive=1 ORDER BY UnitOrder, LearningUnit_id',[$classId,$planId])->fetchAll();
            $positions=[];
            foreach($rows as $unit){
                $position=count($units);
                $positions[(int)$unit['LearningUnit_id']]=$position;
                $units[]=['name'=>(string)$unit['UnitName'],'learning_content'=>'','hours'=>$unit['TeachingHours']===null?'':(string)$unit['TeachingHours'],'k'=>'0.00','a'=>'0.00','p'=>'0.00'];
            }
            foreach($this->scoreItems($classId,$planId,true) as $item){
                $type=(string)$item['AssessmentTypeCode'];
                if($type==='MIDTERM'||$type==='FINAL_EXAM'){
                    if($type==='MIDTERM')$mid=number_format((float)$mid+(float)$item['MaxScore'],2,'.','');
                    else $final=number_format((float)$final+(float)$item['MaxScore'],2,'.','');
                    continue;
                }
                $key=['UNIT_K'=>'k','UNIT_A'=>'a','UNIT_P'=>'p'][$type]??null;
                $u=(int)($item['LearningUnit_id']??0);
                if($key!==null && isset($positions[$u])){
                    $idx=$positions[$u];
                    $units[$idx][$key]=number_format((float)$units[$idx][$key]+(float)$item['MaxScore'],2,'.','');
                }
            }
        }
        if(!$units)$units=[['name'=>'หน่วยที่ 1','learning_content'=>'','hours'=>'','k'=>'0.00','a'=>'0.00','p'=>'0.00']];
        return [
           'subject_class_id'=>$classId,
           'score_plan_id'=>$plan?(int)$plan['ScorePlan_id']:null,
           'base_version'=>$plan?(int)$plan['VersionNumber']:0,
           'draft_version'=>$plan?(int)$plan['VersionNumber']+1:1,
           'draft_revision'=>0,
           'status'=>$plan?(string)$plan['PlanStatus']:'DRAFT',
           'target'=>$plan?(string)$plan['TargetTotalScore']:'100.00',
           'midterm'=>$mid,'final_exam'=>$final,'units'=>$units,'saved_at'=>null
        ];
    }

    /** Academic-year/grade candidates only. Public no-login database mode is read-only and IP-gated. */
    public function studentRosterData(int $classId): array
    {
        $this->subject($classId); // Teacher-scoped SubjectClass check
        $context=$this->statement('SELECT so.AcademicYear_id, so.GradeLevel_id
            FROM SubjectClass sc JOIN SubjectOffering so ON so.SubjectOffering_id=sc.SubjectOffering_id
            WHERE sc.SubjectClass_id=?',[$classId])->fetch();
        if(!$context)throw new WebError(404,'ไม่พบกลุ่มเรียน');
        $year=(int)$context['AcademicYear_id'];
        $grade=(int)$context['GradeLevel_id'];
        $rooms=$this->statement('SELECT cr.ClassRoom_id, cr.RoomName
            FROM ClassRoom cr WHERE cr.AcademicYear_id=? AND cr.GradeLevel_id=? AND cr.IsActive=1
            ORDER BY cr.RoomName,cr.ClassRoom_id',[$year,$grade])->fetchAll();
        // Use the latest class enrollment within this academic year for every student.
        // School-specific StudentClassEnrollment.Status values still require confirmation.
        $students=$this->statement("SELECT s.Student_id, s.StudentCode,
               TRIM(CONCAT_WS(' ',NULLIF(s.StudentPrefix,''),CONCAT(s.StudentFirstName,' ',s.StudentLastName))) AS StudentName,
               cr.ClassRoom_id, cr.RoomName, sce.Status AS ClassStatus
            FROM StudentClassEnrollment sce
            JOIN Student s ON s.Student_id=sce.Student_id AND s.IsActive=1
            JOIN ClassRoom cr ON cr.ClassRoom_id=sce.ClassRoom_id
            WHERE cr.AcademicYear_id=? AND cr.GradeLevel_id=? AND cr.IsActive=1
              AND NOT EXISTS (SELECT 1 FROM StudentClassEnrollment newer
                JOIN ClassRoom cr2 ON cr2.ClassRoom_id=newer.ClassRoom_id
                WHERE newer.Student_id=sce.Student_id AND cr2.AcademicYear_id=?
                  AND newer.StudentClassEnrollment_id>sce.StudentClassEnrollment_id)
            ORDER BY cr.RoomName,s.StudentCode,s.Student_id LIMIT 2001",[$year,$grade,$year])->fetchAll();
        if(count($students)>2000)throw new WebError(409,'รายชื่อนักเรียนเกิน 2,000 คน กรุณาตรวจข้อมูลทะเบียน');
        // Existing enrollment is a historic snapshot. Never silently delete it when roster changes.
        $currentRows=$this->statement('SELECT se.Student_id FROM SubjectEnrollment se WHERE se.SubjectClass_id=? AND '.$this->activeEnrollmentClause('se'),[$classId])->fetchAll();
        $currentIds=array_values(array_unique(array_map(static fn($r)=>(int)$r['Student_id'],$currentRows)));
        $candidateIds=array_fill_keys(array_map(static fn($r)=>(int)$r['Student_id'],$students),true);
        $availableCurrent=array_values(array_filter($currentIds,static fn($id)=>isset($candidateIds[$id])));
        return [
            'rooms'=>$rooms,'students'=>$students,
            'current_ids'=>$availableCurrent,
            'protected_current_count'=>count($currentIds)-count($availableCurrent),
            'mode'=>'INDIVIDUAL','room_ids'=>[],'selected_ids'=>$availableCurrent,
            'draft_revision'=>0,'saved_at'=>null,
        ];
    }

    public function enrollments(int $classId, int $yearId): array
    {
        return $this->statement("SELECT se.SubjectEnrollment_id, se.Student_id, se.EnrollmentStatus, s.StudentCode,
                     TRIM(CONCAT_WS(' ', NULLIF(s.StudentPrefix,''), CONCAT(s.StudentFirstName,' ',s.StudentLastName))) AS StudentName,
                     (SELECT cr.RoomName FROM StudentClassEnrollment sce JOIN ClassRoom cr ON cr.ClassRoom_id = sce.ClassRoom_id
                      WHERE sce.Student_id = se.Student_id AND cr.AcademicYear_id = ? ORDER BY sce.StudentClassEnrollment_id DESC LIMIT 1) AS RoomName
             FROM SubjectEnrollment se JOIN Student s ON s.Student_id = se.Student_id
             WHERE se.SubjectClass_id = ? AND s.IsActive = 1 AND {$this->activeEnrollmentClause('se')}
             ORDER BY s.StudentCode, s.Student_id", [$yearId, $classId])->fetchAll();
    }

    public function scores(int $classId, int $planId): array
    {
        $data = $this->statement("SELECT ss.StudentScore_id, ss.AssessmentItem_id, ss.SubjectEnrollment_id, ss.Score
            FROM StudentScore ss
            JOIN AssessmentItem ai ON ai.AssessmentItem_id = ss.AssessmentItem_id
            JOIN SubjectEnrollment se ON se.SubjectEnrollment_id = ss.SubjectEnrollment_id
            WHERE ai.SubjectClass_id = ? AND ai.ScorePlan_id = ? AND ai.IsActive = 1 AND se.SubjectClass_id = ?", [$classId, $planId, $classId])->fetchAll();
        $out=[];
        foreach ($data as $row) {
            $key = $row['SubjectEnrollment_id'].':'.$row['AssessmentItem_id'];
            if (array_key_exists($key,$out)) throw new WebError(409, 'พบ StudentScore ซ้ำในฐานข้อมูล กรุณาตรวจ UNIQUE ก่อนบันทึก');
            $out[$key] = $row['Score'];
        }
        return $out;
    }

    private function progress(int $classId, int $planId, int $studentCount): array
    {
        $all = $this->statement("SELECT COUNT(*) FROM AssessmentItem WHERE SubjectClass_id = ? AND ScorePlan_id = ? AND IsActive = 1", [$classId, $planId])->fetchColumn();
        $total = (int)$all * $studentCount;
        if ($total === 0) return ['filled'=>0,'total'=>0,'percent'=>null];
        $filled = (int)$this->statement("SELECT COUNT(*) FROM StudentScore ss
            INNER JOIN AssessmentItem ai ON ai.AssessmentItem_id = ss.AssessmentItem_id
            INNER JOIN SubjectEnrollment se ON se.SubjectEnrollment_id = ss.SubjectEnrollment_id
            WHERE ai.SubjectClass_id = ? AND ai.ScorePlan_id = ? AND ai.IsActive = 1
            AND se.SubjectClass_id = ? AND {$this->activeEnrollmentClause('se')} AND ss.Score IS NOT NULL", [$classId, $planId, $classId])->fetchColumn();
        return ['filled'=>$filled,'total'=>$total,'percent'=>(int)floor(100*$filled/$total)];
    }

    /** Verify plan integrity at write time; do not infer from spreadsheet's green cell colors. */
    public function requireReady(int $classId, array $subject, array $plan): void
    {
        if ((int)$subject['ClassIsActive'] !== 1 || $subject['SetupStatus'] !== 'ACTIVE') throw new WebError(409, 'กลุ่มเรียนยังไม่ได้เปิดใช้งานอย่างสมบูรณ์');
        if ($plan['PlanStatus'] !== 'ACTIVE' || (int)$plan['IsCurrent'] !== 1) throw new WebError(409, 'แผนคะแนนยังไม่ ACTIVE');
        if ((int)$this->statement("SELECT COUNT(*) FROM SubjectEnrollment se JOIN Student s ON s.Student_id = se.Student_id WHERE se.SubjectClass_id = ? AND s.IsActive = 1 AND {$this->activeEnrollmentClause('se')}", [$classId])->fetchColumn() < 1) throw new WebError(409, 'ยังไม่มีนักเรียนลงทะเบียน');
        $alloc = $this->allocations((int)$plan['ScorePlan_id']);
        $items = $this->scoreItems($classId, (int)$plan['ScorePlan_id'], true);
        if (!$alloc || !$items) throw new WebError(409, 'แผนคะแนนยังไม่มีรายการคะแนนและการจัดสรรครบ');
        $sum = 0;
        $byType=[];
        foreach ($alloc as $row) {
            $v=ScoreRules::cents((string)$row['AllocatedMaxScore']);
            $sum += $v;
            $code=(string)$row['AssessmentTypeCode'];
            if (isset($byType[$code])) throw new WebError(409, 'จัดสรรคะแนนประเภทซ้ำ');
            $byType[$code]=['max'=>$v,'items'=>0];
        }
        if ($sum !== ScoreRules::cents((string)$plan['TargetTotalScore'])) throw new WebError(409, 'ยอดจัดสรรคะแนนไม่เท่าคะแนนเต็มของแผน');
        foreach ($items as $it) {
            $code=(string)$it['AssessmentTypeCode'];
            if (!isset($byType[$code])) throw new WebError(409, 'พบรายการคะแนนที่ไม่มีการจัดสรรประเภทคะแนน');
            if (in_array($code, ['UNIT_K','UNIT_A','UNIT_P'],true)) {
                if (!$it['LearningUnit_id'] || (int)$it['UnitPlan_id'] !== (int)$plan['ScorePlan_id']) throw new WebError(409, 'รายการ K/A/P ต้องผูกหน่วยในแผนปัจจุบัน');
                if ($it['TypeDomain_id'] !== null && (int)$it['ItemDomain_id'] !== (int)$it['TypeDomain_id']) throw new WebError(409, 'Domain ของรายการคะแนนไม่สอดคล้องประเภทคะแนน');
            }
            $byType[$code]['items'] += ScoreRules::cents((string)$it['MaxScore']);
        }
        foreach ($byType as $code=>$v) {
            if ($v['items'] !== $v['max']) throw new WebError(409, 'คะแนนเต็มรายข้อไม่ตรงยอดจัดสรรหมวด '.$code);
        }
    }

    /** Read-only eligibility check for score edits, including finalized result safeguards. */
    public function scoreWriteBlockReason(int $classId, array $plan, bool $lock = false): ?string
    {
        if (!empty($plan['LockedAt'])) return 'แผนคะแนนถูกล็อกแล้ว ไม่อนุญาตให้แก้ไข';
        $sql = "SELECT fr.FinalResult_id FROM FinalResult fr
                INNER JOIN SubjectEnrollment se ON se.SubjectEnrollment_id = fr.SubjectEnrollment_id
                WHERE se.SubjectClass_id = ? AND
                (fr.LockedAt IS NOT NULL OR fr.FinalizedAt IS NOT NULL
                 OR UPPER(COALESCE(fr.ResultStatus,'')) IN ('LOCKED','FINAL','FINALIZED','APPROVED','CONFIRMED','PUBLISHED'))
                LIMIT 1" . ($lock ? ' FOR UPDATE' : '');
        if ($this->statement($sql, [$classId])->fetchColumn())
            return 'มีผลการเรียนที่ล็อกหรือสรุปผลแล้วในกลุ่มเรียนนี้ ต้องใช้กระบวนการปลดล็อก/อนุมัติแยกต่างหาก';
        return null;
    }

    /** Real-MariaDB score write: admin only; teachers remain read-only in this release. */
    public function save(int $classId, int $expectedPlanId, array $changes, ?string $reason = null): int
    {
        if (!$this->scoreAdmin()) throw new WebError(403, 'เฉพาะผู้ดูแลระบบ SYSTEM_ADMIN เท่านั้นที่บันทึกคะแนนจริงได้ในระยะนี้');
        $reason = trim((string)$reason);
        if (!preg_match('//u', $reason) || ($reasonLength=preg_match_all('/./us', $reason)) < 5 || $reasonLength > 500)
            throw new WebError(422, 'กรุณาระบุเหตุผลการกรอกหรือแก้ไขคะแนน 5–500 ตัวอักษร');
        if (count($changes) === 0 || count($changes) > 1500)
            throw new WebError(422, 'จำนวนช่องคะแนนที่ส่งไม่ถูกต้อง (สูงสุด 1500 ช่องต่อครั้ง)');
        $this->db->beginTransaction();
        try {
            $this->statement('SELECT SubjectClass_id FROM SubjectClass WHERE SubjectClass_id = ? FOR UPDATE', [$classId])->fetchColumn();
            $subject = $this->subject($classId, true); // Permission + existing class, checked AFTER lock.
            $plan = $this->currentPlan($classId);
            if (!$plan || (int)$plan['ScorePlan_id'] !== $expectedPlanId)
                throw new WebError(409, 'แผนคะแนนเปลี่ยนไป กรุณาเรียกข้อมูลใหม่');
            $this->statement('SELECT ScorePlan_id FROM SubjectScorePlan WHERE ScorePlan_id = ? FOR UPDATE', [$expectedPlanId])->fetchColumn();
            $this->requireReady($classId, $subject, $plan);
            $blocked = $this->scoreWriteBlockReason($classId, $plan, true);
            if ($blocked !== null) throw new WebError(423, $blocked);
            $items = $this->scoreItems($classId, $expectedPlanId);
            $allowedItems = [];
            foreach ($items as $it) $allowedItems[(int)$it['AssessmentItem_id']] = (string)$it['MaxScore'];
            $enrollments = $this->statement("SELECT se.SubjectEnrollment_id FROM SubjectEnrollment se JOIN Student s ON s.Student_id=se.Student_id WHERE se.SubjectClass_id = ? AND s.IsActive=1 AND {$this->activeEnrollmentClause('se')}", [$classId])->fetchAll(PDO::FETCH_COLUMN);
            $allowedEnrollments = array_fill_keys(array_map('intval', $enrollments), true);
            $seen=[]; $edits=[];
            foreach ($changes as $change) {
                if (!is_array($change) || !isset($change['item_id'], $change['enrollment_id']) || !array_key_exists('score', $change) || !array_key_exists('expected_score', $change))
                    throw new WebError(422, 'ข้อมูลคะแนนไม่ครบ ต้องระบุคะแนนเดิมเพื่อตรวจการแก้ไขพร้อมกัน');
                $itemId = Bootstrap::id($change['item_id']);
                $enrollmentId = Bootstrap::id($change['enrollment_id']);
                $key = $enrollmentId.':'.$itemId;
                if (isset($seen[$key])) throw new WebError(422, 'มีช่องคะแนนซ้ำในคำขอ');
                $seen[$key]=true;
                if (!isset($allowedItems[$itemId]) || !isset($allowedEnrollments[$enrollmentId]))
                    throw new WebError(403, 'คะแนนไม่อยู่ในกลุ่มเรียน แผน หรือทะเบียนนักเรียนที่อนุญาต');
                $score = ScoreRules::canonical($change['score'], $allowedItems[$itemId]);
                $expected = ScoreRules::canonical($change['expected_score'], $allowedItems[$itemId]);
                $edits[] = [$itemId,$enrollmentId,$score,$expected];
            }
            $updated=0;
            foreach ($edits as [$itemId,$enrollmentId,$score,$expected]) {
                $rows = $this->statement('SELECT StudentScore_id, Score FROM StudentScore WHERE AssessmentItem_id = ? AND SubjectEnrollment_id = ? FOR UPDATE', [$itemId, $enrollmentId])->fetchAll();
                if (count($rows)>1) throw new WebError(409, 'พบคะแนนซ้ำในฐานข้อมูล กรุณาตรวจ UNIQUE ก่อนบันทึก');
                $old = $rows[0]['Score'] ?? null;
                $normalizedOld = $old === null ? null : ScoreRules::canonical((string)$old, $allowedItems[$itemId]);
                if ($normalizedOld !== $expected) throw new WebError(409, 'คะแนนถูกแก้ไขจากอีกหน้าต่าง กรุณาเรียกข้อมูลใหม่ก่อนบันทึก');
                if ($normalizedOld === $score) continue;
                if ($rows) {
                    $this->statement('UPDATE StudentScore SET Score = ?, RecordedByUser_id = ?, UpdatedAt = NOW() WHERE StudentScore_id = ?', [$score,(int)$this->actor['SystemUser_id'],(int)$rows[0]['StudentScore_id']]);
                } elseif ($score !== null) {
                    $this->statement('INSERT INTO StudentScore (AssessmentItem_id, SubjectEnrollment_id, Score, RecordedByUser_id, UpdatedAt) VALUES (?, ?, ?, ?, NOW())', [$itemId,$enrollmentId,$score,(int)$this->actor['SystemUser_id']]);
                }
                $oldJson=json_encode(['score'=>$normalizedOld,'subject_class_id'=>$classId,'score_plan_id'=>$expectedPlanId],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                $newJson=json_encode(['score'=>$score,'subject_class_id'=>$classId,'score_plan_id'=>$expectedPlanId,'reason'=>$reason],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                $this->statement('INSERT INTO AuditLog (EntityName, EntityId, ActionType, OldValuesJson, NewValuesJson, ActorUser_id, ChangedAt) VALUES (?, ?, ?, ?, ?, ?, NOW())', [
                    'StudentScore', $enrollmentId.':'.$itemId, 'SCORE_ADMIN_EDIT', $oldJson, $newJson, (int)$this->actor['SystemUser_id']
                ]);
                $updated++;
            }
            $this->db->commit();
            return $updated;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
