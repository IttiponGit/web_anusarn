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
    loadLatestNews(newsList, 3);
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

async function loadNewsData() {
  try {
    const response = await fetch('data/news.json');

    if (!response.ok) {
      throw new Error('โหลดไฟล์ข่าวไม่สำเร็จ');
    }

    return await response.json();
  } catch (error) {
    console.error('โหลดข่าวไม่สำเร็จ:', error);
    return null;
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
  const items = await loadNewsData();

  if (!items) {
    renderNewsError(container);
    return;
  }

  const item = items.find(newsItem => Number(newsItem.id) === id);

  if (!item) {
    container.innerHTML = '<p class="news-empty">ไม่พบข่าวที่คุณเลือก</p>';
    return;
  }

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

async function loadPersonnelData({ executiveList, subjectHeadList, personnelPreview }) {
  try {
    const response = await fetch('data/personnel.json');

    if (!response.ok) {
      throw new Error('โหลดข้อมูลบุคลากรไม่สำเร็จ');
    }

    const data = await response.json();

    if (executiveList) {
      data.executives.forEach(person => {
        executiveList.innerHTML += `
          <article class="card personnel-card executive-card">
            <div class="avatar">${person.initial}</div>
            <span class="role-label">ฝ่ายบริหาร</span>
            <h3>${person.name}</h3>
            <p>${person.position}</p>
          </article>
        `;
      });
    }

    if (subjectHeadList) {
      data.heads.forEach(person => {
        subjectHeadList.innerHTML += `
          <article class="card personnel-card subject-card">
            <div class="avatar">${person.initial}</div>
            <span class="role-label">หัวหน้ากลุ่มสาระ</span>
            <h3>${person.name}</h3>
            <p>${person.department}</p>
          </article>
        `;
      });
    }

    if (personnelPreview) {
      const previewExecutives = data.executives.slice(0, 3);
      personnelPreview.innerHTML = '';
      previewExecutives.forEach(person => {
        personnelPreview.innerHTML += `
          <article class="card personnel-card executive-card">
            <div class="avatar">${person.initial}</div>
            <h3>${person.name}</h3>
            <p>${person.position}</p>
          </article>
        `;
      });
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

async function loadDownloadsData() {
  try {
    const response = await fetch('data/downloads.json');

    if (!response.ok) {
      throw new Error('โหลดข้อมูลเอกสารไม่สำเร็จ');
    }

    return await response.json();
  } catch (error) {
    console.error('โหลดข้อมูลเอกสารไม่สำเร็จ:', error);
    return null;
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

    const isAvailable = Boolean(item.available && item.file);
    const actionMarkup = isAvailable
      ? `<a class="download-action" href="${item.file}" download>ดาวน์โหลดเอกสาร</a>`
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
