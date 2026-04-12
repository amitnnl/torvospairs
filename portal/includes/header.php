<?php
require_once __DIR__ . '/../config/auth.php';

$customer = customerLoggedIn() ? currentCustomer() : null;
$rfqCount = rfqCount();
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>TORVO SPAIR B2B Portal</title>
    <meta name="description" content="TORVO SPAIR B2B Portal — Browse Power Tool Spare Parts, Request Quotations, and Manage Your Orders.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= PORTAL_URL ?>/assets/css/portal.css">
    <style>
        :root {
            --primary: <?= getSetting('primary_color', '#1e3a8a') ?>;
            --primary-light: <?= getSetting('primary_color_light', '#2563eb') ?>;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="portal-nav">
    <div class="nav-inner">
        <a href="<?= PORTAL_URL ?>/home.php" class="nav-brand">
            <?php $dynLogo = getSetting('logo_image'); if ($dynLogo): ?>
                <img src="<?= UPLOAD_URL . $dynLogo ?>" alt="Logo" style="height:36px;border-radius:6px;background:#fff;padding:3px;">
            <?php else: ?>
                <div class="nav-brand-icon"><i class="fas fa-wrench"></i></div>
            <?php endif; ?>
            <div class="nav-brand-text">
                <div class="brand-name"><?= getSetting('site_title', 'TORVO SPAIR') ?></div>
                <div class="brand-sub"><?= getSetting('b2b_subtitle', 'B2B Portal') ?></div>
            </div>
        </a>

        <button class="mobile-menu-btn" id="mobileMenuToggle">
            <i class="fas fa-bars"></i>
        </button>

        <div class="nav-links" id="mobileNav">
            <a href="<?= PORTAL_URL ?>/home.php" class="nav-link <?= $activePage==='home'?'active':'' ?>">
                <i class="fas fa-home" style="font-size:0.75rem;"></i> Home
            </a>
            <a href="<?= PORTAL_URL ?>/catalogue.php" class="nav-link <?= $activePage==='catalogue'?'active':'' ?>">
                <i class="fas fa-th-large" style="font-size:0.75rem;"></i> Catalogue
            </a>
            <a href="<?= PORTAL_URL ?>/contact.php" class="nav-link <?= $activePage==='contact'?'active':'' ?>">
                <i class="fas fa-envelope" style="font-size:0.75rem;"></i> Contact
            </a>
            <?php if ($customer): ?>
            <a href="<?= PORTAL_URL ?>/dashboard.php" class="nav-link <?= $activePage==='dashboard'?'active':'' ?>">
                <i class="fas fa-tachometer-alt" style="font-size:0.75rem;"></i> My Dashboard
            </a>
            <a href="<?= PORTAL_URL ?>/rfqs.php" class="nav-link <?= $activePage==='rfqs'?'active':'' ?>">
                <i class="fas fa-file-alt" style="font-size:0.75rem;"></i> My RFQs
            </a>
            <a href="<?= PORTAL_URL ?>/orders.php" class="nav-link <?= $activePage==='orders'?'active':'' ?>">
                <i class="fas fa-truck" style="font-size:0.75rem;"></i> My Orders
            </a>
            <?php endif; ?>
        </div>

        <div class="nav-right">
            <!-- Live Search -->
            <div style="position:relative;display:none;" class="portal-search-wrap" id="portalSearchWrap">
                <div style="display:flex;align-items:center;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);border-radius:8px;padding:0.4rem 0.75rem;gap:0.5rem;">
                    <i class="fas fa-search" style="color:rgba(255,255,255,0.5);font-size:0.75rem;"></i>
                    <input type="text" id="portalSearch" placeholder="Search parts, tools..." autocomplete="off"
                           style="background:none;border:none;outline:none;color:#fff;font-size:0.82rem;width:180px;font-family:inherit;"
                           onfocus="this.parentElement.style.borderColor='rgba(255,255,255,0.4)'"
                           onblur="this.parentElement.style.borderColor='rgba(255,255,255,0.15)'">
                </div>
                <div id="portalSearchDrop" style="display:none;position:absolute;top:calc(100%+6px);right:0;min-width:320px;background:#fff;border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,0.15);z-index:200;overflow:hidden;border:1px solid rgba(0,0,0,0.08);"></div>
            </div>

            <!-- RFQ Cart -->
            <a href="<?= PORTAL_URL ?>/rfq_cart.php" class="nav-rfq-btn">
                <i class="fas fa-shopping-cart"></i>
                RFQ Cart
                <?php if ($rfqCount > 0): ?>
                <span class="rfq-count"><?= $rfqCount ?></span>
                <?php endif; ?>
            </a>

            <?php if ($customer): ?>
            <div class="nav-user">
                <div class="nav-user-avatar"><?= strtoupper(substr($customer['name'], 0, 1)) ?></div>
                <div style="display:flex;flex-direction:column;line-height:1.2;">
                    <span style="font-size:0.8rem;font-weight:600;color:#fff;"><?= htmlspecialchars(explode(' ', $customer['name'])[0]) ?></span>
                    <span style="font-size:0.65rem;color:rgba(255,255,255,0.5);"><?= htmlspecialchars($customer['company']) ?></span>
                </div>
                <div style="display:flex;gap:0.25rem;">
                    <a href="<?= PORTAL_URL ?>/profile.php" class="nav-auth-btn nav-login" style="font-size:0.78rem;padding:0.4rem 0.75rem;" title="My Profile">
                        <i class="fas fa-user-cog"></i>
                    </a>
                    <a href="<?= PORTAL_URL ?>/logout.php" class="nav-auth-btn nav-login" style="font-size:0.78rem;padding:0.4rem 0.75rem;" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
            <?php else: ?>
            <a href="<?= PORTAL_URL ?>/index.php" class="nav-auth-btn nav-login">
                <i class="fas fa-sign-in-alt"></i> Login
            </a>
            <a href="<?= PORTAL_URL ?>/register.php" class="nav-auth-btn nav-register">
                <i class="fas fa-building"></i> Register
            </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Flash Messages -->
<?php
$flashTypes = ['success', 'error', 'warning', 'info'];
$flashIcons = ['success'=>'check-circle','error'=>'times-circle','warning'=>'exclamation-triangle','info'=>'info-circle'];
foreach ($flashTypes as $ft):
    $flash = getPortalFlash($ft);
    if ($flash):
?>
<div style="max-width:1300px;margin:0.75rem auto;padding:0 1.5rem;">
    <div class="alert alert-<?= $ft ?>">
        <i class="fas fa-<?= $flashIcons[$ft] ?>"></i>
        <?= $flash ?>
    </div>
</div>
<?php endif; endforeach; ?>
