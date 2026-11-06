<?php
declare(strict_types=1);
namespace Kap\Admin;

/** Pure validation for the Subject catalog. Database membership checks are in SubjectCatalog. */
final class SubjectRules
{
    public static function validate(array $input): array
    {
        $kind=(string)($input['subject_kind']??'');
        if(!in_array($kind,['COURSE','ACTIVITY'],true))
            throw new FieldError('subject_kind','ยังไม่ได้เลือกชนิดรายการ','เลือก “รายวิชา” หรือ “กิจกรรม” ก่อนบันทึก');
        $name=Security::text($input['subject_name']??null,255,true,'subject_name','ชื่อรายวิชา/กิจกรรม');
        $raw=$input['subject_code']??'';
        if(!is_string($raw)) throw new FieldError('subject_code','รหัสรายวิชาไม่ถูกต้อง','กรอกรหัสเป็นข้อความ หรือปล่อยว่างหากยังไม่มีรหัส');
        $code=trim($raw);
        if($code!==''){
            $len=function_exists('mb_strlen')?mb_strlen($code,'UTF-8'):strlen($code);
            if($len>80 || preg_match('/[\x00-\x1F\x7F]/',$code))
                throw new FieldError('subject_code','รหัสรายวิชามีรูปแบบไม่ถูกต้อง','กรอกไม่เกิน 80 ตัวอักษรและไม่มีอักขระควบคุม หรือปล่อยว่าง');
        }
        $area=($input['learning_area_id']??'')===''?null:Security::positive($input['learning_area_id'],'learning_area_id','กลุ่มสาระการเรียนรู้');
        $type=($input['course_type_id']??'')===''?null:Security::positive($input['course_type_id'],'course_type_id','ประเภทวิชา');
        return ['SubjectKind'=>$kind,'Subject_name'=>$name,'Subject_code'=>$code===''?null:$code,
            'Learning_Area_id'=>$area,'TypeofCourse_id'=>$type];
    }
    /** Exactly one grade for each catalog record, with or without subject code. */
    public static function gradeSelection(array $input): array
    {
        if(($input['grade_selection_present']??null)!=='1')
            throw new FieldError('grade_level_id','ต้องระบุระดับชั้นที่ใช้สอนหนึ่งระดับ','โหลดหน้าใหม่แล้วเลือกระดับชั้นก่อนบันทึก');
        // Reject older multi-checkbox forms at API level. Silently picking the
        // first checkbox would corrupt the intended one-subject/one-grade rule.
        if(array_key_exists('grade_level_ids',$input) || array_key_exists('grade_level_ids[]',$input))
            throw new FieldError('grade_level_id','แบบฟอร์มเดิมเลือกได้หลายระดับชั้น กรุณาโหลดหน้าใหม่','เลือกหนึ่งระดับชั้นจากรายการเท่านั้น');
        $id=Security::positive($input['grade_level_id']??null,'grade_level_id','ระดับชั้นที่ใช้สอน');
        return [$id];
    }

}
