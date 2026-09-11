<?php
/*
 * Shared project/workflow helpers
 * Compatible with PHP 5.6
 */

function qa_project_stage_label($stage)
{
    $labels = array(
        'plan' => 'งานแผน',
        'budget' => 'งานงบประมาณ',
        'director' => 'ผู้อำนวยการ'
    );
    return isset($labels[$stage]) ? $labels[$stage] : $stage;
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

    if (qa_user_has_role('admin')) {
        return true;
    }

    return isset($project['owner_user_id']) &&
           (int)$project['owner_user_id'] === (int)$user['user_id'];
}

function qa_project_stage_allowed($stage)
{
    if (qa_user_has_role('admin')) return true;
    if ($stage === 'plan' && qa_user_has_role('plan')) return true;
    if ($stage === 'budget' && qa_user_has_role('budget')) return true;
    if ($stage === 'director' && qa_user_has_role('director')) return true;
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
