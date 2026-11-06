<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/config/bootstrap.php';
use Kap\Teacher\{Bootstrap,Ui,WebError};
try {
 Bootstrap::start();
 $repo=Bootstrap::repo();
 $actor=Bootstrap::mode()==='demo'?Bootstrap::actor():Bootstrap::actor(Bootstrap::db());
 $years=$repo->academicYears();
 $validIds=[];
 foreach($years as $year)$validIds[(int)$year['AcademicYear_id']]=true;
 $requestedYear=null;
 if(isset($_GET['academic_year_id'])){
     $requestedYear=Bootstrap::id($_GET['academic_year_id']);
     if(!isset($validIds[$requestedYear]))throw new WebError(422,'ไม่พบปีการศึกษาที่เลือก');
     $_SESSION['kap_dashboard_academic_year_id']=$requestedYear;
 }
 $selectedYear=$requestedYear;
 if($selectedYear===null){
     $stored=$_SESSION['kap_dashboard_academic_year_id']??null;
     if(is_int($stored)&&isset($validIds[$stored]))$selectedYear=$stored;
 }
 if($selectedYear===null){
     foreach($years as $year){
         if((int)($year['HasAssignedSubjects']??0)===1){$selectedYear=(int)$year['AcademicYear_id'];break;}
     }
 }
 if($selectedYear===null&&$years)$selectedYear=(int)$years[0]['AcademicYear_id'];
 $subjects=$selectedYear===null?[]:$repo->subjects($selectedYear);
 Ui::page('Dashboard',$actor,'dashboard',Ui::dashboard($subjects,$years,$selectedYear));
} catch (\Throwable $e) {Bootstrap::fail($e);}
