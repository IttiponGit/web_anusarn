(()=>{
  'use strict';
  const table=document.getElementById('schoolRosterTable');
  const grade=document.getElementById('rosterGradeFilter');
  const room=document.getElementById('rosterRoomFilter');
  const search=document.getElementById('rosterTextFilter');
  const visible=document.getElementById('schoolRosterVisible');
  function setupRooms(gradeInput, roomInput){
    if(!gradeInput||!roomInput)return;
    const options=[...roomInput.options].filter(o=>o.value);
    const reset=()=>{
      const chosen=gradeInput.value;
      roomInput.value='';roomInput.disabled=!chosen;
      for(const o of options)o.hidden=o.disabled=(!chosen||o.dataset.grade!==chosen);
      roomInput.options[0].textContent=chosen?'ทุกห้อง / เลือกห้อง':'เลือกระดับชั้นก่อน';
    };
    gradeInput.addEventListener('change',reset);
    reset();
  }
  if(table){
    const rows=[...table.tBodies[0].rows];
    const apply=()=>{
      const q=(search?.value||'').trim().toLocaleLowerCase('th');let count=0;
      for(const row of rows){
        if(!row.dataset.grade){row.hidden=false;continue;}
        const show=(!grade?.value||row.dataset.grade===grade.value)
          &&(!room?.value||row.dataset.room===room.value)
          &&(!q||(row.dataset.search||'').includes(q));
        row.hidden=!show;if(show)count++;
      }
      if(visible)visible.textContent=String(count);
    };
    setupRooms(grade,room);
    grade?.addEventListener('change',apply);room?.addEventListener('change',apply);search?.addEventListener('input',apply);
    apply();
  }
  setupRooms(document.getElementById('manageGradeFilter'),document.getElementById('manageRoomFilter'));
  setupRooms(document.getElementById('moveGradeFilter'),document.getElementById('moveRoomFilter'));
  const notify=(type,title,message)=>{
    if(window.KAPNotify?.toast)window.KAPNotify.toast({type,title,message,duration:type==='error'?12000:5000});
    else alert(`${title}: ${message}`);
  };
  for(const form of document.querySelectorAll('form.kap-student-form')){
    form.addEventListener('submit',async event=>{
      event.preventDefault();
      if(!form.reportValidity())return;
      const message=form.dataset.confirm||'ยืนยันบันทึกข้อมูลทะเบียนนักเรียน?';
      const ok=window.KAPNotify?.confirm?await window.KAPNotify.confirm({title:'ยืนยันการดำเนินการ',message,confirmText:'ยืนยัน'}):window.confirm(message);
      if(!ok)return;
      const btn=form.querySelector('button[type="submit"]');
      if(btn)btn.disabled=true;
      try{
        // IMPORTANT: a hidden input named "action" shadows form.action in browsers.
        const url=form.getAttribute('action');
        if(!url||!url.startsWith('/kap/api/students/'))throw new Error('ที่อยู่ API ทะเบียนนักเรียนไม่ถูกต้อง');
        const response=await fetch(url,{method:'POST',credentials:'same-origin',redirect:'manual',body:new FormData(form),headers:{Accept:'application/json'}});
        const ct=response.headers.get('content-type')||'';
        if(!ct.includes('application/json'))throw new Error(`API ไม่ตอบ JSON (HTTP ${response.status}) กรุณาตรวจไฟล์ /kap/api/students/actions.php และ Login`);
        const result=await response.json();
        if(!response.ok||!result.ok){
          const msg=[result.message,result.hint,result.stage?`ขั้นตอน ${result.stage}`:'',result.sqlstate?`SQLSTATE ${result.sqlstate}`:'',result.db_errno?`MariaDB ${result.db_errno}`:''].filter(Boolean).join(' · ');
          const target=result.field?form.querySelector(`[name="${CSS.escape(result.field)}"]`):null;
          if(target){target.classList.add('is-invalid');target.focus();}
          throw new Error(msg||`HTTP ${response.status}`);
        }
        if(window.KAPNotify?.flash)window.KAPNotify.flash({type:'success',title:'บันทึกสำเร็จ',message:result.message||'บันทึกเรียบร้อย'});
        window.location.href='/kap/pages/students/index.php';
      }catch(err){notify('error','บันทึกข้อมูลไม่สำเร็จ',err instanceof Error?err.message:'เกิดข้อผิดพลาดในการบันทึก');}
      finally{if(btn)btn.disabled=false;}
    });
  }
})();
