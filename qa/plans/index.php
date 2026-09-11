<?php
$pageTitle = 'แผนพัฒนาคุณภาพ';
$pageHeading = 'แผนพัฒนาคุณภาพสถานศึกษา';
$pageDescription = 'กำหนดแผน วิสัยทัศน์ พันธกิจ ค่านิยม กลยุทธ์ จุดเน้น และเป้าหมายกลาง เพื่อใช้เชื่อมโยงกับโครงการและการประกันคุณภาพ';
$activeMenu = 'plans';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

$db = qa_db();
$user = qa_current_user();

$canManage = (
    qa_user_has_role('admin') ||
    qa_user_has_role('director') ||
    qa_user_has_role('plan')
);

$breadcrumbs = array(array('label' => 'แผนพัฒนาคุณภาพ'));

function plan_redirect($query)
{
    $url = qa_url('plans/index.php');
    if ($query !== '') {
        $url .= '?' . $query;
    }
    header('Location: ' . $url);
    exit;
}

function plan_flash($type, $text)
{
    $_SESSION['qa_plan_flash'] = array('type' => $type, 'text' => $text);
}

function plan_clean_text($value)
{
    return trim((string) $value);
}

function plan_nullable_int($value)
{
    $v = (int) $value;
    return $v > 0 ? $v : 0;
}

function plan_nullable_float($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $value = str_replace(',', '', $value);
    return is_numeric($value) ? (float) $value : false;
}

function plan_audit($action, $table, $recordId, $oldData, $newData)
{
    $db = qa_db();
    $user = qa_current_user();
    $userId = $user ? (int) $user['user_id'] : 0;
    $oldJson = $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE);
    $newJson = $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE);
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '';

    $stmt = $db->prepare(
        "INSERT INTO qa_audit_logs
        (user_id, action_name, module_name, record_table, record_id, old_data, new_data, ip_address, user_agent)
        VALUES (NULLIF(?,0), ?, 'plan', ?, ?, ?, ?, ?, ?)"
    );
    if ($stmt) {
        $recordText = (string) $recordId;
        $stmt->bind_param('isssssss', $userId, $action, $table, $recordText, $oldJson, $newJson, $ip, $ua);
        $stmt->execute();
        $stmt->close();
    }
}

