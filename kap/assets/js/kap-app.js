(()=>{
  const sidebarBtn=document.getElementById('kapSidebarBtn');
  sidebarBtn?.addEventListener('click',()=>document.getElementById('kapSidebar')?.classList.toggle('show'));
  // Auto-refresh the Dashboard when the academic year changes. The form button
  // remains available for users who have JavaScript disabled.
  document.getElementById('kapAcademicYear')?.addEventListener('change',(event)=>{
    const form=event.currentTarget.form;
    if(form)form.requestSubmit();
  });
  const yearFilter=document.getElementById('kapScoreYearFilter');
  const gradeFilter=document.getElementById('kapScoreGradeFilter');
  const subjectSelect=document.getElementById('kapSubject');
  const filterCourses=()=>{
    if(!subjectSelect)return;
    for(const option of subjectSelect.options){
      if(!option.value)continue;
      option.hidden=!!((yearFilter?.value&&option.dataset.year!==yearFilter.value)||(gradeFilter?.value&&option.dataset.grade!==gradeFilter.value));
    }
    if(subjectSelect.selectedOptions[0]?.hidden)subjectSelect.value='';
  };
  yearFilter?.addEventListener('change',filterCourses);
  gradeFilter?.addEventListener('change',filterCourses);
  const table=document.getElementById('kapScoreTable');
  if(!table)return;
  const notice=window.KAPNotify;
  const info=(message)=>notice.toast({type:'info',message});
  const warn=(message)=>notice.toast({type:'warning',message});
  const fail=(message)=>notice.toast({type:'error',message,duration:10000});
  let allowNavigation=false;
  const inputs=[...table.querySelectorAll('input[data-score]')], rows=[...table.tBodies[0].rows];
  const reasonBox=document.getElementById('kapScoreReason');
  const saveBtn=document.getElementById('kapSave'), readOnly=saveBtn?.dataset.readonly==='1', status=document.getElementById('kapDirtyStatus'), feedback=document.getElementById('kapFeedback');
  const valid=(x)=>{
    const v=x.value.trim();
    if(v==='')return true;
    if(!/^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/.test(v))return false;
    const money=(str)=>{let [a,b='']=str.split('.');return Number(a)*100+Number(b.padEnd(2,'0'));};
    return money(v)<=money(x.dataset.max);
  };
  const normal=(x)=>x.trim()===''?'':Number(x).toFixed(2);
  const changed=(x)=>normal(x.value)!==normal(x.dataset.original);
  function updateTotals(){
    let dirty=0, invalid=0;
    for(const row of rows){
      const scoreInputs=[...row.querySelectorAll('input[data-score]')];
      const groups={k:[],a:[],p:[]};
      scoreInputs.forEach(x=>{
        const okay=valid(x);x.classList.toggle('invalid',!okay);
        x.classList.toggle('dirty',changed(x));
        if(!okay)invalid++;
        if(changed(x))dirty++;
        groups[x.dataset.group].push(x);
      });
      for(const [group,items] of Object.entries(groups)){
        const total=row.querySelector(`[data-total="${group}"]`);
        if(!total)continue;
        const present=items.filter(i=>i.value.trim()!=='' && valid(i));
        total.textContent=present.length?present.reduce((sum,x)=>sum+Number(x.value),0).toFixed(2):'—';
      }
    }
    status.textContent=invalid?`ข้อมูลผิด ${invalid} ช่อง`:dirty?`แก้ไข ${dirty} ช่อง`:'ไม่มีการเปลี่ยนแปลง';
    status.className='kap-tag '+(invalid?'wait':dirty?'wait':'ok');
    if(saveBtn)saveBtn.disabled=readOnly||invalid>0||dirty===0;
    return {dirty,invalid};
  }
  table.addEventListener('input',updateTotals);
  // Spreadsheet-style multiline/tab paste, bounded to the same assessment group and visible student rows.
  table.addEventListener('paste',(evt)=>{
    const target=evt.target;
    if(readOnly || !(target instanceof HTMLInputElement)||target.readOnly||!target.matches('[data-score]'))return;
    const text=evt.clipboardData?.getData('text/plain')??'';
    if(!text.includes('\t')&&!text.includes('\n'))return;
    evt.preventDefault();
    let grid=text.replace(/\r/g,'').replace(/\n$/,'').split('\n').map(line=>line.split('\t'));
    if(grid.length>500||grid.some(line=>line.length>200)){warn('ข้อมูลที่วางมีขนาดใหญ่เกินไป');return;}
    const tr=target.closest('tr'), allRows=[...table.tBodies[0].rows];
    const baseRow=allRows.indexOf(tr), group=target.dataset.group;
    const inGroup=(row)=>[...row.querySelectorAll(`[data-score][data-group="${group}"]`)];
    const col=inGroup(tr).indexOf(target);
    grid.forEach((line,dy)=>{
      if(!allRows[baseRow+dy])return;
      const rowItems=inGroup(allRows[baseRow+dy]);
      line.forEach((value,dx)=>{if(rowItems[col+dx])rowItems[col+dx].value=value.trim();});
    });
    updateTotals();
  });
  document.getElementById('kapReload')?.addEventListener('click',async()=>{
    if(updateTotals().dirty && !await notice.confirm({title:'เรียกข้อมูลใหม่?',message:'การแก้ไขคะแนนที่ยังไม่บันทึกจะหายไป',tone:'warning',confirmText:'เรียกข้อมูลใหม่'}))return;
    allowNavigation=true;notice.flash({type:'info',message:'เรียกข้อมูลคะแนนใหม่แล้ว'});location.reload();
  });
  document.getElementById('kapClear')?.addEventListener('click',async()=>{
    if(readOnly)return;
    if(!await notice.confirm({title:'ล้างคะแนนในหน้าจอ?',message:'คะแนนทุกช่องที่แสดงจะถูกล้างในหน้าจอเท่านั้น\nข้อมูลต้นทางจะยังไม่เปลี่ยนจนกว่าจะบันทึก',tone:'danger',confirmText:'ล้างในหน้าจอ'}))return;
    inputs.forEach(x=>x.value='');updateTotals();info('ล้างคะแนนในหน้าจอแล้ว ยังไม่ได้บันทึก');
  });
  window.addEventListener('beforeunload',evt=>{if(!allowNavigation && updateTotals().dirty){evt.preventDefault();evt.returnValue='';}});
  saveBtn?.addEventListener('click',async()=>{
    const {dirty,invalid}=updateTotals(); if(invalid){feedback.textContent='กรุณาแก้ช่องสีแดง';warn('มีคะแนนไม่ถูกต้อง กรุณาแก้ช่องสีแดง');return;}if(!dirty)return;
    const changes=inputs.filter(changed).map(x=>({item_id:Number(x.dataset.item),enrollment_id:Number(x.dataset.enrollment),score:x.value.trim()===''?null:x.value.trim(),expected_score:x.dataset.original.trim()===''?null:x.dataset.original.trim()}));
    if(reasonBox && (reasonBox.value.trim().length<5 || reasonBox.value.trim().length>500)){
      reasonBox.focus();warn('กรุณาระบุเหตุผลการกรอกหรือแก้ไขคะแนน 5–500 ตัวอักษร');return;
    }
    const adminNote=reasonBox?'ลง MariaDB จริง โดยมีประวัติผู้ดำเนินการและเหตุผล':'ในโหมดทดลอง';
    if(!await notice.confirm({title:'ยืนยันการบันทึกคะแนน?',message:`กำลังบันทึก ${changes.length} ช่อง ${adminNote}${changes.some(x=>x.score===null)?'\nมีช่องว่างที่จะบันทึกเป็น NULL (ยังไม่กรอก)':''}\nค่า 0 จะเป็นคะแนนศูนย์จริง`,tone:changes.some(x=>x.score===null)?'warning':'primary',confirmText:'บันทึกคะแนน'}))return;
    saveBtn.disabled=true;feedback.textContent='กำลังบันทึก...';
    try{
      const res=await fetch(table.dataset.saveUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({csrf:table.dataset.csrf,subject_class_id:Number(table.dataset.subject),score_plan_id:Number(table.dataset.plan),reason:reasonBox?.value.trim()??null,changes})});
      const contentType=res.headers.get('content-type')||'';
      if(!contentType.includes('application/json'))throw new Error(`เซิร์ฟเวอร์ตอบข้อมูลไม่ใช่ JSON (HTTP ${res.status}) กรุณาตรวจ PHP Error Log`);
      const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.message||`ไม่สามารถบันทึกได้ (HTTP ${res.status})`);
      inputs.forEach(x=>{if(changed(x))x.dataset.original=x.value.trim();});
      feedback.textContent=data.message;
      notice.toast({type:'success',title:'บันทึกคะแนนสำเร็จ',message:data.message});
      updateTotals();
    }catch(e){feedback.textContent='บันทึกไม่สำเร็จ: '+e.message;fail('บันทึกคะแนนไม่สำเร็จ: '+e.message);updateTotals();}
  });
  updateTotals();
})();
