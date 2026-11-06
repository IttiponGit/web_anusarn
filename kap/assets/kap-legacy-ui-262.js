/**
 * KAP Phase 2.6.2: display-only enhancement for existing legacy admin pages.
 * - Curriculum rows sorted by BE curriculum year descending, not by Curriculum_id.
 * - Thai visible label prefix "หลักสูตร" -> "หลักสูตรสถานศึกษา".
 * - Existing Cancel and Save controls moved together to bottom-right of edit card.
 * No HTTP requests, SQL, form submission interception, or database writes.
 * Prefer correcting SQL ORDER BY and PHP markup in the original page when source is available.
 */
(() => {
  'use strict';
  const yearFromName = (name) => {
    const m = String(name || '').match(/(?:25|26)\d{2}/);
    return m ? Number(m[0]) : null;
  };
  const norm = (s) => String(s || '').replace(/\s+/g, ' ').trim();

  function sortCurriculumTable(table) {
    const headers = [...table.querySelectorAll('th')].map(th => norm(th.textContent));
    if (!headers.some(x => x.includes('หลักสูตร')) || !headers.some(x => /ช่วงรุ่น|ประเภท|หมวด|รายวิชา/.test(x))) return false;

    let didChange = false;
    for (const tbody of table.tBodies) {
      const rows = [...tbody.children].filter(n => n.tagName === 'TR' && !n.querySelector('th'));
      if (rows.length < 2 || rows.some(r => r.querySelector('[rowspan]'))) continue;
      const indexed = rows.map((r, i) => {
        const firstCell = r.querySelector('td');
        const title = norm(firstCell?.textContent || '');
        return {row: r, index: i, year: title.includes('หลักสูตร') ? yearFromName(title) : null};
      });
      // Do not reorder a table if any row has an unknown curriculum or is a control row.
      if (indexed.some(x => x.year === null)) continue;
      const ordered = [...indexed].sort((a, b) => b.year - a.year || a.index - b.index);
      if (ordered.some((x, i) => x.row !== rows[i])) {
        // Reuse existing DOM rows (and their attached event handlers), no cloning.
        ordered.forEach(x => tbody.appendChild(x.row));
        didChange = true;
      }
    }
    return didChange;
  }

  function renameVisibleLabels(scope) {
    const walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) {
      const t = walker.currentNode;
      const parent = t.parentElement;
      if (parent && !['SCRIPT', 'STYLE', 'TEXTAREA', 'CODE', 'PRE'].includes(parent.tagName) && t.nodeValue.includes('หลักสูตร')) nodes.push(t);
    }
    for (const t of nodes) {
      t.nodeValue = t.nodeValue
        .replace(/หลักสูตรสถานศึกษา\s*(?=(?:25|26)\d{2})/g, 'หลักสูตรสถานศึกษา ')
        .replace(/หลักสูตร(?!สถานศึกษา)\s*(?=(?:25|26)\d{2})/g, 'หลักสูตรสถานศึกษา ')
        .replace(/หลักสูตร(?!สถานศึกษา)/g, 'หลักสูตรสถานศึกษา');
    }
    for (const el of scope.querySelectorAll('[title],[placeholder],[aria-label],input[type="submit"],input[type="button"]')) {
      for (const attr of ['title', 'placeholder', 'aria-label', 'value']) {
        if (!el.hasAttribute(attr)) continue;
        const v = el.getAttribute(attr);
        if (v && v.includes('หลักสูตร')) {
          el.setAttribute(attr, v.replace(/หลักสูตร(?!สถานศึกษา)/g, 'หลักสูตรสถานศึกษา'));
        }
      }
    }
  }

  function gatherControls() {
    return [...document.querySelectorAll('button, a, input[type="submit"], input[type="button"]')];
  }
  function controlText(el) {
    return norm(el.matches('input') ? el.value : el.textContent);
  }
  function groupSaveAndCancel() {
    if (document.querySelector('.kap262-action-group')) return false;
    const controls = gatherControls();
    const saveCandidates = controls.filter(el => /บันทึกการแก้ไข/.test(controlText(el)) && !el.disabled);
    const cancelCandidates = controls.filter(el => /ยกเลิกการแก้ไข/.test(controlText(el)) && !el.disabled);
    if (saveCandidates.length !== 1 || cancelCandidates.length !== 1) return false;
    const save = saveCandidates[0];
    const cancel = cancelCandidates[0];
    const form = save.closest('form');
    if (!form || !form.contains(save) || save === cancel) return false;
    // Never transfer a cancel action from another edit form.
    if (cancel.closest('form') && cancel.closest('form') !== form) return false;
    const heading = [...document.querySelectorAll('h1,h2,h3,h4')].find(el => /แก้ไขทะเบียนรายวิชา|แก้ไขรายวิชา/.test(norm(el.textContent)));
    if (!heading) return false;
    const parentCard = form.closest('.card,.panel,article,section') || form.parentElement;
    if (!parentCard || !parentCard.contains(save)) return false;
    if (cancel.tagName === 'BUTTON') {
      if (cancel.getAttribute('type') === 'submit' && cancel.hasAttribute('formaction')) return false;
      cancel.type = 'button';
    }
    // Insert at the original Save position, maintaining form association.
    const wrapper = document.createElement('div');
    wrapper.className = 'kap262-action-group';
    wrapper.setAttribute('data-kap-patch', '2.6.2');
    save.parentNode.insertBefore(wrapper, save);
    wrapper.append(save, cancel);
    save.classList.add('kap262-save');
    cancel.classList.add('kap262-cancel');
    return true;
  }

  function apply() {
    const current = document.body;
    if (!current) return;
    for (const table of current.querySelectorAll('table')) sortCurriculumTable(table);
    renameVisibleLabels(current);
    groupSaveAndCancel();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', apply, {once: true});
  } else {
    apply();
  }
  // Admin tabs may insert their table when clicked without reloading the page.
  // Only re-run when new nodes are added. Already-sorted tables cause no mutations.
  let pending = false;
  const watch = () => {
    const observer = new MutationObserver((mutations) => {
      if (!mutations.some(m => m.addedNodes.length)) return;
      if (pending) return;
      pending = true;
      requestAnimationFrame(() => { pending = false; apply(); });
    });
    observer.observe(document.body, {subtree: true, childList: true});
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', watch, {once: true});
  } else if (document.body) {
    watch();
  }
})();
