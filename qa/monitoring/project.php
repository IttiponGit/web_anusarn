<?php
$pageTitle = 'ติดตามโครงการ';
$activeMenu = 'monitoring';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

$db = qa_db();
$user = qa_current_user();
$userId = $user ? (int)$user['user_id'] : 0;
$currentYear = qa_current_year_row();
$currentYearBE = $currentYear ? (int)$currentYear['fiscal_year_be'] : 0;

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($projectId <= 0) {
    http_response_code(400);
    die('ไม่พบรหัสโครงการ');
}

function mon_load_project($id)
{
    $db = qa_db();
    $stmt = $db->prepare(
        "SELECT p.*, d.division_name, ps.status_name, pl.plan_code, pl.plan_name,
                CONCAT(COALESCE(u.prefix,''),COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS owner_name
         FROM qa_projects p
         INNER JOIN qa_divisions d ON d.division_id=p.division_id
         LEFT JOIN qa_project_statuses ps ON ps.status_code=p.status_code
         LEFT JOIN qa_plans pl ON pl.plan_id=p.plan_id
         LEFT JOIN qa_users u ON u.user_id=p.owner_user_id
         WHERE p.project_id=? LIMIT 1"
    );
    $stmt->bind_param('i',$id);
    $stmt->execute();
    $result=$stmt->get_result();
    $row=$result?$result->fetch_assoc():null;
    if($result)$result->free();
    $stmt->close();
    return $row;
}

function mon_profile($userId)
{
    $db = qa_db();
    $stmt=$db->prepare("SELECT primary_division_id FROM qa_users WHERE user_id=? LIMIT 1");
    $stmt->bind_param('i',$userId);
    $stmt->execute();
    $stmt->bind_result($divisionId);
    $stmt->fetch();
    $stmt->close();
    return (int)$divisionId;
}

function mon_can_manage($project)
{
    $user=qa_current_user();
    if(!$user||!$project)return false;
    if(qa_user_has_role('admin'))return true;
    if((int)$project['owner_user_id']===(int)$user['user_id'])return true;
    if(qa_user_has_role('division_head')){
        return mon_profile((int)$user['user_id'])===(int)$project['division_id'];
    }
    return false;
}

function mon_can_finance()
{
    return qa_user_has_role('admin') || qa_user_has_role('finance');
}

function mon_can_approve_result()
{
    return qa_user_has_role('admin') || qa_user_has_role('director');
}

function mon_flash($type,$text)
{
    $_SESSION['qa_monitoring_flash']=array('type'=>$type,'text'=>$text);
}

function mon_redirect($projectId,$anchor='')
{
    $url=qa_url('monitoring/project.php?id='.(int)$projectId);
    if($anchor!=='')$url.='#'.$anchor;
    header('Location: '.$url);
    exit;
}

function mon_number($value)
{
    $value=str_replace(',','',trim((string)$value));
    if($value==='')return null;
    return is_numeric($value)?(float)$value:false;
}

function mon_date($value)
{
    $value=trim((string)$value);
    if($value==='')return '';
    $p=explode('-',$value);
    if(count($p)!==3)return false;
    return checkdate((int)$p[1],(int)$p[2],(int)$p[0])?$value:false;
}

function mon_kpi_status($operator,$target,$actual)
{
    if($actual===null||$target===null)return 'pending';
    $target=(float)$target;
    $actual=(float)$actual;
    if($operator==='<=')return $actual<=$target?'achieved':'not_achieved';
    if($operator==='=')return abs($actual-$target)<0.0001?'achieved':'not_achieved';
    return $actual>=$target?'achieved':'not_achieved';
}

function mon_evidence_code($yearBE)
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

$project=mon_load_project($projectId);
if(!$project){
    http_response_code(404);
    die('ไม่พบโครงการ');
}

$allowedStatuses=array('APPROVED','IN_PROGRESS','WAITING_REPORT','COMPLETED','CLOSED');
if(!in_array($project['status_code'],$allowedStatuses,true)){
    http_response_code(400);
    die('โครงการยังไม่อยู่ในขั้นติดตามและประเมินผล');
}

$pageHeading=$project['project_code'].' — '.$project['project_name'];
$pageDescription='บันทึกความก้าวหน้า เบิกจ่ายจริง KPI หลักฐาน และสรุปผลโครงการ';
$breadcrumbs=array(
    array('label'=>'ติดตามและประเมินผล','url'=>qa_url('monitoring/index.php')),
    array('label'=>$project['project_code'])
);

$canManage=mon_can_manage($project);
$canFinance=mon_can_finance();
$canApproveResult=mon_can_approve_result();

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(qa_project_change_has_pending($projectId)){
        mon_flash('danger','โครงการมีคำขอแก้ไข/ยกเลิกที่รอพิจารณา จึงพักการบันทึกข้อมูลชั่วคราว');
        mon_redirect($projectId);
    }

    $token=isset($_POST['csrf_token'])?$_POST['csrf_token']:'';
    if(!qa_verify_csrf($token)){
        http_response_code(400);
        die('CSRF token ไม่ถูกต้อง');
    }

    $action=isset($_POST['action'])?trim($_POST['action']):'';

    /* เริ่มดำเนินโครงการ */
    if($action==='start_project'){
        if(!$canManage){
            http_response_code(403);
            die('คุณไม่มีสิทธิ์เริ่มดำเนินโครงการ');
        }
        if($project['status_code']!=='APPROVED'){
            mon_flash('danger','โครงการไม่ได้อยู่ในสถานะ “อนุมัติแล้ว”');
            mon_redirect($projectId);
        }

        $old=$project['status_code'];
        $stmt=$db->prepare("UPDATE qa_projects SET status_code='IN_PROGRESS' WHERE project_id=?");
        $stmt->bind_param('i',$projectId);
        $ok=$stmt->execute();
        $stmt->close();

        if($ok){
            qa_project_add_history($projectId,$old,'IN_PROGRESS',$userId,'เริ่มดำเนินโครงการ');
            qa_project_audit_action('start_project','qa_projects',$projectId,array('status_code'=>$old),array('status_code'=>'IN_PROGRESS'));
            mon_flash('success','เริ่มดำเนินโครงการเรียบร้อยแล้ว');
        }else{
            mon_flash('danger','ไม่สามารถเปลี่ยนสถานะโครงการได้');
        }
        mon_redirect($projectId);
    }

    /* Progress Report + KPI Result */
    if($action==='add_progress'){
        if(!$canManage){
            http_response_code(403);
            die('คุณไม่มีสิทธิ์รายงานความก้าวหน้า');
        }
        if($project['status_code']!=='IN_PROGRESS'){
            mon_flash('danger','บันทึกความก้าวหน้าได้เฉพาะโครงการที่กำลังดำเนินการ');
            mon_redirect($projectId,'progress');
        }

        $reportDate=mon_date(isset($_POST['report_date'])?$_POST['report_date']:'');
        $progress=mon_number(isset($_POST['progress_percent'])?$_POST['progress_percent']:'');
        $activitySummary=trim(isset($_POST['activity_summary'])?$_POST['activity_summary']:'');
        $outputSummary=trim(isset($_POST['output_summary'])?$_POST['output_summary']:'');
        $problemSummary=trim(isset($_POST['problem_summary'])?$_POST['problem_summary']:'');
        $nextStepSummary=trim(isset($_POST['next_step_summary'])?$_POST['next_step_summary']:'');

        $errors=array();
        if($reportDate===false||$reportDate==='')$errors[]='กรุณาระบุวันที่รายงาน';
        if($progress===false||$progress===null||$progress<0||$progress>100)$errors[]='ความก้าวหน้าต้องอยู่ระหว่าง 0-100';
        if($activitySummary==='')$errors[]='กรุณาระบุสรุปกิจกรรม/ความก้าวหน้า';

        if(!empty($errors)){
            mon_flash('danger',implode(' / ',$errors));
            mon_redirect($projectId,'progress');
        }

        $actualSpent=(float)qa_db_scalar(
            "SELECT COALESCE(SUM(amount),0) FROM qa_expenditures
             WHERE project_id=".(int)$projectId." AND payment_status='paid'",0
        );
        $reportNo=(int)qa_db_scalar(
            "SELECT COALESCE(MAX(report_no),0)+1 FROM qa_progress_reports WHERE project_id=".(int)$projectId,1
        );

        $db->autocommit(false);
        try{
            $stmt=$db->prepare(
                "INSERT INTO qa_progress_reports
                (project_id,report_no,report_date,progress_percent,activity_summary,output_summary,
                 problem_summary,next_step_summary,amount_spent_to_date,reported_by)
                VALUES (?,?,?,?,NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),?,NULLIF(?,0))"
            );
            $stmt->bind_param(
                'iisdssssdi',
                $projectId,$reportNo,$reportDate,$progress,$activitySummary,$outputSummary,
                $problemSummary,$nextStepSummary,$actualSpent,$userId
            );
            if(!$stmt->execute())throw new Exception('ไม่สามารถบันทึกรายงานความก้าวหน้าได้: '.$stmt->error);
            $progressReportId=$stmt->insert_id;
            $stmt->close();

            $actualValues=isset($_POST['kpi_actual'])&&is_array($_POST['kpi_actual'])?$_POST['kpi_actual']:array();
            $resultTexts=isset($_POST['kpi_result_text'])&&is_array($_POST['kpi_result_text'])?$_POST['kpi_result_text']:array();

            $stmt=$db->prepare(
                "SELECT kpi_id,target_value,target_operator
                 FROM qa_project_kpis WHERE project_id=? ORDER BY sort_order,kpi_id"
            );
            $stmt->bind_param('i',$projectId);
            $stmt->execute();
            $result=$stmt->get_result();
            $kpiMeta=array();
            if($result){
                while($row=$result->fetch_assoc())$kpiMeta[]=$row;
                $result->free();
            }
            $stmt->close();

            foreach($kpiMeta as $meta){
                $kpiId=(int)$meta['kpi_id'];
                $raw=isset($actualValues[$kpiId])?trim($actualValues[$kpiId]):'';
                $text=isset($resultTexts[$kpiId])?trim($resultTexts[$kpiId]):'';
                if($raw===''&&$text==='')continue;

                $actual=null;
                if($raw!==''){
                    $actual=mon_number($raw);
                    if($actual===false)throw new Exception('ค่า KPI ต้องเป็นตัวเลข');
                }
                $status=mon_kpi_status(
                    $meta['target_operator'],
                    $meta['target_value']!==null?(float)$meta['target_value']:null,
                    $actual
                );
                $actualDb=$actual===null?null:$actual;

                $stmt=$db->prepare(
                    "INSERT INTO qa_project_kpi_results
                    (kpi_id,progress_report_id,actual_value,result_text,achievement_status,measured_at,recorded_by)
                    VALUES (?,?,?,NULLIF(?,''),?, ?, NULLIF(?,0))"
                );
                $stmt->bind_param(
                    'iidsssi',
                    $kpiId,$progressReportId,$actualDb,$text,$status,$reportDate,$userId
                );
                if(!$stmt->execute())throw new Exception('ไม่สามารถบันทึกผล KPI ได้');
                $stmt->close();
            }

            $db->commit();
            $db->autocommit(true);

            qa_project_audit_action(
                'add_progress_report','qa_progress_reports',$progressReportId,null,
                array('project_id'=>$projectId,'report_no'=>$reportNo,'progress_percent'=>$progress)
            );
            mon_flash('success','บันทึกรายงานความก้าวหน้าครั้งที่ '.$reportNo.' เรียบร้อยแล้ว');
            mon_redirect($projectId,'progress');

        }catch(Exception $e){
            $db->rollback();
            $db->autocommit(true);
            mon_flash('danger',$e->getMessage());
            mon_redirect($projectId,'progress');
        }
    }

    /* Expenditure */
    if($action==='add_expenditure'){
        if(!$canFinance){
            http_response_code(403);
            die('เฉพาะงานการเงินหรือผู้ดูแลระบบที่บันทึกการเบิกจ่ายจริงได้');
        }
        if(!in_array($project['status_code'],array('IN_PROGRESS','WAITING_REPORT'),true)){
            mon_flash('danger','บันทึกการใช้จ่ายได้เฉพาะโครงการที่กำลังดำเนินการหรือรอรายงานผล');
            mon_redirect($projectId,'finance');
        }

        $budgetItemId=isset($_POST['budget_item_id'])?(int)$_POST['budget_item_id']:0;
        $expenseDate=mon_date(isset($_POST['expense_date'])?$_POST['expense_date']:'');
        $documentNo=trim(isset($_POST['document_no'])?$_POST['document_no']:'');
        $payee=trim(isset($_POST['payee_name'])?$_POST['payee_name']:'');
        $detail=trim(isset($_POST['expense_detail'])?$_POST['expense_detail']:'');
        $amount=mon_number(isset($_POST['amount'])?$_POST['amount']:'');

        if($budgetItemId<=0||$expenseDate===false||$expenseDate===''||$detail===''||$amount===false||$amount===null||$amount<=0){
            mon_flash('danger','กรุณากรอกข้อมูลการเบิกจ่ายให้ครบถ้วน');
            mon_redirect($projectId,'finance');
        }

        $item=null;
        $stmt=$db->prepare(
            "SELECT bi.budget_item_id,bi.activity_id,bi.budget_pool_id,bi.item_name,bi.approved_amount,
                    COALESCE((SELECT SUM(e.amount) FROM qa_expenditures e
                              WHERE e.budget_item_id=bi.budget_item_id AND e.payment_status='paid'),0) AS spent
             FROM qa_project_budget_items bi
             WHERE bi.budget_item_id=? AND bi.project_id=? LIMIT 1"
        );
        $stmt->bind_param('ii',$budgetItemId,$projectId);
        $stmt->execute();
        $result=$stmt->get_result();
        $item=$result?$result->fetch_assoc():null;
        if($result)$result->free();
        $stmt->close();

        if(!$item){
            mon_flash('danger','ไม่พบรายการงบประมาณที่เลือก');
            mon_redirect($projectId,'finance');
        }

        $remaining=(float)$item['approved_amount']-(float)$item['spent'];
        if($amount>$remaining+0.005){
            mon_flash('danger','ยอดเบิกเกินวงเงินคงเหลือของรายการนี้ '.qa_money($remaining).' บาท');
            mon_redirect($projectId,'finance');
        }

        $stmt=$db->prepare(
            "INSERT INTO qa_expenditures
            (project_id,activity_id,budget_item_id,budget_pool_id,expense_date,document_no,payee_name,
             expense_detail,amount,payment_status,created_by,verified_by)
            VALUES (?,?,?,?,?,NULLIF(?,''),NULLIF(?,''),?,?,'paid',NULLIF(?,0),NULLIF(?,0))"
        );
        $stmt->bind_param(
            'iiiissssdii',
            $projectId,$item['activity_id'],$budgetItemId,$item['budget_pool_id'],$expenseDate,
            $documentNo,$payee,$detail,$amount,$userId,$userId
        );
        if($stmt->execute()){
            $newId=$stmt->insert_id;
            $stmt->close();
            qa_project_audit_action(
                'add_expenditure','qa_expenditures',$newId,null,
                array('project_id'=>$projectId,'budget_item_id'=>$budgetItemId,'amount'=>$amount,'document_no'=>$documentNo)
            );
            mon_flash('success','บันทึกการเบิกจ่ายจริง '.qa_money($amount).' บาทเรียบร้อยแล้ว');
        }else{
            $err=$stmt->error;
            $stmt->close();
            mon_flash('danger','ไม่สามารถบันทึกการเบิกจ่ายได้: '.$err);
        }
        mon_redirect($projectId,'finance');
    }

    /* Evidence */
    if($action==='add_evidence'){
        if(!$canManage){
            http_response_code(403);
            die('คุณไม่มีสิทธิ์เพิ่มหลักฐานของโครงการ');
        }

        $evidenceType=trim(isset($_POST['evidence_type'])?$_POST['evidence_type']:'');
        $title=trim(isset($_POST['title'])?$_POST['title']:'');
        $description=trim(isset($_POST['description'])?$_POST['description']:'');
        $documentNo=trim(isset($_POST['evidence_document_no'])?$_POST['evidence_document_no']:'');
        $evidenceDate=mon_date(isset($_POST['evidence_date'])?$_POST['evidence_date']:'');
        $activityId=isset($_POST['evidence_activity_id'])?(int)$_POST['evidence_activity_id']:0;
        $progressReportId=isset($_POST['evidence_progress_report_id'])?(int)$_POST['evidence_progress_report_id']:0;
        $externalUrl=trim(isset($_POST['external_url'])?$_POST['external_url']:'');
        $indicatorIds=isset($_POST['evidence_indicator_ids'])&&is_array($_POST['evidence_indicator_ids'])
            ? array_map('intval',$_POST['evidence_indicator_ids']) : array();

        if($evidenceType===''||$title===''||$evidenceDate===false||$evidenceDate===''){
            mon_flash('danger','กรุณาระบุประเภท ชื่อ และวันที่ของหลักฐาน');
            mon_redirect($projectId,'evidence');
        }

        if($externalUrl!=='' && !preg_match('#^https?://#i',$externalUrl)){
            mon_flash('danger','URL หลักฐานต้องขึ้นต้นด้วย http:// หรือ https://');
            mon_redirect($projectId,'evidence');
        }

        $hasFile=isset($_FILES['evidence_file']) &&
                 isset($_FILES['evidence_file']['error']) &&
                 $_FILES['evidence_file']['error']!==UPLOAD_ERR_NO_FILE;

        if(!$hasFile&&$externalUrl===''){
            mon_flash('danger','กรุณาแนบไฟล์หรือระบุ URL ภายนอกอย่างน้อยหนึ่งอย่าง');
            mon_redirect($projectId,'evidence');
        }

        $filePath='';
        $mimeType='';
        $fileSize=null;

        if($hasFile){
            $file=$_FILES['evidence_file'];
            if($file['error']!==UPLOAD_ERR_OK){
                mon_flash('danger','อัปโหลดไฟล์ไม่สำเร็จ (error '.$file['error'].')');
                mon_redirect($projectId,'evidence');
            }
            if((int)$file['size']>10*1024*1024){
                mon_flash('danger','ไฟล์หลักฐานต้องมีขนาดไม่เกิน 10 MB');
                mon_redirect($projectId,'evidence');
            }

            $original=$file['name'];
            $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
            $allowed=array('pdf','jpg','jpeg','png','webp','doc','docx','xls','xlsx');
            if(!in_array($ext,$allowed,true)){
                mon_flash('danger','รองรับไฟล์ PDF, รูปภาพ, Word และ Excel เท่านั้น');
                mon_redirect($projectId,'evidence');
            }

            $safeCode=preg_replace('/[^A-Za-z0-9_-]/','_',$project['project_code']);
            $dirRelative='uploads/evidences/'.$currentYearBE.'/'.$safeCode;
            $dirAbsolute=QA_ROOT.'/'.$dirRelative;

            if(!is_dir($dirAbsolute)&&!mkdir($dirAbsolute,0755,true)){
                mon_flash('danger','ไม่สามารถสร้างโฟลเดอร์เก็บหลักฐานได้ กรุณาตรวจสอบ Permission');
                mon_redirect($projectId,'evidence');
            }

            $random=sha1(uniqid(mt_rand(),true));
            $fileName=date('YmdHis').'_'.$random.'.'.$ext;
            $absolute=$dirAbsolute.'/'.$fileName;

            if(!move_uploaded_file($file['tmp_name'],$absolute)){
                mon_flash('danger','ไม่สามารถบันทึกไฟล์ลงเซิร์ฟเวอร์ได้');
                mon_redirect($projectId,'evidence');
            }

            $filePath=$dirRelative.'/'.$fileName;
            $fileSize=(int)$file['size'];
            if(function_exists('finfo_open')){
                $fi=finfo_open(FILEINFO_MIME_TYPE);
                if($fi){
                    $mimeType=finfo_file($fi,$absolute);
                    finfo_close($fi);
                }
            }
            if($mimeType==='')$mimeType=isset($file['type'])?$file['type']:'application/octet-stream';
        }

        $code=mon_evidence_code($currentYearBE);
        $activityDb=$activityId>0?$activityId:null;
        $progressDb=$progressReportId>0?$progressReportId:null;
        $fileSizeDb=$fileSize===null?null:$fileSize;

        $db->autocommit(false);
        try{
            $stmt=$db->prepare(
                "INSERT INTO qa_evidences
                (evidence_code,plan_id,project_id,activity_id,progress_report_id,evidence_type,title,description,
                 document_no,evidence_date,file_path,external_url,mime_type,file_size,is_public,uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?,''), NULLIF(?,''), ?, NULLIF(?,''), NULLIF(?,''), NULLIF(?,''), ?, 0, NULLIF(?,0))"
            );
            $stmt->bind_param(
                'siiiissssssssii',
                $code,$project['plan_id'],$projectId,$activityDb,$progressDb,$evidenceType,$title,$description,
                $documentNo,$evidenceDate,$filePath,$externalUrl,$mimeType,$fileSizeDb,$userId
            );
            if(!$stmt->execute())throw new Exception('ไม่สามารถบันทึกหลักฐานได้: '.$stmt->error);
            $evidenceId=$stmt->insert_id;
            $stmt->close();

            foreach($indicatorIds as $indicatorId){
                if($indicatorId<=0)continue;
                $stmt=$db->prepare(
                    "INSERT IGNORE INTO qa_evidence_indicator_links(evidence_id,indicator_id) VALUES (?,?)"
                );
                $stmt->bind_param('ii',$evidenceId,$indicatorId);
                if(!$stmt->execute())throw new Exception('ไม่สามารถเชื่อมหลักฐานกับตัวชี้วัดได้');
                $stmt->close();
            }

            $db->commit();
            $db->autocommit(true);

            qa_project_audit_action(
                'add_evidence','qa_evidences',$evidenceId,null,
                array('evidence_code'=>$code,'project_id'=>$projectId,'title'=>$title,'file_path'=>$filePath,'external_url'=>$externalUrl)
            );
            mon_flash('success','เพิ่มหลักฐาน '.$code.' เรียบร้อยแล้ว');
            mon_redirect($projectId,'evidence');

        }catch(Exception $e){
            $db->rollback();
            $db->autocommit(true);
            if($filePath!==''&&file_exists(QA_ROOT.'/'.$filePath))@unlink(QA_ROOT.'/'.$filePath);
            mon_flash('danger',$e->getMessage());
            mon_redirect($projectId,'evidence');
        }
    }

    /* Save / Submit final result */
    if($action==='save_final' || $action==='submit_final'){
        if(!$canManage){
            http_response_code(403);
            die('คุณไม่มีสิทธิ์สรุปผลโครงการ');
        }
        if(!in_array($project['status_code'],array('IN_PROGRESS','WAITING_REPORT'),true)){
            mon_flash('danger','สถานะโครงการไม่รองรับการสรุปผล');
            mon_redirect($projectId,'final');
        }

        $reportDate=mon_date(isset($_POST['final_report_date'])?$_POST['final_report_date']:'');
        $output=trim(isset($_POST['final_output'])?$_POST['final_output']:'');
        $outcome=trim(isset($_POST['final_outcome'])?$_POST['final_outcome']:'');
        $success=trim(isset($_POST['success_summary'])?$_POST['success_summary']:'');
        $problem=trim(isset($_POST['final_problem'])?$_POST['final_problem']:'');
        $recommendation=trim(isset($_POST['recommendation_summary'])?$_POST['recommendation_summary']:'');
        $lesson=trim(isset($_POST['lesson_learned'])?$_POST['lesson_learned']:'');
        $overall=trim(isset($_POST['overall_status'])?$_POST['overall_status']:'partial');

        if($reportDate===false||$reportDate===''||$output===''||$outcome===''){
            mon_flash('danger','กรุณาระบุวันที่รายงาน Output และ Outcome');
            mon_redirect($projectId,'final');
        }
        if(!in_array($overall,array('achieved','partial','not_achieved'),true))$overall='partial';

        $exists=(int)qa_db_scalar(
            "SELECT COUNT(*) FROM qa_project_results WHERE project_id=".(int)$projectId,0
        );

        if($exists>0){
            $stmt=$db->prepare(
                "UPDATE qa_project_results
                 SET report_date=?,output_summary=?,outcome_summary=?,success_summary=NULLIF(?, ''),
                     problem_summary=NULLIF(?, ''),recommendation_summary=NULLIF(?, ''),
                     lesson_learned=NULLIF(?, ''),overall_status=?,reported_by=?,approved_by=NULL,approved_at=NULL
                 WHERE project_id=?"
            );
            $stmt->bind_param(
                'ssssssssii',
                $reportDate,$output,$outcome,$success,$problem,$recommendation,$lesson,$overall,$userId,$projectId
            );
        }else{
            $stmt=$db->prepare(
                "INSERT INTO qa_project_results
                (project_id,report_date,output_summary,outcome_summary,success_summary,problem_summary,
                 recommendation_summary,lesson_learned,overall_status,reported_by)
                VALUES (?,?,?,?,NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),?,NULLIF(?,0))"
            );
            $stmt->bind_param(
                'issssssssi',
                $projectId,$reportDate,$output,$outcome,$success,$problem,$recommendation,$lesson,$overall,$userId
            );
        }

        if(!$stmt->execute()){
            $err=$stmt->error;
            $stmt->close();
            mon_flash('danger','ไม่สามารถบันทึกสรุปผลได้: '.$err);
            mon_redirect($projectId,'final');
        }
        $stmt->close();

        if($action==='submit_final' && $project['status_code']!=='WAITING_REPORT'){
            $old=$project['status_code'];
            $stmt=$db->prepare("UPDATE qa_projects SET status_code='WAITING_REPORT' WHERE project_id=?");
            $stmt->bind_param('i',$projectId);
            $stmt->execute();
            $stmt->close();
            qa_project_add_history($projectId,$old,'WAITING_REPORT',$userId,'ส่งรายงานผลโครงการเพื่อตรวจรับ');
        }

        qa_project_audit_action(
            $action==='submit_final'?'submit_project_result':'save_project_result',
            'qa_project_results',$projectId,null,array('project_id'=>$projectId,'overall_status'=>$overall)
        );
        mon_flash('success',$action==='submit_final'?'ส่งรายงานผลเพื่อตรวจรับเรียบร้อยแล้ว':'บันทึกร่างสรุปผลเรียบร้อยแล้ว');
        mon_redirect($projectId,'final');
    }

    /* Approve final result */
    if($action==='approve_final'){
        if(!$canApproveResult){
            http_response_code(403);
            die('เฉพาะผู้อำนวยการหรือผู้ดูแลระบบที่ตรวจรับผลโครงการได้');
        }
        if($project['status_code']!=='WAITING_REPORT'){
            mon_flash('danger','โครงการไม่ได้อยู่ในสถานะรอรายงานผล');
            mon_redirect($projectId,'final');
        }

        $resultId=(int)qa_db_scalar(
            "SELECT project_result_id FROM qa_project_results WHERE project_id=".(int)$projectId." LIMIT 1",0
        );
        if($resultId<=0){
            mon_flash('danger','ยังไม่มีรายงานผลโครงการ');
            mon_redirect($projectId,'final');
        }

        $db->autocommit(false);
        try{
            $stmt=$db->prepare("UPDATE qa_project_results SET approved_by=?,approved_at=NOW() WHERE project_result_id=?");
            $stmt->bind_param('ii',$userId,$resultId);
            if(!$stmt->execute())throw new Exception('ไม่สามารถรับรองรายงานผลได้');
            $stmt->close();

            $stmt=$db->prepare("UPDATE qa_projects SET status_code='COMPLETED' WHERE project_id=?");
            $stmt->bind_param('i',$projectId);
            if(!$stmt->execute())throw new Exception('ไม่สามารถเปลี่ยนสถานะโครงการได้');
            $stmt->close();

            qa_project_add_history($projectId,'WAITING_REPORT','COMPLETED',$userId,'ผู้อำนวยการรับรองผลการดำเนินโครงการ');

            $db->commit();
            $db->autocommit(true);
            qa_project_audit_action('approve_project_result','qa_project_results',$resultId,null,array('approved_by'=>$userId));
            mon_flash('success','รับรองรายงานผลและเปลี่ยนโครงการเป็น “เสร็จสิ้น” เรียบร้อยแล้ว');
        }catch(Exception $e){
            $db->rollback();
            $db->autocommit(true);
            mon_flash('danger',$e->getMessage());
        }
        mon_redirect($projectId,'final');
    }

    if($action==='close_project'){
        if(!$canApproveResult){
            http_response_code(403);
            die('เฉพาะผู้อำนวยการหรือผู้ดูแลระบบที่ปิดโครงการได้');
        }
        if($project['status_code']!=='COMPLETED'){
            mon_flash('danger','ปิดโครงการได้หลังสถานะ “เสร็จสิ้น” เท่านั้น');
            mon_redirect($projectId);
        }

        $stmt=$db->prepare("UPDATE qa_projects SET status_code='CLOSED' WHERE project_id=?");
        $stmt->bind_param('i',$projectId);
        if($stmt->execute()){
            qa_project_add_history($projectId,'COMPLETED','CLOSED',$userId,'ปิดโครงการ');
            qa_project_audit_action('close_project','qa_projects',$projectId,array('status_code'=>'COMPLETED'),array('status_code'=>'CLOSED'));
            mon_flash('success','ปิดโครงการเรียบร้อยแล้ว');
        }else{
            mon_flash('danger','ไม่สามารถปิดโครงการได้');
        }
        $stmt->close();
        mon_redirect($projectId);
    }
}

