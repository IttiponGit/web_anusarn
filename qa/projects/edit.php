<?php
$pageTitle = 'แก้ไขโครงการ';
$activeMenu = 'projects';

require_once __DIR__ . '/../includes/bootstrap.php';
qa_require_login();

$db = qa_db();
$user = qa_current_user();
$userId = $user ? (int)$user['user_id'] : 0;

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($projectId <= 0) {
    http_response_code(400);
    die('ไม่พบรหัสโครงการ');
}

function edit_clean($value)
{
    return trim((string)$value);
}

function edit_amount($value)
{
    $value = str_replace(',', '', trim((string)$value));
    if ($value === '') return 0.0;
    return is_numeric($value) ? (float)$value : null;
}

function edit_date($value)
{
    $value = trim((string)$value);
    if ($value === '') return '';
    $p = explode('-', $value);
    if (count($p) !== 3) return false;
    return checkdate((int)$p[1], (int)$p[2], (int)$p[0]) ? $value : false;
}

function edit_ids($value)
{
    $out = array();
    if (!is_array($value)) return $out;
    foreach ($value as $id) {
        $id = (int)$id;
        if ($id > 0) $out[$id] = $id;
    }
    return array_values($out);
}

function edit_load_project($id)
{
    $db = qa_db();
    $stmt = $db->prepare(
        "SELECT p.*, d.division_name, pl.plan_code, pl.plan_name
         FROM qa_projects p
         INNER JOIN qa_divisions d ON d.division_id=p.division_id
         LEFT JOIN qa_plans pl ON pl.plan_id=p.plan_id
         WHERE p.project_id=? LIMIT 1"
    );
    $stmt->bind_param('i',$id);
    $stmt->execute();
    $result=$stmt->get_result();
    $row=$result ? $result->fetch_assoc() : null;
    if ($result) $result->free();
    $stmt->close();
    return $row;
}

$project = edit_load_project($projectId);
if (!$project) {
    http_response_code(404);
    die('ไม่พบโครงการ');
}

if (!qa_project_can_edit_row($project)) {
    http_response_code(403);
    die('โครงการนี้แก้ไขไม่ได้ หรือบัญชีของคุณไม่ใช่เจ้าของโครงการ');
}

$pageHeading = 'แก้ไข ' . $project['project_code'];
$pageDescription = $project['status_code'] === 'REVISION'
    ? 'โครงการถูกส่งกลับแก้ไข ปรับข้อมูลตามข้อเสนอแนะแล้วส่งเสนอใหม่'
    : 'แก้ไขร่างโครงการก่อนส่งเข้าสู่ Workflow ตรวจสอบ';
$breadcrumbs = array(
    array('label'=>'โครงการทั้งหมด','url'=>qa_url('projects/index.php')),
    array('label'=>$project['project_code'],'url'=>qa_url('projects/view.php?id=' . $projectId)),
    array('label'=>'แก้ไข')
);

$planId = (int)$project['plan_id'];
$divisionId = (int)$project['division_id'];
$yearId = (int)$project['year_id'];

/* Master Data */
$missions = array();
$strategies = array();
$focusAreas = array();
$targets = array();
$indicators = array();
$divisionPools = array();

$stmt=$db->prepare("SELECT mission_id, mission_code, mission_text FROM qa_missions WHERE plan_id=? AND is_active=1 ORDER BY sort_order");
$stmt->bind_param('i',$planId); $stmt->execute(); $result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$missions[]=$r;$result->free();}$stmt->close();

$stmt=$db->prepare("SELECT strategy_id, strategy_code, strategy_name FROM qa_strategies WHERE plan_id=? AND is_active=1 ORDER BY sort_order");
$stmt->bind_param('i',$planId); $stmt->execute(); $result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$strategies[]=$r;$result->free();}$stmt->close();

$stmt=$db->prepare("SELECT focus_id, focus_code, focus_name FROM qa_focus_areas WHERE plan_id=? AND is_active=1 ORDER BY sort_order");
$stmt->bind_param('i',$planId); $stmt->execute(); $result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$focusAreas[]=$r;$result->free();}$stmt->close();

$stmt=$db->prepare("SELECT target_id, target_code, target_name FROM qa_plan_targets WHERE plan_id=? AND is_active=1 ORDER BY sort_order");
$stmt->bind_param('i',$planId); $stmt->execute(); $result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$targets[]=$r;$result->free();}$stmt->close();

$result=$db->query(
    "SELECT i.indicator_id,i.indicator_code,i.indicator_name,s.standard_code
     FROM qa_indicators i
     INNER JOIN qa_standards s ON s.standard_id=i.standard_id
     INNER JOIN qa_quality_frameworks f ON f.framework_id=s.framework_id
     WHERE f.framework_code='ONESQA' AND i.is_active=1
     ORDER BY CAST(s.standard_code AS UNSIGNED),i.sort_order"
);
if($result){while($r=$result->fetch_assoc())$indicators[]=$r;$result->free();}

/* pools with capacity excluding current project */
$stmt=$db->prepare(
    "SELECT DISTINCT p.budget_pool_id,p.pool_name,s.source_name
     FROM qa_budget_pools p
     INNER JOIN qa_budget_sources s ON s.source_id=p.source_id
     INNER JOIN vw_qa_budget_allocation_summary v
       ON v.budget_pool_id=p.budget_pool_id
      AND v.year_id=?
      AND v.allocation_type='division'
      AND v.division_id=?
     WHERE p.year_id=? AND v.current_allocation>0
     ORDER BY p.budget_pool_id"
);
$stmt->bind_param('iii',$yearId,$divisionId,$yearId);
$stmt->execute();$result=$stmt->get_result();
if($result){
    while($r=$result->fetch_assoc()){
        $cap=qa_project_pool_capacity((int)$r['budget_pool_id'],$divisionId,$yearId,$projectId);
        $r['allocation']=$cap['allocation'];
        $r['available']=$cap['available'];
        $divisionPools[]=$r;
    }
    $result->free();
}
$stmt->close();

