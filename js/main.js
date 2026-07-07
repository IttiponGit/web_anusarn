document.addEventListener('DOMContentLoaded', () => {
  const menuBtn = document.getElementById('menuBtn');
  const mainNav = document.getElementById('mainNav');
  const header = document.querySelector('.site-header, .topbar');

  if (header) {
    const updateHeaderState = () => {
      if (window.scrollY > 12) {
        header.classList.add('scrolled');
      } else {
        header.classList.remove('scrolled');
      }
    };

    updateHeaderState();
    window.addEventListener('scroll', updateHeaderState, { passive: true });
  }

  if (menuBtn && mainNav) {
    const closeDropdowns = () => {
      mainNav.querySelectorAll('.nav-dropdown.open').forEach(dropdown => {
        dropdown.classList.remove('open');
        const toggle = dropdown.querySelector('.nav-dropdown-toggle');
        if (toggle) {
          toggle.setAttribute('aria-expanded', 'false');
        }
      });
    };

    const toggleMenu = () => {
      const isOpen = mainNav.classList.toggle('show');
      menuBtn.setAttribute('aria-expanded', String(isOpen));

      if (!isOpen) {
        closeDropdowns();
      }
    };

    menuBtn.addEventListener('click', toggleMenu);

    mainNav.querySelectorAll('.nav-dropdown-toggle').forEach(toggle => {
      toggle.addEventListener('click', () => {
        const dropdown = toggle.closest('.nav-dropdown');
        if (!dropdown) {
          return;
        }

        mainNav.querySelectorAll('.nav-dropdown.open').forEach(openDropdown => {
          if (openDropdown !== dropdown) {
            openDropdown.classList.remove('open');
            const openToggle = openDropdown.querySelector('.nav-dropdown-toggle');
            if (openToggle) {
              openToggle.setAttribute('aria-expanded', 'false');
            }
          }
        });

        const isOpen = dropdown.classList.toggle('open');
        toggle.setAttribute('aria-expanded', String(isOpen));
      });
    });

    mainNav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 800) {
          mainNav.classList.remove('show');
          menuBtn.setAttribute('aria-expanded', 'false');
          closeDropdowns();
        }
      });
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth > 800) {
        mainNav.classList.remove('show');
        menuBtn.setAttribute('aria-expanded', 'false');
        closeDropdowns();
      }
    });
  }

  const newsList = document.getElementById('newsList');
  const allNewsList = document.getElementById('allNewsList');
  const newsDetail = document.getElementById('newsDetail');
  const executiveList = document.getElementById('executiveList');
  const subjectHeadList = document.getElementById('subjectHeadList');
  const personnelPreview = document.getElementById('personnelPreview');
  const downloadsList = document.getElementById('downloadsList');
  const homeStudentCount = document.getElementById('homeStudentCount');
  const homePersonnelCount = document.getElementById('homePersonnelCount');
  const homeClassroomCount = document.getElementById('homeClassroomCount');
  const homeAcademicYear = document.getElementById('homeAcademicYear');
  const contactContent = document.getElementById('contactContent');
  const contactStatus = document.getElementById('contactStatus');
  const contactDetails = document.getElementById('contactDetails');
  const contactFallbackMessage = document.getElementById('contactFallbackMessage');
  const schoolNameText = document.getElementById('schoolNameText');
  const contactAddress = document.getElementById('contactAddress');
  const contactPhone = document.getElementById('contactPhone');
  const contactFax = document.getElementById('contactFax');
  const contactEmail = document.getElementById('contactEmail');
  const contactWebsite = document.getElementById('contactWebsite');
  const contactWorkingHours = document.getElementById('contactWorkingHours');
  const contactFacebook = document.getElementById('contactFacebook');
  const mapStatus = document.getElementById('mapStatus');
  const mapEmbedWrap = document.getElementById('mapEmbedWrap');
  const mapFallback = document.getElementById('mapFallback');
  const contactMapFrame = document.getElementById('contactMapFrame');
  const contactMapLink = document.getElementById('contactMapLink');
  const contactMapFallbackLink = document.getElementById('contactMapFallbackLink');

  if (newsList) {
    loadLatestNews(newsList, 4);
  }

  if (allNewsList) {
    loadAllNews(allNewsList);
  }

  if (newsDetail) {
    loadNewsDetail(newsDetail);
  }

  if (executiveList || subjectHeadList || personnelPreview) {
    loadPersonnelData({ executiveList, subjectHeadList, personnelPreview });
  }

  if (downloadsList) {
    loadDownloads(downloadsList);
  }

  if (homeStudentCount || homePersonnelCount || homeClassroomCount || homeAcademicYear) {
    loadHomepageStats({
      homeStudentCount,
      homePersonnelCount,
      homeClassroomCount,
      homeAcademicYear,
    });
  }

  if (contactContent) {
    loadContactInfo();
  }

  const contactForm = document.querySelector('.contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', event => {
      event.preventDefault();
      alert('ระบบตัวอย่าง: ได้รับข้อความแล้ว');
    });
  }
});

