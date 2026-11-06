/**
 * KAP shared CRUD notices (no JavaScript framework required).
 * API: await KAPNotify.confirm({title,message,tone,confirmText,cancelText})
 *      KAPNotify.toast({type:'success'|'error'|'warning'|'info',title,message,duration})
 *      await KAPNotify.alert({title,message,tone})
 *      KAPNotify.flash({type,message,title})  // show after a safe reload
 * Every message enters the DOM with textContent, never as HTML.
 */
(() => {
  'use strict';
  if (window.KAPNotify) return;
  const ICONS = {success:'check-circle-fill', error:'x-circle-fill', warning:'exclamation-triangle-fill', info:'info-circle-fill'};
  const FLASH = 'kap:notice:flash:v1';
  const toastContainer = () => {
    let el = document.querySelector('#kapToastLayer');
    if (!el) {
      el = document.createElement('div');
      el.id = 'kapToastLayer';
      el.className = 'kap-toast-layer';
      el.setAttribute('role','region');
      el.setAttribute('aria-label','การแจ้งเตือน');
      document.body.appendChild(el);
    }
    return el;
  };
  const mk = (tag,cls,txt) => {
    const el=document.createElement(tag);
    if(cls)el.className=cls;
    if(txt !== undefined)el.textContent=String(txt);
    return el;
  };
  const options = o => typeof o === 'string' ? {message:o} : (o || {});
  let lastModal = Promise.resolve(); // modal queue: never display multiple confirmations at once
  function toast(raw={}) {
    const o=options(raw), type=['success','error','warning','info'].includes(o.type)?o.type:'info';
    const title=o.title || ({success:'สำเร็จ',error:'เกิดข้อผิดพลาด',warning:'โปรดตรวจสอบ',info:'แจ้งข้อมูล'})[type];
    const layer=toastContainer();
    const node=mk('div',`kap-toast kap-toast-${type}`);
    node.setAttribute('role',type==='error'?'alert':'status');
    const icon=mk('i',`bi bi-${ICONS[type]} kap-toast-icon`);icon.setAttribute('aria-hidden','true');
    const content=mk('div','kap-toast-content');
    content.append(mk('div','kap-toast-title',title),mk('div','kap-toast-message',o.message??''));
    const close=mk('button','kap-toast-close','×');close.type='button';close.setAttribute('aria-label','ปิดการแจ้งเตือน');
    node.append(icon,content,close);
    const remove=()=>{node.remove();};
    close.addEventListener('click',remove);
    layer.appendChild(node);
    // Errors remain on screen longer; never hide critical failures immediately.
    const duration=Number.isFinite(Number(o.duration))?Math.max(0,Math.min(30000,Number(o.duration))):(type==='error'?10000:4500);
    if(duration>0)setTimeout(remove,duration);
    return node;
  }
  function dialog(raw,confirmation) {
    const o=options(raw);
    const tone=['danger','warning','primary','info'].includes(o.tone)?o.tone:(confirmation?'warning':'info');
    const opener=document.activeElement;
    const overlay=mk('div','kap-dialog-overlay');
    const box=mk('div','kap-dialog');
    box.setAttribute('role','dialog');box.setAttribute('aria-modal','true');
    const titleId='kap-dialog-title';const descId='kap-dialog-description';
    box.setAttribute('aria-labelledby',titleId);box.setAttribute('aria-describedby',descId);
    const top=mk('div','kap-dialog-top');
    const symbol=mk('span',`kap-dialog-symbol kap-dialog-symbol-${tone}`);
    const icon=mk('i','bi bi-'+(tone==='danger'?'trash3-fill':tone==='warning'?'exclamation-triangle-fill':'info-circle-fill'));
    icon.setAttribute('aria-hidden','true');symbol.appendChild(icon);
    const heading=mk('h2','kap-dialog-title',o.title||(confirmation?'ยืนยันการดำเนินการ':'แจ้งเตือน'));
    heading.id=titleId;top.append(symbol,heading);
    const message=mk('p','kap-dialog-message',o.message??'');message.id=descId;
    const actions=mk('div','kap-dialog-actions');
    const cancel=mk('button','btn btn-outline-secondary',o.cancelText||'ยกเลิก');cancel.type='button';
    const yes=mk('button',`btn ${tone==='danger'?'btn-danger':'btn-primary'}`,o.confirmText||(confirmation?'ยืนยัน':'ตกลง'));yes.type='button';
    if(confirmation)actions.append(cancel);actions.append(yes);
    box.append(top,message,actions);overlay.appendChild(box);document.body.appendChild(overlay);
    document.body.classList.add('kap-dialog-open');
    return new Promise(resolve=>{
      let ended=false;
      function finish(result) {
        if(ended)return;ended=true;
        document.removeEventListener('keydown',keyHandler,true);
        overlay.remove();document.body.classList.remove('kap-dialog-open');
        if(opener && opener.isConnected && typeof opener.focus==='function')opener.focus();
        resolve(result);
      }
      function keyHandler(e) {
        if(e.key==='Escape'){e.preventDefault();finish(false);}
        if(e.key==='Tab') {
          const focused=[...(confirmation?[cancel]:[]),yes];
          const current=focused.indexOf(document.activeElement);
          if(e.shiftKey && current<=0){e.preventDefault();focused.at(-1).focus();}
          else if(!e.shiftKey && current===focused.length-1){e.preventDefault();focused[0].focus();}
        }
      }
      document.addEventListener('keydown',keyHandler,true);
      cancel.addEventListener('click',()=>finish(false));
      yes.addEventListener('click',()=>finish(true));
      // Do not cancel destructive confirmation via backdrop, require explicit answer.
      yes.focus();
    });
  }
  function enqueueDialog(raw,confirmation) {
    const next=lastModal.catch(()=>{}).then(()=>dialog(raw,confirmation));
    lastModal=next;
    return next;
  }
  function flash(raw) {
    try {sessionStorage.setItem(FLASH,JSON.stringify(options(raw)));}catch(_){}
  }
  window.KAPNotify={toast,confirm:o=>enqueueDialog(o,true),alert:o=>enqueueDialog(o,false),flash};
  const consumeFlash=()=>{
    try {
      const str=sessionStorage.getItem(FLASH);
      if(str){sessionStorage.removeItem(FLASH);toast(JSON.parse(str));}
    }catch(_){}
  };
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',consumeFlash,{once:true});
  else consumeFlash();
})();
