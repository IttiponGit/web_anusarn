<?php
/*
 * Phase 3.5 - Project Correction & Cancellation Management
 * Compatible with PHP 5.6
 */

function qa_project_change_table_ready()
{
    static $ready = null;
    if ($ready !== null) return $ready;

    $db = qa_db();
    $result = $db->query("SHOW TABLES LIKE 'qa_project_change_requests'");
    $ready = $result && $result->num_rows > 0;
    if ($result) $result->free();
    return $ready;
}

function qa_project_change_type_label($type)
{
    $labels = array(
        'correction' => 'ขอแก้ไขโครงการ',
        'cancellation' => 'ขอยกเลิกโครงการ'
    );
    return isset($labels[$type]) ? $labels[$type] : $type;
}

function qa_project_change_status_label($status)
{
    $labels = array(
        'pending' => 'รอพิจารณา',
        'approved' => 'อนุมัติคำขอ',
        'rejected' => 'ไม่อนุมัติคำขอ',
        'withdrawn' => 'ถอนคำขอ'
    );
    return isset($labels[$status]) ? $labels[$status] : $status;
}

function qa_project_change_status_badge($status)
{
    if ($status === 'approved') return 'badge-green';
    if ($status === 'rejected' || $status === 'withdrawn') return 'badge-red';
    return 'badge-yellow';
}

function qa_project_change_category_label($category)
{
    $labels = array(
        'general' => 'ข้อมูลทั่วไป',
        'schedule' => 'ระยะเวลา / สถานที่',
        'alignment' => 'แผน / กลยุทธ์ / ตัวชี้วัด',
        'budget' => 'งบประมาณ',
        'objective' => 'วัตถุประสงค์ / กลุ่มเป้าหมาย',
        'result' => 'ผลการดำเนินงาน',
        'other' => 'อื่น ๆ',
        'cancel' => 'ยกเลิกโครงการ'
    );
    return isset($labels[$category]) ? $labels[$category] : $category;
}

function qa_project_change_resolution_label($mode)
{
    $labels = array(
        'reopen_revision' => 'เปิดกลับมาแก้ไขและส่งอนุมัติใหม่',
        'amendment_record' => 'บันทึกแก้ไขย้อนหลัง โดยไม่เขียนทับข้อมูลเดิม',
        'cancel_project' => 'ยกเลิกโครงการและเก็บประวัติเดิม'
    );
    return isset($labels[$mode]) ? $labels[$mode] : $mode;
}