const NEWS_API_URL = 'api/news.php';
const SETTINGS_API_URL = 'api/setting.php';
const CONTACT_API_URL = 'api/contact.php';
const HOMEPAGE_STATS_FALLBACK = {
  personnel_count: null,
  student_count: null,
  classroom_count: null,
  academic_year: null,
};

function normalizeCount(rawValue, fallbackValue) {
  const value = String(rawValue ?? '').trim();
  return /^\d+$/.test(value) ? value : fallbackValue;
}

function normalizeAcademicYear(rawValue, fallbackValue) {
  const value = String(rawValue ?? '').trim();
  return /^\d{4}$/.test(value) ? value : fallbackValue;
}

function pickHomepageStats(settingsPayload) {
  const data = settingsPayload && typeof settingsPayload === 'object' ? settingsPayload.data : null;
  const allSettings = data && typeof data.settings === 'object' ? data.settings : null;

  return {
    personnel_count:
      (data && data.personnel_count)
      ?? (allSettings && allSettings.personnel_count)
      ?? null,
    student_count:
      (data && data.student_count)
      ?? (allSettings && allSettings.student_count)
      ?? null,
    classroom_count:
      (data && data.classroom_count)
      ?? (allSettings && allSettings.classroom_count)
      ?? null,
    academic_year:
      (data && data.academic_year)
      ?? (allSettings && allSettings.academic_year)
      ?? null,
  };
}

function setDynamicContainerBusy(container, isBusy) {
  if (!container) {
    return;
  }

  container.setAttribute('aria-busy', String(isBusy));
}

function renderHomepageStats(elements, stats) {
  const personnelCount = normalizeCount(stats.personnel_count, HOMEPAGE_STATS_FALLBACK.personnel_count);
  const studentCount = normalizeCount(stats.student_count, HOMEPAGE_STATS_FALLBACK.student_count);
  const classroomCount = normalizeCount(stats.classroom_count, HOMEPAGE_STATS_FALLBACK.classroom_count);
  const academicYear = normalizeAcademicYear(stats.academic_year, HOMEPAGE_STATS_FALLBACK.academic_year);

  if (elements.homePersonnelCount) {
    elements.homePersonnelCount.textContent = personnelCount ? `${personnelCount} คน` : 'ไม่พร้อมใช้งาน';
  }

  if (elements.homeStudentCount) {
    elements.homeStudentCount.textContent = studentCount ? `${studentCount} คน` : 'ไม่พร้อมใช้งาน';
  }

  if (elements.homeClassroomCount) {
    elements.homeClassroomCount.textContent = classroomCount ? `${classroomCount} ห้อง` : 'ไม่พร้อมใช้งาน';
  }

  if (elements.homeAcademicYear) {
    elements.homeAcademicYear.textContent = academicYear || 'ไม่พร้อมใช้งาน';
  }
}

