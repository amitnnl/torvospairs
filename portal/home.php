<?php
require_once __DIR__ . '/config/auth.php';
ensureB2BTables();

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

<!-- Hero Section -->
<div style="background:var(--primary-dark);padding:5rem 1.5rem;position:relative;overflow:hidden;" id="heroSection">
    
    <!-- Sliding Background Container -->
    <?php
        $defaultSlides = [
            'https://images.unsplash.com/photo-1504148455328-c376907d081c?auto=format&fit=crop&w=1920&q=80',
            'https://images.unsplash.com/photo-1544377192-339241c27c65?auto=format&fit=crop&w=1920&q=80',
            'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&w=1920&q=80'
        ];
        $activeSlides = [];
        for ($i=1; $i<=3; $i++) {
            $s = getSetting("hero_slide_$i");
            if (!empty($s) && file_exists(UPLOAD_DIR . $s)) {
                $activeSlides[] = UPLOAD_URL . $s;
            } else {
                $activeSlides[] = $defaultSlides[$i-1];
            }
        }
    ?>
    <div id="heroSlider" style="position:absolute;inset:0;z-index:0;background-color:#000;">
        <?php foreach($activeSlides as $idx => $slideImg): ?>
        <div class="hero-slide <?= $idx === 0 ? 'active' : '' ?>" style="background-image:url('<?= htmlspecialchars($slideImg) ?>');"></div>
        <?php endforeach; ?>
    </div>
    
    <!-- Gradient Overlay to keep text readable -->
    <div style="position:absolute;inset:0;z-index:0;background:linear-gradient(135deg, rgba(15,23,42,0.95) 0%, rgba(30,58,138,0.85) 45%, rgba(29,78,216,0.7) 100%);"></div>

    <style>
        .hero-slide {
            position: absolute; inset: 0; background-size: cover; background-position: center;
            opacity: 0; transform: scale(1.08); transition: opacity 1.5s ease-in-out, transform 6s linear;
        }
        .hero-slide.active { opacity: 0.6; transform: scale(1); }
    </style>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const slides = document.querySelectorAll('.hero-slide');
            let currentSlide = 0;
            if(slides.length > 0) {
                setInterval(() => {
                    slides[currentSlide].classList.remove('active');
                    currentSlide = (currentSlide + 1) % slides.length;
                    slides[currentSlide].classList.add('active');
                }, 5000); // Fades every 5 seconds
            }
        });
    </script>

    <!-- Animated background pattern -->
    <div style="position:absolute;inset:0;opacity:0.2;background-image:radial-gradient(circle,#fff 1px,transparent 1px);background-size:40px 40px;z-index:0;"></div>
    <!-- Glow blobs -->
    <div style="position:absolute;top:-80px;right:-80px;width:400px;height:400px;background:radial-gradient(circle,rgba(249,115,22,0.15),transparent 70%);pointer-events:none;"></div>
    <div style="position:absolute;bottom:-80px;left:-80px;width:400px;height:400px;background:radial-gradient(circle,rgba(37,99,235,0.2),transparent 70%);pointer-events:none;"></div>

    <div style="max-width:1100px;margin:0 auto;position:relative;z-index:1;">
        <div class="hero-grid">
            <div>
                <div style="display:inline-flex;align-items:center;gap:0.5rem;background:rgba(249,115,22,0.15);border:1px solid rgba(249,115,22,0.3);padding:0.4rem 1rem;border-radius:50px;color:#fb923c;font-size:0.75rem;font-weight:700;letter-spacing:0.5px;margin-bottom:1.5rem;">
                    <i class="fas fa-bolt"></i> <?= getSetting('b2b_subtitle', "INDIA'S TRUSTED B2B POWER TOOL PARTS SUPPLIER") ?>
                </div>
                <h1 style="font-size:clamp(2rem,4vw,2.8rem);font-weight:900;color:#fff;line-height:1.15;margin-bottom:1rem;">
                    <?= nl2br(getSetting('hero_title', "Genuine Spare Parts for Every Power Tool")) ?>
                </h1>
                <p style="font-size:1rem;color:rgba(255,255,255,0.6);line-height:1.7;margin-bottom:2rem;max-width:480px;">
                    <?= getSetting('hero_subtitle', $productCount . "+ genuine parts. Filter by tool compatibility, get instant quotes, and track your orders.") ?>
                </p>
                <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                    <a href="catalogue.php" style="background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;padding:0.85rem 2rem;border-radius:10px;font-weight:700;font-size:0.95rem;display:inline-flex;align-items:center;gap:0.5rem;text-decoration:none;box-shadow:0 8px 24px rgba(249,115,22,0.35);transition:all 0.3s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                        <i class="fas fa-th-large"></i> Browse Catalogue
                    </a>
                    <a href="register.php" style="background:rgba(255,255,255,0.08);color:#fff;border:1px solid rgba(255,255,255,0.25);padding:0.85rem 2rem;border-radius:10px;font-weight:600;font-size:0.95rem;display:inline-flex;align-items:center;gap:0.5rem;text-decoration:none;transition:all 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.15)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'">
                        <i class="fas fa-handshake"></i> Become a Partner
                    </a>
                </div>
            </div>

            <!-- Stats grid -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <?php foreach ([
                    [$productCount . '+', 'Spare Parts', 'fas fa-cog', '#6c63ff'],
                    [$toolCount,          'Tool Types',  'fas fa-tools','#48daf5'],
                    [$catCount,           'Categories',  'fas fa-layer-group','#22c55e'],
                    ['24hr',              'Quote Reply', 'fas fa-clock','#f97316'],
                ] as [$num, $lbl, $icon, $color]): ?>
                <div style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:14px;padding:1.5rem;text-align:center;backdrop-filter:blur(8px);">
                    <i class="<?= $icon ?>" style="color:<?= $color ?>;font-size:1.5rem;margin-bottom:0.6rem;display:block;"></i>
                    <div style="font-size:1.8rem;font-weight:900;color:#fff;"><?= $num ?></div>
                    <div style="font-size:0.72rem;color:rgba(255,255,255,0.45);text-transform:uppercase;letter-spacing:0.5px;"><?= $lbl ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- How It Works -->
