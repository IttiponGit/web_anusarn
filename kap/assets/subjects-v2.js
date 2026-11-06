/* Excel paste into editable rows. This modifies HTML inputs only; server validates/commits. */
(() => {
  'use strict';
  const grid = document.getElementById('sv2-edit-grid');
  const info = document.getElementById('sv2-grid-info');
  const cols = ['code','name','kind','type','area'];
  const say = (s, bad=false) => { if(info){info.textContent=s;info.style.color=bad?'#b42318':'#315d9e';} };
  if (grid) {
    grid.addEventListener('input', e => { const tr=e.target.closest('tr'); if(tr)tr.classList.add('sv2-modified'); });
    grid.addEventListener('change', e => { const tr=e.target.closest('tr'); if(tr)tr.classList.add('sv2-modified'); });
    grid.addEventListener('paste', e => {
      const target = e.target.closest('[data-col]');
      if(!target || target.disabled || target.readOnly) return;
      const clip=e.clipboardData?.getData('text/plain')||'';
      if(!clip.includes('\t')&&!clip.includes('\n'))return;
      e.preventDefault();
      const rows=Array.from(grid.querySelectorAll('tbody tr[data-row]'));
      const tr=target.closest('tr');const ri=rows.indexOf(tr);const ci=cols.indexOf(target.dataset.col);
      if(ri<0||ci<0)return;
      let pasted=clip.replace(/\r\n?/g,'\n').split('\n').map(line=>line.split('\t'));
      if(pasted.length && pasted[pasted.length-1].every(cell=>cell===''))pasted.pop();
      let count=0;let errors=[];
      pasted.forEach((cells,offset)=>{
        const row=rows[ri+offset];if(!row){errors.push('ข้อมูลเกินจำนวนแถวที่แสดง');return;}
        if(row.classList.contains('sv2-locked')){errors.push('มีแถวเปิดใช้งานที่ไม่อนุญาตให้แก้');return;}
        cells.forEach((text,j)=>{
          const col=cols[ci+j];if(!col){errors.push('จำนวนคอลัมน์เกินที่กำหนด');return;}
          const input=row.querySelector('[data-col="'+col+'"]');if(!input||input.disabled||input.readOnly)return;
          const value=text.trim();
          if(input.tagName==='SELECT'){
            const match=Array.from(input.options).find(o=>o.value===value||o.textContent?.trim()===value||o.textContent?.trim().startsWith(value+' ('));
            if(match){input.value=match.value;count++;}
            else if(col==='kind' && ['รายวิชา','กิจกรรม'].includes(value)){input.value=value==='รายวิชา'?'COURSE':'ACTIVITY';count++;}
            else {errors.push('ค่าไม่ตรงกับตัวเลือกคอลัมน์ '+col+': '+value);row.classList.add('sv2-paste-invalid');}
          }else{input.value=value;count++;}
          row.classList.add('sv2-modified');
        });
      });
      say('วาง '+count+' ช่องแล้ว (ยังไม่บันทึก)'+(errors.length?' • ข้อควรแก้: '+[...new Set(errors)].join('; '):''),errors.length>0);
    });
    const form=document.getElementById('sv2-grid-form');
    form?.addEventListener('submit',e=>{
      if(grid.querySelector('.sv2-paste-invalid')){e.preventDefault();say('ตรวจแก้ค่าที่วางไม่ตรงตัวเลือกก่อนบันทึก',true);return;}
      if(!window.confirm('ยืนยันบันทึกข้อมูลที่แก้ไข? ระบบจะตรวจข้อมูลทั้งชุดและบันทึกพร้อมกัน หรือยกเลิกทั้งหมดหากมีข้อผิดพลาด'))e.preventDefault();
    });
  }
  const curriculum=document.getElementById('sv2-curriculum');
  const section=document.getElementById('sv2-section');
  const track=document.getElementById('sv2-track');
  const filter=()=>{
    const v=curriculum?.value||'';
    [section,track].forEach(select=>{
      if(!select)return;
      Array.from(select.options).forEach(option=>{
        const matches=!option.dataset.curriculum||option.dataset.curriculum===v;
        option.disabled=!matches;option.hidden=!matches;
      });
      if(select.selectedOptions.length&&select.selectedOptions[0].disabled)select.value='';
    });
  };
  curriculum?.addEventListener('change',filter);filter();
  const period=document.getElementById('sv2-period');const semester=document.getElementById('sv2-semester');
  const changePeriod=()=>{if(semester){semester.disabled=period?.value==='YEAR';}};
  period?.addEventListener('change',changePeriod);changePeriod();
})();