function setHomepageStatsLoading(elements) {
  if (elements.homePersonnelCount) {
    elements.homePersonnelCount.textContent = 'กำลังโหลด...';
  }

  if (elements.homeStudentCount) {
    elements.homeStudentCount.textContent = 'กำลังโหลด...';
  }

  if (elements.homeClassroomCount) {
    elements.homeClassroomCount.textContent = 'กำลังโหลด...';
  }

  if (elements.homeAcademicYear) {
    elements.homeAcademicYear.textContent = 'กำลังโหลด...';
  }
}

async function fetchSettingsWithTimeout(url, timeoutMs = 5000) {
  const controller = new AbortController();
  const timeoutId = window.setTimeout(() => controller.abort(), timeoutMs);

  try {
    const response = await fetch(url, { signal: controller.signal });
    if (!response.ok) {
      throw new Error('โหลดข้อมูลตั้งค่าไม่สำเร็จ');
    }

    const payload = await response.json();
    if (!payload || payload.success !== true) {
      throw new Error('รูปแบบข้อมูลตั้งค่าไม่ถูกต้อง');
    }

    return payload;
  } finally {
    window.clearTimeout(timeoutId);
  }
}

function setContactLoadingState(isLoading) {
  const elements = getContactElements();

  if (elements.contactContent) {
    elements.contactContent.setAttribute('aria-busy', String(isLoading));
  }

  if (elements.contactStatus) {
    elements.contactStatus.hidden = !isLoading;
    elements.contactStatus.textContent = isLoading ? 'กำลังโหลดข้อมูลติดต่อ...' : '';
  }

  if (elements.mapStatus) {
    elements.mapStatus.hidden = !isLoading;
    elements.mapStatus.textContent = isLoading ? 'กำลังโหลดข้อมูลแผนที่...' : '';
  }
}

function getContactElements() {
  return {
    contactContent: document.getElementById('contactContent'),
    contactStatus: document.getElementById('contactStatus'),
    contactDetails: document.getElementById('contactDetails'),
    contactFallbackMessage: document.getElementById('contactFallbackMessage'),
    schoolNameText: document.getElementById('schoolNameText'),
    contactAddress: document.getElementById('contactAddress'),
    contactPhone: document.getElementById('contactPhone'),
    contactFax: document.getElementById('contactFax'),
    contactEmail: document.getElementById('contactEmail'),
    contactWebsite: document.getElementById('contactWebsite'),
    contactWorkingHours: document.getElementById('contactWorkingHours'),
    contactFacebook: document.getElementById('contactFacebook'),
    mapStatus: document.getElementById('mapStatus'),
    mapEmbedWrap: document.getElementById('mapEmbedWrap'),
    mapFallback: document.getElementById('mapFallback'),
    contactMapFrame: document.getElementById('contactMapFrame'),
    contactMapLink: document.getElementById('contactMapLink'),
    contactMapFallbackLink: document.getElementById('contactMapFallbackLink'),
  };
}

function normalizeWebsiteUrl(url) {
  const raw = String(url || '').trim();
  if (!raw) {
    return '';
  }

  if (/^https?:\/\//i.test(raw)) {
    return raw;
  }

  return `https://${raw}`;
}

function setParagraphLink(paragraph, { href, text, type }) {
  if (!paragraph) {
    return;
  }

  paragraph.replaceChildren();

  const rawHref = String(href || '').trim();
  const rawText = String(text || '').trim();

  if (!rawHref || !rawText) {
    paragraph.textContent = '-';
    return;
  }

  const link = document.createElement('a');
  if (type === 'tel') {
    link.href = `tel:${rawHref.replace(/[^\d+]/g, '')}`;
  } else if (type === 'email') {
    link.href = `mailto:${rawHref}`;
  } else {
    link.href = rawHref;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
  }
  link.textContent = rawText;
  paragraph.appendChild(link);
}

function setTextContent(target, value, fallbackText = '-') {
  if (!target) {
    return;
  }

  const text = String(value || '').trim();
  target.textContent = text || fallbackText;
}

