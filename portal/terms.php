<?php
require_once __DIR__ . '/config/auth.php';
$pageTitle = 'Terms of Service';
include __DIR__ . '/includes/header.php';
?>
<div class="fw-section">
    <div class="container" style="max-width: 800px; background: #fff; padding: 3rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); margin-top: 2rem; margin-bottom: 2rem;">
        <h1 style="font-size: 2rem; font-weight: 800; color: var(--text-dark); margin-bottom: 1.5rem;">Terms of Service</h1>
        <p style="color: var(--text-light); margin-bottom: 2rem;">Last updated: <?= date('F d, Y') ?></p>
        
        <div style="color: var(--text-medium); line-height: 1.8;">
            <h3 style="color: var(--text-dark); margin-top: 1.5rem; margin-bottom: 0.5rem;">1. Acceptance of Terms</h3>
            <p>By registering as a partner on the <?= htmlspecialchars(getSetting('site_title', 'TORVO SPAIR')) ?> B2B Portal, you agree to comply with and be bound by these Terms of Service. If you do not agree to these terms, please do not use our platform.</p>

            <h3 style="color: var(--text-dark); margin-top: 1.5rem; margin-bottom: 0.5rem;">2. B2B Operations</h3>
            <p>This platform is exclusively for dealers, distributors, and business entities. All products are sold for B2B purposes. Access to pricing requires an approved partner account.</p>

            <h3 style="color: var(--text-dark); margin-top: 1.5rem; margin-bottom: 0.5rem;">3. Pricing, RFQ, and Invoices</h3>
            <p>Prices provided via Quotations (RFQs) are subject to stock availability and may change without prior notice. All finalized transactions represent a binding agreement. GST will be applied as per Indian law.</p>

            <h3 style="color: var(--text-dark); margin-top: 1.5rem; margin-bottom: 0.5rem;">4. Payments and Refunds</h3>
            <p>Payments made via our online gateway partners must be completed to initiate dispatch. Cancellation or refund requests are subject to our return policies and must be initiated within 24 hours of placing the order, subject to management approval.</p>

            <h3 style="color: var(--text-dark); margin-top: 1.5rem; margin-bottom: 0.5rem;">5. Shipping and Delivery</h3>
            <p>Expected delivery timelines are estimates. We are not liable for transit delays caused by third-party logistics providers or unforeseen circumstances.</p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
