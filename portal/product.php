<?php
require_once __DIR__ . '/config/auth.php';


$db = portalDB();
$pid = (int)($_GET['id'] ?? 0);
if (!$pid) { header('Location: catalogue.php'); exit; }

$product = $db->prepare("SELECT p.*,c.name AS cat FROM products p JOIN categories c ON p.category_id=c.id WHERE p.id=? AND p.status='active'");
$product->execute([$pid]);
$p = $product->fetch();
if (!$p) { header('Location: catalogue.php'); exit; }

// Compatible tools
$tools = $db->prepare("SELECT t.* FROM tools t JOIN product_compatibility pc ON t.id=pc.tool_id WHERE pc.product_id=? AND t.status='active'");
$tools->execute([$pid]);
$compatTools = $tools->fetchAll();

// Related products (same category)
$related = $db->prepare("SELECT p.*,c.name AS cat FROM products p JOIN categories c ON p.category_id=c.id WHERE p.category_id=? AND p.id!=? AND p.status='active' ORDER BY RAND() LIMIT 4");
$related->execute([$p['category_id'], $pid]);
$relatedProducts = $related->fetchAll();

// Add to RFQ cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_rfq'])) {
    if (!customerLoggedIn()) {
        setPortalFlash('info', 'Please login to add items to your RFQ cart.');
        header('Location: index.php?redirect=product.php?id=' . $pid); exit;
    }
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    $cart = $_SESSION['rfq_cart'] ?? [];
    $found = false;
    foreach ($cart as &$item) {
        if ($item['product_id'] == $pid) { $item['quantity'] = min($item['quantity'] + $qty, 1000); $found = true; break; }
    }
    unset($item);
    if (!$found) {
        $cart[] = ['product_id'=>$pid,'name'=>$p['name'],'sku'=>$p['sku'],'price'=>$p['price'],'image'=>$p['image'],'quantity'=>$qty];
    }
    $_SESSION['rfq_cart'] = $cart;
    setPortalFlash('success', htmlspecialchars($p['name']) . ' added to your RFQ cart.');
    header('Location: product.php?id=' . $pid); exit;
}

$inStock = $p['quantity'] > 0;
$lowStock = $inStock && $p['quantity'] <= ($p['min_stock'] ?? 5);

$pageTitle  = $p['name'];
$activePage = 'catalogue';
include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb"><div class="breadcrumb-inner">
    <a href="home.php">Home</a>
    <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
    <a href="catalogue.php">Catalogue</a>
    <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
    <a href="catalogue.php?cat=<?= $p['category_id'] ?>"><?= htmlspecialchars($p['cat']) ?></a>
    <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
    <span><?= htmlspecialchars($p['name']) ?></span>
</div></div>