function qa_project_change_load_project($projectId)
{
    $db = qa_db();
    $stmt = $db->prepare(
        "SELECT p.*, d.division_name, ps.status_name,
                CONCAT(COALESCE(u.prefix,''),COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS owner_name
         FROM qa_projects p
         INNER JOIN qa_divisions d ON d.division_id=p.division_id
         LEFT JOIN qa_project_statuses ps ON ps.status_code=p.status_code
         LEFT JOIN qa_users u ON u.user_id=p.owner_user_id
         WHERE p.project_id=? LIMIT 1"
    );
    if (!$stmt) return null;
    $stmt->bind_param('i',$projectId);
    $stmt->execute();
    $result=$stmt->get_result();
    $row=$result?$result->fetch_assoc():null;
    if($result)$result->free();
    $stmt->close();
    return $row;
}

function qa_project_change_can_request($project)
{
    $user=qa_current_user();
    if(!$user || !$project) return false;

    if(qa_user_has_role('admin')) return true;

    if(isset($project['owner_user_id']) &&
       (int)$project['owner_user_id']===(int)$user['user_id']) {
        return true;
    }

    if(qa_user_has_role('division_head')) {
        $profile=qa_project_user_profile((int)$user['user_id']);
        return $profile &&
               (int)$profile['primary_division_id']===(int)$project['division_id'];
    }

    return false;
}

function qa_project_change_can_review()
{
    return qa_user_has_role('admin') || qa_user_has_role('director');
}

function qa_project_change_allowed_types($project)
{
    if(!$project) return array();

    $status=$project['status_code'];
    $types=array();

    if(in_array($status,array(
        'SUBMITTED','PLAN_REVIEW','BUDGET_REVIEW','PENDING_APPROVAL',
        'APPROVED','IN_PROGRESS','WAITING_REPORT','COMPLETED','CLOSED'
    ),true)) {
        $types[]='correction';
    }

    if(in_array($status,array(
        'DRAFT','REVISION','SUBMITTED','PLAN_REVIEW','BUDGET_REVIEW',
        'PENDING_APPROVAL','APPROVED','IN_PROGRESS','WAITING_REPORT'
    ),true)) {
        $types[]='cancellation';
    }

    return $types;
}

function qa_project_change_execution_summary($projectId)
{
    $db=qa_db();
    $projectId=(int)$projectId;

    return array(
        'actual_expense'=>(float)qa_db_scalar(
            "SELECT COALESCE(SUM(amount),0)
             FROM qa_expenditures
             WHERE project_id=".$projectId." AND payment_status='paid'",0
        ),
        'commitments'=>(int)qa_db_scalar(
            "SELECT COUNT(*) FROM qa_commitments WHERE project_id=".$projectId,0
        ),
        'progress_reports'=>(int)qa_db_scalar(
            "SELECT COUNT(*) FROM qa_progress_reports WHERE project_id=".$projectId,0
        ),
        'evidences'=>(int)qa_db_scalar(
            "SELECT COUNT(*) FROM qa_evidences WHERE project_id=".$projectId,0
        ),
        'project_results'=>(int)qa_db_scalar(
            "SELECT COUNT(*) FROM qa_project_results WHERE project_id=".$projectId,0
        )
    );
}

function qa_project_change_has_execution_data($projectId)
{
    $s=qa_project_change_execution_summary($projectId);
    return $s['actual_expense']>0.005 ||
           $s['commitments']>0 ||
           $s['progress_reports']>0 ||
           $s['evidences']>0 ||
           $s['project_results']>0;
}

function qa_project_change_resolution_mode($project, $requestType)
{
    if($requestType==='cancellation') return 'cancel_project';

    if($requestType!=='correction' || !$project) return '';

    if(in_array($project['status_code'],array(
        'SUBMITTED','PLAN_REVIEW','BUDGET_REVIEW','PENDING_APPROVAL','APPROVED'
    ),true) && !qa_project_change_has_execution_data((int)$project['project_id'])) {
        return 'reopen_revision';
    }

    return 'amendment_record';
}

function qa_project_change_pending_request($projectId)
{
    if(!qa_project_change_table_ready()) return null;

    $db=qa_db();
    $stmt=$db->prepare(
        "SELECT *
         FROM qa_project_change_requests
         WHERE project_id=? AND request_status='pending'
         ORDER BY request_id DESC
         LIMIT 1"
    );
    if(!$stmt) return null;
    $stmt->bind_param('i',$projectId);
    $stmt->execute();
    $result=$stmt->get_result();
    $row=$result?$result->fetch_assoc():null;
    if($result)$result->free();
    $stmt->close();
    return $row;
}

function qa_project_change_has_pending($projectId)
{
    return qa_project_change_pending_request($projectId)!==null;
}

function qa_project_change_list_for_project($projectId)
{
    if(!qa_project_change_table_ready()) return array();

    $db=qa_db();
    $rows=array();
    $stmt=$db->prepare(
        "SELECT r.*,
                CONCAT(COALESCE(req.prefix,''),COALESCE(req.first_name,''),' ',COALESCE(req.last_name,'')) AS requester_name,
                CONCAT(COALESCE(rv.prefix,''),COALESCE(rv.first_name,''),' ',COALESCE(rv.last_name,'')) AS reviewer_name
         FROM qa_project_change_requests r
         LEFT JOIN qa_users req ON req.user_id=r.requested_by
         LEFT JOIN qa_users rv ON rv.user_id=r.reviewed_by
         WHERE r.project_id=?
         ORDER BY r.request_id DESC"
    );
    if(!$stmt) return $rows;
    $stmt->bind_param('i',$projectId);
    $stmt->execute();
    $result=$stmt->get_result();
    if($result){
        while($row=$result->fetch_assoc())$rows[]=$row;
        $result->free();
    }
    $stmt->close();
    return $rows;
}
