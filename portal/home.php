<?php
require_once __DIR__ . '/config/auth.php';


$db = portalDB();

// Quick stats for hero
$productCount = $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$toolCount    = $db->query("SELECT COUNT(*) FROM tools WHERE status='active'")->fetchColumn();
$catCount     = $db->query("SELECT COUNT(*) FROM categories WHERE status='active'")->fetchColumn();

// Featured categories (with product count)
$featCats = $db->query("SELECT c.*, COUNT(p.id) AS prod_count FROM categories c LEFT JOIN products p ON p.category_id=c.id AND p.status='active' WHERE c.status='active' GROUP BY c.id ORDER BY prod_count DESC LIMIT 6")->fetchAll();

// Newest products
$newProducts = $db->query("SELECT p.*, c.name AS cat FROM products p JOIN categories c ON p.category_id=c.id WHERE p.status='active' ORDER BY p.created_at DESC LIMIT 8")->fetchAll();

$pageTitle  = 'B2B Spare Parts Portal';
$activePage = 'home';
include __DIR__ . '/includes/header.php';
?>

<!-- ═══════════════════════════════════════════════════════════════
     HERO SLIDER — Cinematic Full-Screen with Ken-Burns, Progress Bar,
     Animated Text Reveal, Prev/Next Controls and Dot Indicators
════════════════════════════════════════════════════════════════ -->
<?php
    $sliderDefaults = [
        [
            'img'      => 'https://images.unsplash.com/photo-1504148455328-c376907d081c?auto=format&fit=crop&w=1920&q=80',
            'tag'      => 'Genuine OEM Parts',
            'headline' => getSetting('hero_title', 'Genuine Spare Parts for Every Power Tool'),
            'sub'      => getSetting('hero_subtitle', $productCount . '+ genuine parts in stock. Filter by tool compatibility and get instant quotes.'),
            'cta1'     => ['Browse Catalogue', 'catalogue.php', 'fas fa-th-large'],
            'cta2'     => ['Request Quote',    'rfq_cart.php',  'fas fa-file-invoice'],
            'accent'   => '#f97316',
        ],
        [
            'img'      => 'https://images.unsplash.com/photo-1544377192-339241c27c65?auto=format&fit=crop&w=1920&q=80',
            'tag'      => 'B2B Partner Program',
            'headline' => 'Exclusive Pricing for Dealers & Distributors',
            'sub'      => 'Tiered discounts for Silver & Gold partners. Pan-India delivery within 3–7 business days.',
            'cta1'     => ['Become a Partner', 'register.php',  'fas fa-handshake'],
            'cta2'     => ['Contact Sales',     'contact.php',   'fas fa-headset'],
            'accent'   => '#6c63ff',
        ],
        [
            'img'      => 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&w=1920&q=80',
            'tag'      => 'GST-Compliant Invoicing',
            'headline' => 'Fast Quotes. Real-Time Stock. Instant Delivery.',
            'sub'      => 'Submit an RFQ in minutes and get a confirmed price within 24 hours with GST-ready invoices.',
            'cta1'     => ['Start RFQ Now',    'rfq_cart.php',  'fas fa-shopping-cart'],
            'cta2'     => ['Track My Order',   'orders.php',    'fas fa-truck'],
            'accent'   => '#22c55e',
        ],
    ];

    // Override images with admin-uploaded ones
    for ($i = 1; $i <= 3; $i++) {
        $s = getSetting("hero_slide_$i");
        if (!empty($s) && file_exists(UPLOAD_DIR . $s)) {
            $sliderDefaults[$i-1]['img'] = UPLOAD_URL . $s;
        }
    }
    $totalSlides = count($sliderDefaults);
?>

<style>
/* ── Hero Wrapper ── */
#heroWrap {
    position: relative;
    width: 100%;
    height: 100vh;
    min-height: 600px;
    max-height: 900px;
    overflow: hidden;
    background: #0f172a;
}

/* ── Each Slide ── */
.hs-slide {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    opacity: 0;
    z-index: 0;
    transition: opacity 1.1s cubic-bezier(0.4, 0, 0.2, 1);
    pointer-events: none;
}
.hs-slide.hs-active {
    opacity: 1;
    z-index: 1;
    pointer-events: auto;
}

/* ── Background Image (Ken-Burns) ── */
.hs-bg {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    transform: scale(1.12);
    transition: transform 7s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    will-change: transform;
}
.hs-slide.hs-active .hs-bg {
    transform: scale(1);
}