/* Existing data */
$objectives=array();
$stmt=$db->prepare("SELECT objective_text FROM qa_project_objectives WHERE project_id=? ORDER BY sort_order,objective_id");
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$objectives[]=$r['objective_text'];$result->free();}$stmt->close();

$beneficiary=null;
$stmt=$db->prepare("SELECT * FROM qa_project_beneficiaries WHERE project_id=? ORDER BY beneficiary_id LIMIT 1");
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();
$beneficiary=$result?$result->fetch_assoc():null;if($result)$result->free();$stmt->close();

$selectedMissionIds=array();
$stmt=$db->prepare("SELECT mission_id FROM qa_project_mission_links WHERE project_id=?");
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$selectedMissionIds[]=(int)$r['mission_id'];$result->free();}$stmt->close();

$selectedStrategyIds=array();
$stmt=$db->prepare("SELECT strategy_id FROM qa_project_strategy_links WHERE project_id=?");
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$selectedStrategyIds[]=(int)$r['strategy_id'];$result->free();}$stmt->close();

$selectedFocusIds=array();
$stmt=$db->prepare("SELECT focus_id FROM qa_project_focus_links WHERE project_id=?");
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$selectedFocusIds[]=(int)$r['focus_id'];$result->free();}$stmt->close();

$selectedTargetIds=array();
$stmt=$db->prepare("SELECT target_id FROM qa_project_target_links WHERE project_id=?");
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$selectedTargetIds[]=(int)$r['target_id'];$result->free();}$stmt->close();

$selectedIndicatorIds=array();
$stmt=$db->prepare("SELECT indicator_id FROM qa_project_indicator_links WHERE project_id=?");
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$selectedIndicatorIds[]=(int)$r['indicator_id'];$result->free();}$stmt->close();

$budgetRows=array();
$stmt=$db->prepare(
    "SELECT bi.*,a.activity_name
     FROM qa_project_budget_items bi
     LEFT JOIN qa_project_activities a ON a.activity_id=bi.activity_id
     WHERE bi.project_id=? ORDER BY bi.sort_order,bi.budget_item_id"
);
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$budgetRows[]=$r;$result->free();}$stmt->close();

$kpiRows=array();
$stmt=$db->prepare("SELECT * FROM qa_project_kpis WHERE project_id=? ORDER BY sort_order,kpi_id");
$stmt->bind_param('i',$projectId);$stmt->execute();$result=$stmt->get_result();
if($result){while($r=$result->fetch_assoc())$kpiRows[]=$r;$result->free();}$stmt->close();

$formErrors=array();

