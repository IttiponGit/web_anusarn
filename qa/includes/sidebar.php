<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="brand-mark">QA</div>
        <div>
            <strong>ระบบประกันคุณภาพ</strong>
            <span>สถานศึกษา</span>
        </div>
    </div>

    <nav class="nav-menu">
        <a class="nav-item <?= $activeMenu === 'dashboard' ? 'active' : '' ?>" href="<?= h(qa_url('dashboard.php')) ?>">
            <span>▦</span><span>Dashboard</span>
        </a>

        <div class="nav-section">แผนและงบประมาณ</div>
        <a class="nav-item <?= $activeMenu === 'plans' ? 'active' : '' ?>" href="<?= h(qa_url('plans/index.php')) ?>">
            <span>◇</span><span>แผนพัฒนาคุณภาพ</span>
        </a>
        <a class="nav-item <?= $activeMenu === 'budget' ? 'active' : '' ?>" href="<?= h(qa_url('budget/index.php')) ?>">
            <span>฿</span><span>งบประมาณประจำปี</span>
        </a>
        <a class="nav-item <?= $activeMenu === 'allocation' ? 'active' : '' ?>" href="<?= h(qa_url('budget/allocation.php')) ?>">
            <span>◔</span><span>จัดสรรงบ 4 ฝ่าย</span>
        </a>

        <div class="nav-section">โครงการ / งาน / กิจกรรม</div>
        <a class="nav-item <?= $activeMenu === 'projects' ? 'active' : '' ?>" href="<?= h(qa_url('projects/index.php')) ?>">
            <span>▤</span><span>โครงการทั้งหมด</span>
        </a>
        <a class="nav-item <?= $activeMenu === 'project-create' ? 'active' : '' ?>" href="<?= h(qa_url('projects/create.php')) ?>">
            <span>＋</span><span>เสนอโครงการใหม่</span>
        </a>
        <a class="nav-item <?= $activeMenu === 'approvals' ? 'active' : '' ?>" href="<?= h(qa_url('approvals/index.php')) ?>">
            <span>✓</span><span>ตรวจสอบ / อนุมัติ</span>
        </a>
        <a class="nav-item <?= $activeMenu === 'project-changes' ? 'active' : '' ?>" href="<?= h(qa_url('projects/changes.php')) ?>">
            <span>↺</span><span>แก้ไข / ยกเลิกโครงการ</span>
        </a>

        <div class="nav-section">ติดตามคุณภาพ</div>
        <a class="nav-item <?= $activeMenu === 'monitoring' ? 'active' : '' ?>" href="<?= h(qa_url('monitoring/index.php')) ?>">
            <span>↗</span><span>ติดตามและประเมินผล</span>
        </a>
        <a class="nav-item <?= $activeMenu === 'evidences' ? 'active' : '' ?>" href="<?= h(qa_url('evidences/index.php')) ?>">
            <span>▣</span><span>คลังหลักฐาน</span>
        </a>
        <a class="nav-item <?= $activeMenu === 'quality' ? 'active' : '' ?>" href="<?= h(qa_url('quality/index.php')) ?>">
            <span>◎</span><span>มาตรฐาน / ตัวชี้วัด</span>
        </a>

        <div class="nav-section">สารสนเทศและระบบ</div>
        <a class="nav-item <?= $activeMenu === 'reports' ? 'active' : '' ?>" href="<?= h(qa_url('reports/index.php')) ?>">
            <span>▥</span><span>รายงาน / SAR / สมศ.</span>
        </a>
        <a class="nav-item <?= $activeMenu === 'settings' ? 'active' : '' ?>" href="<?= h(qa_url('settings/index.php')) ?>">
            <span>⚙</span><span>ตั้งค่าระบบ</span>
        </a>
        <a class="nav-item <?= $activeMenu === 'roadmap' ? 'active' : '' ?>" href="<?= h(qa_url('settings/roadmap.php')) ?>">
            <span>☑</span><span>Roadmap การพัฒนา</span>
        </a>
    </nav>
</aside>
