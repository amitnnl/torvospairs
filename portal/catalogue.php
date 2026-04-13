<?php
require_once __DIR__ . '/config/auth.php';


// Handle add to RFQ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['add_rfq'])) {
    $pid = (int)$_POST['product_id'];
    $qty = max(1, (int)($_POST['qty'] ?? 1));
    addToRFQ($pid, $qty);
    header('Location: catalogue.php?' . http_build_query(array_filter([
        'tool'   => $_GET['tool'] ?? '',
        'cat'    => $_GET['cat'] ?? '',
        'brand'  => $_GET['brand'] ?? '',
        'search' => $_GET['search'] ?? '',
    ])) . '&added=' . $pid);
    exit;
}

$db     = portalDB();
$added  = (int)($_GET['added'] ?? 0);

// Fetch filters
$toolId  = (int)($_GET['tool']   ?? 0);
$catId   = (int)($_GET['cat']    ?? 0);
$brand   = sanitize($_GET['brand']  ?? '');
$search  = sanitize($_GET['search'] ?? '');

// Fetch tools & categories for dropdowns
$tools      = $db->query("SELECT id, name, brand FROM tools WHERE status='active' ORDER BY name")->fetchAll();
$categories = $db->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name")->fetchAll();
$brands     = $db->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' AND status='active' ORDER BY brand")->fetchAll(PDO::FETCH_COLUMN);

// Build product query
$where = ["p.status = 'active'"];
$params = [];

if ($toolId) {
    $where[] = "EXISTS (SELECT 1 FROM product_compatibility pc WHERE pc.product_id = p.id AND pc.tool_id = ?)";
    $params[] = $toolId;
}
if ($catId) { $where[] = "p.category_id = ?"; $params[] = $catId; }
if ($brand) { $where[] = "p.brand = ?";        $params[] = $brand; }
if ($search) {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
}

$sql = "SELECT p.*, c.name AS category_name FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE " . implode(' AND ', $where) . " ORDER BY p.name";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get compat tags for each product
$allCompat = [];
$compatRows = $db->query("SELECT pc.product_id, t.name AS tool_name FROM product_compatibility pc JOIN tools t ON pc.tool_id = t.id")->fetchAll();
foreach ($compatRows as $r) $allCompat[$r['product_id']][] = $r['tool_name'];

$pageTitle  = 'Parts Catalogue';
$activePage = 'catalogue';
include __DIR__ . '/includes/header.php';

$rfqCart = getRFQCart();
?>

<!-- Hero (only on fresh load with no filters) -->
<?php if (!$toolId && !$catId && !$brand && !$search): ?>
<div class="portal-hero">
    <div class="hero-inner">
        <div class="hero-badge"><i class="fas fa-shield-alt"></i> Verified B2B Supplier</div>
        <h1 class="hero-title">Power Tool <span>Spare Parts</span><br>& Accessories Catalogue</h1>
        <p class="hero-desc">Browse 500+ genuine spare parts for Drills, Grinders, Cutters, Jigsaws & more. Filter by tool compatibility to find the exact part you need.</p>
        <div class="hero-actions">
            <a href="#catalogue-section" class="btn-hero-primary"><i class="fas fa-search"></i> Browse Catalogue</a>
            <a href="register.php" class="btn-hero-outline"><i class="fas fa-handshake"></i> Become a Partner</a>
        </div>
    </div>
</div>