function renderContactInfo(data) {
  const elements = getContactElements();

  if (!elements.contactContent) {
    return;
  }

  const safeData = data && typeof data === 'object' ? data : {};
  setContactLoadingState(false);

  if (elements.contactStatus) {
    elements.contactStatus.hidden = true;
  }

  if (elements.contactFallbackMessage) {
    elements.contactFallbackMessage.hidden = true;
  }

  if (elements.contactDetails) {
    elements.contactDetails.hidden = false;
  }

  setTextContent(elements.schoolNameText, safeData.school_name);
  setTextContent(elements.contactAddress, safeData.address);
  setParagraphLink(elements.contactPhone, { href: safeData.phone, text: safeData.phone, type: 'tel' });
  setTextContent(elements.contactFax, safeData.fax);
  setParagraphLink(elements.contactEmail, { href: safeData.email, text: safeData.email, type: 'email' });
  setParagraphLink(elements.contactWebsite, {
    href: normalizeWebsiteUrl(safeData.website),
    text: safeData.website,
  });
  setTextContent(elements.contactWorkingHours, safeData.working_hours);
  setParagraphLink(elements.contactFacebook, {
    href: safeData.facebook_url,
    text: safeData.facebook_url ? 'Facebook Page' : '',
  });

  const mapUrl = String(safeData.map_url || '').trim();
  const mapEmbedUrl = String(safeData.map_embed_url || '').trim();
  const hasMapEmbed = Boolean(mapEmbedUrl);
  const hasMapUrl = Boolean(mapUrl);

  if (elements.mapEmbedWrap) {
    elements.mapEmbedWrap.hidden = !hasMapEmbed;
  }

  if (elements.mapFallback) {
    elements.mapFallback.hidden = hasMapEmbed;
  }

  if (elements.contactMapFrame) {
    elements.contactMapFrame.src = hasMapEmbed ? mapEmbedUrl : '';
  }

  if (elements.contactMapLink) {
    elements.contactMapLink.hidden = !hasMapUrl || !hasMapEmbed;
    if (hasMapUrl) {
      elements.contactMapLink.href = mapUrl;
      elements.contactMapLink.textContent = 'เปิด Google Maps';
    } else {
      elements.contactMapLink.removeAttribute('href');
    }
  }

  if (elements.contactMapFallbackLink) {
    elements.contactMapFallbackLink.hidden = !hasMapUrl || hasMapEmbed;
    if (hasMapUrl) {
      elements.contactMapFallbackLink.href = mapUrl;
      elements.contactMapFallbackLink.textContent = 'เปิด Google Maps';
    } else {
      elements.contactMapFallbackLink.removeAttribute('href');
    }
  }
}

function renderContactFallback() {
  const elements = getContactElements();

  if (!elements.contactContent) {
    return;
  }

  setContactLoadingState(false);

  if (elements.contactStatus) {
    elements.contactStatus.hidden = true;
  }

  if (elements.contactDetails) {
    elements.contactDetails.hidden = true;
  }

  if (elements.contactFallbackMessage) {
    elements.contactFallbackMessage.hidden = false;
  }

  if (elements.mapStatus) {
    elements.mapStatus.hidden = true;
  }

  if (elements.mapEmbedWrap) {
    elements.mapEmbedWrap.hidden = true;
  }

  if (elements.mapFallback) {
    elements.mapFallback.hidden = false;
  }

  if (elements.contactMapLink) {
    elements.contactMapLink.hidden = true;
  }

  if (elements.contactMapFallbackLink) {
    elements.contactMapFallbackLink.hidden = true;
  }
}

