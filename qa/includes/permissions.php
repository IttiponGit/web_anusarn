<?php
/* QA v1.4.1 — Capability-based RBAC */

function qa_permissions_ready()
{
    static $ready=null;
    if($ready!==null)return $ready;
    $db=qa_db();
    foreach(array('qa_permissions','qa_role_permissions') as $table){
        $res=$db->query("SHOW TABLES LIKE '".$db->real_escape_string($table)."'");
        if(!$res || $res->num_rows<1){
            if($res)$res->free();
            $ready=false;
            return false;
        }
        $res->free();
    }
    $ready=true;
    return true;
}

function qa_permission_fallback_map()
{
    return array(
        'admin'=>array('*'),
        'director'=>array('dashboard.view','dashboard.executive','plan.view','plan.manage','budget.view','budget.manage','budget.allocation.view','budget.allocation.manage','project.view_all','project.create','project.change.review','project.approve.final','activity.view_all','monitoring.view_all','monitoring.result.approve','evidence.view_all','quality.view','reports.view','sar.view','sar.finalize','obec.view'),
        'deputy_director'=>array('dashboard.view','dashboard.executive','plan.view','budget.view','budget.allocation.view','project.view_all','project.create','activity.view_all','monitoring.view_all','evidence.view_all','quality.view','reports.view','sar.view','obec.view'),
        'plan'=>array('dashboard.view','plan.view','plan.manage','budget.view','budget.manage','budget.allocation.view','budget.allocation.manage','project.view_all','project.create','project.approve.plan','activity.view_all','monitoring.view_all','evidence.view_all','quality.view','reports.view','sar.view','sar.edit','obec.view','obec.manage'),
        'budget'=>array('dashboard.view','budget.view','budget.manage','budget.allocation.view','budget.allocation.manage','project.view_all','project.create','project.approve.budget','activity.view_all','monitoring.view_all','evidence.view_all','quality.view','reports.view','obec.view','obec.manage'),
        'finance'=>array('dashboard.view','budget.view','budget.allocation.view','finance.record','project.view_all','activity.view_all','monitoring.view_all','monitoring.finance','evidence.view_all','quality.view','reports.view','obec.view','obec.manage'),
        'division_head'=>array('dashboard.view','budget.view','budget.allocation.view','project.view_division','project.create','project.change.request','activity.view_division','activity.manage.context','activity.report.context','monitoring.view_division','monitoring.manage.context','evidence.view_division','evidence.add.context','quality.view','reports.view'),
        'project_owner'=>array('dashboard.view','budget.view','budget.allocation.view','project.view_own','project.create','project.edit_own','project.change.request','activity.view_own','activity.manage.context','activity.report.context','monitoring.view_own','monitoring.manage.context','evidence.view_own','evidence.add.context','quality.view'),
        'viewer'=>array('dashboard.view','plan.view','budget.view','budget.allocation.view','project.view_all','activity.view_all','monitoring.view_all','evidence.view_all','quality.view','reports.view','sar.view','obec.view')
    );
}

function qa_user_can($permissionCode)
{
    static $cache=array();
    if(!qa_is_logged_in())return false;
    $user=qa_current_user();
    if(!$user)return false;
    $permissionCode=trim((string)$permissionCode);
    if($permissionCode==='')return false;

    $userId=(int)$user['user_id'];
    $key=$userId.'|'.$permissionCode;
    if(isset($cache[$key]))return $cache[$key];

    if(qa_user_has_role('admin')){
        $cache[$key]=true;
        return true;
    }

    if(qa_permissions_ready()){
        $db=qa_db();
        $stmt=$db->prepare(
            "SELECT COUNT(*)
             FROM qa_user_roles ur
             INNER JOIN qa_roles r ON r.role_id=ur.role_id AND r.is_active=1
             INNER JOIN qa_role_permissions rp ON rp.role_id=r.role_id
             INNER JOIN qa_permissions p ON p.permission_id=rp.permission_id AND p.is_active=1
             WHERE ur.user_id=? AND p.permission_code=?"
        );
        if($stmt){
            $stmt->bind_param('is',$userId,$permissionCode);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();
            $cache[$key]=((int)$count>0);
            return $cache[$key];
        }
    }

    $map=qa_permission_fallback_map();
    foreach($user['roles'] as $roleCode){
        if(!isset($map[$roleCode]))continue;
        if(in_array('*',$map[$roleCode],true)||in_array($permissionCode,$map[$roleCode],true)){
            $cache[$key]=true;
            return true;
        }
    }
    $cache[$key]=false;
    return false;
}