/* flash */
$message='';
$messageType='success';
if(isset($_SESSION['qa_monitoring_flash'])&&is_array($_SESSION['qa_monitoring_flash'])){
    $messageType=isset($_SESSION['qa_monitoring_flash']['type'])?$_SESSION['qa_monitoring_flash']['type']:'success';
    $message=isset($_SESSION['qa_monitoring_flash']['text'])?$_SESSION['qa_monitoring_flash']['text']:'';
    unset($_SESSION['qa_monitoring_flash']);
}

/* Reload project */
$project=mon_load_project($projectId);

/* Summary */
$actualExpense=(float)qa_db_scalar(
    "SELECT COALESCE(SUM(amount),0) FROM qa_expenditures WHERE project_id=".(int)$projectId." AND payment_status='paid'",0
);
$balance=(float)$project['approved_budget']-$actualExpense;
$latestProgress=(float)qa_db_scalar(
    "SELECT COALESCE((SELECT progress_percent FROM qa_progress_reports WHERE project_id=".(int)$projectId." ORDER BY report_no DESC,progress_report_id DESC LIMIT 1),0)",0
);
if(in_array($project['status_code'],array('COMPLETED','CLOSED'),true))$latestProgress=100;

/* Activities */
$activities=array();
$stmt=$db->prepare("SELECT * FROM qa_project_activities WHERE project_id=? ORDER BY sort_order,activity_id");
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$activities[]=$r;$result->free();}$stmt->close();

