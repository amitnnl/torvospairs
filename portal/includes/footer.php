</div><!-- end main content -->

<footer class="portal-footer">
    <div class="footer-inner">
        <div class="footer-grid">
            <div class="footer-about">
                <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.5rem;">
                    <?php $dynLogo = getSetting('logo_image'); if ($dynLogo): ?>
                        <img src="<?= UPLOAD_URL . $dynLogo ?>" alt="Logo" style="height:34px;border-radius:6px;background:#fff;padding:2px;">
                    <?php else: ?>
                        <div style="width:34px;height:34px;background:linear-gradient(135deg,#2563eb,#f97316);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1rem;"><i class="fas fa-wrench" style="color:#fff;"></i></div>
                    <?php endif; ?>
                    <span style="font-size:1rem;font-weight:800;color:#fff;letter-spacing:1px;"><?= getSetting('site_title', 'TORVO SPAIR') ?></span>
                </div>
                <p><?= getSetting('hero_subtitle', 'Your trusted B2B partner for Power Tool Spare Parts & Accessories.') ?></p>
                <div style="margin-top:1rem;display:flex;gap:0.5rem;">
                    <a href="https://api.whatsapp.com/send?phone=<?= getSetting('whatsapp_number', '919800000000') ?>" target="_blank" style="width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.9rem;"><i class="fab fa-whatsapp"></i></a>
                    <a href="mailto:<?= getSetting('contact_email', 'sales@torvo.com') ?>" style="width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.9rem;"><i class="fas fa-envelope"></i></a>
                    <a href="tel:<?= getSetting('contact_phone', '+91 98000 00000') ?>" style="width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.9rem;"><i class="fas fa-phone"></i></a>
                </div>
            </div>
            <div class="footer-col">
                <h4>Quick Links</h4>
                <a href="<?= PORTAL_URL ?>/home.php">Home</a>
                <a href="<?= PORTAL_URL ?>/catalogue.php">Browse Catalogue</a>
                <a href="<?= PORTAL_URL ?>/rfq_cart.php">Request Quotation</a>
                <a href="<?= PORTAL_URL ?>/register.php">Partner Registration</a>
                <a href="<?= PORTAL_URL ?>/dashboard.php">My Dashboard</a>
                <a href="<?= PORTAL_URL ?>/orders.php">My Orders</a>
            </div>
            <div class="footer-col">
                <h4>Contact Us</h4>
                <a href="tel:<?= getSetting('contact_phone', '+91 98000 00000') ?>"><i class="fas fa-phone" style="width:14px;"></i> <?= getSetting('contact_phone', '+91 98000 00000') ?></a>
                <a href="mailto:<?= getSetting('contact_email', 'sales@torvo.com') ?>"><i class="fas fa-envelope" style="width:14px;"></i> <?= getSetting('contact_email', 'sales@torvo.com') ?></a>
                <a href="#"><i class="fas fa-map-marker-alt" style="width:14px;"></i> <?= getSetting('contact_address', 'Mumbai, Maharashtra') ?></a>
                <a href="https://api.whatsapp.com/send?phone=<?= getSetting('whatsapp_number', '919800000000') ?>" target="_blank" style="color:#25d366;margin-top:0.25rem;"><i class="fab fa-whatsapp" style="width:14px;"></i> WhatsApp Us</a>
                <a href="<?= PORTAL_URL ?>/contact.php"><i class="fas fa-paper-plane" style="width:14px;"></i> Send Enquiry</a>
            </div>
        </div>
        <div class="footer-bottom">
            <span><a href="../admin.php" style="color:rgba(255,255,255,0.5);text-decoration:none;" target="_blank"><i class="fas fa-lock" style="font-size:0.75rem;"></i> Admin Panel</a> &nbsp;&middot;&nbsp; © <?= date('Y') ?> <?= getSetting('site_title', 'TORVO SPAIR') ?>. All rights reserved.</span>
            <span>
                <a href="<?= PORTAL_URL ?>/privacy.php" style="color:rgba(255,255,255,0.5);text-decoration:none;">Privacy Policy</a> &nbsp;&middot;&nbsp; 
                <a href="<?= PORTAL_URL ?>/terms.php" style="color:rgba(255,255,255,0.5);text-decoration:none;">Terms of Service</a> &nbsp;&middot;&nbsp; 
                <a href="<?= PORTAL_URL ?>/contact.php" style="color:rgba(255,255,255,0.5);text-decoration:none;">Contact</a>
            </span>
        </div>
    </div>
