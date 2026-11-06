<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="brand-mark">QA</div>
        <div>
            <strong>ระบบประกันคุณภาพ</strong>
            <span>สถานศึกษา</span>
        </div>
    </div>

    <nav class="nav-menu sidebar-accordion" id="qaSidebarNav">
        <?php if (qa_menu_can('dashboard')): ?>
        <a class="nav-item nav-item-top <?= $activeMenu === 'dashboard' ? 'active' : '' ?>" href="<?= h(qa_url('dashboard.php')) ?>">
            <span>▦</span><span>Dashboard</span>
        </a>
        <?php endif; ?>

        <?php if (qa_menu_group_can(array('plans','budget','allocation'))): ?>
        <div class="nav-group" data-nav-group="plan-budget">
            <button class="nav-group-toggle" type="button" aria-expanded="false">
                <span class="nav-group-title">แผนและงบประมาณ</span>
                <span class="nav-group-chevron">⌄</span>
            </button>
            <div class="nav-group-items">
                <?php if (qa_menu_can('plans')): ?>
                <a class="nav-item <?= $activeMenu === 'plans' ? 'active' : '' ?>" href="<?= h(qa_url('plans/index.php')) ?>"><span>◇</span><span>แผนพัฒนาคุณภาพ</span></a>
                <?php endif; ?>

                <?php if (qa_menu_can('budget')): ?>
                <a class="nav-item <?= $activeMenu === 'budget' ? 'active' : '' ?>" href="<?= h(qa_url('budget/index.php')) ?>"><span>฿</span><span>งบประมาณประจำปี</span></a>
                <?php endif; ?>

                <?php if (qa_menu_can('allocation')): ?>
                <a class="nav-item <?= $activeMenu === 'allocation' ? 'active' : '' ?>" href="<?= h(qa_url('budget/allocation.php')) ?>">
                    <span>◔</span>
                    <span class="allocation-nav-label">จัดสรรงบ 5 ฝ่าย
                        <span class="division-nav-dots" aria-hidden="true"><i class="dot-academic"></i><i class="dot-budget"></i><i class="dot-personnel"></i><i class="dot-general"></i><i class="dot-student-affairs"></i></span>
                    </span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (qa_menu_group_can(array('projects','project-create','approvals','project-changes','my-activities'))): ?>
        <div class="nav-group" data-nav-group="projects">
            <button class="nav-group-toggle" type="button" aria-expanded="false">
                <span class="nav-group-title">โครงการ / งาน / กิจกรรม</span>
                <span class="nav-group-chevron">⌄</span>
            </button>
            <div class="nav-group-items">
                <?php if (qa_menu_can('projects')): ?>
                <a class="nav-item <?= $activeMenu === 'projects' ? 'active' : '' ?>" href="<?= h(qa_url('projects/index.php')) ?>"><span>▤</span><span>โครงการทั้งหมด</span></a>
                <?php endif; ?>

                <?php if (qa_menu_can('project-create')): ?>
                <a class="nav-item <?= $activeMenu === 'project-create' ? 'active' : '' ?>" href="<?= h(qa_url('projects/create.php')) ?>"><span>＋</span><span>เสนอโครงการใหม่</span></a>
                <?php endif; ?>

                <?php if (qa_menu_can('approvals')): ?>
                <a class="nav-item <?= $activeMenu === 'approvals' ? 'active' : '' ?>" href="<?= h(qa_url('approvals/index.php')) ?>"><span>✓</span><span>ตรวจสอบ / อนุมัติ</span></a>
                <?php endif; ?>

                <?php if (qa_menu_can('project-changes')): ?>
                <a class="nav-item <?= $activeMenu === 'project-changes' ? 'active' : '' ?>" href="<?= h(qa_url('projects/changes.php')) ?>"><span>↺</span><span>แก้ไข / ยกเลิกโครงการ</span></a>
                <?php endif; ?>
                <?php if (qa_menu_can('my-activities')): ?>
                <a class="nav-item <?= $activeMenu === 'my-activities' ? 'active' : '' ?>" href="<?= h(qa_url('activities/index.php')) ?>"><span>◫</span><span>กิจกรรมที่รับผิดชอบ</span></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (qa_menu_group_can(array('monitoring','evidences','quality'))): ?>
        <div class="nav-group" data-nav-group="quality">
            <button class="nav-group-toggle" type="button" aria-expanded="false">
                <span class="nav-group-title">ติดตามคุณภาพ</span>
                <span class="nav-group-chevron">⌄</span>
            </button>
            <div class="nav-group-items">
                <?php if (qa_menu_can('monitoring')): ?>
                <a class="nav-item <?= $activeMenu === 'monitoring' ? 'active' : '' ?>" href="<?= h(qa_url('monitoring/index.php')) ?>"><span>↗</span><span>ติดตามและประเมินผล</span></a>
                <?php endif; ?>

                <?php if (qa_menu_can('evidences')): ?>
                <a class="nav-item <?= $activeMenu === 'evidences' ? 'active' : '' ?>" href="<?= h(qa_url('evidences/index.php')) ?>"><span>▣</span><span>คลังหลักฐาน</span></a>
                <?php endif; ?>

                <?php if (qa_menu_can('quality')): ?>
                <a class="nav-item <?= $activeMenu === 'quality' ? 'active' : '' ?>" href="<?= h(qa_url('quality/index.php')) ?>"><span>◎</span><span>มาตรฐาน / ตัวชี้วัด</span></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (qa_menu_group_can(array('reports','sar-builder','obec-workload','settings','admin-users','permissions','obec-master','roadmap'))): ?>
        <div class="nav-group" data-nav-group="information">
            <button class="nav-group-toggle" type="button" aria-expanded="false">
                <span class="nav-group-title">สารสนเทศและระบบ</span>
                <span class="nav-group-chevron">⌄</span>
            </button>
            <div class="nav-group-items">
                <?php if (qa_menu_can('reports')): ?>
                <a class="nav-item <?= $activeMenu === 'reports' ? 'active' : '' ?>" href="<?= h(qa_url('reports/index.php')) ?>"><span>▥</span><span>รายงาน / SAR / สมศ.</span></a>
                <?php endif; ?>

                <?php if (qa_menu_can('sar-builder')): ?>
                <a class="nav-item <?= $activeMenu === 'sar-builder' ? 'active' : '' ?>" href="<?= h(qa_url('reports/sar/index.php')) ?>"><span>▧</span><span>SAR Builder</span></a>
                <?php endif; ?>

                <?php if (qa_menu_can('obec-workload')): ?>
                <a class="nav-item <?= $activeMenu === 'obec-workload' ? 'active' : '' ?>" href="<?= h(qa_url('reports/obec-workload.php')) ?>"><span>▦</span><span>ปริมาณงาน สพฐ. / แบบ 10</span></a>
                <?php endif; ?>

                <?php if (qa_menu_can('settings')): ?>
                <a class="nav-item <?= $activeMenu === 'settings' ? 'active' : '' ?>" href="<?= h(qa_url('settings/index.php')) ?>"><span>⚙</span><span>ตั้งค่าระบบ</span></a>
                <?php endif; ?>

                <?php if (qa_menu_can('admin-users')): ?>
                <a class="nav-item <?= $activeMenu === 'admin-users' ? 'active' : '' ?>" href="<?= h(qa_url('settings/users.php')) ?>"><span>♙</span><span>จัดการผู้ใช้งาน</span></a>
                <?php endif; ?>
                <?php if (qa_menu_can('permissions')): ?>
                <a class="nav-item <?= $activeMenu === 'permissions' ? 'active' : '' ?>" href="<?= h(qa_url('settings/permissions.php')) ?>"><span>⌘</span><span>สิทธิ์และ Role</span></a>
                <?php endif; ?>

                <?php if (qa_menu_can('obec-master')): ?>
                <a class="nav-item <?= $activeMenu === 'obec-master' ? 'active' : '' ?>" href="<?= h(qa_url('settings/obec-subactivities.php')) ?>"><span>≡</span><span>Master กิจกรรมย่อย สพฐ.</span></a>
                <?php endif; ?>

                <?php if (qa_menu_can('roadmap')): ?>
                <a class="nav-item <?= $activeMenu === 'roadmap' ? 'active' : '' ?>" href="<?= h(qa_url('settings/roadmap.php')) ?>"><span>☑</span><span>Roadmap การพัฒนา</span></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="nav-group-static">
            <div class="nav-group-static-title">เว็บไซต์โรงเรียน</div>
            <a class="nav-item nav-item-main-site" href="/" title="กลับเว็บไซต์หลักของโรงเรียน"><span>←</span><span>กลับเว็บไซต์หลัก</span></a>
        </div>
    </nav>
</aside>