/* Budget items */
$budgetItems=array();
$stmt=$db->prepare(
    "SELECT bi.*,a.activity_name,s.source_name,bp.pool_name,
            COALESCE((SELECT SUM(e.amount) FROM qa_expenditures e WHERE e.budget_item_id=bi.budget_item_id AND e.payment_status='paid'),0) AS spent
     FROM qa_project_budget_items bi
     LEFT JOIN qa_project_activities a ON a.activity_id=bi.activity_id
     LEFT JOIN qa_budget_pools bp ON bp.budget_pool_id=bi.budget_pool_id
     LEFT JOIN qa_budget_sources s ON s.source_id=bp.source_id
     WHERE bi.project_id=? ORDER BY bi.sort_order,bi.budget_item_id"
);
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$budgetItems[]=$r;$result->free();}$stmt->close();

/* Expenditures */
$expenditures=array();
$stmt=$db->prepare(
    "SELECT e.*,bi.item_name,a.activity_name
     FROM qa_expenditures e
     LEFT JOIN qa_project_budget_items bi ON bi.budget_item_id=e.budget_item_id
     LEFT JOIN qa_project_activities a ON a.activity_id=e.activity_id
     WHERE e.project_id=? ORDER BY e.expense_date DESC,e.expenditure_id DESC"
);
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$expenditures[]=$r;$result->free();}$stmt->close();