/* ── Gradient Overlays ── */
.hs-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(110deg,
        rgba(10, 15, 40, 0.93) 0%,
        rgba(10, 15, 40, 0.75) 50%,
        rgba(10, 15, 40, 0.35) 100%);
}
.hs-overlay-right {
    position: absolute; inset: 0;
    background: linear-gradient(270deg,
        var(--hs-accent, #f97316) 0%,
        transparent 55%);
    opacity: 0.08;
}

/* ── Noise / Dot Pattern ── */
.hs-dots-pattern {
    position: absolute; inset: 0; z-index: 1; pointer-events: none;
    background-image: radial-gradient(circle, rgba(255,255,255,0.07) 1px, transparent 1px);
    background-size: 38px 38px;
}

/* ── Glow Blobs ── */
.hs-glow-tl {
    position: absolute; top: -100px; left: -100px;
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(99,102,241,0.18), transparent 65%);
    pointer-events: none;
}
.hs-glow-br {
    position: absolute; bottom: -100px; right: -80px;
    width: 500px; height: 500px;
    background: radial-gradient(circle, var(--hs-accent, rgba(249,115,22,0.18)), transparent 65%);
    opacity: 0.25;
    pointer-events: none;
}

/* ── Content ── */
.hs-content {
    position: relative; z-index: 2;
    max-width: 1200px; width: 100%; margin: 0 auto;
    padding: 0 2rem;
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 3rem;
    align-items: center;
}
.hs-left {}
.hs-tag {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.18);
    border-left: 3px solid var(--hs-accent, #f97316);
    padding: 0.45rem 1.1rem;
    border-radius: 6px;
    color: #fff;
    font-size: 0.72rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;
    margin-bottom: 1.75rem;
    opacity: 0; transform: translateY(20px);
    transition: opacity 0.7s ease 0.1s, transform 0.7s ease 0.1s;
}
.hs-slide.hs-active .hs-tag { opacity: 1; transform: translateY(0); }