async function loadContactInfo() {
  setContactLoadingState(true);

  let response;

  try {
    response = await fetch(CONTACT_API_URL, { credentials: 'same-origin' });
  } catch (error) {
    console.error('โหลดข้อมูลติดต่อไม่สำเร็จ:', error);
    renderContactFallback();
    return;
  }

  if (!response.ok) {
    console.error('โหลดข้อมูลติดต่อไม่สำเร็จ: HTTP', response.status);
    renderContactFallback();
    return;
  }

  try {
    const payload = await response.json();
    if (!payload || payload.success !== true || !payload.data) {
      throw new Error('รูปแบบข้อมูลติดต่อไม่ถูกต้อง');
    }

    renderContactInfo(payload.data);
  } catch (error) {
    renderContactFallback();
  }
}

async function loadHomepageStats(elements) {
  setHomepageStatsLoading(elements);

  try {
    const payload = await fetchSettingsWithTimeout(SETTINGS_API_URL);
    const stats = pickHomepageStats(payload);
    renderHomepageStats(elements, stats);
  } catch (error) {
    console.error('โหลดสถิติหน้าแรกไม่สำเร็จ:', error);
    renderHomepageStats(elements, HOMEPAGE_STATS_FALLBACK);
  }
}

function getNewsApiUrl() {
  return NEWS_API_URL;
}

function buildNewsApiRequestUrl(id = null) {
  const url = new URL(getNewsApiUrl(), window.location.href);

  if (id !== null && id !== undefined) {
    url.searchParams.set('id', String(id));
  }

  return url.toString();
}

async function fetchNewsJson(url) {
  const response = await fetch(url);

  if (!response.ok) {
    throw new Error(`โหลดข่าวไม่สำเร็จ: ${url}`);
  }

  return await response.json();
}

function extractApiNewsList(payload) {
  if (payload && payload.success === true && Array.isArray(payload.data)) {
    return payload.data;
  }

  return null;
}

function extractApiNewsItem(payload) {
  if (payload && payload.success === true && payload.data && !Array.isArray(payload.data)) {
    return payload.data;
  }

  return null;
}

async function loadNewsData() {
  try {
    const payload = await fetchNewsJson(buildNewsApiRequestUrl());
    const items = extractApiNewsList(payload);

    if (items) {
      return items;
    }

    throw new Error('รูปแบบข้อมูลข่าวจาก API ไม่ถูกต้อง');
  } catch (error) {
    console.error('โหลดข่าวจาก API ไม่สำเร็จ:', error);
    return null;
  }
}

async function loadNewsItem(id) {
  try {
    const payload = await fetchNewsJson(buildNewsApiRequestUrl(id));
    const item = extractApiNewsItem(payload);

    if (item) {
      return { item, loaded: true };
    }

    throw new Error('รูปแบบข้อมูลข่าวรายตัวจาก API ไม่ถูกต้อง');
  } catch (error) {
    console.error('โหลดข่าวรายตัวจาก API ไม่สำเร็จ:', error);
    return { item: null, loaded: false };
  }
}

function renderNewsError(container, message = 'ไม่พบข่าวประชาสัมพันธ์') {
  if (!container) {
    return;
  }

  setDynamicContainerBusy(container, false);
  container.classList.add('news-list-empty');
  container.innerHTML = `
    <div class="news-empty-state" role="status" aria-live="polite">
      <p class="news-empty-message">${message}</p>
    </div>
  `;
}

function renderNewsCards(container, items) {
  if (!container) {
    return;
  }

  if (!Array.isArray(items) || items.length === 0) {
    renderNewsError(container, 'ไม่พบข่าวประชาสัมพันธ์');
    return;
  }

  container.classList.remove('news-list-empty');
  container.innerHTML = '';
  setDynamicContainerBusy(container, false);

  const fragment = document.createDocumentFragment();

  items.forEach(item => {
    const article = document.createElement('article');
    article.className = 'card news-card';

    article.innerHTML = `
      <div class="news-media">
        <img src="${item.image || ''}" alt="${item.title}" class="news-image">
      </div>
      <span class="news-category">${item.category || 'ข่าว'}</span>
      <h3>${item.title}</h3>
      <p>${item.summary || item.content || ''}</p>
      <small>${item.date || ''}</small>
      <a href="news-detail.html?id=${item.id}" class="readmore" aria-label="อ่านเพิ่มเติม ${item.title}">อ่านเพิ่มเติม <span aria-hidden="true">→</span></a>
    `;

    fragment.appendChild(article);
  });

  container.appendChild(fragment);
}