/* KPIs */
$kpis=array();
$stmt=$db->prepare(
    "SELECT k.*,
            (SELECT kr.actual_value FROM qa_project_kpi_results kr WHERE kr.kpi_id=k.kpi_id ORDER BY kr.kpi_result_id DESC LIMIT 1) AS latest_actual,
            (SELECT kr.achievement_status FROM qa_project_kpi_results kr WHERE kr.kpi_id=k.kpi_id ORDER BY kr.kpi_result_id DESC LIMIT 1) AS latest_status
     FROM qa_project_kpis k WHERE k.project_id=? ORDER BY k.sort_order,k.kpi_id"
);
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$kpis[]=$r;$result->free();}$stmt->close();

/* Progress reports */
$progressReports=array();
$stmt=$db->prepare(
    "SELECT pr.*,CONCAT(COALESCE(u.prefix,''),COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS reporter_name
     FROM qa_progress_reports pr
     LEFT JOIN qa_users u ON u.user_id=pr.reported_by
     WHERE pr.project_id=? ORDER BY pr.report_no DESC,pr.progress_report_id DESC"
);
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$progressReports[]=$r;$result->free();}$stmt->close();

/* Indicators linked to project */
$indicators=array();
$stmt=$db->prepare(
    "SELECT i.indicator_id,i.indicator_code,i.indicator_name
     FROM qa_project_indicator_links l
     INNER JOIN qa_indicators i ON i.indicator_id=l.indicator_id
     WHERE l.project_id=? ORDER BY i.indicator_code"
);
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$indicators[]=$r;$result->free();}$stmt->close();

