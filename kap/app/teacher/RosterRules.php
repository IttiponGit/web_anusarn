<?php
declare(strict_types=1);
namespace Kap\Teacher;

/** Validate a student-selection snapshot. No enrollment is inserted/deleted here. */
final class RosterRules
{
    private static function ids(mixed $raw, string $label): array
    {
        if (!is_array($raw) || count($raw)>2000) throw new WebError(422,$label.' ไม่ถูกต้อง');
        $ids=[];
        foreach ($raw as $value) {
            if (!(is_int($value)||is_string($value)) || !ctype_digit((string)$value) || (int)$value<1) {
                throw new WebError(422,$label.' มีรหัสไม่ถูกต้อง');
            }
            $id=(int)$value;
            if(isset($ids[$id])) throw new WebError(422,$label.' มีรหัสซ้ำ');
            $ids[$id]=true;
        }
        return array_keys($ids);
    }

    public static function validate(array $payload, array $source): array
    {
        $mode=$payload['mode']??null;
        if(!is_string($mode) || !in_array($mode,['ALL_GRADE','SELECTED_ROOMS','INDIVIDUAL'],true)) {
            throw new WebError(422,'กรุณาเลือกวิธีเลือกนักเรียน');
        }
        $providedRooms=self::ids($payload['room_ids']??[],'รายการห้อง');
        // The browser sends selected_ids. Older clients may still submit student_ids.
        // Normalize both names so the server never mistakes a non-empty selection for [].
        $hasSelected=array_key_exists('selected_ids',$payload);
        $hasLegacy=array_key_exists('student_ids',$payload);
        if ($hasSelected && $hasLegacy) {
            $canonical=self::ids($payload['selected_ids'],'รายชื่อนักเรียน');
            $legacy=self::ids($payload['student_ids'],'รายชื่อนักเรียน (รูปแบบเดิม)');
            $checkA=$canonical; $checkB=$legacy;
            sort($checkA,SORT_NUMERIC); sort($checkB,SORT_NUMERIC);
            if ($checkA!==$checkB) throw new WebError(422,'ข้อมูลรายชื่อนักเรียนไม่ตรงกัน กรุณาโหลดหน้าใหม่');
            $providedStudents=$canonical;
        } else {
            $providedStudents=self::ids(
                $hasSelected ? $payload['selected_ids'] : ($hasLegacy ? $payload['student_ids'] : []),
                'รายชื่อนักเรียน'
            );
        }
        $rooms=[];
        foreach ($source['rooms'] as $room) $rooms[(int)$room['ClassRoom_id']]=true;
        $students=[];
        foreach ($source['students'] as $student) $students[(int)$student['Student_id']]=(int)$student['ClassRoom_id'];
        foreach ($providedRooms as $roomId) if(!isset($rooms[$roomId])) throw new WebError(403,'ห้องที่เลือกไม่อยู่ในปี/ระดับชั้นของรายวิชานี้');
        foreach ($providedStudents as $studentId) if(!isset($students[$studentId])) throw new WebError(403,'นักเรียนที่เลือกไม่อยู่ในปี/ระดับชั้นของรายวิชานี้');
        if($mode==='ALL_GRADE') {
            $selected=array_keys($students);
            $roomIds=[];
        } elseif($mode==='SELECTED_ROOMS') {
            if(!$providedRooms)throw new WebError(422,'กรุณาเลือกอย่างน้อยหนึ่งห้อง');
            $roomIds=$providedRooms;
            $selected=[];
            foreach($students as $studentId=>$roomId) if(in_array($roomId,$roomIds,true))$selected[]=$studentId;
        } else {
            $roomIds=[];
            $selected=$providedStudents;
        }
        sort($selected,SORT_NUMERIC);
        sort($roomIds,SORT_NUMERIC);
        if(!$selected) throw new WebError(422,'ยังไม่ได้เลือกนักเรียนอย่างน้อยหนึ่งคน');
        return ['mode'=>$mode,'room_ids'=>$roomIds,'selected_ids'=>$selected];
    }
}
