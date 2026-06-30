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
    const toggleMenu = () => {
      const isOpen = mainNav.classList.toggle('show');
      menuBtn.setAttribute('aria-expanded', String(isOpen));
    };

    menuBtn.addEventListener('click', toggleMenu);

    mainNav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 800) {
          mainNav.classList.remove('show');
          menuBtn.setAttribute('aria-expanded', 'false');
        }
      });
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth > 800) {
        mainNav.classList.remove('show');
        menuBtn.setAttribute('aria-expanded', 'false');
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

  const contactForm = document.querySelector('.contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', event => {
      event.preventDefault();
      alert('ระบบตัวอย่าง: ได้รับข้อความแล้ว');
    });
  }
});

const NEWS_API_URL = 'api/news.php';
const NEWS_API_LOCALHOST_URL = 'http://anusarn-deaf.ac.th/api/news.php';
const NEWS_FALLBACK_URL = 'data/news.json';

function getNewsApiUrl() {
  const hostname = window.location.hostname;

  if (hostname === 'localhost' || hostname === '127.0.0.1') {
    return NEWS_API_LOCALHOST_URL;
  }

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

function extractNewsList(payload) {
  if (Array.isArray(payload)) {
    return payload;
  }

  if (payload && Array.isArray(payload.data)) {
    return payload.data;
  }

  return null;
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

function extractNewsItem(payload, id) {
  if (payload && payload.data && !Array.isArray(payload.data)) {
    return payload.data;
  }

  const items = extractNewsList(payload);

  if (!items) {
    return null;
  }

  return items.find(newsItem => Number(newsItem.id) === id) || null;
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
    console.error('โหลดข่าวจาก API ไม่สำเร็จ, กำลัง fallback ไปใช้ JSON:', error);

    try {
      const fallbackPayload = await fetchNewsJson(NEWS_FALLBACK_URL);
      return extractNewsList(fallbackPayload);
    } catch (fallbackError) {
      console.error('โหลดข่าวจากไฟล์สำรองไม่สำเร็จ:', fallbackError);
      return null;
    }
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
    console.error('โหลดข่าวรายตัวจาก API ไม่สำเร็จ, กำลัง fallback ไปใช้ JSON:', error);

    try {
      const fallbackPayload = await fetchNewsJson(NEWS_FALLBACK_URL);
      return {
        item: extractNewsItem(fallbackPayload, id),
        loaded: true
      };
    } catch (fallbackError) {
      console.error('โหลดข่าวรายตัวจากไฟล์สำรองไม่สำเร็จ:', fallbackError);
      return { item: null, loaded: false };
    }
  }
}

function renderNewsError(container, message = 'ไม่สามารถโหลดข่าวประชาสัมพันธ์ได้') {
  if (!container) {
    return;
  }

  container.innerHTML = `<p class="news-empty">${message}</p>`;
}

function renderNewsCards(container, items) {
  if (!container) {
    return;
  }

  if (!Array.isArray(items) || items.length === 0) {
    renderNewsError(container, 'ยังไม่มีข่าวประชาสัมพันธ์');
    return;
  }

  container.innerHTML = '';

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
  const items = await loadNewsData();

  if (!items) {
    renderNewsError(container);
    return;
  }

  renderNewsCards(container, items.slice(0, limit));
}

async function loadAllNews(container) {
  const items = await loadNewsData();

  if (!items) {
    renderNewsError(container);
    return;
  }

  renderNewsCards(container, items);
}

async function loadNewsDetail(container) {
  const params = new URLSearchParams(window.location.search);
  const id = Number(params.get('id'));

  if (!Number.isInteger(id) || id <= 0) {
    container.innerHTML = '<p class="news-empty">ไม่พบข่าวที่คุณเลือก</p>';
    return;
  }

  const result = await loadNewsItem(id);

  if (!result.loaded) {
    renderNewsError(container);
    return;
  }

  if (!result.item) {
    container.innerHTML = '<p class="news-empty">ไม่พบข่าวที่คุณเลือก</p>';
    return;
  }

  const item = result.item;

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
    }
  } catch (error) {
    console.error('โหลดข้อมูลบุคลากรไม่สำเร็จ:', error);

    if (executiveList) {
      executiveList.innerHTML = '<p class="news-empty">ไม่สามารถโหลดข้อมูลบุคลากรได้</p>';
    }

    if (subjectHeadList) {
      subjectHeadList.innerHTML = '<p class="news-empty">ไม่สามารถโหลดข้อมูลบุคลากรได้</p>';
    }

    if (personnelPreview) {
      personnelPreview.innerHTML = '<p class="news-empty">ไม่สามารถโหลดข้อมูลบุคลากรได้</p>';
    }
  }
}

const PERSONNEL_API_URL = 'api/personnel.php';
const PERSONNEL_API_LOCALHOST_URL = 'http://anusarn-deaf.ac.th/api/personnel.php';
const PERSONNEL_FALLBACK_URL = 'data/personnel.json';

function getPersonnelApiUrl() {
  const hostname = window.location.hostname;

  if (hostname === 'localhost' || hostname === '127.0.0.1') {
    return PERSONNEL_API_LOCALHOST_URL;
  }

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
const DOWNLOADS_API_LOCALHOST_URL = 'http://anusarn-deaf.ac.th/api/downloads.php';
const DOWNLOADS_FALLBACK_URL = 'data/downloads.json';

function getDownloadsApiUrl() {
  const hostname = window.location.hostname;

  if (hostname === 'localhost' || hostname === '127.0.0.1') {
    return DOWNLOADS_API_LOCALHOST_URL;
  }

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
  try {
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
  } catch (error) {
    console.error('โหลดเอกสารจาก API ไม่สำเร็จ, กำลัง fallback ไปใช้ JSON:', error);

    try {
      const fallbackResponse = await fetch(DOWNLOADS_FALLBACK_URL);

      if (!fallbackResponse.ok) {
        throw new Error('โหลดข้อมูลเอกสารสำรองไม่สำเร็จ');
      }

      const fallbackPayload = await fallbackResponse.json();
      return normalizeDownloadsPayload(fallbackPayload);
    } catch (fallbackError) {
      console.error('โหลดข้อมูลเอกสารจากไฟล์สำรองไม่สำเร็จ:', fallbackError);
      return null;
    }
  }
}

function renderDownloadsError(container, message = 'ไม่สามารถโหลดรายการเอกสารได้') {
  if (!container) {
    return;
  }

  container.innerHTML = `<p class="news-empty">${message}</p>`;
}

function renderDownloads(container, items) {
  if (!container) {
    return;
  }

  if (!Array.isArray(items) || items.length === 0) {
    renderDownloadsError(container, 'ยังไม่มีรายการเอกสาร');
    return;
  }

  container.innerHTML = '';

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
  const items = await loadDownloadsData();

  if (!items) {
    renderDownloadsError(container);
    return;
  }

  renderDownloads(container, items);
}
