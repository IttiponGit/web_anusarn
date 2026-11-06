<?php
declare(strict_types=1);
namespace Kap\Teacher;

use PDO;

/**
 * Read-only, academic-year student directory for authorised school personnel.
 *
 * This is not a "currently enrolled / eligible for course registration" query.
 * A school-year directory must show the latest room membership recorded for that
 * year even if its Status differs from a demo setting, or its EndDate is in the
 * past. Show those flags to the reviewer rather than silently hiding students.
 * Course enrollment and score-writing authorization remain separate.
 */
final class RealStudentDirectory
{
    public static function read(PDO $db, string $year = '2569'): array
    {
        if (!preg_match('/^[0-9]{4}$/D', $year)) {
            throw new WebError(422, 'ปีการศึกษาไม่ถูกต้อง');
        }

        $q = $db->prepare('SELECT AcademicYear_id, AcademicYear_name FROM AcademicYear WHERE AcademicYear_name = ? LIMIT 2');
        $q->execute([$year]);
        $years = $q->fetchAll(PDO::FETCH_ASSOC);
        if (count($years) !== 1) {
            throw new WebError(409, 'ไม่พบปีการศึกษาหรือพบปีการศึกษาซ้ำ กรุณาตรวจตาราง AcademicYear');
        }
        $yearId = (int) $years[0]['AcademicYear_id'];

        // Do NOT use allowed_class_statuses: that provisional setting belongs
        // to enrollment eligibility, not the school's read-only year directory.
        // Also do NOT filter by CURDATE(), Student.IsActive, ClassRoom.IsActive,
        // or GradeLevel.IsActive, as these hide historical year records.
        // Select the most recent room record per student within THIS year only.
        $sql = <<<'SQL'
SELECT s.Student_id, s.StudentCode,
       TRIM(CONCAT_WS(' ', NULLIF(s.StudentPrefix, ''), s.StudentFirstName, s.StudentLastName)) AS StudentName,
       s.IsActive AS StudentActive,
       cr.ClassRoom_id, cr.RoomName, cr.IsActive AS RoomActive,
       gl.GradeLevel_id, gl.GradeName, gl.SortOrder, gl.IsActive AS GradeActive,
       sce.Status AS ClassStatus, sce.StartDate, sce.EndDate
FROM StudentClassEnrollment sce
JOIN Student s ON s.Student_id = sce.Student_id
JOIN ClassRoom cr ON cr.ClassRoom_id = sce.ClassRoom_id
JOIN GradeLevel gl ON gl.GradeLevel_id = cr.GradeLevel_id
WHERE cr.AcademicYear_id = ?
  AND NOT EXISTS (
    SELECT 1
    FROM StudentClassEnrollment newer
    JOIN ClassRoom newerRoom ON newerRoom.ClassRoom_id = newer.ClassRoom_id
    WHERE newer.Student_id = sce.Student_id
      AND newerRoom.AcademicYear_id = ?
      AND newer.StudentClassEnrollment_id > sce.StudentClassEnrollment_id
  )
ORDER BY gl.SortOrder, cr.RoomName, s.StudentCode, s.Student_id
LIMIT 5001
SQL;
        $q = $db->prepare($sql);
        $q->execute([$yearId, $yearId]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 5000) {
            throw new WebError(409, 'พบข้อมูลทะเบียนมากกว่า 5,000 คน กรุณาตรวจข้อมูลซ้ำก่อนแสดงผล');
        }

        $statuses = [];
        $inactiveStudents = 0;
        $inactiveRooms = 0;
        $inactiveGrades = 0;
        $datedMemberships = 0;
        foreach ($rows as $row) {
            $status = trim((string) ($row['ClassStatus'] ?? ''));
            $status = $status === '' ? '(ไม่ระบุ)' : $status;
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
            if ((int) ($row['StudentActive'] ?? 0) !== 1) ++$inactiveStudents;
            if ((int) ($row['RoomActive'] ?? 0) !== 1) ++$inactiveRooms;
            if ((int) ($row['GradeActive'] ?? 0) !== 1) ++$inactiveGrades;
            if (($row['EndDate'] ?? null) !== null && (string) $row['EndDate'] !== '') ++$datedMemberships;
        }
        ksort($statuses);

        return [
            'year_id' => $yearId,
            'year' => $year,
            'students' => $rows,
            'status_counts' => $statuses,
            'inactive_student_count' => $inactiveStudents,
            'inactive_room_count' => $inactiveRooms,
            'inactive_grade_count' => $inactiveGrades,
            'dated_membership_count' => $datedMemberships,
        ];
    }
}