.hs-headline {
    font-size: clamp(2rem, 4.5vw, 3.4rem);
    font-weight: 900; color: #fff; line-height: 1.12;
    margin-bottom: 1.25rem;
    opacity: 0; transform: translateY(28px);
    transition: opacity 0.8s ease 0.25s, transform 0.8s ease 0.25s;
}
.hs-headline em { font-style: normal; color: var(--hs-accent, #f97316); }
.hs-slide.hs-active .hs-headline { opacity: 1; transform: translateY(0); }

.hs-sub {
    font-size: 1.05rem; color: rgba(255,255,255,0.6);
    line-height: 1.75; max-width: 520px;
    margin-bottom: 2.25rem;
    opacity: 0; transform: translateY(24px);
    transition: opacity 0.8s ease 0.4s, transform 0.8s ease 0.4s;
}
.hs-slide.hs-active .hs-sub { opacity: 1; transform: translateY(0); }

.hs-actions {
    display: flex; gap: 0.85rem; flex-wrap: wrap;
    opacity: 0; transform: translateY(20px);
    transition: opacity 0.7s ease 0.55s, transform 0.7s ease 0.55s;
}
.hs-slide.hs-active .hs-actions { opacity: 1; transform: translateY(0); }

.hs-btn-primary {
    display: inline-flex; align-items: center; gap: 0.55rem;
    padding: 0.9rem 2rem; border-radius: 10px;
    background: var(--hs-accent, #f97316); color: #fff;
    font-weight: 800; font-size: 0.9rem;
    text-decoration: none;
    box-shadow: 0 8px 28px -4px var(--hs-accent, rgba(249,115,22,0.5));
    transition: transform 0.25s, box-shadow 0.25s;
}
.hs-btn-primary:hover { transform: translateY(-3px); box-shadow: 0 14px 36px -4px var(--hs-accent, rgba(249,115,22,0.55)); }

.hs-btn-ghost {
    display: inline-flex; align-items: center; gap: 0.55rem;
    padding: 0.9rem 2rem; border-radius: 10px;
    background: rgba(255,255,255,0.07);
    border: 1.5px solid rgba(255,255,255,0.22);
    color: #fff;
    font-weight: 600; font-size: 0.9rem;
    text-decoration: none;
    backdrop-filter: blur(6px);
    transition: background 0.25s, transform 0.25s;
}
.hs-btn-ghost:hover { background: rgba(255,255,255,0.14); transform: translateY(-3px); }

/* ── Stats Panel (right side) ── */
.hs-stats {
    display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;
    opacity: 0; transform: translateX(30px);
    transition: opacity 0.9s ease 0.5s, transform 0.9s ease 0.5s;
}
.hs-slide.hs-active .hs-stats { opacity: 1; transform: translateX(0); }
.hs-stat-card {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    backdrop-filter: blur(12px);
    border-radius: 16px;
    padding: 1.4rem 1rem;
    text-align: center;
    transition: background 0.3s;
}
.hs-stat-card:hover { background: rgba(255,255,255,0.09); }
.hs-stat-icon { font-size: 1.5rem; margin-bottom: 0.6rem; }
.hs-stat-num { font-size: 1.9rem; font-weight: 900; color: #fff; line-height: 1; }
.hs-stat-label { font-size: 0.68rem; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.8px; margin-top: 0.3rem; }

/* ── Progress Bar ── */
#hsProgressBar {
    position: absolute; bottom: 0; left: 0;
    height: 3px;
    background: var(--hs-accent-bar, #f97316);
    width: 0%;
    z-index: 10;
    transition: width linear;
}

/* ── Navigation Arrows ── */
.hs-nav-btn {
    position: absolute; top: 50%; z-index: 10;
    transform: translateY(-50%);
    width: 50px; height: 50px; border-radius: 50%;
    background: rgba(255,255,255,0.08);
    border: 1.5px solid rgba(255,255,255,0.2);
    color: #fff; font-size: 1.1rem;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    backdrop-filter: blur(8px);
    transition: background 0.25s, transform 0.25s;
}
.hs-nav-btn:hover { background: rgba(255,255,255,0.2); transform: translateY(-50%) scale(1.1); }
#hsPrev { left: 1.5rem; }
#hsNext { right: 1.5rem; }

/* ── Dot Indicators ── */
#hsDots {
    position: absolute; bottom: 24px; left: 50%;
    transform: translateX(-50%);
    display: flex; gap: 0.6rem; z-index: 10;
}
.hs-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: rgba(255,255,255,0.35);
    cursor: pointer;
    transition: all 0.3s;
}
.hs-dot.hs-dot-active {
    width: 28px;
    border-radius: 4px;
    background: #fff;
}

/* ── Responsive ── */
@media (max-width: 900px) {
    #heroWrap { height: 85vh; max-height: 750px; }
    .hs-content { grid-template-columns: 1fr; gap: 2rem; }
    .hs-stats { display: none; }
    .hs-headline { font-size: clamp(1.7rem, 6vw, 2.6rem); }
}
@media (max-width: 480px) {
    #heroWrap { height: 92vh; }
    .hs-content { padding: 0 1.25rem; }
    .hs-btn-primary, .hs-btn-ghost { padding: 0.8rem 1.4rem; font-size: 0.82rem; }
    #hsPrev { left: 0.75rem; } #hsNext { right: 0.75rem; }
}
</style>

<div id="heroWrap">
    <?php foreach ($sliderDefaults as $si => $slide): ?>
    <div class="hs-slide <?= $si === 0 ? 'hs-active' : '' ?>"
         data-accent="<?= htmlspecialchars($slide['accent']) ?>"
         style="--hs-accent:<?= htmlspecialchars($slide['accent']) ?>;">
        
        <!-- Background -->
        <div class="hs-bg" style="background-image:url('<?= htmlspecialchars($slide['img']) ?>');"></div>
        
        <!-- Overlays -->
        <div class="hs-overlay"></div>
        <div class="hs-overlay-right"></div>
        <div class="hs-dots-pattern"></div>
        <div class="hs-glow-tl"></div>
        <div class="hs-glow-br" style="background:radial-gradient(circle,<?= htmlspecialchars($slide['accent']) ?>44,transparent 65%);"></div>
        
        <!-- Content -->
        <div class="hs-content">
            <div class="hs-left">
                <div class="hs-tag" style="border-left-color:<?= htmlspecialchars($slide['accent']) ?>;">
                    <i class="fas fa-bolt" style="color:<?= htmlspecialchars($slide['accent']) ?>;"></i>
                    <?= htmlspecialchars($slide['tag']) ?>
                </div>
                <h1 class="hs-headline">
                    <?= nl2br(htmlspecialchars($slide['headline'])) ?>
                </h1>
                <p class="hs-sub"><?= htmlspecialchars($slide['sub']) ?></p>
                <div class="hs-actions">
                    <a href="<?= $slide['cta1'][1] ?>" class="hs-btn-primary" style="background:<?= htmlspecialchars($slide['accent']) ?>;box-shadow:0 8px 28px -4px <?= htmlspecialchars($slide['accent']) ?>88;">
                        <i class="<?= $slide['cta1'][2] ?>"></i> <?= htmlspecialchars($slide['cta1'][0]) ?>
                    </a>
                    <a href="<?= $slide['cta2'][1] ?>" class="hs-btn-ghost">
                        <i class="<?= $slide['cta2'][2] ?>"></i> <?= htmlspecialchars($slide['cta2'][0]) ?>
                    </a>
                </div>
            </div>

            <!-- Stats Panel -->
            <div class="hs-stats">
                <?php foreach ([
                    [$productCount . '+', 'Spare Parts',  'fas fa-cog',         '#6c63ff'],
                    [$toolCount,          'Tool Types',   'fas fa-tools',       '#48daf5'],
                    [$catCount,           'Categories',   'fas fa-layer-group', '#22c55e'],
                    ['24hr',              'Quote Reply',  'fas fa-clock',       '#f97316'],
                ] as [$num, $lbl, $icon, $color]): ?>
                <div class="hs-stat-card">
                    <div class="hs-stat-icon"><i class="<?= $icon ?>" style="color:<?= $color ?>;"></i></div>
                    <div class="hs-stat-num"><?= $num ?></div>
                    <div class="hs-stat-label"><?= $lbl ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Progress Bar -->
    <div id="hsProgressBar"></div>

    <!-- Navigation Arrows -->
    <button class="hs-nav-btn" id="hsPrev" aria-label="Previous slide">
        <i class="fas fa-chevron-left"></i>
    </button>
    <button class="hs-nav-btn" id="hsNext" aria-label="Next slide">
        <i class="fas fa-chevron-right"></i>
    </button>

    <!-- Dot Indicators -->
    <div id="hsDots">
        <?php for ($d = 0; $d < $totalSlides; $d++): ?>
        <div class="hs-dot <?= $d === 0 ? 'hs-dot-active' : '' ?>" data-slide="<?= $d ?>" role="button" aria-label="Go to slide <?= $d+1 ?>"></div>
        <?php endfor; ?>
    </div>
</div>

<script>
(function() {
    const SLIDE_DURATION = 6000; // ms each slide stays
    const slides  = document.querySelectorAll('.hs-slide');
    const dots    = document.querySelectorAll('.hs-dot');
    const bar     = document.getElementById('hsProgressBar');
    const prevBtn = document.getElementById('hsPrev');
    const nextBtn = document.getElementById('hsNext');
    let current   = 0;
    let timer     = null;
    let barTimer  = null;

    function setAccentOnRoot(accent) {
        document.getElementById('hsProgressBar').style.background = accent;
    }

    function goTo(n, direction) {
        slides[current].classList.remove('hs-active');
        dots[current].classList.remove('hs-dot-active');
        current = (n + slides.length) % slides.length;
        slides[current].classList.add('hs-active');
        dots[current].classList.add('hs-dot-active');

        // Update progress bar accent colour
        const accent = slides[current].dataset.accent || '#f97316';
        bar.style.background = accent;

        startBar();
    }

    function startBar() {
        // Reset bar
        bar.style.transition = 'none';
        bar.style.width = '0%';
        clearTimeout(barTimer);

        // Animate
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                bar.style.transition = `width ${SLIDE_DURATION}ms linear`;
                bar.style.width = '100%';
            });
        });
    }

    function startAuto() {
        clearInterval(timer);
        timer = setInterval(() => goTo(current + 1), SLIDE_DURATION);
    }

    function resetAuto() {
        clearInterval(timer);
        startAuto();
    }

    // Initial state
    if (slides.length > 0) {
        const accent = slides[0].dataset.accent || '#f97316';
        bar.style.background = accent;
        startBar();
        startAuto();
    }

    prevBtn.addEventListener('click', () => { goTo(current - 1); resetAuto(); });
    nextBtn.addEventListener('click', () => { goTo(current + 1); resetAuto(); });
    dots.forEach(dot => {
        dot.addEventListener('click', () => { goTo(parseInt(dot.dataset.slide)); resetAuto(); });
    });

    // Pause on hover
    const wrap = document.getElementById('heroWrap');
    wrap.addEventListener('mouseenter', () => clearInterval(timer));
    wrap.addEventListener('mouseleave', () => { startAuto(); startBar(); });

    // Touch / swipe support
    let touchStartX = 0;
    wrap.addEventListener('touchstart', e => { touchStartX = e.changedTouches[0].screenX; }, { passive: true });
    wrap.addEventListener('touchend', e => {
        const dx = e.changedTouches[0].screenX - touchStartX;
        if (Math.abs(dx) > 50) { dx < 0 ? goTo(current + 1) : goTo(current - 1); resetAuto(); }
    }, { passive: true });

    // Keyboard navigation
    document.addEventListener('keydown', e => {
        if (e.key === 'ArrowLeft')  { goTo(current - 1); resetAuto(); }
        if (e.key === 'ArrowRight') { goTo(current + 1); resetAuto(); }
    });
})();
</script>

