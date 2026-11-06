/** KAP roster draft editor: selection is demo/session only. No direct database mutations. */
(() => {
  'use strict';
  const root = document.getElementById('kapRosterRoot');
  const node = document.getElementById('kapRosterData');
  if (!root || !node) return;
  const $ = id => document.getElementById(id);
  const locked = root.dataset.readonly === '1';
  const initial = JSON.parse(node.textContent);
  const students = initial.students || [];
  const rooms = initial.rooms || [];
  const allowed = new Set(students.map(s => Number(s.Student_id)));
  const current = new Set((initial.current_ids || []).map(Number));
  let stored = {
    mode: initial.mode || 'INDIVIDUAL',
    room_ids: (initial.room_ids || []).map(Number),
    selected_ids: (initial.selected_ids || []).map(Number)
  };
  let mode = stored.mode;
  let selected = new Set(stored.selected_ids.filter(id => allowed.has(id)));
  let roomIds = new Set(stored.room_ids.map(Number));
  let revision = Number(initial.draft_revision || 0);
  let busy = false;
  let dirty = false;
  const notice = window.KAPNotify;
  const toast = (type, message) => notice?.toast({type, message});
  const escape = val => String(val ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
  const sorted = ids => [...ids].map(Number).sort((a,b) => a-b);

  function derived() {
    if (mode === 'ALL_GRADE') return new Set(students.map(s => Number(s.Student_id)));
    if (mode === 'SELECTED_ROOMS') return new Set(students.filter(s => roomIds.has(Number(s.ClassRoom_id))).map(s => Number(s.Student_id)));
    return new Set([...selected].filter(id => allowed.has(id)));
  }
  function draftState() {
    return {
      mode,
      room_ids: mode === 'SELECTED_ROOMS' ? sorted(roomIds) : [],
      selected_ids: sorted(derived())
    };
  }
  function updateState() {
    const state = draftState();
    dirty = JSON.stringify(state) !== JSON.stringify(stored);
    const set = new Set(state.selected_ids);
    const added = [...set].filter(id => !current.has(id)).length;
    const removed = [...current].filter(id => !set.has(id)).length;
    const count = state.selected_ids.length;
    const perRoom = new Map();
    for (const student of students) {
      if (!set.has(Number(student.Student_id))) continue;
      const room = String(student.RoomName ?? 'ไม่ระบุ');
      perRoom.set(room, (perRoom.get(room) || 0) + 1);
    }
    const roomSummary = [...perRoom.entries()]
      .sort(([a], [b]) => a.localeCompare(b, 'th', {numeric:true}))
      .map(([room, n]) => `ห้อง ${room}: ${n} คน`).join(' · ');
    $('rosterCount').textContent = `เลือก ${count} คน จากทั้งหมด ${students.length} คน · ${perRoom.size} ห้อง` + (roomSummary ? ` (${roomSummary})` : '');
    $('rosterDiff').textContent = `เปรียบเทียบทะเบียนปัจจุบัน: เพิ่ม ${added} คน · ไม่อยู่ในรายการร่าง ${removed} คน (ยังไม่ถอนทะเบียนจริง)`;
    $('rosterDirty').textContent = dirty ? 'มีการแก้ไขยังไม่บันทึก' : 'ยังไม่มีการเปลี่ยนแปลง';
    $('rosterDirty').className = 'kap-tag ' + (dirty ? 'wait' : 'ok');
    $('rosterSave').disabled = locked || busy || !dirty || count === 0 || (mode === 'SELECTED_ROOMS' && roomIds.size === 0);
  }
  function drawRooms() {
    const visible = mode === 'SELECTED_ROOMS';
    $('rosterRoomPanel').hidden = !visible;
    $('rosterRoomChoices').innerHTML = rooms.map(room => {
      const id = Number(room.ClassRoom_id);
      const checked = roomIds.has(id) ? ' checked' : '';
      return `<label class="roster-room-choice"><input class="form-check-input me-2" type="checkbox" data-room-id="${id}"${checked}${locked?' disabled':''}>ห้อง ${escape(room.RoomName)}</label>`;
    }).join('') || '<span class="text-muted">ไม่มีห้องเรียนในปี/ระดับชั้นนี้</span>';
  }
  function drawStudents() {
    const query = $('rosterSearch').value.trim().toLocaleLowerCase('th');
    const checkedSet = derived();
    let shown = 0;
    let html = '';
    for (const student of students) {
      const id = Number(student.Student_id);
      const haystack = `${student.StudentCode||''} ${student.StudentName||''} ${student.RoomName||''}`.toLocaleLowerCase('th');
      if(query && !haystack.includes(query)) continue;
      shown++;
      const checked = checkedSet.has(id);
      html += `<tr class="${checked?'roster-selected':''}">
        <td><input class="form-check-input" type="checkbox" data-student-id="${id}" aria-label="เลือก ${escape(student.StudentName)}" ${checked?'checked':''} ${(locked || mode !== 'INDIVIDUAL')?'disabled':''}></td>
        <td>${escape(student.StudentCode)}</td>
        <td class="text-start">${escape(student.StudentName)}</td>
        <td>${escape(student.RoomName)}</td>
        <td>${escape(student.ClassStatus || '—')}</td>
      </tr>`;
    }
    $('rosterRows').innerHTML = html || `<tr><td colspan="5" class="py-4 text-center text-muted">${students.length===0?'ยังไม่มีรายชื่อนักเรียนจากทะเบียนในระดับนี้':'ไม่พบรายชื่อที่ค้นหา'}</td></tr>`;
    $('rosterRows').setAttribute('data-visible-count', String(shown));
    updateState();
  }
  function draw() {
    $('rosterMode').value=mode;
    drawRooms();
    drawStudents();
  }
  $('rosterMode').addEventListener('change', e => {
    if(locked)return;
    // The individual mode starts with the currently visible selection, not an empty list.
    const oldSelection=derived();
    mode=e.target.value;
    if(mode==='INDIVIDUAL')selected=oldSelection;
    draw();
  });
  $('rosterRoomChoices').addEventListener('change', e => {
    const input=e.target.closest('input[data-room-id]');
    if (!input || locked) return;
    const id=Number(input.dataset.roomId);
    if(input.checked)roomIds.add(id); else roomIds.delete(id);
    drawStudents();
  });
  $('rosterRows').addEventListener('change', e => {
    const input=e.target.closest('input[data-student-id]');
    if(!input || locked || mode!=='INDIVIDUAL') return;
    const id=Number(input.dataset.studentId);
    if(input.checked)selected.add(id); else selected.delete(id);
    input.closest('tr')?.classList.toggle('roster-selected',input.checked);
    updateState();
  });
  $('rosterSearch').addEventListener('input', drawStudents);
  $('rosterReset').addEventListener('click', async () => {
    if(locked || busy) return;
    if(dirty && !(await notice.confirm({title:'คืนค่ารายชื่อนักเรียน?',message:'ข้อมูลการเลือกที่ยังไม่ได้บันทึกจะถูกยกเลิก',tone:'warning',confirmText:'คืนค่า'}))) return;
    mode=stored.mode;
    roomIds=new Set(stored.room_ids);
    selected=new Set(stored.selected_ids);
    $('rosterFeedback').textContent='';
    draw();
    toast('info','คืนค่ารายชื่อนักเรียนล่าสุดแล้ว');
  });
  $('rosterSave').addEventListener('click', async () => {
    if(locked || busy) return;
    const state=draftState();
    if(!state.selected_ids.length){toast('warning','กรุณาเลือกนักเรียนอย่างน้อย 1 คน');return;}
    const added=state.selected_ids.filter(id=>!current.has(id)).length;
    const removed=[...current].filter(id=>!state.selected_ids.includes(id)).length;
    const ok=await notice.confirm({
      title:'บันทึกการเลือกนักเรียน?',
      message:`เลือก ${state.selected_ids.length} คน (เพิ่ม ${added}, เปลี่ยนจากทะเบียนเดิม ${removed})\nบันทึกเป็นร่างใน Session เท่านั้น ยังไม่เพิ่ม/ถอนทะเบียนและไม่เปลี่ยนคะแนนจริง`,
      tone:'primary',confirmText:'บันทึกร่าง'
    });
    if(!ok)return;
    busy=true;updateState();$('rosterFeedback').textContent='กำลังบันทึกรายชื่อ...';
    try {
      const response=await fetch(root.dataset.saveUrl,{
        method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json'},
        body:JSON.stringify({subject_class_id:Number(root.dataset.subject),revision,csrf:root.dataset.csrf,...state})
      });
      const result=await response.json();
      if(!response.ok || !result.ok)throw new Error(result.message || 'บันทึกไม่สำเร็จ');
      revision=Number(result.revision);
      stored=state;
      $('rosterFeedback').textContent=result.message;
      toast('success',`บันทึกร่าง ${result.selected_count} คนเรียบร้อย`);
    } catch(error) {
      $('rosterFeedback').textContent=error.message;
      toast('error','ไม่สามารถบันทึก: '+error.message);
    } finally {busy=false;draw();}
  });
  // Initialize the current stored selection; selected students are never automatically removed.
  draw();
})();