<div class="section container" style="max-width:1100px;">

    <!-- Main product section -->
    <div style="display:grid;grid-template-columns:380px 1fr;gap:2rem;margin-bottom:2rem;align-items:start;">

        <!-- Product Image -->
        <div>
            <div style="background:#fff;border:1px solid var(--border);border-radius:14px;overflow:hidden;aspect-ratio:1;display:flex;align-items:center;justify-content:center;margin-bottom:0.75rem;position:relative;">
                <?php if ($p['image'] && file_exists(UPLOAD_DIR . $p['image'])): ?>
                <img src="<?= UPLOAD_URL . htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" style="max-width:100%;max-height:100%;object-fit:contain;padding:1.5rem;">
                <?php else: ?>
                <div style="text-align:center;color:var(--border);">
                    <i class="fas fa-cog" style="font-size:5rem;display:block;margin-bottom:0.5rem;"></i>
                    <span style="font-size:0.75rem;">No image</span>
                </div>
                <?php endif; ?>
                <?php if (!$inStock): ?>
                <div style="position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.7);display:flex;align-items:center;justify-content:center;">
                    <span style="background:#ef4444;color:#fff;padding:0.5rem 1.5rem;border-radius:50px;font-weight:800;font-size:0.875rem;">Out of Stock</span>
                </div>
                <?php endif; ?>
            </div>
            <!-- Quick action buttons -->
            <div style="display:flex;gap:0.5rem;">
                <a href="https://api.whatsapp.com/send?phone=919800000000&text=Hi! I'd like to enquire about <?= urlencode($p['name']) ?> (SKU: <?= urlencode($p['sku']) ?>)" target="_blank"
                   class="btn btn-outline" style="flex:1;color:#25d366;border-color:#25d366;justify-content:center;">
                    <i class="fab fa-whatsapp"></i> Enquire
                </a>
                <a href="catalogue.php?cat=<?= $p['category_id'] ?>" class="btn btn-outline" style="flex:1;justify-content:center;">
                    <i class="fas fa-layer-group"></i> Category
                </a>
            </div>
        </div>

        <!-- Product Details -->
        <div>
            <div class="product-cat" style="margin-bottom:0.4rem;"><?= htmlspecialchars($p['cat']) ?></div>
            <h1 style="font-size:1.5rem;font-weight:900;color:var(--text-dark);line-height:1.2;margin-bottom:0.5rem;"><?= htmlspecialchars($p['name']) ?></h1>

            <?php if ($p['brand']): ?>
            <div style="font-size:0.82rem;color:var(--text-light);margin-bottom:0.75rem;">Brand: <strong><?= htmlspecialchars($p['brand']) ?></strong></div>
            <?php endif; ?>

            <!-- Price & Stock -->
            <div style="display:flex;align-items:center;gap:1.25rem;margin-bottom:1rem;padding:1rem;background:var(--bg-gray);border-radius:12px;">
                <div>
                    <div style="font-size:0.65rem;text-transform:uppercase;color:var(--text-muted);letter-spacing:0.5px;">Unit Price</div>
                    <div style="font-size:1.75rem;font-weight:900;color:var(--primary);"><?= formatCurrency($p['price']) ?></div>
                    <div style="font-size:0.7rem;color:var(--text-muted);">excl. GST</div>
                </div>
                <div style="width:1px;height:50px;background:var(--border);"></div>
                <div>
                    <div style="font-size:0.65rem;text-transform:uppercase;color:var(--text-muted);">Stock</div>
                    <?php if (!$inStock): ?>
                    <span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Out of Stock</span>
                    <?php elseif ($lowStock): ?>
                    <span class="status-badge status-reviewing"><i class="fas fa-exclamation-triangle"></i> Low Stock</span>
                    <?php else: ?>
                    <span class="status-badge status-accepted"><i class="fas fa-check-circle"></i> In Stock</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Specs table -->
            <div style="margin-bottom:1.25rem;background:#fff;border:1px solid var(--border);border-radius:12px;overflow:hidden;">
                <div style="padding:0.6rem 1rem;background:var(--bg-gray);font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);">Product Details</div>
                <?php foreach ([
                    ['SKU',       $p['sku']],
                    ['Brand',     $p['brand']   ?: '—'],
                    ['Category',  $p['cat']],
                    ['Min Order', ($p['min_stock'] ?? 1) . ' units'],
                ] as [$label, $val]): ?>
                <div style="display:flex;padding:0.6rem 1rem;border-bottom:1px solid var(--border);font-size:0.83rem;">
                    <span style="color:var(--text-muted);width:120px;flex-shrink:0;"><?= $label ?></span>
                    <span style="font-weight:600;color:var(--text-dark);"><?= htmlspecialchars($val) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Description -->
            <?php if ($p['description']): ?>
            <div style="margin-bottom:1.25rem;font-size:0.875rem;color:var(--text-medium);line-height:1.7;">
                <?= nl2br(htmlspecialchars($p['description'])) ?>
            </div>
            <?php endif; ?>

            <!-- Add to RFQ -->
            <form method="POST" style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
            <!-- Add to RFQ -->
            <form method="POST" style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;background:#fff;border:1px solid var(--border);border-radius:10px;overflow:hidden;">
                    <button type="button" onclick="const i=document.getElementById('qty');i.value=Math.max(1,+i.value-1)" style="width:38px;height:38px;border:none;background:none;cursor:pointer;font-size:1.1rem;color:var(--text-muted);">−</button>
                    <input type="number" name="quantity" id="qty" value="1" min="1" max="10000" style="width:60px;text-align:center;border:none;outline:none;font-size:0.95rem;font-weight:700;">
                    <button type="button" onclick="const i=document.getElementById('qty');i.value=+i.value+1" style="width:38px;height:38px;border:none;background:none;cursor:pointer;font-size:1.1rem;color:var(--text-muted);">+</button>
                </div>
                <?php if ($inStock): ?>
                <button type="submit" name="add_to_rfq" class="btn btn-accent btn-lg" style="flex:1;min-width:180px;justify-content:center;">
                    <i class="fas fa-file-invoice"></i> Add to RFQ Cart
                </button>
                <?php else: ?>
                <button type="button" class="btn btn-lg" style="flex:1;min-width:180px;background:var(--bg-gray);color:var(--text-muted);cursor:not-allowed;" disabled>
                    <i class="fas fa-times"></i> Out of Stock
                </button>
                <?php endif; ?>
                <a href="rfq_cart.php" class="btn btn-outline btn-lg"><i class="fas fa-shopping-cart"></i> View Cart</a>
            </form>
        </div>
    </div>

    <!-- Exploded View & Spare Parts Section -->
    <?php
    // Fetch diagram parts if they exist
    $diagStmt = $db->prepare("
        SELECT dp.*, p.name, p.sku, p.price, p.image, p.quantity 
        FROM diagram_parts dp 
        JOIN products p ON dp.part_product_id = p.id 
        WHERE dp.parent_product_id = ? 
        ORDER BY CAST(dp.number_on_diagram AS UNSIGNED) ASC
    ");
    $diagStmt->execute([$pid]);
    $diagramParts = $diagStmt->fetchAll();
    
    // Check if this tool has an exploded view
    $hasDiagram = !empty($p['diagram_image']) || !empty($diagramParts);
    if ($hasDiagram):
    ?>
    <div class="card" style="margin-bottom:2rem;border:none;box-shadow:var(--shadow-lg);">
        <div class="card-header" style="background:linear-gradient(90deg, var(--primary-dark), var(--primary));border:none;padding:1.5rem 2rem;">
            <div class="card-title" style="color:#fff;font-size:1.2rem;">
                <i class="fas fa-microchip" style="color:var(--accent);"></i> Exploded View & Spare Parts
            </div>
        </div>
        <div class="card-body" style="padding:0;">
            <div style="display:grid;grid-template-columns:1fr 350px;gap:0;">
                
                <!-- Interaction Diagram Area -->
                <div style="padding:2rem;background:#fcfcfc;border-right:1px solid var(--border);">
                    <div style="position:relative;background:#fff;border-radius:12px;padding:1rem;box-shadow:var(--shadow-sm);min-height:400px;display:flex;align-items:center;justify-content:center;">
                        <?php if (!empty($p['diagram_image'])): ?>
                            <img src="<?= UPLOAD_URL . htmlspecialchars($p['diagram_image']) ?>" style="max-width:100%;object-fit:contain;" id="explodedDiagram">
                            
                            <!-- Hotspots Overlay (CSS-only for now) -->
                            <?php foreach ($diagramParts as $dp): ?>
                            <div class="diagram-hotspot" 
                                 style="position:absolute;left:<?= $dp['x_percent'] ?>%;top:<?= $dp['y_percent'] ?>%;"
                                 title="<?= htmlspecialchars($dp['name']) ?>"
                                 onclick="document.getElementById('part_row_<?= $dp['part_product_id'] ?>').scrollIntoView({behavior:'smooth'})">
                                <?= $dp['number_on_diagram'] ?>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align:center;color:var(--text-muted);">
                                <i class="fas fa-draw-polygon" style="font-size:4rem;display:block;margin-bottom:1rem;opacity:0.2;"></i>
                                <p>Exploded view drawing is currently being updated for this tool.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Parts List Panel -->
                <div style="max-height:600px;overflow-y:auto;background:#fff;">
                    <div style="padding:1rem 1.5rem;background:var(--bg-gray);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:2;">
                        <span style="font-size:0.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Identified Parts</span>
                    </div>
                    <?php if (empty($diagramParts)): ?>
                        <div style="padding:3rem 2rem;text-align:center;color:var(--text-muted);font-size:0.875rem;">
                            No individual parts linked yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($diagramParts as $dp): ?>
                        <div id="part_row_<?= $dp['part_product_id'] ?>" style="padding:1rem 1.5rem;border-bottom:1px solid var(--border);transition:all 0.2s;" onmouseover="this.style.background='var(--bg-gray)'" onmouseout="this.style.background='transparent'">
                            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.5rem;">
                                <div style="width:24px;height:24px;background:var(--primary);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.65rem;font-weight:800;flex-shrink:0;">
                                    <?= $dp['number_on_diagram'] ?>
                                </div>
                                <div style="font-weight:700;font-size:0.875rem;color:var(--text-dark);"><?= htmlspecialchars($dp['name']) ?></div>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <div style="font-size:0.75rem;color:var(--text-muted);">SKU: <?= htmlspecialchars($dp['sku']) ?></div>
                                <div style="font-weight:800;color:var(--primary);font-size:0.9rem;"><?= formatCurrency($dp['price']) ?></div>
                            </div>
                            <div style="margin-top:0.75rem;">
                                <a href="product.php?id=<?= $dp['part_product_id'] ?>" class="btn btn-outline btn-sm btn-full" style="font-size:0.7rem;padding:0.4rem;">
                                    <i class="fas fa-shopping-cart"></i> View & Add
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <style>
        .diagram-hotspot {
            background: var(--accent); color: #fff; width: 22px; height: 22px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 900;
            cursor: pointer; box-shadow: 0 0 0 2px #fff, 0 4px 10px rgba(0,0,0,0.2);
            transition: all 0.2s; border: none;
        }
        .diagram-hotspot:hover {
            transform: scale(1.3); background: var(--primary-light); z-index: 10;
        }
    </style>
    <?php endif; ?>

    <!-- Compatible Tools -->
    <?php if (!empty($compatTools)): ?>
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-tools" style="color:var(--accent);"></i> Compatible Power Tools</div>
            <span style="font-size:0.78rem;color:var(--text-muted);"><?= count($compatTools) ?> tool<?= count($compatTools)!=1?'s':'' ?></span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:0.75rem;padding:1rem 1.25rem;">
            <?php foreach ($compatTools as $tool): ?>
            <div style="display:flex;align-items:center;gap:0.75rem;background:var(--bg-gray);border-radius:10px;padding:0.65rem 0.85rem;">
                <div style="width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,var(--accent),#ea580c);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-tools" style="color:#fff;font-size:0.8rem;"></i>
                </div>
                <div>
                    <div style="font-size:0.82rem;font-weight:700;color:var(--text-dark);"><?= htmlspecialchars($tool['name']) ?></div>
                    <div style="font-size:0.7rem;color:var(--text-muted);"><?= htmlspecialchars($tool['brand'] ?? '') ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Related Products -->
    <?php if (!empty($relatedProducts)): ?>
    <div>
        <h2 style="font-size:1.1rem;font-weight:800;color:var(--text-dark);margin-bottom:1rem;">Related Products</h2>
        <div class="product-grid">
            <?php foreach ($relatedProducts as $rp):
                $rInStock = $rp['quantity'] > 0;
            ?>
            <div class="product-card">
                <div class="product-img">
                    <?php if ($rp['image'] && file_exists(UPLOAD_DIR.$rp['image'])): ?>
                        <img src="<?= UPLOAD_URL.htmlspecialchars($rp['image']) ?>" alt="">
                    <?php else: ?>
                        <i class="fas fa-cog no-img"></i>
                    <?php endif; ?>
                    <span class="product-badge <?= $rInStock?'badge-instock':'badge-out' ?>"><?= $rInStock?'In Stock':'Out of Stock' ?></span>
                </div>
                <div class="product-body">
                    <div class="product-cat"><?= htmlspecialchars($rp['cat']) ?></div>
                    <div class="product-name"><?= htmlspecialchars($rp['name']) ?></div>
                    <div class="product-sku">SKU: <?= htmlspecialchars($rp['sku']) ?></div>
                    <div class="product-footer">
                        <div class="product-price"><?= formatCurrency($rp['price']) ?></div>
                        <a href="product.php?id=<?= $rp['id'] ?>" class="btn-add-rfq" style="text-decoration:none;"><i class="fas fa-eye"></i> View</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
