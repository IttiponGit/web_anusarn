(function () {
  const isSubPage = window.location.pathname.includes('/information/pages/');
  const ROOT = isSubPage ? '..' : '.';
  const DATA_ROOT = `${ROOT}/data`;
  const API_ROOT = `${ROOT}/api`;
  const page = document.body.dataset.page || 'home';
  const SCHOOL_NAME = 'โรงเรียนโสตศึกษาอนุสารสุนทร';
  const ACADEMIC_YEAR = String(new Date().getFullYear() + 543);

  const navItems = [
    { key: 'home', label: 'หน้าแรก', icon: 'bi-house-door-fill', href: isSubPage ? '../index.html' : 'index.html' },
    { key: 'basic', label: 'ข้อมูลพื้นฐานโรงเรียน', icon: 'bi-building', href: isSubPage ? 'basic.html' : 'pages/basic.html' },
    { key: 'direction', label: 'ทิศทางการศึกษา', icon: 'bi-signpost-split-fill', href: isSubPage ? 'direction.html' : 'pages/direction.html' },
    { key: 'performance', label: 'ผลการดำเนินงาน / SAR', icon: 'bi-bar-chart-line-fill', href: isSubPage ? 'performance.html' : 'pages/performance.html' },
    { key: 'personnel', label: 'ข้อมูลบุคลากร', icon: 'bi-person-badge-fill', href: isSubPage ? 'personnel.html' : 'pages/personnel.html' },
    { key: 'students', label: 'ข้อมูลนักเรียน', icon: 'bi-people-fill', href: isSubPage ? 'students.html' : 'pages/students.html' },
    { key: 'academic', label: 'ข้อมูลวิชาการ', icon: 'bi-mortarboard-fill', href: isSubPage ? 'academic.html' : 'pages/academic.html' },
    { key: 'budget', label: 'งบประมาณ', icon: 'bi-wallet2', href: isSubPage ? 'budget.html' : 'pages/budget.html' },
    { key: 'awards', label: 'ผลงานและรางวัล', icon: 'bi-award-fill', href: isSubPage ? 'awards.html' : 'pages/awards.html' },
    { key: 'downloads', label: 'ดาวน์โหลดเอกสาร', icon: 'bi-download', href: isSubPage ? 'downloads.html' : 'pages/downloads.html' }
  ];

  const pageMeta = {
    home: ['หน้าแรก', 'ภาพรวมสำคัญของระบบสารสนเทศสถานศึกษา', 'ระบบสารสนเทศโรงเรียนโสตศึกษาอนุสารสุนทร', 'สรุปข้อมูลสำคัญแบบอ่านง่ายสำหรับการติดตามภาพรวมสถานศึกษา'],
    basic: ['ข้อมูลพื้นฐานโรงเรียน', 'ข้อมูลอ้างอิงหลักของสถานศึกษา', 'ข้อมูลพื้นฐานโรงเรียน', 'รายละเอียดสำคัญของโรงเรียนที่จัดวางในรูปแบบการ์ดและตาราง'],
    direction: ['ทิศทางการศึกษา', 'วิสัยทัศน์ พันธกิจ และกลยุทธ์', 'ทิศทางการศึกษา', 'กรอบการพัฒนาของโรงเรียนที่อ่านง่ายและติดตามได้'],
    performance: ['ผลการดำเนินงาน / SAR', 'ตัวชี้วัดการประเมินตนเองของสถานศึกษา', 'ผลการดำเนินงาน / SAR', 'สรุปผลการดำเนินงานพร้อมกราฟและสถานะเป้าหมาย'],
    personnel: ['ข้อมูลบุคลากร', 'สถิติและโครงสร้างบุคลากรของโรงเรียน', 'ข้อมูลบุคลากร', 'แสดงข้อมูลฝ่ายบริหาร สถิติบุคลากร คุณวุฒิ ประสบการณ์ และรางวัล'],
    students: ['ข้อมูลนักเรียน', `ข้อมูล ณ วันที่ 10 มิถุนายน ${ACADEMIC_YEAR}`, 'ข้อมูลนักเรียน', 'แสดงเฉพาะข้อมูลนักเรียน ไม่รวมหลักสูตร ผลสัมฤทธิ์ O-NET หรือ NT'],
    academic: ['ข้อมูลวิชาการ', 'หลักสูตร ผลสัมฤทธิ์ O-NET NT และการสำเร็จการศึกษา', 'ข้อมูลวิชาการ', 'รวมข้อมูลหลักสูตรสถานศึกษา ผลสัมฤทธิ์ทางการเรียน O-NET NT และการสำเร็จการศึกษา'],
    budget: ['งบประมาณ', 'ข้อมูลการจัดสรรงบประมาณของโรงเรียน', 'งบประมาณ', 'มุมมองการใช้ทรัพยากรในปีงบประมาณปัจจุบัน'],
    awards: ['ผลงานและรางวัล', 'เกียรติยศและผลงานเด่นของโรงเรียน', 'ผลงานและรางวัล', 'รวบรวมรางวัลและผลงานในรูปแบบการ์ด'],
    downloads: ['ดาวน์โหลดเอกสาร', 'รายการไฟล์และเอกสารสำคัญ', 'ดาวน์โหลดเอกสาร', 'เอกสารสำหรับใช้งานทั่วไปในรูปแบบตารางที่อ่านง่าย']
  };

  const dataFilesByPage = {
    home: [],
    basic: ['school'],
    direction: ['school'],
    performance: ['school'],
    personnel: ['school', 'personnel'],
    academic: ['school', 'academic'],
    budget: ['school', 'budget']
  };

  const byId = (id) => document.getElementById(id);
  const escapeHtml = (value) => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  const formatNumber = (value) => Number(value || 0).toLocaleString('th-TH');
  const formatCurrency = (value) => Number(value || 0).toLocaleString('th-TH');
  const formatPercent = (value) => Number(value || 0).toFixed(2).replace(/\.00$/, '');
  const parsePercentValue = (value) => {
    const normalized = String(value ?? '').replace('%', '').trim();
    const number = parseFloat(normalized);
    return Number.isFinite(number) ? number : 0;
  };

  const formatSarPercent = (value) => `${formatPercent(parsePercentValue(value))}%`;

  async function loadJson(key) {
    const response = await fetch(`${DATA_ROOT}/${key}.json`);
    if (!response.ok) throw new Error(`ไม่สามารถโหลดไฟล์ ${key}.json`);
    return response.json();
  }

  async function loadStudentsApi() {
    const response = await fetch(`${API_ROOT}/students.php`);
    if (!response.ok) throw new Error('ไม่สามารถโหลดข้อมูลนักเรียนจากฐานข้อมูล');

    const payload = await response.json();
    if (!payload.success) throw new Error(payload.error || 'ไม่สามารถโหลดข้อมูลนักเรียนจากฐานข้อมูล');

    return payload.students;
  }

  async function loadDocumentsApi() {
    const response = await fetch(`${API_ROOT}/documents.php`, {
      cache: 'no-store',
      headers: {
        Accept: 'application/json'
      }
    });
    if (!response.ok) throw new Error('ไม่สามารถโหลดข้อมูลเอกสารจากฐานข้อมูล');

    const payload = await response.json();
    if (!payload.success) throw new Error(payload.error || 'ไม่สามารถโหลดข้อมูลเอกสารจากฐานข้อมูล');

    return {
      items: Array.isArray(payload.data) ? payload.data : [],
      summary: payload.summary || {}
    };
  }

  async function loadSchoolSettingsApi() {
    const response = await fetch(`${API_ROOT}/school.php`, {
      cache: 'no-store',
      headers: {
        Accept: 'application/json'
      }
    });
    if (!response.ok) throw new Error('ไม่สามารถโหลดข้อมูลโรงเรียนจากฐานข้อมูล');

    const payload = await response.json();
    if (!payload.success) throw new Error(payload.message || payload.error || 'ไม่สามารถโหลดข้อมูลโรงเรียนจากฐานข้อมูล');

    const settings = payload.data || {};
    return {
      schoolName: settings.schoolName || settings.schoolNameTh || SCHOOL_NAME,
      schoolNameTh: settings.schoolNameTh || settings.schoolName || SCHOOL_NAME,
      schoolNameEn: settings.schoolNameEn || '',
      academicYear: settings.academicYear || settings.academic_year || ACADEMIC_YEAR,
      studentCount: Number(settings.studentCount ?? settings.student_count ?? 0),
      personnelCount: Number(settings.personnelCount ?? settings.personnel_count ?? 0),
      classroomCount: Number(settings.classroomCount ?? settings.classroom_count ?? 0),
      levelRange: settings.levelRange || '',
      identity: settings.identity || '',
      vision: settings.vision || '',
      contact: settings.contact || {},
      settings: settings.settings || {}
    };
  }

  async function loadPersonnelApi() {
    const response = await fetch(`${API_ROOT}/personnel.php`, {
      cache: 'no-store',
      headers: {
        Accept: 'application/json'
      }
    });
    if (!response.ok) throw new Error('ไม่สามารถโหลดข้อมูลบุคลากรจากฐานข้อมูล');

    const payload = await response.json();
    if (!payload.success) throw new Error(payload.error || 'ไม่สามารถโหลดข้อมูลบุคลากรจากฐานข้อมูล');

    const items = Array.isArray(payload.data) ? payload.data : [];
    const summary = payload.summary || {};
    return {
      items,
      totalPersonnel: Number(summary.totalPersonnel ?? items.length),
      administratorCount: Number(summary.administratorCount || 0),
      teacherCount: Number(summary.teacherCount || 0),
      supportCount: Number(summary.supportCount || 0),
      byPosition: Array.isArray(summary.byPosition) ? summary.byPosition : []
    };
  }

  async function loadPageData() {
    if (page === 'home') {
      const [school, personnel, students] = await Promise.all([
        loadSchoolSettingsApi(),
        loadPersonnelApi(),
        loadStudentsApi()
      ]);
      const studentTotal = Number(school.studentCount || 0);
      const classroomTotal = Number(school.classroomCount || 0);
      return {
        school: {
          ...school,
          academicYear: school.academicYear || students?.academicYear || ACADEMIC_YEAR
        },
        personnel,
        students: {
          ...students,
          totalStudents: studentTotal || students?.totalStudents || 0,
          classroomCount: classroomTotal || students?.classroomCount || 0
        }
      };
    }

    if (page === 'personnel') {
      const [school, personnel] = await Promise.all([
        loadSchoolSettingsApi(),
        loadPersonnelApi()
      ]);
      return { school, personnel };
    }

    if (page === 'students') {
      const students = await loadStudentsApi();
      return {
        school: {
          schoolName: SCHOOL_NAME,
          academicYear: students?.academicYear || ACADEMIC_YEAR
        },
        students
      };
    }

    if (page === 'awards') {
      return {
        school: {
          schoolName: SCHOOL_NAME,
          academicYear: ACADEMIC_YEAR
        }
      };
    }

    if (page === 'downloads') {
      const downloads = await loadDocumentsApi();
      return {
        school: {
          schoolName: SCHOOL_NAME,
          academicYear: ACADEMIC_YEAR
        },
        downloads
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
    const schoolName = school?.schoolName && !school.schoolName.includes('เน€เธ') ? school.schoolName : SCHOOL_NAME;
    document.title = `${title} | ${schoolName}`;
    setText('pageTitle', title);
    setText('pageSubtitle', `${subtitle} • ปีการศึกษา ${academicYear}`);
    setText('heroTitle', heroTitle);
    setText('heroSubtitle', heroSubtitle);
    setText('heroYear', academicYear);
    setText('heroSchoolName', schoolName);

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
    const students = data.students || {};
    const levels = Array.isArray(students.byLevel) ? students.byLevel : [];
    const classrooms = students.classroomCount || levels.reduce((total, item) => total + Number(item.classrooms || 0), 0);
    const levelGroupCount = Number(students.levelGroupCount || 0) || levels.filter((item) => Number(item.count || 0) > 0 || Number(item.classrooms || 0) > 0).length || levels.length;
    const personnelTotal = data.personnel?.totalPersonnel || '-';

    setSummaryValues([`${formatNumber(students.totalStudents)} คน`, `${personnelTotal} คน`, `${formatNumber(classrooms)} ห้อง`, `${formatNumber(levelGroupCount)} ช่วงชั้น`]);
    setText('sumStudents', formatNumber(students.totalStudents));
    setText('sumPersonnel', personnelTotal);
    setText('sumClassrooms', formatNumber(classrooms));
    setText('sumLevelGroups', formatNumber(levelGroupCount));
    if (data.school?.levelRange) setText('homeLevelLead', data.school.levelRange);
    if (data.school?.identity) setText('homeIdentityLead', data.school.identity);
    if (data.school?.vision) setText('homeVisionLead', data.school.vision);
  }

  function renderBasic(data) {
    const school = data.school || {};
    const academicYear = school.academicYear || ACADEMIC_YEAR;
    const levelRange = school.levelRange || '-';
    const classroomCount = Number(school.classroomCount || 0);
    const studentCount = Number(school.studentCount || 0);
    const personnelCount = Number(school.personnelCount || 0);
    const classroomText = classroomCount > 0 ? `${formatNumber(classroomCount)} ห้อง` : '-';
    const studentText = studentCount > 0 ? `${formatNumber(studentCount)} คน` : '-';
    const personnelText = personnelCount > 0 ? `${formatNumber(personnelCount)} คน` : '-';

    setSummaryValues([academicYear, levelRange, classroomText, studentText]);
    setMetricValues([school.schoolName || SCHOOL_NAME, levelRange, studentText, academicYear]);
    const tbody = byId('basicTableBody');
    if (!tbody) return;
    const rows = [
      ['ชื่อสถานศึกษา', school.schoolName || SCHOOL_NAME],
      ['ปีการศึกษา', academicYear],
      ['ระดับที่เปิดสอน', levelRange],
      ['จำนวนห้องเรียน', classroomText],
      ['จำนวนนักเรียน', studentText],
      ['จำนวนบุคลากร', personnelText]
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
    const evaluatedIndicators = indicators.map((item) => {
      const targetValue = parsePercentValue(item.target);
      const actualValue = parsePercentValue(item.actual);
      const isPassed = actualValue >= targetValue;
      const existingStatus = String(item.status || '').trim();

      return {
        ...item,
        targetValue,
        actualValue,
        isPassed,
        actualBadgeClass: isPassed
          ? 'bg-success-subtle text-success border border-success-subtle'
          : 'bg-warning-subtle text-warning border border-warning-subtle',
        statusText: isPassed ? 'ผ่าน' : (existingStatus && !existingStatus.includes('ผ่าน') ? existingStatus : 'กำลังพัฒนา')
      };
    });
    const passedCount = evaluatedIndicators.filter((item) => item.isPassed).length;
    const developingCount = evaluatedIndicators.length - passedCount;

    setSummaryValues([`${evaluatedIndicators.length} ตัวชี้วัด`, `${passedCount} รายการ`, `${developingCount} รายการ`, data.school?.academicYear || ACADEMIC_YEAR]);
    const tbody = byId('sarTableBody');
    if (tbody) {
      tbody.innerHTML = evaluatedIndicators.map((item) => `<tr><td>${escapeHtml(item.name)}</td><td><span class="badge badge-soft-primary">${escapeHtml(formatSarPercent(item.target))}</span></td><td><span class="badge ${item.actualBadgeClass}">${escapeHtml(formatSarPercent(item.actual))}</span></td><td>${escapeHtml(item.statusText)}</td></tr>`).join('');
    }
  }

  function renderPersonnel(data) {
    setText('personnelTotal', data.personnel.totalPersonnel);
    setText('teacherCount', data.personnel.teacherCount);
    setText('supportCount', data.personnel.supportCount);
    setSummaryValues([`${data.personnel.totalPersonnel} คน`, `${data.personnel.teacherCount} คน`, `${data.personnel.supportCount} คน`, `${data.personnel.administratorCount || 0} คน`]);
    const tbody = byId('personnelTableBody');
    if (tbody) {
      tbody.innerHTML = data.personnel.byPosition.map((item) => `<tr><td>${escapeHtml(item.position)}</td><td>${escapeHtml(item.count)}</td><td>${formatPercent(item.percent)}%</td><td><span class="badge badge-soft-primary">${escapeHtml(item.count)} คน</span></td></tr>`).join('');
    }
  }

  function renderKeyValueList(id, items, labelKey, valueKey, unit = 'คน') {
    const target = byId(id);
    if (!target) return;
    target.innerHTML = (items || []).map((item) => `<div class="d-flex align-items-center justify-content-between gap-3 py-2 border-bottom"><span>${escapeHtml(item[labelKey])}</span><strong>${formatNumber(item[valueKey])} ${unit}</strong></div>`).join('');
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
      tbody.innerHTML = students.byLevel.map((item) => {
        const percent = Number(students.totalStudents) > 0 ? Math.round((Number(item.count) / Number(students.totalStudents)) * 100) : 0;
        return `<tr><td>${escapeHtml(item.level)}</td><td>${formatNumber(item.count)} คน</td><td>${formatNumber(item.classrooms)} ห้อง</td><td><span class="badge badge-soft-info">${percent}%</span></td></tr>`;
      }).join('');
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
      activities.innerHTML = (students.studentActivities || []).map((item) => `<div class="soft-card p-3 mb-2"><div class="d-flex justify-content-between gap-3"><strong>${escapeHtml(item.name)}</strong><span class="badge badge-soft-primary">${formatNumber(item.participants)} คน</span></div><div class="small text-muted mt-2">${escapeHtml(item.note)}</div></div>`).join('');
    }

    const scholarships = byId('scholarshipList');
    if (scholarships) {
      scholarships.innerHTML = (students.scholarships || []).map((item) => `<tr><td>${escapeHtml(item.name)}</td><td>${formatNumber(item.count)} คน</td><td>${formatCurrency(item.amount)} บาท</td></tr>`).join('');
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
    setSummaryValues([data.budget.fiscalYear, `${formatCurrency(data.budget.total)} บาท`, `${data.budget.items.length} หมวด`, 'งบประมาณ']);
    const tbody = byId('budgetTableBody');
    if (tbody) {
      tbody.innerHTML = data.budget.items.map((item) => `<tr><td>${escapeHtml(item.category)}</td><td>${formatCurrency(item.amount)}</td><td><span class="badge badge-soft-warning">${formatPercent(item.percent)}%</span></td><td><div class="progress" style="height:0.7rem"><div class="progress-bar bg-primary" style="width:${Number(item.percent) || 0}%"></div></div></td></tr>`).join('');
    }
  }

  function renderAwards(data) {
    if (!data.awards) return;

    const items = data.awards?.items || [];
    setSummaryValues([`${items.length} รายการ`, items.map((item) => Number(item.year)).sort((a, b) => b - a)[0] || '-', 'รางวัล', 'ผลงาน']);
  }

  function renderDownloads(data) {
    const items = data.downloads?.items || [];
    const summary = data.downloads?.summary || {};
    const fileTypes = summary.file_types?.length ? summary.file_types : [...new Set(items.map((item) => item.file_type).filter(Boolean))];
    const latestUpdatedAt = summary.latest_updated_at || items.map((item) => item.updated_at || item.created_at || '').sort().reverse()[0] || '-';
    setSummaryValues([`${Number(summary.total ?? items.length).toLocaleString('th-TH')} รายการ`, fileTypes.join(' / ') || '-', latestUpdatedAt, 'สาธารณะ']);
    const tbody = byId('downloadsTableBody');
    if (tbody) {
      if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">ยังไม่มีข้อมูลเอกสาร</td></tr>';
        return;
      }

      tbody.innerHTML = items.map((item) => {
        const updatedAt = item.updated_at || item.created_at || '-';
        return `<tr><td><div class="fw-semibold">${escapeHtml(item.document_title || '-')}</div>${item.description ? `<div class="small text-muted">${escapeHtml(item.description)}</div>` : ''}</td><td><span class="badge badge-soft-primary">${escapeHtml(item.document_category || item.file_type || '-')}</span></td><td>${escapeHtml(updatedAt)}</td><td><a href="${escapeHtml(item.file_url || '#')}" class="btn btn-sm btn-outline-primary rounded-pill" target="_blank" rel="noopener"><i class="bi bi-download me-1"></i>ดาวน์โหลด</a></td></tr>`;
      }).join('');
    }
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
      // awards.html renders awards through its own inline API script.
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
