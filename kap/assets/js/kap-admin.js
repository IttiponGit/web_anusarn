(() => {
  'use strict';
  document.querySelectorAll('[data-teacher-mode]').forEach(el => {
    const root=el.closest('form');
    if(!root)return;
    const teachingRole=()=>[...root.querySelectorAll('input[name="role_ids[]"][data-role-code="SUBJECT_TEACHER"]')]
      .some(input=>input.checked);
    const change=()=>{
      const needsTeachingArea=teachingRole();
      const linked=root.querySelector('[data-teacher-existing]');
      const created=root.querySelector('[data-teacher-new]');
      if(linked) {
        linked.hidden=el.value!=='existing';
        linked.querySelectorAll('select,input').forEach(field=>{
          field.disabled=el.value!=='existing';
          if(field.name==='teacher_citizen_id')field.required=el.value==='existing';
        });
      }
      if(created) {
        created.hidden=el.value!=='new';
        created.querySelectorAll('input,select').forEach(field=>{
          field.disabled=el.value!=='new';
          if(['teacher_id','first_name','last_name'].includes(field.name))field.required=el.value==='new';
          if(field.name==='learning_area_id')field.required=el.value==='new' && needsTeachingArea;
        });
      }
      const label=root.querySelector('[data-role-area-hint]');
      if(label)label.textContent=needsTeachingArea
        ? 'เลือกบทบาทครูประจำวิชา: ต้องเชื่อมข้อมูลบุคลากรและระบุกลุ่มสาระการเรียนรู้'
        : 'บทบาทที่ไม่ได้สอน: เว้นกลุ่มสาระได้ หรือเลือกไม่เชื่อมข้อมูลบุคลากรก็ได้';
    };
    // Leadership-only accounts do not need a Teacher record. Do not silently
    // change a user's explicit existing/new selection or discard entered data.
    change();
    el.addEventListener('change',change);
    root.querySelectorAll('input[name="role_ids[]"]').forEach(role=>role.addEventListener('change',()=>{
      // Convenience for leadership-only accounts: if no existing personnel has
      // been chosen, do not force an irrelevant profile selection.
      const executive=[...root.querySelectorAll('input[name="role_ids[]"]')]
        .some(box=>box.checked && ['DIRECTOR','DEPUTY_ACADEMICS'].includes(box.dataset.roleCode));
      const linkedSelect=root.querySelector('[name="teacher_citizen_id"]');
      if(executive && !teachingRole() && el.value==='existing' && !linkedSelect?.value)el.value='none';
      change();
    }));
  });
  // Keep error feedback associated with the exact form field. Never render
  // server-supplied strings as HTML; use textContent to avoid script injection.
  const resetFieldError=(form)=>{
    if(typeof form.querySelectorAll!=='function')return;
    form.querySelectorAll('.kap-field-validation').forEach(node=>node.remove());
    form.querySelectorAll('.is-invalid').forEach(node=>{
      node.classList.remove('is-invalid');node.removeAttribute('aria-invalid');
    });
  };
  const markFieldError=(form,fieldName,message)=>{
    if(typeof fieldName!=='string' || fieldName.length>64)return;
    // The API sends only documented field names, not selectors.
    const candidates=[...form.querySelectorAll('[name]')];
    const input=candidates.find(node=>node.name===fieldName && !node.disabled);
    if(!input)return;
    input.classList.add('is-invalid');
    input.setAttribute('aria-invalid','true');
    const area=fieldName==='role_ids[]' ? (input.closest('.kap-role-grid')||input) : input;
    const note=document.createElement('div');
    note.className='kap-field-validation invalid-feedback d-block';
    note.setAttribute('role','alert');
    note.textContent=message;
    area.insertAdjacentElement('afterend',note);
    try{input.scrollIntoView({behavior:'smooth',block:'center'});}catch(_err){}
    input.focus({preventScroll:true});
  };
  document.querySelectorAll('.kap-admin-form').forEach(form => {
    const clearOnChange=()=>resetFieldError(form);
    form.addEventListener('input',clearOnChange);
    form.addEventListener('change',clearOnChange);
    form.addEventListener('submit', async e => {
      e.preventDefault();
      resetFieldError(form);
      const confirmText=form.dataset.confirm || 'ยืนยันบันทึกข้อมูล?';
      const yes=window.KAPNotify
        ? await KAPNotify.confirm({title:'ยืนยันการทำรายการ',message:confirmText,tone:form.dataset.danger?'danger':'warning'})
        : window.confirm(confirmText);
      if(!yes)return;
      const button=form.querySelector('[type="submit"],button:not([type])');
      if(button)button.disabled=true;
      try {
        // HTMLFormElement.action is shadowed by <input name="action"> in KAP forms.
        // Always read the form's *attribute*, never the similarly named DOM property.
        const submitAction=form.getAttribute('action');
        if(!submitAction)throw Error('ไม่พบ URL สำหรับบันทึกข้อมูลในฟอร์ม');
        const endpoint=new URL(submitAction,window.location.href);
        if(endpoint.origin!==window.location.origin)
          throw Error('URL สำหรับบันทึกข้อมูลไม่อยู่ในเว็บไซต์ KAP');
        const r=await fetch(endpoint.href,{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{'Accept':'application/json'}});
        // An Apache 404/403 page, a PHP fatal error, or a login page is HTML.
        // Never present such a response as a JSON parsing error or as success.
        const contentType=(r.headers.get('content-type') || '').toLowerCase();
        if(!contentType.includes('application/json')) {
          const path=endpoint.pathname;
          if(r.status===404)throw Error('ไม่พบ API บันทึกข้อมูล (HTTP 404): '+path+' โปรดตรวจไฟล์ผ่าน FTP');
          if(r.status===401 || (r.redirected && r.url.includes('/auth/login.php')))
            throw Error('Session หมดอายุ กรุณาเข้าสู่ระบบอีกครั้ง');
          if(r.status===403)throw Error('API ถูกปฏิเสธ (HTTP 403) โปรดตรวจสิทธิ์และการตั้งค่า .htaccess');
          if(r.status>=500)throw Error('Backend ส่ง HTML กลับมา (HTTP '+r.status+') โปรดตรวจ PHP Error Log ของ Hosting');
          throw Error('API ไม่ได้ตอบ JSON (HTTP '+r.status+'): '+path+' โปรดตรวจ URL และไฟล์บน Hosting');
        }
        let j;
        try {j=await r.json();}
        catch(_err){throw Error('API ตอบ JSON ไม่สมบูรณ์ กรุณาตรวจ PHP Error Log');}
        if(!r.ok || !j || j.ok!==true) {
          if(r.status===401)throw Error('กรุณาเข้าสู่ระบบ KAP อีกครั้ง');
          if(r.status===419)throw Error('รหัส CSRF หมดอายุ กรุณาโหลดหน้านี้ใหม่แล้วลองอีกครั้ง');
          const error=new Error((j && j.message) || 'ไม่สามารถบันทึกข้อมูลได้ (HTTP '+r.status+')');
          if(typeof j?.field==='string')error.field=j.field;
          if(typeof j?.hint==='string')error.hint=j.hint;
          throw error;
        }
        if(window.KAPNotify)KAPNotify.flash({type:'success',message:j.message||'บันทึกสำเร็จ'});
        window.location.reload();
      }catch(err){
        const problem=String(err.message||err);
        const hint=typeof err.hint==='string' && err.hint.trim()
          ? err.hint.trim()
          : (problem.includes('HTTP 5')
            ? 'โปรดแจ้งผู้ดูแลระบบพร้อมรหัสอ้างอิงหรือรหัสข้อผิดพลาด ไม่ต้องส่งรหัสผ่าน'
            : 'ตรวจสอบช่องที่กรอกและลองใหม่ หากยังไม่สำเร็จให้ติดต่อผู้ดูแลระบบ');
        if(typeof err.field==='string')markFieldError(form,err.field,problem+' | วิธีแก้ไข: '+hint);
        const message='สาเหตุ: '+problem+'\nวิธีแก้ไข: '+hint;
        if(window.KAPNotify)KAPNotify.toast({type:'error',title:'บันทึกข้อมูลไม่สำเร็จ',message,duration:15000});
        else window.alert(message);
      }finally {if(button)button.disabled=false;}
    });
  });
})();

// CurriculumSection parent choices must belong to the selected Curriculum.
(() => {
  'use strict';
  const curriculum=document.querySelector('#sectionCurriculum');
  const parent=document.querySelector('#sectionParent');
  if(!curriculum||!parent)return;
  const update=()=>{
    const allowed=curriculum.value;
    for(const option of parent.querySelectorAll('option[data-curriculum-id]')){
      option.disabled=!allowed || option.dataset.curriculumId!==allowed;
    }
    const selected=parent.selectedOptions[0];
    if(selected && selected.disabled)parent.value='';
  };
  curriculum.addEventListener('change',()=>{parent.value='';update();});
  update();
})();