<div style="background:#fff;padding:4rem 1.5rem;border-bottom:1px solid var(--border);">
    <div style="max-width:1100px;margin:0 auto;text-align:center;">
        <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--primary);margin-bottom:0.5rem;">Simple Process</div>
        <h2 style="font-size:1.75rem;font-weight:800;color:var(--text-dark);margin-bottom:3rem;">How It Works</h2>
        <div class="process-grid">
            <!-- Connecting line -->
            <div class="process-line" style="position:absolute;top:28px;left:12%;right:12%;height:2px;background:linear-gradient(90deg,var(--primary),var(--accent));z-index:0;opacity:0.2;"></div>
            <?php foreach ([
                ['1','Register',    'fas fa-building',     'Create your B2B account as a dealer or distributor.', 'var(--primary)'],
                ['2','Browse',      'fas fa-search',        'Explore 500+ parts filtered by your tool type.', 'var(--primary-light)'],
                ['3','Request RFQ', 'fas fa-file-invoice',  'Add to cart and submit a Request for Quotation.', '#6366f1'],
                ['4','Get Shipped', 'fas fa-truck',         'Receive confirmed pricing and track your delivery.', 'var(--accent)'],
            ] as [$step, $title, $icon, $desc, $color]): ?>
            <div style="position:relative;z-index:1;">
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

<!-- Featured Categories -->
<?php if (!empty($featCats)): ?>
<div style="padding:4rem 1.5rem;background:var(--bg-gray);">
    <div style="max-width:1300px;margin:0 auto;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;">
            <div>
                <div style="font-size:0.75rem;color:var(--primary);font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:0.4rem;">Product Range</div>
                <h2 style="font-size:1.5rem;font-weight:800;color:var(--text-dark);">Shop by Category</h2>
            </div>
            <a href="catalogue.php" class="btn btn-outline btn-sm">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;">
            <?php
            $catIcons  = ['Drill Parts'=>'fa-circle-dot','Grinder Accessories'=>'fa-sun','Cutting Tools'=>'fa-scissors','Power Tools'=>'fa-bolt','Accessories'=>'fa-toolbox','Bearings'=>'fa-gear'];
            $catColors = ['#6c63ff','#f97316','#ef4444','#2563eb','#16a34a','#0891b2'];
            foreach ($featCats as $idx => $cat):
                $color = $catColors[$idx % count($catColors)];
            ?>
            <a href="catalogue.php?cat=<?= $cat['id'] ?>" style="background:#fff;border:1px solid var(--border);border-radius:14px;padding:1.5rem 1rem;text-align:center;text-decoration:none;transition:all 0.25s;display:block;" onmouseover="this.style.borderColor='<?= $color ?>';this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 24px <?= $color ?>22'" onmouseout="this.style.borderColor='var(--border)';this.style.transform='';this.style.boxShadow=''">
                <div style="width:50px;height:50px;border-radius:12px;background:<?= $color ?>15;display:flex;align-items:center;justify-content:center;margin:0 auto 0.75rem;">
                    <i class="fas fa-cog" style="color:<?= $color ?>;font-size:1.2rem;"></i>
                </div>
                <div style="font-weight:700;color:var(--text-dark);font-size:0.875rem;margin-bottom:0.2rem;"><?= htmlspecialchars($cat['name']) ?></div>
                <div style="font-size:0.7rem;color:var(--text-muted);"><?= $cat['prod_count'] ?> products</div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- New Arrivals -->
