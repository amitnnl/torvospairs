<?php
// includes/header.php
// Usage: include at top of every page
// Expects: $pageTitle (string), $pageIcon (fa class), $activePage (string)

if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/database.php';
requireLogin();

$user = currentUser();
$initials = strtoupper(substr($user['name'], 0, 1));

// Low stock count for notification badge
$db = getDB();
$lowStockCount = $db->query("SELECT COUNT(*) FROM products WHERE quantity <= min_stock AND status = 'active'")->fetchColumn();

// Flash messages
$flashSuccess = getFlash('success');
$flashError   = getFlash('error');
$flashWarning = getFlash('warning');
$flashInfo    = getFlash('info');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> – <?= getSetting('site_title', 'TORVO SPAIR') ?></title>
    <meta name="description" content="<?= getSetting('site_title', 'TORVO SPAIR') ?> Inventory Management System">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <!-- Load Chart.js early to prevent "Chart is not defined" errors in dashboard/reports -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: <?= getSetting('primary_color', '#2563eb') ?>;
            --primary-light: <?= getSetting('primary_color_light', '#1d4ed8') ?>;
        }
    </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="app-layout">
<!-- ====== SIDEBAR ====== -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <?php $dynLogo = getSetting('logo_image'); if ($dynLogo): ?>
            <img src="<?= UPLOAD_URL . $dynLogo ?>" alt="Logo" style="height:36px;border-radius:6px;background:#fff;padding:3px;">
        <?php else: ?>
            <div class="brand-icon"><i class="fas fa-wrench" style="color:#fff;"></i></div>
        <?php endif; ?>
        <div class="brand-text">
            <h2><?= getSetting('site_title', 'TORVO SPAIR') ?></h2>
            <span>Inventory System</span>
        </div>
    </div>

    <div class="sidebar-menu">
        <div class="sidebar-section">
            <div class="sidebar-section-title">Main</div>
            <a href="<?= APP_URL ?>/pages/dashboard.php" class="sidebar-nav-item <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas fa-chart-pie"></i></div>
                Dashboard
            </a>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-section-title">Inventory</div>
            <a href="<?= APP_URL ?>/pages/products.php" class="sidebar-nav-item <?= ($activePage ?? '') === 'products' ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas fa-boxes"></i></div>
                Products
            </a>
            <a href="<?= APP_URL ?>/pages/tools.php" class="sidebar-nav-item <?= ($activePage ?? '') === 'tools' ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas fa-tools"></i></div>
                Power Tools
            </a>
            <a href="<?= APP_URL ?>/pages/categories.php" class="sidebar-nav-item <?= ($activePage ?? '') === 'categories' ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas fa-layer-group"></i></div>
                Categories
            </a>
            <a href="<?= APP_URL ?>/pages/stock.php" class="sidebar-nav-item <?= ($activePage ?? '') === 'stock' ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas fa-warehouse"></i></div>
                Stock Management
                <?php if ($lowStockCount > 0): ?>
                <span class="badge"><?= $lowStockCount ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= APP_URL ?>/pages/compatibility.php" class="sidebar-nav-item <?= ($activePage ?? '') === 'compatibility' ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas fa-link"></i></div>
                Compatibility Map
            </a>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-section-title">Reports & B2B</div>
            <a href="<?= APP_URL ?>/pages/reports.php" class="sidebar-nav-item <?= ($activePage ?? '') === 'reports' ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas fa-chart-bar"></i></div>
                Reports
            </a>
            <a href="<?= APP_URL ?>/pages/sales_report.php" class="sidebar-nav-item <?= ($activePage ?? '') === 'sales_report' ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas fa-chart-line"></i></div>
                Sales Analytics
            </a>
            <a href="<?= APP_URL ?>/pages/rfq_manager.php" class="sidebar-nav-item <?= ($activePage ?? '') === 'rfqs' ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas fa-file-alt"></i></div>
                RFQ Manager
            </a>
            <a href="<?= APP_URL ?>/pages/customers_b2b.php" class="sidebar-nav-item <?= ($activePage ?? '') === 'customers_b2b' ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas fa-building"></i></div>
                B2B Customers
            </a>
            <a href="<?= APP_URL ?>/pages/orders_admin.php" class="sidebar-nav-item <?= ($activePage ?? '') === 'orders_admin' ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas fa-truck"></i></div>
                Order Management
            </a>
            <a href="<?= APP_URL ?>/pages/discount_manager.php" class="sidebar-nav-item <?= ($activePage ?? '') === 'discounts' ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas fa-tags"></i></div>
                Pricing & Discounts
            </a>
            <a href="<?= APP_URL ?>/pages/enquiries.php" class="sidebar-nav-item <?= ($activePage ?? '') === 'enquiries' ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas fa-envelope-open"></i></div>
                Enquiries
            </a>
            <a href="<?= APP_URL ?>/pages/api_docs.php" class="sidebar-nav-item <?= ($activePage ?? '') === 'api_docs' ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas fa-code"></i></div>
                API Docs
            </a>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-section-title">Public Portal</div>
            <a href="<?= APP_URL ?>/portal/home.php" target="_blank" class="sidebar-nav-item">
                <div class="nav-icon"><i class="fas fa-external-link-alt"></i></div>
                View Website
            </a>
            <a href="<?= APP_URL ?>/portal/catalogue.php" target="_blank" class="sidebar-nav-item">
                <div class="nav-icon"><i class="fas fa-store"></i></div>
                B2B Catalogue
            </a>
        </div>

        <?php if (isAdmin()): ?>
        <div class="sidebar-section">
            <div class="sidebar-section-title">Admin</div>
            <a href="<?= APP_URL ?>/pages/users.php" class="sidebar-nav-item <?= ($activePage ?? '') === 'users' ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas fa-users"></i></div>
                User Management
            </a>
            <a href="<?= APP_URL ?>/pages/settings.php" class="sidebar-nav-item <?= ($activePage ?? '') === 'settings' ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas fa-cogs"></i></div>
                Site Settings
            </a>
        </div>
        <?php endif; ?>

        <div class="sidebar-section">
            <div class="sidebar-section-title">Account</div>
            <a href="<?= APP_URL ?>/pages/profile.php" class="sidebar-nav-item <?= ($activePage ?? '') === 'profile' ? 'active' : '' ?>">
                <div class="nav-icon"><i class="fas fa-user-circle"></i></div>
                My Profile
            </a>
        </div>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <a href="<?= APP_URL ?>/pages/profile.php" style="display:flex;align-items:center;gap:0.75rem;flex:1;text-decoration:none;min-width:0;">
                <div class="user-avatar"><?= $initials ?></div>
                <div class="user-info">
                    <div class="uname"><?= htmlspecialchars($user['name']) ?></div>
                    <div class="urole"><?= htmlspecialchars($user['role']) ?></div>
                </div>
            </a>
            <a href="<?= APP_URL ?>/pages/logout.php" class="logout-btn" data-tooltip="Sign Out">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>

