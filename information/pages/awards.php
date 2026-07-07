<!doctype html>
<html lang="th">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ผลงานและรางวัล | โรงเรียนโสตศึกษาอนุสารสุนทร</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    #awardGroupTable tbody tr:nth-child(odd)>* {
      --bs-table-bg: #ffffff;
      background-color: #ffffff;
    }

    #awardGroupTable tbody tr:nth-child(even)>* {
      --bs-table-bg: #eef6ff;
      background-color: #eef6ff;
    }
  </style>
</head>

<body data-page="awards">
  <div class="layout-shell">
    <aside class="app-sidebar d-none d-lg-flex flex-column">
      <div class="sidebar-brand">
        <div>
          <h1>สารสนเทศโรงเรียน</h1>
          <p data-school-name>โรงเรียนโสตศึกษาอนุสารสุนทร</p>
        </div>
      </div>
      <div class="sidebar-meta"><span class="badge rounded-pill">ปีการศึกษา <span
            data-academic-year>2569</span></span><span class="badge rounded-pill">รางวัล</span></div>
      <ul class="nav flex-column app-menu js-sidebar-menu"></ul>
      <a class="main-site-link" href="../../">
        <i class="bi bi-house-door-fill"></i>
        <span>กลับเว็บไซต์หลัก</span>
      </a>
    </aside>
    <div class="content-area">
      <header class="app-topbar d-flex align-items-center">
        <div class="container-fluid d-flex align-items-center justify-content-between gap-3">
          <div class="d-flex align-items-center gap-3 topbar-shell"><button
              class="btn btn-primary d-lg-none rounded-circle shadow-sm" type="button" data-bs-toggle="offcanvas"
              data-bs-target="#mobileSidebar"><i class="bi bi-list"></i></button>
            <div class="d-flex flex-column">
              <h1 class="page-title" id="pageTitle">ผลงานและรางวัล</h1>
              <p class="page-subtitle" id="pageSubtitle">เกียรติยศและผลงานเด่นของโรงเรียน • ปีการศึกษา 2569</p>
            </div>
          </div>
          <nav aria-label="breadcrumb" class="d-none d-md-block">
            <ol class="breadcrumb" id="breadcrumbTrail"></ol>
          </nav>
        </div>
      </header>
      <main class="page-content">
        <div class="container-fluid">
          <div id="errorBox" class="alert alert-danger d-none"></div>
          <section class="hero-panel rounded-4 p-3 p-xl-4 mb-3">
            <div class="row align-items-center g-4">
              <div class="col-lg-8"><span class="badge rounded-pill bg-white text-primary mb-3 px-3 py-2">Awards
                  Showcase</span>
                <h2 class="display-6 fw-bold mb-3" id="heroTitle">ผลงานและรางวัล</h2>
                <p class="lead mb-0" id="heroSubtitle">รวบรวมรางวัลและผลงานในรูปแบบการ์ด</p>
              </div>
              <div class="col-lg-4">
                <div class="summary-grid">
                  <div class="summary-chip"><span class="label">รางวัลนักเรียน</span><strong data-awards-summary="student">-</strong></div>
                  <div class="summary-chip"><span class="label">รางวัลครูและบุคลากร</span><strong data-awards-summary="staff">-</strong></div>
                  <div class="summary-chip"><span class="label">รางวัลสถานศึกษา</span><strong data-awards-summary="institution">-</strong></div>
                </div>
              </div>
            </div>
          </section>
          <section class="award-section">
            <div class="award-section-head">
              <div>
                <span class="badge badge-soft-primary rounded-pill mb-2">Award Groups</span>
                <h3 class="h5 mb-1"><i class="bi bi-award-fill me-2 text-primary"></i>รายการผลงานและรางวัล</h3>
                <p class="award-section-desc mb-0" id="awardGroupDescription">เลือกประเภทหลักเพื่อดูรายการรางวัล</p>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3" id="awardGroupTabs" aria-label="แท็บประเภทรางวัล"></div>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0" id="awardGroupTable">
                <thead>
                  <tr id="awardTableHead">
                    <th class="text-center">ชื่อรางวัล</th>
                    <th class="text-nowrap text-center">ปี</th>
                    <th class="text-nowrap text-center">จำนวนรางวัลที่ได้รับ</th>
                    <th class="text-nowrap text-center">จำนวนผู้ได้รับ</th>
                    <th class="text-center">สรุปผลรางวัล</th>
                    <th class="text-center">หน่วยงาน</th>
                    <th class="text-center">ระดับ</th>
                  </tr>
                </thead>
                <tbody id="awardGroupTableBody"></tbody>
              </table>
            </div>
          </section>
          <footer class="mt-4 pb-1">
            <p class="footer-note mb-0">Information Portal | <span data-school-name>โรงเรียนโสตศึกษาอนุสารสุนทร</span> |
              พ.ศ. <span id="copyrightYear"></span></p>
          </footer>
        </div>
      </main>
    </div>
  </div>
  <div class="offcanvas offcanvas-start mobile-sidebar" tabindex="-1" id="mobileSidebar"
    aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header">
      <div>
        <h5 class="offcanvas-title mb-1" id="mobileSidebarLabel">สารสนเทศโรงเรียน</h5>
        <p class="mb-0 small" data-school-name>โรงเรียนโสตศึกษาอนุสารสุนทร</p>
      </div><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
      <div class="sidebar-meta mb-3"><span class="badge rounded-pill">ปีการศึกษา <span
            data-academic-year>2569</span></span></div>
      <ul class="nav flex-column mobile-menu js-offcanvas-menu"></ul><a class="main-site-link" href="../../"><i
          class="bi bi-house-door-fill"></i><span>กลับเว็บไซต์หลัก</span></a>
    </div>
  </div>
  <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/vendor/chartjs/chart.umd.min.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/charts.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', async () => {
      const tabContainer = document.getElementById('awardGroupTabs');
      const tableBody = document.getElementById('awardGroupTableBody');
      const groupDescription = document.getElementById('awardGroupDescription');
      const summaryItems = document.querySelectorAll('[data-awards-summary]');
      const apiEndpoint = '/information/api/awards.php';
      const groups = [
        {
          key: 'student',
          title: 'รางวัลนักเรียน',
          description: 'ผลงานและรางวัลที่นักเรียนได้รับ'
        },
        {
          key: 'staff',
          title: 'รางวัลครูและบุคลากร',
          description: 'ผลงานและรางวัลที่ผู้บริหาร ครู และบุคลากรทางการศึกษาได้รับ'
        },
        {
          key: 'institution',
          title: 'รางวัลสถานศึกษา',
          description: 'ผลงานและรางวัลที่โรงเรียนได้รับ'
        }
      ];
      let activeGroup = groups[0].key;

      if (!tabContainer || !tableBody) {
        return;
      }

      const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

      const setSummary = (summary = {}) => {
        summaryItems.forEach((item) => {
          const value = Number(summary[item.dataset.awardsSummary] || 0);
          item.textContent = `${value.toLocaleString('th-TH')} รายการ`;
        });
      };

      const setLoading = () => {
        tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>กำลังโหลดข้อมูลรางวัล...</td></tr>';
      };

      const setError = () => {
        tableBody.innerHTML = '<tr><td colspan="7"><div class="alert alert-danger mb-0">ไม่สามารถโหลดข้อมูลรางวัลได้ กรุณาลองใหม่อีกครั้ง</div></td></tr>';
      };

      const renderTable = (group, items) => {
        if (groupDescription) {
          groupDescription.textContent = group.description || '';
        }

        if (!items.length) {
          tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">ยังไม่มีข้อมูลรางวัลในหมวดหมู่นี้</td></tr>';
          return;
        }

        const sortedItems = [...items].sort((a, b) => {
          const yearA = Number(a.award_year || 0);
          const yearB = Number(b.award_year || 0);
          if (yearA !== yearB) {
            return yearB - yearA;
          }

          const orderA = Number(a.sort_order || 0);
          const orderB = Number(b.sort_order || 0);
          if (orderA !== orderB) {
            return orderA - orderB;
          }

          return Number(a.id || 0) - Number(b.id || 0);
        });

        tableBody.innerHTML = sortedItems.map((item) => {
          const recipientCount = Number(item.recipient_count || 0);
          const awardReceivedCount = Number(item.award_received_count || 0);
          const resultSummary = String(item.result_summary || '').trim();

          return `<tr>
            <td>${escapeHtml(item.title || 'ไม่ระบุชื่อรางวัล')}</td>
            <td class="text-nowrap text-center">${escapeHtml(item.award_year || '-')}</td>
            <td class="text-nowrap text-center">${awardReceivedCount.toLocaleString('th-TH')}</td>
            <td class="text-nowrap text-center">${recipientCount.toLocaleString('th-TH')}</td>
            <td>${resultSummary ? escapeHtml(resultSummary) : '-'}</td>
            <td>${escapeHtml(item.organizer || '-')}</td>
            <td class="text-nowrap">${escapeHtml(item.level || '-')}</td>
          </tr>`;
        }).join('');
      };

      const loadAwards = async (groupKey) => {
        const group = groups.find((item) => item.key === groupKey) || groups[0];
        activeGroup = group.key;
        setLoading();

        try {
          const url = new URL(apiEndpoint, window.location.href);
          url.searchParams.set('group', group.key);
          url.searchParams.set('limit', '100');

          const response = await fetch(url.toString(), {
            cache: 'no-store',
            headers: {
              Accept: 'application/json'
            }
          });

          if (!response.ok) {
            throw new Error('Cannot load awards data');
          }

          const payload = await response.json();
          if (!payload.success) {
            throw new Error(payload.error || 'Cannot load awards data');
          }

          setSummary(payload.summary);
          renderTable(group, Array.isArray(payload.data) ? payload.data : []);
        } catch (error) {
          setError();
        }
      };

      const renderTabs = () => {
        tabContainer.innerHTML = groups.map((group, index) => `
          <button class="btn btn-sm ${index === 0 ? 'btn-primary' : 'btn-outline-primary'} rounded-pill" type="button" data-award-group="${escapeHtml(group.key)}">
            ${escapeHtml(group.title)}
          </button>
        `).join('');

        tabContainer.querySelectorAll('[data-award-group]').forEach((button) => {
          button.addEventListener('click', () => {
            const selected = button.dataset.awardGroup;
            tabContainer.querySelectorAll('[data-award-group]').forEach((target) => {
              target.classList.toggle('btn-primary', target === button);
              target.classList.toggle('btn-outline-primary', target !== button);
            });
            loadAwards(selected);
          });
        });
      };

      renderTabs();
      loadAwards(activeGroup);
    });
  </script>
</body>

</html>
