(function () {
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });
  }
})();

/* ============================================================
   QA v1.4.7.1 — Thai Buddhist Calendar Navigation
   แปลง input[type=date] เป็นปฏิทินภาษาไทย โดยคงค่า ISO YYYY-MM-DD
   ไว้ใน input เดิม เพื่อไม่กระทบ PHP/ฐานข้อมูล
   ============================================================ */
(function () {
  var thaiMonths = [
    'มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
    'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'
  ];
  var thaiWeekdays = ['อา','จ','อ','พ','พฤ','ศ','ส'];
  var activePicker = null;

  function pad2(n) {
    return n < 10 ? '0' + n : String(n);
  }

  function parseIso(value) {
    if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
    var parts = value.split('-');
    var y = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10) - 1;
    var d = parseInt(parts[2], 10);
    var date = new Date(y, m, d, 12, 0, 0, 0);
    if (date.getFullYear() !== y || date.getMonth() !== m || date.getDate() !== d) return null;
    return date;
  }

  function toIso(date) {
    return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
  }

  function thaiDateText(date) {
    if (!date) return 'เลือกวันที่';
    return date.getDate() + ' ' + thaiMonths[date.getMonth()] + ' ' + (date.getFullYear() + 543);
  }

  function sameDate(a, b) {
    return !!a && !!b &&
      a.getFullYear() === b.getFullYear() &&
      a.getMonth() === b.getMonth() &&
      a.getDate() === b.getDate();
  }

  function fireChange(input) {
    var ev;
    if (typeof Event === 'function') {
      ev = new Event('change', {bubbles:true});
    } else {
      ev = document.createEvent('HTMLEvents');
      ev.initEvent('change', true, false);
    }
    input.dispatchEvent(ev);
  }

  function closePicker() {
    if (!activePicker) return;
    if (activePicker.el && activePicker.el.parentNode) {
      activePicker.el.parentNode.removeChild(activePicker.el);
    }
    if (activePicker.trigger) activePicker.trigger.setAttribute('aria-expanded', 'false');
    activePicker = null;
  }

  function isOutsideRange(date, minDate, maxDate) {
    var time = date.getTime();
    if (minDate && time < minDate.getTime()) return true;
    if (maxDate && time > maxDate.getTime()) return true;
    return false;
  }

  function positionPicker(picker, trigger) {
    var rect = trigger.getBoundingClientRect();
    var scrollX = window.pageXOffset || document.documentElement.scrollLeft || 0;
    var scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
    var width = Math.min(330, Math.max(286, rect.width));
    picker.style.width = width + 'px';

    var left = rect.left + scrollX;
    var top = rect.bottom + scrollY + 6;
    var viewportRight = scrollX + document.documentElement.clientWidth;
    if (left + width > viewportRight - 10) left = viewportRight - width - 10;
    if (left < scrollX + 10) left = scrollX + 10;

    picker.style.left = left + 'px';
    picker.style.top = top + 'px';
  }

  function openPicker(input, trigger, displayText) {
    if (input.disabled || input.readOnly) return;
    closePicker();

    var selected = parseIso(input.value);
    var now = new Date();
    var minDate = parseIso(input.getAttribute('min'));
    var maxDate = parseIso(input.getAttribute('max'));
    var viewDate = selected ? new Date(selected.getTime()) : new Date(now.getFullYear(), now.getMonth(), 1, 12);

    /* หากยังไม่มีวันที่ที่เลือก และช่วง min/max ไม่ครอบคลุมเดือนปัจจุบัน
       ให้เปิดปฏิทินที่เดือนแรก/สุดท้ายที่เลือกได้แทนการแสดงเดือนที่ทุกวันถูกปิด */
    if (!selected && minDate) {
      var minMonthStart = new Date(minDate.getFullYear(), minDate.getMonth(), 1, 12);
      if (viewDate.getTime() < minMonthStart.getTime()) viewDate = minMonthStart;
    }
    if (!selected && maxDate) {
      var maxMonthStart = new Date(maxDate.getFullYear(), maxDate.getMonth(), 1, 12);
      if (viewDate.getTime() > maxMonthStart.getTime()) viewDate = maxMonthStart;
    }

    var picker = document.createElement('div');
    picker.className = 'qa-thai-calendar';
    picker.setAttribute('role', 'dialog');
    picker.setAttribute('aria-label', 'ปฏิทินเลือกวันที่');

    activePicker = {el:picker, trigger:trigger, input:input};
    trigger.setAttribute('aria-expanded', 'true');

    function render() {
      while (picker.firstChild) picker.removeChild(picker.firstChild);

      var header = document.createElement('div');
      header.className = 'qa-thai-calendar-header';

      var prev = document.createElement('button');
      prev.type = 'button';
      prev.className = 'qa-thai-calendar-nav';
      prev.setAttribute('aria-label', 'เดือนก่อนหน้า');
      prev.innerHTML = '&#8249;';

      var selectors = document.createElement('div');
      selectors.className = 'qa-thai-calendar-selectors';

      var monthSelect = document.createElement('select');
      monthSelect.className = 'qa-thai-calendar-select qa-thai-calendar-month';
      monthSelect.setAttribute('aria-label', 'เลือกเดือน');

      var yearSelect = document.createElement('select');
      yearSelect.className = 'qa-thai-calendar-select qa-thai-calendar-year';
      yearSelect.setAttribute('aria-label', 'เลือกปี พ.ศ.');

      var currentYear = now.getFullYear();
      var minYear = minDate ? minDate.getFullYear() : currentYear - 20;
      var maxYear = maxDate ? maxDate.getFullYear() : currentYear + 20;

      /* ค่าใน input ที่มีอยู่ต้องปรากฏในรายการปีเสมอ แม้จะอยู่นอกช่วงมาตรฐาน */
      if (selected && selected.getFullYear() < minYear) minYear = selected.getFullYear();
      if (selected && selected.getFullYear() > maxYear) maxYear = selected.getFullYear();
      if (viewDate.getFullYear() < minYear) minYear = viewDate.getFullYear();
      if (viewDate.getFullYear() > maxYear) maxYear = viewDate.getFullYear();

      /* ป้องกัน min/max ที่ตั้งค่าผิดจนไม่มีรายการปี */
      if (minYear > maxYear) {
        var swapYear = minYear;
        minYear = maxYear;
        maxYear = swapYear;
      }

      function monthHasSelectableDay(year, month) {
        var firstDay = new Date(year, month, 1, 12);
        var lastDay = new Date(year, month + 1, 0, 12);
        if (minDate && lastDay.getTime() < minDate.getTime()) return false;
        if (maxDate && firstDay.getTime() > maxDate.getTime()) return false;
        return true;
      }

      function clampMonthForYear(year, month) {
        if (monthHasSelectableDay(year, month)) return month;
        var m;
        for (m = 0; m < 12; m++) {
          if (monthHasSelectableDay(year, m)) return m;
        }
        return month;
      }

      var viewYear = viewDate.getFullYear();
      var viewMonth = viewDate.getMonth();

      for (var mi = 0; mi < thaiMonths.length; mi++) {
        var monthOption = document.createElement('option');
        monthOption.value = String(mi);
        monthOption.textContent = thaiMonths[mi];
        monthOption.disabled = !monthHasSelectableDay(viewYear, mi);
        if (mi === viewMonth) monthOption.selected = true;
        monthSelect.appendChild(monthOption);
      }

      for (var yi = minYear; yi <= maxYear; yi++) {
        var yearOption = document.createElement('option');
        yearOption.value = String(yi);
        yearOption.textContent = 'พ.ศ. ' + String(yi + 543);
        if (yi === viewYear) yearOption.selected = true;
        yearSelect.appendChild(yearOption);
      }

      var next = document.createElement('button');
      next.type = 'button';
      next.className = 'qa-thai-calendar-nav';
      next.setAttribute('aria-label', 'เดือนถัดไป');
      next.innerHTML = '&#8250;';

      var prevMonthDate = new Date(viewYear, viewMonth - 1, 1, 12);
      var nextMonthDate = new Date(viewYear, viewMonth + 1, 1, 12);
      prev.disabled = !monthHasSelectableDay(prevMonthDate.getFullYear(), prevMonthDate.getMonth());
      next.disabled = !monthHasSelectableDay(nextMonthDate.getFullYear(), nextMonthDate.getMonth());

      prev.addEventListener('click', function () {
        if (prev.disabled) return;
        viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1, 12);
        render();
      });
      next.addEventListener('click', function () {
        if (next.disabled) return;
        viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1, 12);
        render();
      });

      monthSelect.addEventListener('change', function () {
        var month = parseInt(monthSelect.value, 10);
        viewDate = new Date(viewDate.getFullYear(), month, 1, 12);
        render();
      });

      yearSelect.addEventListener('change', function () {
        var year = parseInt(yearSelect.value, 10);
        var month = clampMonthForYear(year, viewDate.getMonth());
        viewDate = new Date(year, month, 1, 12);
        render();
      });

      selectors.appendChild(monthSelect);
      selectors.appendChild(yearSelect);
      header.appendChild(prev);
      header.appendChild(selectors);
      header.appendChild(next);
      picker.appendChild(header);

      var weekdays = document.createElement('div');
      weekdays.className = 'qa-thai-calendar-weekdays';
      for (var w = 0; w < thaiWeekdays.length; w++) {
        var wd = document.createElement('span');
        wd.textContent = thaiWeekdays[w];
        weekdays.appendChild(wd);
      }
      picker.appendChild(weekdays);

      var grid = document.createElement('div');
      grid.className = 'qa-thai-calendar-grid';
      var first = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1, 12);
      var firstDay = first.getDay();
      var daysInMonth = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0, 12).getDate();

      for (var blank = 0; blank < firstDay; blank++) {
        var spacer = document.createElement('span');
        spacer.className = 'qa-thai-calendar-blank';
        grid.appendChild(spacer);
      }

      for (var day = 1; day <= daysInMonth; day++) {
        (function (dayNo) {
          var dayDate = new Date(viewDate.getFullYear(), viewDate.getMonth(), dayNo, 12);
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'qa-thai-calendar-day';
          btn.textContent = String(dayNo);

          if (sameDate(dayDate, selected)) btn.className += ' selected';
          if (sameDate(dayDate, now)) btn.className += ' today';
          if (isOutsideRange(dayDate, minDate, maxDate)) {
            btn.disabled = true;
            btn.className += ' disabled';
          }

          btn.addEventListener('click', function () {
            selected = dayDate;
            input.value = toIso(dayDate);
            displayText.textContent = thaiDateText(dayDate);
            fireChange(input);
            closePicker();
          });
          grid.appendChild(btn);
        })(day);
      }
      picker.appendChild(grid);

      var footer = document.createElement('div');
      footer.className = 'qa-thai-calendar-footer';

      var todayBtn = document.createElement('button');
      todayBtn.type = 'button';
      todayBtn.className = 'qa-thai-calendar-text-btn';
      todayBtn.textContent = 'วันนี้';
      todayBtn.disabled = isOutsideRange(new Date(now.getFullYear(), now.getMonth(), now.getDate(), 12), minDate, maxDate);
      todayBtn.addEventListener('click', function () {
        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 12);
        input.value = toIso(today);
        displayText.textContent = thaiDateText(today);
        fireChange(input);
        closePicker();
      });
      footer.appendChild(todayBtn);

      if (!input.hasAttribute('required')) {
        var clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'qa-thai-calendar-text-btn muted';
        clearBtn.textContent = 'ล้างวันที่';
        clearBtn.addEventListener('click', function () {
          input.value = '';
          displayText.textContent = 'เลือกวันที่';
          fireChange(input);
          closePicker();
        });
        footer.appendChild(clearBtn);
      }

      picker.appendChild(footer);
      positionPicker(picker, trigger);
    }

    document.body.appendChild(picker);
    render();
  }

  function enhanceDateInput(input) {
    if (!input || input.getAttribute('data-thai-calendar') === '1') return;
    input.setAttribute('data-thai-calendar', '1');

    var wrapper = document.createElement('div');
    wrapper.className = 'qa-thai-date-field';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    input.className += (input.className ? ' ' : '') + 'qa-thai-date-native';
    input.setAttribute('tabindex', '-1');

    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'qa-thai-date-trigger';
    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.setAttribute('aria-expanded', 'false');

    var text = document.createElement('span');
    text.className = 'qa-thai-date-text';
    text.textContent = thaiDateText(parseIso(input.value));

    var icon = document.createElement('span');
    icon.className = 'qa-thai-date-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = '&#128197;';

    trigger.appendChild(text);
    trigger.appendChild(icon);
    wrapper.appendChild(trigger);

    if (input.disabled || input.readOnly) {
      trigger.disabled = true;
      trigger.className += ' disabled';
    }

    trigger.addEventListener('click', function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      openPicker(input, trigger, text);
    });

    input.addEventListener('change', function () {
      text.textContent = thaiDateText(parseIso(input.value));
    });
  }

  function initThaiCalendars() {
    var inputs = document.querySelectorAll('input[type="date"]');
    for (var i = 0; i < inputs.length; i++) enhanceDateInput(inputs[i]);
  }

  document.addEventListener('click', function (ev) {
    if (!activePicker) return;
    if (activePicker.el.contains(ev.target) || activePicker.trigger.contains(ev.target)) return;
    closePicker();
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' || ev.keyCode === 27) closePicker();
  });

  window.addEventListener('resize', function () {
    if (activePicker) positionPicker(activePicker.el, activePicker.trigger);
  });

  window.addEventListener('scroll', function () {
    if (activePicker) positionPicker(activePicker.el, activePicker.trigger);
  }, true);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initThaiCalendars);
  } else {
    initThaiCalendars();
  }
})();
