(function () {
  const isSubPage = window.location.pathname.includes('/information/pages/');
  const APP_BASE_PATH = (() => {
    const marker = '/information/';
    const markerIndex = window.location.pathname.indexOf(marker);
    if (markerIndex >= 0) return window.location.pathname.slice(0, markerIndex) || '';
    return '';
  })();
  const ROOT = isSubPage ? '..' : '.';
  const DATA_ROOT = `${ROOT}/data`;
  const SITE_API_ROOT = `${APP_BASE_PATH}/api`;
  const INFORMATION_API_ROOT = `${APP_BASE_PATH}/information/api`;
  const page = document.body.dataset.page || 'home';
  const SCHOOL_NAME = 'โรงเรียนโสตศึกษาอนุสารสุนทร';
  const ACADEMIC_YEAR = '';

  const navItems = [
    { key: 'home', label: 'หน้าแรก', icon: 'bi-house-door-fill', href: isSubPage ? '../index.html' : 'index.html' },
    { key: 'basic', label: 'ข้อมูลพื้นฐานโรงเรียน', icon: 'bi-building', href: isSubPage ? 'basic.html' : 'pages/basic.html' },
    { key: 'direction', label: 'ทิศทางการศึกษา', icon: 'bi-signpost-split-fill', href: isSubPage ? 'direction.html' : 'pages/direction.html' },
    { key: 'performance', label: 'ผลการดำเนินงาน / SAR', icon: 'bi-bar-chart-line-fill', href: isSubPage ? 'performance.html' : 'pages/performance.html' },
    { key: 'personnel', label: 'ข้อมูลบุคลากร', icon: 'bi-person-badge-fill', href: isSubPage ? 'personnel.html' : 'pages/personnel.html' },
    { key: 'students', label: 'ข้อมูลนักเรียน', icon: 'bi-people-fill', href: isSubPage ? 'students.html' : 'pages/students.html' },
    { key: 'academic', label: 'ข้อมูลวิชาการ', icon: 'bi-mortarboard-fill', href: isSubPage ? 'academic.html' : 'pages/academic.html' },
    { key: 'budget', label: 'งบประมาณ', icon: 'bi-wallet2', href: isSubPage ? 'budget.html' : 'pages/budget.html' },
    { key: 'awards', label: 'ผลงานและรางวัล', icon: 'bi-award-fill', href: isSubPage ? 'awards.php' : 'pages/awards.php' },
    { key: 'downloads', label: 'ดาวน์โหลดเอกสาร', icon: 'bi-download', href: isSubPage ? 'downloads.html' : 'pages/downloads.html' }
  ];

  const pageMeta = {
    home: ['หน้าแรก', 'ภาพรวมสำคัญของระบบสารสนเทศสถานศึกษา', 'ระบบสารสนเทศโรงเรียนโสตศึกษาอนุสารสุนทร', 'สรุปข้อมูลสำคัญแบบอ่านง่ายสำหรับการติดตามภาพรวมสถานศึกษา'],
    basic: ['ข้อมูลพื้นฐานโรงเรียน', 'ข้อมูลอ้างอิงหลักของสถานศึกษา', 'ข้อมูลพื้นฐานโรงเรียน', 'รายละเอียดสำคัญของโรงเรียนที่จัดวางในรูปแบบการ์ดและตาราง'],
    direction: ['ทิศทางการศึกษา', 'วิสัยทัศน์ พันธกิจ และกลยุทธ์', 'ทิศทางการศึกษา', 'กรอบการพัฒนาของโรงเรียนที่อ่านง่ายและติดตามได้'],
    performance: ['ผลการดำเนินงาน / SAR', 'ตัวชี้วัดการประเมินตนเองของสถานศึกษา', 'ผลการดำเนินงาน / SAR', 'สรุปผลการดำเนินงานพร้อมกราฟและสถานะเป้าหมาย'],
    personnel: ['ข้อมูลบุคลากร', 'สถิติและโครงสร้างบุคลากรของโรงเรียน', 'ข้อมูลบุคลากร', 'แสดงข้อมูลฝ่ายบริหาร สถิติบุคลากร คุณวุฒิ ประสบการณ์ และรางวัล'],
    students: ['ข้อมูลนักเรียน', 'ข้อมูลนักเรียนจากฐานข้อมูล', 'ข้อมูลนักเรียน', 'แสดงเฉพาะข้อมูลนักเรียน ไม่รวมหลักสูตร ผลสัมฤทธิ์ O-NET หรือ NT'],
    academic: ['ข้อมูลวิชาการ', 'หลักสูตร ผลสัมฤทธิ์ O-NET NT และการสำเร็จการศึกษา', 'ข้อมูลวิชาการ', 'รวมข้อมูลหลักสูตรสถานศึกษา ผลสัมฤทธิ์ทางการเรียน O-NET NT และการสำเร็จการศึกษา'],
    budget: ['งบประมาณ', 'ข้อมูลการจัดสรรงบประมาณของโรงเรียน', 'งบประมาณ', 'มุมมองการใช้ทรัพยากรในปีงบประมาณปัจจุบัน'],
    awards: ['ผลงานและรางวัล', 'เกียรติยศและผลงานเด่นของโรงเรียน', 'ผลงานและรางวัล', 'รวบรวมรางวัลและผลงานในรูปแบบการ์ด'],
    downloads: ['ดาวน์โหลดเอกสาร', 'รายการไฟล์และเอกสารสำคัญ', 'ดาวน์โหลดเอกสาร', 'เอกสารสำหรับใช้งานทั่วไปในรูปแบบตารางที่อ่านง่าย']
  };

  const dataFilesByPage = {
    home: ['school', 'personnel', 'students'],
    basic: ['school'],
    direction: ['school'],
    performance: ['school'],
    personnel: ['school', 'personnel'],
    students: ['school', 'students'],
    academic: ['school', 'academic'],
    budget: ['school'],
    awards: ['school'],
    downloads: ['school', 'downloads']
  };

  const byId = (id) => document.getElementById(id);
  const escapeHtml = (value) => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  const formatNumber = (value) => Number(value || 0).toLocaleString('th-TH');
  const formatCount = (value) => (value === null || value === undefined || value === '' || value === '-' ? '-' : formatNumber(value));
  const formatCurrency = (value) => Number(value || 0).toLocaleString('th-TH');
  const formatPercent = (value) => Number(value || 0).toFixed(2).replace(/\.00$/, '');
  const noDataText = 'ไม่มีข้อมูล';
  const budgetNoDataText = 'ยังไม่มีข้อมูลงบประมาณ';

  function pickValue(source, keys, fallback = null) {
    for (const key of keys) {
      if (source && Object.prototype.hasOwnProperty.call(source, key) && source[key] !== null && source[key] !== undefined && source[key] !== '') {
        return source[key];
      }
    }
    return fallback;
  }

  function toNumber(value, fallback = 0) {
    if (value === null || value === undefined || value === '') return fallback;
    const normalized = String(value).replace(/[,บาท%]/g, '').trim();
    const numberValue = Number(normalized);
    return Number.isFinite(numberValue) ? numberValue : fallback;
  }

  async function loadJson(key) {
    const response = await fetch(`${DATA_ROOT}/${key}.json`);
    if (!response.ok) throw new Error(`ไม่สามารถโหลดไฟล์ ${key}.json`);
    return response.json();
  }

  async function loadApiJson(url) {
    const response = await fetch(url, { cache: 'no-store' });
    let payload = null;
    try {
      payload = await response.json();
    } catch (error) {
      if (!response.ok) throw new Error(`Cannot load ${url}`);
      throw error;
    }
    if (!response.ok) throw new Error(payload?.message || payload?.error || `Cannot load ${url}`);
    if (payload && payload.success === false) {
      throw new Error(payload.message || payload.error || `Cannot load ${url}`);
    }
    return payload && Object.prototype.hasOwnProperty.call(payload, 'data') ? payload.data : payload;
  }

  async function loadSettingsData() {
    const settings = await loadApiJson(`${SITE_API_ROOT}/setting.php`);
    const academicYear = settings.academic_year ? String(settings.academic_year) : '';
    return {
      settings,
      school: {
        schoolName: settings.school_name || SCHOOL_NAME,
        academicYear,
        classroomCount: settings.classroom_count ?? null,
        levelGroupCount: settings.level_group_count ?? 4
      },
      students: {
        totalStudents: settings.student_count ?? null
      }
    };
  }

  async function loadPersonnelData() {
    return loadApiJson(`${INFORMATION_API_ROOT}/personnel.php`);
  }

  async function loadBudgetData(year = '2569') {
    return loadApiJson(`${SITE_API_ROOT}/budget.php?action=all&year=${encodeURIComponent(year)}`);
  }

  async function loadBudgetElectricityData(year) {
    return loadApiJson(`${SITE_API_ROOT}/budget.php?action=electricity&year=${encodeURIComponent(year)}`);
  }

  async function loadPageData() {
    if (page === 'home') {
      const settingsData = await loadSettingsData();
      return {
        ...settingsData,
        personnel: {
          totalPersonnel: settingsData.settings.personnel_count ?? null
        }
      };
    }

    if (page === 'personnel') {
      const [settingsData, personnel] = await Promise.all([
        loadSettingsData(),
        loadPersonnelData()
      ]);
      return {
        ...settingsData,
        personnel
      };
    }

    if (page === 'budget') {
      const [settingsData, budgetData, electricity2568, electricity2569] = await Promise.all([
        loadSettingsData(),
        loadBudgetData('2569'),
        loadBudgetElectricityData('2568'),
        loadBudgetElectricityData('2569')
      ]);
      return {
        ...settingsData,
        ...budgetData,
        electricityComparison: {
          2568: electricity2568,
          2569: electricity2569
        },
        budget: budgetData.budget || {
          fiscalYear: budgetData.overview?.fiscalYear || '2569',
          total: budgetData.overview?.totalBudget || 0,
          items: budgetData.categories || []
        }
      };
    }

    const keys = dataFilesByPage[page] || dataFilesByPage.home;
    const entries = await Promise.all(keys.map(async (key) => [key, await loadJson(key)]));
    return Object.fromEntries(entries);
  }

  function setText(id, value) {
    const el = byId(id);
    if (el) el.textContent = value;
  }

  function setSummaryValues(values) {
    document.querySelectorAll('.summary-chip strong').forEach((target, index) => {
      if (values[index] !== undefined) target.textContent = values[index];
    });
  }

  function setMetricValues(values) {
    document.querySelectorAll('.metric-card .metric-value').forEach((target, index) => {
      if (values[index] !== undefined) target.textContent = values[index];
    });
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
      offcanvasEl.querySelectorAll('.js-offcanvas-menu .nav-link').forEach((link) => link.addEventListener('click', () => instance.hide()));
    }

    const quickMenu = byId('quickMenu');
    if (quickMenu) {
      quickMenu.innerHTML = navItems.slice(1)
        .map((item) => `<a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="${escapeHtml(item.href)}"><i class="bi ${escapeHtml(item.icon)} text-primary"></i><span>${escapeHtml(item.label)}</span></a>`)
        .join('');
    }
  }

  function fillSchoolIdentity(school) {
    const schoolName = school?.schoolName && !school.schoolName.includes('เธ') ? school.schoolName : SCHOOL_NAME;
    const academicYear = school?.academicYear || ACADEMIC_YEAR;
    document.querySelectorAll('[data-school-name]').forEach((el) => { el.textContent = schoolName; });
    document.querySelectorAll('[data-academic-year]').forEach((el) => { el.textContent = academicYear; });
    setText('copyrightYear', new Date().getFullYear());
  }

  function applyPageChrome(school) {
    const [title, subtitle, heroTitle, heroSubtitle] = pageMeta[page] || pageMeta.home;
    const academicYear = school?.academicYear || ACADEMIC_YEAR;
    document.title = `${title} | ${SCHOOL_NAME}`;
    setText('pageTitle', title);
    setText('pageSubtitle', `${subtitle} • ปีการศึกษา ${academicYear}`);
    setText('heroTitle', heroTitle);
    setText('heroSubtitle', heroSubtitle);
    setText('heroYear', academicYear);
    setText('heroSchoolName', SCHOOL_NAME);

    const breadcrumb = byId('breadcrumbTrail');
    if (breadcrumb) {
      breadcrumb.innerHTML = ['หน้าแรก', title]
        .filter((item, index) => index === 0 || item !== 'หน้าแรก')
        .map((item, index, list) => index === list.length - 1
          ? `<li class="breadcrumb-item active" aria-current="page">${escapeHtml(item)}</li>`
          : `<li class="breadcrumb-item"><a href="${isSubPage ? '../index.html' : 'index.html'}">${escapeHtml(item)}</a></li>`)
        .join('');
    }
  }

  function renderHome(data) {
    const academicYear = data.school?.academicYear || '';
    const students = data.settings?.student_count ?? data.students?.totalStudents ?? null;
    const personnel = data.settings?.personnel_count ?? data.personnel?.totalPersonnel ?? null;
    const classrooms = data.settings?.classroom_count ?? data.school?.classroomCount ?? null;
    const levelGroups = data.settings?.level_group_count ?? data.school?.levelGroupCount ?? 4;
    setSummaryValues([`${formatCount(students)} คน`, `${formatCount(personnel)} คน`, `${formatCount(classrooms)} ห้อง`, `${formatCount(levelGroups)} ช่วงชั้น`]);
    setText('sumStudents', formatCount(students));
    setText('sumPersonnel', formatCount(personnel));
    setText('sumClassrooms', formatCount(classrooms));
    setText('sumYear', academicYear || '-');
  }

  function renderBasic(data) {
    const academicYear = data.school?.academicYear || ACADEMIC_YEAR || '-';
    const classroomCount = data.school?.classroomCount ?? data.settings?.classroom_count ?? '-';
    const studentCount = data.school?.studentCount ?? data.students?.totalStudents ?? data.settings?.student_count ?? '-';
    const personnelCount = data.school?.personnelCount ?? data.personnel?.totalPersonnel ?? data.settings?.personnel_count ?? '-';
    setSummaryValues([academicYear, 'อนุบาล - มัธยมศึกษาตอนปลาย', `${formatCount(classroomCount)} ห้อง`, `${formatCount(studentCount)} คน`]);
    setMetricValues([SCHOOL_NAME, 'อนุบาล - มัธยมศึกษาตอนปลาย', `${formatCount(studentCount)} คน`, academicYear]);
    const tbody = byId('basicTableBody');
    if (!tbody) return;
    const rows = [
      ['ชื่อสถานศึกษา', SCHOOL_NAME],
      ['ปีการศึกษา', academicYear],
      ['ระดับที่เปิดสอน', 'อนุบาล - มัธยมศึกษาตอนปลาย'],
      ['จำนวนห้องเรียน', `${formatCount(classroomCount)} ห้อง`],
      ['จำนวนนักเรียน', `${formatCount(studentCount)} คน`],
      ['จำนวนบุคลากร', `${formatCount(personnelCount)} คน`]
    ];
    tbody.innerHTML = rows.map(([label, value]) => `<tr><th scope="row" class="text-nowrap">${escapeHtml(label)}</th><td>${escapeHtml(value)}</td></tr>`).join('');
  }

  function renderDirection(data) {
    const missions = byId('missionList');
    const strategies = byId('strategyList');
    if (missions && data.school?.missions) {
      missions.innerHTML = data.school.missions.map((item, index) => `<li class="list-group-item d-flex align-items-start gap-3"><span class="icon-circle icon-blue flex-shrink-0"><i class="bi bi-check2-circle"></i></span><div><div class="item-title">พันธกิจ ${index + 1}</div><div class="item-desc">${escapeHtml(item)}</div></div></li>`).join('');
    }
    if (strategies && data.school?.strategies) {
      strategies.innerHTML = data.school.strategies.map((item, index) => `<li class="list-group-item d-flex align-items-start gap-3"><span class="icon-circle icon-purple flex-shrink-0"><i class="bi bi-graph-up-arrow"></i></span><div><div class="item-title">กลยุทธ์ ${index + 1}</div><div class="item-desc">${escapeHtml(item)}</div></div></li>`).join('');
    }
  }

  function renderPerformance(data) {
    const indicators = data.school?.sarIndicators || [];
    setSummaryValues([`${indicators.length} ตัวชี้วัด`, `${indicators.filter((x) => String(x.status).includes('ผ่าน')).length} รายการ`, ACADEMIC_YEAR, 'SAR']);
    const tbody = byId('sarTableBody');
    if (tbody) {
      const toPercentNumber = (value) => Number(String(value ?? '').replace('%', '').trim());
      tbody.innerHTML = indicators.map((item) => {
        const target = toPercentNumber(item.target);
        const actual = toPercentNumber(item.actual);
        const actualBadgeClass = Number.isFinite(actual) && Number.isFinite(target) && actual < target ? 'badge-soft-warning' : 'badge-soft-success';
        return `<tr><td>${escapeHtml(item.name)}</td><td><span class="badge badge-soft-primary">${escapeHtml(item.target)}%</span></td><td><span class="badge ${actualBadgeClass}">${escapeHtml(item.actual)}%</span></td><td>${escapeHtml(item.status)}</td></tr>`;
      }).join('');
    }
  }

  function renderPersonnel(data) {
    const personnel = data.personnel || {};
    const kpis = Array.isArray(personnel.kpis) ? personnel.kpis : [];
    const formatKpi = (value, unit = 'คน') => value === null || value === undefined || value === '' ? noDataText : `${formatNumber(value)} ${unit}`;
    const kpiValue = (index, fallbackValue) => {
      if (kpis[index]) {
        return formatKpi(kpis[index].value, kpis[index].unit || 'คน');
      }
      return formatKpi(fallbackValue);
    };

    setText('personnelTotal', kpis[0] ? formatNumber(kpis[0].value) : (personnel.totalPersonnel ?? noDataText));
    setText('teacherCount', kpis[1] ? formatNumber(kpis[1].value) : (personnel.teacherCount ?? noDataText));
    setText('supportCount', kpis[2] ? formatNumber(kpis[2].value) : (personnel.supportCount ?? noDataText));
    setText('administratorCount', kpis[3] ? formatNumber(kpis[3].value) : (personnel.administratorCount ?? noDataText));
    setSummaryValues([
      kpiValue(0, personnel.totalPersonnel),
      kpiValue(1, personnel.teacherCount),
      kpiValue(2, personnel.supportCount),
      kpiValue(3, personnel.administratorCount)
    ]);
    const tbody = byId('personnelTableBody');
    if (tbody) {
      const positionSummary = Array.isArray(personnel.positionSummary) ? personnel.positionSummary : [];
      tbody.innerHTML = positionSummary.length
        ? positionSummary.map((item) => {
          const percent = item.percentDisplay || `${formatPercent(item.percentCalculated ?? item.percent)}%`;
          return `<tr><td>${escapeHtml(item.position || noDataText)}</td><td>${formatNumber(item.maleCount)} คน</td><td>${formatNumber(item.femaleCount)} คน</td><td>${formatNumber(item.count)} คน</td><td><span class="badge badge-soft-primary">${escapeHtml(percent)}</span></td></tr>`;
        }).join('')
        : `<tr><td colspan="5" class="text-center text-muted">${noDataText}</td></tr>`;
    }
  }

  function renderKeyValueList(id, items, labelKey, valueKey, unit = 'คน') {
    const target = byId(id);
    if (!target) return;
    target.innerHTML = items.map((item) => `<div class="d-flex align-items-center justify-content-between gap-3 py-2 border-bottom"><span>${escapeHtml(item[labelKey])}</span><strong>${formatNumber(item[valueKey])} ${unit}</strong></div>`).join('');
  }

  function renderStudents(data) {
    const students = data.students;
    const classroomCount = students.classroomCount || students.byLevel.reduce((total, item) => total + Number(item.classrooms || 0), 0);
    setText('studentsTotal', students.totalStudents);
    setText('maleTotal', students.byGender.male);
    setText('femaleTotal', students.byGender.female);
    setText('classroomTotal', classroomCount);
    setSummaryValues([`${students.totalStudents} คน`, `${students.byGender.male} คน`, `${students.byGender.female} คน`, `${classroomCount} ห้อง`]);

    const tbody = byId('studentsTableBody');
    if (tbody) {
      tbody.innerHTML = students.byLevel.map((item) => `<tr><td>${escapeHtml(item.level)}</td><td>${formatNumber(item.count)} คน</td><td>${formatNumber(item.classrooms)} ห้อง</td><td><span class="badge badge-soft-info">${Math.round((Number(item.count) / Number(students.totalStudents)) * 100)}%</span></td></tr>`).join('');
    }

    renderKeyValueList('provinceList', students.byProvince, 'province', 'count');
    renderKeyValueList('ethnicityList', students.byEthnicity, 'name', 'count');
    renderKeyValueList('religionList', students.byReligion, 'name', 'count');
    renderKeyValueList('impairmentList', students.byImpairment, 'type', 'count');
    renderKeyValueList('multipleDisabilityList', students.multipleDisabilities, 'type', 'count');
    renderKeyValueList('residenceList', students.residenceType, 'type', 'count');
    renderKeyValueList('dormitoryList', students.dormitories, 'name', 'count');

    const activities = byId('activityList');
    if (activities) {
      activities.innerHTML = students.studentActivities.map((item) => `<div class="soft-card p-3 mb-2"><div class="d-flex justify-content-between gap-3"><strong>${escapeHtml(item.name)}</strong><span class="badge badge-soft-primary">${formatNumber(item.participants)} คน</span></div><div class="small text-muted mt-2">${escapeHtml(item.note)}</div></div>`).join('');
    }

    const scholarships = byId('scholarshipList');
    if (scholarships) {
      scholarships.innerHTML = students.scholarships.map((item) => `<tr><td>${escapeHtml(item.name)}</td><td>${formatNumber(item.count)} คน</td><td>${formatCurrency(item.amount)} บาท</td></tr>`).join('');
    }
  }

  function renderAcademic(data) {
    const academic = data.academic;
    setSummaryValues([`${academic.curriculum.length} หลักสูตร`, `${academic.learningAreas.length} กลุ่มสาระ`, `${academic.onet.length} ระดับ`, `${academic.graduation.length} ระดับ`]);

    const curriculum = byId('curriculumList');
    if (curriculum) {
      curriculum.innerHTML = academic.curriculum.map((item) => `<div class="col-md-6"><div class="soft-card p-3 h-100"><div class="item-title">${escapeHtml(item.name)}</div><div class="badge badge-soft-primary my-2">${escapeHtml(item.level)}</div><p class="item-desc mb-0">${escapeHtml(item.summary)}</p></div></div>`).join('');
    }

    const areas = byId('learningAreaList');
    if (areas) {
      areas.innerHTML = academic.learningAreas.map((item) => `<span class="badge badge-soft-info me-2 mb-2">${escapeHtml(item)}</span>`).join('');
    }

    const activities = byId('curriculumActivityList');
    if (activities) {
      activities.innerHTML = academic.curriculumActivities.map((item) => `<span class="badge badge-soft-success me-2 mb-2">${escapeHtml(item)}</span>`).join('');
    }

    const subjects = byId('subjectsByLevel');
    if (subjects) {
      subjects.innerHTML = academic.subjectsByLevel.map((group) => `<div class="col-md-4"><div class="section-card h-100"><div class="card-header"><h3 class="h6 mb-0">${escapeHtml(group.level)}</h3></div><div class="card-body"><ul class="mb-0">${group.items.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul></div></div></div>`).join('');
    }

    const primary = byId('achievementPrimaryBody');
    if (primary) primary.innerHTML = academic.achievementPrimary.map((item) => `<tr><td>${escapeHtml(item.grade)}</td><td>${formatPercent(item.average)}%</td></tr>`).join('');
    const secondary = byId('achievementSecondaryBody');
    if (secondary) secondary.innerHTML = academic.achievementSecondary.map((item) => `<tr><td>${escapeHtml(item.grade)}</td><td>${formatPercent(item.average)}%</td></tr>`).join('');
    const onet = byId('onetBody');
    if (onet) onet.innerHTML = academic.onet.map((item) => `<tr><td>${escapeHtml(item.level)}</td><td>${formatPercent(item.thai)}</td><td>${formatPercent(item.math)}</td><td>${formatPercent(item.science)}</td><td>${formatPercent(item.english)}</td></tr>`).join('');
    const nt = byId('ntBody');
    if (nt) nt.innerHTML = academic.nt.map((item) => `<tr><td>${escapeHtml(item.level)}</td><td>${formatPercent(item.thai)}</td><td>${formatPercent(item.math)}</td><td>${formatPercent(item.average)}</td></tr>`).join('');
    const graduation = byId('graduationBody');
    if (graduation) graduation.innerHTML = academic.graduation.map((item) => `<tr><td>${escapeHtml(item.level)}</td><td>${formatNumber(item.graduates)} คน</td><td>${formatPercent(item.percent)}%</td></tr>`).join('');
  }

  function renderBudget(data) {
    const budget = data.budget || {};
    const overview = data.overview || {};
    const categories = Array.isArray(data.categories) ? data.categories : (budget.items || []);
    const cards = Array.isArray(data.cards) ? data.cards : [];
    const items = Array.isArray(data.items) ? data.items : [];
    const mainItems = items.filter((item) => {
      const level = String(pickValue(item, ['level', 'apiItemLevel', 'api_item_level'], pickValue(item.raw, ['api_item_level', 'item_level'], ''))).toUpperCase();
      return level === 'MAIN' || level === '1';
    });
    const teachingManagement = items.filter((item) => {
      const parentCode = pickValue(item, ['parentCode', 'parentItemCode', 'parent_item_code'], pickValue(item.raw, ['parent_item_code'], ''));
      return parentCode === 'SUBSIDY_TEACHING_MANAGEMENT';
    });
    const electricity = Array.isArray(data.electricity) ? data.electricity : [];
    const fiscalYear = pickValue(budget, ['fiscalYear', 'fiscal_year'], pickValue(overview, ['fiscalYear', 'fiscal_year'], '2569'));
    const totalBudget = toNumber(pickValue(budget, ['total', 'totalBudget', 'total_budget'], pickValue(overview, ['totalBudget', 'total_budget'], 0)));
    const categoryCount = toNumber(pickValue(overview, ['categoryCount', 'category_count'], categories.length), categories.length);
    const itemCount = toNumber(pickValue(overview, ['itemCount', 'item_count'], items.length), items.length);
    const electricityMonths = toNumber(
      pickValue(overview, ['electricityMonthsRecorded', 'electricity_months_recorded'], null),
      electricity.filter((item) => pickValue(item, ['amount'], pickValue(item.raw, ['amount'], null)) !== null).length
    );
    const electricityTotal = toNumber(
      pickValue(overview, ['electricityTotalAmount', 'electricity_total_amount'], null),
      electricity.reduce((sum, item) => sum + toNumber(pickValue(item, ['amount'], pickValue(item.raw, ['amount'], 0))), 0)
    );
    const teachingTotal = teachingManagement.reduce((sum, item) => sum + toNumber(pickValue(item, ['amount'], pickValue(item.raw, ['amount'], 0))), 0);
    const findCard = (patterns) => cards.find((card) => {
      const haystack = `${pickValue(card, ['key', 'cardKey'], '')} ${pickValue(card, ['label', 'title', 'cardTitle'], pickValue(card.raw, ['card_title'], ''))}`.toLowerCase();
      return patterns.some((pattern) => haystack.includes(pattern));
    });
    const cardDisplay = (card, fallback, fallbackUnit = '') => {
      if (!card) return fallback;
      const rawValue = pickValue(card, ['value', 'valueText', 'cardValueText'], pickValue(card.raw, ['card_value_text'], null));
      const numericValue = pickValue(card, ['valueNumber', 'cardValueNumber'], pickValue(card.raw, ['card_value_number'], null));
      const value = numericValue !== null
        ? formatCurrency(numericValue)
        : String(rawValue ?? fallback);
      const unit = pickValue(card, ['unit', 'cardUnit'], pickValue(card.raw, ['card_unit'], fallbackUnit));
      return unit ? `${value} ${unit}` : value;
    };
    const totalCard = findCard(['total_budget', 'budget_total', 'งบประมาณรวม', 'งบรวม']);
    const categoryCard = findCard(['category_count', 'หมวด']);
    const itemCard = findCard(['item_count', 'รายการ']);
    const electricityCard = findCard(['electricity', 'ค่าไฟ']);

    setSummaryValues([fiscalYear, `${formatCurrency(totalBudget)} บาท`, `${categoryCount} หมวด`, 'API']);
    setMetricValues([
      fiscalYear,
      cardDisplay(totalCard, `${formatCurrency(totalBudget)} บาท`, 'บาท'),
      cardDisplay(categoryCard, `${categoryCount} หมวด`)
    ]);
    setText('fiscalYear', fiscalYear);
    setText('budgetTotal', `${formatCurrency(totalBudget)} บาท`);
    setText('budgetCategoryCount', categoryCount);
    setText('budgetItemCount', cardDisplay(itemCard, itemCount));
    setText('electricityRecorded', electricityMonths);
    setText('electricityTotal', cardDisplay(electricityCard, `${formatCurrency(electricityTotal)} บาท`, 'บาท'));
    setText('teachingTotal', `${formatCurrency(teachingTotal)} บาท`);

    const tbody = byId('budgetTableBody');
    if (tbody) {
      tbody.innerHTML = categories.length
        ? categories.map((item) => {
          const name = pickValue(item, ['category', 'categoryName', 'name'], pickValue(item.raw, ['category_name'], noDataText));
          const amount = toNumber(pickValue(item, ['amount'], pickValue(item.raw, ['amount'], 0)));
          const percent = toNumber(pickValue(item, ['percent', 'percentCalculated', 'percentage'], pickValue(item.raw, ['percent_calculated', 'percent_reported'], 0)));
          return `<tr><td>${escapeHtml(name)}</td><td>${formatCurrency(amount)} บาท</td><td><span class="badge badge-soft-warning">${formatPercent(percent)}%</span></td><td><div class="progress" style="height:0.7rem"><div class="progress-bar bg-primary" style="width:${percent || 0}%"></div></div></td></tr>`;
        }).join('')
        : `<tr><td colspan="4" class="text-center text-muted">${budgetNoDataText}</td></tr>`;
    }

    const mainItemsBody = byId('budgetMainItemsBody');
    if (mainItemsBody) {
      mainItemsBody.innerHTML = mainItems.length
        ? mainItems.map((item) => {
          const code = pickValue(item, ['code', 'itemCode'], pickValue(item.raw, ['item_code'], '-'));
          const category = pickValue(item, ['category', 'categoryName'], pickValue(item.raw, ['category_name'], noDataText));
          const name = pickValue(item, ['name', 'itemName'], pickValue(item.raw, ['item_name'], noDataText));
          const amount = toNumber(pickValue(item, ['amount'], pickValue(item.raw, ['amount'], 0)));
          const percent = toNumber(pickValue(item, ['percent', 'percentCalculated'], pickValue(item.raw, ['percent_calculated', 'percent_reported'], 0)));
          return `<tr data-item-code="${escapeHtml(code || '')}"><td>${escapeHtml(category)}</td><td>${escapeHtml(name)}</td><td>${formatCurrency(amount)} บาท</td><td><span class="badge badge-soft-primary">${formatPercent(percent)}%</span></td></tr>`;
        }).join('')
        : `<tr><td colspan="4" class="text-center text-muted">${budgetNoDataText}</td></tr>`;
    }

    const teachingBody = byId('teachingManagementBody');
    if (teachingBody) {
      teachingBody.innerHTML = teachingManagement.length
        ? teachingManagement.map((item) => {
          const code = pickValue(item, ['code', 'itemCode'], pickValue(item.raw, ['item_code'], '-'));
          const category = pickValue(item, ['category', 'categoryName'], pickValue(item.raw, ['category_name'], noDataText));
          const name = pickValue(item, ['name', 'itemName'], pickValue(item.raw, ['item_name'], noDataText));
          const amount = toNumber(pickValue(item, ['amount'], pickValue(item.raw, ['amount'], 0)));
          const percent = toNumber(pickValue(item, ['percent', 'percentCalculated'], pickValue(item.raw, ['percent_calculated', 'percent_reported'], 0)));
          return `<tr data-item-code="${escapeHtml(code || '')}"><td>${escapeHtml(name)}</td><td>${escapeHtml(category)}</td><td>${formatCurrency(amount)} บาท</td><td><span class="badge badge-soft-success">${formatPercent(percent)}%</span></td></tr>`;
        }).join('')
        : `<tr><td colspan="4" class="text-center text-muted">${budgetNoDataText}</td></tr>`;
    }

    const electricityBody = byId('electricityMonthlyBody');
    if (electricityBody) {
      electricityBody.innerHTML = electricity.length
        ? electricity.map((item) => {
          const amount = pickValue(item, ['amount'], pickValue(item.raw, ['amount'], null));
          const hasAmount = amount !== null && amount !== undefined && amount !== '';
          const month = pickValue(item, ['month', 'monthLabel', 'monthLabelTh'], pickValue(item.raw, ['month_label_th'], noDataText));
          const status = pickValue(item, ['status', 'dataStatus'], pickValue(item.raw, ['data_status'], hasAmount ? 'มีข้อมูล' : 'ยังไม่มีข้อมูล'));
          return `<tr><td>${escapeHtml(month)}</td><td>${hasAmount ? `${formatCurrency(amount)} บาท` : '-'}</td><td><span class="badge ${hasAmount ? 'badge-soft-success' : 'badge-soft-warning'}">${escapeHtml(hasAmount ? status : 'ยังไม่มีข้อมูล')}</span></td></tr>`;
        }).join('')
        : `<tr><td colspan="3" class="text-center text-muted">${budgetNoDataText}</td></tr>`;
    }

    const notesList = byId('budgetNotesList');
    if (notesList) {
      const notes = Array.isArray(data.notes) ? data.notes : [];
      notesList.innerHTML = notes.length
        ? notes.map((item) => `<div class="soft-card p-3 mb-2"><strong>${escapeHtml(item.title)}</strong><div class="small text-muted mt-1">${escapeHtml(item.note)}</div></div>`).join('')
        : `<div class="text-muted">${noDataText}</div>`;
    }
  }

  function renderAwards(data) {
    return data;
  }

  function renderDownloads(data) {
    const items = data.downloads?.items || [];
    setSummaryValues([`${items.length} รายการ`, [...new Set(items.map((item) => item.type))].join(' / '), items.map((item) => item.updatedAt).sort().reverse()[0] || '-', 'สาธารณะ']);
    const tbody = byId('downloadsTableBody');
    if (tbody) tbody.innerHTML = items.map((item) => `<tr><td>${escapeHtml(item.name)}</td><td><span class="badge badge-soft-primary">${escapeHtml(item.type)}</span></td><td>${escapeHtml(item.updatedAt)}</td><td><a href="${escapeHtml(item.link || '#')}" class="btn btn-sm btn-outline-primary rounded-pill"><i class="bi bi-download me-1"></i>ดาวน์โหลด</a></td></tr>`).join('');
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
      if (page === 'academic') renderAcademic(data);
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
