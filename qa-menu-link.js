/**
 * โรงเรียนโสตศึกษาอนุสารสุนทร
 * เพิ่มเมนู "ระบบประกันคุณภาพ" ไปยัง /qa/
 *
 * วิธีใช้ที่แนะนำ:
 * - นำโค้ดนี้ไปวางท้ายไฟล์ JavaScript หลักของเว็บไซต์
 *   หรือบันทึกเป็น qa-menu-link.js แล้ว include ก่อน </body>
 *
 * จุดเด่น:
 * - ไม่ต้องรู้ชื่อ class ของเมนูเดิม
 * - clone เมนู "ติดต่อโรงเรียน" เพื่อให้ได้ style/responsive เหมือนเดิม
 * - รองรับกรณีมีเมนู Desktop และ Mobile ซ้ำกัน
 */
(function () {
  'use strict';

  function addQaMenuLinks() {
    var links = document.querySelectorAll('a');
    var targets = [];
    var i;

    /* ป้องกันการเพิ่มซ้ำ */
    for (i = 0; i < links.length; i++) {
      if (
        links[i].getAttribute('data-qa-menu-link') === '1' ||
        links[i].getAttribute('href') === '/qa/' ||
        links[i].getAttribute('href') === '/qa'
      ) {
        return;
      }
    }

    /* หา "ติดต่อโรงเรียน" ทุกชุด เช่น Desktop / Mobile */
    for (i = 0; i < links.length; i++) {
      var text = (links[i].textContent || '').replace(/\s+/g, ' ').trim();
      if (text === 'ติดต่อโรงเรียน') {
        targets.push(links[i]);
      }
    }

    for (i = 0; i < targets.length; i++) {
      var contactLink = targets[i];
      var qaLink = contactLink.cloneNode(true);

      qaLink.setAttribute('href', '/qa/');
      qaLink.setAttribute('data-qa-menu-link', '1');
      qaLink.setAttribute('title', 'ระบบประกันคุณภาพสถานศึกษา');
      qaLink.removeAttribute('aria-current');

      /* เปลี่ยนเฉพาะข้อความ แต่คง element/class/icon wrapper เดิม */
      var textWasChanged = false;
      var childNodes = qaLink.childNodes;
      var j;

      for (j = 0; j < childNodes.length; j++) {
        if (childNodes[j].nodeType === 3 && childNodes[j].nodeValue.trim() !== '') {
          childNodes[j].nodeValue = ' ระบบประกันคุณภาพ ';
          textWasChanged = true;
        }
      }

      if (!textWasChanged) {
        /* กรณีข้อความอยู่ใน span */
        var spans = qaLink.querySelectorAll('span');
        for (j = spans.length - 1; j >= 0; j--) {
          if ((spans[j].textContent || '').trim() === 'ติดต่อโรงเรียน') {
            spans[j].textContent = 'ระบบประกันคุณภาพ';
            textWasChanged = true;
            break;
          }
        }
      }

      if (!textWasChanged) {
        qaLink.textContent = 'ระบบประกันคุณภาพ';
      }

      /* วางก่อนเมนูติดต่อโรงเรียน */
      contactLink.parentNode.insertBefore(qaLink, contactLink);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addQaMenuLinks);
  } else {
    addQaMenuLinks();
  }
})();