async function loadLatestNews(container, limit = 3) {
  setDynamicContainerBusy(container, true);
  const items = await loadNewsData();

  if (!items) {
    renderNewsError(container);
    return;
  }

  renderNewsCards(container, items.slice(0, limit));
}

async function loadAllNews(container) {
  setDynamicContainerBusy(container, true);
  const items = await loadNewsData();

  if (!items) {
    renderNewsError(container);
    return;
  }

  renderNewsCards(container, items);
}

async function loadNewsDetail(container) {
  setDynamicContainerBusy(container, true);
  const params = new URLSearchParams(window.location.search);
  const id = Number(params.get('id'));

  if (!Number.isInteger(id) || id <= 0) {
    setDynamicContainerBusy(container, false);
    container.innerHTML = '<p class="news-empty">ไม่พบข่าวที่คุณเลือก</p>';
    return;
  }

  const result = await loadNewsItem(id);

  if (!result.loaded) {
    renderNewsError(container);
    return;
  }

  if (!result.item) {
    setDynamicContainerBusy(container, false);
    container.innerHTML = '<p class="news-empty">ไม่พบข่าวที่คุณเลือก</p>';
    return;
  }

  const item = result.item;

  setDynamicContainerBusy(container, false);
  container.innerHTML = `
    <article class="card news-detail-card">
      <div class="news-media">
        <img src="${item.image || ''}" alt="${item.title}" class="news-image">
      </div>
      <span class="news-category">${item.category || 'ข่าว'}</span>
      <h3>${item.title}</h3>
      <div class="news-detail-meta">
        <small>${item.date || ''}</small>
      </div>
      <div class="news-detail-body">
        <p>${item.content || ''}</p>
      </div>
      <a href="news.html" class="readmore">← กลับหน้าข่าวทั้งหมด</a>
    </article>
  `;
}

function createPersonnelAvatar(person) {
  const imagePath = typeof person.image === 'string' ? person.image.trim() : '';

  return `
    <div class="personnel-media${imagePath ? '' : ' fallback'}">
      <img src="${imagePath}" alt="${person.name}" class="personnel-photo" loading="lazy" decoding="async">
      <div class="avatar personnel-avatar-fallback">${person.initial}</div>
    </div>
  `;
}

function bindPersonnelImageFallback(container) {
  if (!container) {
    return;
  }

  container.querySelectorAll('.personnel-media').forEach(media => {
    const img = media.querySelector('.personnel-photo');

    if (!img || !img.getAttribute('src')) {
      media.classList.add('fallback');
      return;
    }

    img.addEventListener('error', () => {
      media.classList.add('fallback');
    }, { once: true });
  });
}

