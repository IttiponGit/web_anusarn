(function () {
  const isSubPage = window.location.pathname.includes('/information/pages/');
  const ROOT = isSubPage ? '..' : '.';
  const DATA_ROOT = `${ROOT}/data`;
  const page = document.body.dataset.page || 'home';

  const navItems = [
    { key: 'home', label: 'หน้าแรก', icon: 'bi-house-door-fill', href: isSubPage ? '../index.html' : 'index.html' },
    { key: 'basic', label: 'ข้อมูลพื้นฐานโรงเรียน', icon: 'bi-building', href: isSubPage ? 'basic.html' : 'pages/basic.html' },
    { key: 'direction', label: 'ทิศทางการศึกษา', icon: 'bi-signpost-split-fill', href: isSubPage ? 'direction.html' : 'pages/direction.html' },
    { key: 'performance', label: 'ผลการดำเนินงาน / SAR', icon: 'bi-bar-chart-line-fill', href: isSubPage ? 'performance.html' : 'pages/performance.html' },
    { key: 'personnel', label: 'ข้อมูลบุคลากร', icon: 'bi-person-badge-fill', href: isSubPage ? 'personnel.html' : 'pages/personnel.html' },
    { key: 'students', label: 'ข้อมูลนักเรียน', icon: 'bi-people-fill', href: isSubPage ? 'students.html' : 'pages/students.html' },
    { key: 'budget', label: 'งบประมาณ', icon: 'bi-wallet2', href: isSubPage ? 'budget.html' : 'pages/budget.html' },
    { key: 'awards', label: 'ผลงานและรางวัล', icon: 'bi-award-fill', href: isSubPage ? 'awards.html' : 'pages/awards.html' },
    { key: 'downloads', label: 'ดาวน์โหลดเอกสาร', icon: 'bi-download', href: isSubPage ? 'downloads.html' : 'pages/downloads.html' }
  ];

  const pageMeta = {
    home: {
      title: 'หน้าแรก',
      subtitle: 'ภาพรวมสำคัญของโรงเรียนในรูปแบบ dashboard',
      heroTitle: 'ระบบสารสนเทศโรงเรียนโสตศึกษาอนุสารสุนทร',
      heroSubtitle: 'สรุปข้อมูลสำคัญแบบอ่านง่าย ใช้ Bootstrap 5, Sidebar, Card และกราฟสำหรับการติดตามภาพรวมสถานศึกษา',
      breadcrumb: ['หน้าแรก']
    },
    basic: {
      title: 'ข้อมูลพื้นฐานโรงเรียน',
      subtitle: 'ข้อมูลอ้างอิงหลักของสถานศึกษา',
      heroTitle: 'ข้อมูลพื้นฐานโรงเรียน',
      heroSubtitle: 'รายละเอียดสำคัญของโรงเรียนที่จัดวางในรูปแบบการ์ดและตาราง',
      breadcrumb: ['หน้าแรก', 'ข้อมูลพื้นฐานโรงเรียน']
    },
    direction: {
      title: 'ทิศทางการศึกษา',
      subtitle: 'วิสัยทัศน์ พันธกิจ และกลยุทธ์',
      heroTitle: 'ทิศทางการศึกษา',
      heroSubtitle: 'กรอบการพัฒนาของโรงเรียนที่คงความเป็นทางการและอ่านง่าย',
      breadcrumb: ['หน้าแรก', 'ทิศทางการศึกษา']
    },
    performance: {
      title: 'ผลการดำเนินงาน / SAR',
      subtitle: 'ตัวชี้วัดการประเมินตนเองของสถานศึกษา',
      heroTitle: 'ผลการดำเนินงาน / SAR',
      heroSubtitle: 'สรุปผลการดำเนินงานพร้อมกราฟและสถานะเป้าหมาย',
      breadcrumb: ['หน้าแรก', 'ผลการดำเนินงาน / SAR']
    },
    personnel: {
      title: 'ข้อมูลบุคลากร',
      subtitle: 'สถิติและโครงสร้างบุคลากรของโรงเรียน',
      heroTitle: 'ข้อมูลบุคลากร',
      heroSubtitle: 'จัดแสดงภาพรวมบุคลากรและสัดส่วนตามตำแหน่ง',
      breadcrumb: ['หน้าแรก', 'ข้อมูลบุคลากร']
    },
    students: {
      title: 'ข้อมูลนักเรียน',
      subtitle: 'ภาพรวมจำนวนและสัดส่วนนักเรียน',
      heroTitle: 'ข้อมูลนักเรียน',
      heroSubtitle: 'แสดงเฉพาะสถิติภาพรวม ไม่มีข้อมูลส่วนบุคคล',
      breadcrumb: ['หน้าแรก', 'ข้อมูลนักเรียน']
    },
    budget: {
      title: 'งบประมาณ',
      subtitle: 'ข้อมูลการจัดสรรงบประมาณของโรงเรียน',
      heroTitle: 'งบประมาณ',
      heroSubtitle: 'มุมมองการใช้ทรัพยากรในปีงบประมาณปัจจุบัน',
      breadcrumb: ['หน้าแรก', 'งบประมาณ']
    },
    awards: {
      title: 'ผลงานและรางวัล',
      subtitle: 'เกียรติยศและผลงานเด่นของโรงเรียน',
      heroTitle: 'ผลงานและรางวัล',
      heroSubtitle: 'รวบรวมรางวัลและผลงานในรูปแบบการ์ด',
      breadcrumb: ['หน้าแรก', 'ผลงานและรางวัล']
    },
    downloads: {
      title: 'ดาวน์โหลดเอกสาร',
      subtitle: 'รายการไฟล์และเอกสารสำคัญ',
      heroTitle: 'ดาวน์โหลดเอกสาร',
      heroSubtitle: 'เอกสารสำหรับใช้งานทั่วไปในรูปแบบตารางที่อ่านง่าย',
      breadcrumb: ['หน้าแรก', 'ดาวน์โหลดเอกสาร']
    }
  };

  const dataFilesByPage = {
    home: ['school', 'personnel', 'students'],
    basic: ['school'],
    direction: ['school'],
    performance: ['school'],
    personnel: ['school', 'personnel'],
    students: ['school', 'students'],
    budget: ['school', 'budget'],
    awards: ['school', 'awards'],
    downloads: ['school', 'downloads']
  };

  function byId(id) {
    return document.getElementById(id);
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatCurrency(value) {
    return Number(value).toLocaleString('th-TH');
  }

  function formatPercent(value) {
    return Number(value).toFixed(2).replace(/\.00$/, '');
  }

  function clampPercent(value) {
    const number = Number(value);
    if (Number.isNaN(number)) return 0;
    return Math.max(0, Math.min(100, number));
  }

  function safeLink(value) {
    const link = String(value || '#').trim();
    if (link === '#' || link.startsWith('./') || link.startsWith('../') || link.startsWith('/')) return link;
    if (/^https?:\/\//i.test(link)) return link;
    return '#';
  }

  function formatLevelRange(value) {
    return String(value || '-').replace(/\s*ถึง\s*/, ' - ');
  }

  function statusBadge(status) {
    const normalized = String(status || '').trim();
    if (normalized.includes('ผ่าน') || normalized.includes('ดี') || normalized.includes('สำเร็จ')) return 'badge-soft-success';
    if (normalized.includes('พัฒนา') || normalized.includes('ปรับปรุง')) return 'badge-soft-warning';
    if (normalized.includes('เสี่ยง') || normalized.includes('ต่ำ')) return 'badge-soft-danger';
    return 'badge-soft-primary';
  }

  async function loadJson(key) {
    const response = await fetch(`${DATA_ROOT}/${key}.json`);
    if (!response.ok) {
      throw new Error(`ไม่สามารถโหลดไฟล์ ${key}.json`);
    }
    return response.json();
  }

  async function loadPageData() {
    const keys = dataFilesByPage[page] || dataFilesByPage.home;
    const entries = await Promise.all(keys.map(async (key) => [key, await loadJson(key)]));
    return Object.fromEntries(entries);
  }

  function buildMenus() {
    const menuMarkup = navItems
      .map((item) => `<li class="nav-item"><a class="nav-link ${item.key === page ? 'active' : ''}" href="${escapeHtml(item.href)}"><i class="bi ${escapeHtml(item.icon)}"></i><span>${escapeHtml(item.label)}</span></a></li>`)
      .join('');

    document.querySelectorAll('.js-sidebar-menu, .js-offcanvas-menu').forEach((target) => {
      target.innerHTML = menuMarkup;
    });

    const offcanvasEl = byId('mobileSidebar');
    if (offcanvasEl && window.bootstrap) {
      const instance = window.bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
      offcanvasEl.querySelectorAll('.js-offcanvas-menu .nav-link').forEach((link) => {
        link.addEventListener('click', () => instance.hide());
      });
    }

    const quickMenu = byId('quickMenu');
    if (quickMenu) {
      quickMenu.innerHTML = navItems
        .slice(1)
        .map((item) => `<a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="${escapeHtml(item.href)}"><i class="bi ${escapeHtml(item.icon)} text-primary"></i><span>${escapeHtml(item.label)}</span></a>`)
        .join('');
    }
  }

  function fillSchoolIdentity(school) {
    document.querySelectorAll('[data-school-name]').forEach((el) => {
      el.textContent = school.schoolName;
    });
    document.querySelectorAll('[data-academic-year]').forEach((el) => {
      el.textContent = school.academicYear;
    });
    const y = byId('copyrightYear');
    if (y) y.textContent = new Date().getFullYear();
  }

  function setText(id, value) {
    const el = byId(id);
    if (el) el.textContent = value;
  }

  function setSummaryValues(values) {
    const targets = document.querySelectorAll('.summary-chip strong');
    values.forEach((value, index) => {
      if (targets[index]) targets[index].textContent = value;
    });
  }

  function setMetricValues(values) {
    const targets = document.querySelectorAll('.metric-card .metric-value');
    values.forEach((value, index) => {
      if (targets[index]) targets[index].textContent = value;
    });
  }

  function applyPageChrome(school) {
    const meta = pageMeta[page] || pageMeta.home;
    document.title = `${meta.title} | ${school.schoolName}`;

    setText('pageTitle', meta.title);
    setText('pageSubtitle', `${meta.subtitle} • ปีการศึกษา ${school.academicYear}`);
    setText('heroTitle', meta.heroTitle);
    setText('heroSubtitle', meta.heroSubtitle);
    setText('heroYear', school.academicYear);
    setText('heroSchoolName', school.schoolName);

    const breadcrumb = byId('breadcrumbTrail');
    if (breadcrumb) {
      const items = meta.breadcrumb || [];
      breadcrumb.innerHTML = items
        .map((item, index) => {
          const safeItem = escapeHtml(item);
          if (index === items.length - 1) {
            return `<li class="breadcrumb-item active" aria-current="page">${safeItem}</li>`;
          }
          return `<li class="breadcrumb-item"><a href="${index === 0 ? (isSubPage ? '../index.html' : 'index.html') : '#'}">${safeItem}</a></li>`;
        })
        .join('');
    }
  }

  function renderHome(data) {
    setSummaryValues([
      `${data.students.totalStudents} คน`,
      `${data.personnel.totalPersonnel} คน`,
      `${data.school.classroomCount} ห้อง`,
      formatLevelRange(data.school.levelRange)
    ]);
    setText('sumStudents', `${data.students.totalStudents}`);
    setText('sumPersonnel', `${data.personnel.totalPersonnel}`);
    setText('sumClassrooms', `${data.school.classroomCount}`);
    setText('sumYear', data.school.academicYear);
    setText('levelRange', data.school.levelRange);
    setText('identityText', data.school.identity);
    setText('visionText', data.school.vision);
    setText('homeIdentityLead', data.school.identity);
    setText('homeVisionLead', data.school.vision);
    setText('homeLevelLead', data.school.levelRange);
  }

  function renderBasic(data) {
    setSummaryValues([
      data.school.academicYear,
      data.school.levelRange,
      `${data.school.classroomCount} ห้อง`,
      `${data.school.studentCount} คน`
    ]);
    setMetricValues([
      data.school.schoolName,
      formatLevelRange(data.school.levelRange),
      `${data.school.studentCount} คน`,
      data.school.academicYear
    ]);

    const tbody = byId('basicTableBody');
    if (!tbody) return;
    const rows = [
      ['ชื่อสถานศึกษา', data.school.schoolName],
      ['ปีการศึกษา', `<span class="badge badge-soft-primary">${escapeHtml(data.school.academicYear)}</span>`],
      ['ระดับที่เปิดสอน', data.school.levelRange],
      ['จำนวนห้องเรียน', `${data.school.classroomCount} ห้อง`],
      ['จำนวนนักเรียน', `${data.school.studentCount} คน`],
      ['จำนวนบุคลากร', `${data.school.personnelCount} คน`],
      ['อัตลักษณ์', data.school.identity],
      ['วิสัยทัศน์', data.school.vision]
    ];
    tbody.innerHTML = rows
      .map((row) => `<tr><th scope="row" class="text-nowrap">${escapeHtml(row[0])}</th><td>${row[0] === 'ปีการศึกษา' ? row[1] : escapeHtml(row[1])}</td></tr>`)
      .join('');
  }

  function renderDirection(data) {
    const missions = byId('missionList');
    const strategies = byId('strategyList');
    if (missions) {
      missions.innerHTML = data.school.missions
        .map((item, index) => `<li class="list-group-item d-flex align-items-start gap-3"><span class="icon-circle icon-blue flex-shrink-0"><i class="bi bi-check2-circle"></i></span><div><div class="item-title">พันธกิจ ${index + 1}</div><div class="item-desc">${escapeHtml(item)}</div></div></li>`)
        .join('');
    }
    if (strategies) {
      strategies.innerHTML = data.school.strategies
        .map((item, index) => `<li class="list-group-item d-flex align-items-start gap-3"><span class="icon-circle icon-purple flex-shrink-0"><i class="bi bi-graph-up-arrow"></i></span><div><div class="item-title">${escapeHtml(item)}</div><div class="item-desc">ยุทธศาสตร์ลำดับที่ ${index + 1} เพื่อขับเคลื่อนคุณภาพการศึกษา</div></div></li>`)
        .join('');
    }
  }

  function renderPerformance(data) {
    const passedCount = data.school.sarIndicators.filter((item) => item.status.includes('ผ่าน')).length;
    const developingCount = data.school.sarIndicators.filter((item) => item.status.includes('พัฒนา')).length;
    setSummaryValues([
      `${data.school.sarIndicators.length} ตัวชี้วัด`,
      `${passedCount} รายการ`,
      `${developingCount} รายการ`,
      data.school.academicYear
    ]);

    const tbody = byId('sarTableBody');
    const cards = byId('sarCards');
    if (tbody) {
      tbody.innerHTML = data.school.sarIndicators
        .map((item) => `<tr><td>${escapeHtml(item.name)}</td><td><span class="badge badge-soft-primary">${escapeHtml(item.target)}%</span></td><td><span class="badge badge-soft-success">${escapeHtml(item.actual)}%</span></td><td><span class="badge ${statusBadge(item.status)}">${escapeHtml(item.status)}</span></td></tr>`)
        .join('');
    }
    if (cards) {
      const cardIcons = ['bi-award', 'bi-clipboard-data', 'bi-people', 'bi-gear'];
      cards.innerHTML = data.school.sarIndicators
        .map((item, index) => `<div class="col-12 col-md-6 col-xxl-3"><div class="performance-info-card performance-info-card-${index % 4} h-100"><div class="d-flex align-items-center justify-content-between mb-3"><span class="performance-info-icon"><i class="bi ${cardIcons[index % cardIcons.length]}"></i></span><span class="badge badge-soft-${index === 0 ? 'success' : index === 3 ? 'warning' : 'primary'}">SAR</span></div><div class="performance-info-label">${escapeHtml(item.name)}</div><div class="performance-info-value">${escapeHtml(item.actual)}%</div><div class="performance-info-note">เป้าหมาย ${escapeHtml(item.target)}% | สถานะ ${escapeHtml(item.status)}</div></div></div>`)
        .join('');
    }
  }

  function renderPersonnel(data) {
    setText('personnelTotal', `${data.personnel.totalPersonnel}`);
    setText('teacherCount', `${data.personnel.teacherCount}`);
    setText('supportCount', `${data.personnel.supportCount}`);
    setSummaryValues([
      `${data.personnel.totalPersonnel} คน`,
      `${data.personnel.teacherCount} คน`,
      `${data.personnel.supportCount} คน`,
      `${data.personnel.byPosition.find((item) => item.position === 'ผู้บริหาร')?.count || 0} คน`
    ]);

    const tbody = byId('personnelTableBody');
    if (!tbody) return;
    tbody.innerHTML = data.personnel.byPosition
      .map((item) => `<tr><td>${escapeHtml(item.position)}</td><td>${escapeHtml(item.count)}</td><td>${formatPercent(item.percent)}%</td><td><span class="badge badge-soft-primary">${escapeHtml(item.count)} คน</span></td></tr>`)
      .join('');
  }

  function renderStudents(data) {
    setText('studentsTotal', `${data.students.totalStudents}`);
    setText('maleTotal', `${data.students.byGender.male}`);
    setText('femaleTotal', `${data.students.byGender.female}`);
    setSummaryValues([
      `${data.students.totalStudents} คน`,
      `${data.students.byGender.male} คน`,
      `${data.students.byGender.female} คน`,
      `${data.students.byLevel.reduce((total, item) => total + Number(item.classrooms), 0)} ห้อง`
    ]);

    const tbody = byId('studentsTableBody');
    if (!tbody) return;
    tbody.innerHTML = data.students.byLevel
      .map((item) => `<tr><td>${escapeHtml(item.level)}</td><td>${escapeHtml(item.count)}</td><td>${escapeHtml(item.classrooms)}</td><td><span class="badge badge-soft-info">${Math.round((Number(item.count) / Number(data.students.totalStudents)) * 100)}%</span></td></tr>`)
      .join('');
  }

  function renderBudget(data) {
    setText('fiscalYear', data.budget.fiscalYear);
    setText('budgetTotal', `${formatCurrency(data.budget.total)} บาท`);
    setSummaryValues([
      data.budget.fiscalYear,
      `${formatCurrency(data.budget.total)} บาท`,
      `${data.budget.items.length} หมวด`,
      'JSON กลาง'
    ]);
    setMetricValues([
      data.budget.fiscalYear,
      `${formatCurrency(data.budget.total)} บาท`,
      data.budget.items.length.toString()
    ]);

    const tbody = byId('budgetTableBody');
    if (!tbody) return;
    tbody.innerHTML = data.budget.items
      .map((item) => {
        const percent = clampPercent(item.percent);
        return `<tr><td>${escapeHtml(item.category)}</td><td>${formatCurrency(item.amount)}</td><td><span class="badge badge-soft-warning">${formatPercent(percent)}%</span></td><td><div class="progress" style="height:0.7rem"><div class="progress-bar bg-primary" role="progressbar" style="width:${percent}%" aria-valuenow="${percent}" aria-valuemin="0" aria-valuemax="100"></div></div></td></tr>`;
      })
      .join('');
  }

  function renderAwards(data) {
    setSummaryValues([
      `${data.awards.items.length} รายการ`,
      data.awards.items.map((item) => Number(item.year)).sort((a, b) => b - a)[0]?.toString() || '-',
      'ภายนอก',
      'ผลงาน'
    ]);

    const container = byId('awardsCards');
    if (!container) return;
    const palette = ['metric-blue', 'metric-green', 'metric-yellow', 'metric-purple'];
    const icons = ['bi-award-fill', 'bi-trophy-fill', 'bi-stars', 'bi-bookmark-star-fill'];
    container.innerHTML = data.awards.items
      .map((item, index) => `<div class="col-12 col-md-6 col-xl-4"><div class="metric-card ${palette[index % palette.length]} h-100"><div class="card-body"><div class="d-flex align-items-center justify-content-between mb-3"><span class="metric-icon"><i class="bi ${icons[index % icons.length]}"></i></span><span class="badge badge-soft-success">${escapeHtml(item.year)}</span></div><h3 class="h5 mb-2">${escapeHtml(item.title)}</h3><p class="mb-2 metric-note">${escapeHtml(item.organization)}</p><p class="mb-0">${escapeHtml(item.summary)}</p></div></div></div>`)
      .join('');
  }

  function renderDownloads(data) {
    setSummaryValues([
      `${data.downloads.items.length} รายการ`,
      [...new Set(data.downloads.items.map((item) => item.type))].join(' / '),
      data.downloads.items.map((item) => item.updatedAt).sort().reverse()[0] || '-',
      'สาธารณะ'
    ]);

    const tbody = byId('downloadsTableBody');
    if (!tbody) return;
    tbody.innerHTML = data.downloads.items
      .map((item) => `<tr><td>${escapeHtml(item.name)}</td><td><span class="badge badge-soft-primary">${escapeHtml(item.type)}</span></td><td>${escapeHtml(item.updatedAt)}</td><td><a href="${escapeHtml(safeLink(item.link))}" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-download me-1"></i>ดาวน์โหลด</a></td></tr>`)
      .join('');
  }

  async function init() {
    try {
      buildMenus();
      const data = await loadPageData();
      fillSchoolIdentity(data.school);
      applyPageChrome(data.school);

      if (page === 'home') renderHome(data);
      if (page === 'basic') renderBasic(data);
      if (page === 'direction') renderDirection(data);
      if (page === 'performance') renderPerformance(data);
      if (page === 'personnel') renderPersonnel(data);
      if (page === 'students') renderStudents(data);
      if (page === 'budget') renderBudget(data);
      if (page === 'awards') renderAwards(data);
      if (page === 'downloads') renderDownloads(data);

      document.dispatchEvent(new CustomEvent('information:dataReady', { detail: data }));
    } catch (error) {
      const box = byId('errorBox');
      if (box) {
        box.classList.remove('d-none');
        box.textContent = error.message;
      }
    }
  }

  document.addEventListener('DOMContentLoaded', init);
})();