if($_SERVER['REQUEST_METHOD']==='POST'){
    $token=isset($_POST['csrf_token'])?$_POST['csrf_token']:'';
    if(!qa_verify_csrf($token)){
        http_response_code(400);
        die('CSRF token ไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }

    $action=isset($_POST['action'])?$_POST['action']:'save';
    $isResubmit=$action==='resubmit';

    $projectType=edit_clean(isset($_POST['project_type'])?$_POST['project_type']:'project');
    $projectName=edit_clean(isset($_POST['project_name'])?$_POST['project_name']:'');
    $locationText=edit_clean(isset($_POST['location_text'])?$_POST['location_text']:'');
    $startDate=edit_date(isset($_POST['start_date'])?$_POST['start_date']:'');
    $endDate=edit_date(isset($_POST['end_date'])?$_POST['end_date']:'');
    $principleReason=edit_clean(isset($_POST['principle_reason'])?$_POST['principle_reason']:'');
    $needProblem=edit_clean(isset($_POST['need_problem'])?$_POST['need_problem']:'');
    $expectedOutput=edit_clean(isset($_POST['expected_output'])?$_POST['expected_output']:'');
    $expectedOutcome=edit_clean(isset($_POST['expected_outcome'])?$_POST['expected_outcome']:'');

    $beneficiaryType=edit_clean(isset($_POST['beneficiary_type'])?$_POST['beneficiary_type']:'');
    $beneficiaryDetail=edit_clean(isset($_POST['beneficiary_detail'])?$_POST['beneficiary_detail']:'');
    $beneficiaryCount=isset($_POST['beneficiary_count'])?(int)$_POST['beneficiary_count']:0;
    $beneficiaryUnit=edit_clean(isset($_POST['beneficiary_unit'])?$_POST['beneficiary_unit']:'คน');

    $objectiveText=edit_clean(isset($_POST['objectives_text'])?$_POST['objectives_text']:'');
    $objectiveLines=preg_split('/\r\n|\r|\n/',$objectiveText);
    $newObjectives=array();
    foreach($objectiveLines as $line){
        $line=trim($line);
        $line=preg_replace('/^\s*\d+[\.\)]\s*/u','',$line);
        if($line!=='')$newObjectives[]=$line;
    }

    $missionIds=edit_ids(isset($_POST['mission_ids'])?$_POST['mission_ids']:array());
    $strategyIds=edit_ids(isset($_POST['strategy_ids'])?$_POST['strategy_ids']:array());
    $focusIds=edit_ids(isset($_POST['focus_ids'])?$_POST['focus_ids']:array());
    $targetIds=edit_ids(isset($_POST['target_ids'])?$_POST['target_ids']:array());
    $indicatorIds=edit_ids(isset($_POST['indicator_ids'])?$_POST['indicator_ids']:array());

    if($projectName==='')$formErrors[]='กรุณาระบุชื่อโครงการ';
    if($startDate===false||$endDate===false)$formErrors[]='วันที่ไม่ถูกต้อง';
    if($startDate&&$endDate&&$endDate<$startDate)$formErrors[]='วันที่สิ้นสุดต้องไม่น้อยกว่าวันที่เริ่ม';

    if($isResubmit){
        if($principleReason==='')$formErrors[]='กรุณาระบุหลักการและเหตุผล';
        if(empty($newObjectives))$formErrors[]='กรุณาระบุวัตถุประสงค์อย่างน้อย 1 ข้อ';
        if(empty($indicatorIds))$formErrors[]='กรุณาเลือกตัวชี้วัด สมศ. อย่างน้อย 1 ตัว';
        if($expectedOutput==='')$formErrors[]='กรุณาระบุ Output';
        if($expectedOutcome==='')$formErrors[]='กรุณาระบุ Outcome';
    }

    /* Budget */
    $activityNames=isset($_POST['activity_name'])&&is_array($_POST['activity_name'])?$_POST['activity_name']:array();
    $expenseCategories=isset($_POST['expense_category'])&&is_array($_POST['expense_category'])?$_POST['expense_category']:array();
    $itemNames=isset($_POST['item_name'])&&is_array($_POST['item_name'])?$_POST['item_name']:array();
    $quantities=isset($_POST['quantity'])&&is_array($_POST['quantity'])?$_POST['quantity']:array();
    $unitNames=isset($_POST['unit_name'])&&is_array($_POST['unit_name'])?$_POST['unit_name']:array();
    $unitPrices=isset($_POST['unit_price'])&&is_array($_POST['unit_price'])?$_POST['unit_price']:array();
    $poolIds=isset($_POST['budget_pool_id'])&&is_array($_POST['budget_pool_id'])?$_POST['budget_pool_id']:array();

    $newBudgetRows=array();
    $requestedBudget=0.0;
    $requestedByPool=array();
    $rowCount=max(count($activityNames),count($itemNames),count($unitPrices),count($poolIds));

    for($i=0;$i<$rowCount;$i++){
        $activity=edit_clean(isset($activityNames[$i])?$activityNames[$i]:'');
        $category=edit_clean(isset($expenseCategories[$i])?$expenseCategories[$i]:'');
        $item=edit_clean(isset($itemNames[$i])?$itemNames[$i]:'');
        $qty=edit_amount(isset($quantities[$i])?$quantities[$i]:'1');
        $unit=edit_clean(isset($unitNames[$i])?$unitNames[$i]:'');
        $price=edit_amount(isset($unitPrices[$i])?$unitPrices[$i]:'0');
        $poolId=isset($poolIds[$i])?(int)$poolIds[$i]:0;

        if($activity===''&&$item===''&&(!$price||$price==0))continue;
        if($activity==='')$formErrors[]='กรุณาระบุชื่อกิจกรรม';
        if($item==='')$formErrors[]='กรุณาระบุรายการค่าใช้จ่าย';
        if($qty===null||$qty<=0)$formErrors[]='จำนวนต้องมากกว่า 0';
        if($price===null||$price<0)$formErrors[]='ราคาต่อหน่วยไม่ถูกต้อง';
        if($poolId<=0)$formErrors[]='กรุณาเลือกแหล่งเงิน';

        if($qty===null||$price===null)continue;
        $amount=(float)$qty*(float)$price;
        if($amount<=0)continue;

        $newBudgetRows[]=array(
            'activity_name'=>$activity,'expense_category'=>$category,'item_name'=>$item,
            'quantity'=>$qty,'unit_name'=>$unit,'unit_price'=>$price,
            'budget_pool_id'=>$poolId,'amount'=>$amount
        );
        $requestedBudget+=$amount;
        if(!isset($requestedByPool[$poolId]))$requestedByPool[$poolId]=0.0;
        $requestedByPool[$poolId]+=$amount;
    }

    if($isResubmit&&$requestedBudget<=0)$formErrors[]='กรุณาระบุงบประมาณอย่างน้อย 1 รายการ';

    foreach($requestedByPool as $poolId=>$amount){
        $cap=qa_project_pool_capacity((int)$poolId,$divisionId,$yearId,$projectId);
        if($cap['allocation']<=0){
            $formErrors[]='พบแหล่งเงินที่ไม่ได้จัดสรรให้ฝ่ายนี้';
        }elseif($isResubmit&&$amount>$cap['available']+0.005){
            $formErrors[]='ยอดขอจากก้อนงบหนึ่งเกินวงเงินพร้อมใช้ '.qa_money($cap['available']).' บาท';
        }
    }

    /* KPI */
    $kpiNames=isset($_POST['kpi_name'])&&is_array($_POST['kpi_name'])?$_POST['kpi_name']:array();
    $kpiTypes=isset($_POST['kpi_type'])&&is_array($_POST['kpi_type'])?$_POST['kpi_type']:array();
    $kpiTargets=isset($_POST['kpi_target'])&&is_array($_POST['kpi_target'])?$_POST['kpi_target']:array();
    $kpiUnits=isset($_POST['kpi_unit'])&&is_array($_POST['kpi_unit'])?$_POST['kpi_unit']:array();
    $kpiMethods=isset($_POST['kpi_method'])&&is_array($_POST['kpi_method'])?$_POST['kpi_method']:array();

    $newKpis=array();
    $kCount=max(count($kpiNames),count($kpiTargets));
    for($i=0;$i<$kCount;$i++){
        $name=edit_clean(isset($kpiNames[$i])?$kpiNames[$i]:'');
        $type=edit_clean(isset($kpiTypes[$i])?$kpiTypes[$i]:'quantitative');
        $target=edit_amount(isset($kpiTargets[$i])?$kpiTargets[$i]:'');
        $unit=edit_clean(isset($kpiUnits[$i])?$kpiUnits[$i]:'');
        $method=edit_clean(isset($kpiMethods[$i])?$kpiMethods[$i]:'');
        if($name===''&&($target===0.0||$target===null))continue;
        if($name===''){$formErrors[]='กรุณาระบุข้อความ KPI';continue;}
        if($target===null){$formErrors[]='ค่าเป้าหมาย KPI ต้องเป็นตัวเลข';continue;}
        if(!in_array($type,array('quantitative','qualitative'),true))$type='quantitative';
        $newKpis[]=array('name'=>$name,'type'=>$type,'target'=>$target,'unit'=>$unit,'method'=>$method);
    }
    if($isResubmit&&empty($newKpis))$formErrors[]='กรุณาระบุ KPI อย่างน้อย 1 ข้อ';

    if(empty($formErrors)){
        $oldSnapshot=$project;
        $db->autocommit(false);
        try{
            $newStatus=$project['status_code'];
            if($isResubmit)$newStatus='SUBMITTED';

            $startDb=$startDate?$startDate:'';
            $endDb=$endDate?$endDate:'';
            $submittedAt=$isResubmit?date('Y-m-d H:i:s'):($project['submitted_at']?$project['submitted_at']:'');

            $stmt=$db->prepare(
                "UPDATE qa_projects SET
                    project_type=?,project_name=?,principle_reason=NULLIF(?,''),need_problem=NULLIF(?,''),
                    expected_output=NULLIF(?,''),expected_outcome=NULLIF(?,''),location_text=NULLIF(?, ''),
                    start_date=NULLIF(?,''),end_date=NULLIF(?,''),requested_budget=?,
                    approved_budget=0,status_code=?,submitted_at=NULLIF(?,''),approved_at=NULL
                 WHERE project_id=?"
            );
            $stmt->bind_param(
                'sssssssssdssi',
                $projectType,$projectName,$principleReason,$needProblem,$expectedOutput,$expectedOutcome,
                $locationText,$startDb,$endDb,$requestedBudget,$newStatus,$submittedAt,$projectId
            );
            if(!$stmt->execute())throw new Exception('ไม่สามารถอัปเดตข้อมูลหลักได้');
            $stmt->close();

            /* clear editable child data */
            $tables=array(
                'qa_project_objectives','qa_project_beneficiaries','qa_project_mission_links',
                'qa_project_strategy_links','qa_project_focus_links','qa_project_target_links',
                'qa_project_indicator_links','qa_project_kpis'
            );
            foreach($tables as $table){
                if(!$db->query("DELETE FROM ".$table." WHERE project_id=".(int)$projectId))
                    throw new Exception('ไม่สามารถอัปเดตข้อมูลประกอบได้');
            }
            if(!$db->query("DELETE FROM qa_project_budget_items WHERE project_id=".(int)$projectId))
                throw new Exception('ไม่สามารถล้างรายการงบประมาณเดิมได้');
            if(!$db->query("DELETE FROM qa_project_activities WHERE project_id=".(int)$projectId))
                throw new Exception('ไม่สามารถล้างกิจกรรมเดิมได้');

            foreach($newObjectives as $idx=>$objective){
                $sort=$idx+1;
                $stmt=$db->prepare("INSERT INTO qa_project_objectives(project_id,objective_text,sort_order) VALUES(?,?,?)");
                $stmt->bind_param('isi',$projectId,$objective,$sort);
                if(!$stmt->execute())throw new Exception('ไม่สามารถบันทึกวัตถุประสงค์ได้');
                $stmt->close();
            }

            if($beneficiaryType!==''||$beneficiaryDetail!==''||$beneficiaryCount>0){
                $stmt=$db->prepare(
                    "INSERT INTO qa_project_beneficiaries(project_id,group_type,group_detail,target_count,unit_name)
                     VALUES(?,?,NULLIF(?,''),NULLIF(?,0),?)"
                );
                $stmt->bind_param('issis',$projectId,$beneficiaryType,$beneficiaryDetail,$beneficiaryCount,$beneficiaryUnit);
                if(!$stmt->execute())throw new Exception('ไม่สามารถบันทึกกลุ่มเป้าหมายได้');
                $stmt->close();
            }

            foreach($missionIds as $id){
                $stmt=$db->prepare("INSERT INTO qa_project_mission_links(project_id,mission_id) VALUES(?,?)");
                $stmt->bind_param('ii',$projectId,$id);if(!$stmt->execute())throw new Exception('mission link');$stmt->close();
            }
            foreach($strategyIds as $id){
                $stmt=$db->prepare("INSERT INTO qa_project_strategy_links(project_id,strategy_id) VALUES(?,?)");
                $stmt->bind_param('ii',$projectId,$id);if(!$stmt->execute())throw new Exception('strategy link');$stmt->close();
            }
            foreach($focusIds as $id){
                $stmt=$db->prepare("INSERT INTO qa_project_focus_links(project_id,focus_id) VALUES(?,?)");
                $stmt->bind_param('ii',$projectId,$id);if(!$stmt->execute())throw new Exception('focus link');$stmt->close();
            }
            foreach($targetIds as $id){
                $stmt=$db->prepare("INSERT INTO qa_project_target_links(project_id,target_id) VALUES(?,?)");
                $stmt->bind_param('ii',$projectId,$id);if(!$stmt->execute())throw new Exception('target link');$stmt->close();
            }
            foreach($indicatorIds as $idx=>$id){
                $primary=$idx===0?1:0;
                $stmt=$db->prepare("INSERT INTO qa_project_indicator_links(project_id,indicator_id,is_primary) VALUES(?,?,?)");
                $stmt->bind_param('iii',$projectId,$id,$primary);if(!$stmt->execute())throw new Exception('indicator link');$stmt->close();
            }

            $activityMap=array();$activitySort=0;
            foreach($newBudgetRows as $idx=>$row){
                $key=trim($row['activity_name']);
                if(!isset($activityMap[$key])){
                    $activitySort++;
                    $stmt=$db->prepare(
                        "INSERT INTO qa_project_activities
                        (project_id,activity_no,activity_name,start_date,end_date,responsible_user_id,status,sort_order)
                        VALUES(?,?,?,NULLIF(?,''),NULLIF(?,''),?,'planned',?)"
                    );
                    $stmt->bind_param('iisssii',$projectId,$activitySort,$key,$startDb,$endDb,$userId,$activitySort);
                    if(!$stmt->execute())throw new Exception('activity');
                    $activityMap[$key]=$stmt->insert_id;$stmt->close();
                }
                $activityId=(int)$activityMap[$key];
                $sort=$idx+1;
                $stmt=$db->prepare(
                    "INSERT INTO qa_project_budget_items
                    (project_id,activity_id,budget_pool_id,expense_category,item_name,quantity,unit_name,unit_price,requested_amount,approved_amount,sort_order)
                    VALUES(?,?,?,NULLIF(?,''),?,?,NULLIF(?,''),?,?,0,?)"
                );
                $stmt->bind_param(
                    'iiissdsddi',
                    $projectId,$activityId,$row['budget_pool_id'],$row['expense_category'],$row['item_name'],
                    $row['quantity'],$row['unit_name'],$row['unit_price'],$row['amount'],$sort
                );
                if(!$stmt->execute())throw new Exception('budget item');
                $stmt->close();
            }

            foreach($newKpis as $idx=>$kpi){
                $sort=$idx+1;$code='KPI-'.$sort;$operator='>=';
                $stmt=$db->prepare(
                    "INSERT INTO qa_project_kpis
                    (project_id,kpi_code,kpi_name,kpi_type,target_value,target_operator,target_unit,measurement_method,sort_order)
                    VALUES(?,?,?,?,?,?,NULLIF(?,''),NULLIF(?,''),?)"
                );
                $stmt->bind_param('isssdsssi',$projectId,$code,$kpi['name'],$kpi['type'],$kpi['target'],$operator,$kpi['unit'],$kpi['method'],$sort);
                if(!$stmt->execute())throw new Exception('kpi');
                $stmt->close();
            }

            if($isResubmit){
                if(!qa_project_reset_approvals($projectId))throw new Exception('ไม่สามารถรีเซ็ต Workflow ได้');
                if(!qa_project_add_history(
                    $projectId,$project['status_code'],'SUBMITTED',$userId,
                    $project['status_code']==='REVISION'?'แก้ไขและส่งโครงการใหม่':'ส่งร่างโครงการเข้าสู่กระบวนการตรวจสอบ'
                ))throw new Exception('ไม่สามารถบันทึกประวัติได้');
                qa_project_add_comment(
                    $projectId,'owner','resubmit',
                    $project['status_code']==='REVISION'?'เจ้าของโครงการแก้ไขตามข้อเสนอแนะและส่งใหม่':'เจ้าของโครงการส่งโครงการ',
                    $userId
                );
            }else{
                qa_project_add_comment($projectId,'owner','edit','บันทึกการแก้ไขโครงการ', $userId);
            }

            $db->commit();$db->autocommit(true);

            qa_project_audit_action(
                $isResubmit?'resubmit_project':'update_project',
                'qa_projects',$projectId,$oldSnapshot,
                array('status_code'=>$newStatus,'requested_budget'=>$requestedBudget,'project_name'=>$projectName)
            );

            $_SESSION['qa_project_flash']=array(
                'type'=>'success',
                'text'=>$isResubmit?'แก้ไขและส่งโครงการใหม่เรียบร้อยแล้ว':'บันทึกการแก้ไขเรียบร้อยแล้ว'
            );
            header('Location: '.qa_url('projects/view.php').'?id='.$projectId);
            exit;

        }catch(Exception $e){
            $db->rollback();$db->autocommit(true);
            $formErrors[]='ไม่สามารถบันทึกการแก้ไขได้: '.$e->getMessage();
        }
    }
}

/* rehydrate from POST after validation errors */
if($_SERVER['REQUEST_METHOD']==='POST'&&!empty($formErrors)){
    $project['project_type']=isset($_POST['project_type'])?$_POST['project_type']:$project['project_type'];
    $project['project_name']=isset($_POST['project_name'])?$_POST['project_name']:$project['project_name'];
    $project['location_text']=isset($_POST['location_text'])?$_POST['location_text']:$project['location_text'];
    $project['start_date']=isset($_POST['start_date'])?$_POST['start_date']:$project['start_date'];
    $project['end_date']=isset($_POST['end_date'])?$_POST['end_date']:$project['end_date'];
    $project['principle_reason']=isset($_POST['principle_reason'])?$_POST['principle_reason']:$project['principle_reason'];
    $project['need_problem']=isset($_POST['need_problem'])?$_POST['need_problem']:$project['need_problem'];
    $project['expected_output']=isset($_POST['expected_output'])?$_POST['expected_output']:$project['expected_output'];
    $project['expected_outcome']=isset($_POST['expected_outcome'])?$_POST['expected_outcome']:$project['expected_outcome'];
    $objectives=preg_split('/\r\n|\r|\n/',isset($_POST['objectives_text'])?$_POST['objectives_text']:'');
    $selectedMissionIds=edit_ids(isset($_POST['mission_ids'])?$_POST['mission_ids']:array());
    $selectedStrategyIds=edit_ids(isset($_POST['strategy_ids'])?$_POST['strategy_ids']:array());
    $selectedFocusIds=edit_ids(isset($_POST['focus_ids'])?$_POST['focus_ids']:array());
    $selectedTargetIds=edit_ids(isset($_POST['target_ids'])?$_POST['target_ids']:array());
    $selectedIndicatorIds=edit_ids(isset($_POST['indicator_ids'])?$_POST['indicator_ids']:array());

    $beneficiary=array(
        'group_type'=>isset($_POST['beneficiary_type'])?$_POST['beneficiary_type']:'',
        'group_detail'=>isset($_POST['beneficiary_detail'])?$_POST['beneficiary_detail']:'',
        'target_count'=>isset($_POST['beneficiary_count'])?$_POST['beneficiary_count']:'',
        'unit_name'=>isset($_POST['beneficiary_unit'])?$_POST['beneficiary_unit']:'คน'
    );

    $budgetRows=array();
    if(isset($_POST['activity_name'])&&is_array($_POST['activity_name'])){
        for($i=0;$i<count($_POST['activity_name']);$i++){
            $budgetRows[]=array(
                'activity_name'=>isset($_POST['activity_name'][$i])?$_POST['activity_name'][$i]:'',
                'expense_category'=>isset($_POST['expense_category'][$i])?$_POST['expense_category'][$i]:'',
                'item_name'=>isset($_POST['item_name'][$i])?$_POST['item_name'][$i]:'',
                'quantity'=>isset($_POST['quantity'][$i])?$_POST['quantity'][$i]:'1',
                'unit_name'=>isset($_POST['unit_name'][$i])?$_POST['unit_name'][$i]:'',
                'unit_price'=>isset($_POST['unit_price'][$i])?$_POST['unit_price'][$i]:'0',
                'budget_pool_id'=>isset($_POST['budget_pool_id'][$i])?$_POST['budget_pool_id'][$i]:0
            );
        }
    }
    $kpiRows=array();
    if(isset($_POST['kpi_name'])&&is_array($_POST['kpi_name'])){
        for($i=0;$i<count($_POST['kpi_name']);$i++){
            $kpiRows[]=array(
                'kpi_name'=>isset($_POST['kpi_name'][$i])?$_POST['kpi_name'][$i]:'',
                'kpi_type'=>isset($_POST['kpi_type'][$i])?$_POST['kpi_type'][$i]:'quantitative',
                'target_value'=>isset($_POST['kpi_target'][$i])?$_POST['kpi_target'][$i]:'',
                'target_unit'=>isset($_POST['kpi_unit'][$i])?$_POST['kpi_unit'][$i]:'',
                'measurement_method'=>isset($_POST['kpi_method'][$i])?$_POST['kpi_method'][$i]:''
            );
        }
    }
}

if(empty($budgetRows))$budgetRows[]=array('activity_name'=>'','expense_category'=>'','item_name'=>'','quantity'=>1,'unit_name'=>'รายการ','unit_price'=>0,'budget_pool_id'=>0);
if(empty($kpiRows))$kpiRows[]=array('kpi_name'=>'','kpi_type'=>'quantitative','target_value'=>'','target_unit'=>'%','measurement_method'=>'');

require QA_ROOT . '/includes/header.php';
?>

<?php if(!empty($formErrors)): ?>
<div class="alert alert-danger" style="margin-bottom:16px">
    <strong>กรุณาตรวจสอบข้อมูล</strong>
    <ul style="margin:8px 0 0 18px">
        <?php foreach(array_unique($formErrors) as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if($project['status_code']==='REVISION'): ?>
<div class="alert alert-danger" style="margin-bottom:16px">
    โครงการถูกส่งกลับแก้ไข กรุณาตรวจความเห็นจากผู้ตรวจในหน้ารายละเอียด แล้วแก้ไขก่อนกด “ส่งใหม่”
</div>
<?php endif; ?>

<form method="post" id="editProjectForm">
<input type="hidden" name="csrf_token" value="<?= h(qa_csrf_token()) ?>">

<div class="card">
    <div class="section-heading">
        <div><h2>ข้อมูลทั่วไป</h2><p>รหัส <?= h($project['project_code']) ?> • <?= h($project['division_name']) ?> • <?= h($project['plan_code']) ?></p></div>
        <span class="badge badge-yellow"><?= h($project['status_code']) ?></span>
    </div>
    <div class="form-grid">
        <div class="form-group">
            <label>ประเภท</label>
            <select name="project_type">
                <option value="project" <?= $project['project_type']==='project'?'selected':'' ?>>โครงการ</option>
                <option value="work" <?= $project['project_type']==='work'?'selected':'' ?>>งาน</option>
                <option value="activity" <?= $project['project_type']==='activity'?'selected':'' ?>>กิจกรรม</option>
            </select>
        </div>
        <div class="form-group">
            <label>ฝ่าย / แผน</label>
            <input value="<?= h($project['division_name'].' / '.$project['plan_code']) ?>" readonly>
        </div>
        <div class="form-group full">
            <label>ชื่อโครงการ *</label>
            <input name="project_name" required value="<?= h($project['project_name']) ?>">
        </div>
        <div class="form-group">
            <label>สถานที่</label>
            <input name="location_text" value="<?= h($project['location_text']) ?>">
        </div>
        <div class="form-group">
            <label>วันที่เริ่ม</label>
            <input type="date" name="start_date" value="<?= h($project['start_date']) ?>">
        </div>
        <div class="form-group">
            <label>วันที่สิ้นสุด</label>
            <input type="date" name="end_date" value="<?= h($project['end_date']) ?>">
        </div>
    </div>

    <h3 style="margin-top:18px">กลุ่มเป้าหมาย</h3>
    <div class="form-grid">
        <div class="form-group"><label>ประเภท</label><input name="beneficiary_type" value="<?= $beneficiary?h($beneficiary['group_type']):'' ?>"></div>
        <div class="form-group"><label>รายละเอียด</label><input name="beneficiary_detail" value="<?= $beneficiary?h($beneficiary['group_detail']):'' ?>"></div>
        <div class="form-group"><label>จำนวน</label><input type="number" min="0" name="beneficiary_count" value="<?= $beneficiary?h($beneficiary['target_count']):'' ?>"></div>
        <div class="form-group"><label>หน่วย</label><input name="beneficiary_unit" value="<?= $beneficiary?h($beneficiary['unit_name']):'คน' ?>"></div>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <h2>หลักการและวัตถุประสงค์</h2>
    <div class="form-group"><label>หลักการและเหตุผล</label><textarea name="principle_reason"><?= h($project['principle_reason']) ?></textarea></div>
    <div class="form-group"><label>ปัญหา / ความต้องการจำเป็น</label><textarea name="need_problem"><?= h($project['need_problem']) ?></textarea></div>
    <div class="form-group"><label>วัตถุประสงค์</label><textarea name="objectives_text"><?= h(implode("\n",$objectives)) ?></textarea></div>
</div>

<div class="card" style="margin-top:16px">
    <h2>ความสอดคล้อง</h2>
    <div class="alignment-grid">
        <div class="alignment-box"><h3>พันธกิจ</h3>
            <?php foreach($missions as $r): ?><label class="check-row"><input type="checkbox" name="mission_ids[]" value="<?= h($r['mission_id']) ?>" <?= in_array((int)$r['mission_id'],$selectedMissionIds,true)?'checked':'' ?>><span><strong><?= h($r['mission_code']) ?></strong> <?= h($r['mission_text']) ?></span></label><?php endforeach; ?>
        </div>
        <div class="alignment-box"><h3>กลยุทธ์</h3>
            <?php foreach($strategies as $r): ?><label class="check-row"><input type="checkbox" name="strategy_ids[]" value="<?= h($r['strategy_id']) ?>" <?= in_array((int)$r['strategy_id'],$selectedStrategyIds,true)?'checked':'' ?>><span><strong><?= h($r['strategy_code']) ?></strong> <?= h($r['strategy_name']) ?></span></label><?php endforeach; ?>
        </div>
        <div class="alignment-box"><h3>จุดเน้น</h3>
            <?php foreach($focusAreas as $r): ?><label class="check-row"><input type="checkbox" name="focus_ids[]" value="<?= h($r['focus_id']) ?>" <?= in_array((int)$r['focus_id'],$selectedFocusIds,true)?'checked':'' ?>><span><strong><?= h($r['focus_code']) ?></strong> <?= h($r['focus_name']) ?></span></label><?php endforeach; ?>
        </div>
        <div class="alignment-box"><h3>เป้าหมายแผน</h3>
            <?php foreach($targets as $r): ?><label class="check-row"><input type="checkbox" name="target_ids[]" value="<?= h($r['target_id']) ?>" <?= in_array((int)$r['target_id'],$selectedTargetIds,true)?'checked':'' ?>><span><strong><?= h($r['target_code']) ?></strong> <?= h($r['target_name']) ?></span></label><?php endforeach; ?>
        </div>
    </div>
    <div class="alignment-box" style="margin-top:14px"><h3>ตัวชี้วัด สมศ.</h3><div class="indicator-grid">
        <?php foreach($indicators as $r): ?><label class="check-row"><input type="checkbox" name="indicator_ids[]" value="<?= h($r['indicator_id']) ?>" <?= in_array((int)$r['indicator_id'],$selectedIndicatorIds,true)?'checked':'' ?>><span><strong><?= h($r['indicator_code']) ?></strong> <?= h($r['indicator_name']) ?></span></label><?php endforeach; ?>
    </div></div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading"><div><h2>กิจกรรมและงบประมาณ</h2><p>วงเงินพร้อมใช้คำนวณโดยไม่นับโครงการนี้ซ้ำ</p></div><button class="btn btn-sm" type="button" id="addBudgetRow">＋ เพิ่มรายการ</button></div>
    <div class="pool-budget-list">
        <?php foreach($divisionPools as $pool): ?><div><span><?= h($pool['source_name'].' — '.$pool['pool_name']) ?></span><strong>จัดสรร <?= h(qa_money($pool['allocation'])) ?> / พร้อมใช้ <?= h(qa_money($pool['available'])) ?></strong></div><?php endforeach; ?>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>กิจกรรม</th><th>หมวด</th><th>รายการ</th><th>จำนวน</th><th>หน่วย</th><th>ราคาต่อหน่วย</th><th>แหล่งเงิน</th><th class="text-right">รวม</th><th></th></tr></thead>
        <tbody id="budgetRows">
        <?php foreach($budgetRows as $row): ?>
        <tr class="budget-row">
            <td><input name="activity_name[]" value="<?= h($row['activity_name']) ?>"></td>
            <td><input name="expense_category[]" value="<?= h($row['expense_category']) ?>"></td>
            <td><input name="item_name[]" value="<?= h($row['item_name']) ?>"></td>
            <td><input class="budget-qty" type="number" step="0.01" min="0.01" name="quantity[]" value="<?= h($row['quantity']) ?>"></td>
            <td><input name="unit_name[]" value="<?= h($row['unit_name']) ?>"></td>
            <td><input class="budget-price" type="number" step="0.01" min="0" name="unit_price[]" value="<?= h($row['unit_price']) ?>"></td>
            <td><select name="budget_pool_id[]"><option value="">-- เลือก --</option><?php foreach($divisionPools as $pool): ?><option value="<?= h($pool['budget_pool_id']) ?>" <?= (int)$row['budget_pool_id']===(int)$pool['budget_pool_id']?'selected':'' ?>><?= h($pool['source_name'].' / '.$pool['pool_name']) ?></option><?php endforeach; ?></select></td>
            <td class="text-right money-cell budget-line-total">0.00</td>
            <td><button class="btn btn-danger btn-sm remove-budget-row" type="button">ลบ</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="table-total-row"><td colspan="7"><strong>รวม</strong></td><td class="text-right"><strong id="budgetTotal">0.00</strong></td><td></td></tr></tfoot>
    </table></div>
</div>

<div class="card" style="margin-top:16px">
    <div class="section-heading"><div><h2>KPI / Output / Outcome</h2></div><button class="btn btn-sm" type="button" id="addKpiRow">＋ เพิ่ม KPI</button></div>
    <div class="table-wrap"><table>
        <thead><tr><th>KPI</th><th>ประเภท</th><th>เป้าหมาย</th><th>หน่วย</th><th>วิธีวัด</th><th></th></tr></thead>
        <tbody id="kpiRows">
        <?php foreach($kpiRows as $row): ?>
        <tr class="kpi-row">
            <td><input name="kpi_name[]" value="<?= h($row['kpi_name']) ?>"></td>
            <td><select name="kpi_type[]"><option value="quantitative" <?= $row['kpi_type']==='quantitative'?'selected':'' ?>>เชิงปริมาณ</option><option value="qualitative" <?= $row['kpi_type']==='qualitative'?'selected':'' ?>>เชิงคุณภาพ</option></select></td>
            <td><input type="number" step="0.01" name="kpi_target[]" value="<?= h($row['target_value']) ?>"></td>
            <td><input name="kpi_unit[]" value="<?= h($row['target_unit']) ?>"></td>
            <td><input name="kpi_method[]" value="<?= h($row['measurement_method']) ?>"></td>
            <td><button class="btn btn-danger btn-sm remove-kpi-row" type="button">ลบ</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <div class="form-group" style="margin-top:16px"><label>Output</label><textarea name="expected_output"><?= h($project['expected_output']) ?></textarea></div>
    <div class="form-group"><label>Outcome</label><textarea name="expected_outcome"><?= h($project['expected_outcome']) ?></textarea></div>
</div>

<div class="card" style="margin-top:16px">
    <div class="action-bar" style="margin:0">
        <button class="btn" type="submit" name="action" value="save">บันทึกการแก้ไข</button>
        <button class="btn btn-primary" type="submit" name="action" value="resubmit" onclick="return confirm('ยืนยันส่งโครงการเข้าสู่ Workflow ตรวจสอบอีกครั้ง?');">
            <?= $project['status_code']==='REVISION'?'ส่งใหม่':'ส่งเสนอ' ?>
        </button>
        <a class="btn" href="<?= h(qa_url('projects/view.php?id='.$projectId)) ?>">ยกเลิก</a>
    </div>
</div>
</form>

<template id="budgetRowTemplate"><tr class="budget-row">
<td><input name="activity_name[]"></td><td><input name="expense_category[]"></td><td><input name="item_name[]"></td>
<td><input class="budget-qty" type="number" step="0.01" min="0.01" name="quantity[]" value="1"></td><td><input name="unit_name[]" value="รายการ"></td>
<td><input class="budget-price" type="number" step="0.01" min="0" name="unit_price[]" value="0.00"></td>
<td><select name="budget_pool_id[]"><option value="">-- เลือก --</option><?php foreach($divisionPools as $pool): ?><option value="<?= h($pool['budget_pool_id']) ?>"><?= h($pool['source_name'].' / '.$pool['pool_name']) ?></option><?php endforeach; ?></select></td>
<td class="text-right money-cell budget-line-total">0.00</td><td><button class="btn btn-danger btn-sm remove-budget-row" type="button">ลบ</button></td>
</tr></template>

<template id="kpiRowTemplate"><tr class="kpi-row">
<td><input name="kpi_name[]"></td><td><select name="kpi_type[]"><option value="quantitative">เชิงปริมาณ</option><option value="qualitative">เชิงคุณภาพ</option></select></td>
<td><input type="number" step="0.01" name="kpi_target[]"></td><td><input name="kpi_unit[]" value="%"></td><td><input name="kpi_method[]"></td><td><button class="btn btn-danger btn-sm remove-kpi-row" type="button">ลบ</button></td>
</tr></template>

<script>
(function(){
    var budgetRows=document.getElementById('budgetRows');
    var budgetTemplate=document.getElementById('budgetRowTemplate');
    var addBudget=document.getElementById('addBudgetRow');
    var totalEl=document.getElementById('budgetTotal');

    function money(v){return Number(v||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});}
    function recalc(){
        var total=0,rows=budgetRows.querySelectorAll('.budget-row');
        for(var i=0;i<rows.length;i++){
            var q=parseFloat(rows[i].querySelector('.budget-qty').value||'0');
            var p=parseFloat(rows[i].querySelector('.budget-price').value||'0');
            if(!isFinite(q)||q<0)q=0;if(!isFinite(p)||p<0)p=0;
            var line=q*p;total+=line;rows[i].querySelector('.budget-line-total').innerHTML=money(line);
        }
        totalEl.innerHTML=money(total);
    }
    function bindBudget(row){
        var inputs=row.querySelectorAll('.budget-qty,.budget-price');
        for(var i=0;i<inputs.length;i++)inputs[i].addEventListener('input',recalc);
        row.querySelector('.remove-budget-row').addEventListener('click',function(){
            if(budgetRows.querySelectorAll('.budget-row').length>1)row.parentNode.removeChild(row);
            recalc();
        });
    }
    var initial=budgetRows.querySelectorAll('.budget-row');for(var i=0;i<initial.length;i++)bindBudget(initial[i]);
    addBudget.addEventListener('click',function(){var f=document.importNode(budgetTemplate.content,true);var r=f.querySelector('.budget-row');budgetRows.appendChild(f);bindBudget(r);recalc();});
    recalc();

    var kpiRows=document.getElementById('kpiRows'),kpiTemplate=document.getElementById('kpiRowTemplate'),addKpi=document.getElementById('addKpiRow');
    function bindKpi(row){row.querySelector('.remove-kpi-row').addEventListener('click',function(){if(kpiRows.querySelectorAll('.kpi-row').length>1)row.parentNode.removeChild(row);});}
    var kr=kpiRows.querySelectorAll('.kpi-row');for(var j=0;j<kr.length;j++)bindKpi(kr[j]);
    addKpi.addEventListener('click',function(){var f=document.importNode(kpiTemplate.content,true);var r=f.querySelector('.kpi-row');kpiRows.appendChild(f);bindKpi(r);});
})();
</script>

<?php require QA_ROOT . '/includes/footer.php'; ?>