function plan_fetch_row($table, $pk, $id)
{
    $db = qa_db();
    $allowed = array(
        'qa_plans' => 'plan_id',
        'qa_visions' => 'vision_id',
        'qa_missions' => 'mission_id',
        'qa_school_values' => 'value_id',
        'qa_strategies' => 'strategy_id',
        'qa_focus_areas' => 'focus_id',
        'qa_plan_targets' => 'target_id'
    );
    if (!isset($allowed[$table]) || $allowed[$table] !== $pk) {
        return null;
    }

    $stmt = $db->prepare("SELECT * FROM " . $table . " WHERE " . $pk . " = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    return $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        http_response_code(403);
        die('คุณไม่มีสิทธิ์แก้ไขแผนพัฒนาคุณภาพ');
    }

    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!qa_verify_csrf($token)) {
        http_response_code(400);
        die('CSRF token ไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $selectedPlanId = isset($_POST['selected_plan_id']) ? (int) $_POST['selected_plan_id'] : 0;
    $userId = $user ? (int) $user['user_id'] : 0;

    /* -------------------- PLAN -------------------- */
    if ($action === 'create_plan' || $action === 'update_plan') {
        $planId = isset($_POST['plan_id']) ? (int) $_POST['plan_id'] : 0;
        $planCode = plan_clean_text(isset($_POST['plan_code']) ? $_POST['plan_code'] : '');
        $planName = plan_clean_text(isset($_POST['plan_name']) ? $_POST['plan_name'] : '');
        $planType = plan_clean_text(isset($_POST['plan_type']) ? $_POST['plan_type'] : 'development');
        $startYear = plan_nullable_int(isset($_POST['start_year_be']) ? $_POST['start_year_be'] : 0);
        $endYear = plan_nullable_int(isset($_POST['end_year_be']) ? $_POST['end_year_be'] : 0);
        $description = plan_clean_text(isset($_POST['description']) ? $_POST['description'] : '');
        $status = plan_clean_text(isset($_POST['status']) ? $_POST['status'] : 'active');

        $errors = array();
        if ($planCode === '') $errors[] = 'กรุณาระบุรหัสแผน';
        if ($planName === '') $errors[] = 'กรุณาระบุชื่อแผน';
        if ($startYear > 0 && $endYear > 0 && $endYear < $startYear) $errors[] = 'ปีสิ้นสุดต้องไม่น้อยกว่าปีเริ่มต้น';

        if (!empty($errors)) {
            plan_flash('danger', implode(' / ', $errors));
            plan_redirect($selectedPlanId > 0 ? 'plan=' . $selectedPlanId : '');
        }

        $startYearValue = $startYear > 0 ? $startYear : null;
        $endYearValue = $endYear > 0 ? $endYear : null;

        if ($action === 'create_plan') {
            $stmt = $db->prepare(
                "INSERT INTO qa_plans
                (plan_code, plan_name, plan_type, start_year_be, end_year_be, description, status, created_by)
                VALUES (?, ?, ?, ?, ?, NULLIF(?,''), ?, NULLIF(?,0))"
            );
            $stmt->bind_param('sssiissi', $planCode, $planName, $planType, $startYearValue, $endYearValue, $description, $status, $userId);

            if ($stmt->execute()) {
                $newId = $stmt->insert_id;
                $stmt->close();
                plan_audit('create_plan', 'qa_plans', $newId, null, array(
                    'plan_code' => $planCode,
                    'plan_name' => $planName,
                    'plan_type' => $planType,
                    'start_year_be' => $startYearValue,
                    'end_year_be' => $endYearValue,
                    'description' => $description,
                    'status' => $status
                ));
                plan_flash('success', 'เพิ่มแผนพัฒนาคุณภาพเรียบร้อยแล้ว');
                plan_redirect('plan=' . $newId);
            } else {
                $errorText = $db->errno == 1062 ? 'รหัสแผนนี้มีอยู่แล้ว' : 'ไม่สามารถเพิ่มแผนได้';
                $stmt->close();
                plan_flash('danger', $errorText);
                plan_redirect('');
            }
        } else {
            $oldRow = plan_fetch_row('qa_plans', 'plan_id', $planId);
            if (!$oldRow) {
                plan_flash('danger', 'ไม่พบแผนที่ต้องการแก้ไข');
                plan_redirect('');
            }

            $stmt = $db->prepare(
                "UPDATE qa_plans
                 SET plan_code=?, plan_name=?, plan_type=?, start_year_be=?, end_year_be=?,
                     description=NULLIF(?,''), status=?
                 WHERE plan_id=?"
            );
            $stmt->bind_param('sssiissi', $planCode, $planName, $planType, $startYearValue, $endYearValue, $description, $status, $planId);

            if ($stmt->execute()) {
                $stmt->close();
                plan_audit('update_plan', 'qa_plans', $planId, $oldRow, array(
                    'plan_code' => $planCode,
                    'plan_name' => $planName,
                    'plan_type' => $planType,
                    'start_year_be' => $startYearValue,
                    'end_year_be' => $endYearValue,
                    'description' => $description,
                    'status' => $status
                ));
                plan_flash('success', 'แก้ไขข้อมูลแผนเรียบร้อยแล้ว');
                plan_redirect('plan=' . $planId);
            } else {
                $errorText = $db->errno == 1062 ? 'รหัสแผนนี้ซ้ำกับรายการอื่น' : 'ไม่สามารถแก้ไขแผนได้';
                $stmt->close();
                plan_flash('danger', $errorText);
                plan_redirect('plan=' . $planId . '&edit=plan');
            }
        }
    }

    if ($action === 'delete_plan') {
        $planId = isset($_POST['plan_id']) ? (int) $_POST['plan_id'] : 0;
        $oldRow = plan_fetch_row('qa_plans', 'plan_id', $planId);
        if (!$oldRow) {
            plan_flash('danger', 'ไม่พบแผนที่ต้องการลบ');
            plan_redirect('');
        }

        $childCount = 0;
        $queries = array(
            "SELECT COUNT(*) FROM qa_visions WHERE plan_id=?",
            "SELECT COUNT(*) FROM qa_missions WHERE plan_id=?",
            "SELECT COUNT(*) FROM qa_school_values WHERE plan_id=?",
            "SELECT COUNT(*) FROM qa_strategies WHERE plan_id=?",
            "SELECT COUNT(*) FROM qa_focus_areas WHERE plan_id=?",
            "SELECT COUNT(*) FROM qa_plan_targets WHERE plan_id=?",
            "SELECT COUNT(*) FROM qa_projects WHERE plan_id=?"
        );
        foreach ($queries as $sql) {
            $count = 0;
            $stmt = $db->prepare($sql);
            $stmt->bind_param('i', $planId);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();
            $childCount += (int) $count;
        }

        if ($childCount > 0) {
            plan_flash('danger', 'ลบแผนไม่ได้ เนื่องจากมีข้อมูลวิสัยทัศน์/พันธกิจ/ค่านิยม/กลยุทธ์/จุดเน้น/เป้าหมาย หรือมีโครงการอ้างอิงอยู่');
            plan_redirect('plan=' . $planId);
        }

        $stmt = $db->prepare("DELETE FROM qa_plans WHERE plan_id=?");
        $stmt->bind_param('i', $planId);
        if ($stmt->execute()) {
            $stmt->close();
            plan_audit('delete_plan', 'qa_plans', $planId, $oldRow, null);
            plan_flash('success', 'ลบแผนเรียบร้อยแล้ว');
            plan_redirect('');
        } else {
            $stmt->close();
            plan_flash('danger', 'ไม่สามารถลบแผนได้');
            plan_redirect('plan=' . $planId);
        }
    }

    /* -------------------- VISION -------------------- */
    if ($action === 'save_vision') {
        $planId = $selectedPlanId;
        $visionId = isset($_POST['vision_id']) ? (int) $_POST['vision_id'] : 0;
        $visionText = plan_clean_text(isset($_POST['vision_text']) ? $_POST['vision_text'] : '');

        if ($planId <= 0 || $visionText === '') {
            plan_flash('danger', 'กรุณาระบุข้อความวิสัยทัศน์');
            plan_redirect('plan=' . $planId);
        }

        if ($visionId > 0) {
            $oldRow = plan_fetch_row('qa_visions', 'vision_id', $visionId);
            $stmt = $db->prepare("UPDATE qa_visions SET vision_text=? WHERE vision_id=? AND plan_id=?");
            $stmt->bind_param('sii', $visionText, $visionId, $planId);
            if ($stmt->execute()) {
                $stmt->close();
                plan_audit('update_vision', 'qa_visions', $visionId, $oldRow, array('vision_text' => $visionText));
                plan_flash('success', 'แก้ไขวิสัยทัศน์เรียบร้อยแล้ว');
            } else {
                $stmt->close();
                plan_flash('danger', 'ไม่สามารถแก้ไขวิสัยทัศน์ได้');
            }
        } else {
            $stmt = $db->prepare("INSERT INTO qa_visions (plan_id, vision_text, sort_order, is_active) VALUES (?, ?, 1, 1)");
            $stmt->bind_param('is', $planId, $visionText);
            if ($stmt->execute()) {
                $newId = $stmt->insert_id;
                $stmt->close();
                plan_audit('create_vision', 'qa_visions', $newId, null, array('plan_id' => $planId, 'vision_text' => $visionText));
                plan_flash('success', 'เพิ่มวิสัยทัศน์เรียบร้อยแล้ว');
            } else {
                $stmt->close();
                plan_flash('danger', 'ไม่สามารถเพิ่มวิสัยทัศน์ได้');
            }
        }
        plan_redirect('plan=' . $planId);
    }

    if ($action === 'delete_vision') {
        $planId = $selectedPlanId;
        $visionId = isset($_POST['vision_id']) ? (int) $_POST['vision_id'] : 0;
        $oldRow = plan_fetch_row('qa_visions', 'vision_id', $visionId);
        $stmt = $db->prepare("DELETE FROM qa_visions WHERE vision_id=? AND plan_id=?");
        $stmt->bind_param('ii', $visionId, $planId);
        if ($stmt->execute()) {
            $stmt->close();
            plan_audit('delete_vision', 'qa_visions', $visionId, $oldRow, null);
            plan_flash('success', 'ลบวิสัยทัศน์เรียบร้อยแล้ว');
        } else {
            $stmt->close();
            plan_flash('danger', 'ไม่สามารถลบวิสัยทัศน์ได้');
        }
        plan_redirect('plan=' . $planId);
    }

    /* -------------------- MISSION -------------------- */
    if ($action === 'save_mission') {
        $planId = $selectedPlanId;
        $missionId = isset($_POST['mission_id']) ? (int) $_POST['mission_id'] : 0;
        $missionCode = plan_clean_text(isset($_POST['mission_code']) ? $_POST['mission_code'] : '');
        $missionText = plan_clean_text(isset($_POST['mission_text']) ? $_POST['mission_text'] : '');

        if ($planId <= 0 || $missionCode === '' || $missionText === '') {
            plan_flash('danger', 'กรุณาระบุรหัสและข้อความพันธกิจ');
            plan_redirect('plan=' . $planId);
        }

        if ($missionId > 0) {
            $oldRow = plan_fetch_row('qa_missions', 'mission_id', $missionId);
            $stmt = $db->prepare("UPDATE qa_missions SET mission_code=?, mission_text=? WHERE mission_id=? AND plan_id=?");
            $stmt->bind_param('ssii', $missionCode, $missionText, $missionId, $planId);
            $ok = $stmt->execute();
            $err = $db->errno;
            $stmt->close();

            if ($ok) {
                plan_audit('update_mission', 'qa_missions', $missionId, $oldRow, array('mission_code'=>$missionCode, 'mission_text'=>$missionText));
                plan_flash('success', 'แก้ไขพันธกิจเรียบร้อยแล้ว');
            } else {
                plan_flash('danger', $err == 1062 ? 'รหัสพันธกิจซ้ำในแผนนี้' : 'ไม่สามารถแก้ไขพันธกิจได้');
            }
        } else {
            $sortOrder = (int) qa_db_scalar("SELECT COALESCE(MAX(sort_order),0)+1 FROM qa_missions WHERE plan_id=" . $planId, 1);
            $stmt = $db->prepare("INSERT INTO qa_missions (plan_id, mission_code, mission_text, sort_order, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param('issi', $planId, $missionCode, $missionText, $sortOrder);
            $ok = $stmt->execute();
            $newId = $stmt->insert_id;
            $err = $db->errno;
            $stmt->close();

            if ($ok) {
                plan_audit('create_mission', 'qa_missions', $newId, null, array('plan_id'=>$planId, 'mission_code'=>$missionCode, 'mission_text'=>$missionText));
                plan_flash('success', 'เพิ่มพันธกิจเรียบร้อยแล้ว');
            } else {
                plan_flash('danger', $err == 1062 ? 'รหัสพันธกิจซ้ำในแผนนี้' : 'ไม่สามารถเพิ่มพันธกิจได้');
            }
        }
        plan_redirect('plan=' . $planId);
    }

    if ($action === 'delete_mission') {
        $planId = $selectedPlanId;
        $missionId = isset($_POST['mission_id']) ? (int) $_POST['mission_id'] : 0;
        $oldRow = plan_fetch_row('qa_missions', 'mission_id', $missionId);

        $projectLinks = (int) qa_db_scalar("SELECT COUNT(*) FROM qa_project_mission_links WHERE mission_id=" . $missionId, 0);
        if ($projectLinks > 0) {
            plan_flash('danger', 'ลบพันธกิจไม่ได้ เนื่องจากมีโครงการเชื่อมโยงอยู่');
            plan_redirect('plan=' . $planId);
        }

        $stmt = $db->prepare("DELETE FROM qa_missions WHERE mission_id=? AND plan_id=?");
        $stmt->bind_param('ii', $missionId, $planId);
        if ($stmt->execute()) {
            $stmt->close();
            plan_audit('delete_mission', 'qa_missions', $missionId, $oldRow, null);
            plan_flash('success', 'ลบพันธกิจเรียบร้อยแล้ว');
        } else {
            $stmt->close();
            plan_flash('danger', 'ไม่สามารถลบพันธกิจได้');
        }
        plan_redirect('plan=' . $planId);
    }

    /* -------------------- SCHOOL VALUE -------------------- */
    if ($action === 'save_value') {
        $planId = $selectedPlanId;
        $valueId = isset($_POST['value_id']) ? (int) $_POST['value_id'] : 0;
        $valueCode = plan_clean_text(isset($_POST['value_code']) ? $_POST['value_code'] : '');
        $valueName = plan_clean_text(isset($_POST['value_name']) ? $_POST['value_name'] : '');
        $description = plan_clean_text(isset($_POST['value_description']) ? $_POST['value_description'] : '');

        if ($planId <= 0 || $valueCode === '' || $valueName === '') {
            plan_flash('danger', 'กรุณาระบุรหัสและชื่อค่านิยม');
            plan_redirect('plan=' . $planId);
        }

        if ($valueId > 0) {
            $oldRow = plan_fetch_row('qa_school_values', 'value_id', $valueId);
            $stmt = $db->prepare(
                "UPDATE qa_school_values
                 SET value_code=?, value_name=?, description=NULLIF(?,'')
                 WHERE value_id=? AND plan_id=?"
            );
            $stmt->bind_param('sssii', $valueCode, $valueName, $description, $valueId, $planId);
            $ok = $stmt->execute();
            $err = $db->errno;
            $stmt->close();

            if ($ok) {
                plan_audit('update_school_value', 'qa_school_values', $valueId, $oldRow, array(
                    'value_code'=>$valueCode,
                    'value_name'=>$valueName,
                    'description'=>$description
                ));
                plan_flash('success', 'แก้ไขค่านิยมเรียบร้อยแล้ว');
            } else {
                plan_flash('danger', $err == 1062 ? 'รหัสค่านิยมซ้ำในแผนนี้' : 'ไม่สามารถแก้ไขค่านิยมได้');
            }
        } else {
            $sortOrder = (int) qa_db_scalar(
                "SELECT COALESCE(MAX(sort_order),0)+1 FROM qa_school_values WHERE plan_id=" . $planId,
                1
            );
            $stmt = $db->prepare(
                "INSERT INTO qa_school_values
                (plan_id, value_code, value_name, description, sort_order, is_active)
                VALUES (?, ?, ?, NULLIF(?,''), ?, 1)"
            );
            $stmt->bind_param('isssi', $planId, $valueCode, $valueName, $description, $sortOrder);
            $ok = $stmt->execute();
            $newId = $stmt->insert_id;
            $err = $db->errno;
            $stmt->close();

            if ($ok) {
                plan_audit('create_school_value', 'qa_school_values', $newId, null, array(
                    'plan_id'=>$planId,
                    'value_code'=>$valueCode,
                    'value_name'=>$valueName,
                    'description'=>$description
                ));
                plan_flash('success', 'เพิ่มค่านิยมเรียบร้อยแล้ว');
            } else {
                plan_flash('danger', $err == 1062 ? 'รหัสค่านิยมซ้ำในแผนนี้' : 'ไม่สามารถเพิ่มค่านิยมได้');
            }
        }
        plan_redirect('plan=' . $planId);
    }

    if ($action === 'delete_value') {
        $planId = $selectedPlanId;
        $valueId = isset($_POST['value_id']) ? (int) $_POST['value_id'] : 0;
        $oldRow = plan_fetch_row('qa_school_values', 'value_id', $valueId);

        $stmt = $db->prepare("DELETE FROM qa_school_values WHERE value_id=? AND plan_id=?");
        $stmt->bind_param('ii', $valueId, $planId);
        if ($stmt->execute()) {
            $stmt->close();
            plan_audit('delete_school_value', 'qa_school_values', $valueId, $oldRow, null);
            plan_flash('success', 'ลบค่านิยมเรียบร้อยแล้ว');
        } else {
            $stmt->close();
            plan_flash('danger', 'ไม่สามารถลบค่านิยมได้');
        }
        plan_redirect('plan=' . $planId);
    }

    /* -------------------- STRATEGY -------------------- */
    if ($action === 'save_strategy') {
        $planId = $selectedPlanId;
        $strategyId = isset($_POST['strategy_id']) ? (int) $_POST['strategy_id'] : 0;
        $missionId = plan_nullable_int(isset($_POST['mission_id']) ? $_POST['mission_id'] : 0);
        $strategyCode = plan_clean_text(isset($_POST['strategy_code']) ? $_POST['strategy_code'] : '');
        $strategyName = plan_clean_text(isset($_POST['strategy_name']) ? $_POST['strategy_name'] : '');
        $description = plan_clean_text(isset($_POST['strategy_description']) ? $_POST['strategy_description'] : '');

        if ($planId <= 0 || $strategyCode === '' || $strategyName === '') {
            plan_flash('danger', 'กรุณาระบุรหัสและชื่อกลยุทธ์');
            plan_redirect('plan=' . $planId);
        }

        $missionValue = $missionId > 0 ? $missionId : null;

        if ($strategyId > 0) {
            $oldRow = plan_fetch_row('qa_strategies', 'strategy_id', $strategyId);
            $stmt = $db->prepare(
                "UPDATE qa_strategies SET mission_id=?, strategy_code=?, strategy_name=?, description=NULLIF(?,'')
                 WHERE strategy_id=? AND plan_id=?"
            );
            $stmt->bind_param('isssii', $missionValue, $strategyCode, $strategyName, $description, $strategyId, $planId);
            $ok = $stmt->execute();
            $err = $db->errno;
            $stmt->close();

            if ($ok) {
                plan_audit('update_strategy', 'qa_strategies', $strategyId, $oldRow, array(
                    'mission_id'=>$missionValue, 'strategy_code'=>$strategyCode, 'strategy_name'=>$strategyName, 'description'=>$description
                ));
                plan_flash('success', 'แก้ไขกลยุทธ์เรียบร้อยแล้ว');
            } else {
                plan_flash('danger', $err == 1062 ? 'รหัสกลยุทธ์ซ้ำในแผนนี้' : 'ไม่สามารถแก้ไขกลยุทธ์ได้');
            }
        } else {
            $sortOrder = (int) qa_db_scalar("SELECT COALESCE(MAX(sort_order),0)+1 FROM qa_strategies WHERE plan_id=" . $planId, 1);
            $stmt = $db->prepare(
                "INSERT INTO qa_strategies
                (plan_id, mission_id, strategy_code, strategy_name, description, sort_order, is_active)
                VALUES (?, ?, ?, ?, NULLIF(?,''), ?, 1)"
            );
            $stmt->bind_param('iisssi', $planId, $missionValue, $strategyCode, $strategyName, $description, $sortOrder);
            $ok = $stmt->execute();
            $newId = $stmt->insert_id;
            $err = $db->errno;
            $stmt->close();

            if ($ok) {
                plan_audit('create_strategy', 'qa_strategies', $newId, null, array(
                    'plan_id'=>$planId, 'mission_id'=>$missionValue, 'strategy_code'=>$strategyCode, 'strategy_name'=>$strategyName, 'description'=>$description
                ));
                plan_flash('success', 'เพิ่มกลยุทธ์เรียบร้อยแล้ว');
            } else {
                plan_flash('danger', $err == 1062 ? 'รหัสกลยุทธ์ซ้ำในแผนนี้' : 'ไม่สามารถเพิ่มกลยุทธ์ได้');
            }
        }
        plan_redirect('plan=' . $planId);
    }

    if ($action === 'delete_strategy') {
        $planId = $selectedPlanId;
        $strategyId = isset($_POST['strategy_id']) ? (int) $_POST['strategy_id'] : 0;
        $oldRow = plan_fetch_row('qa_strategies', 'strategy_id', $strategyId);

        $projectLinks = (int) qa_db_scalar("SELECT COUNT(*) FROM qa_project_strategy_links WHERE strategy_id=" . $strategyId, 0);
        $targetLinks = (int) qa_db_scalar("SELECT COUNT(*) FROM qa_plan_targets WHERE strategy_id=" . $strategyId, 0);
        if ($projectLinks > 0 || $targetLinks > 0) {
            plan_flash('danger', 'ลบกลยุทธ์ไม่ได้ เนื่องจากมีโครงการหรือเป้าหมายเชื่อมโยงอยู่');
            plan_redirect('plan=' . $planId);
        }

        $stmt = $db->prepare("DELETE FROM qa_strategies WHERE strategy_id=? AND plan_id=?");
        $stmt->bind_param('ii', $strategyId, $planId);
        if ($stmt->execute()) {
            $stmt->close();
            plan_audit('delete_strategy', 'qa_strategies', $strategyId, $oldRow, null);
            plan_flash('success', 'ลบกลยุทธ์เรียบร้อยแล้ว');
        } else {
            $stmt->close();
            plan_flash('danger', 'ไม่สามารถลบกลยุทธ์ได้');
        }
        plan_redirect('plan=' . $planId);
    }

    /* -------------------- FOCUS -------------------- */
    if ($action === 'save_focus') {
        $planId = $selectedPlanId;
        $focusId = isset($_POST['focus_id']) ? (int) $_POST['focus_id'] : 0;
        $focusCode = plan_clean_text(isset($_POST['focus_code']) ? $_POST['focus_code'] : '');
        $focusName = plan_clean_text(isset($_POST['focus_name']) ? $_POST['focus_name'] : '');
        $description = plan_clean_text(isset($_POST['focus_description']) ? $_POST['focus_description'] : '');

        if ($planId <= 0 || $focusCode === '' || $focusName === '') {
            plan_flash('danger', 'กรุณาระบุรหัสและชื่อจุดเน้น');
            plan_redirect('plan=' . $planId);
        }

        if ($focusId > 0) {
            $oldRow = plan_fetch_row('qa_focus_areas', 'focus_id', $focusId);
            $stmt = $db->prepare(
                "UPDATE qa_focus_areas SET focus_code=?, focus_name=?, description=NULLIF(?,'')
                 WHERE focus_id=? AND plan_id=?"
            );
            $stmt->bind_param('sssii', $focusCode, $focusName, $description, $focusId, $planId);
            $ok = $stmt->execute();
            $err = $db->errno;
            $stmt->close();

            if ($ok) {
                plan_audit('update_focus', 'qa_focus_areas', $focusId, $oldRow, array(
                    'focus_code'=>$focusCode, 'focus_name'=>$focusName, 'description'=>$description
                ));
                plan_flash('success', 'แก้ไขจุดเน้นเรียบร้อยแล้ว');
            } else {
                plan_flash('danger', $err == 1062 ? 'รหัสจุดเน้นซ้ำในแผนนี้' : 'ไม่สามารถแก้ไขจุดเน้นได้');
            }
        } else {
            $sortOrder = (int) qa_db_scalar("SELECT COALESCE(MAX(sort_order),0)+1 FROM qa_focus_areas WHERE plan_id=" . $planId, 1);
            $stmt = $db->prepare(
                "INSERT INTO qa_focus_areas
                (plan_id, focus_code, focus_name, description, sort_order, is_active)
                VALUES (?, ?, ?, NULLIF(?,''), ?, 1)"
            );
            $stmt->bind_param('isssi', $planId, $focusCode, $focusName, $description, $sortOrder);
            $ok = $stmt->execute();
            $newId = $stmt->insert_id;
            $err = $db->errno;
            $stmt->close();

            if ($ok) {
                plan_audit('create_focus', 'qa_focus_areas', $newId, null, array(
                    'plan_id'=>$planId, 'focus_code'=>$focusCode, 'focus_name'=>$focusName, 'description'=>$description
                ));
                plan_flash('success', 'เพิ่มจุดเน้นเรียบร้อยแล้ว');
            } else {
                plan_flash('danger', $err == 1062 ? 'รหัสจุดเน้นซ้ำในแผนนี้' : 'ไม่สามารถเพิ่มจุดเน้นได้');
            }
        }
        plan_redirect('plan=' . $planId);
    }

    if ($action === 'delete_focus') {
        $planId = $selectedPlanId;
        $focusId = isset($_POST['focus_id']) ? (int) $_POST['focus_id'] : 0;
        $oldRow = plan_fetch_row('qa_focus_areas', 'focus_id', $focusId);

        $projectLinks = (int) qa_db_scalar("SELECT COUNT(*) FROM qa_project_focus_links WHERE focus_id=" . $focusId, 0);
        $targetLinks = (int) qa_db_scalar("SELECT COUNT(*) FROM qa_plan_targets WHERE focus_id=" . $focusId, 0);
        if ($projectLinks > 0 || $targetLinks > 0) {
            plan_flash('danger', 'ลบจุดเน้นไม่ได้ เนื่องจากมีโครงการหรือเป้าหมายเชื่อมโยงอยู่');
            plan_redirect('plan=' . $planId);
        }

        $stmt = $db->prepare("DELETE FROM qa_focus_areas WHERE focus_id=? AND plan_id=?");
        $stmt->bind_param('ii', $focusId, $planId);
        if ($stmt->execute()) {
            $stmt->close();
            plan_audit('delete_focus', 'qa_focus_areas', $focusId, $oldRow, null);
            plan_flash('success', 'ลบจุดเน้นเรียบร้อยแล้ว');
        } else {
            $stmt->close();
            plan_flash('danger', 'ไม่สามารถลบจุดเน้นได้');
        }
        plan_redirect('plan=' . $planId);
    }

    /* -------------------- TARGET -------------------- */
    if ($action === 'save_target') {
        $planId = $selectedPlanId;
        $targetId = isset($_POST['target_id']) ? (int) $_POST['target_id'] : 0;
        $strategyId = plan_nullable_int(isset($_POST['target_strategy_id']) ? $_POST['target_strategy_id'] : 0);
        $focusId = plan_nullable_int(isset($_POST['target_focus_id']) ? $_POST['target_focus_id'] : 0);
        $targetCode = plan_clean_text(isset($_POST['target_code']) ? $_POST['target_code'] : '');
        $targetName = plan_clean_text(isset($_POST['target_name']) ? $_POST['target_name'] : '');
        $indicatorText = plan_clean_text(isset($_POST['indicator_text']) ? $_POST['indicator_text'] : '');
        $targetValue = plan_nullable_float(isset($_POST['target_value']) ? $_POST['target_value'] : '');
        $targetUnit = plan_clean_text(isset($_POST['target_unit']) ? $_POST['target_unit'] : '');
        $baselineValue = plan_nullable_float(isset($_POST['baseline_value']) ? $_POST['baseline_value'] : '');

        if ($targetValue === false || $baselineValue === false) {
            plan_flash('danger', 'ค่าเป้าหมายหรือค่าฐานต้องเป็นตัวเลข');
            plan_redirect('plan=' . $planId);
        }
        if ($planId <= 0 || $targetName === '') {
            plan_flash('danger', 'กรุณาระบุชื่อเป้าหมาย');
            plan_redirect('plan=' . $planId);
        }

        $strategyValue = $strategyId > 0 ? $strategyId : null;
        $focusValue = $focusId > 0 ? $focusId : null;

        if ($targetId > 0) {
            $oldRow = plan_fetch_row('qa_plan_targets', 'target_id', $targetId);
            $stmt = $db->prepare(
                "UPDATE qa_plan_targets
                 SET strategy_id=?, focus_id=?, target_code=NULLIF(?,''), target_name=?,
                     indicator_text=NULLIF(?,''), target_value=?, target_unit=NULLIF(?,''),
                     baseline_value=?
                 WHERE target_id=? AND plan_id=?"
            );
            $stmt->bind_param(
                'iisssdsdii',
                $strategyValue, $focusValue, $targetCode, $targetName, $indicatorText,
                $targetValue, $targetUnit, $baselineValue, $targetId, $planId
            );
            $ok = $stmt->execute();
            $stmt->close();

            if ($ok) {
                plan_audit('update_target', 'qa_plan_targets', $targetId, $oldRow, array(
                    'strategy_id'=>$strategyValue, 'focus_id'=>$focusValue, 'target_code'=>$targetCode,
                    'target_name'=>$targetName, 'indicator_text'=>$indicatorText, 'target_value'=>$targetValue,
                    'target_unit'=>$targetUnit, 'baseline_value'=>$baselineValue
                ));
                plan_flash('success', 'แก้ไขเป้าหมายเรียบร้อยแล้ว');
            } else {
                plan_flash('danger', 'ไม่สามารถแก้ไขเป้าหมายได้');
            }
        } else {
            $sortOrder = (int) qa_db_scalar("SELECT COALESCE(MAX(sort_order),0)+1 FROM qa_plan_targets WHERE plan_id=" . $planId, 1);
            $stmt = $db->prepare(
                "INSERT INTO qa_plan_targets
                (plan_id, strategy_id, focus_id, target_code, target_name, indicator_text,
                 target_value, target_unit, baseline_value, sort_order, is_active)
                VALUES (?, ?, ?, NULLIF(?,''), ?, NULLIF(?,''), ?, NULLIF(?,''), ?, ?, 1)"
            );
            $stmt->bind_param(
                'iiisssdsdi',
                $planId, $strategyValue, $focusValue, $targetCode, $targetName, $indicatorText,
                $targetValue, $targetUnit, $baselineValue, $sortOrder
            );
            $ok = $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();

            if ($ok) {
                plan_audit('create_target', 'qa_plan_targets', $newId, null, array(
                    'plan_id'=>$planId, 'strategy_id'=>$strategyValue, 'focus_id'=>$focusValue,
                    'target_code'=>$targetCode, 'target_name'=>$targetName, 'indicator_text'=>$indicatorText,
                    'target_value'=>$targetValue, 'target_unit'=>$targetUnit, 'baseline_value'=>$baselineValue
                ));
                plan_flash('success', 'เพิ่มเป้าหมายเรียบร้อยแล้ว');
            } else {
                plan_flash('danger', 'ไม่สามารถเพิ่มเป้าหมายได้');
            }
        }
        plan_redirect('plan=' . $planId);
    }

    if ($action === 'delete_target') {
        $planId = $selectedPlanId;
        $targetId = isset($_POST['target_id']) ? (int) $_POST['target_id'] : 0;
        $oldRow = plan_fetch_row('qa_plan_targets', 'target_id', $targetId);

        $stmt = $db->prepare("DELETE FROM qa_plan_targets WHERE target_id=? AND plan_id=?");
        $stmt->bind_param('ii', $targetId, $planId);
        if ($stmt->execute()) {
            $stmt->close();
            plan_audit('delete_target', 'qa_plan_targets', $targetId, $oldRow, null);
            plan_flash('success', 'ลบเป้าหมายเรียบร้อยแล้ว');
        } else {
            $stmt->close();
            plan_flash('danger', 'ไม่สามารถลบเป้าหมายได้');
        }
        plan_redirect('plan=' . $planId);
    }
}

/* -------------------- LOAD DATA -------------------- */

$message = '';
$messageType = 'success';
if (isset($_SESSION['qa_plan_flash']) && is_array($_SESSION['qa_plan_flash'])) {
    $messageType = isset($_SESSION['qa_plan_flash']['type']) ? $_SESSION['qa_plan_flash']['type'] : 'success';
    $message = isset($_SESSION['qa_plan_flash']['text']) ? $_SESSION['qa_plan_flash']['text'] : '';
    unset($_SESSION['qa_plan_flash']);
}

$plans = array();
$result = $db->query(
    "SELECT *
     FROM qa_plans
     ORDER BY CASE WHEN status='active' THEN 0 ELSE 1 END, start_year_be DESC, plan_id DESC"
);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $plans[] = $row;
    }
    $result->free();
}

