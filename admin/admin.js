(function () {
  const CANONICAL_PRODUCTION_HOST = 'anusarn-deaf.ac.th';
  if (window.location.hostname.toLowerCase() === `www.${CANONICAL_PRODUCTION_HOST}`) {
    const canonicalUrl = new URL(window.location.href);
    canonicalUrl.hostname = CANONICAL_PRODUCTION_HOST;
    window.location.replace(canonicalUrl.href);
    return;
  }

  const AUTH_API_URL = '/api/admin/auth.php';
  const ME_API_URL = '/api/admin/me.php';
  const LOGOUT_API_URL = '/api/admin/logout.php';
  const NEWS_API_URL = '/api/admin/news_admin.php';
  const PERSONNEL_API_URL = '/api/admin/personnel_admin.php';
  const DOWNLOADS_API_URL = '/api/admin/downloads_admin.php';
  const CONTACT_ADMIN_API_URL = '/api/admin/contact_admin.php';
  const UPLOAD_API_URL = '/api/admin/upload.php';
  const SETTINGS_ADMIN_API_URL = '/api/admin/settings_admin.php';

  const loginForm = document.getElementById('loginForm');
  const loginMessage = document.getElementById('loginMessage');
  const logoutBtn = document.getElementById('logoutBtn');
  const adminName = document.getElementById('adminName');
  const statsMessage = document.getElementById('statsMessage');
  const newsCount = document.getElementById('newsCount');
  const personnelCount = document.getElementById('personnelCount');
  const downloadsCount = document.getElementById('downloadsCount');
  const adminCount = document.getElementById('adminCount');
  const homepageStatsForm = document.getElementById('homepageStatsForm');
  const homepageStatsMessage = document.getElementById('homepageStatsMessage');
  const homepagePersonnelCount = document.getElementById('homepagePersonnelCount');
  const homepageStudentCount = document.getElementById('homepageStudentCount');
  const homepageClassroomCount = document.getElementById('homepageClassroomCount');
  const homepageAcademicYear = document.getElementById('homepageAcademicYear');
  const saveHomepageStatsBtn = document.getElementById('saveHomepageStatsBtn');
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
  const saveNewsBtn = document.getElementById('saveNewsBtn');
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
  const saveDownloadBtn = document.getElementById('saveDownloadBtn');
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
  const personnelSubjectGroup = document.getElementById('subject_group') || document.getElementById('personnelDepartment');
  const personnelGroupName = document.getElementById('group_name') || document.getElementById('personnelGroupName');
  const personnelImage = document.getElementById('personnelImage');
  const personnelImageFile = document.getElementById('personnelImageFile');
  const uploadPersonnelImageBtn = document.getElementById('uploadPersonnelImageBtn');
  const personnelImagePreviewWrap = document.getElementById('personnelImagePreviewWrap');
  const personnelImagePreview = document.getElementById('personnelImagePreview');
  const personnelDisplayOrder = document.getElementById('personnelDisplayOrder');
  const personnelStatus = document.getElementById('personnelStatus');
  const savePersonnelBtn = document.getElementById('savePersonnelBtn');
  const cancelPersonnelBtn = document.getElementById('cancelPersonnelBtn');
  const contactForm = document.getElementById('contactForm');
  const contactMessage = document.getElementById('contactMessage');
  const contactFormTitle = document.getElementById('contactFormTitle');
  const contactSaveBtn = document.getElementById('saveContactBtn');
  const contactSchoolName = document.getElementById('schoolName');
  const contactAddress = document.getElementById('address');
  const contactPhone = document.getElementById('phone');
  const contactFax = document.getElementById('fax');
  const contactEmail = document.getElementById('email');
  const contactWebsite = document.getElementById('website');
  const contactFacebookUrl = document.getElementById('facebookUrl');
  const contactWorkingHours = document.getElementById('workingHours');
  const contactMapUrl = document.getElementById('mapUrl');
  const contactMapEmbedUrl = document.getElementById('mapEmbedUrl');

  const MAX_IMAGE_FILE_SIZE_BYTES = 5 * 1024 * 1024;
  const MAX_DOCUMENT_FILE_SIZE_BYTES = 15 * 1024 * 1024;
  const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
  const ALLOWED_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
  const ALLOWED_DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
  const ALLOWED_DOCUMENT_MIME_TYPES = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
  ];
  const BLOCKED_FILE_EXTENSIONS = [
    'php', 'phtml', 'php3', 'php4', 'php5', 'phar',
    'js', 'mjs', 'cjs', 'html', 'htm',
    'exe', 'bat', 'cmd', 'com', 'scr', 'dll', 'msi',
  ];

  const requestLocks = {
    contactSubmit: false,
    homepageStatsSubmit: false,
    newsSubmit: false,
    newsDelete: false,
    newsUpload: false,
    personnelSubmit: false,
    personnelDelete: false,
    personnelUpload: false,
    downloadsSubmit: false,
    downloadsDelete: false,
    downloadsUpload: false,
  };

  let confirmModalElement = null;
  let confirmModalResolve = null;
  let previousActiveElement = null;
  let confirmModalIsProcessing = false;

  function getConfirmToneClass(tone) {
    if (tone === 'danger') {
      return 'confirm-modal-danger';
    }

    if (tone === 'warning') {
      return 'confirm-modal-warning';
    }

    if (tone === 'success') {
      return 'confirm-modal-success';
    }

    return 'confirm-modal-info';
  }

  function ensureConfirmModal() {
    if (confirmModalElement) {
      return confirmModalElement;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'confirm-modal-overlay';
    wrapper.hidden = true;
    wrapper.innerHTML = `
      <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle" aria-describedby="confirmModalMessage">
        <div class="confirm-modal-header">
          <h3 id="confirmModalTitle" class="confirm-modal-title"></h3>
        </div>
        <p id="confirmModalMessage" class="confirm-modal-message"></p>
        <div class="confirm-modal-actions">
          <button type="button" class="secondary-btn confirm-modal-cancel">ยกเลิก</button>
          <button type="button" class="primary-btn confirm-modal-confirm">ยืนยัน</button>
        </div>
      </div>
    `;

    document.body.appendChild(wrapper);
    confirmModalElement = wrapper;

    wrapper.dataset.allowOverlayClose = 'true';

    return wrapper;
  }

  function cleanupConfirmModalListeners() {
    if (!confirmModalElement || !confirmModalElement._modalHandlers) {
      return;
    }

    const {
      overlayClick,
      keydown,
      cancelClick,
      confirmClick,
      cancelBtn,
      confirmBtn,
    } = confirmModalElement._modalHandlers;

    confirmModalElement.removeEventListener('click', overlayClick);
    confirmModalElement.removeEventListener('keydown', keydown);
    cancelBtn.removeEventListener('click', cancelClick);
    confirmBtn.removeEventListener('click', confirmClick);
    delete confirmModalElement._modalHandlers;
  }

  function bindConfirmModalListeners() {
    if (!confirmModalElement) {
      return;
    }

    cleanupConfirmModalListeners();

    const cancelBtn = confirmModalElement.querySelector('.confirm-modal-cancel');
    const confirmBtn = confirmModalElement.querySelector('.confirm-modal-confirm');

    const overlayClick = (event) => {
      const allowOverlayClose = confirmModalElement.dataset.allowOverlayClose === 'true';
      if (event.target === confirmModalElement && allowOverlayClose && !confirmModalIsProcessing) {
        closeConfirmModal(false);
      }
    };

    const keydown = (event) => {
      if (event.key === 'Escape') {
        if (confirmModalIsProcessing) {
          return;
        }

        event.preventDefault();
        closeConfirmModal(false);
        return;
      }

      if (event.key !== 'Tab') {
        return;
      }

      const focusable = confirmModalElement.querySelectorAll('button:not([disabled])');
      if (!focusable.length) {
        return;
      }

      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };

    const cancelClick = () => {
      if (confirmModalIsProcessing) {
        return;
      }

      closeConfirmModal(false);
    };

    const confirmClick = () => {
      if (confirmModalIsProcessing) {
        return;
      }

      confirmModalIsProcessing = true;
      confirmBtn.disabled = true;
      cancelBtn.disabled = true;
      closeConfirmModal(true, { force: true });
    };

    confirmModalElement.addEventListener('click', overlayClick);
    confirmModalElement.addEventListener('keydown', keydown);
    cancelBtn.addEventListener('click', cancelClick);
    confirmBtn.addEventListener('click', confirmClick);

    confirmModalElement._modalHandlers = {
      overlayClick,
      keydown,
      cancelClick,
      confirmClick,
      cancelBtn,
      confirmBtn,
    };
  }

  function closeConfirmModal(confirmed, options = {}) {
    if (!confirmModalElement) {
      return;
    }

    if (confirmModalIsProcessing && !options.force) {
      return;
    }

    const modal = confirmModalElement.querySelector('.confirm-modal');
    const confirmBtn = confirmModalElement.querySelector('.confirm-modal-confirm');
    const cancelBtn = confirmModalElement.querySelector('.confirm-modal-cancel');

    cleanupConfirmModalListeners();

    confirmModalElement.hidden = true;
    confirmModalElement.dataset.allowOverlayClose = 'true';
    document.body.classList.remove('confirm-modal-open');
    modal.classList.remove('confirm-modal-danger', 'confirm-modal-warning', 'confirm-modal-success', 'confirm-modal-info');
    confirmBtn.disabled = false;
    cancelBtn.disabled = false;
    confirmModalIsProcessing = false;

    try {
      if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
        previousActiveElement.focus();
      }
    } catch (error) {
      console.error(error);
    }

    previousActiveElement = null;

    const resolver = confirmModalResolve;
    confirmModalResolve = null;
    if (resolver) {
      resolver(Boolean(confirmed));
    }
  }

  function openConfirmModal({ title, message, confirmText = 'ยืนยัน', cancelText = 'ยกเลิก', tone = 'info', allowOverlayClose = null }) {
    const wrapper = ensureConfirmModal();
    const modal = wrapper.querySelector('.confirm-modal');
    const titleElement = wrapper.querySelector('.confirm-modal-title');
    const messageElement = wrapper.querySelector('.confirm-modal-message');
    const confirmBtn = wrapper.querySelector('.confirm-modal-confirm');
    const cancelBtn = wrapper.querySelector('.confirm-modal-cancel');

    if (confirmModalResolve) {
      closeConfirmModal(false, { force: true });
    }

    titleElement.textContent = String(title || 'ยืนยันการทำรายการ');
    messageElement.textContent = String(message || 'กรุณายืนยันการทำรายการนี้');
    confirmBtn.textContent = String(confirmText || 'ยืนยัน');
    cancelBtn.textContent = String(cancelText || 'ยกเลิก');

    modal.classList.remove('confirm-modal-danger', 'confirm-modal-warning', 'confirm-modal-success', 'confirm-modal-info');
    modal.classList.add(getConfirmToneClass(tone));

    const resolvedOverlayClose = typeof allowOverlayClose === 'boolean'
      ? allowOverlayClose
      : (tone !== 'danger');

    confirmModalIsProcessing = false;
    wrapper.dataset.allowOverlayClose = resolvedOverlayClose ? 'true' : 'false';

    previousActiveElement = document.activeElement;
    wrapper.hidden = false;
    document.body.classList.add('confirm-modal-open');
    bindConfirmModalListeners();

    window.setTimeout(() => {
      confirmBtn.focus();
    }, 0);

    return new Promise((resolve) => {
      confirmModalResolve = resolve;
    });
  }

  function setupMessageRegion(element) {
    if (!element) {
      return;
    }

    element.setAttribute('aria-live', 'polite');
    element.setAttribute('aria-atomic', 'true');
    element.setAttribute('role', 'status');
  }

  function lockRequest(key) {
    if (requestLocks[key]) {
      return false;
    }

    requestLocks[key] = true;
    return true;
  }

  function unlockRequest(key) {
    if (!(key in requestLocks)) {
      return;
    }

    requestLocks[key] = false;
  }

  function getButtonTextElement(buttonElement) {
    if (!buttonElement) {
      return null;
    }

    return buttonElement.querySelector('span') || buttonElement;
  }

  function setButtonLoading(buttonElement, isLoading, loadingText = 'กำลังดำเนินการ...') {
    if (!buttonElement) {
      return;
    }

    const textElement = getButtonTextElement(buttonElement);
    if (isLoading) {
      if (!buttonElement.dataset.originalLabel) {
        buttonElement.dataset.originalLabel = textElement ? textElement.textContent : '';
      }

      if (textElement) {
        textElement.textContent = loadingText;
      }

      buttonElement.disabled = true;
      buttonElement.classList.add('is-loading');
      buttonElement.setAttribute('aria-busy', 'true');
      return;
    }

    if (textElement && Object.prototype.hasOwnProperty.call(buttonElement.dataset, 'originalLabel')) {
      textElement.textContent = buttonElement.dataset.originalLabel;
    }

    delete buttonElement.dataset.originalLabel;
    buttonElement.classList.remove('is-loading');
    buttonElement.removeAttribute('aria-busy');
    buttonElement.disabled = false;
  }

  function resolveMessageType(typeOrError) {
    if (typeof typeOrError === 'boolean') {
      return typeOrError ? 'error' : 'info';
    }

    return typeOrError || 'info';
  }

  function setMessage(element, message, typeOrError = 'info') {
    if (!element) {
      return;
    }

    const type = resolveMessageType(typeOrError);
    element.textContent = String(message || '').trim();
    element.classList.remove('message-success', 'message-error', 'message-warning', 'message-info', 'error-message');

    if (!element.textContent) {
      return;
    }

    if (type === 'success') {
      element.classList.add('message-success');
      return;
    }

    if (type === 'warning') {
      element.classList.add('message-warning');
      return;
    }

    if (type === 'error') {
      element.classList.add('message-error', 'error-message');
      return;
    }

    element.classList.add('message-info');
  }

  function isLikelyTechnicalError(message) {
    const input = String(message || '');
    return /(exception|stack|trace|fatal|warning|notice|sql|syntax|undefined|unexpected token|<[^>]+>|^error:)/i.test(input);
  }

  function toUserErrorMessage(error, fallbackMessage) {
    const rawMessage = error && error.message ? String(error.message).trim() : '';
    if (!rawMessage) {
      return fallbackMessage;
    }

    if (rawMessage.length > 160 || isLikelyTechnicalError(rawMessage)) {
      console.error(error);
      return fallbackMessage;
    }

    return rawMessage;
  }

  function getFileExtension(fileName) {
    const normalized = String(fileName || '').toLowerCase();
    const lastDotIndex = normalized.lastIndexOf('.');
    if (lastDotIndex < 0) {
      return '';
    }

    return normalized.slice(lastDotIndex + 1);
  }

  function validateFile(file, {
    fileLabel,
    allowedExtensions,
    allowedMimeTypes,
    maxSizeBytes,
  }) {
    if (!file) {
      return { valid: false, message: `กรุณาเลือก${fileLabel}` };
    }

    const extension = getFileExtension(file.name);
    const mimeType = String(file.type || '').toLowerCase();

    if (!extension || BLOCKED_FILE_EXTENSIONS.includes(extension)) {
      return { valid: false, message: `${fileLabel}ไม่ปลอดภัยหรือไม่รองรับ` };
    }

    if (!allowedExtensions.includes(extension)) {
      return { valid: false, message: `${fileLabel}ต้องเป็น ${allowedExtensions.join(', ').toUpperCase()} เท่านั้น` };
    }

    if (mimeType && !allowedMimeTypes.includes(mimeType)) {
      return { valid: false, message: `${fileLabel}มีชนิดไฟล์ไม่ถูกต้อง` };
    }

    if (Number(file.size) > maxSizeBytes) {
      return { valid: false, message: `${fileLabel}มีขนาดใหญ่เกินกำหนด (${formatFileSize(maxSizeBytes)} สูงสุด)` };
    }

    return { valid: true };
  }

  async function requestActionConfirmation(options) {
    const confirmed = await openConfirmModal(options);
    return confirmed;
  }

  [
    loginMessage,
    statsMessage,
    homepageStatsMessage,
    newsMessage,
    personnelMessage,
    downloadsMessage,
    contactMessage,
  ].forEach(setupMessageRegion);

  async function fetchMe() {
    try {
      const response = await fetch(ME_API_URL, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json'
        },
        cache: 'no-store'
      });

      if (response.status === 401) {
        return null;
      }

      if (!response.ok) {
        throw new Error(`Session check failed: ${response.status}`);
      }

      const data = await response.json();
      if (!data.success || !data.logged_in) {
        return null;
      }

      return data.data || data.user || null;
    } catch (error) {
      console.error('Unable to check login session:', error);
      return null;
    }
  }

  async function login(username, password) {
    const formData = new FormData();
    formData.append('username', username);
    formData.append('password', password);

    const response = await fetch(AUTH_API_URL, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json'
      }
    });

    let data = null;
    try {
      data = await response.json();
    } catch (error) {
      if (response.ok) {
        throw error;
      }
    }

    return {
      ok: response.ok,
      status: response.status,
      data
    };
  }

  function getSafeLoginReturnUrl() {
    const returnUrl = new URLSearchParams(window.location.search).get('return');
    return returnUrl && returnUrl.startsWith('/') && !returnUrl.startsWith('//')
      ? returnUrl
      : 'dashboard.html';
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

  async function fetchDownloadsCountFromAdminApi() {
    const response = await fetch(DOWNLOADS_API_URL, {
      credentials: 'same-origin'
    });

    if (response.status === 401) {
      return { unauthorized: true };
    }

    if (!response.ok) {
      throw new Error('Failed to fetch downloads list');
    }

    const data = await response.json();

    if (!data.success || !Array.isArray(data.data)) {
      throw new Error('Invalid downloads response');
    }

    return { count: data.data.length };
  }

  function setHomepageStatsMessage(message, typeOrError = 'info') {
    setMessage(homepageStatsMessage, message, typeOrError);
  }

  function parseNonNegativeInteger(value) {
    const input = String(value || '').trim();
    if (!/^\d+$/.test(input)) {
      return null;
    }

    const parsed = Number(input);
    if (!Number.isInteger(parsed) || parsed < 0) {
      return null;
    }

    return parsed;
  }

  function parseAcademicYear(value) {
    const input = String(value || '').trim();
    if (!/^\d{4}$/.test(input)) {
      return null;
    }

    return input;
  }

  function setContactMessage(message, typeOrError = 'info') {
    setMessage(contactMessage, message, typeOrError);
  }

  function setContactFormDisabled(isDisabled) {
    if (!contactForm) {
      return;
    }

    contactForm.querySelectorAll('input, textarea, button').forEach((element) => {
      element.disabled = isDisabled;
    });
  }

  function isValidEmailAddress(value) {
    const input = String(value || '').trim();
    if (input === '') {
      return true;
    }

    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input);
  }

  function isValidHttpUrl(value) {
    const input = String(value || '').trim();
    if (input === '') {
      return true;
    }

    try {
      const url = new URL(input);
      return url.protocol === 'http:' || url.protocol === 'https:';
    } catch (error) {
      return false;
    }
  }

  function fillContactForm(data) {
    if (!data) {
      return;
    }

    if (contactSchoolName) contactSchoolName.value = data.school_name || '';
    if (contactAddress) contactAddress.value = data.address || '';
    if (contactPhone) contactPhone.value = data.phone || '';
    if (contactFax) contactFax.value = data.fax || '';
    if (contactEmail) contactEmail.value = data.email || '';
    if (contactWebsite) contactWebsite.value = data.website || '';
    if (contactFacebookUrl) contactFacebookUrl.value = data.facebook_url || '';
    if (contactWorkingHours) contactWorkingHours.value = data.working_hours || '';
    if (contactMapUrl) contactMapUrl.value = data.map_url || '';
    if (contactMapEmbedUrl) contactMapEmbedUrl.value = data.map_embed_url || '';
  }

  function readContactFormPayload() {
    const payload = {
      school_name: contactSchoolName ? contactSchoolName.value.trim() : '',
      address: contactAddress ? contactAddress.value.trim() : '',
      phone: contactPhone ? contactPhone.value.trim() : '',
      fax: contactFax ? contactFax.value.trim() : '',
      email: contactEmail ? contactEmail.value.trim() : '',
      website: contactWebsite ? contactWebsite.value.trim() : '',
      facebook_url: contactFacebookUrl ? contactFacebookUrl.value.trim() : '',
      working_hours: contactWorkingHours ? contactWorkingHours.value.trim() : '',
      map_url: contactMapUrl ? contactMapUrl.value.trim() : '',
      map_embed_url: contactMapEmbedUrl ? contactMapEmbedUrl.value.trim() : '',
    };

    if (payload.school_name === '') {
      throw new Error('กรุณากรอกชื่อโรงเรียน');
    }

    if (payload.address === '') {
      throw new Error('กรุณากรอกที่อยู่');
    }

    if (!isValidEmailAddress(payload.email)) {
      throw new Error('รูปแบบอีเมลไม่ถูกต้อง');
    }

    if (!isValidHttpUrl(payload.website)) {
      throw new Error('รูปแบบเว็บไซต์ไม่ถูกต้อง');
    }

    if (!isValidHttpUrl(payload.facebook_url)) {
      throw new Error('รูปแบบ Facebook Page ไม่ถูกต้อง');
    }

    if (!isValidHttpUrl(payload.map_url)) {
      throw new Error('รูปแบบลิงก์ Google Maps ไม่ถูกต้อง');
    }

    if (!isValidHttpUrl(payload.map_embed_url)) {
      throw new Error('รูปแบบลิงก์แผนที่แบบฝังไม่ถูกต้อง');
    }

    return payload;
  }

  async function requestContactApi(method, payload = null) {
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 10000);

    const options = {
      method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json'
      },
      signal: controller.signal,
    };

    if (payload) {
      options.body = JSON.stringify(payload);
    }

    let response;
    try {
      response = await fetch(CONTACT_ADMIN_API_URL, options);
    } catch (error) {
      if (error && error.name === 'AbortError') {
        throw new Error('หมดเวลาในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง');
      }

      throw new Error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
    } finally {
      window.clearTimeout(timeoutId);
    }

    if (response.status === 401) {
      window.location.href = 'login.html';
      return null;
    }

    let data = null;
    try {
      data = await response.json();
    } catch (error) {
      console.error(error);
      throw new Error('ได้รับข้อมูลจากเซิร์ฟเวอร์ไม่ถูกต้อง');
    }

    if (!response.ok || !data.success) {
      const apiMessage = data && (data.error || data.message);
      throw new Error(toUserErrorMessage({ message: apiMessage }, 'ไม่สามารถบันทึกข้อมูลติดต่อได้')); 
    }

    return data;
  }

  async function loadContactSettings() {
    if (!contactForm) {
      return;
    }

    setContactMessage('กำลังโหลดข้อมูลติดต่อ...', 'info');
    setContactFormDisabled(true);

    try {
      const response = await requestContactApi('GET');
      if (!response || !response.data) {
        return;
      }

      fillContactForm(response.data);
      setContactMessage('');
    } catch (error) {
      setContactMessage(toUserErrorMessage(error, 'โหลดข้อมูลติดต่อไม่สำเร็จ'), 'error');
    } finally {
      setContactFormDisabled(false);
    }
  }

  async function handleContactFormSubmit(event) {
    event.preventDefault();

    let payload;
    try {
      payload = readContactFormPayload();
    } catch (error) {
      setContactMessage(toUserErrorMessage(error, 'ตรวจสอบข้อมูลติดต่อไม่สำเร็จ'), 'error');
      return;
    }

    const confirmed = await requestActionConfirmation({
      title: 'ยืนยันการบันทึกข้อมูลติดต่อ',
      message: 'ยืนยันการบันทึกข้อมูลติดต่อโรงเรียนนี้หรือไม่?',
      confirmText: 'ยืนยันการบันทึก',
      cancelText: 'ยกเลิก',
      tone: 'info',
    });

    if (!confirmed) {
      return;
    }

    if (!lockRequest('contactSubmit')) {
      setContactMessage('กำลังบันทึกข้อมูลอยู่ กรุณารอสักครู่', 'warning');
      return;
    }

    setContactFormDisabled(true);
    setButtonLoading(contactSaveBtn, true, 'กำลังบันทึก...');
    setContactMessage('กำลังบันทึกข้อมูล...', 'info');

    try {
      const response = await requestContactApi('POST', payload);

      if (!response || !response.data) {
        return;
      }

      fillContactForm(response.data);
      setContactMessage('บันทึกข้อมูลติดต่อเรียบร้อยแล้ว', 'success');
    } catch (error) {
      setContactMessage(toUserErrorMessage(error, 'บันทึกข้อมูลติดต่อไม่สำเร็จ'), 'error');
    } finally {
      setContactFormDisabled(false);
      setButtonLoading(contactSaveBtn, false);
      unlockRequest('contactSubmit');
    }
  }

  function readHomepageStatsPayload() {
    const personnelValue = parseNonNegativeInteger(homepagePersonnelCount ? homepagePersonnelCount.value : '');
    const studentValue = parseNonNegativeInteger(homepageStudentCount ? homepageStudentCount.value : '');
    const classroomValue = parseNonNegativeInteger(homepageClassroomCount ? homepageClassroomCount.value : '');
    const academicYearValue = parseAcademicYear(homepageAcademicYear ? homepageAcademicYear.value : '');

    if (personnelValue === null) {
      throw new Error('จำนวนบุคลากรต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป');
    }

    if (studentValue === null) {
      throw new Error('จำนวนนักเรียนต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป');
    }

    if (classroomValue === null) {
      throw new Error('จำนวนห้องเรียนต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป');
    }

    if (academicYearValue === null) {
      throw new Error('ปีการศึกษาต้องเป็นตัวเลข 4 หลัก');
    }

    return {
      personnel_count: personnelValue,
      student_count: studentValue,
      classroom_count: classroomValue,
      academic_year: academicYearValue,
    };
  }

  function fillHomepageStatsForm(stats) {
    if (!homepagePersonnelCount || !homepageStudentCount || !homepageClassroomCount || !homepageAcademicYear) {
      return;
    }

    homepagePersonnelCount.value = String(stats.personnel_count ?? 85);
    homepageStudentCount.value = String(stats.student_count ?? 196);
    homepageClassroomCount.value = String(stats.classroom_count ?? 29);
    homepageAcademicYear.value = String(stats.academic_year ?? 2569);
  }

  async function requestHomepageSettingsApi(method, payload) {
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 10000);

    const options = {
      method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json'
      },
      signal: controller.signal,
    };

    if (payload) {
      options.body = JSON.stringify(payload);
    }

    let response;
    try {
      response = await fetch(SETTINGS_ADMIN_API_URL, options);
    } catch (error) {
      if (error && error.name === 'AbortError') {
        throw new Error('หมดเวลาในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง');
      }
      throw new Error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
    } finally {
      window.clearTimeout(timeoutId);
    }

    if (response.status === 401) {
      window.location.href = 'login.html';
      return null;
    }

    let data = null;
    try {
      data = await response.json();
    } catch (error) {
      console.error(error);
      throw new Error('ได้รับข้อมูลจากเซิร์ฟเวอร์ไม่ถูกต้อง');
    }

    if (!response.ok || !data.success) {
      const apiMessage = data && (data.message || data.error);
      throw new Error(toUserErrorMessage({ message: apiMessage }, 'ไม่สามารถบันทึกสถิติหน้าแรกได้'));
    }

    return data;
  }

  async function loadHomepageStatsSettings() {
    if (!homepageStatsForm) {
      return;
    }

    setHomepageStatsMessage('กำลังโหลดข้อมูลสถิติหน้าแรก...', 'info');

    try {
      const response = await requestHomepageSettingsApi('GET');
      if (!response || !response.data) {
        return;
      }

      fillHomepageStatsForm(response.data);
      setHomepageStatsMessage('');
    } catch (error) {
      setHomepageStatsMessage(toUserErrorMessage(error, 'โหลดข้อมูลสถิติหน้าแรกไม่สำเร็จ'), 'error');
    }
  }

  async function handleHomepageStatsSubmit(event) {
    event.preventDefault();

    let payload;
    try {
      payload = readHomepageStatsPayload();
    } catch (error) {
      setHomepageStatsMessage(toUserErrorMessage(error, 'ตรวจสอบข้อมูลสถิติหน้าแรกไม่สำเร็จ'), 'error');
      return;
    }

    const confirmed = await requestActionConfirmation({
      title: 'ยืนยันการบันทึกสถิติหน้าแรก',
      message: 'ยืนยันการบันทึกการแก้ไขสถิติหน้าแรกหรือไม่?',
      confirmText: 'ยืนยันการบันทึก',
      cancelText: 'ยกเลิก',
      tone: 'info',
    });

    if (!confirmed) {
      return;
    }

    if (!lockRequest('homepageStatsSubmit')) {
      setHomepageStatsMessage('กำลังบันทึกข้อมูลอยู่ กรุณารอสักครู่', 'warning');
      return;
    }

    setButtonLoading(saveHomepageStatsBtn, true, 'กำลังบันทึก...');

    try {
      setHomepageStatsMessage('กำลังบันทึกข้อมูล...', 'info');

      const response = await requestHomepageSettingsApi('POST', payload);
      if (!response || !response.data) {
        return;
      }

      fillHomepageStatsForm(response.data);
      setHomepageStatsMessage('บันทึกสถิติหน้าแรกเรียบร้อยแล้ว', 'success');
    } catch (error) {
      setHomepageStatsMessage(toUserErrorMessage(error, 'บันทึกสถิติหน้าแรกไม่สำเร็จ'), 'error');
    } finally {
      setButtonLoading(saveHomepageStatsBtn, false);
      unlockRequest('homepageStatsSubmit');
    }
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderAdminIdentity(admin) {
    if (!adminName || !admin) {
      return;
    }

    const roleText = String(admin.role || admin.user_role || 'ผู้ดูแลระบบ').trim();
    const accountText = String(admin.username || admin.login || admin.full_name || '-').trim();

    adminName.innerHTML = `
      <span class="sidebar-user-row"><strong>บทบาท:</strong><span>${escapeHtml(roleText || 'ผู้ดูแลระบบ')}</span></span>
      <span class="sidebar-user-row"><strong>บัญชี:</strong><span>${escapeHtml(accountText || '-')}</span></span>
    `;
  }

  function getStatusBadgeMarkup(rawStatus) {
    const normalized = String(rawStatus || '').trim().toLowerCase();
    const label = normalized || '-';
    let toneClass = 'status-neutral';

    if (normalized === 'publish') {
      toneClass = 'status-success';
    } else if (normalized === 'draft') {
      toneClass = 'status-warning';
    } else if (normalized === 'archived') {
      toneClass = 'status-danger';
    }

    return `<span class="status-badge ${toneClass}">${escapeHtml(label)}</span>`;
  }

  function getActionIconMarkup(type) {
    if (type === 'edit') {
      return '<svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
    }

    if (type === 'delete') {
      return '<svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 10v6M14 10v6"/></svg>';
    }

    return '<svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18"/><path d="M6 6l12 12"/><circle cx="12" cy="12" r="9"/></svg>';
  }

  function getActionButtonMarkup({ label, type, buttonClasses, rowId }) {
    const semanticClass = type === 'edit'
      ? 'action-edit'
      : (type === 'delete' ? 'action-delete' : 'action-disable');

    return `
      <button type="button" class="table-btn action-btn ${semanticClass} ${buttonClasses}" data-id="${rowId}">
        ${getActionIconMarkup(type)}
        <span>${escapeHtml(label)}</span>
      </button>
    `;
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

    let response;
    try {
      response = await fetch(UPLOAD_API_URL, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      });
    } catch (error) {
      throw new Error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์เพื่ออัปโหลดไฟล์ได้');
    }

    if (response.status === 401) {
      window.location.href = 'login.html';
      return null;
    }

    let data = null;
    try {
      data = await response.json();
    } catch (error) {
      console.error(error);
      throw new Error('ได้รับข้อมูลตอบกลับการอัปโหลดไม่ถูกต้อง');
    }

    if (!response.ok || !data.success) {
      const apiMessage = data && (data.message || data.error);
      throw new Error(toUserErrorMessage({ message: apiMessage }, 'อัปโหลดไฟล์ไม่สำเร็จ'));
    }

    return data;
  }

  function setNewsMessage(message, typeOrError = 'info') {
    setMessage(newsMessage, message, typeOrError);
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
      console.error(error);
      throw new Error('ได้รับข้อมูลจากเซิร์ฟเวอร์ไม่ถูกต้อง');
    }

    if (!response.ok || !data.success) {
      const apiMessage = data && (data.error || data.message);
      throw new Error(toUserErrorMessage({ message: apiMessage }, 'ไม่สามารถดำเนินการกับข้อมูลข่าวได้'));
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
        <td class="cell-status">${getStatusBadgeMarkup(item.status)}</td>
        <td class="cell-actions">
          <div class="row-actions">
            ${getActionButtonMarkup({ label: 'แก้ไข', type: 'edit', buttonClasses: 'edit-news-btn', rowId: item.id })}
            ${getActionButtonMarkup({ label: 'ลบ', type: 'delete', buttonClasses: 'delete-news-btn', rowId: item.id })}
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
      setNewsMessage(toUserErrorMessage(error, 'โหลดข้อมูลข่าวไม่สำเร็จ'), 'error');
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
    const title = newsTitle.value.trim();
    const summary = newsSummary.value.trim();
    const content = newsContent.value.trim();
    const date = newsDate.value.trim();
    const category = newsCategory.value.trim();
    const image = newsImage.value.trim();

    if (!title) {
      throw new Error('กรุณากรอกหัวข้อข่าว');
    }

    if (!summary) {
      throw new Error('กรุณากรอกสรุปข่าว');
    }

    if (!content) {
      throw new Error('กรุณากรอกเนื้อหาข่าว');
    }

    if (!date) {
      throw new Error('กรุณากรอกวันที่ข่าว');
    }

    if (!category) {
      throw new Error('กรุณากรอกหมวดหมู่ข่าว');
    }

    if (!image) {
      throw new Error('กรุณาระบุรูปภาพข่าวหรืออัปโหลดรูปภาพ');
    }

    return {
      id: newsId.value ? Number(newsId.value) : undefined,
      title,
      summary,
      content,
      date,
      category,
      image,
      status: newsStatus.value.trim(),
      upload_id: Number.isInteger(parsedUploadId) && parsedUploadId > 0 ? parsedUploadId : undefined,
    };
  }

  function handleNewsImageFileSelect() {
    if (!newsImageFile || !newsImageFile.files || newsImageFile.files.length === 0) {
      return;
    }

    const selectedFile = newsImageFile.files[0];
    const validation = validateFile(selectedFile, {
      fileLabel: 'ไฟล์รูปภาพ',
      allowedExtensions: ALLOWED_IMAGE_EXTENSIONS,
      allowedMimeTypes: ALLOWED_IMAGE_MIME_TYPES,
      maxSizeBytes: MAX_IMAGE_FILE_SIZE_BYTES,
    });

    if (!validation.valid) {
      setNewsMessage(validation.message, 'error');
      newsImageFile.value = '';
      return;
    }

    const objectUrl = URL.createObjectURL(selectedFile);
    showImagePreview(objectUrl);
    setNewsMessage('');
  }

  async function handleUploadNewsImage() {
    if (!lockRequest('newsUpload')) {
      setNewsMessage('กำลังอัปโหลดรูปภาพอยู่ กรุณารอสักครู่', 'warning');
      return;
    }

    if (!newsImageFile || !newsImageFile.files || newsImageFile.files.length === 0) {
      setNewsMessage('กรุณาเลือกรูปภาพก่อนอัปโหลด', 'error');
      unlockRequest('newsUpload');
      return;
    }

    const selectedFile = newsImageFile.files[0];
    const validation = validateFile(selectedFile, {
      fileLabel: 'ไฟล์รูปภาพ',
      allowedExtensions: ALLOWED_IMAGE_EXTENSIONS,
      allowedMimeTypes: ALLOWED_IMAGE_MIME_TYPES,
      maxSizeBytes: MAX_IMAGE_FILE_SIZE_BYTES,
    });

    if (!validation.valid) {
      setNewsMessage(validation.message, 'error');
      unlockRequest('newsUpload');
      return;
    }

    const relatedId = Number(newsId.value);

    setButtonLoading(uploadNewsImageBtn, true, 'กำลังอัปโหลด...');
    setNewsMessage('กำลังอัปโหลดรูปภาพ...', 'info');

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
      setNewsMessage('อัปโหลดรูปภาพสำเร็จ', 'success');
    } catch (error) {
      setNewsMessage(toUserErrorMessage(error, 'อัปโหลดรูปภาพไม่สำเร็จ'), 'error');
    } finally {
      setButtonLoading(uploadNewsImageBtn, false);
      unlockRequest('newsUpload');
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
      setNewsMessage(toUserErrorMessage(error, 'โหลดข้อมูลข่าวไม่สำเร็จ'), 'error');
    }
  }

  async function handleDeleteNews(id, triggerButton) {
    const confirmed = await requestActionConfirmation({
      title: 'ยืนยันการลบข่าว',
      message: 'ยืนยันการลบข่าวนี้หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้',
      confirmText: 'ยืนยันการลบ',
      cancelText: 'ยกเลิก',
      tone: 'danger',
    });

    if (!confirmed) {
      return;
    }

    if (!lockRequest('newsDelete')) {
      setNewsMessage('กำลังลบข้อมูลข่าวอยู่ กรุณารอสักครู่', 'warning');
      return;
    }

    setButtonLoading(triggerButton, true, 'กำลังลบ...');
    setNewsMessage('กำลังลบข้อมูลข่าว...', 'info');

    try {
      await requestNewsApi(NEWS_API_URL, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id }),
      });

      setNewsMessage('ลบข่าวเรียบร้อยแล้ว', 'success');
      await fetchNewsList();
    } catch (error) {
      setNewsMessage(toUserErrorMessage(error, 'ลบข่าวไม่สำเร็จ'), 'error');
    } finally {
      setButtonLoading(triggerButton, false);
      unlockRequest('newsDelete');
    }
  }

  async function handleNewsFormSubmit(event) {
    event.preventDefault();

    let payload;
    let isEditMode = false;

    try {
      payload = readNewsFormPayload();
      isEditMode = Boolean(payload.id);
    } catch (error) {
      setNewsMessage(toUserErrorMessage(error, 'ตรวจสอบข้อมูลข่าวไม่สำเร็จ'), 'error');
      return;
    }

    const confirmed = await requestActionConfirmation({
      title: isEditMode ? 'ยืนยันการบันทึกการแก้ไขข่าว' : 'ยืนยันการเพิ่มข่าว',
      message: isEditMode
        ? 'ยืนยันการบันทึกการแก้ไขข่าวนี้หรือไม่?'
        : 'ยืนยันการเพิ่มข้อมูลข่าวนี้หรือไม่?',
      confirmText: 'ยืนยัน',
      cancelText: 'ยกเลิก',
      tone: isEditMode ? 'info' : 'success',
    });

    if (!confirmed) {
      return;
    }

    if (!lockRequest('newsSubmit')) {
      setNewsMessage('กำลังบันทึกข้อมูลข่าวอยู่ กรุณารอสักครู่', 'warning');
      return;
    }

    setButtonLoading(saveNewsBtn, true, 'กำลังบันทึก...');
    setNewsMessage('กำลังบันทึกข้อมูลข่าว...', 'info');

    try {
      await requestNewsApi(NEWS_API_URL, {
        method: isEditMode ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload),
      });

      setNewsMessage(isEditMode ? 'แก้ไขข่าวเรียบร้อยแล้ว' : 'เพิ่มข่าวเรียบร้อยแล้ว', 'success');
      showNewsForm(false);
      clearNewsForm();
      await fetchNewsList();
    } catch (error) {
      setNewsMessage(toUserErrorMessage(error, 'บันทึกข้อมูลข่าวไม่สำเร็จ'), 'error');
    } finally {
      setButtonLoading(saveNewsBtn, false);
      unlockRequest('newsSubmit');
    }
  }

  async function initNewsPage() {
    const admin = await fetchMe();

    if (!admin) {
      window.location.href = 'login.html';
      return;
    }

    renderAdminIdentity(admin);

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
          await handleDeleteNews(id, deleteBtn);
        }
      }
    });
  }

  function setPersonnelMessage(message, typeOrError = 'info') {
    setMessage(personnelMessage, message, typeOrError);
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
      || value.startsWith('data:')
      || value.startsWith('blob:');

    let previewSrc = value;

    if (!isAbsoluteOrSpecialUrl) {
      if (value.startsWith('/')) {
        previewSrc = value;
      } else {
        const normalized = value
          .replace(/^\.\/+/, '')
          .replace(/^(?:\.\.\/)+/, '')
          .replace(/^\/+/, '');

        if (normalized.startsWith('uploads/') || normalized.startsWith('images/')) {
          previewSrc = `/${normalized}`;
        } else if (normalized.startsWith('personnel/')) {
          previewSrc = `/uploads/${normalized}`;
        } else {
          previewSrc = `/uploads/personnel/${normalized}`;
        }
      }
    }

    personnelImagePreview.src = previewSrc;
    personnelImagePreviewWrap.hidden = false;
  }

  function isAllowedPersonnelImageFile(file) {
    const validation = validateFile(file, {
      fileLabel: 'ไฟล์รูปภาพ',
      allowedExtensions: ALLOWED_IMAGE_EXTENSIONS,
      allowedMimeTypes: ALLOWED_IMAGE_MIME_TYPES,
      maxSizeBytes: MAX_IMAGE_FILE_SIZE_BYTES,
    });

    return validation.valid;
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
    return value === 'ผู้บริหาร' ? 'ฝ่ายบริหาร' : value;
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
      console.error(error);
      throw new Error('ได้รับข้อมูลจากเซิร์ฟเวอร์ไม่ถูกต้อง');
    }

    if (!response.ok || !data.success) {
      const apiMessage = data && (data.error || data.message);
      throw new Error(toUserErrorMessage({ message: apiMessage }, 'ไม่สามารถดำเนินการกับข้อมูลบุคลากรได้'));
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
        <td>${escapeHtml(item.group_name)}</td>
        <td>${escapeHtml(item.subject_group || item.department || '')}</td>
        <td>${escapeHtml(item.display_order)}</td>
        <td class="cell-status">${getStatusBadgeMarkup(item.status)}</td>
        <td class="cell-actions">
          <div class="row-actions">
            ${getActionButtonMarkup({ label: 'แก้ไข', type: 'edit', buttonClasses: 'edit-personnel-btn', rowId: item.id })}
            ${getActionButtonMarkup({ label: 'ปิดใช้งาน', type: 'disable', buttonClasses: 'delete-news-btn delete-personnel-btn', rowId: item.id })}
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
      setPersonnelMessage(toUserErrorMessage(error, 'โหลดข้อมูลบุคลากรไม่สำเร็จ'), 'error');
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
    setSelectValueFromOptions(personnelSubjectGroup, personnel.subject_group || personnel.department || '');
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
    const validation = validateFile(selectedFile, {
      fileLabel: 'ไฟล์รูปภาพ',
      allowedExtensions: ALLOWED_IMAGE_EXTENSIONS,
      allowedMimeTypes: ALLOWED_IMAGE_MIME_TYPES,
      maxSizeBytes: MAX_IMAGE_FILE_SIZE_BYTES,
    });

    if (!validation.valid) {
      setPersonnelMessage(validation.message, 'error');
      personnelImageFile.value = '';
      return;
    }

    const objectUrl = URL.createObjectURL(selectedFile);
    showPersonnelImagePreview(objectUrl);
    setPersonnelMessage('');
  }

  async function handleUploadPersonnelImage() {
    if (!lockRequest('personnelUpload')) {
      setPersonnelMessage('กำลังอัปโหลดรูปภาพอยู่ กรุณารอสักครู่', 'warning');
      return;
    }

    if (!personnelImageFile || !personnelImageFile.files || personnelImageFile.files.length === 0) {
      setPersonnelMessage('กรุณาเลือกรูปภาพก่อนอัปโหลด', 'error');
      unlockRequest('personnelUpload');
      return;
    }

    const selectedFile = personnelImageFile.files[0];
    const validation = validateFile(selectedFile, {
      fileLabel: 'ไฟล์รูปภาพ',
      allowedExtensions: ALLOWED_IMAGE_EXTENSIONS,
      allowedMimeTypes: ALLOWED_IMAGE_MIME_TYPES,
      maxSizeBytes: MAX_IMAGE_FILE_SIZE_BYTES,
    });

    if (!validation.valid) {
      setPersonnelMessage(validation.message, 'error');
      unlockRequest('personnelUpload');
      return;
    }

    setButtonLoading(uploadPersonnelImageBtn, true, 'กำลังอัปโหลด...');
    setPersonnelMessage('กำลังอัปโหลดรูปภาพ...', 'info');

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
      setPersonnelMessage('อัปโหลดรูปภาพสำเร็จ', 'success');
    } catch (error) {
      setPersonnelMessage(toUserErrorMessage(error, 'อัปโหลดรูปบุคลากรไม่สำเร็จ'), 'error');
    } finally {
      setButtonLoading(uploadPersonnelImageBtn, false);
      unlockRequest('personnelUpload');
    }
  }

  function readPersonnelFormPayload() {
    const parsedDisplayOrder = Number(personnelDisplayOrder.value);
    const imageValue = personnelImage.value.trim();
    const name = personnelName.value.trim();
    const position = personnelPosition.value.trim();
    const groupName = personnelGroupName.value.trim();

    if (!name) {
      throw new Error('กรุณากรอกชื่อ-นามสกุลบุคลากร');
    }

    if (!position) {
      throw new Error('กรุณากรอกตำแหน่งบุคลากร');
    }

    if (!groupName) {
      throw new Error('กรุณาเลือกประเภทกลุ่มบุคลากร');
    }

    if (!imageValue) {
      throw new Error('กรุณาระบุรูปภาพบุคลากรหรืออัปโหลดรูปภาพ');
    }

    return {
      id: personnelId.value ? Number(personnelId.value) : undefined,
      name,
      position,
      group_name: groupName,
      subject_group: personnelSubjectGroup.value.trim(),
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
      setPersonnelMessage(toUserErrorMessage(error, 'โหลดข้อมูลบุคลากรไม่สำเร็จ'), 'error');
    }
  }

  async function handleDeletePersonnel(id, triggerButton) {
    const confirmed = await requestActionConfirmation({
      title: 'ยืนยันการปิดใช้งานบุคลากร',
      message: 'ยืนยันการปิดใช้งานบุคลากรนี้หรือไม่? เมื่อปิดใช้งานแล้ว รายการนี้จะไม่แสดงบนหน้าเว็บไซต์',
      confirmText: 'ยืนยันการปิดใช้งาน',
      cancelText: 'ยกเลิก',
      tone: 'danger',
    });

    if (!confirmed) {
      return;
    }

    if (!lockRequest('personnelDelete')) {
      setPersonnelMessage('กำลังปิดใช้งานบุคลากรอยู่ กรุณารอสักครู่', 'warning');
      return;
    }

    setButtonLoading(triggerButton, true, 'กำลังปิดใช้งาน...');
    setPersonnelMessage('กำลังอัปเดตสถานะบุคลากร...', 'info');

    try {
      await requestPersonnelApi(PERSONNEL_API_URL, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id }),
      });

      setPersonnelMessage('ปิดใช้งานบุคลากรเรียบร้อยแล้ว', 'success');
      await fetchPersonnelList();
    } catch (error) {
      setPersonnelMessage(toUserErrorMessage(error, 'ปิดใช้งานบุคลากรไม่สำเร็จ'), 'error');
    } finally {
      setButtonLoading(triggerButton, false);
      unlockRequest('personnelDelete');
    }
  }

  async function handlePersonnelFormSubmit(event) {
    event.preventDefault();

    let payload;
    let isEditMode = false;

    try {
      payload = readPersonnelFormPayload();
      isEditMode = Boolean(payload.id);

      if (!Number.isInteger(payload.display_order) || payload.display_order < 0) {
        throw new Error('กรุณากรอก display_order เป็นเลขจำนวนเต็มตั้งแต่ 0 ขึ้นไป');
      }
    } catch (error) {
      setPersonnelMessage(toUserErrorMessage(error, 'ตรวจสอบข้อมูลบุคลากรไม่สำเร็จ'), 'error');
      return;
    }

    const confirmed = await requestActionConfirmation({
      title: isEditMode ? 'ยืนยันการบันทึกการแก้ไขบุคลากร' : 'ยืนยันการเพิ่มบุคลากร',
      message: isEditMode
        ? 'ยืนยันการบันทึกการแก้ไขข้อมูลบุคลากรนี้หรือไม่?'
        : 'ยืนยันการเพิ่มข้อมูลบุคลากรนี้หรือไม่?',
      confirmText: 'ยืนยัน',
      cancelText: 'ยกเลิก',
      tone: isEditMode ? 'info' : 'success',
    });

    if (!confirmed) {
      return;
    }

    if (!lockRequest('personnelSubmit')) {
      setPersonnelMessage('กำลังบันทึกข้อมูลบุคลากรอยู่ กรุณารอสักครู่', 'warning');
      return;
    }

    setButtonLoading(savePersonnelBtn, true, 'กำลังบันทึก...');
    setPersonnelMessage('กำลังบันทึกข้อมูลบุคลากร...', 'info');

    try {
      await requestPersonnelApi(PERSONNEL_API_URL, {
        method: isEditMode ? 'PUT' : 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload),
      });

      setPersonnelMessage(isEditMode ? 'แก้ไขบุคลากรเรียบร้อยแล้ว' : 'เพิ่มบุคลากรเรียบร้อยแล้ว', 'success');
      showPersonnelForm(false);
      clearPersonnelForm();
      await fetchPersonnelList();
    } catch (error) {
      setPersonnelMessage(toUserErrorMessage(error, 'บันทึกข้อมูลบุคลากรไม่สำเร็จ'), 'error');
    } finally {
      setButtonLoading(savePersonnelBtn, false);
      unlockRequest('personnelSubmit');
    }
  }

  async function initPersonnelPage() {
    const admin = await fetchMe();

    if (!admin) {
      window.location.href = 'login.html';
      return;
    }

    renderAdminIdentity(admin);

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
          await handleDeletePersonnel(id, deleteBtn);
        }
      }
    });
  }

  function setDownloadsMessage(message, typeOrError = 'info') {
    setMessage(downloadsMessage, message, typeOrError);
  }

  function normalizeDownloadsStatusValue(rawStatus) {
    const status = String(rawStatus || '').trim().toLowerCase();

    if (status === 'publish') {
      return 'publish';
    }

    if (status === 'archived') {
      return 'archived';
    }

    if (status === 'draft') {
      return 'draft';
    }

    return 'draft';
  }

  function normalizeDownloadsStatusFormOptions() {
    if (!downloadStatus) {
      return;
    }

    downloadStatus.id = 'downloadStatus';
    downloadStatus.name = 'status';

    const expectedOptions = [
      { value: 'publish', label: 'publish' },
      { value: 'draft', label: 'draft' },
      { value: 'archived', label: 'archived' },
    ];

    const hasExpectedOptions = expectedOptions.every((entry) =>
      Array.from(downloadStatus.options).some((option) => option.value === entry.value)
    );

    if (!hasExpectedOptions || downloadStatus.options.length !== expectedOptions.length) {
      downloadStatus.innerHTML = '';
      expectedOptions.forEach((entry) => {
        const option = document.createElement('option');
        option.value = entry.value;
        option.textContent = entry.label;
        downloadStatus.appendChild(option);
      });
    }

    downloadStatus.value = normalizeDownloadsStatusValue(downloadStatus.value);
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
    normalizeDownloadsStatusFormOptions();
    downloadStatus.value = 'publish';
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
      console.error(error);
      throw new Error('ได้รับข้อมูลจากเซิร์ฟเวอร์ไม่ถูกต้อง');
    }

    if (!response.ok || !data.success) {
      const apiMessage = data && (data.error || data.message);
      throw new Error(toUserErrorMessage({ message: apiMessage }, 'ไม่สามารถดำเนินการกับข้อมูลเอกสารได้'));
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
        <td class="cell-status">${getStatusBadgeMarkup(item.status)}</td>
        <td class="cell-actions">
          <div class="row-actions">
            ${getActionButtonMarkup({ label: 'แก้ไข', type: 'edit', buttonClasses: 'edit-download-btn', rowId: item.id })}
            ${getActionButtonMarkup({ label: 'ลบ', type: 'delete', buttonClasses: 'delete-news-btn delete-download-btn', rowId: item.id })}
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
      setDownloadsMessage(toUserErrorMessage(error, 'โหลดข้อมูลเอกสารไม่สำเร็จ'), 'error');
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
    normalizeDownloadsStatusFormOptions();
    downloadStatus.value = normalizeDownloadsStatusValue(item.status);
  }

  function readDownloadsFormPayload() {
    const parsedDisplayOrder = Number(downloadDisplayOrder.value);
    const parsedUploadId = Number(downloadUploadId ? downloadUploadId.value : '');
    const fileUrl = downloadFileUrl.value.trim();
    const title = downloadTitle.value.trim();
    let buttonText = downloadButtonText.value.trim();

    if (!title) {
      throw new Error('กรุณากรอกชื่อเอกสาร');
    }

    if (!fileUrl && !(Number.isInteger(parsedUploadId) && parsedUploadId > 0)) {
      throw new Error('กรุณาระบุไฟล์เอกสารหรืออัปโหลดไฟล์ก่อนบันทึก');
    }

    if (fileUrl !== '' && (buttonText === '' || buttonText === 'รอเพิ่มไฟล์')) {
      buttonText = 'ดาวน์โหลด';
    }

    const selectedStatus = normalizeDownloadsStatusValue(downloadStatus ? downloadStatus.value : '');

    if (!['publish', 'draft', 'archived'].includes(selectedStatus)) {
      throw new Error('กรุณาเลือกสถานะเอกสารให้ถูกต้อง');
    }

    return {
      id: downloadId.value ? Number(downloadId.value) : undefined,
      title,
      description: downloadDescription.value.trim(),
      category: downloadCategory.value.trim(),
      file_url: fileUrl,
      button_text: buttonText,
      display_order: Number.isFinite(parsedDisplayOrder) ? parsedDisplayOrder : NaN,
      status: selectedStatus,
      upload_id: Number.isInteger(parsedUploadId) && parsedUploadId > 0 ? parsedUploadId : undefined,
    };
  }

  function handleDownloadFileSelect() {
    if (!downloadFile || !downloadFile.files || downloadFile.files.length === 0) {
      return;
    }

    const selectedFile = downloadFile.files[0];
    const validation = validateFile(selectedFile, {
      fileLabel: 'ไฟล์เอกสาร',
      allowedExtensions: ALLOWED_DOCUMENT_EXTENSIONS,
      allowedMimeTypes: ALLOWED_DOCUMENT_MIME_TYPES,
      maxSizeBytes: MAX_DOCUMENT_FILE_SIZE_BYTES,
    });

    if (!validation.valid) {
      setDownloadsMessage(validation.message, 'error');
      downloadFile.value = '';
      return;
    }

    setDownloadsMessage('');
  }

  async function handleUploadDownloadFile() {
    if (!lockRequest('downloadsUpload')) {
      setDownloadsMessage('กำลังอัปโหลดไฟล์อยู่ กรุณารอสักครู่', 'warning');
      return;
    }

    if (!downloadFile || !downloadFile.files || downloadFile.files.length === 0) {
      setDownloadsMessage('กรุณาเลือกไฟล์เอกสารก่อนอัปโหลด', 'error');
      unlockRequest('downloadsUpload');
      return;
    }

    const selectedFile = downloadFile.files[0];
    const validation = validateFile(selectedFile, {
      fileLabel: 'ไฟล์เอกสาร',
      allowedExtensions: ALLOWED_DOCUMENT_EXTENSIONS,
      allowedMimeTypes: ALLOWED_DOCUMENT_MIME_TYPES,
      maxSizeBytes: MAX_DOCUMENT_FILE_SIZE_BYTES,
    });

    if (!validation.valid) {
      setDownloadsMessage(validation.message, 'error');
      unlockRequest('downloadsUpload');
      return;
    }

    const relatedId = Number(downloadId.value);

    setButtonLoading(uploadDownloadFileBtn, true, 'กำลังอัปโหลด...');
    setDownloadsMessage('กำลังอัปโหลดไฟล์...', 'info');

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
      setDownloadsMessage('อัปโหลดไฟล์สำเร็จ', 'success');
    } catch (error) {
      setDownloadsMessage(toUserErrorMessage(error, 'อัปโหลดไฟล์ไม่สำเร็จ'), 'error');
    } finally {
      setButtonLoading(uploadDownloadFileBtn, false);
      unlockRequest('downloadsUpload');
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
      setDownloadsMessage(toUserErrorMessage(error, 'โหลดข้อมูลเอกสารไม่สำเร็จ'), 'error');
    }
  }

  async function handleDeleteDownload(id, triggerButton) {
    const confirmed = await requestActionConfirmation({
      title: 'ยืนยันการลบเอกสาร',
      message: 'ยืนยันการลบเอกสารนี้หรือไม่? การลบจะนำเอกสารออกจากระบบทันที',
      confirmText: 'ยืนยันการลบ',
      cancelText: 'ยกเลิก',
      tone: 'danger',
    });

    if (!confirmed) {
      return;
    }

    if (!lockRequest('downloadsDelete')) {
      setDownloadsMessage('กำลังลบเอกสารอยู่ กรุณารอสักครู่', 'warning');
      return;
    }

    setButtonLoading(triggerButton, true, 'กำลังลบ...');
    setDownloadsMessage('กำลังลบเอกสาร...', 'info');

    try {
      await requestDownloadsApi(DOWNLOADS_API_URL, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id }),
      });

      setDownloadsMessage('ลบเอกสารเรียบร้อยแล้ว', 'success');
      await fetchDownloadsList();
    } catch (error) {
      setDownloadsMessage(toUserErrorMessage(error, 'ลบเอกสารไม่สำเร็จ'), 'error');
    } finally {
      setButtonLoading(triggerButton, false);
      unlockRequest('downloadsDelete');
    }
  }

  async function handleDownloadsFormSubmit(event) {
    event.preventDefault();

    let payload;
    let isEditMode = false;

    try {
      payload = readDownloadsFormPayload();
      const statusElement = document.getElementById('downloadStatus');
      const statusFromSelect = statusElement ? statusElement.value : payload.status;
      payload.status = normalizeDownloadsStatusValue(statusFromSelect);
      isEditMode = Boolean(payload.id);

      if (!Number.isInteger(payload.display_order) || payload.display_order < 0) {
        throw new Error('กรุณากรอก display_order เป็นเลขจำนวนเต็มตั้งแต่ 0 ขึ้นไป');
      }
    } catch (error) {
      setDownloadsMessage(toUserErrorMessage(error, 'ตรวจสอบข้อมูลเอกสารไม่สำเร็จ'), 'error');
      return;
    }

    const confirmed = await requestActionConfirmation({
      title: isEditMode ? 'ยืนยันการบันทึกการแก้ไขเอกสาร' : 'ยืนยันการเพิ่มเอกสาร',
      message: isEditMode
        ? 'ยืนยันการบันทึกการแก้ไขข้อมูลเอกสารนี้หรือไม่?'
        : 'ยืนยันการเพิ่มข้อมูลเอกสารนี้หรือไม่?',
      confirmText: 'ยืนยัน',
      cancelText: 'ยกเลิก',
      tone: isEditMode ? 'info' : 'success',
    });

    if (!confirmed) {
      return;
    }

    if (!lockRequest('downloadsSubmit')) {
      setDownloadsMessage('กำลังบันทึกข้อมูลเอกสารอยู่ กรุณารอสักครู่', 'warning');
      return;
    }

    setButtonLoading(saveDownloadBtn, true, 'กำลังบันทึก...');
    setDownloadsMessage('กำลังบันทึกข้อมูลเอกสาร...', 'info');

    try {
      const requestBody = {
        ...payload,
        action: isEditMode ? 'update' : 'create',
      };

      console.log('download payload status:', requestBody.status, 'id:', requestBody.id ?? null, 'action:', requestBody.action);

      await requestDownloadsApi(DOWNLOADS_API_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(requestBody),
      });

      setDownloadsMessage(isEditMode ? 'แก้ไขเอกสารเรียบร้อยแล้ว' : 'เพิ่มเอกสารเรียบร้อยแล้ว', 'success');
      showDownloadsForm(false);
      clearDownloadsForm();
      await fetchDownloadsList();
    } catch (error) {
      setDownloadsMessage(toUserErrorMessage(error, 'บันทึกข้อมูลเอกสารไม่สำเร็จ'), 'error');
    } finally {
      setButtonLoading(saveDownloadBtn, false);
      unlockRequest('downloadsSubmit');
    }
  }

  async function initDownloadsPage() {
    const admin = await fetchMe();

    if (!admin) {
      window.location.href = 'login.html';
      return;
    }

    renderAdminIdentity(admin);

    normalizeDownloadsStatusFormOptions();

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

    if (downloadFile) {
      downloadFile.addEventListener('change', handleDownloadFileSelect);
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
          await handleDeleteDownload(id, deleteBtn);
        }
      }
    });
  }

  async function initContactPage() {
    const admin = await fetchMe();

    if (!admin) {
      window.location.href = 'login.html';
      return;
    }

    renderAdminIdentity(admin);

    await loadContactSettings();

    if (contactForm) {
      contactForm.addEventListener('submit', handleContactFormSubmit);
    }
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

    renderAdminIdentity(admin);

    try {
      const stats = await fetchDashboardStats();

      if (stats && stats.unauthorized) {
        window.location.href = 'login.html';
        return;
      }

      const downloadsStats = await fetchDownloadsCountFromAdminApi();

      if (downloadsStats && downloadsStats.unauthorized) {
        window.location.href = 'login.html';
        return;
      }

      if (downloadsStats && Number.isInteger(downloadsStats.count)) {
        stats.downloads_count = downloadsStats.count;
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

    if (homepageStatsForm) {
      await loadHomepageStatsSettings();
      homepageStatsForm.addEventListener('submit', handleHomepageStatsSubmit);
    }
  }

  async function logout() {
    await fetch(LOGOUT_API_URL, {
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
        const loginResponse = await login(username, password);

        if (loginResponse.status === 401) {
          loginMessage.textContent = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
          return;
        }

        if (!loginResponse.ok) {
          console.error(`Login request failed with status ${loginResponse.status}`);
          loginMessage.textContent = 'ไม่สามารถเข้าสู่ระบบได้ กรุณาลองใหม่';
          return;
        }

        const result = loginResponse.data || {};

        if (result.success && result.logged_in) {
          const admin = await fetchMe();
          if (!admin) {
            loginMessage.textContent = 'สร้าง Session ไม่สำเร็จ กรุณาลองเข้าสู่ระบบอีกครั้ง';
            return;
          }
          window.location.replace(getSafeLoginReturnUrl());
          return;
        }

        loginMessage.textContent = 'ไม่สามารถเข้าสู่ระบบได้ กรุณาลองใหม่';
      } catch (error) {
        console.error('Unable to complete login request:', error);
        loginMessage.textContent = 'ไม่สามารถเข้าสู่ระบบได้ กรุณาลองใหม่';
      }
    });

    fetchMe().then((admin) => {
      if (admin) {
        window.location.replace(getSafeLoginReturnUrl());
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

  if (contactForm) {
    initContactPage();
  }

  if (logoutBtn) {
    logoutBtn.addEventListener('click', logout);
  }
})();
