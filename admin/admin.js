(function () {
  const NEWS_API_URL = '../api/admin/news_admin.php';
  const PERSONNEL_API_URL = '../api/admin/personnel_admin.php';

  const loginForm = document.getElementById('loginForm');
  const loginMessage = document.getElementById('loginMessage');
  const logoutBtn = document.getElementById('logoutBtn');
  const adminName = document.getElementById('adminName');
  const statsMessage = document.getElementById('statsMessage');
  const newsCount = document.getElementById('newsCount');
  const personnelCount = document.getElementById('personnelCount');
  const downloadsCount = document.getElementById('downloadsCount');
  const adminCount = document.getElementById('adminCount');
  const newsTableBody = document.getElementById('newsTableBody');
  const newsMessage = document.getElementById('newsMessage');
  const addNewsBtn = document.getElementById('addNewsBtn');
  const newsFormCard = document.getElementById('newsFormCard');
  const newsForm = document.getElementById('newsForm');
  const newsFormTitle = document.getElementById('newsFormTitle');
  const newsId = document.getElementById('newsId');
  const newsTitle = document.getElementById('newsTitle');
  const newsSummary = document.getElementById('newsSummary');
  const newsContent = document.getElementById('newsContent');
  const newsDate = document.getElementById('newsDate');
  const newsCategory = document.getElementById('newsCategory');
  const newsImage = document.getElementById('newsImage');
  const newsStatus = document.getElementById('newsStatus');
  const cancelNewsBtn = document.getElementById('cancelNewsBtn');
  const personnelTableBody = document.getElementById('personnelTableBody');
  const personnelMessage = document.getElementById('personnelMessage');
  const addPersonnelBtn = document.getElementById('addPersonnelBtn');
  const personnelFormCard = document.getElementById('personnelFormCard');
  const personnelForm = document.getElementById('personnelForm');
  const personnelFormTitle = document.getElementById('personnelFormTitle');
  const personnelId = document.getElementById('personnelId');
  const personnelName = document.getElementById('personnelName');
  const personnelPosition = document.getElementById('personnelPosition');
  const personnelDepartment = document.getElementById('personnelDepartment');
  const personnelGroupName = document.getElementById('personnelGroupName');
  const personnelImage = document.getElementById('personnelImage');
  const personnelDisplayOrder = document.getElementById('personnelDisplayOrder');
  const personnelStatus = document.getElementById('personnelStatus');
  const cancelPersonnelBtn = document.getElementById('cancelPersonnelBtn');

  async function fetchMe() {
    const response = await fetch('../api/admin/me.php', {
      credentials: 'same-origin'
    });

    if (!response.ok) {
      return null;
    }

    const data = await response.json();
    return data.success && data.logged_in ? data.data : null;
  }

  async function login(username, password) {
    const formData = new FormData();
    formData.append('username', username);
    formData.append('password', password);

    const response = await fetch('../api/admin/auth.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    });

    return response.json();
  }

  async function fetchDashboardStats() {
    const response = await fetch('../api/admin/dashboard_stats.php', {
      credentials: 'same-origin'
    });

    if (response.status === 401) {
      return { unauthorized: true };
    }

    if (!response.ok) {
      throw new Error('Failed to fetch dashboard stats');
    }

    const data = await response.json();

    if (!data.success || !data.data) {
      throw new Error('Invalid dashboard stats response');
    }

    return data.data;
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function setNewsMessage(message, isError = false) {
    if (!newsMessage) {
      return;
    }

    newsMessage.textContent = message;
    newsMessage.classList.toggle('error-message', isError);
  }

  function showNewsForm(isOpen) {
    if (!newsFormCard) {
      return;
    }

    newsFormCard.hidden = !isOpen;
  }

  function clearNewsForm() {
    if (!newsForm) {
      return;
    }

    newsForm.reset();
    newsId.value = '';
    newsStatus.value = 'published';
  }

  function openCreateNewsForm() {
    clearNewsForm();
    if (newsFormTitle) {
      newsFormTitle.textContent = 'เพิ่มข่าว';
    }
    showNewsForm(true);
  }

  async function requestNewsApi(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
    });

    if (response.status === 401) {
      window.location.href = 'login.html';
      return null;
    }

    let data = null;

    try {
      data = await response.json();
    } catch (error) {
      throw new Error('Invalid server response');
    }

    if (!response.ok || !data.success) {
      throw new Error(data.error || 'เกิดข้อผิดพลาดจากเซิร์ฟเวอร์');
    }

    return data;
  }

  function renderNewsTable(list) {
    if (!newsTableBody) {
      return;
    }

    if (!Array.isArray(list) || list.length === 0) {
      newsTableBody.innerHTML = '<tr><td colspan="5">ยังไม่มีรายการข่าว</td></tr>';
      return;
    }

    newsTableBody.innerHTML = list.map((item) => `
      <tr>
        <td>${escapeHtml(item.title)}</td>
        <td>${escapeHtml(item.category)}</td>
        <td>${escapeHtml(item.date)}</td>
        <td>${escapeHtml(item.status || '-')}</td>
        <td>
          <div class="row-actions">
            <button type="button" class="table-btn edit-news-btn" data-id="${item.id}">แก้ไข</button>
            <button type="button" class="table-btn delete-news-btn" data-id="${item.id}">ลบ</button>
          </div>
        </td>
      </tr>
    `).join('');
  }

  async function fetchNewsList() {
    if (!newsTableBody) {
      return;
    }

    newsTableBody.innerHTML = '<tr><td colspan="5">กำลังโหลดข้อมูล...</td></tr>';

    try {
      const result = await requestNewsApi(NEWS_API_URL);
      if (!result) {
        return;
      }
      renderNewsTable(result.data || []);
    } catch (error) {
      newsTableBody.innerHTML = '<tr><td colspan="5">โหลดข้อมูลไม่สำเร็จ</td></tr>';
      setNewsMessage(error.message, true);
    }
  }

  async function loadNewsDetail(id) {
    const result = await requestNewsApi(`${NEWS_API_URL}?id=${id}`);
    return result ? result.data : null;
  }

  function fillNewsForm(news) {
    newsId.value = news.id || '';
    newsTitle.value = news.title || '';
    newsSummary.value = news.summary || '';
    newsContent.value = news.content || '';
    newsDate.value = news.date || '';
    newsCategory.value = news.category || '';
    newsImage.value = news.image || '';
    newsStatus.value = news.status || 'published';
  }

  function readNewsFormPayload() {
    return {
      id: newsId.value ? Number(newsId.value) : undefined,
      title: newsTitle.value.trim(),
      summary: newsSummary.value.trim(),
      content: newsContent.value.trim(),
      date: newsDate.value.trim(),
      category: newsCategory.value.trim(),
      image: newsImage.value.trim(),
      status: newsStatus.value.trim(),
    };
  }

  async function handleEditNews(id) {
    setNewsMessage('กำลังโหลดข้อมูลข่าว...');
    try {
      const news = await loadNewsDetail(id);
      if (!news) {
        return;
      }

      fillNewsForm(news);
      if (newsFormTitle) {
        newsFormTitle.textContent = 'แก้ไขข่าว';
      }
      showNewsForm(true);
      setNewsMessage('');
    } catch (error) {
      setNewsMessage(error.message, true);
    }
  }

  async function handleDeleteNews(id) {
    const confirmed = window.confirm('ยืนยันการลบข่าวนี้ใช่หรือไม่?');
    if (!confirmed) {
      return;
    }

    setNewsMessage('กำลังลบข้อมูล...');

    try {
      await requestNewsApi(NEWS_API_URL, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id }),
      });

      setNewsMessage('ลบข่าวเรียบร้อยแล้ว');
      await fetchNewsList();
    } catch (error) {
      setNewsMessage(error.message, true);
    }
  }

  async function handleNewsFormSubmit(event) {
    event.preventDefault();

    const payload = readNewsFormPayload();
    const isEditMode = Boolean(payload.id);

    setNewsMessage('กำลังบันทึกข้อมูล...');

    try {
      await requestNewsApi(NEWS_API_URL, {
        method: isEditMode ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload),
      });

      setNewsMessage(isEditMode ? 'แก้ไขข่าวเรียบร้อยแล้ว' : 'เพิ่มข่าวเรียบร้อยแล้ว');
      showNewsForm(false);
      clearNewsForm();
      await fetchNewsList();
    } catch (error) {
      setNewsMessage(error.message, true);
    }
  }

  async function initNewsPage() {
    const admin = await fetchMe();

    if (!admin) {
      window.location.href = 'login.html';
      return;
    }

    if (adminName) {
      adminName.textContent = `${admin.full_name} (${admin.role})`;
    }

    await fetchNewsList();

    if (addNewsBtn) {
      addNewsBtn.addEventListener('click', () => {
        openCreateNewsForm();
        setNewsMessage('');
      });
    }

    if (cancelNewsBtn) {
      cancelNewsBtn.addEventListener('click', () => {
        showNewsForm(false);
        clearNewsForm();
        setNewsMessage('');
      });
    }

    if (newsForm) {
      newsForm.addEventListener('submit', handleNewsFormSubmit);
    }

    newsTableBody.addEventListener('click', async (event) => {
      const editBtn = event.target.closest('.edit-news-btn');
      if (editBtn) {
        const id = Number(editBtn.dataset.id);
        if (id > 0) {
          await handleEditNews(id);
        }
        return;
      }

      const deleteBtn = event.target.closest('.delete-news-btn');
      if (deleteBtn) {
        const id = Number(deleteBtn.dataset.id);
        if (id > 0) {
          await handleDeleteNews(id);
        }
      }
    });
  }

  function setPersonnelMessage(message, isError = false) {
    if (!personnelMessage) {
      return;
    }

    personnelMessage.textContent = message;
    personnelMessage.classList.toggle('error-message', isError);
  }

  function showPersonnelForm(isOpen) {
    if (!personnelFormCard) {
      return;
    }

    personnelFormCard.hidden = !isOpen;
  }

  function clearPersonnelForm() {
    if (!personnelForm) {
      return;
    }

    personnelForm.reset();
    personnelId.value = '';
    personnelDisplayOrder.value = '0';
    personnelStatus.value = 'active';
  }

  function openCreatePersonnelForm() {
    clearPersonnelForm();
    if (personnelFormTitle) {
      personnelFormTitle.textContent = 'เพิ่มบุคลากร';
    }
    showPersonnelForm(true);
  }

  async function requestPersonnelApi(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
    });

    if (response.status === 401) {
      window.location.href = 'login.html';
      return null;
    }

    let data = null;

    try {
      data = await response.json();
    } catch (error) {
      throw new Error('Invalid server response');
    }

    if (!response.ok || !data.success) {
      throw new Error(data.error || 'เกิดข้อผิดพลาดจากเซิร์ฟเวอร์');
    }

    return data;
  }

  function renderPersonnelTable(list) {
    if (!personnelTableBody) {
      return;
    }

    if (!Array.isArray(list) || list.length === 0) {
      personnelTableBody.innerHTML = '<tr><td colspan="7">ยังไม่มีรายการบุคลากร</td></tr>';
      return;
    }

    personnelTableBody.innerHTML = list.map((item) => `
      <tr>
        <td>${escapeHtml(item.name)}</td>
        <td>${escapeHtml(item.position)}</td>
        <td>${escapeHtml(item.department)}</td>
        <td>${escapeHtml(item.group_name)}</td>
        <td>${escapeHtml(item.display_order)}</td>
        <td>${escapeHtml(item.status || '-')}</td>
        <td>
          <div class="row-actions">
            <button type="button" class="table-btn edit-personnel-btn" data-id="${item.id}">แก้ไข</button>
            <button type="button" class="table-btn delete-news-btn delete-personnel-btn" data-id="${item.id}">ปิดใช้งาน</button>
          </div>
        </td>
      </tr>
    `).join('');
  }

  async function fetchPersonnelList() {
    if (!personnelTableBody) {
      return;
    }

    personnelTableBody.innerHTML = '<tr><td colspan="7">กำลังโหลดข้อมูล...</td></tr>';

    try {
      const result = await requestPersonnelApi(PERSONNEL_API_URL);
      if (!result) {
        return;
      }
      renderPersonnelTable(result.data || []);
    } catch (error) {
      personnelTableBody.innerHTML = '<tr><td colspan="7">โหลดข้อมูลไม่สำเร็จ</td></tr>';
      setPersonnelMessage(error.message, true);
    }
  }

  async function loadPersonnelDetail(id) {
    const result = await requestPersonnelApi(`${PERSONNEL_API_URL}?id=${id}`);
    return result ? result.data : null;
  }

  function fillPersonnelForm(personnel) {
    personnelId.value = personnel.id || '';
    personnelName.value = personnel.name || '';
    personnelPosition.value = personnel.position || '';
    personnelDepartment.value = personnel.department || '';
    personnelGroupName.value = personnel.group_name || '';
    personnelImage.value = personnel.image || '';
    personnelDisplayOrder.value = String(Number(personnel.display_order ?? 0));
    personnelStatus.value = personnel.status || 'active';
  }

  function readPersonnelFormPayload() {
    const parsedDisplayOrder = Number(personnelDisplayOrder.value);
    return {
      id: personnelId.value ? Number(personnelId.value) : undefined,
      name: personnelName.value.trim(),
      position: personnelPosition.value.trim(),
      department: personnelDepartment.value.trim(),
      group_name: personnelGroupName.value.trim(),
      image: personnelImage.value.trim(),
      display_order: Number.isFinite(parsedDisplayOrder) ? parsedDisplayOrder : NaN,
      status: personnelStatus.value.trim(),
    };
  }

  async function handleEditPersonnel(id) {
    setPersonnelMessage('กำลังโหลดข้อมูลบุคลากร...');
    try {
      const personnel = await loadPersonnelDetail(id);
      if (!personnel) {
        return;
      }

      fillPersonnelForm(personnel);
      if (personnelFormTitle) {
        personnelFormTitle.textContent = 'แก้ไขบุคลากร';
      }
      showPersonnelForm(true);
      setPersonnelMessage('');
    } catch (error) {
      setPersonnelMessage(error.message, true);
    }
  }

  async function handleDeletePersonnel(id) {
    const confirmed = window.confirm('ยืนยันการปิดใช้งานบุคลากรนี้ใช่หรือไม่?');
    if (!confirmed) {
      return;
    }

    setPersonnelMessage('กำลังอัปเดตสถานะ...');

    try {
      await requestPersonnelApi(PERSONNEL_API_URL, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id }),
      });

      setPersonnelMessage('ปิดใช้งานบุคลากรเรียบร้อยแล้ว');
      await fetchPersonnelList();
    } catch (error) {
      setPersonnelMessage(error.message, true);
    }
  }

  async function handlePersonnelFormSubmit(event) {
    event.preventDefault();

    const payload = readPersonnelFormPayload();
    const isEditMode = Boolean(payload.id);

    if (!Number.isInteger(payload.display_order) || payload.display_order < 0) {
      setPersonnelMessage('กรุณากรอก display_order เป็นเลขจำนวนเต็มตั้งแต่ 0 ขึ้นไป', true);
      return;
    }

    setPersonnelMessage('กำลังบันทึกข้อมูล...');

    try {
      await requestPersonnelApi(PERSONNEL_API_URL, {
        method: isEditMode ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload),
      });

      setPersonnelMessage(isEditMode ? 'แก้ไขบุคลากรเรียบร้อยแล้ว' : 'เพิ่มบุคลากรเรียบร้อยแล้ว');
      showPersonnelForm(false);
      clearPersonnelForm();
      await fetchPersonnelList();
    } catch (error) {
      setPersonnelMessage(error.message, true);
    }
  }

  async function initPersonnelPage() {
    const admin = await fetchMe();

    if (!admin) {
      window.location.href = 'login.html';
      return;
    }

    if (adminName) {
      adminName.textContent = `${admin.full_name} (${admin.role})`;
    }

    await fetchPersonnelList();

    if (addPersonnelBtn) {
      addPersonnelBtn.addEventListener('click', () => {
        openCreatePersonnelForm();
        setPersonnelMessage('');
      });
    }

    if (cancelPersonnelBtn) {
      cancelPersonnelBtn.addEventListener('click', () => {
        showPersonnelForm(false);
        clearPersonnelForm();
        setPersonnelMessage('');
      });
    }

    if (personnelForm) {
      personnelForm.addEventListener('submit', handlePersonnelFormSubmit);
    }

    personnelTableBody.addEventListener('click', async (event) => {
      const editBtn = event.target.closest('.edit-personnel-btn');
      if (editBtn) {
        const id = Number(editBtn.dataset.id);
        if (id > 0) {
          await handleEditPersonnel(id);
        }
        return;
      }

      const deleteBtn = event.target.closest('.delete-personnel-btn');
      if (deleteBtn) {
        const id = Number(deleteBtn.dataset.id);
        if (id > 0) {
          await handleDeletePersonnel(id);
        }
      }
    });
  }

  function renderDashboardStats(stats) {
    if (!newsCount || !personnelCount || !downloadsCount || !adminCount) {
      return;
    }

    newsCount.textContent = String(stats.news_count);
    personnelCount.textContent = String(stats.personnel_count);
    downloadsCount.textContent = String(stats.downloads_count);
    adminCount.textContent = String(stats.admin_count);
  }

  async function initDashboard() {
    const admin = await fetchMe();

    if (!admin) {
      window.location.href = 'login.html';
      return;
    }

    adminName.textContent = `${admin.full_name} (${admin.role})`;

    try {
      const stats = await fetchDashboardStats();

      if (stats && stats.unauthorized) {
        window.location.href = 'login.html';
        return;
      }

      renderDashboardStats(stats);
      if (statsMessage) {
        statsMessage.textContent = '';
      }
    } catch (error) {
      if (statsMessage) {
        statsMessage.textContent = 'โหลดข้อมูลไม่สำเร็จ';
      }
    }
  }

  async function logout() {
    await fetch('../api/admin/logout.php', {
      method: 'POST',
      credentials: 'same-origin'
    });
    window.location.href = 'login.html';
  }

  if (loginForm) {
    loginForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      loginMessage.textContent = 'กำลังตรวจสอบข้อมูล...';

      const username = document.getElementById('username').value.trim();
      const password = document.getElementById('password').value;

      try {
        const result = await login(username, password);

        if (result.success) {
          window.location.href = 'dashboard.html';
          return;
        }

        loginMessage.textContent = result.error || 'เข้าสู่ระบบไม่สำเร็จ';
      } catch (error) {
        loginMessage.textContent = 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้';
      }
    });

    fetchMe().then((admin) => {
      if (admin) {
        window.location.href = 'dashboard.html';
      }
    });
  }

  if (adminName && statsMessage) {
    initDashboard();
  }

  if (newsTableBody) {
    initNewsPage();
  }

  if (personnelTableBody) {
    initPersonnelPage();
  }

  if (logoutBtn) {
    logoutBtn.addEventListener('click', logout);
  }
})();
