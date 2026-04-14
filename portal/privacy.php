<?php
require_once __DIR__ . '/config/auth.php';
$pageTitle = 'Privacy Policy';
include __DIR__ . '/includes/header.php';
?>
<div class="fw-section">
    <div class="container" style="max-width: 800px; background: #fff; padding: 3rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); margin-top: 2rem; margin-bottom: 2rem;">
        <h1 style="font-size: 2rem; font-weight: 800; color: var(--text-dark); margin-bottom: 1.5rem;">Privacy Policy</h1>
        <p style="color: var(--text-light); margin-bottom: 2rem;">Last updated: <?= date('F d, Y') ?></p>
        
        <div style="color: var(--text-medium); line-height: 1.8;">
            <h3 style="color: var(--text-dark); margin-top: 1.5rem; margin-bottom: 0.5rem;">1. Information We Collect</h3>
            <p>We collect information necessary to provide our B2B procurement services, including but not limited to your company name, contact person, email address, phone number, GSTIN, and billing/shipping addresses.</p>

            <h3 style="color: var(--text-dark); margin-top: 1.5rem; margin-bottom: 0.5rem;">2. Use of Information</h3>
            <p>The information collected is used strictly for processing your Quotations (RFQs), managing your orders, providing customer support, and complying with Indian taxation and GST regulations.</p>

            <h3 style="color: var(--text-dark); margin-top: 1.5rem; margin-bottom: 0.5rem;">3. Data Protection and Payment Gateways</h3>
            <p>We implement robust security measures to protect your personal and company data. Online payments are processed securely through our authorized payment gateway partners (such as Razorpay). We do not store your credit card or sensitive banking details on our servers.</p>

            <h3 style="color: var(--text-dark); margin-top: 1.5rem; margin-bottom: 0.5rem;">4. Information Sharing</h3>
            <p>We do not sell or share your data with third parties for marketing purposes. Data may only be shared with reliable logistics partners for shipping purposes or when legally mandated by government authorities.</p>

            <h3 style="color: var(--text-dark); margin-top: 1.5rem; margin-bottom: 0.5rem;">5. Contact Us</h3>
            <p>If you have any questions about this Privacy Policy, please contact us at <strong><?= htmlspecialchars(getSetting('contact_email', 'sales@torvo.com')) ?></strong>.</p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