<!-- ── How It Works ────────────── -->
<div class="home-how-section">
    <div class="home-section-inner" style="text-align:center;">
        <div class="section-label">Simple Process</div>
        <h2 class="section-title" style="margin-bottom:3rem;">How It Works</h2>
        <div class="process-grid">
            <div class="process-line"></div>
            <?php foreach ([
                ['1','Register',    'fas fa-building',     'Create your B2B account as a dealer or distributor.', 'var(--primary)'],
                ['2','Browse',      'fas fa-search',       'Explore 500+ parts filtered by your tool type.', 'var(--primary-light)'],
                ['3','Request RFQ', 'fas fa-file-invoice', 'Add to cart and submit a Request for Quotation.', '#6366f1'],
                ['4','Get Shipped', 'fas fa-truck',        'Receive confirmed pricing and track your delivery.', 'var(--accent)'],
            ] as array $stepInfo): 
                list($step, $title, $icon, $desc, $color) = $stepInfo;
            ?>
            <div class="process-step animate__animated animate__fadeInUp" style="animation-delay: <?= $step * 0.1 ?>s">
                <div style="width:56px;height:56px;border-radius:50%;background:<?= $color ?>;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;box-shadow:0 8px 20px <?= $color ?>33;">
                    <i class="<?= $icon ?>" style="color:#fff;font-size:1.1rem;"></i>
                </div>
                <div style="font-size:0.65rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.25rem;">Step <?= $step ?></div>
                <div style="font-weight:800;color:var(--text-dark);margin-bottom:0.4rem;"><?= $title ?></div>
                <div style="font-size:0.8rem;color:var(--text-light);line-height:1.5;"><?= $desc ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── Featured Categories ──────── -->