$selectedPlanId = isset($_GET['plan']) ? (int) $_GET['plan'] : 0;
if ($selectedPlanId <= 0 && !empty($plans)) {
    $selectedPlanId = (int) $plans[0]['plan_id'];
}

$selectedPlan = null;
foreach ($plans as $plan) {
    if ((int) $plan['plan_id'] === $selectedPlanId) {
        $selectedPlan = $plan;
        break;
    }
}

$visions = array();
$missions = array();
$schoolValues = array();
$strategies = array();
$focusAreas = array();
$targets = array();

if ($selectedPlan) {
    $stmt = $db->prepare("SELECT * FROM qa_visions WHERE plan_id=? ORDER BY sort_order, vision_id");
    $stmt->bind_param('i', $selectedPlanId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) $visions[] = $row;
        $result->free();
    }
    $stmt->close();

    $stmt = $db->prepare("SELECT * FROM qa_missions WHERE plan_id=? ORDER BY sort_order, mission_id");
    $stmt->bind_param('i', $selectedPlanId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) $missions[] = $row;
        $result->free();
    }
    $stmt->close();

    $stmt = $db->prepare("SELECT * FROM qa_school_values WHERE plan_id=? ORDER BY sort_order, value_id");
    $stmt->bind_param('i', $selectedPlanId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) $schoolValues[] = $row;
        $result->free();
    }
    $stmt->close();

    $stmt = $db->prepare(
        "SELECT s.*, m.mission_code, m.mission_text
         FROM qa_strategies s
         LEFT JOIN qa_missions m ON m.mission_id=s.mission_id
         WHERE s.plan_id=?
         ORDER BY s.sort_order, s.strategy_id"
    );
    $stmt->bind_param('i', $selectedPlanId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) $strategies[] = $row;
        $result->free();
    }
    $stmt->close();

    $stmt = $db->prepare("SELECT * FROM qa_focus_areas WHERE plan_id=? ORDER BY sort_order, focus_id");
    $stmt->bind_param('i', $selectedPlanId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) $focusAreas[] = $row;
        $result->free();
    }
    $stmt->close();

    $stmt = $db->prepare(
        "SELECT t.*, s.strategy_code, s.strategy_name, f.focus_code, f.focus_name
         FROM qa_plan_targets t
         LEFT JOIN qa_strategies s ON s.strategy_id=t.strategy_id
         LEFT JOIN qa_focus_areas f ON f.focus_id=t.focus_id
         WHERE t.plan_id=?
         ORDER BY t.sort_order, t.target_id"
    );
    $stmt->bind_param('i', $selectedPlanId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) $targets[] = $row;
        $result->free();
    }
    $stmt->close();
}

