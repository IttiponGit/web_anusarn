(function () {
  const loginForm = document.getElementById('loginForm');
  const loginMessage = document.getElementById('loginMessage');
  const logoutBtn = document.getElementById('logoutBtn');
  const adminName = document.getElementById('adminName');
  const statsMessage = document.getElementById('statsMessage');
  const newsCount = document.getElementById('newsCount');
  const personnelCount = document.getElementById('personnelCount');
  const downloadsCount = document.getElementById('downloadsCount');
  const adminCount = document.getElementById('adminCount');

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

  if (adminName) {
    initDashboard();
  }

  if (logoutBtn) {
    logoutBtn.addEventListener('click', logout);
  }
})();
