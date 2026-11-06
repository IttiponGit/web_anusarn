<?php
declare(strict_types=1);
namespace Kap\Teacher;

final class PlanRules
{
    private static function amount(mixed $raw, string $label, bool $required=true): string
    {
        if (!is_string($raw) && !is_int($raw)) throw new WebError(422,$label.' ไม่ถูกต้อง');
        $s=trim((string)$raw);
        if ($s==='' && !$required) return '';
        if (!preg_match('/^(?:0|[1-9]\d{0,3})(?:\.\d{1,2})?$/D',$s)) throw new WebError(422,$label.' ต้องเป็นเลขไม่ติดลบ ทศนิยมไม่เกินสองตำแหน่ง');
        $parts=explode('.',$s,2);
        $cents=(int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');
        if($cents>999999)throw new WebError(422,$label.' มากเกินช่วงที่อนุญาต');
        return number_format($cents/100,2,'.','');
    }
    public static function cents(string $x):int
    {
        $v=explode('.',$x,2);
        return ((int)$v[0])*100+(int)str_pad($v[1]??'',2,'0');
    }
    public static function validate(array $in):array
    {
        if (!isset($in['units']) || !is_array($in['units']) || count($in['units'])<1 || count($in['units'])>50) throw new WebError(422,'จำนวนหน่วยต้องอยู่ระหว่าง 1 ถึง 50');
        $target=self::amount($in['target']??null,'คะแนนเต็ม');
        if(self::cents($target)===0)throw new WebError(422,'คะแนนเต็มต้องมากกว่าศูนย์');
        $mid=self::amount($in['midterm']??null,'กลางภาค');
        $final=self::amount($in['final_exam']??null,'ปลายภาค');
        $out=[];$total=self::cents($mid)+self::cents($final);
        foreach($in['units'] as $i=>$row){
            if(!is_array($row)) throw new WebError(422,'รูปแบบหน่วยไม่ถูกต้อง');
            $name=trim((string)($row['name']??''));
            if ($name==='') throw new WebError(422,'กรุณากรอกชื่อหน่วยที่ '.($i+1));
            if (preg_match_all('/./us', $name, $matches) === false || count($matches[0])>255) throw new WebError(422,'ชื่อหน่วยยาวเกินไป');
            // สาระการเรียนรู้ is editable draft text. It is distinct from LearningIndicators.
            $rawContent=$row['learning_content']??'';
            if(!is_string($rawContent))throw new WebError(422,'สาระการเรียนรู้หน่วยที่ '.($i+1).' ไม่ถูกต้อง');
            $content=trim($rawContent);
            if(preg_match_all('/./us',$content,$characters)===false || count($characters[0])>2000)throw new WebError(422,'สาระการเรียนรู้หน่วยที่ '.($i+1).' ยาวเกิน 2,000 ตัวอักษร หรือมีอักขระไม่ถูกต้อง');
            $hours=self::amount($row['hours']??'','ชั่วโมงหน่วยที่ '.($i+1),false);
            $k=self::amount($row['k']??null,'คะแนน K หน่วยที่ '.($i+1));
            $a=self::amount($row['a']??null,'คะแนน A หน่วยที่ '.($i+1));
            $p=self::amount($row['p']??null,'คะแนน P หน่วยที่ '.($i+1));
            $total+=self::cents($k)+self::cents($a)+self::cents($p);
            $out[]=['name'=>$name,'learning_content'=>$content,'hours'=>$hours,'k'=>$k,'a'=>$a,'p'=>$p];
        }
        if($total>99999999)throw new WebError(422,'คะแนนรวมเกินขนาดที่กำหนด');
        // Drafts may be incomplete. Activation will require exact total and further checks.
        return ['units'=>$out,'target'=>$target,'midterm'=>$mid,'final_exam'=>$final,'total'=>number_format($total/100,2,'.',''),'balanced'=>$total===self::cents($target)];
    }
}