$editType = isset($_GET['edit']) ? $_GET['edit'] : '';
$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$editPlan = null;
$editVision = null;
$editMission = null;
$editValue = null;
$editStrategy = null;
$editFocus = null;
$editTarget = null;

if ($editType === 'plan' && $selectedPlan) {
    $editPlan = $selectedPlan;
} elseif ($editType === 'vision' && $editId > 0) {
    $editVision = plan_fetch_row('qa_visions', 'vision_id', $editId);
} elseif ($editType === 'mission' && $editId > 0) {
    $editMission = plan_fetch_row('qa_missions', 'mission_id', $editId);
} elseif ($editType === 'value' && $editId > 0) {
    $editValue = plan_fetch_row('qa_school_values', 'value_id', $editId);
} elseif ($editType === 'strategy' && $editId > 0) {
    $editStrategy = plan_fetch_row('qa_strategies', 'strategy_id', $editId);
} elseif ($editType === 'focus' && $editId > 0) {
    $editFocus = plan_fetch_row('qa_focus_areas', 'focus_id', $editId);
} elseif ($editType === 'target' && $editId > 0) {
    $editTarget = plan_fetch_row('qa_plan_targets', 'target_id', $editId);
}

require QA_ROOT . '/includes/header.php';
?>

<?php if ($message !== ''): ?>
    <div class="alert <?= $messageType === 'danger' ? 'alert-danger' : 'alert-success' ?>" style="margin-bottom:16px">
        <?= h($message) ?>
    </div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <div class="section-heading">
            <div>
                <h2>แผนพัฒนาคุณภาพ</h2>
                <p>เลือกแผนเพื่อจัดการวิสัยทัศน์ พันธกิจ ค่านิยม กลยุทธ์ จุดเน้น และเป้าหมาย</p>
            </div>
            <span class="badge badge-blue"><?= h(count($plans)) ?> แผน</span>
        </div>

        <?php if (!empty($plans)): ?>
            <form method="get" action="<?= h(qa_url('plans/index.php')) ?>">
                <div class="form-group">
                    <label for="plan">แผนที่กำลังจัดการ</label>
                    <select id="plan" name="plan" onchange="this.form.submit()">
                        <?php foreach ($plans as $plan): ?>
                            <option value="<?= h($plan['plan_id']) ?>" <?= (int)$plan['plan_id'] === $selectedPlanId ? 'selected' : '' ?>>
                                <?= h($plan['plan_code'] . ' — ' . $plan['plan_name']) ?>
                                <?= $plan['start_year_be'] ? h(' (' . $plan['start_year_be'] . ($plan['end_year_be'] ? '-' . $plan['end_year_be'] : '') . ')') : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        <?php else: ?>
            <div class="notice">ยังไม่มีแผนพัฒนาคุณภาพในระบบ กรุณาสร้างแผนแรกก่อน</div>
        <?php endif; ?>

        <?php if ($selectedPlan): ?>
            <div class="plan-summary-box">
                <div><span>รหัสแผน</span><strong><?= h($selectedPlan['plan_code']) ?></strong></div>
                <div><span>ชื่อแผน</span><strong><?= h($selectedPlan['plan_name']) ?></strong></div>
                <div><span>ช่วงปี</span><strong><?= h(($selectedPlan['start_year_be'] ? $selectedPlan['start_year_be'] : '-') . ' - ' . ($selectedPlan['end_year_be'] ? $selectedPlan['end_year_be'] : '-')) ?></strong></div>
                <div><span>สถานะ</span><strong><?= h($selectedPlan['status']) ?></strong></div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
    <div class="card">
        <div class="section-heading">
            <div>
                <h2><?= $editPlan ? 'แก้ไขแผน' : 'เพิ่มแผนใหม่' ?></h2>
                <p>ตัวอย่างรหัส: SDP-2570-2574 หรือ QA-PLAN-01</p>
            </div>
            <?php if ($editPlan): ?>
                <a class="btn btn-sm" href="<?= h(qa_url('plans/index.php?plan=' . $selectedPlanId)) ?>">ยกเลิก</a>
            <?php endif; ?>
        </div>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $editPlan ? 'update_plan' : 'create_plan' ?>">
            <input type="hidden" name="selected_plan_id" value="<?= h($selectedPlanId) ?>">
            <?php if ($editPlan): ?><input type="hidden" name="plan_id" value="<?= h($selectedPlanId) ?>"><?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label>รหัสแผน *</label>
                    <input name="plan_code" required maxlength="50" value="<?= $editPlan ? h($editPlan['plan_code']) : '' ?>" placeholder="เช่น SDP-2570-2574">
                </div>
                <div class="form-group">
                    <label>ประเภทแผน</label>
                    <select name="plan_type">
                        <?php
                        $planTypeValue = $editPlan ? $editPlan['plan_type'] : 'development';
                        $planTypes = array(
                            'development' => 'แผนพัฒนาคุณภาพ',
                            'annual' => 'แผนปฏิบัติการประจำปี',
                            'other' => 'แผนอื่น ๆ'
                        );
                        foreach ($planTypes as $code => $label):
                        ?>
                            <option value="<?= h($code) ?>" <?= $planTypeValue === $code ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full">
                    <label>ชื่อแผน *</label>
                    <input name="plan_name" required maxlength="255" value="<?= $editPlan ? h($editPlan['plan_name']) : '' ?>" placeholder="เช่น แผนพัฒนาคุณภาพการศึกษา พ.ศ. 2570-2574">
                </div>
                <div class="form-group">
                    <label>ปีเริ่มต้น (พ.ศ.)</label>
                    <input type="number" name="start_year_be" min="2500" max="2700" value="<?= $editPlan && $editPlan['start_year_be'] ? h($editPlan['start_year_be']) : '' ?>">
                </div>
                <div class="form-group">
                    <label>ปีสิ้นสุด (พ.ศ.)</label>
                    <input type="number" name="end_year_be" min="2500" max="2700" value="<?= $editPlan && $editPlan['end_year_be'] ? h($editPlan['end_year_be']) : '' ?>">
                </div>
                <div class="form-group">
                    <label>สถานะ</label>
                    <?php $statusValue = $editPlan ? $editPlan['status'] : 'active'; ?>
                    <select name="status">
                        <option value="active" <?= $statusValue === 'active' ? 'selected' : '' ?>>ใช้งาน</option>
                        <option value="inactive" <?= $statusValue === 'inactive' ? 'selected' : '' ?>>ไม่ใช้งาน</option>
                        <option value="archived" <?= $statusValue === 'archived' ? 'selected' : '' ?>>เก็บถาวร</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>รายละเอียด</label>
                    <textarea name="description" placeholder="รายละเอียดเพิ่มเติมของแผน"><?= $editPlan && $editPlan['description'] ? h($editPlan['description']) : '' ?></textarea>
                </div>
            </div>

            <div class="action-bar" style="margin-top:14px;margin-bottom:0">
                <button class="btn btn-primary" type="submit"><?= $editPlan ? 'บันทึกการแก้ไข' : 'สร้างแผน' ?></button>
                <?php if ($selectedPlan && !$editPlan): ?>
                    <a class="btn" href="<?= h(qa_url('plans/index.php?plan=' . $selectedPlanId . '&edit=plan')) ?>">แก้ไขแผนที่เลือก</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($selectedPlan && !$editPlan): ?>
            <form method="post" style="margin-top:10px" onsubmit="return confirm('ยืนยันลบแผนนี้? ระบบจะลบได้เฉพาะแผนที่ยังไม่มีข้อมูลเชื่อมโยง');">
                <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_plan">
                <input type="hidden" name="selected_plan_id" value="<?= h($selectedPlanId) ?>">
                <input type="hidden" name="plan_id" value="<?= h($selectedPlanId) ?>">
                <button class="btn btn-danger btn-sm" type="submit">ลบแผนที่เลือก</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($selectedPlan): ?>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>1. วิสัยทัศน์</h2><p>ข้อความทิศทางหลักของสถานศึกษา</p></div>
        <span class="badge badge-blue"><?= h(count($visions)) ?> รายการ</span>
    </div>

    <?php if (!empty($visions)): ?>
        <?php foreach ($visions as $vision): ?>
            <div class="master-item">
                <div class="master-main"><?= nl2br(h($vision['vision_text'])) ?></div>
                <?php if ($canManage): ?>
                    <div class="row-actions">
                        <a class="btn btn-sm" href="<?= h(qa_url('plans/index.php?plan=' . $selectedPlanId . '&edit=vision&id=' . $vision['vision_id'] . '#vision-form')) ?>">แก้ไข</a>
                        <form method="post" onsubmit="return confirm('ยืนยันลบวิสัยทัศน์นี้?');">
                            <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_vision">
                            <input type="hidden" name="selected_plan_id" value="<?= h($selectedPlanId) ?>">
                            <input type="hidden" name="vision_id" value="<?= h($vision['vision_id']) ?>">
                            <button class="btn btn-danger btn-sm" type="submit">ลบ</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-cell">ยังไม่ได้บันทึกวิสัยทัศน์</div>
    <?php endif; ?>

    <?php if ($canManage): ?>
    <form method="post" id="vision-form" class="master-form">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="save_vision">
        <input type="hidden" name="selected_plan_id" value="<?= h($selectedPlanId) ?>">
        <input type="hidden" name="vision_id" value="<?= $editVision ? h($editVision['vision_id']) : '0' ?>">
        <div class="form-group">
            <label><?= $editVision ? 'แก้ไขวิสัยทัศน์' : 'เพิ่มวิสัยทัศน์' ?></label>
            <textarea name="vision_text" required placeholder="ระบุวิสัยทัศน์ของสถานศึกษา"><?= $editVision ? h($editVision['vision_text']) : '' ?></textarea>
        </div>
        <div class="action-bar" style="margin-bottom:0">
            <button class="btn btn-primary btn-sm" type="submit"><?= $editVision ? 'บันทึกการแก้ไข' : 'เพิ่มวิสัยทัศน์' ?></button>
            <?php if ($editVision): ?><a class="btn btn-sm" href="<?= h(qa_url('plans/index.php?plan=' . $selectedPlanId . '#vision-form')) ?>">ยกเลิก</a><?php endif; ?>
        </div>
    </form>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>2. พันธกิจ</h2><p>พันธกิจที่โครงการจะเลือกเชื่อมโยงในขั้นเสนอ</p></div>
        <span class="badge badge-blue"><?= h(count($missions)) ?> รายการ</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th style="width:120px">รหัส</th><th>พันธกิจ</th><?php if ($canManage): ?><th style="width:140px">จัดการ</th><?php endif; ?></tr></thead>
            <tbody>
            <?php if (empty($missions)): ?>
                <tr><td colspan="<?= $canManage ? '3' : '2' ?>" class="empty-cell">ยังไม่มีพันธกิจ</td></tr>
            <?php else: ?>
                <?php foreach ($missions as $mission): ?>
                <tr>
                    <td><strong><?= h($mission['mission_code']) ?></strong></td>
                    <td><?= h($mission['mission_text']) ?></td>
                    <?php if ($canManage): ?>
                    <td>
                        <div class="row-actions">
                            <a class="btn btn-sm" href="<?= h(qa_url('plans/index.php?plan=' . $selectedPlanId . '&edit=mission&id=' . $mission['mission_id'] . '#mission-form')) ?>">แก้ไข</a>
                            <form method="post" onsubmit="return confirm('ยืนยันลบพันธกิจนี้?');">
                                <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_mission">
                                <input type="hidden" name="selected_plan_id" value="<?= h($selectedPlanId) ?>">
                                <input type="hidden" name="mission_id" value="<?= h($mission['mission_id']) ?>">
                                <button class="btn btn-danger btn-sm" type="submit">ลบ</button>
                            </form>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($canManage): ?>
    <form method="post" id="mission-form" class="master-form">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="save_mission">
        <input type="hidden" name="selected_plan_id" value="<?= h($selectedPlanId) ?>">
        <input type="hidden" name="mission_id" value="<?= $editMission ? h($editMission['mission_id']) : '0' ?>">
        <div class="form-grid">
            <div class="form-group">
                <label>รหัสพันธกิจ *</label>
                <input name="mission_code" required maxlength="50" value="<?= $editMission ? h($editMission['mission_code']) : '' ?>" placeholder="เช่น M1">
            </div>
            <div class="form-group full">
                <label>ข้อความพันธกิจ *</label>
                <textarea name="mission_text" required placeholder="ระบุพันธกิจ"><?= $editMission ? h($editMission['mission_text']) : '' ?></textarea>
            </div>
        </div>
        <div class="action-bar" style="margin-bottom:0">
            <button class="btn btn-primary btn-sm" type="submit"><?= $editMission ? 'บันทึกการแก้ไข' : 'เพิ่มพันธกิจ' ?></button>
            <?php if ($editMission): ?><a class="btn btn-sm" href="<?= h(qa_url('plans/index.php?plan=' . $selectedPlanId . '#mission-form')) ?>">ยกเลิก</a><?php endif; ?>
        </div>
    </form>
    <?php endif; ?>