<?php if (!empty($featCats)): ?>
<div class="home-cat-section">
    <div class="home-section-inner">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:0.75rem;">
            <div>
                <div class="section-label">Product Range</div>
                <h2 class="section-title">Shop by Category</h2>
            </div>
            <a href="catalogue.php" class="btn btn-outline btn-sm">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="cat-grid">
            <?php
            $catColors = ['#6c63ff','#f97316','#ef4444','#2563eb','#16a34a','#0891b2'];
            foreach ($featCats as $idx => $cat):
                $color = $catColors[$idx % count($catColors)];
            ?>
            <a href="catalogue.php?cat=<?= $cat['id'] ?>" class="cat-card animate__animated animate__fadeInUp" style="--cc:<?= $color ?>; animation-delay: <?= $idx * 0.1 ?>s">
                <div class="cat-icon"><i class="fas fa-cog" style="color:<?= $color ?>;font-size:1.2rem;"></i></div>
                <div class="cat-name"><?= htmlspecialchars($cat['name']) ?></div>
                <div class="cat-count"><?= $cat['prod_count'] ?> products</div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── New Arrivals ─────────────── -->
<?php if (!empty($newProducts)): ?>
<div class="home-new-section">
    <div class="home-section-inner">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:0.75rem;">
            <div>
                <div style="font-size:0.75rem;color:var(--accent);font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:0.4rem;">Latest Stock</div>
                <h2 style="font-size:1.5rem;font-weight:800;color:var(--text-dark);">New Arrivals</h2>
            </div>
            <a href="catalogue.php" class="btn btn-outline btn-sm">Full Catalogue <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="product-grid">
            <?php foreach (array_slice($newProducts, 0, 4) as $idx => $p):
                $stockClass = $p['quantity'] == 0 ? 'badge-out' : ($p['quantity'] <= ($p['min_stock']??5) ? 'badge-low' : 'badge-instock');
                $stockLabel = $p['quantity'] == 0 ? 'Out of Stock' : ($p['quantity'] <= ($p['min_stock']??5) ? 'Low Stock' : 'In Stock');
            ?>
            <div class="product-card animate__animated animate__fadeInUp" style="animation-delay: <?= $idx * 0.1 ?>s">
                <div class="product-img">
                    <?php if ($p['image'] && file_exists(UPLOAD_DIR.$p['image'])): ?>
                        <img src="<?= UPLOAD_URL.htmlspecialchars($p['image']) ?>" alt="">
                    <?php else: ?>
                        <i class="fas fa-cog no-img"></i>
                    <?php endif; ?>
                    <span class="product-badge <?= $stockClass ?>"><?= $stockLabel ?></span>
                    <span class="product-badge" style="left:auto;right:0.75rem;background:rgba(249,115,22,0.12);color:var(--accent);">NEW</span>
                </div>
                <div class="product-body">
                    <div class="product-cat"><?= htmlspecialchars($p['cat']) ?></div>
                    <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="product-sku">SKU: <?= htmlspecialchars($p['sku']) ?></div>
                    <div class="product-footer">
                        <div class="product-price">
                            <span class="product-price-hidden"><i class="fas fa-lock"></i> RFQ Pricing</span>
                        </div>
                        <a href="catalogue.php?search=<?= urlencode($p['sku']) ?>" class="btn-add-rfq" style="text-decoration:none;">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Why Choose Us ────────────── -->
