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
    { key: 'downloads', label: 'ดาวน์โหลดเอกสาร', icon: 'bi-download', href: isSubPage ? 'downloads.html' : 'pages/downloads.html' },
    { key: 'contact', label: 'ติดต่อโรงเรียน', icon: 'bi-telephone-fill', href: isSubPage ? 'contact.html' : 'pages/contact.html' }
  ];

  function byId(id) {
    return document.getElementById(id);
  }

  async function loadJson(fileName) {
    const response = await fetch(`${DATA_ROOT}/${fileName}`);
    if (!response.ok) {
      throw new Error(`ไม่สามารถโหลดไฟล์ ${fileName}`);
    }
    return response.json();
  }

  function buildMenus() {
    const menuMarkup = navItems
      .map((item) => `<li class="nav-item"><a class="nav-link ${item.key === page ? 'active' : ''}" href="${item.href}"><i class="bi ${item.icon}"></i><span>${item.label}</span></a></li>`)
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
        .map((item) => `<a class="list-group-item list-group-item-action" href="${item.href}"><i class="bi ${item.icon} me-2"></i>${item.label}</a>`)
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

  function renderHome(data) {
    setText('sumStudents', `${data.students.totalStudents} คน`);
    setText('sumPersonnel', `${data.personnel.totalPersonnel} คน`);
    setText('sumClassrooms', `${data.school.classroomCount} ห้อง`);
    setText('sumYear', data.school.academicYear);
    setText('levelRange', data.school.levelRange);
    setText('identityText', data.school.identity);
    setText('visionText', data.school.vision);
  }

  function renderBasic(data) {
    const tbody = byId('basicTableBody');
    if (!tbody) return;
    const rows = [
      ['ชื่อสถานศึกษา', data.school.schoolName],
      ['ปีการศึกษา', data.school.academicYear],
      ['ระดับที่เปิดสอน', data.school.levelRange],
      ['จำนวนห้องเรียน', `${data.school.classroomCount} ห้อง`],
      ['จำนวนนักเรียน', `${data.school.studentCount} คน`],
      ['จำนวนบุคลากร', `${data.school.personnelCount} คน`],
      ['อัตลักษณ์', data.school.identity],
      ['วิสัยทัศน์', data.school.vision]
    ];
    tbody.innerHTML = rows.map((r) => `<tr><th>${r[0]}</th><td>${r[1]}</td></tr>`).join('');
  }

  function renderDirection(data) {
    const missions = byId('missionList');
    const strategies = byId('strategyList');
    if (missions) missions.innerHTML = data.school.missions.map((x) => `<li>${x}</li>`).join('');
    if (strategies) strategies.innerHTML = data.school.strategies.map((x) => `<li>${x}</li>`).join('');
  }

  function renderPerformance(data) {
    const tbody = byId('sarTableBody');
    const cards = byId('sarCards');
    if (tbody) {
      tbody.innerHTML = data.school.sarIndicators
        .map((x) => `<tr><td>${x.name}</td><td>${x.target}%</td><td>${x.actual}%</td><td>${x.status}</td></tr>`)
        .join('');
    }
    if (cards) {
      cards.innerHTML = data.school.sarIndicators
        .map((x) => `<div class="col-md-6"><div class="card section-card h-100"><div class="card-body"><h6>${x.name}</h6><p class="mb-1">ผลจริง: <strong>${x.actual}%</strong></p><p class="mb-0 text-secondary">เป้าหมาย: ${x.target}%</p></div></div></div>`)
        .join('');
    }
  }

  function renderPersonnel(data) {
    setText('personnelTotal', `${data.personnel.totalPersonnel} คน`);
    setText('teacherCount', `${data.personnel.teacherCount} คน`);
    setText('supportCount', `${data.personnel.supportCount} คน`);
    const tbody = byId('personnelTableBody');
    if (!tbody) return;
    tbody.innerHTML = data.personnel.byPosition
      .map((x) => `<tr><td>${x.position}</td><td>${x.count}</td><td>${x.percent}%</td></tr>`)
      .join('');
  }

  function renderStudents(data) {
    setText('studentsTotal', `${data.students.totalStudents} คน`);
    setText('maleTotal', `${data.students.byGender.male} คน`);
    setText('femaleTotal', `${data.students.byGender.female} คน`);
    const tbody = byId('studentsTableBody');
    if (!tbody) return;
    tbody.innerHTML = data.students.byLevel
      .map((x) => `<tr><td>${x.level}</td><td>${x.count}</td><td>${x.classrooms}</td></tr>`)
      .join('');
  }

  function renderBudget(data) {
    setText('fiscalYear', data.budget.fiscalYear);
    setText('budgetTotal', `${data.budget.total.toLocaleString()} บาท`);
    const tbody = byId('budgetTableBody');
    if (!tbody) return;
    tbody.innerHTML = data.budget.items
      .map((x) => `<tr><td>${x.category}</td><td>${x.amount.toLocaleString()}</td><td>${x.percent}%</td></tr>`)
      .join('');
  }

  function renderAwards(data) {
    const container = byId('awardsCards');
    if (!container) return;
    container.innerHTML = data.awards.items
      .map((x) => `<div class="col-md-6"><div class="card section-card h-100"><div class="card-body"><h5>${x.title}</h5><p class="mb-1"><strong>ปี:</strong> ${x.year}</p><p class="mb-1"><strong>หน่วยงาน:</strong> ${x.organization}</p><p class="mb-0">${x.summary}</p></div></div></div>`)
      .join('');
  }

  function renderDownloads(data) {
    const tbody = byId('downloadsTableBody');
    if (!tbody) return;
    tbody.innerHTML = data.downloads.items
      .map((x) => `<tr><td>${x.name}</td><td>${x.type}</td><td>${x.updatedAt}</td><td><a href="${x.link}" class="btn btn-sm btn-outline-primary">ดาวน์โหลด</a></td></tr>`)
      .join('');
  }

  function renderContact(data) {
    setText('contactAddress', data.school.contact.address);
    setText('contactPhone', data.school.contact.phone);
    setText('contactEmail', data.school.contact.email);
    setText('contactWorktime', data.school.contact.worktime);
  }

  async function init() {
    try {
      buildMenus();
      const [school, personnel, students, budget, awards, downloads] = await Promise.all([
        loadJson('school.json'),
        loadJson('personnel.json'),
        loadJson('students.json'),
        loadJson('budget.json'),
        loadJson('awards.json'),
        loadJson('downloads.json')
      ]);

      const data = { school, personnel, students, budget, awards, downloads };
      fillSchoolIdentity(school);

      if (page === 'home') renderHome(data);
      if (page === 'basic') renderBasic(data);
      if (page === 'direction') renderDirection(data);
      if (page === 'performance') renderPerformance(data);
      if (page === 'personnel') renderPersonnel(data);
      if (page === 'students') renderStudents(data);
      if (page === 'budget') renderBudget(data);
      if (page === 'awards') renderAwards(data);
      if (page === 'downloads') renderDownloads(data);
      if (page === 'contact') renderContact(data);

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