</div>


<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div>
            <h2>3. ค่านิยมของสถานศึกษา</h2>
            <p>ค่านิยมหลักที่ใช้กำกับวัฒนธรรมองค์กร การปฏิบัติงาน และการพัฒนาคุณภาพของสถานศึกษา</p>
        </div>
        <span class="badge badge-blue"><?= h(count($schoolValues)) ?> รายการ</span>
    </div>

    <?php if (empty($schoolValues)): ?>
        <div class="empty-cell">ยังไม่มีค่านิยมของสถานศึกษา</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th style="width:120px">รหัส</th>
                    <th style="width:260px">ค่านิยม</th>
                    <th>รายละเอียด</th>
                    <?php if ($canManage): ?><th style="width:140px">จัดการ</th><?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($schoolValues as $schoolValue): ?>
                    <tr>
                        <td><strong><?= h($schoolValue['value_code']) ?></strong></td>
                        <td><strong><?= h($schoolValue['value_name']) ?></strong></td>
                        <td><?= $schoolValue['description'] ? nl2br(h($schoolValue['description'])) : '-' ?></td>
                        <?php if ($canManage): ?>
                        <td>
                            <div class="row-actions">
                                <a class="btn btn-sm" href="<?= h(qa_url('plans/index.php?plan=' . $selectedPlanId . '&edit=value&id=' . $schoolValue['value_id'] . '#value-form')) ?>">แก้ไข</a>
                                <form method="post" onsubmit="return confirm('ยืนยันลบค่านิยมนี้?');">
                                    <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_value">
                                    <input type="hidden" name="selected_plan_id" value="<?= h($selectedPlanId) ?>">
                                    <input type="hidden" name="value_id" value="<?= h($schoolValue['value_id']) ?>">
                                    <button class="btn btn-danger btn-sm" type="submit">ลบ</button>
                                </form>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($canManage): ?>
    <form method="post" id="value-form" class="master-form">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="save_value">
        <input type="hidden" name="selected_plan_id" value="<?= h($selectedPlanId) ?>">
        <input type="hidden" name="value_id" value="<?= $editValue ? h($editValue['value_id']) : '0' ?>">

        <div class="form-grid">
            <div class="form-group">
                <label>รหัสค่านิยม *</label>
                <input
                    name="value_code"
                    required
                    maxlength="50"
                    value="<?= $editValue ? h($editValue['value_code']) : '' ?>"
                    placeholder="เช่น V1">
            </div>
            <div class="form-group">
                <label>ชื่อค่านิยม *</label>
                <input
                    name="value_name"
                    required
                    maxlength="255"
                    value="<?= $editValue ? h($editValue['value_name']) : '' ?>"
                    placeholder="เช่น มุ่งผลสัมฤทธิ์">
            </div>
            <div class="form-group full">
                <label>รายละเอียด / พฤติกรรมที่คาดหวัง</label>
                <textarea
                    name="value_description"
                    placeholder="อธิบายความหมายหรือพฤติกรรมที่สะท้อนค่านิยม"><?= $editValue && $editValue['description'] ? h($editValue['description']) : '' ?></textarea>
            </div>
        </div>

        <div class="action-bar" style="margin-bottom:0">
            <button class="btn btn-primary btn-sm" type="submit"><?= $editValue ? 'บันทึกการแก้ไข' : 'เพิ่มค่านิยม' ?></button>
            <?php if ($editValue): ?>
                <a class="btn btn-sm" href="<?= h(qa_url('plans/index.php?plan=' . $selectedPlanId . '#value-form')) ?>">ยกเลิก</a>
            <?php endif; ?>
        </div>
    </form>
    <?php endif; ?>