function qa_user_can_any($permissionCodes)
{
    if(!is_array($permissionCodes))$permissionCodes=array($permissionCodes);
    foreach($permissionCodes as $code)if(qa_user_can($code))return true;
    return false;
}

function qa_require_permission($permissionCode)
{
    qa_require_login();
    if(!qa_user_can($permissionCode)){http_response_code(403);die('คุณไม่มีสิทธิ์ใช้งานส่วนนี้');}
}

function qa_require_any_permission($permissionCodes)
{
    qa_require_login();
    if(!qa_user_can_any($permissionCodes)){http_response_code(403);die('คุณไม่มีสิทธิ์ใช้งานส่วนนี้');}
}

function qa_user_primary_division_id($userId)
{
    static $cache=array();
    $userId=(int)$userId;
    if($userId<=0)return 0;
    if(isset($cache[$userId]))return $cache[$userId];
    $db=qa_db();
    $stmt=$db->prepare("SELECT primary_division_id FROM qa_users WHERE user_id=? LIMIT 1");
    if(!$stmt)return 0;
    $stmt->bind_param('i',$userId);$stmt->execute();$stmt->bind_result($divisionId);$stmt->fetch();$stmt->close();
    $cache[$userId]=(int)$divisionId;
    return $cache[$userId];
}

function qa_project_scope_condition($alias)
{
    $alias=preg_replace('/[^A-Za-z0-9_]/','',(string)$alias);
    if($alias==='')$alias='p';
    $user=qa_current_user();if(!$user)return '1=0';$uid=(int)$user['user_id'];
    if(qa_user_can('project.view_all'))return '1=1';
    if(qa_user_can('project.view_division')){
        $did=qa_user_primary_division_id($uid);return $did>0?$alias.'.division_id='.$did:'1=0';
    }
    if(qa_user_can('project.view_own')){
        return '(' . $alias.'.owner_user_id='.$uid .
            ' OR EXISTS(SELECT 1 FROM qa_project_activities qascope WHERE qascope.project_id='.$alias.'.project_id AND qascope.responsible_user_id='.$uid.'))';
    }
    return '1=0';
}

function qa_activity_scope_condition($activityAlias,$projectAlias)
{
    $activityAlias=preg_replace('/[^A-Za-z0-9_]/','',(string)$activityAlias);
    $projectAlias=preg_replace('/[^A-Za-z0-9_]/','',(string)$projectAlias);
    if($activityAlias==='')$activityAlias='a';if($projectAlias==='')$projectAlias='p';
    $user=qa_current_user();if(!$user)return '1=0';$uid=(int)$user['user_id'];
    if(qa_user_can('activity.view_all'))return '1=1';
    if(qa_user_can('activity.view_division')){
        $did=qa_user_primary_division_id($uid);return $did>0?$projectAlias.'.division_id='.$did:'1=0';
    }
    if(qa_user_can('activity.view_own')){
        return '(' . $activityAlias.'.responsible_user_id='.$uid.' OR '.$projectAlias.'.owner_user_id='.$uid.')';
    }
    return '1=0';
}

function qa_monitoring_scope_condition($alias)
{
    $alias=preg_replace('/[^A-Za-z0-9_]/','',(string)$alias);if($alias==='')$alias='p';
    $user=qa_current_user();if(!$user)return '1=0';$uid=(int)$user['user_id'];
    if(qa_user_can('monitoring.view_all'))return '1=1';
    if(qa_user_can('monitoring.view_division')){
        $did=qa_user_primary_division_id($uid);return $did>0?$alias.'.division_id='.$did:'1=0';
    }
    if(qa_user_can('monitoring.view_own')){
        return '(' . $alias.'.owner_user_id='.$uid .
            ' OR EXISTS(SELECT 1 FROM qa_project_activities qmscope WHERE qmscope.project_id='.$alias.'.project_id AND qmscope.responsible_user_id='.$uid.'))';
    }
    return '1=0';
}

function qa_evidence_scope_condition($alias)
{
    $alias=preg_replace('/[^A-Za-z0-9_]/','',(string)$alias);if($alias==='')$alias='p';
    $user=qa_current_user();if(!$user)return '1=0';$uid=(int)$user['user_id'];
    if(qa_user_can('evidence.view_all'))return '1=1';
    if(qa_user_can('evidence.view_division')){
        $did=qa_user_primary_division_id($uid);return $did>0?$alias.'.division_id='.$did:'1=0';
    }
    if(qa_user_can('evidence.view_own')){
        return '(' . $alias.'.owner_user_id='.$uid .
            ' OR EXISTS(SELECT 1 FROM qa_project_activities qescope WHERE qescope.project_id='.$alias.'.project_id AND qescope.responsible_user_id='.$uid.'))';
    }
    return '1=0';
}