async function loadPersonnelData({ executiveList, subjectHeadList, personnelPreview }) {
  setDynamicContainerBusy(executiveList, true);
  setDynamicContainerBusy(subjectHeadList, true);
  setDynamicContainerBusy(personnelPreview, true);

  try {
    const data = await loadPersonnelSourceData();

    if (executiveList) {
      executiveList.innerHTML = '';
      data.executives.forEach(person => {
        executiveList.innerHTML += `
          <article class="card personnel-card executive-card">
            ${createPersonnelAvatar(person)}
            <span class="role-label">ฝ่ายบริหาร</span>
            <h3>${person.name}</h3>
            <p>${person.position}</p>
          </article>
        `;
      });

      bindPersonnelImageFallback(executiveList);
      setDynamicContainerBusy(executiveList, false);
    }

    if (subjectHeadList) {
      subjectHeadList.innerHTML = '';
      data.heads.forEach(person => {
        subjectHeadList.innerHTML += `
          <article class="card personnel-card subject-card">
            ${createPersonnelAvatar(person)}
            <span class="role-label">หัวหน้ากลุ่มสาระ</span>
            <h3>${person.name}</h3>
            <p>${person.department}</p>
          </article>
        `;
      });

      bindPersonnelImageFallback(subjectHeadList);
      setDynamicContainerBusy(subjectHeadList, false);
    }

    if (personnelPreview) {
      const previewExecutives = data.executives.slice(0, 3);
      personnelPreview.innerHTML = '';
      previewExecutives.forEach(person => {
        personnelPreview.innerHTML += `
          <article class="card personnel-card executive-card">
            ${createPersonnelAvatar(person)}
            <h3>${person.name}</h3>
            <p>${person.position}</p>
          </article>
        `;
      });

      bindPersonnelImageFallback(personnelPreview);
      setDynamicContainerBusy(personnelPreview, false);
    }
  } catch (error) {
    console.error('โหลดข้อมูลบุคลากรไม่สำเร็จ:', error);

    if (executiveList) {
      executiveList.innerHTML = '<p class="news-empty">ไม่สามารถโหลดข้อมูลบุคลากรได้</p>';
      setDynamicContainerBusy(executiveList, false);
    }

    if (subjectHeadList) {
      subjectHeadList.innerHTML = '<p class="news-empty">ไม่สามารถโหลดข้อมูลบุคลากรได้</p>';
      setDynamicContainerBusy(subjectHeadList, false);
    }

    if (personnelPreview) {
      personnelPreview.innerHTML = '<p class="news-empty">ไม่สามารถโหลดข้อมูลบุคลากรได้</p>';
      setDynamicContainerBusy(personnelPreview, false);
    }
  }
}

const PERSONNEL_API_URL = 'api/personnel.php';
const PERSONNEL_FALLBACK_URL = 'data/personnel.json';

function getPersonnelApiUrl() {
  return PERSONNEL_API_URL;
}

function normalizePersonnelData(payload) {
  if (!payload || typeof payload !== 'object') {
    return null;
  }

  if (Array.isArray(payload.executives) && Array.isArray(payload.heads)) {
    return payload;
  }

  if (!(payload.success === true && Array.isArray(payload.data))) {
    return null;
  }

  const executives = [];
  const heads = [];

  payload.data.forEach(person => {
    if (!person || typeof person !== 'object') {
      return;
    }

    const department = typeof person.department === 'string' ? person.department.trim() : '';
    const groupName = typeof person.group_name === 'string' ? person.group_name.trim() : '';

    const normalized = {
      name: person.name || '',
      position: person.position || '',
      department,
      initial: person.initial || '',
      image: person.image || ''
    };

    if (department === 'ฝ่ายบริหาร') {
      executives.push(normalized);
      return;
    }

    if (department === 'หัวหน้ากลุ่มสาระ') {
      heads.push({
        ...normalized,
        department: groupName || department
      });
    }
  });

  return { executives, heads };
}

async function fetchPersonnelSource(url) {
  const response = await fetch(url);

  if (!response.ok) {
    throw new Error(`โหลดข้อมูลบุคลากรไม่สำเร็จ: ${url}`);
  }

  const payload = await response.json();
  const normalized = normalizePersonnelData(payload);

  if (!normalized) {
    throw new Error('รูปแบบข้อมูลบุคลากรไม่ถูกต้อง');
  }

  return normalized;
}

async function loadPersonnelSourceData() {
  try {
    return await fetchPersonnelSource(getPersonnelApiUrl());
  } catch (error) {
    console.error('โหลดบุคลากรจาก API ไม่สำเร็จ, กำลัง fallback ไปใช้ JSON:', error);
    return await fetchPersonnelSource(PERSONNEL_FALLBACK_URL);
  }
}

const DOWNLOADS_API_URL = 'api/downloads.php';

function getDownloadsApiUrl() {
  return DOWNLOADS_API_URL;
}