</div>

<div class="grid grid-2" style="margin-top:16px">
    <div class="card">
        <div class="section-heading">
            <div><h2>4. กลยุทธ์</h2><p>ผูกกับพันธกิจได้ และใช้เป็นตัวเลือกในโครงการ</p></div>
            <span class="badge badge-blue"><?= h(count($strategies)) ?> รายการ</span>
        </div>

        <?php if (empty($strategies)): ?>
            <div class="empty-cell">ยังไม่มีกลยุทธ์</div>
        <?php else: ?>
            <?php foreach ($strategies as $strategy): ?>
                <div class="master-item">
                    <div>
                        <div><strong><?= h($strategy['strategy_code']) ?> — <?= h($strategy['strategy_name']) ?></strong></div>
                        <div class="subtle">
                            <?= $strategy['mission_code'] ? h('พันธกิจ: ' . $strategy['mission_code']) : 'ยังไม่ผูกพันธกิจ' ?>
                        </div>
                        <?php if ($strategy['description']): ?><div class="master-desc"><?= nl2br(h($strategy['description'])) ?></div><?php endif; ?>
                    </div>
                    <?php if ($canManage): ?>
                    <div class="row-actions">
                        <a class="btn btn-sm" href="<?= h(qa_url('plans/index.php?plan=' . $selectedPlanId . '&edit=strategy&id=' . $strategy['strategy_id'] . '#strategy-form')) ?>">แก้ไข</a>
                        <form method="post" onsubmit="return confirm('ยืนยันลบกลยุทธ์นี้?');">
                            <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_strategy">
                            <input type="hidden" name="selected_plan_id" value="<?= h($selectedPlanId) ?>">
                            <input type="hidden" name="strategy_id" value="<?= h($strategy['strategy_id']) ?>">
                            <button class="btn btn-danger btn-sm" type="submit">ลบ</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($canManage): ?>
        <form method="post" id="strategy-form" class="master-form">
            <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
            <input type="hidden" name="action" value="save_strategy">
            <input type="hidden" name="selected_plan_id" value="<?= h($selectedPlanId) ?>">
            <input type="hidden" name="strategy_id" value="<?= $editStrategy ? h($editStrategy['strategy_id']) : '0' ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>รหัสกลยุทธ์ *</label>
                    <input name="strategy_code" required value="<?= $editStrategy ? h($editStrategy['strategy_code']) : '' ?>" placeholder="เช่น S1">
                </div>
                <div class="form-group">
                    <label>พันธกิจที่เกี่ยวข้อง</label>
                    <select name="mission_id">
                        <option value="">-- ไม่ระบุ --</option>
                        <?php foreach ($missions as $mission): ?>
                            <option value="<?= h($mission['mission_id']) ?>" <?= $editStrategy && (int)$editStrategy['mission_id'] === (int)$mission['mission_id'] ? 'selected' : '' ?>>
                                <?= h($mission['mission_code'] . ' — ' . $mission['mission_text']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full">
                    <label>ชื่อกลยุทธ์ *</label>
                    <input name="strategy_name" required value="<?= $editStrategy ? h($editStrategy['strategy_name']) : '' ?>" placeholder="ระบุชื่อกลยุทธ์">
                </div>
                <div class="form-group full">
                    <label>รายละเอียด</label>
                    <textarea name="strategy_description"><?= $editStrategy && $editStrategy['description'] ? h($editStrategy['description']) : '' ?></textarea>
                </div>
            </div>
            <div class="action-bar" style="margin-bottom:0">
                <button class="btn btn-primary btn-sm" type="submit"><?= $editStrategy ? 'บันทึกการแก้ไข' : 'เพิ่มกลยุทธ์' ?></button>
                <?php if ($editStrategy): ?><a class="btn btn-sm" href="<?= h(qa_url('plans/index.php?plan=' . $selectedPlanId . '#strategy-form')) ?>">ยกเลิก</a><?php endif; ?>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="section-heading">
            <div><h2>5. จุดเน้นโรงเรียน</h2><p>จุดเน้นที่ต้องการติดตามทั้งโครงการ งบประมาณ และผลลัพธ์</p></div>
            <span class="badge badge-blue"><?= h(count($focusAreas)) ?> รายการ</span>
        </div>

        <?php if (empty($focusAreas)): ?>
            <div class="empty-cell">ยังไม่มีจุดเน้นโรงเรียน</div>
        <?php else: ?>
            <?php foreach ($focusAreas as $focus): ?>
                <div class="master-item">
                    <div>
                        <div><strong><?= h($focus['focus_code']) ?> — <?= h($focus['focus_name']) ?></strong></div>
                        <?php if ($focus['description']): ?><div class="master-desc"><?= nl2br(h($focus['description'])) ?></div><?php endif; ?>
                    </div>
                    <?php if ($canManage): ?>
                    <div class="row-actions">
                        <a class="btn btn-sm" href="<?= h(qa_url('plans/index.php?plan=' . $selectedPlanId . '&edit=focus&id=' . $focus['focus_id'] . '#focus-form')) ?>">แก้ไข</a>
                        <form method="post" onsubmit="return confirm('ยืนยันลบจุดเน้นนี้?');">
                            <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_focus">
                            <input type="hidden" name="selected_plan_id" value="<?= h($selectedPlanId) ?>">
                            <input type="hidden" name="focus_id" value="<?= h($focus['focus_id']) ?>">
                            <button class="btn btn-danger btn-sm" type="submit">ลบ</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($canManage): ?>
        <form method="post" id="focus-form" class="master-form">
            <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
            <input type="hidden" name="action" value="save_focus">
            <input type="hidden" name="selected_plan_id" value="<?= h($selectedPlanId) ?>">
            <input type="hidden" name="focus_id" value="<?= $editFocus ? h($editFocus['focus_id']) : '0' ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>รหัสจุดเน้น *</label>
                    <input name="focus_code" required value="<?= $editFocus ? h($editFocus['focus_code']) : '' ?>" placeholder="เช่น F1">
                </div>
                <div class="form-group">
                    <label>ชื่อจุดเน้น *</label>
                    <input name="focus_name" required value="<?= $editFocus ? h($editFocus['focus_name']) : '' ?>" placeholder="เช่น ทักษะอาชีพ">
                </div>
                <div class="form-group full">
                    <label>รายละเอียด</label>
                    <textarea name="focus_description"><?= $editFocus && $editFocus['description'] ? h($editFocus['description']) : '' ?></textarea>
                </div>
            </div>
            <div class="action-bar" style="margin-bottom:0">
                <button class="btn btn-primary btn-sm" type="submit"><?= $editFocus ? 'บันทึกการแก้ไข' : 'เพิ่มจุดเน้น' ?></button>
                <?php if ($editFocus): ?><a class="btn btn-sm" href="<?= h(qa_url('plans/index.php?plan=' . $selectedPlanId . '#focus-form')) ?>">ยกเลิก</a><?php endif; ?>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>6. เป้าหมาย / ตัวชี้วัดของแผน</h2><p>ใช้กำหนดค่าเป้าหมายที่วัดได้ของแผนพัฒนาคุณภาพ</p></div>
        <span class="badge badge-blue"><?= h(count($targets)) ?> รายการ</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>รหัส</th>
                <th>เป้าหมาย</th>
                <th>ตัวชี้วัด</th>
                <th>กลยุทธ์ / จุดเน้น</th>
                <th class="text-right">ค่าฐาน</th>
                <th class="text-right">เป้าหมาย</th>
                <?php if ($canManage): ?><th>จัดการ</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($targets)): ?>
                <tr><td colspan="<?= $canManage ? '7' : '6' ?>" class="empty-cell">ยังไม่มีเป้าหมายของแผน</td></tr>
            <?php else: ?>
                <?php foreach ($targets as $target): ?>
                <tr>
                    <td><?= $target['target_code'] ? h($target['target_code']) : '-' ?></td>
                    <td><strong><?= h($target['target_name']) ?></strong></td>
                    <td><?= $target['indicator_text'] ? h($target['indicator_text']) : '-' ?></td>
                    <td>
                        <?= $target['strategy_code'] ? h('กลยุทธ์ ' . $target['strategy_code']) : '-' ?>
                        <?php if ($target['focus_code']): ?><div class="subtle"><?= h('จุดเน้น ' . $target['focus_code']) ?></div><?php endif; ?>
                    </td>
                    <td class="text-right"><?= $target['baseline_value'] !== null ? h(number_format((float)$target['baseline_value'], 2)) : '-' ?></td>
                    <td class="text-right">
                        <?= $target['target_value'] !== null ? h(number_format((float)$target['target_value'], 2)) : '-' ?>
                        <?= $target['target_unit'] ? h(' ' . $target['target_unit']) : '' ?>
                    </td>
                    <?php if ($canManage): ?>
                    <td>
                        <div class="row-actions">
                            <a class="btn btn-sm" href="<?= h(qa_url('plans/index.php?plan=' . $selectedPlanId . '&edit=target&id=' . $target['target_id'] . '#target-form')) ?>">แก้ไข</a>
                            <form method="post" onsubmit="return confirm('ยืนยันลบเป้าหมายนี้?');">
                                <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_target">
                                <input type="hidden" name="selected_plan_id" value="<?= h($selectedPlanId) ?>">
                                <input type="hidden" name="target_id" value="<?= h($target['target_id']) ?>">
                                <button class="btn btn-danger btn-sm" type="submit">ลบ</button>
                            </form>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($canManage): ?>
    <form method="post" id="target-form" class="master-form">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="save_target">
        <input type="hidden" name="selected_plan_id" value="<?= h($selectedPlanId) ?>">
        <input type="hidden" name="target_id" value="<?= $editTarget ? h($editTarget['target_id']) : '0' ?>">

        <div class="form-grid">
            <div class="form-group">
                <label>รหัสเป้าหมาย</label>
                <input name="target_code" value="<?= $editTarget && $editTarget['target_code'] ? h($editTarget['target_code']) : '' ?>" placeholder="เช่น T1">
            </div>
            <div class="form-group">
                <label>กลยุทธ์</label>
                <select name="target_strategy_id">
                    <option value="">-- ไม่ระบุ --</option>
                    <?php foreach ($strategies as $strategy): ?>
                        <option value="<?= h($strategy['strategy_id']) ?>" <?= $editTarget && (int)$editTarget['strategy_id'] === (int)$strategy['strategy_id'] ? 'selected' : '' ?>>
                            <?= h($strategy['strategy_code'] . ' — ' . $strategy['strategy_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>จุดเน้น</label>
                <select name="target_focus_id">
                    <option value="">-- ไม่ระบุ --</option>
                    <?php foreach ($focusAreas as $focus): ?>
                        <option value="<?= h($focus['focus_id']) ?>" <?= $editTarget && (int)$editTarget['focus_id'] === (int)$focus['focus_id'] ? 'selected' : '' ?>>
                            <?= h($focus['focus_code'] . ' — ' . $focus['focus_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full">
                <label>ชื่อเป้าหมาย *</label>
                <input name="target_name" required value="<?= $editTarget ? h($editTarget['target_name']) : '' ?>" placeholder="เช่น ผู้เรียนมีทักษะอาชีพตามเกณฑ์ที่กำหนด">
            </div>
            <div class="form-group full">
                <label>ข้อความตัวชี้วัด</label>
                <textarea name="indicator_text" placeholder="ระบุสิ่งที่จะวัด"><?= $editTarget && $editTarget['indicator_text'] ? h($editTarget['indicator_text']) : '' ?></textarea>
            </div>
            <div class="form-group">
                <label>ค่าฐาน (Baseline)</label>
                <input type="number" step="0.01" name="baseline_value" value="<?= $editTarget && $editTarget['baseline_value'] !== null ? h(number_format((float)$editTarget['baseline_value'], 2, '.', '')) : '' ?>">
            </div>
            <div class="form-group">
                <label>ค่าเป้าหมาย</label>
                <input type="number" step="0.01" name="target_value" value="<?= $editTarget && $editTarget['target_value'] !== null ? h(number_format((float)$editTarget['target_value'], 2, '.', '')) : '' ?>">
            </div>
            <div class="form-group">
                <label>หน่วย</label>
                <input name="target_unit" value="<?= $editTarget && $editTarget['target_unit'] ? h($editTarget['target_unit']) : '' ?>" placeholder="เช่น %, คน, โครงการ">
            </div>
        </div>

        <div class="action-bar" style="margin-bottom:0">
            <button class="btn btn-primary btn-sm" type="submit"><?= $editTarget ? 'บันทึกการแก้ไข' : 'เพิ่มเป้าหมาย' ?></button>
            <?php if ($editTarget): ?><a class="btn btn-sm" href="<?= h(qa_url('plans/index.php?plan=' . $selectedPlanId . '#target-form')) ?>">ยกเลิก</a><?php endif; ?>
        </div>
    </form>
    <?php endif; ?>
</div>

<div class="notice" style="margin-top:16px">
    เมื่อข้อมูลส่วนนี้ครบ หน้า “เสนอโครงการใหม่” จะสามารถดึงพันธกิจ กลยุทธ์ จุดเน้น และเป้าหมายจากฐานข้อมูลจริงมาให้เลือกได้โดยไม่ต้องพิมพ์ซ้ำ ส่วนค่านิยมจะใช้เป็น Master Data สำหรับการบริหารและหลักฐานตัวชี้วัด 2.1
</div>

<?php else: ?>
<div class="notice" style="margin-top:16px">
    กรุณาสร้างแผนพัฒนาคุณภาพก่อน จึงจะสามารถบันทึกวิสัยทัศน์ พันธกิจ ค่านิยม กลยุทธ์ จุดเน้น และเป้าหมายได้
</div>
<?php endif; ?>

<?php require QA_ROOT . '/includes/footer.php'; ?>
