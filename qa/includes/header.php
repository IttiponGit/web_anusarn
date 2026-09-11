<?php
if (!defined('QA_ROOT')) {
    require_once __DIR__ . '/bootstrap.php';
}
qa_require_login();
$user = qa_current_user();
$currentYear = qa_current_year_row();
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= h($pageTitle) ?> | <?= h(QA_APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= h(qa_url('assets/css/app.css')) ?>">
</head>
<body>
<div class="app-shell">
    <?php require QA_ROOT . '/includes/sidebar.php'; ?>
    <div class="app-main">
        <header class="topbar">
            <button class="icon-button" id="sidebarToggle" type="button" aria-label="เปิด/ปิดเมนู">☰</button>
            <div class="topbar-title">
                <strong><?= h(QA_SCHOOL_NAME) ?></strong>
                <span>ระบบประกันคุณภาพสถานศึกษา</span>
            </div>
            <div class="topbar-actions">
                <span class="year-chip">ปีงบประมาณ <?= $currentYear ? h($currentYear['fiscal_year_be']) : '-' ?></span>
                <span class="user-chip"><?= h($user ? $user['display_name'] : '') ?></span>
                <a class="text-link" href="<?= h(qa_url('logout.php')) ?>">ออกจากระบบ</a>
            </div>
        </header>

        <main class="content">
            <?php if (!empty($breadcrumbs)): ?>
                <nav class="breadcrumbs" aria-label="breadcrumb">
                    <a href="<?= h(qa_url('dashboard.php')) ?>">Dashboard</a>
                    <?php foreach ($breadcrumbs as $crumb): ?>
                        <span>/</span>
                        <?php if (!empty($crumb['url'])): ?>
                            <a href="<?= h($crumb['url']) ?>"><?= h($crumb['label']) ?></a>
                        <?php else: ?>
                            <span><?= h($crumb['label']) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <section class="page-header">
                <div>
                    <h1><?= h($pageHeading) ?></h1>
                    <?php if ($pageDescription): ?><p><?= h($pageDescription) ?></p><?php endif; ?>
                </div>
                <span class="prototype-badge">DEVELOPMENT</span>
            </section>
