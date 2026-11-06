<?php
/*
 * Shared project/workflow helpers
 * Compatible with PHP 5.6
 */

function qa_project_stage_label($stage)
{
    $labels = array(
        'owner' => 'ผู้จัดทำโครงการ',
        'plan' => 'งานแผน',
        'budget' => 'งานงบประมาณ',
        'director' => 'ผู้อำนวยการ',
        'correction' => 'การแก้ไข / ยกเลิกโครงการ',
        'system' => 'ระบบ'
    );
    return isset($labels[$stage]) ? $labels[$stage] : $stage;
}

function qa_project_stage_description($stage)
{
    $labels = array(
        'plan' => 'ตรวจสอบความสอดคล้องกับแผน พันธกิจ กลยุทธ์ เป้าหมาย และตัวชี้วัด',
        'budget' => 'ตรวจสอบแหล่งงบประมาณ รายการค่าใช้จ่าย และวงเงินที่เสนอขอ',
        'director' => 'พิจารณาอนุมัติโครงการขั้นสุดท้าย'
    );
    return isset($labels[$stage]) ? $labels[$stage] : '';
}

function qa_project_stage_permission_code($stage)
{
    $map = array(
        'plan' => 'project.approve.plan',
        'budget' => 'project.approve.budget',
        'director' => 'project.approve.final'
    );
    return isset($map[$stage]) ? $map[$stage] : '';
}

function qa_project_stage_responsible_users($stage)
{
    $permissionCode = qa_project_stage_permission_code($stage);
    if ($permissionCode === '' || !function_exists('qa_permissions_ready') || !qa_permissions_ready()) {
        return array();
    }

    $db = qa_db();
    $rows = array();

    /* ปกติไม่แสดง admin เป็นผู้รับผิดชอบประจำขั้นตอน หากมีผู้ใช้ตามสายงานจริง */
    $sql =
        "SELECT u.user_id, u.prefix, u.first_name, u.last_name, u.position_name,
" .
        "       MIN(CASE WHEN r.role_code='admin' THEN 9 ELSE 1 END) AS role_rank
" .
        "FROM qa_users u
" .
        "INNER JOIN qa_user_roles ur ON ur.user_id=u.user_id
" .
        "INNER JOIN qa_roles r ON r.role_id=ur.role_id AND r.is_active=1
" .
        "INNER JOIN qa_role_permissions rp ON rp.role_id=r.role_id
" .
        "INNER JOIN qa_permissions p ON p.permission_id=rp.permission_id AND p.is_active=1
" .
        "WHERE u.status='active' AND p.permission_code=?
" .
        "GROUP BY u.user_id,u.prefix,u.first_name,u.last_name,u.position_name
" .
        "ORDER BY role_rank,u.first_name,u.last_name";

    $stmt = $db->prepare($sql);
    if (!$stmt) return array();
    $stmt->bind_param('s', $permissionCode);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        $result->free();
    }
    $stmt->close();

    /* ถ้ามีผู้ใช้สายงานจริง ให้ตัดบัญชีที่มีเพียง admin ออกจากรายการเพื่อไม่ให้รก */
    $normal = array();
    foreach ($rows as $row) {
        if ((int)$row['role_rank'] < 9) $normal[] = $row;
    }
    return !empty($normal) ? $normal : $rows;
}

function qa_project_decision_label($decision)
{
    $labels = array(
        'pending' => 'รอดำเนินการ',
        'approved' => 'ผ่าน',
        'revision' => 'ส่งกลับแก้ไข',
        'rejected' => 'ไม่อนุมัติ',
        'cancelled' => 'ยกเลิกตามคำขอ'
    );
    return isset($labels[$decision]) ? $labels[$decision] : $decision;
}

function qa_project_decision_label_for_stage($stage, $decision)
{
    if ($decision === 'pending') {
        if ($stage === 'plan') return 'รอตรวจสอบโดยงานแผน';
        if ($stage === 'budget') return 'รอตรวจสอบงบประมาณ';
        if ($stage === 'director') return 'รอผู้อำนวยการพิจารณา';
    }
    if ($decision === 'approved') {
        if ($stage === 'plan') return 'งานแผนตรวจสอบผ่าน';
        if ($stage === 'budget') return 'งานงบประมาณตรวจสอบผ่าน';
        if ($stage === 'director') return 'อนุมัติโครงการแล้ว';
    }
    if ($decision === 'revision') return 'ส่งกลับให้ผู้จัดทำแก้ไข';
    if ($decision === 'rejected') {
        return $stage === 'director' ? 'ไม่อนุมัติโครงการ' : 'ไม่ผ่านการตรวจสอบ';
    }
    return qa_project_decision_label($decision);
}