/* Evidences */
$evidences=array();
$stmt=$db->prepare(
    "SELECT ev.*,
            GROUP_CONCAT(i.indicator_code ORDER BY i.indicator_code SEPARATOR ', ') AS indicator_codes
     FROM qa_evidences ev
     LEFT JOIN qa_evidence_indicator_links l ON l.evidence_id=ev.evidence_id
     LEFT JOIN qa_indicators i ON i.indicator_id=l.indicator_id
     WHERE ev.project_id=?
     GROUP BY ev.evidence_id
     ORDER BY ev.evidence_date DESC,ev.evidence_id DESC"
);
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$evidences[]=$r;$result->free();}$stmt->close();

/* Final result */
$finalResult=null;
$stmt=$db->prepare("SELECT * FROM qa_project_results WHERE project_id=? LIMIT 1");
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();
$finalResult=$result?$result->fetch_assoc():null;if($result)$result->free();$stmt->close();

require QA_ROOT . '/includes/header.php';
?>

<?php if($message!==''): ?>
<div class="alert <?= $messageType==='danger'?'alert-danger':'alert-success' ?>" style="margin-bottom:16px"><?= h($message) ?></div>
<?php endif; ?>

<?php $pendingProjectChange=qa_project_change_pending_request($projectId); ?>
<?php if($pendingProjectChange): ?>
<div class="notice" style="margin-bottom:16px">
    <strong>พักการดำเนินงานชั่วคราว:</strong>
    มี <?= h(qa_project_change_type_label($pendingProjectChange['request_type'])) ?>
    #<?= h($pendingProjectChange['request_id']) ?> รอพิจารณา
    <a href="<?= h(qa_url('projects/changes.php?id='.$pendingProjectChange['request_id'])) ?>">เปิดคำขอ</a>