function normalizeDownloadItem(item) {
  if (!item || typeof item !== 'object') {
    return null;
  }

  const fallbackFile = typeof item.file === 'string' ? item.file : '';
  const fileUrl = typeof item.file_url === 'string' ? item.file_url : fallbackFile;
  const buttonText = typeof item.button_text === 'string' ? item.button_text : 'ดาวน์โหลดเอกสาร';
  const displayOrder = Number.isFinite(Number(item.display_order))
    ? Number(item.display_order)
    : Number(item.id) || 0;

  return {
    id: item.id ?? null,
    title: item.title || '',
    description: item.description || '',
    category: item.category || 'เอกสาร',
    file_url: fileUrl,
    button_text: buttonText,
    display_order: displayOrder
  };
}

function normalizeDownloadsPayload(payload) {
  const rawItems = Array.isArray(payload)
    ? payload
    : payload && payload.success === true && Array.isArray(payload.data)
      ? payload.data
      : null;

  if (!rawItems) {
    return null;
  }

  return rawItems
    .map(normalizeDownloadItem)
    .filter(Boolean)
    .sort((a, b) => a.display_order - b.display_order);
}

async function loadDownloadsData() {
  const response = await fetch(getDownloadsApiUrl());

  if (!response.ok) {
    throw new Error('โหลดข้อมูลเอกสารจาก API ไม่สำเร็จ');
  }

  const payload = await response.json();
  const normalized = normalizeDownloadsPayload(payload);

  if (!normalized) {
    throw new Error('รูปแบบข้อมูลเอกสารจาก API ไม่ถูกต้อง');
  }

  return normalized;
}

function renderDownloadsError(container, message = 'ไม่สามารถโหลดรายการเอกสารได้') {
  if (!container) {
    return;
  }

  setDynamicContainerBusy(container, false);
  container.classList.add('downloads-list-empty');
  container.innerHTML = `
    <div class="news-empty-state" role="status" aria-live="polite">
      <p class="news-empty-message">${message}</p>
    </div>
  `;
}

function renderDownloads(container, items) {
  if (!container) {
    return;
  }

  if (!Array.isArray(items) || items.length === 0) {
    renderDownloadsError(container, 'ยังไม่มีรายการเอกสาร');
    return;
  }

  container.classList.remove('downloads-list-empty');
  container.innerHTML = '';
  setDynamicContainerBusy(container, false);

  const fragment = document.createDocumentFragment();

  items.forEach(item => {
    const article = document.createElement('article');
    article.className = 'card download-card';

    const fileUrl = typeof item.file_url === 'string' ? item.file_url.trim() : '';
    const isAvailable = fileUrl.length > 0;
    const buttonText = isAvailable
      ? ((typeof item.button_text === 'string' && item.button_text.trim()) || 'ดาวน์โหลดเอกสาร')
      : 'รอเพิ่มไฟล์';
    const actionMarkup = isAvailable
      ? `<a class="download-action" href="${fileUrl}" download>${buttonText}</a>`
      : '<button class="download-action" type="button" disabled>รอเพิ่มไฟล์</button>';

    article.innerHTML = `
      <span class="download-category">${item.category || 'เอกสาร'}</span>
      <h3>${item.title}</h3>
      <p>${item.description || ''}</p>
      <div class="download-card-footer">
        <span class="download-status ${isAvailable ? 'available' : 'pending'}">${isAvailable ? 'พร้อมดาวน์โหลด' : 'รอเพิ่มไฟล์'}</span>
        ${actionMarkup}
      </div>
    `;

    fragment.appendChild(article);
  });

  container.appendChild(fragment);
}

async function loadDownloads(container) {
  setDynamicContainerBusy(container, true);
  try {
    const items = await loadDownloadsData();
    renderDownloads(container, items);
  } catch (error) {
    console.error('โหลดข้อมูลเอกสารไม่สำเร็จ:', error);
    renderDownloadsError(container, 'ไม่สามารถโหลดรายการเอกสารได้');
  }
}
