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
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?><?= getSetting('site_title', 'TORVO SPAIR') ?></title>
    <meta name="description" content="<?= getSetting('site_title', 'TORVO SPAIR') ?> B2B Portal — Browse Power Tool Spare Parts, Request Quotations, and Manage Your Orders.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= PORTAL_URL ?>/assets/css/portal.css?v=1.1">
    <style>
        :root {
            --primary: <?= getSetting('primary_color', '#1e3a8a') ?>;
            --primary-light: <?= getSetting('primary_color_light', '#2563eb') ?>;
        }
    </style>
    
    <!-- WhatsApp Floating Button CSS -->
    <style>
        .wa-float {
            position: fixed; bottom: 30px; right: 30px;
            width: 60px; height: 60px; border-radius: 50%;
            background-color: #25d366; color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 30px; box-shadow: 2px 2px 15px rgba(0,0,0,0.2);
            z-index: 9999; transition: all 0.3s ease;
            text-decoration: none;
        }
        .wa-float:hover {
            transform: scale(1.1); background-color: #128c7e; color: #fff;
        }
        .wa-float .wa-tooltip {
            position: absolute; right: 80px; background: #333; color: #fff;
            padding: 8px 15px; border-radius: 8px; font-size: 14px;
            white-space: nowrap; opacity: 0; visibility: hidden;
            transition: all 0.3s ease; font-weight: 500;
        }
        .wa-float:hover .wa-tooltip {
            opacity: 1; visibility: visible;
        }
        @keyframes pulse-wa {
            0% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7); }
            70% { box-shadow: 0 0 0 15px rgba(37, 211, 102, 0); }
            100% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); }
        }
        .wa-float { animation: pulse-wa 2s infinite; }
    </style>
</head>
<body>

<!-- WhatsApp Floating Button -->
<?php $waNumber = getSetting('whatsapp_number', preg_replace('/[^0-9]/', '', getSetting('contact_phone', '919876543210'))); ?>
<a href="https://wa.me/<?= $waNumber ?>?text=Hello%20<?= urlencode(getSetting('site_title', 'TORVO SPAIR')) ?>%20Support,%20I%20need%20assistance%20with%20an%20order." class="wa-float" target="_blank" rel="noopener">
    <i class="fab fa-whatsapp"></i>
    <span class="wa-tooltip">Direct Support</span>