function qa_project_comment_type_label($type)
{
    $labels = array(
        'edit' => 'บันทึกแก้ไขข้อมูลโครงการ',
        'resubmit' => 'ส่งโครงการเข้ากระบวนการตรวจสอบ',
        'approval' => 'ความเห็นประกอบการตรวจสอบ / อนุมัติ',
        'revision' => 'ข้อเสนอแนะให้แก้ไข',
        'rejection' => 'เหตุผลที่ไม่ผ่าน / ไม่อนุมัติ',
        'correction_request' => 'คำขอแก้ไขโครงการ',
        'cancellation_request' => 'คำขอยกเลิกโครงการ',
        'change_withdrawn' => 'ถอนคำขอแก้ไข / ยกเลิก',
        'change_rejected' => 'ไม่อนุมัติคำขอแก้ไข / ยกเลิก',
        'correction_approved' => 'อนุมัติคำขอแก้ไขโครงการ',
        'cancellation_approved' => 'อนุมัติคำขอยกเลิกโครงการ'
    );
    return isset($labels[$type]) ? $labels[$type] : $type;
}

function qa_project_thai_datetime($value)
{
    $value = trim((string)$value);
    if ($value === '') return '-';
    $ts = strtotime($value);
    if ($ts === false) return $value;
    $months = array('', 'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
        'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม');
    $day = (int)date('j',$ts);
    $month = $months[(int)date('n',$ts)];
    $year = (int)date('Y',$ts) + 543;
    return $day.' '.$month.' '.$year.' เวลา '.date('H:i',$ts).' น.';
}