</footer>


<script>
// Mobile Menu Toggle
const mobileBtn = document.getElementById('mobileMenuToggle');
const mobileNav = document.getElementById('mobileNav');
if (mobileBtn && mobileNav) {
    mobileBtn.addEventListener('click', () => {
        mobileNav.classList.toggle('show-mobile');
    });
}

// Add to RFQ feedback animation
document.querySelectorAll('.btn-add-rfq').forEach(btn => {
    btn.addEventListener('click', function() {
        if (!this.classList.contains('added')) {
            const orig = this.innerHTML;
            this.classList.add('added');
            this.innerHTML = '<i class="fas fa-check"></i> Added!';
            setTimeout(() => { this.classList.remove('added'); this.innerHTML = orig; }, 1800);
        }
    });
});

// Show search bar (hidden initially to prevent FOUC)
const sw = document.getElementById('portalSearchWrap');
if (sw) sw.style.display = 'block';


// Portal Live Search (navbar)
const portalSearchInput = document.getElementById('portalSearch');
const portalSearchDrop  = document.getElementById('portalSearchDrop');
if (portalSearchInput) {
    let timer;
    portalSearchInput.addEventListener('input', function() {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 2) { portalSearchDrop.style.display='none'; return; }
        timer = setTimeout(() => {
            fetch('<?= APP_URL ?>/api/search.php?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if (!data.data || !data.data.results.length) {
                        portalSearchDrop.innerHTML = '<div style="padding:1rem;text-align:center;color:#888;font-size:0.82rem;">No results found</div>';
                    } else {
                        portalSearchDrop.innerHTML = data.data.results.slice(0,6).map(r => `
                            <a href="<?= PORTAL_URL ?>/catalogue.php?search=${encodeURIComponent(r.name)}" style="display:flex;align-items:center;gap:0.75rem;padding:0.65rem 1rem;border-bottom:1px solid rgba(0,0,0,0.06);text-decoration:none;transition:background 0.15s;" onmouseover="this.style.background='rgba(37,99,235,0.05)'" onmouseout="this.style.background=''">
                                <div style="width:30px;height:30px;border-radius:7px;background:${r.type==='product'?'rgba(37,99,235,0.1)':'rgba(249,115,22,0.1)'};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-${r.type==='product'?'cog':'tools'}" style="font-size:0.75rem;color:${r.type==='product'?'#2563eb':'#f97316'};"></i>
                                </div>
                                <div style="min-width:0;">
                                    <div style="font-weight:600;font-size:0.82rem;color:#111;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${r.name}</div>
                                    <div style="font-size:0.7rem;color:#888;">${r.type === 'product' ? (r.category + (r.price > 0 ? ' · ₹'+parseFloat(r.price).toFixed(2) : '')) : 'Power Tool'}</div>
                                </div>
                            </a>
                        `).join('') + `<a href="<?= PORTAL_URL ?>/catalogue.php?search=${encodeURIComponent(q)}" style="display:block;padding:0.65rem 1rem;text-align:center;font-size:0.78rem;color:#2563eb;font-weight:600;">View all results for "${q}" →</a>`;
                    }
                    portalSearchDrop.style.display = 'block';
                }).catch(() => {});
        }, 320);
    });
    document.addEventListener('click', e => { if (!portalSearchInput.contains(e.target) && !portalSearchDrop.contains(e.target)) portalSearchDrop.style.display='none'; });
}
</script>
</body>
</html>
