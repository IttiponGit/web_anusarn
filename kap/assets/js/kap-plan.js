(() => {
  'use strict';
  const root = document.getElementById('kapPlanRoot');
  const initNode = document.getElementById('kapPlanData');
  if (!root || !initNode) return;
  const byId = (id) => document.getElementById(id);
  const locked = root.dataset.readonly === '1';
  const saved = JSON.parse(initNode.textContent);
  let revision = Number(saved.draft_revision || 0);
  let rows = structuredClone(saved.units || []);
  let dirty = false;
  const count = byId('planCount');
  const feedback = byId('planFeedback');
  const saveBtn = byId('planSave');
  const body = byId('planRows');
  const maxUnits = 50;
  const notice = window.KAPNotify;
  const info = (message) => notice.toast({type:'info',message});
  const warn = (message) => notice.toast({type:'warning',message});
  const fail = (message) => notice.toast({type:'error',message,duration:10000});
  const escape = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const fmt = c => (c / 100).toFixed(2);
  const asCents = (value, emptyOK = false) => {
    const str = String(value ?? '').trim();
    if (str === '' && emptyOK) return 0;
    if (!/^(?:0|[1-9]\d{0,3})(?:\.\d{1,2})?$/.test(str)) return null;
    const [a,b=''] = str.split('.');
    return Number(a)*100 + Number(b.padEnd(2,'0'));
  };
  const editor = (key,i,value,type='number') => `<input ${locked?'disabled':''} class="form-control form-control-sm plan-field" data-row="${i}" data-key="${key}" ${type==='number'?'type="number" min="0" step="0.01" max="9999.99"':'type="text" maxlength="255"'} value="${escape(value)}" aria-label="${key} หน่วยที่ ${i+1}">`;
  const contentEditor = (i,value) => `<textarea ${locked?'disabled':''} class="form-control form-control-sm plan-field plan-content-field" data-row="${i}" data-key="learning_content" rows="2" maxlength="2000" placeholder="สาระหรือเนื้อหาที่เรียนในหน่วยนี้" aria-label="สาระการเรียนรู้ หน่วยที่ ${i+1}">${escape(value ?? '')}</textarea>`;
  function renderTable() {
    body.innerHTML = rows.map((u,i) => `<tr>
      <td class="text-center">${i+1}</td>
      <td>${editor('name',i,u.name,'text')}</td>
      <td class="plan-cell-content">${contentEditor(i,u.learning_content)}</td>
      <td>${editor('hours',i,u.hours ?? '', 'number')}</td>
      <td class="plan-cell-k">${editor('k',i,u.k)}</td>
      <td class="plan-cell-a">${editor('a',i,u.a)}</td>
      <td class="plan-cell-p">${editor('p',i,u.p)}</td>
      <td class="fw-bold text-center" data-unit-total="${i}">0.00</td>
      <td><button ${locked?'disabled':''} class="btn btn-sm btn-outline-danger" data-delete="${i}" title="ลบหน่วย ${i+1}" type="button"><i class="bi bi-trash"></i></button></td>
    </tr>`).join('');
    count.value = rows.length;
    calculate();
  }
  function collectRows() {
    for (const el of body.querySelectorAll('[data-row][data-key]')) rows[Number(el.dataset.row)][el.dataset.key] = el.value;
  }
  function calculate() {
    let invalid = false;
    const totals = {k:0,a:0,p:0};
    rows.forEach((row,i) => {
      let unit=0;
      for (const key of ['k','a','p']) {
        const cents=asCents(row[key]);
        const input=body.querySelector(`[data-row="${i}"][data-key="${key}"]`);
        input?.classList.toggle('is-invalid',cents === null);
        if(cents === null) invalid=true;
        else { totals[key]+=cents; unit+=cents; }
      }
      if(!String(row.name||'').trim() || [...String(row.name)].length>255) invalid=true;
      const content = String(row.learning_content ?? '');
      const contentValid = [...content].length <= 2000;
      body.querySelector(`[data-row="${i}"][data-key="learning_content"]`)?.classList.toggle('is-invalid', !contentValid);
      if(!contentValid) invalid=true;
      const hours=asCents(row.hours,true);
      if(hours===null)invalid=true;
      const cell=body.querySelector(`[data-unit-total="${i}"]`);
      if(cell)cell.textContent=fmt(unit);
    });
    const mid=asCents(byId('planMidterm').value);
    const final=asCents(byId('planFinal').value);
    const target=asCents(byId('planTarget').value);
    for(const [el,cents] of [[byId('planMidterm'),mid],[byId('planFinal'),final],[byId('planTarget'),target]]) el.classList.toggle('is-invalid',cents===null || (el.id==='planTarget' && cents===0));
    if(mid===null||final===null||target===null||target===0)invalid=true;
    const totalUnits=totals.k+totals.a+totals.p;
    const total=totalUnits+(mid||0)+(final||0);
    const put=(id,val)=>byId(id).textContent=fmt(val);
    put('planKSum',totals.k);put('planASum',totals.a);put('planPSum',totals.p);put('planUnitsSum',totalUnits);
    put('planSummaryK',totals.k);put('planSummaryA',totals.a);put('planSummaryP',totals.p);
    put('planSummaryMid',mid||0);put('planSummaryFinal',final||0);put('planGrandTotal',total);
    const balance=byId('planBalance');
    const okay=target!==null && target>0 && total===target && !invalid;
    balance.textContent=invalid?'กรอกข้อมูลไม่ถูกต้อง':okay?'รวมครบตามคะแนนเป้าหมาย':'ยังไม่ครบ: '+fmt(total)+' / '+(target===null?'—':fmt(target));
    balance.className='kap-tag '+(okay?'ok':'wait');
    saveBtn.disabled=locked||invalid||!dirty;
    byId('planDirty').textContent=dirty?'มีการแก้ไขยังไม่บันทึก':'ยังไม่มีการเปลี่ยนแปลง';
    byId('planDirty').className='kap-tag '+(dirty?'wait':'ok');
    return !invalid;
  }
  function markDirty(){dirty=true; calculate();}
  function createUnit() {
    return {name:`หน่วยที่ ${rows.length+1}`,learning_content:'',hours:'',k:'0.00',a:'0.00',p:'0.00'};
  }
  byId('planSubject')?.addEventListener('change', async e => {
    const id=e.target.value;
    if (!id) return;
    if(dirty && !await notice.confirm({title:'เปลี่ยนรายวิชา?',message:'มีข้อมูลที่ยังไม่บันทึก หากเปลี่ยนรายวิชา การแก้ไขในหน้านี้จะหายไป',tone:'warning',confirmText:'เปลี่ยนรายวิชา'})) { e.target.value=root.dataset.subject;return; }
    location.href=location.pathname+'?subject_class_id='+encodeURIComponent(id);
  });
  body.addEventListener('input',event => {
    if(!event.target.matches('[data-row][data-key]'))return;
    collectRows();markDirty();
  });
  body.addEventListener('click',async event => {
    const b=event.target.closest('[data-delete]');if(!b||locked)return;
    if(rows.length<=1){warn('ต้องมีหน่วยการเรียนรู้อย่างน้อย 1 หน่วย');return;}
    collectRows();
    const index=Number(b.dataset.delete);
    const name=String(rows[index]?.name || `หน่วยที่ ${index+1}`);
    if(!await notice.confirm({title:'ลบหน่วยการเรียนรู้?',message:`ต้องการลบ “${name}” ออกจากแผนฉบับร่างใช่หรือไม่?\nการเปลี่ยนแปลงจะยังไม่ถูกบันทึกจนกว่าจะกด “บันทึกข้อมูล”`,tone:'danger',confirmText:'ลบหน่วย'}))return;
    rows.splice(index,1);renderTable();markDirty();
    info(`นำ ${name} ออกจากตารางแล้ว (ยังไม่ได้บันทึก)`);
  });
  byId('planAdd')?.addEventListener('click',()=>{
    if(locked)return;if(rows.length>=maxUnits){warn('เพิ่มได้สูงสุด 50 หน่วย');return;}
    collectRows();rows.push(createUnit());renderTable();markDirty();info(`เพิ่มหน่วยที่ ${rows.length} แล้ว (ยังไม่ได้บันทึก)`);
  });
  byId('planApplyCount')?.addEventListener('click',async ()=>{
    if(locked)return;
    const n=Number(count.value);
    if(!Number.isInteger(n)||n<1||n>maxUnits){warn('กรุณาระบุจำนวนหน่วยระหว่าง 1 ถึง 50');count.value=rows.length;return;}
    collectRows();
    if(n<rows.length && !await notice.confirm({title:'ลดจำนวนหน่วย?',message:`กำลังลดจาก ${rows.length} เหลือ ${n} หน่วย ข้อมูลแถวท้ายจะถูกนำออกจากตารางก่อนบันทึก`,tone:'danger',confirmText:'ลดจำนวนหน่วย'})){count.value=rows.length;return;}
    while(rows.length<n)rows.push(createUnit());
    rows.length=n;renderTable();markDirty();feedback.textContent='';info(`ปรับจำนวนหน่วยเป็น ${n} แล้ว (ยังไม่ได้บันทึก)`);
  });
  for(const id of ['planMidterm','planFinal','planTarget'])byId(id).addEventListener('input',markDirty);
  byId('planReset')?.addEventListener('click',async ()=>{
    if(locked)return;if(dirty && !await notice.confirm({title:'คืนค่าที่บันทึกไว้?',message:'การแก้ไขที่ยังไม่บันทึกทั้งหมดจะถูกยกเลิก',tone:'warning',confirmText:'คืนค่า'}))return;
    rows=structuredClone(saved.units);byId('planMidterm').value=saved.midterm;byId('planFinal').value=saved.final_exam;byId('planTarget').value=saved.target;
    dirty=false;renderTable();feedback.textContent='คืนค่าที่บันทึกไว้แล้ว';info('คืนค่าที่บันทึกไว้ล่าสุดแล้ว');
  });
  saveBtn?.addEventListener('click',async()=>{
    if(locked)return;collectRows();if(!calculate()) {feedback.textContent='กรุณาตรวจช่องข้อมูลที่มีสีแดง';warn('ไม่สามารถบันทึกได้ กรุณาตรวจช่องข้อมูลที่มีสีแดง');return;}
    if(!await notice.confirm({title:'บันทึกแผนคะแนน?',message:'บันทึกหน่วยการเรียนรู้และคะแนน K/A/P ที่แก้ไขเป็นฉบับร่างใน Session\nยังไม่มีการเปลี่ยนแปลงข้อมูลบน MariaDB จริง',tone:'primary',confirmText:'บันทึกข้อมูล'}))return;
    const payload={subject_class_id:Number(root.dataset.subject),revision,csrf:root.dataset.csrf,units:rows,target:byId('planTarget').value,midterm:byId('planMidterm').value,final_exam:byId('planFinal').value};
    saveBtn.disabled=true;feedback.textContent='กำลังบันทึกข้อมูล...';
    try{
      const response=await fetch(root.dataset.saveUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},credentials:'same-origin',body:JSON.stringify(payload)});
      const data=await response.json();
      if(!response.ok||!data.ok)throw new Error(data.message||'บันทึกไม่สำเร็จ');
      revision=data.revision;
      saved.units=structuredClone(rows);saved.target=payload.target;saved.midterm=payload.midterm;saved.final_exam=payload.final_exam;
      dirty=false;feedback.textContent=data.message+(data.balanced?' · คะแนนครบ':' · คะแนนยังไม่ครบสำหรับเปิดใช้งาน');
      notice.toast({type:'success',title:'บันทึกข้อมูลสำเร็จ',message:data.message});
      if(!data.balanced)warn('คะแนนรวมยังไม่ตรงคะแนนเต็มเป้าหมาย จึงยังไม่พร้อมเปิดใช้งาน');
    }catch(error){feedback.textContent='ไม่สามารถบันทึก: '+error.message;fail('บันทึกไม่สำเร็จ: '+error.message);}
    calculate();
  });
  renderTable();
})();
