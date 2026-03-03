<?php
if (!defined('BASE_URL')) define('BASE_URL', '');
requireLogin();
$currentUser = getCurrentUser();
$flashMessages = getFlash();
$pageTitle = $pageTitle ?? APP_NAME;
$companyLogo = getSetting('company_logo', '');
$companyName = getSetting('company_name', 'HSG Aviation');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> | <?= sanitize($companyName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <?php if (isset($extraCss)) echo $extraCss; ?>
    <script>const BASE_URL = "<?= BASE_URL ?>";</script>
</head>
<body>

<div id="sidebar" class="sidebar">
    <div class="sidebar-brand">
        <a href="<?= BASE_URL ?>/index.php" class="brand-link">
            <?php if ($companyLogo): ?>
                <img src="<?= BASE_URL ?>/<?= sanitize($companyLogo) ?>" alt="Logo" class="brand-logo">
            <?php else: ?>
                <div class="brand-icon"><i class="bi bi-send-fill"></i></div>
            <?php endif; ?>
            <div class="brand-text">
                <span class="brand-name"><?= sanitize($companyName) ?></span>
                <span class="brand-subtitle">Teklif Yönetimi</span>
            </div>
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <span class="nav-label">Ana Menü</span>
            <a href="<?= BASE_URL ?>/index.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'index.php' && dirname($_SERVER['PHP_SELF']) == '/' ? 'active' : '' ?>">
                <i class="bi bi-grid-fill"></i> <span>Dashboard</span>
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-label">Teklifler</span>
            <a href="<?= BASE_URL ?>/quotes/index.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/quotes/') !== false ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text-fill"></i> <span>Teklifler</span>
            </a>
            <a href="<?= BASE_URL ?>/quotes/create.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'create.php' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle-fill"></i> <span>Yeni Teklif</span>
            </a>
            <a href="<?= BASE_URL ?>/templates/index.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/templates/') !== false ? 'active' : '' ?>">
                <i class="bi bi-layout-text-window-reverse"></i> <span>Şablonlar</span>
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-label">Müşteriler & Ürünler</span>
            <a href="<?= BASE_URL ?>/customers/index.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/customers/') !== false ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> <span>Müşteriler</span>
            </a>
            <a href="<?= BASE_URL ?>/products/index.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/products/') !== false ? 'active' : '' ?>">
                <i class="bi bi-box-seam-fill"></i> <span>Ürün & Hizmetler</span>
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-label">Sistem</span>
            <a href="<?= BASE_URL ?>/settings/index.php" class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/settings/') !== false ? 'active' : '' ?>">
                <i class="bi bi-gear-fill"></i> <span>Ayarlar</span>
            </a>
            <a href="<?= BASE_URL ?>/logout.php" class="nav-item text-danger-nav">
                <i class="bi bi-box-arrow-right"></i> <span>Çıkış</span>
            </a>
        </div>
    </nav>
</div>

<div class="main-wrapper">
    <header class="top-header">
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <div class="header-title">
            <h1><?= sanitize($pageTitle) ?></h1>
        </div>
        <div class="header-actions">
            <a href="<?= BASE_URL ?>/quotes/create.php" class="btn btn-primary btn-sm d-none d-md-flex">
                <i class="bi bi-plus-lg me-1"></i> Yeni Teklif
            </a>
            <div class="dropdown">
                <button class="user-btn dropdown-toggle" data-bs-toggle="dropdown">
                    <div class="user-avatar"><?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?></div>
                    <span class="d-none d-md-inline"><?= sanitize($currentUser['name'] ?? 'Kullanıcı') ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/settings/index.php"><i class="bi bi-gear me-2"></i>Ayarlar</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Çıkış</a></li>
                </ul>
            </div>
        </div>
    </header>

    <main class="content-area">
        <?php foreach ($flashMessages as $flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' || $flash['type'] === 'danger' ? 'x-circle' : 'info-circle') ?>-fill me-2"></i>
                <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>
