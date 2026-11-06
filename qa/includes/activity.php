<?php
/* QA v1.4.6 — Phase 4.2.2 Activity Hierarchy & Assignment */

function qa_activity_tables_ready()
{
    static $ready=null;
    if($ready!==null)return $ready;
    $db=qa_db();

    $q=$db->query("SHOW TABLES LIKE 'qa_activity_progress_reports'");
    if(!$q || $q->num_rows<1){if($q)$q->free();$ready=false;return false;}
    $q->free();

    foreach(array('progress_percent','parent_activity_id') as $column){
        $q=$db->query("SHOW COLUMNS FROM qa_project_activities LIKE '".$db->real_escape_string($column)."'");
        if(!$q || $q->num_rows<1){if($q)$q->free();$ready=false;return false;}
        $q->free();
    }

    $ready=true;
    return true;
}

function qa_activity_load($activityId)
{
    if(!qa_activity_tables_ready())return null;
    $db=qa_db();
    $stmt=$db->prepare(
        "SELECT a.*,p.project_code,p.project_name,p.project_id,p.plan_id,p.year_id,p.division_id,
                p.owner_user_id,p.status_code AS project_status,p.start_date AS project_start_date,
                p.end_date AS project_end_date,d.division_name,
                pa.activity_no AS parent_activity_no,pa.activity_name AS parent_activity_name,
                CONCAT(COALESCE(u.prefix,''),COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS responsible_name,
                CONCAT(COALESCE(po.prefix,''),COALESCE(po.first_name,''),' ',COALESCE(po.last_name,'')) AS project_owner_name,
                (SELECT COUNT(*) FROM qa_project_activities ch WHERE ch.parent_activity_id=a.activity_id) AS child_count
         FROM qa_project_activities a
         INNER JOIN qa_projects p ON p.project_id=a.project_id
         INNER JOIN qa_divisions d ON d.division_id=p.division_id
         LEFT JOIN qa_project_activities pa ON pa.activity_id=a.parent_activity_id
         LEFT JOIN qa_users u ON u.user_id=a.responsible_user_id
         LEFT JOIN qa_users po ON po.user_id=p.owner_user_id
         WHERE a.activity_id=? LIMIT 1"
    );
    if(!$stmt)return null;
    $stmt->bind_param('i',$activityId);
    $stmt->execute();
    $result=$stmt->get_result();
    $row=$result?$result->fetch_assoc():null;
    if($result)$result->free();
    $stmt->close();
    return $row;
}

function qa_activity_user_division($userId)
{
    $db=qa_db();
    $stmt=$db->prepare("SELECT primary_division_id FROM qa_users WHERE user_id=? LIMIT 1");
    if(!$stmt)return 0;
    $stmt->bind_param('i',$userId);
    $stmt->execute();
    $stmt->bind_result($divisionId);
    $stmt->fetch();
    $stmt->close();
    return (int)$divisionId;
}

function qa_activity_can_manage_project($project)
{
    $user=qa_current_user();
    if(!$user||!$project)return false;
    if(qa_user_can('activity.manage_all'))return true;
    if(!qa_user_can('activity.manage.context'))return false;
    if((int)$project['owner_user_id']===(int)$user['user_id'])return true;
    if(qa_user_can('project.view_division')){
        return qa_activity_user_division((int)$user['user_id'])===(int)$project['division_id'];
    }
    return false;
}

function qa_activity_can_view($activity)
{
    $user=qa_current_user();
    if(!$user||!$activity)return false;
    if(qa_user_can('activity.view_all'))return true;
    if(qa_user_can('activity.view_division')){
        return qa_activity_user_division((int)$user['user_id'])===(int)$activity['division_id'];
    }
    if(qa_user_can('activity.view_own')){
        return (int)$activity['owner_user_id']===(int)$user['user_id'] ||
               (int)$activity['responsible_user_id']===(int)$user['user_id'];
    }
    return false;
}

/* รายงานกิจกรรม: ผู้รับผิดชอบโดยตรงเป็นผู้รายงาน
   ผู้ดูแลที่มี activity.report_all สามารถรายงานแทนได้ */
function qa_activity_can_report($activity)
{
    $user=qa_current_user();
    if(!$user||!$activity)return false;
    if(qa_user_can('activity.report_all'))return true;
    if(!qa_user_can('activity.report.context'))return false;
    return (int)$activity['responsible_user_id']===(int)$user['user_id'];
}

function qa_activity_status_label($status)
{
    $map=array(
        'planned'=>'วางแผน',
        'in_progress'=>'กำลังดำเนินการ',
        'completed'=>'เสร็จสิ้น',
        'cancelled'=>'ยกเลิก'
    );
    return isset($map[$status])?$map[$status]:$status;
}

function qa_activity_status_badge($status)
{
    if($status==='completed')return 'badge-green';
    if($status==='in_progress')return 'badge-blue';
    if($status==='cancelled')return 'badge-red';
    return 'badge-yellow';
}

function qa_activity_display_no($row)
{
    if(!$row)return '';
    $no=isset($row['activity_no'])?(int)$row['activity_no']:0;
    if(isset($row['parent_activity_id']) && (int)$row['parent_activity_id']>0){
        $parentNo=isset($row['parent_activity_no'])?(int)$row['parent_activity_no']:0;
        if($parentNo>0)return $parentNo.'.'.$no;
    }
    return (string)$no;
}

function qa_activity_is_child($row)
{
    return $row && isset($row['parent_activity_id']) && (int)$row['parent_activity_id']>0;
}

function qa_activity_child_count($activityId)
{
    return (int)qa_db_scalar(
        "SELECT COUNT(*) FROM qa_project_activities WHERE parent_activity_id=".(int)$activityId,0
    );
}

function qa_activity_next_no($projectId,$parentActivityId)
{
    $projectId=(int)$projectId;
    $parentActivityId=(int)$parentActivityId;
    if($parentActivityId>0){
        return (int)qa_db_scalar(
            "SELECT COALESCE(MAX(activity_no),0)+1 FROM qa_project_activities
             WHERE project_id=".$projectId." AND parent_activity_id=".$parentActivityId,1
        );
    }
    return (int)qa_db_scalar(
        "SELECT COALESCE(MAX(activity_no),0)+1 FROM qa_project_activities
         WHERE project_id=".$projectId." AND parent_activity_id IS NULL",1
    );
}

function qa_activity_recalculate_parent($parentActivityId)
{
    $parentActivityId=(int)$parentActivityId;
    if($parentActivityId<=0)return true;
    $db=qa_db();

    $stmt=$db->prepare(
        "SELECT COUNT(*),COALESCE(AVG(progress_percent),0)
         FROM qa_project_activities
         WHERE parent_activity_id=? AND status<>'cancelled'"
    );
    if(!$stmt)return false;
    $stmt->bind_param('i',$parentActivityId);
    $stmt->execute();
    $stmt->bind_result($count,$avgProgress);
    $stmt->fetch();
    $stmt->close();

    $count=(int)$count;
    $avgProgress=$count>0?(float)$avgProgress:0.0;
    if($avgProgress<0)$avgProgress=0;
    if($avgProgress>100)$avgProgress=100;
    $status=$avgProgress>=100?'completed':($avgProgress>0?'in_progress':'planned');

    if($status==='completed'){
        $stmt=$db->prepare(
            "UPDATE qa_project_activities
             SET progress_percent=?,status=?,last_reported_at=NOW(),completed_at=NOW()
             WHERE activity_id=?"
        );
    }else{
        $stmt=$db->prepare(
            "UPDATE qa_project_activities
             SET progress_percent=?,status=?,last_reported_at=NOW(),completed_at=NULL
             WHERE activity_id=?"
        );
    }
    if(!$stmt)return false;
    $stmt->bind_param('dsi',$avgProgress,$status,$parentActivityId);
    $ok=$stmt->execute();
    $stmt->close();
    if(!$ok)return false;

    $grandParent=(int)qa_db_scalar(
        "SELECT COALESCE(parent_activity_id,0) FROM qa_project_activities WHERE activity_id=".$parentActivityId,0
    );
    if($grandParent>0)return qa_activity_recalculate_parent($grandParent);
    return true;
}

function qa_activity_recalculate_ancestors($activityId)
{
    $parent=(int)qa_db_scalar(
        "SELECT COALESCE(parent_activity_id,0) FROM qa_project_activities WHERE activity_id=".(int)$activityId,0
    );
    if($parent<=0)return true;
    return qa_activity_recalculate_parent($parent);
}

function qa_activity_evidence_code($yearBE)
{
    $db=qa_db();
    $prefix='EV-'.$yearBE.'-';
    $esc=$db->real_escape_string($prefix);
    $next=(int)qa_db_scalar(
        "SELECT COALESCE(MAX(CAST(SUBSTRING(evidence_code,".(strlen($prefix)+1).") AS UNSIGNED)),0)+1
         FROM qa_evidences WHERE evidence_code LIKE '".$esc."%'",1
    );
    if($next<=0)$next=1;
    return $prefix.str_pad($next,5,'0',STR_PAD_LEFT);
}