<div class="home-why-section">
    <div class="home-section-inner" style="text-align:center;">
        <h2 style="font-size:clamp(1.5rem,3vw,1.9rem);font-weight:900;color:#fff;margin-bottom:0.5rem;">Why Partner With <?= getSetting('site_title','TORVO SPAIR') ?>?</h2>
        <p style="color:rgba(255,255,255,0.5);margin-bottom:3rem;">Trusted by 200+ dealers and distributors across India</p>
        <div class="feature-grid">
            <?php foreach ([
                ['fas fa-shield-alt',  'Genuine Parts',       'All parts are OEM-grade certified for quality assurance and long service life.'],
                ['fas fa-truck',       'Fast Delivery',       'Pan-India delivery within 3–7 business days with real-time tracking.'],
                ['fas fa-percent',     'Competitive Pricing', 'Tiered pricing for Silver & Gold partners with up to 10% discount.'],
                ['fas fa-headset',     'Dedicated Support',   'Dedicated account manager for every partner account.'],
                ['fas fa-file-invoice','GST Invoicing',       'GST-compliant invoices with CGST/SGST split for seamless accounting.'],
                ['fas fa-boxes',       '500+ Products',       'Comprehensive range covering Drills, Grinders, Cutters, Jigsaws & more.'],
            ] as [$icon, $title, $desc]): ?>
            <div style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:14px;padding:1.75rem;text-align:left;">
                <div style="width:44px;height:44px;background:rgba(249,115,22,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:1rem;">
                    <i class="<?= $icon ?>" style="color:#fb923c;font-size:1rem;"></i>
                </div>
                <div style="font-weight:700;color:#fff;margin-bottom:0.4rem;"><?= $title ?></div>
                <div style="font-size:0.82rem;color:rgba(255,255,255,0.5);line-height:1.6;"><?= $desc ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── CTA ──────────────────────── -->
<div class="home-cta-section">
    <h2 style="font-size:clamp(1.5rem,3vw,1.9rem);font-weight:900;color:var(--text-dark);margin-bottom:0.75rem;">Ready to Start Sourcing?</h2>
    <p style="color:var(--text-light);margin-bottom:2rem;max-width:500px;margin:0 auto 2rem;">Register as a dealer or distributor today and get access to exclusive B2B pricing, priority support, and fast delivery.</p>
    <div style="display:flex;justify-content:center;gap:0.75rem;flex-wrap:wrap;">
        <a href="register.php" class="btn btn-accent btn-lg"><i class="fas fa-handshake"></i> Apply for Partnership</a>
        <a href="catalogue.php" class="btn btn-outline btn-lg"><i class="fas fa-th-large"></i> Browse Catalogue</a>
        <a href="contact.php" class="btn btn-outline btn-lg"><i class="fas fa-envelope"></i> Contact Sales</a>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
