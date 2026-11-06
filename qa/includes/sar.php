<?php
/* QA v1.3 — SAR Builder helper */

function qa_sar_tables_ready()
{
    static $ready=null;
    if($ready!==null)return $ready;

    $db=qa_db();
    $tables=array('qa_sar_builder_reports','qa_sar_builder_standard_entries','qa_sar_builder_indicator_entries');
    foreach($tables as $table){
        $result=$db->query("SHOW TABLES LIKE '".$db->real_escape_string($table)."'");
        if(!$result || $result->num_rows<1){
            if($result)$result->free();
            $ready=false;
            return false;
        }
        $result->free();
    }
    $ready=true;
    return true;
}

function qa_sar_can_edit()
{
    return qa_user_can('sar.edit');
}

function qa_sar_can_finalize()
{
    return qa_user_can('sar.finalize');
}

function qa_sar_status_label($code)
{
    $map=array(
        'DRAFT'=>'ฉบับร่าง',
        'REVIEW'=>'รอตรวจรับรอง',
        'FINAL'=>'รับรองแล้ว'
    );
    return isset($map[$code])?$map[$code]:$code;
}

function qa_sar_status_badge($code)
{
    if($code==='FINAL')return 'badge-green';
    if($code==='REVIEW')return 'badge-yellow';
    return 'badge-blue';
}

function qa_sar_framework()
{
    $db=qa_db();
    $result=$db->query(
        "SELECT * FROM qa_quality_frameworks
         WHERE framework_code='ONESQA'
         ORDER BY framework_id DESC LIMIT 1"
    );
    $row=$result?$result->fetch_assoc():null;
    if($result)$result->free();
    return $row;
}

function qa_sar_report_by_id($reportId)
{
    if(!qa_sar_tables_ready())return null;
    $db=qa_db();
    $stmt=$db->prepare(
        "SELECT r.*,y.fiscal_year_be,f.framework_code,f.framework_name
         FROM qa_sar_builder_reports r
         INNER JOIN qa_years y ON y.year_id=r.year_id
         INNER JOIN qa_quality_frameworks f ON f.framework_id=r.framework_id
         WHERE r.report_id=? LIMIT 1"
    );
    if(!$stmt)return null;
    $stmt->bind_param('i',$reportId);
    $stmt->execute();
    $result=$stmt->get_result();
    $row=$result?$result->fetch_assoc():null;
    if($result)$result->free();
    $stmt->close();
    return $row;
}

function qa_sar_current_report($yearId,$frameworkId)
{
    if(!qa_sar_tables_ready())return null;
    $db=qa_db();
    $stmt=$db->prepare(
        "SELECT r.*,y.fiscal_year_be,f.framework_code,f.framework_name
         FROM qa_sar_builder_reports r
         INNER JOIN qa_years y ON y.year_id=r.year_id
         INNER JOIN qa_quality_frameworks f ON f.framework_id=r.framework_id
         WHERE r.year_id=? AND r.framework_id=? LIMIT 1"
    );
    if(!$stmt)return null;
    $stmt->bind_param('ii',$yearId,$frameworkId);
    $stmt->execute();
    $result=$stmt->get_result();
    $row=$result?$result->fetch_assoc():null;
    if($result)$result->free();
    $stmt->close();
    return $row;
}

