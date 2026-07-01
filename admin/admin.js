(function () {
  const NEWS_API_URL = '../api/admin/news_admin.php';
  const PERSONNEL_API_URL = '../api/admin/personnel_admin.php';
  const DOWNLOADS_API_URL = '../api/admin/downloads_admin.php';
  const UPLOAD_API_URL = '../api/admin/upload.php';

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
  const newsUploadId = document.getElementById('newsUploadId');
  const newsImageFile = document.getElementById('newsImageFile');
  const uploadNewsImageBtn = document.getElementById('uploadNewsImageBtn');
  const newsImagePreviewWrap = document.getElementById('newsImagePreviewWrap');
  const newsImagePreview = document.getElementById('newsImagePreview');
  const newsStatus = document.getElementById('newsStatus');
  const cancelNewsBtn = document.getElementById('cancelNewsBtn');
  const downloadsTableBody = document.getElementById('downloadsTableBody');
  const downloadsMessage = document.getElementById('downloadsMessage');
  const addDownloadBtn = document.getElementById('addDownloadBtn');
  const downloadsFormCard = document.getElementById('downloadsFormCard');
  const downloadsForm = document.getElementById('downloadsForm');
  const downloadsFormTitle = document.getElementById('downloadsFormTitle');
  const downloadId = document.getElementById('downloadId');
  const downloadUploadId = document.getElementById('downloadUploadId');
  const downloadTitle = document.getElementById('downloadTitle');
  const downloadDescription = document.getElementById('downloadDescription');
  const downloadCategory = document.getElementById('downloadCategory');
  const downloadDisplayOrder = document.getElementById('downloadDisplayOrder');
  const downloadFileUrl = document.getElementById('downloadFileUrl');
  const downloadFile = document.getElementById('downloadFile');
  const uploadDownloadFileBtn = document.getElementById('uploadDownloadFileBtn');
  const downloadButtonText = document.getElementById('downloadButtonText');
  const downloadStatus = document.getElementById('downloadStatus');
  const cancelDownloadBtn = document.getElementById('cancelDownloadBtn');
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
  const personnelImageFile = document.getElementById('personnelImageFile');
  const uploadPersonnelImageBtn = document.getElementById('uploadPersonnelImageBtn');
  const personnelImagePreviewWrap = document.getElementById('personnelImagePreviewWrap');
  const personnelImagePreview = document.getElementById('personnelImagePreview');
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

  function showImagePreview(pathOrUrl) {
    if (!newsImagePreview || !newsImagePreviewWrap) {
      return;
    }

    const value = String(pathOrUrl || '').trim();
    if (!value) {
      newsImagePreview.removeAttribute('src');
      newsImagePreviewWrap.hidden = true;
      return;
    }

    const previewSrc = value.startsWith('uploads/') ? `../${value}` : value;
    newsImagePreview.src = previewSrc;
    newsImagePreviewWrap.hidden = false;
  }

  async function uploadFileViaApi(file, { category, relatedType, relatedId }) {
    const formData = new FormData();
    formData.append('file', file);
    if (category) {
      formData.append('category', category);
    }
    if (relatedType) {
      formData.append('related_type', relatedType);
    }
    if (Number.isInteger(relatedId) && relatedId > 0) {
      formData.append('related_id', String(relatedId));
    }

    const response = await fetch(UPLOAD_API_URL, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    });

    if (response.status === 401) {
      window.location.href = 'login.html';
      return null;
    }

    let data = null;
    try {
      data = await response.json();
    } catch (error) {
      throw new Error('Invalid upload response');
    }

    if (!response.ok || !data.success) {
      throw new Error(data.message || data.error || 'อัปโหลดไฟล์ไม่สำเร็จ');
    }

    return data;
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
    if (newsUploadId) {
      newsUploadId.value = '';
    }
    newsStatus.value = 'published';
    showImagePreview('');
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
    if (newsUploadId) {
      newsUploadId.value = '';
    }
    newsStatus.value = news.status || 'published';
    showImagePreview(news.image || '');
  }

  function readNewsFormPayload() {
    const parsedUploadId = Number(newsUploadId ? newsUploadId.value : '');

    return {
      id: newsId.value ? Number(newsId.value) : undefined,
      title: newsTitle.value.trim(),
      summary: newsSummary.value.trim(),
      content: newsContent.value.trim(),
      date: newsDate.value.trim(),
      category: newsCategory.value.trim(),
      image: newsImage.value.trim(),
      status: newsStatus.value.trim(),
      upload_id: Number.isInteger(parsedUploadId) && parsedUploadId > 0 ? parsedUploadId : undefined,
    };
  }

  function handleNewsImageFileSelect() {
    if (!newsImageFile || !newsImageFile.files || newsImageFile.files.length === 0) {
      return;
    }

    const selectedFile = newsImageFile.files[0];
    const objectUrl = URL.createObjectURL(selectedFile);
    showImagePreview(objectUrl);
  }

  async function handleUploadNewsImage() {
    if (!newsImageFile || !newsImageFile.files || newsImageFile.files.length === 0) {
      setNewsMessage('กรุณาเลือกรูปภาพก่อนอัปโหลด', true);
      return;
    }

    const selectedFile = newsImageFile.files[0];
    const relatedId = Number(newsId.value);

    setNewsMessage('กำลังอัปโหลดรูปภาพ...');

    try {
      const result = await uploadFileViaApi(selectedFile, {
        category: 'news_image',
        relatedType: 'news',
        relatedId: Number.isInteger(relatedId) && relatedId > 0 ? relatedId : undefined,
      });

      if (!result) {
        return;
      }

      newsImage.value = result.file_path || '';
      if (newsUploadId) {
        newsUploadId.value = String(result.upload_id || '');
      }
      showImagePreview(result.file_path || '');
      setNewsMessage('อัปโหลดรูปภาพสำเร็จ');
    } catch (error) {
      setNewsMessage(error.message, true);
    }
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

    if (newsImageFile) {
      newsImageFile.addEventListener('change', handleNewsImageFileSelect);
    }

    if (uploadNewsImageBtn) {
      uploadNewsImageBtn.addEventListener('click', handleUploadNewsImage);
    }

    if (newsImage) {
      newsImage.addEventListener('input', () => {
        showImagePreview(newsImage.value);
      });
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

  function showPersonnelImagePreview(pathOrUrl) {
    if (!personnelImagePreview || !personnelImagePreviewWrap) {
      return;
    }

    const value = String(pathOrUrl || '').trim();
    if (!value) {
      personnelImagePreview.removeAttribute('src');
      personnelImagePreviewWrap.hidden = true;
      return;
    }

    const isAbsoluteOrSpecialUrl = /^(?:https?:)?\/\//i.test(value)
      || value.startsWith('/')
      || value.startsWith('../')
      || value.startsWith('data:')
      || value.startsWith('blob:');
    const previewSrc = isAbsoluteOrSpecialUrl ? value : `../${value}`;
    personnelImagePreview.src = previewSrc;
    personnelImagePreviewWrap.hidden = false;
  }

  function isAllowedPersonnelImageFile(file) {
    if (!file) {
      return false;
    }

    const allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    const lowerName = String(file.name || '').toLowerCase();
    const hasAllowedExtension = /\.(jpg|jpeg|png|webp|gif)$/i.test(lowerName);
    const hasAllowedMimeType = allowedMimeTypes.includes(String(file.type || '').toLowerCase());

    return hasAllowedExtension && hasAllowedMimeType;
  }

  function showPersonnelForm(isOpen) {
    if (!personnelFormCard) {
      return;
    }

    personnelFormCard.hidden = !isOpen;
  }

  function setSelectValueFromOptions(selectElement, rawValue) {
    if (!selectElement) {
      return;
    }

    const value = String(rawValue || '').trim();
    const hasMatchingOption = Array.from(selectElement.options).some((optionElement) => optionElement.value === value);
    selectElement.value = hasMatchingOption ? value : '';
  }

  function normalizePersonnelGroupName(rawValue) {
    const value = String(rawValue || '').trim();
    return value === 'ฝ่ายบริหาร' ? 'ผู้บริหาร' : value;
  }

  function clearPersonnelForm() {
    if (!personnelForm) {
      return;
    }

    personnelForm.reset();
    personnelId.value = '';
    personnelDisplayOrder.value = '0';
    personnelStatus.value = 'active';
    showPersonnelImagePreview('');
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
    const personnelPath = String(personnel.path || personnel.image || '').trim();

    personnelId.value = personnel.id || '';
    personnelName.value = personnel.name || '';
    personnelPosition.value = personnel.position || '';
    setSelectValueFromOptions(personnelDepartment, personnel.department || '');
    setSelectValueFromOptions(personnelGroupName, normalizePersonnelGroupName(personnel.group_name || ''));
    personnelImage.value = personnelPath;
    personnelDisplayOrder.value = String(Number(personnel.display_order ?? 0));
    personnelStatus.value = personnel.status || 'active';
    showPersonnelImagePreview(personnelPath);
  }

  function handlePersonnelImageFileSelect() {
    if (!personnelImageFile || !personnelImageFile.files || personnelImageFile.files.length === 0) {
      return;
    }

    const selectedFile = personnelImageFile.files[0];
    if (!isAllowedPersonnelImageFile(selectedFile)) {
      setPersonnelMessage('รองรับเฉพาะไฟล์รูปภาพ JPG, JPEG, PNG, WEBP และ GIF', true);
      personnelImageFile.value = '';
      return;
    }

    const objectUrl = URL.createObjectURL(selectedFile);
    showPersonnelImagePreview(objectUrl);
    setPersonnelMessage('');
  }

  async function handleUploadPersonnelImage() {
    if (!personnelImageFile || !personnelImageFile.files || personnelImageFile.files.length === 0) {
      setPersonnelMessage('กรุณาเลือกรูปภาพก่อนอัปโหลด', true);
      return;
    }

    const selectedFile = personnelImageFile.files[0];
    if (!isAllowedPersonnelImageFile(selectedFile)) {
      setPersonnelMessage('รองรับเฉพาะไฟล์รูปภาพ JPG, JPEG, PNG, WEBP และ GIF', true);
      return;
    }

    setPersonnelMessage('กำลังอัปโหลดรูปภาพ...');

    try {
      const result = await uploadFileViaApi(selectedFile, {
        category: 'personnel_image',
      });

      if (!result) {
        return;
      }

      if (result.file_type && result.file_type !== 'image') {
        throw new Error('ไฟล์ที่อัปโหลดต้องเป็นรูปภาพเท่านั้น');
      }

      personnelImage.value = result.file_path || '';
      showPersonnelImagePreview(result.file_path || '');
      setPersonnelMessage('อัปโหลดรูปภาพสำเร็จ');
    } catch (error) {
      setPersonnelMessage(error.message, true);
    }
  }

  function readPersonnelFormPayload() {
    const parsedDisplayOrder = Number(personnelDisplayOrder.value);
    const imageValue = personnelImage.value.trim();

    return {
      id: personnelId.value ? Number(personnelId.value) : undefined,
      name: personnelName.value.trim(),
      position: personnelPosition.value.trim(),
      department: personnelDepartment.value.trim(),
      group_name: personnelGroupName.value.trim(),
      image: imageValue,
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

    if (personnelImageFile) {
      personnelImageFile.addEventListener('change', handlePersonnelImageFileSelect);
    }

    if (uploadPersonnelImageBtn) {
      uploadPersonnelImageBtn.addEventListener('click', handleUploadPersonnelImage);
    }

    if (personnelImagePreview) {
      personnelImagePreview.addEventListener('error', () => {
        personnelImagePreview.removeAttribute('src');
        personnelImagePreviewWrap.hidden = true;
      });
    }

    if (personnelImage) {
      personnelImage.addEventListener('input', () => {
        showPersonnelImagePreview(personnelImage.value);
      });
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

  function setDownloadsMessage(message, isError = false) {
    if (!downloadsMessage) {
      return;
    }

    downloadsMessage.textContent = message;
    downloadsMessage.classList.toggle('error-message', isError);
  }

  function showDownloadsForm(isOpen) {
    if (!downloadsFormCard) {
      return;
    }

    downloadsFormCard.hidden = !isOpen;
  }

  function clearDownloadsForm() {
    if (!downloadsForm) {
      return;
    }

    downloadsForm.reset();
    downloadId.value = '';
    if (downloadUploadId) {
      downloadUploadId.value = '';
    }
    downloadDisplayOrder.value = '0';
    downloadStatus.value = 'active';
  }

  function openCreateDownloadForm() {
    clearDownloadsForm();
    if (downloadsFormTitle) {
      downloadsFormTitle.textContent = 'เพิ่มเอกสาร';
    }
    showDownloadsForm(true);
  }

  async function requestDownloadsApi(url, options = {}) {
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

  function renderDownloadsTable(list) {
    if (!downloadsTableBody) {
      return;
    }

    if (!Array.isArray(list) || list.length === 0) {
      downloadsTableBody.innerHTML = '<tr><td colspan="7">ยังไม่มีรายการเอกสาร</td></tr>';
      return;
    }

    downloadsTableBody.innerHTML = list.map((item) => {
      const filePath = item.file_url || '-';
      const mimeType = typeof item.upload_mime_type === 'string' ? item.upload_mime_type : '';
      const fileTypeRaw = typeof item.upload_file_type === 'string' ? item.upload_file_type : '';
      const fileType = fileTypeRaw ? fileTypeRaw.toUpperCase() : 'N/A';
      const fileSize = Number(item.upload_file_size);
      const formattedFileSize = Number.isFinite(fileSize) && fileSize > 0 ? formatFileSize(fileSize) : 'N/A';

      return `
      <tr>
        <td>${escapeHtml(item.title)}</td>
        <td>${escapeHtml(item.category)}</td>
        <td>
          <div>${escapeHtml(filePath)}</div>
          <div class="download-meta-badges">
            <span class="meta-badge">ชนิด: ${escapeHtml(fileType)}</span>
            <span class="meta-badge">ขนาด: ${escapeHtml(formattedFileSize)}</span>
            <span class="meta-badge">MIME: ${escapeHtml(mimeType || 'N/A')}</span>
          </div>
        </td>
        <td>${escapeHtml(item.button_text || '-')}</td>
        <td>${escapeHtml(item.display_order ?? '-')}</td>
        <td>${escapeHtml(item.status || '-')}</td>
        <td>
          <div class="row-actions">
            <button type="button" class="table-btn edit-download-btn" data-id="${item.id}">แก้ไข</button>
            <button type="button" class="table-btn delete-news-btn delete-download-btn" data-id="${item.id}">ปิดใช้งาน</button>
          </div>
        </td>
      </tr>
    `;
    }).join('');
  }

  function formatFileSize(bytes) {
    if (!Number.isFinite(bytes) || bytes <= 0) {
      return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let value = bytes;
    let index = 0;

    while (value >= 1024 && index < units.length - 1) {
      value /= 1024;
      index += 1;
    }

    const rounded = index === 0 ? String(Math.round(value)) : value.toFixed(2);
    return `${rounded} ${units[index]}`;
  }

  async function fetchDownloadsList() {
    if (!downloadsTableBody) {
      return;
    }

    downloadsTableBody.innerHTML = '<tr><td colspan="7">กำลังโหลดข้อมูล...</td></tr>';

    try {
      const result = await requestDownloadsApi(DOWNLOADS_API_URL);
      if (!result) {
        return;
      }
      renderDownloadsTable(result.data || []);
    } catch (error) {
      downloadsTableBody.innerHTML = '<tr><td colspan="7">โหลดข้อมูลไม่สำเร็จ</td></tr>';
      setDownloadsMessage(error.message, true);
    }
  }

  async function loadDownloadDetail(id) {
    const result = await requestDownloadsApi(`${DOWNLOADS_API_URL}?id=${id}`);
    return result ? result.data : null;
  }

  function fillDownloadsForm(item) {
    downloadId.value = item.id || '';
    if (downloadUploadId) {
      downloadUploadId.value = '';
    }
    downloadTitle.value = item.title || '';
    downloadDescription.value = item.description || '';
    downloadCategory.value = item.category || '';
    downloadDisplayOrder.value = String(Number(item.display_order ?? 0));
    downloadFileUrl.value = item.file_url || '';
    downloadButtonText.value = item.button_text || 'ดาวน์โหลดเอกสาร';
    downloadStatus.value = item.status || 'active';
  }

  function readDownloadsFormPayload() {
    const parsedDisplayOrder = Number(downloadDisplayOrder.value);
    const parsedUploadId = Number(downloadUploadId ? downloadUploadId.value : '');
    const fileUrl = downloadFileUrl.value.trim();
    let buttonText = downloadButtonText.value.trim();

    if (fileUrl !== '' && (buttonText === '' || buttonText === 'รอเพิ่มไฟล์')) {
      buttonText = 'ดาวน์โหลด';
    }

    return {
      id: downloadId.value ? Number(downloadId.value) : undefined,
      title: downloadTitle.value.trim(),
      description: downloadDescription.value.trim(),
      category: downloadCategory.value.trim(),
      file_url: fileUrl,
      button_text: buttonText,
      display_order: Number.isFinite(parsedDisplayOrder) ? parsedDisplayOrder : NaN,
      status: downloadStatus.value.trim(),
      upload_id: Number.isInteger(parsedUploadId) && parsedUploadId > 0 ? parsedUploadId : undefined,
    };
  }

  async function handleUploadDownloadFile() {
    if (!downloadFile || !downloadFile.files || downloadFile.files.length === 0) {
      setDownloadsMessage('กรุณาเลือกไฟล์เอกสารก่อนอัปโหลด', true);
      return;
    }

    const selectedFile = downloadFile.files[0];
    const relatedId = Number(downloadId.value);

    setDownloadsMessage('กำลังอัปโหลดไฟล์...');

    try {
      const result = await uploadFileViaApi(selectedFile, {
        category: 'download_file',
        relatedType: 'downloads',
        relatedId: Number.isInteger(relatedId) && relatedId > 0 ? relatedId : undefined,
      });

      if (!result) {
        return;
      }

      downloadFileUrl.value = result.file_path || '';
      if (downloadUploadId) {
        downloadUploadId.value = String(result.upload_id || '');
      }
      if (downloadButtonText) {
        const currentButtonText = downloadButtonText.value.trim();
        if (currentButtonText === '' || currentButtonText === 'รอเพิ่มไฟล์') {
          downloadButtonText.value = 'ดาวน์โหลด';
        }
      }
      setDownloadsMessage('อัปโหลดไฟล์สำเร็จ');
    } catch (error) {
      setDownloadsMessage(error.message, true);
    }
  }

  async function handleEditDownload(id) {
    setDownloadsMessage('กำลังโหลดข้อมูลเอกสาร...');
    try {
      const item = await loadDownloadDetail(id);
      if (!item) {
        return;
      }

      fillDownloadsForm(item);
      if (downloadsFormTitle) {
        downloadsFormTitle.textContent = 'แก้ไขเอกสาร';
      }
      showDownloadsForm(true);
      setDownloadsMessage('');
    } catch (error) {
      setDownloadsMessage(error.message, true);
    }
  }

  async function handleDeleteDownload(id) {
    const confirmed = window.confirm('ยืนยันการปิดใช้งานเอกสารนี้ใช่หรือไม่?');
    if (!confirmed) {
      return;
    }

    setDownloadsMessage('กำลังอัปเดตสถานะ...');

    try {
      await requestDownloadsApi(DOWNLOADS_API_URL, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id }),
      });

      setDownloadsMessage('ปิดใช้งานเอกสารเรียบร้อยแล้ว');
      await fetchDownloadsList();
    } catch (error) {
      setDownloadsMessage(error.message, true);
    }
  }

  async function handleDownloadsFormSubmit(event) {
    event.preventDefault();

    const payload = readDownloadsFormPayload();
    const isEditMode = Boolean(payload.id);

    if (!Number.isInteger(payload.display_order) || payload.display_order < 0) {
      setDownloadsMessage('กรุณากรอก display_order เป็นเลขจำนวนเต็มตั้งแต่ 0 ขึ้นไป', true);
      return;
    }

    setDownloadsMessage('กำลังบันทึกข้อมูล...');

    try {
      await requestDownloadsApi(DOWNLOADS_API_URL, {
        method: isEditMode ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload),
      });

      setDownloadsMessage(isEditMode ? 'แก้ไขเอกสารเรียบร้อยแล้ว' : 'เพิ่มเอกสารเรียบร้อยแล้ว');
      showDownloadsForm(false);
      clearDownloadsForm();
      await fetchDownloadsList();
    } catch (error) {
      setDownloadsMessage(error.message, true);
    }
  }

  async function initDownloadsPage() {
    const admin = await fetchMe();

    if (!admin) {
      window.location.href = 'login.html';
      return;
    }

    if (adminName) {
      adminName.textContent = `${admin.full_name} (${admin.role})`;
    }

    await fetchDownloadsList();

    if (addDownloadBtn) {
      addDownloadBtn.addEventListener('click', () => {
        openCreateDownloadForm();
        setDownloadsMessage('');
      });
    }

    if (cancelDownloadBtn) {
      cancelDownloadBtn.addEventListener('click', () => {
        showDownloadsForm(false);
        clearDownloadsForm();
        setDownloadsMessage('');
      });
    }

    if (downloadsForm) {
      downloadsForm.addEventListener('submit', handleDownloadsFormSubmit);
    }

    if (uploadDownloadFileBtn) {
      uploadDownloadFileBtn.addEventListener('click', handleUploadDownloadFile);
    }

    downloadsTableBody.addEventListener('click', async (event) => {
      const editBtn = event.target.closest('.edit-download-btn');
      if (editBtn) {
        const id = Number(editBtn.dataset.id);
        if (id > 0) {
          await handleEditDownload(id);
        }
        return;
      }

      const deleteBtn = event.target.closest('.delete-download-btn');
      if (deleteBtn) {
        const id = Number(deleteBtn.dataset.id);
        if (id > 0) {
          await handleDeleteDownload(id);
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

  if (downloadsTableBody) {
    initDownloadsPage();
  }

  if (logoutBtn) {
    logoutBtn.addEventListener('click', logout);
  }
})();
