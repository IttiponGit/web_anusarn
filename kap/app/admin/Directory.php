<?php
declare(strict_types=1);
namespace Kap\Admin;

use PDO;

/** Read-only queries, no data fabricated from imported spreadsheets. */
final class Directory
{
    public function __construct(private PDO $db) {}

    public function hasTeacherPrefix(): bool
    {
        $s = $this->db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='Teacher' AND COLUMN_NAME='TeacherPrefix'");
        return (int)$s->fetchColumn() > 0;
    }
    public function learningAreas(): array
    {
        // Actual column name read from SchemaDictionary: LearningArea_name.
        return $this->db->query('SELECT LearningArea_id, LearningArea_name FROM LearningArea ORDER BY LearningArea_id')->fetchAll(PDO::FETCH_ASSOC);
    }
    public function teachers(): array
    {
        $prefix=$this->hasTeacherPrefix()?'t.TeacherPrefix':'NULL AS TeacherPrefix';
        return $this->db->query("SELECT t.Teacher_id,t.Teacher_id AS CitizenId,t.Teacher_name,t.Teacher_Lastname,t.LearningArea_id,t.IsActive,$prefix,la.LearningArea_name
           FROM Teacher t LEFT JOIN LearningArea la ON la.LearningArea_id=t.LearningArea_id
           ORDER BY t.Teacher_name,t.Teacher_Lastname,t.Teacher_id LIMIT 3000")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function roles(): array
    {
        return $this->db->query('SELECT Role_id,RoleCode,RoleName FROM Role WHERE IsActive=1 ORDER BY RoleCode')->fetchAll(PDO::FETCH_ASSOC);
    }
    public function users(): array
    {
        $prefix=$this->hasTeacherPrefix()?'t.TeacherPrefix':'NULL AS TeacherPrefix';
        $sql="SELECT u.SystemUser_id,u.Username,u.IsActive,u.Teacher_id,t.Teacher_id AS CitizenId,t.Teacher_name,t.Teacher_Lastname,t.LearningArea_id,$prefix,la.LearningArea_name,
              GROUP_CONCAT(DISTINCT CASE WHEN ur.IsActive=1 AND r.IsActive=1 THEN r.RoleCode ELSE NULL END ORDER BY r.RoleCode SEPARATOR ', ') AS RoleCodes
              FROM SystemUser u LEFT JOIN Teacher t ON t.Teacher_id=u.Teacher_id
              LEFT JOIN LearningArea la ON la.LearningArea_id=t.LearningArea_id
              LEFT JOIN SystemUserRole ur ON ur.SystemUser_id=u.SystemUser_id
              LEFT JOIN Role r ON r.Role_id=ur.Role_id
              GROUP BY u.SystemUser_id,u.Username,u.IsActive,u.Teacher_id,t.Teacher_id,t.Teacher_name,t.Teacher_Lastname,t.LearningArea_id,la.LearningArea_name".
              ($this->hasTeacherPrefix()?',t.TeacherPrefix':'')." ORDER BY u.SystemUser_id DESC LIMIT 2000";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    public function activeYears(): array
    {
        return $this->db->query('SELECT AcademicYear_id,AcademicYear_name FROM AcademicYear ORDER BY AcademicYear_name DESC')->fetchAll(PDO::FETCH_ASSOC);
    }
    public function subjectClasses(int $yearId): array
    {
        $s=$this->db->prepare('SELECT sc.SubjectClass_id,sc.ClassGroupName,sc.SetupStatus,so.TotalInstructionalHours,sub.Subject_code,sub.Subject_name,gl.GradeName,ay.AcademicYear_name,at.Semester
            FROM SubjectClass sc JOIN SubjectOffering so ON so.SubjectOffering_id=sc.SubjectOffering_id
            JOIN Subject sub ON sub.Subject_id=so.Subject_id
            JOIN GradeLevel gl ON gl.GradeLevel_id=so.GradeLevel_id
            JOIN AcademicYear ay ON ay.AcademicYear_id=so.AcademicYear_id
            LEFT JOIN AcademicTerm at ON at.AcademicTerm_id=so.AcademicTerm_id
            WHERE so.AcademicYear_id=? AND sc.IsActive=1 AND so.IsActive=1
            ORDER BY gl.SortOrder,sub.Subject_code,sc.SubjectClass_id LIMIT 3000');
        $s->execute([$yearId]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function assignments(int $yearId): array
    {
        $s=$this->db->prepare('SELECT st.SubjectTeacher_id,st.SubjectClass_id,st.Teacher_id,st.TeachingRole,st.IsActive,t.Teacher_name,t.Teacher_Lastname,
            sub.Subject_code,sub.Subject_name,sc.ClassGroupName,gl.GradeName
            FROM SubjectTeacher st JOIN Teacher t ON t.Teacher_id=st.Teacher_id
            JOIN SubjectClass sc ON sc.SubjectClass_id=st.SubjectClass_id
            JOIN SubjectOffering so ON so.SubjectOffering_id=sc.SubjectOffering_id
            JOIN Subject sub ON sub.Subject_id=so.Subject_id JOIN GradeLevel gl ON gl.GradeLevel_id=so.GradeLevel_id
            WHERE so.AcademicYear_id=? ORDER BY sub.Subject_code,sc.SubjectClass_id,st.Teacher_id');
        $s->execute([$yearId]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }
}