function qa_project_user_profile($userId)
{
    $db = qa_db();
    $stmt = $db->prepare(
        "SELECT user_id, username, prefix, first_name, last_name, position_name, primary_division_id
         FROM qa_users WHERE user_id=? LIMIT 1"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if ($result) $result->free();
    $stmt->close();
    return $row;
}

function qa_project_can_edit_row($project)
{
    $user = qa_current_user();
    if (!$user || !$project) return false;

    if (!in_array($project['status_code'], array('DRAFT', 'REVISION'), true)) {
        return false;
    }

    if (qa_user_can('project.edit_all')) {
        return true;
    }

    if (!qa_user_can('project.edit_own')) {
        return false;
    }

    return isset($project['owner_user_id']) &&
           (int)$project['owner_user_id'] === (int)$user['user_id'];
}

function qa_project_stage_allowed($stage)
{
    if ($stage === 'plan') return qa_user_can('project.approve.plan');
    if ($stage === 'budget') return qa_user_can('project.approve.budget');
    if ($stage === 'director') return qa_user_can('project.approve.final');
    return false;
}

function qa_project_add_comment($projectId, $stage, $type, $text, $userId)
{
    $text = trim((string)$text);
    if ($text === '') return true;

    $db = qa_db();
    $stmt = $db->prepare(
        "INSERT INTO qa_project_comments
        (project_id, comment_stage, comment_type, comment_text, created_by)
        VALUES (?, NULLIF(?,''), ?, ?, NULLIF(?,0))"
    );
    if (!$stmt) return false;
    $stmt->bind_param('isssi', $projectId, $stage, $type, $text, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function qa_project_add_history($projectId, $fromStatus, $toStatus, $userId, $comment)
{
    $db = qa_db();
    $stmt = $db->prepare(
        "INSERT INTO qa_project_status_history
        (project_id, from_status_code, to_status_code, changed_by, comment_text)
        VALUES (?, NULLIF(?,''), ?, NULLIF(?,0), NULLIF(?,''))"
    );
    if (!$stmt) return false;
    $stmt->bind_param('issis', $projectId, $fromStatus, $toStatus, $userId, $comment);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function qa_project_audit_action($action, $recordTable, $recordId, $oldData, $newData)
{
    $db = qa_db();
    $user = qa_current_user();
    $userId = $user ? (int)$user['user_id'] : 0;
    $oldJson = $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE);
    $newJson = $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE);
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '';

    $stmt = $db->prepare(
        "INSERT INTO qa_audit_logs
        (user_id, action_name, module_name, record_table, record_id, old_data, new_data, ip_address, user_agent)
        VALUES (NULLIF(?,0), ?, 'projects', ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) return false;
    $recordText = (string)$recordId;
    $stmt->bind_param('isssssss', $userId, $action, $recordTable, $recordText, $oldJson, $newJson, $ip, $ua);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function qa_project_ensure_approval_rows($projectId)
{
    $db = qa_db();
    $stages = array(
        array(1, 'plan'),
        array(2, 'budget'),
        array(3, 'director')
    );

    foreach ($stages as $stage) {
        $seq = (int)$stage[0];
        $code = $stage[1];

        $count = 0;
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM qa_project_approvals
             WHERE project_id=? AND sequence_no=?"
        );
        $stmt->bind_param('ii', $projectId, $seq);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ((int)$count === 0) {
            $stmt = $db->prepare(
                "INSERT INTO qa_project_approvals
                (project_id, sequence_no, approval_stage, decision_status)
                VALUES (?, ?, ?, 'pending')"
            );
            $stmt->bind_param('iis', $projectId, $seq, $code);
            if (!$stmt->execute()) {
                $stmt->close();
                return false;
            }
            $stmt->close();
        }
    }

    return true;
}

function qa_project_reset_approvals($projectId)
{
    $db = qa_db();
    if (!qa_project_ensure_approval_rows($projectId)) return false;

    $stmt = $db->prepare(
        "UPDATE qa_project_approvals
         SET approver_user_id=NULL,
             decision_status='pending',
             decision_comment=NULL,
             decision_at=NULL
         WHERE project_id=?"
    );
    $stmt->bind_param('i', $projectId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function qa_project_active_approval($projectId)
{
    $db = qa_db();

    $changeBlock = '';
    if (function_exists('qa_project_change_table_ready') && qa_project_change_table_ready()) {
        $changeBlock =
            " AND NOT EXISTS (
                SELECT 1
                FROM qa_project_change_requests cr
                WHERE cr.project_id=a.project_id
                  AND cr.request_status='pending'
              ) ";
    }

    $sql =
        "SELECT a.*
         FROM qa_project_approvals a
         WHERE a.project_id=?
           AND a.decision_status='pending'
           " . $changeBlock . "
           AND NOT EXISTS (
               SELECT 1
               FROM qa_project_approvals p
               WHERE p.project_id=a.project_id
                 AND p.sequence_no<a.sequence_no
                 AND p.decision_status<>'approved'
           )
         ORDER BY a.sequence_no
         LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if ($result) $result->free();
    $stmt->close();
    return $row;
}

function qa_project_pool_capacity($poolId, $divisionId, $yearId, $excludeProjectId)
{
    $db = qa_db();

    $allocation = 0.0;
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(current_allocation),0)
         FROM vw_qa_budget_allocation_summary
         WHERE budget_pool_id=? AND year_id=?
           AND allocation_type='division' AND division_id=?"
    );
    $stmt->bind_param('iii', $poolId, $yearId, $divisionId);
    $stmt->execute();
    $stmt->bind_result($allocation);
    $stmt->fetch();
    $stmt->close();

    $reserved = 0.0;
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(
            CASE
              WHEN bi.approved_amount > 0
                   AND pr.status_code IN ('PENDING_APPROVAL','APPROVED','IN_PROGRESS','WAITING_REPORT','COMPLETED','CLOSED')
              THEN bi.approved_amount
              ELSE bi.requested_amount
            END
        ),0)
         FROM qa_project_budget_items bi
         INNER JOIN qa_projects pr ON pr.project_id=bi.project_id
         WHERE bi.budget_pool_id=?
           AND pr.year_id=?
           AND pr.division_id=?
           AND pr.project_id<>?
           AND pr.status_code NOT IN ('DRAFT','REVISION','REJECTED','CANCELLED')"
    );
    $stmt->bind_param('iiii', $poolId, $yearId, $divisionId, $excludeProjectId);
    $stmt->execute();
    $stmt->bind_result($reserved);
    $stmt->fetch();
    $stmt->close();

    return array(
        'allocation' => (float)$allocation,
        'reserved_other' => (float)$reserved,
        'available' => (float)$allocation - (float)$reserved
    );
}