<?php if (!empty($newProducts)): ?>
<div style="padding:4rem 1.5rem;background:#fff;">
    <div style="max-width:1300px;margin:0 auto;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;">
            <div>
                <div style="font-size:0.75rem;color:var(--accent);font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:0.4rem;">Latest Stock</div>
                <h2 style="font-size:1.5rem;font-weight:800;color:var(--text-dark);">New Arrivals</h2>
            </div>
            <a href="catalogue.php" class="btn btn-outline btn-sm">Full Catalogue <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="product-grid">
            <?php foreach (array_slice($newProducts, 0, 4) as $p):
                $stockClass = $p['quantity'] == 0 ? 'badge-out' : ($p['quantity'] <= ($p['min_stock']??5) ? 'badge-low' : 'badge-instock');
                $stockLabel = $p['quantity'] == 0 ? 'Out of Stock' : ($p['quantity'] <= ($p['min_stock']??5) ? 'Low Stock' : 'In Stock');
            ?>
            <div class="product-card">
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
                            <?php if ($customer && isset($customer['status']) && $customer['status'] === 'active'): ?>
                                <?= formatCurrency($p['price']) ?><small>/unit</small>
                            <?php else: ?>
                                <span style="font-size:0.75rem;color:var(--warning);"><i class="fas fa-lock"></i> <?php echo $customer ? 'Pending' : 'Login for Price'; ?></span>
                            <?php endif; ?>
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

<!-- Why Choose Us -->
<div style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8);padding:4rem 1.5rem;">
    <div style="max-width:1100px;margin:0 auto;text-align:center;">
        <h2 style="font-size:1.75rem;font-weight:900;color:#fff;margin-bottom:0.5rem;">Why Partner With TORVO SPAIR?</h2>
        <p style="color:rgba(255,255,255,0.5);margin-bottom:3rem;">Trusted by 200+ dealers and distributors across India</p>
        <div class="feature-grid">
            <?php foreach ([
                ['fas fa-shield-alt',       'Genuine Parts',         'All parts are OEM-grade certified for quality assurance and long service life.'],
                ['fas fa-truck',             'Fast Delivery',         'Pan-India delivery within 3-7 business days with real-time tracking.'],
                ['fas fa-percent',           'Competitive Pricing',   'Tiered pricing for Silver & Gold partners with up to 10% discount.'],
                ['fas fa-headset',           'Dedicated Support',     'Dedicated account manager for every partner account.'],
                ['fas fa-file-invoice',      'GST Invoicing',         'GST-compliant invoices with CGST/SGST split for seamless accounting.'],
                ['fas fa-boxes',             '500+ Products',         'Comprehensive range covering Drills, Grinders, Cutters, Jigsaws & more.'],
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

<!-- CTA -->
<div style="background:#fff;padding:4rem 1.5rem;text-align:center;">
    <h2 style="font-size:1.75rem;font-weight:900;color:var(--text-dark);margin-bottom:0.75rem;">Ready to Start Sourcing?</h2>
    <p style="color:var(--text-light);margin-bottom:2rem;max-width:500px;margin-left:auto;margin-right:auto;">Register as a dealer or distributor today and get access to exclusive B2B pricing, priority support, and fast delivery.</p>
    <div style="display:flex;justify-content:center;gap:1rem;flex-wrap:wrap;">
        <a href="register.php" class="btn btn-accent btn-lg"><i class="fas fa-handshake"></i> Apply for Partnership</a>
        <a href="catalogue.php" class="btn btn-outline btn-lg"><i class="fas fa-th-large"></i> Browse Catalogue</a>
        <a href="contact.php" class="btn btn-outline btn-lg"><i class="fas fa-envelope"></i> Contact Sales</a>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