<!-- Stats Bar -->
<div class="stats-bar">
    <div class="stats-inner">
        <div class="stat-item">
            <span class="stat-num"><?= count($products) ?>+</span>
            <span class="stat-label">Products</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
            <span class="stat-num"><?= count($tools) ?></span>
            <span class="stat-label">Tool Types</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
            <span class="stat-num"><?= count($categories) ?></span>
            <span class="stat-label">Categories</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
            <span class="stat-num">24hr</span>
            <span class="stat-label">Quote Turnaround</span>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="breadcrumb-inner">
        <a href="catalogue.php">Catalogue</a> <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
        <?php if ($toolId): ?>
            <?php $t = current(array_filter($tools, fn($t)=>$t['id']==$toolId)); ?>
            <span><?= htmlspecialchars($t['name'] ?? 'Tool') ?></span>
        <?php elseif ($catId): ?>
            <?php $c = current(array_filter($categories, fn($c)=>$c['id']==$catId)); ?>
            <span><?= htmlspecialchars($c['name'] ?? 'Category') ?></span>
        <?php elseif ($search): ?>
            <span>Search: "<?= htmlspecialchars($search) ?>"</span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div id="catalogue-section" class="section container">

    <!-- Added to RFQ notice -->
    <?php if ($added): ?>
    <div class="alert alert-success" style="margin-bottom:1rem;">
        <i class="fas fa-check-circle"></i>
        Product added to your RFQ Cart!
        <a href="rfq_cart.php" style="font-weight:700;color:var(--success);margin-left:0.5rem;">View Cart <i class="fas fa-arrow-right"></i></a>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="filter-bar">
        <form method="GET" style="display:contents;" id="filterForm">
            <!-- Tool filter -->
            <div class="filter-group">
                <label><i class="fas fa-tools"></i> Tool:</label>
                <select name="tool" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Tools</option>
                    <?php foreach ($tools as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $toolId==$t['id']?'selected':'' ?>>
                        <?= htmlspecialchars($t['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Category filter -->
            <div class="filter-group">
                <label><i class="fas fa-tag"></i> Category:</label>
                <select name="cat" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $catId==$c['id']?'selected':'' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Brand filter -->
            <div class="filter-group">
                <label><i class="fas fa-industry"></i> Brand:</label>
                <select name="brand" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Brands</option>
                    <?php foreach ($brands as $b): ?>
                    <option value="<?= htmlspecialchars($b) ?>" <?= $brand===$b?'selected':'' ?>>
                        <?= htmlspecialchars($b) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Search -->
            <div class="filter-group filter-search">
                <label><i class="fas fa-search"></i></label>
                <input type="text" name="search" class="filter-input" placeholder="Search parts, SKU, description..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn-filter btn-filter-primary"><i class="fas fa-search"></i> Search</button>
            <?php if ($toolId || $catId || $brand || $search): ?>
            <a href="catalogue.php" class="btn-filter btn-filter-reset"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Results info -->
    <div class="results-info">
        Showing <strong><?= count($products) ?></strong> product<?= count($products)!=1?'s':'' ?>
        <?php if ($toolId && !empty($t)): ?> compatible with <strong><?= htmlspecialchars($t['name']) ?></strong><?php endif; ?>
        <?php if ($search): ?> matching "<strong><?= htmlspecialchars($search) ?></strong>"<?php endif; ?>
    </div>

    <!-- Product Grid -->
    <?php if (empty($products)): ?>
    <div class="empty-state">
        <i class="fas fa-search-minus"></i>
        <h3>No products found</h3>
        <p>Try adjusting your filters or <a href="catalogue.php" style="color:var(--primary);">view all products</a></p>
    </div>
    <?php else: ?>
    <div class="product-grid">
        <?php foreach ($products as $p):
            $inCart = isset($rfqCart[$p['id']]);
            $stockClass = $p['quantity'] == 0 ? 'badge-out' : ($p['quantity'] <= $p['min_stock'] ? 'badge-low' : 'badge-instock');
            $stockLabel = $p['quantity'] == 0 ? 'Out of Stock' : ($p['quantity'] <= $p['min_stock'] ? 'Low Stock' : 'In Stock');
            $compatTags = $allCompat[$p['id']] ?? [];
        ?>
        <div class="product-card">
            <div class="product-img">
                <?php if ($p['image'] && file_exists(UPLOAD_DIR . $p['image'])): ?>
                    <img src="<?= UPLOAD_URL . htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                <?php else: ?>
                    <i class="fas fa-cog no-img"></i>
                <?php endif; ?>
                <span class="product-badge <?= $stockClass ?>"><?= $stockLabel ?></span>
            </div>
            <div class="product-body">
                <div class="product-cat"><?= htmlspecialchars($p['category_name']) ?></div>
                <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                <div class="product-sku">SKU: <?= htmlspecialchars($p['sku']) ?> <?= $p['brand']?'· ' . htmlspecialchars($p['brand']):'' ?></div>
                <?php if (!empty($compatTags)): ?>
                <div class="product-compat">
                    <?php foreach (array_slice($compatTags,0,3) as $tag): ?>
                    <span class="compat-tag"><i class="fas fa-tools" style="font-size:0.55rem;"></i> <?= htmlspecialchars($tag) ?></span>
                    <?php endforeach; ?>
                    <?php if (count($compatTags)>3): ?><span class="compat-tag">+<?= count($compatTags)-3 ?> more</span><?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="product-footer">
                    <div class="product-price-hidden">
                        <i class="fas fa-lock"></i>
                        <?php if (!$customer): ?>
                            <span>Login to Request Quote</span>
                        <?php elseif (($customer['status'] ?? '') === 'pending'): ?>
                            <span>Approval Pending</span>
                        <?php else: ?>
                            <span>RFQ Pricing</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($p['quantity'] > 0): ?>
                    <form method="POST">
                        <input type="hidden" name="add_rfq" value="1">
                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="qty" value="1">
                        <button type="submit" class="btn-add-rfq <?= $inCart?'added':'' ?>">
                            <?php if ($inCart): ?>
                                <i class="fas fa-check"></i> In Cart
                            <?php else: ?>
                                <i class="fas fa-plus"></i> Add RFQ
                            <?php endif; ?>
                        </button>
                    </form>
                    <?php else: ?>
                    <span style="font-size:0.75rem;color:var(--danger);font-weight:600;">Unavailable</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