</div>
<?php endif; ?>

<div class="action-bar">
    <a class="btn" href="<?= h(qa_url('monitoring/index.php')) ?>">← รายการติดตาม</a>
    <a class="btn" href="<?= h(qa_url('projects/view.php?id='.$projectId)) ?>">รายละเอียดโครงการเดิม</a>
    <?php if($project['status_code']==='APPROVED'&&$canManage): ?>
    <form method="post" style="display:inline" onsubmit="return confirm('ยืนยันเริ่มดำเนินโครงการ?');">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="start_project">
        <button class="btn btn-primary" type="submit">เริ่มดำเนินโครงการ</button>
    </form>
    <?php endif; ?>
    <?php if($project['status_code']==='COMPLETED'&&$canApproveResult): ?>
    <form method="post" style="display:inline" onsubmit="return confirm('ยืนยันปิดโครงการ?');">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="close_project">
        <button class="btn btn-primary" type="submit">ปิดโครงการ</button>
    </form>
    <?php endif; ?>
</div>

<div class="grid grid-5">
    <div class="card"><div class="metric-label">สถานะ</div><div style="margin-top:10px"><span class="badge badge-blue"><?= h($project['status_name']?$project['status_name']:$project['status_code']) ?></span></div></div>
    <div class="card"><div class="metric-label">ความก้าวหน้า</div><div class="metric-value"><?= h(number_format($latestProgress,0)) ?>%</div></div>
    <div class="card"><div class="metric-label">งบอนุมัติ</div><div class="metric-value"><?= h(qa_money($project['approved_budget'])) ?></div></div>
    <div class="card"><div class="metric-label">เบิกจ่ายจริง</div><div class="metric-value"><?= h(qa_money($actualExpense)) ?></div></div>
    <div class="card"><div class="metric-label">คงเหลือ</div><div class="metric-value <?= $balance<0?'text-danger':'' ?>"><?= h(qa_money($balance)) ?></div></div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>ภาพรวมการดำเนินงาน</h2><p><?= h($project['division_name']) ?> • ผู้รับผิดชอบ <?= h(trim($project['owner_name'])) ?></p></div>
    </div>
    <div class="progress-head"><span>ความก้าวหน้าล่าสุด</span><strong><?= h(number_format($latestProgress,2)) ?>%</strong></div>
    <div class="progress progress-large"><span style="width:<?= h(number_format(min(100,max(0,$latestProgress)),2,'.','')) ?>%"></span></div>
</div>