</a>

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
            <!-- Mobile Search Bar -->
            <div class="mobile-search-only" style="padding:0 0.5rem 0.75rem;display:none;">
                <div style="display:flex;align-items:center;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);border-radius:10px;padding:0.6rem 0.85rem;gap:0.75rem;">
                    <i class="fas fa-search" style="color:rgba(255,255,255,0.6);"></i>
                    <input type="text" placeholder="Search products..."
                           oninput="const s=document.getElementById('portalSearch');if(s){s.value=this.value;s.dispatchEvent(new Event('input'));}"
                           style="background:none;border:none;outline:none;color:#fff;width:100%;font-size:0.9rem;">
                </div>
            </div>

            <!-- Main Nav Links -->
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

            <!-- Mobile-only Auth links — visible inside hamburger menu only -->
            <div class="mobile-only-link" style="height:1px;background:rgba(255,255,255,0.08);margin:0.4rem 0;display:none;"></div>
            <?php if ($customer): ?>
            <div class="mobile-only-link" style="display:none;padding:0.5rem 1.1rem;display:none;align-items:center;gap:0.75rem;">
                <div class="nav-user-avatar" style="flex-shrink:0;"><?= strtoupper(substr($customer['name'],0,1)) ?></div>
                <div>
                    <div style="font-size:0.88rem;font-weight:700;color:#fff;"><?= htmlspecialchars($customer['name']) ?></div>
                    <div style="font-size:0.7rem;color:rgba(255,255,255,0.45);"><?= htmlspecialchars($customer['company'] ?? '') ?></div>
                </div>
            </div>
            <a href="<?= PORTAL_URL ?>/profile.php" class="nav-link mobile-only-link" style="display:none;">
                <i class="fas fa-user-cog" style="font-size:0.75rem;"></i> My Profile
            </a>
            <a href="<?= PORTAL_URL ?>/logout.php" class="nav-link mobile-only-link" style="display:none;color:rgba(255,120,120,0.9);">
                <i class="fas fa-sign-out-alt" style="font-size:0.75rem;"></i> Logout
            </a>
            <?php else: ?>
            <a href="<?= PORTAL_URL ?>/index.php" class="nav-link mobile-only-link" style="display:none;">
                <i class="fas fa-sign-in-alt" style="font-size:0.75rem;"></i> Login
            </a>
            <a href="<?= PORTAL_URL ?>/register.php" class="nav-link mobile-only-link" style="display:none;background:rgba(249,115,22,0.12);border:1px solid rgba(249,115,22,0.2);">
                <i class="fas fa-building" style="font-size:0.75rem;"></i> Register as Dealer
            </a>
            <?php endif; ?>
        </div>

        <div class="nav-right">
            <!-- Live Search (desktop only) -->

            <!-- Live Search (desktop only) -->
            <div style="position:relative;" class="portal-search-wrap desktop-only" id="portalSearchWrap">
                <div style="display:flex;align-items:center;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);border-radius:8px;padding:0.4rem 0.75rem;gap:0.5rem;">
                    <i class="fas fa-search" style="color:rgba(255,255,255,0.5);font-size:0.75rem;"></i>
                    <input type="text" id="portalSearch" placeholder="Search parts..." autocomplete="off"
                           style="background:none;border:none;outline:none;color:#fff;font-size:0.82rem;width:180px;font-family:inherit;"
                           onfocus="this.parentElement.style.borderColor='rgba(255,255,255,0.4)'"
                           onblur="this.parentElement.style.borderColor='rgba(255,255,255,0.15)'">
                </div>
                <div id="portalSearchDrop" style="display:none;position:absolute;top:calc(100%+6px);right:0;min-width:320px;background:#fff;border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,0.15);z-index:200;overflow:hidden;border:1px solid rgba(0,0,0,0.08);"></div>
            </div>

            <!-- RFQ Cart — always visible on ALL screen sizes -->
            <a href="<?= PORTAL_URL ?>/rfq_cart.php" class="nav-rfq-btn">
                <i class="fas fa-shopping-cart"></i>
                <span class="rfq-cart-label">RFQ Cart</span>
                <?php if ($rfqCount > 0): ?>
                <span class="rfq-count"><?= $rfqCount ?></span>
                <?php endif; ?>
            </a>

            <!-- Desktop-only auth/user controls -->
            <?php if ($customer): ?>
            <div class="nav-user desktop-only">
                <div class="nav-user-avatar"><?= strtoupper(substr($customer['name'], 0, 1)) ?></div>
                <div style="display:flex;flex-direction:column;line-height:1.2;">
                    <span style="font-size:0.8rem;font-weight:600;color:#fff;"><?= htmlspecialchars(explode(' ', $customer['name'])[0]) ?></span>
                    <span style="font-size:0.65rem;color:rgba(255,255,255,0.5);"><?= htmlspecialchars($customer['company'] ?? '') ?></span>
                </div>
                <a href="<?= PORTAL_URL ?>/profile.php" class="nav-auth-btn nav-login" style="font-size:0.78rem;padding:0.4rem 0.75rem;" title="My Profile">
                    <i class="fas fa-user-cog"></i>
                </a>
                <a href="<?= PORTAL_URL ?>/logout.php" class="nav-auth-btn nav-login" style="font-size:0.78rem;padding:0.4rem 0.75rem;" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
            <?php else: ?>
            <a href="<?= PORTAL_URL ?>/index.php" class="nav-auth-btn nav-login desktop-only">
                <i class="fas fa-sign-in-alt"></i> Login
            </a>
            <a href="<?= PORTAL_URL ?>/register.php" class="nav-auth-btn nav-register desktop-only">
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