function qa_sar_indicator_metrics($yearId,$indicatorId)
{
    $db=qa_db();
    $yearId=(int)$yearId;
    $indicatorId=(int)$indicatorId;

    $sql="SELECT
        COUNT(DISTINCT p.project_id) AS project_count,
        COUNT(DISTINCT CASE WHEN p.status_code IN ('COMPLETED','CLOSED') THEN p.project_id END) AS completed_count,
        COUNT(DISTINCT CASE WHEN pr.approved_at IS NOT NULL THEN p.project_id END) AS certified_count,
        COALESCE(SUM(DISTINCT CASE WHEN p.project_id IS NOT NULL THEN p.approved_budget ELSE 0 END),0) AS approved_budget
      FROM qa_project_indicator_links pil
      INNER JOIN qa_projects p ON p.project_id=pil.project_id
      LEFT JOIN qa_project_results pr ON pr.project_id=p.project_id
      WHERE pil.indicator_id=".$indicatorId."
        AND p.year_id=".$yearId."
        AND p.status_code NOT IN ('DRAFT','REVISION','REJECTED','CANCELLED')";

    $result=$db->query($sql);
    $base=$result?$result->fetch_assoc():array();
    if($result)$result->free();

    $actual=(float)qa_db_scalar(
        "SELECT COALESCE(SUM(e.amount),0)
         FROM qa_expenditures e
         INNER JOIN qa_projects p ON p.project_id=e.project_id
         INNER JOIN qa_project_indicator_links pil ON pil.project_id=p.project_id
         WHERE pil.indicator_id=".$indicatorId."
           AND p.year_id=".$yearId."
           AND e.payment_status='paid'",0
    );

    $evidence=(int)qa_db_scalar(
        "SELECT COUNT(DISTINCT ev.evidence_id)
         FROM qa_evidence_indicator_links eil
         INNER JOIN qa_evidences ev ON ev.evidence_id=eil.evidence_id
         INNER JOIN qa_projects p ON p.project_id=ev.project_id
         WHERE eil.indicator_id=".$indicatorId."
           AND p.year_id=".$yearId,0
    );

    $kpiTotal=(int)qa_db_scalar(
        "SELECT COUNT(*)
         FROM qa_project_kpis k
         INNER JOIN qa_projects p ON p.project_id=k.project_id
         INNER JOIN qa_project_indicator_links pil ON pil.project_id=p.project_id
         WHERE pil.indicator_id=".$indicatorId."
           AND p.year_id=".$yearId."
           AND p.status_code NOT IN ('DRAFT','REVISION','REJECTED','CANCELLED')",0
    );

    $kpiAchieved=(int)qa_db_scalar(
        "SELECT COUNT(*)
         FROM qa_project_kpis k
         INNER JOIN qa_projects p ON p.project_id=k.project_id
         INNER JOIN qa_project_indicator_links pil ON pil.project_id=p.project_id
         WHERE pil.indicator_id=".$indicatorId."
           AND p.year_id=".$yearId."
           AND p.status_code NOT IN ('DRAFT','REVISION','REJECTED','CANCELLED')
           AND (
              SELECT kr.achievement_status
              FROM qa_project_kpi_results kr
              WHERE kr.kpi_id=k.kpi_id
              ORDER BY kr.kpi_result_id DESC
              LIMIT 1
           )='achieved'",0
    );

    return array(
        'project_count'=>isset($base['project_count'])?(int)$base['project_count']:0,
        'completed_count'=>isset($base['completed_count'])?(int)$base['completed_count']:0,
        'certified_count'=>isset($base['certified_count'])?(int)$base['certified_count']:0,
        'approved_budget'=>isset($base['approved_budget'])?(float)$base['approved_budget']:0,
        'actual_expense'=>$actual,
        'evidence_count'=>$evidence,
        'kpi_total'=>$kpiTotal,
        'kpi_achieved'=>$kpiAchieved
    );
}

function qa_sar_auto_summary($yearBE,$indicator,$metrics)
{
    return 'ปีงบประมาณ '.$yearBE.' ตัวชี้วัด '.$indicator['indicator_code'].' “'.$indicator['indicator_name'].'” '
        .'มีโครงการ/งาน/กิจกรรมที่เชื่อมโยง '.$metrics['project_count'].' รายการ '
        .'เสร็จสิ้น/ปิดโครงการ '.$metrics['completed_count'].' รายการ '
        .'ผลโครงการได้รับการรับรอง '.$metrics['certified_count'].' รายการ '
        .'วงเงินอนุมัติรวม '.qa_money($metrics['approved_budget']).' บาท '
        .'เบิกจ่ายจริง '.qa_money($metrics['actual_expense']).' บาท '
        .'มีหลักฐานเชื่อมตรง '.$metrics['evidence_count'].' รายการ '
        .'และ KPI บรรลุ '.$metrics['kpi_achieved'].' จาก '.$metrics['kpi_total'].' ตัว';
}