<div class="card" id="progress" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>1. รายงานความก้าวหน้าและ KPI</h2><p>บันทึกเป็นรายครั้ง พร้อมค่า KPI ณ วันที่ติดตาม</p></div>
        <span class="badge badge-blue"><?= h(count($progressReports)) ?> ครั้ง</span>
    </div>

    <?php if(!empty($progressReports)): ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ครั้ง</th><th>วันที่</th><th>ความก้าวหน้า</th><th>สรุปกิจกรรม</th><th class="text-right">ใช้จ่ายสะสม</th><th>ผู้รายงาน</th></tr></thead>
            <tbody>
            <?php foreach($progressReports as $r): ?>
            <tr>
                <td><?= h($r['report_no']) ?></td>
                <td><?= h($r['report_date']) ?></td>
                <td><?= h(number_format((float)$r['progress_percent'],2)) ?>%</td>
                <td><?= h($r['activity_summary']) ?></td>
                <td class="text-right"><?= h(qa_money($r['amount_spent_to_date'])) ?></td>
                <td><?= h(trim($r['reporter_name'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if($canManage&&$project['status_code']==='IN_PROGRESS'): ?>
    <form method="post" class="master-form">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="add_progress">
        <div class="form-grid">
            <div class="form-group"><label>วันที่รายงาน *</label><input type="date" name="report_date" required value="<?= h(date('Y-m-d')) ?>"></div>
            <div class="form-group"><label>ความก้าวหน้า (%) *</label><input type="number" name="progress_percent" min="0" max="100" step="0.01" required value="<?= h(number_format($latestProgress,2,'.','')) ?>"></div>
            <div class="form-group full"><label>สรุปกิจกรรม / ความก้าวหน้า *</label><textarea name="activity_summary" required></textarea></div>
            <div class="form-group full"><label>Output ที่เกิดขึ้น ณ รอบนี้</label><textarea name="output_summary"></textarea></div>
            <div class="form-group full"><label>ปัญหา / อุปสรรค</label><textarea name="problem_summary"></textarea></div>
            <div class="form-group full"><label>ขั้นตอนถัดไป</label><textarea name="next_step_summary"></textarea></div>
        </div>

        <?php if(!empty($kpis)): ?>
        <h3>ผล KPI ณ รอบรายงาน</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>KPI</th><th>เป้าหมาย</th><th style="width:160px">ผลจริง</th><th>คำอธิบายผล</th></tr></thead>
                <tbody>
                <?php foreach($kpis as $kpi): ?>
                <tr>
                    <td><?= h($kpi['kpi_code'].' — '.$kpi['kpi_name']) ?></td>
                    <td><?= h($kpi['target_operator'].' '.number_format((float)$kpi['target_value'],2).' '.$kpi['target_unit']) ?></td>
                    <td><input type="number" step="0.01" name="kpi_actual[<?= h($kpi['kpi_id']) ?>]" value="<?= $kpi['latest_actual']!==null?h(number_format((float)$kpi['latest_actual'],2,'.','')):'' ?>"></td>
                    <td><input name="kpi_result_text[<?= h($kpi['kpi_id']) ?>]" placeholder="หมายเหตุ / ผลเชิงคุณภาพ"></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit" style="margin-top:14px">บันทึกรายงานความก้าวหน้า</button>
    </form>
    <?php endif; ?>
</div>

<div class="card" id="finance" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>2. การเบิกจ่ายจริง</h2><p>งานการเงินบันทึกค่าใช้จ่ายจริงโดยผูกกับรายการงบที่ได้รับอนุมัติ</p></div>
        <span class="badge badge-blue"><?= h(count($expenditures)) ?> รายการ</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th>วันที่</th><th>เลขที่เอกสาร</th><th>รายการ</th><th>ผู้รับเงิน/ร้านค้า</th><th class="text-right">จำนวนเงิน</th></tr></thead>
            <tbody>
            <?php if(empty($expenditures)): ?>
                <tr><td colspan="5" class="empty-cell">ยังไม่มีการเบิกจ่ายจริง</td></tr>
            <?php else: ?>
                <?php foreach($expenditures as $e): ?>
                <tr>
                    <td><?= h($e['expense_date']) ?></td>
                    <td><?= $e['document_no']?h($e['document_no']):'-' ?></td>
                    <td><?= h($e['item_name']) ?><div class="subtle"><?= h($e['expense_detail']) ?></div></td>
                    <td><?= $e['payee_name']?h($e['payee_name']):'-' ?></td>
                    <td class="text-right money-cell"><?= h(qa_money($e['amount'])) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($canFinance&&in_array($project['status_code'],array('IN_PROGRESS','WAITING_REPORT'),true)): ?>
    <form method="post" class="master-form">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="add_expenditure">
        <div class="form-grid">
            <div class="form-group full">
                <label>รายการงบประมาณ *</label>
                <select name="budget_item_id" required>
                    <option value="">-- เลือกรายการ --</option>
                    <?php foreach($budgetItems as $item): ?>
                    <?php $remain=(float)$item['approved_amount']-(float)$item['spent']; ?>
                    <option value="<?= h($item['budget_item_id']) ?>" <?= $remain<=0?'disabled':'' ?>>
                        <?= h($item['item_name'].' — อนุมัติ '.qa_money($item['approved_amount']).' / ใช้แล้ว '.qa_money($item['spent']).' / เหลือ '.qa_money($remain)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>วันที่เบิก *</label><input type="date" name="expense_date" required value="<?= h(date('Y-m-d')) ?>"></div>
            <div class="form-group"><label>จำนวนเงิน *</label><input type="number" name="amount" min="0.01" step="0.01" required></div>
            <div class="form-group"><label>เลขที่เอกสาร</label><input name="document_no"></div>
            <div class="form-group"><label>ผู้รับเงิน / ร้านค้า</label><input name="payee_name"></div>
            <div class="form-group full"><label>รายละเอียดค่าใช้จ่าย *</label><textarea name="expense_detail" required></textarea></div>
        </div>
        <button class="btn btn-primary" type="submit">บันทึกการเบิกจ่าย</button>
    </form>
    <?php endif; ?>
</div>

<div class="card" id="evidence" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>3. หลักฐานเชิงประจักษ์</h2><p>แนบไฟล์หรือ URL และเชื่อมกับตัวชี้วัด สมศ. ของโครงการได้โดยตรง</p></div>
        <span class="badge badge-blue"><?= h(count($evidences)) ?> หลักฐาน</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th>รหัส</th><th>หลักฐาน</th><th>ประเภท</th><th>วันที่</th><th>ตัวชี้วัด</th><th>เปิด</th></tr></thead>
            <tbody>
            <?php if(empty($evidences)): ?>
                <tr><td colspan="6" class="empty-cell">ยังไม่มีหลักฐาน</td></tr>
            <?php else: ?>
                <?php foreach($evidences as $ev): ?>
                <tr>
                    <td><strong><?= h($ev['evidence_code']) ?></strong></td>
                    <td><?= h($ev['title']) ?></td>
                    <td><?= h($ev['evidence_type']) ?></td>
                    <td><?= $ev['evidence_date']?h($ev['evidence_date']):'-' ?></td>
                    <td><?= $ev['indicator_codes']?h($ev['indicator_codes']):'-' ?></td>
                    <td>
                        <?php if($ev['file_path']): ?><a class="btn btn-sm" target="_blank" href="<?= h(qa_url($ev['file_path'])) ?>">ไฟล์</a><?php endif; ?>
                        <?php if($ev['external_url']): ?><a class="btn btn-sm" target="_blank" rel="noopener" href="<?= h($ev['external_url']) ?>">URL</a><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($canManage): ?>
    <form method="post" enctype="multipart/form-data" class="master-form">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="add_evidence">
        <div class="form-grid">
            <div class="form-group"><label>ประเภทหลักฐาน *</label><input name="evidence_type" required placeholder="เช่น รายงานผล / ภาพกิจกรรม / คำสั่ง"></div>
            <div class="form-group"><label>วันที่หลักฐาน</label><input type="date" name="evidence_date" value="<?= h(date('Y-m-d')) ?>"></div>
            <div class="form-group full"><label>ชื่อหลักฐาน *</label><input name="title" required></div>
            <div class="form-group"><label>กิจกรรม</label><select name="evidence_activity_id"><option value="0">-- ไม่ระบุ --</option><?php foreach($activities as $a): ?><option value="<?= h($a['activity_id']) ?>"><?= h($a['activity_name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>รอบรายงานความก้าวหน้า</label><select name="evidence_progress_report_id"><option value="0">-- ไม่ระบุ --</option><?php foreach($progressReports as $r): ?><option value="<?= h($r['progress_report_id']) ?>">ครั้งที่ <?= h($r['report_no']) ?> — <?= h($r['report_date']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>เลขที่เอกสาร</label><input name="evidence_document_no"></div>
            <div class="form-group"><label>ไฟล์ (ไม่เกิน 10 MB)</label><input type="file" name="evidence_file" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"></div>
            <div class="form-group full"><label>URL ภายนอก</label><input type="url" name="external_url" placeholder="https://..."></div>
            <div class="form-group full"><label>รายละเอียด</label><textarea name="description"></textarea></div>
        </div>

        <?php if(!empty($indicators)): ?>
        <div class="alignment-box">
            <h3>เชื่อมกับตัวชี้วัด สมศ.</h3>
            <div class="indicator-grid">
                <?php foreach($indicators as $indicator): ?>
                <label class="check-row">
                    <input type="checkbox" name="evidence_indicator_ids[]" value="<?= h($indicator['indicator_id']) ?>">
                    <span><strong><?= h($indicator['indicator_code']) ?></strong> <?= h($indicator['indicator_name']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <button class="btn btn-primary" type="submit" style="margin-top:14px">เพิ่มหลักฐาน</button>
    </form>
    <?php endif; ?>
</div>

<div class="card" id="final" style="margin-top:16px">
    <div class="section-heading">
        <div><h2>4. สรุปผลโครงการ</h2><p>สรุป Output, Outcome, ความสำเร็จ ปัญหา และบทเรียน เพื่อใช้เป็นข้อมูล SAR/สมศ.</p></div>
        <?php if($finalResult&&$finalResult['approved_at']): ?><span class="badge badge-green">รับรองแล้ว</span><?php endif; ?>
    </div>

    <?php if($canManage&&in_array($project['status_code'],array('IN_PROGRESS','WAITING_REPORT'),true)): ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <div class="form-grid">
            <div class="form-group"><label>วันที่รายงาน *</label><input type="date" name="final_report_date" required value="<?= h($finalResult?$finalResult['report_date']:date('Y-m-d')) ?>"></div>
            <div class="form-group">
                <label>ผลโดยรวม</label>
                <?php $overall=$finalResult?$finalResult['overall_status']:'partial'; ?>
                <select name="overall_status">
                    <option value="achieved" <?= $overall==='achieved'?'selected':'' ?>>บรรลุเป้าหมาย</option>
                    <option value="partial" <?= $overall==='partial'?'selected':'' ?>>บรรลุบางส่วน</option>
                    <option value="not_achieved" <?= $overall==='not_achieved'?'selected':'' ?>>ไม่บรรลุเป้าหมาย</option>
                </select>
            </div>
            <div class="form-group full"><label>Output *</label><textarea name="final_output" required><?= h($finalResult?$finalResult['output_summary']:'') ?></textarea></div>
            <div class="form-group full"><label>Outcome *</label><textarea name="final_outcome" required><?= h($finalResult?$finalResult['outcome_summary']:'') ?></textarea></div>
            <div class="form-group full"><label>สรุปความสำเร็จ</label><textarea name="success_summary"><?= h($finalResult?$finalResult['success_summary']:'') ?></textarea></div>
            <div class="form-group full"><label>ปัญหา / อุปสรรค</label><textarea name="final_problem"><?= h($finalResult?$finalResult['problem_summary']:'') ?></textarea></div>
            <div class="form-group full"><label>ข้อเสนอแนะ</label><textarea name="recommendation_summary"><?= h($finalResult?$finalResult['recommendation_summary']:'') ?></textarea></div>
            <div class="form-group full"><label>บทเรียนที่ได้รับ</label><textarea name="lesson_learned"><?= h($finalResult?$finalResult['lesson_learned']:'') ?></textarea></div>
        </div>
        <div class="action-bar" style="margin-bottom:0">
            <button class="btn" type="submit" name="action" value="save_final">บันทึกร่างสรุปผล</button>
            <button class="btn btn-primary" type="submit" name="action" value="submit_final" onclick="return confirm('ยืนยันส่งรายงานผลเพื่อตรวจรับ?');">ส่งรายงานผล</button>
        </div>
    </form>
    <?php elseif($finalResult): ?>
        <div class="grid grid-2">
            <div><h3>Output</h3><p class="project-long-text"><?= nl2br(h($finalResult['output_summary'])) ?></p></div>
            <div><h3>Outcome</h3><p class="project-long-text"><?= nl2br(h($finalResult['outcome_summary'])) ?></p></div>
        </div>
        <?php if($finalResult['success_summary']): ?><h3>สรุปความสำเร็จ</h3><p class="project-long-text"><?= nl2br(h($finalResult['success_summary'])) ?></p><?php endif; ?>
    <?php else: ?>
        <div class="empty-cell">ยังไม่มีรายงานสรุปผลโครงการ</div>
    <?php endif; ?>

    <?php if($canApproveResult&&$project['status_code']==='WAITING_REPORT'&&$finalResult): ?>
    <form method="post" class="master-form" onsubmit="return confirm('ยืนยันรับรองผลการดำเนินโครงการ?');">
        <input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">
        <input type="hidden" name="action" value="approve_final">
        <button class="btn btn-primary" type="submit">ผู้อำนวยการรับรองผล / เสร็จสิ้นโครงการ</button>
    </form>
    <?php endif; ?>
</div>

<?php require QA_ROOT . '/includes/footer.php'; ?>