<!-- ====== MAIN ====== -->
<div class="main-content">
    <header class="top-header">
        <div class="header-left">
            <button class="toggle-sidebar-btn" onclick="toggleSidebar()" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <div class="page-title">
                    <?php if (!empty($pageIcon)): ?><i class="<?= $pageIcon ?>"></i>&nbsp;<?php endif; ?>
                    <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
                </div>
                <?php if (!empty($pageBreadcrumb)): ?>
                <div class="page-breadcrumb">
                    <i class="fas fa-home"></i> Home
                    <i class="fas fa-chevron-right"></i>
                    <?= htmlspecialchars($pageBreadcrumb) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="header-right">
            <div class="header-search-bar" style="position:relative;">
                <i class="fas fa-search"></i>
                <input type="text" id="globalSearch" placeholder="Search products, tools..." autocomplete="off">
                <div id="searchDropdown" style="display:none;position:absolute;top:calc(100% + 6px);left:0;right:0;background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-md);box-shadow:var(--shadow-lg);z-index:300;overflow:hidden;min-width:320px;"></div>
            </div>
            <a href="<?= APP_URL ?>/pages/stock.php" class="icon-btn" data-tooltip="Low Stock Alerts">
                <i class="fas fa-bell"></i>
                <?php if ($lowStockCount > 0): ?><span class="notif-dot"></span><?php endif; ?>
            </a>
            <a href="<?= APP_URL ?>/pages/rfq_manager.php" class="icon-btn" data-tooltip="RFQ Inbox">
                <i class="fas fa-file-alt"></i>
            </a>
            <a href="<?= APP_URL ?>/portal/catalogue.php" target="_blank" class="icon-btn" data-tooltip="B2B Portal" style="background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;border-color:transparent;">
                <i class="fas fa-store"></i>
            </a>
        </div>
    </header>

    <!-- Flash Messages -->
    <div style="padding: 0 1.5rem; padding-top: 1rem;">
    <?php if ($flashSuccess): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>
    <?php if ($flashWarning): ?>
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($flashWarning) ?></div>
    <?php endif; ?>
    <?php if ($flashInfo): ?>
        <div class="alert alert-info"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($flashInfo) ?></div>
    <?php endif; ?>
    </div>
