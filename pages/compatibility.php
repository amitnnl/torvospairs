<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle  = 'Compatibility Map';
$pageIcon   = 'fas fa-project-diagram';
$activePage = 'compatibility';
$pageBreadcrumb = 'Compatibility Mapping';
include BASE_PATH . '/includes/header.php';

$db = getDB();

// ---- Handle POST (classic form save) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $tool_ids   = $_POST['tool_ids'] ?? [];
    if ($product_id > 0) {
        $db->prepare("DELETE FROM product_compatibility WHERE product_id=?")->execute([$product_id]);
        if (!empty($tool_ids)) {
            $ins = $db->prepare("INSERT IGNORE INTO product_compatibility (product_id, tool_id) VALUES (?,?)");
            foreach ($tool_ids as $tid) $ins->execute([$product_id, (int)$tid]);
        }
        setFlash('success', 'Mapping saved.');
        header('Location: compatibility.php'); exit;
    }
}

// ---- Handle AJAX save ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save'])) {
    header('Content-Type: application/json');
    $product_id = (int)($_POST['product_id'] ?? 0);
    $tool_ids   = $_POST['tool_ids'] ?? [];
    if ($product_id > 0) {
        $db->prepare("DELETE FROM product_compatibility WHERE product_id=?")->execute([$product_id]);
        if (!empty($tool_ids)) {
            $ins = $db->prepare("INSERT IGNORE INTO product_compatibility (product_id, tool_id) VALUES (?,?)");
            foreach ($tool_ids as $tid) $ins->execute([$product_id, (int)$tid]);
        }
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Invalid product']);
    }
    exit;
}

$tools    = $db->query("SELECT * FROM tools WHERE status='active' ORDER BY name")->fetchAll();
$products = $db->query("
    SELECT p.*, c.name AS cat_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.status='active'
    ORDER BY c.name, p.name
")->fetchAll();

// Build full compat map: product_id => [tool_ids]
$compatRows = $db->query("SELECT product_id, tool_id FROM product_compatibility")->fetchAll();
$prodCompat = [];
foreach ($compatRows as $r) {
    $prodCompat[$r['product_id']][] = (int)$r['tool_id'];
}

// Tool compat counts
$toolCounts = [];
foreach ($tools as $t) $toolCounts[$t['id']] = 0;
foreach ($compatRows as $r) {
    if (isset($toolCounts[$r['tool_id']])) $toolCounts[$r['tool_id']]++;
}

// Group products by category
$byCategory = [];
foreach ($products as $p) {
    $byCategory[$p['cat_name']][] = $p;
}

$totalProducts = count($products);
$totalTools    = count($tools);
$totalLinks    = count($compatRows);
?>

<style>
/* ── Compatibility Map Unique UI ───────────────────────── */
.cmap-shell {
    display: grid;
    grid-template-columns: 260px 1fr 220px;
    gap: 1rem;
    padding: 0 1.5rem 2rem;
    min-height: calc(100vh - 140px);
}

/* LEFT – Tool Picker */
.cmap-tools-panel {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    position: sticky;
    top: 80px;
    height: calc(100vh - 150px);
}
.cmap-panel-head {
    padding: 1rem 1.1rem 0.75rem;
    border-bottom: 1px solid var(--border-color);
    background: linear-gradient(135deg, rgba(var(--primary-rgb,37,99,235),0.08), transparent);
}
.cmap-panel-head h3 {
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    margin: 0 0 0.6rem;
}
.cmap-tool-list {
    overflow-y: auto;
    flex: 1;
    padding: 0.6rem;
}
.cmap-tool-btn {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    width: 100%;
    padding: 0.6rem 0.85rem;
    border: 1.5px solid transparent;
    border-radius: 10px;
    background: transparent;
    cursor: pointer;
    text-align: left;
    margin-bottom: 0.3rem;
    transition: all 0.2s ease;
    color: var(--text-secondary);
    font-size: 0.82rem;
    font-weight: 500;
    font-family: inherit;
    position: relative;
}
.cmap-tool-btn:hover {
    background: var(--bg-card2);
    color: var(--text-primary);
    transform: translateX(3px);
}
.cmap-tool-btn.active {
    background: linear-gradient(135deg, rgba(37,99,235,0.12), rgba(37,99,235,0.05));
    border-color: var(--primary);
    color: var(--primary);
    font-weight: 600;
}
.cmap-tool-icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    color: #fff;
    flex-shrink: 0;
    transition: transform 0.2s;
}
.cmap-tool-btn.active .cmap-tool-icon {
    transform: scale(1.15);
}
.cmap-tool-count {
    margin-left: auto;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 20px;
    background: var(--bg-main);
    color: var(--text-muted);
    min-width: 24px;
    text-align: center;
    transition: all 0.3s;
}
.cmap-tool-btn.active .cmap-tool-count {
    background: var(--primary);
    color: #fff;
}
.cmap-tool-btn.all-tools-btn {
    border: 1.5px dashed var(--border-color);
    color: var(--text-muted);
    font-size: 0.78rem;
}
.cmap-tool-btn.all-tools-btn.active {
    border-color: var(--primary);
    border-style: solid;
}

/* CENTER – Matrix */
.cmap-matrix-panel {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.cmap-matrix-head {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: linear-gradient(135deg, var(--bg-card), var(--bg-card2));
    flex-wrap: wrap;
}
.cmap-matrix-head h2 {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    flex: 1;
}
.cmap-search-wrap {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: var(--bg-main);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.35rem 0.75rem;
    min-width: 200px;
}
.cmap-search-wrap input {
    border: none;
    background: none;
    outline: none;
    font-size: 0.82rem;
    color: var(--text-primary);
    width: 100%;
    font-family: inherit;
}
.cmap-search-wrap i { color: var(--text-muted); font-size: 0.78rem; }

.cmap-matrix-body {
    overflow-y: auto;
    flex: 1;
    padding: 0.75rem;
}

/* Category group */
.cmap-cat-group {
    margin-bottom: 1rem;
}
.cmap-cat-title {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-muted);
    padding: 0.3rem 0.5rem;
    margin-bottom: 0.4rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.cmap-cat-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border-color);
}

/* Product row */
.cmap-product-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 0.75rem;
    border: 1px solid transparent;
    border-radius: 10px;
    margin-bottom: 0.3rem;
    transition: all 0.2s;
    position: relative;
}
.cmap-product-row:hover {
    background: var(--bg-card2);
    border-color: var(--border-color);
}
.cmap-product-row.has-match {
    background: rgba(37,99,235,0.04);
    border-color: rgba(37,99,235,0.15);
}
.cmap-product-row.hidden-row { display: none; }

.cmap-product-info {
    flex: 1;
    min-width: 0;
}
.cmap-product-name {
    font-size: 0.83rem;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cmap-product-sku {
    font-size: 0.7rem;
    color: var(--text-muted);
    margin-top: 1px;
}

/* Tool Toggle Chips on each row */
.cmap-tool-chips {
    display: flex;
    gap: 0.3rem;
    flex-wrap: wrap;
    justify-content: flex-end;
    max-width: 55%;
}
.cmap-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 0.67rem;
    font-weight: 600;
    cursor: pointer;
    border: 1.5px solid var(--border-color);
    background: var(--bg-main);
    color: var(--text-muted);
    transition: all 0.18s ease;
    user-select: none;
    white-space: nowrap;
}
.cmap-chip:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: scale(1.05);
}
.cmap-chip.linked {
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    border-color: transparent;
    color: #fff;
    box-shadow: 0 2px 8px rgba(37,99,235,0.3);
}
.cmap-chip.linked:hover {
    filter: brightness(1.1);
    transform: scale(1.05);
}
.cmap-chip.highlighted {
    border-color: var(--primary);
    color: var(--primary);
    font-weight: 700;
}
.cmap-chip.linked.highlighted {
    box-shadow: 0 2px 12px rgba(37,99,235,0.5);
    filter: brightness(1.08);
}

/* Saving indicator */
.cmap-save-indicator {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.68rem;
    color: var(--success, #16a34a);
    font-weight: 600;
    opacity: 0;
    transition: opacity 0.3s;
    pointer-events: none;
}
.cmap-save-indicator.show { opacity: 1; }
.cmap-save-indicator.saving { color: var(--text-muted); }

/* RIGHT – Summary Panel */
.cmap-summary-panel {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    position: sticky;
    top: 80px;
    height: calc(100vh - 150px);
}
.cmap-stat-block {
    padding: 1rem 1.1rem;
    border-bottom: 1px solid var(--border-color);
    text-align: center;
}
.cmap-stat-num {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
    margin-bottom: 0.2rem;
}
.cmap-stat-label {
    font-size: 0.72rem;
    color: var(--text-muted);
    font-weight: 500;
}

/* Coverage meter */
.cmap-meter-wrap {
    padding: 1rem 1.1rem;
    border-bottom: 1px solid var(--border-color);
}
.cmap-meter-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 0.5rem;
    display: flex;
    justify-content: space-between;
}
.cmap-meter-bar {
    height: 8px;
    background: var(--bg-main);
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 0.4rem;
}
.cmap-meter-fill {
    height: 100%;
    border-radius: 20px;
    background: linear-gradient(90deg, var(--primary), #7c3aed);
    transition: width 0.5s ease;
}

/* Active tool info */
.cmap-active-tool-info {
    padding: 1rem 1.1rem;
    flex: 1;
    overflow-y: auto;
}
.cmap-active-tool-info h4 {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
    margin: 0 0 0.75rem;
}
.cmap-detail-tool-card {
    background: linear-gradient(135deg, rgba(37,99,235,0.1), rgba(124,58,237,0.1));
    border: 1px solid rgba(37,99,235,0.2);
    border-radius: 10px;
    padding: 0.85rem;
    text-align: center;
}
.cmap-detail-tool-name {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.3rem;
}
.cmap-detail-tool-compat {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--primary);
    line-height: 1;
}
.cmap-detail-tool-compat-label {
    font-size: 0.68rem;
    color: var(--text-muted);
}
.cmap-tips {
    margin-top: 0.85rem;
    padding: 0.75rem;
    background: var(--bg-card2);
    border-radius: 8px;
    font-size: 0.73rem;
    color: var(--text-muted);
    line-height: 1.5;
}
.cmap-tips strong { color: var(--text-secondary); }

/* Responsive */
@media (max-width: 1100px) {
    .cmap-shell { grid-template-columns: 220px 1fr; }
    .cmap-summary-panel { display: none; }
}
@media (max-width: 768px) {
    .cmap-shell { grid-template-columns: 1fr; }
    .cmap-tools-panel { height: auto; position: static; }
    .cmap-tool-list { max-height: 200px; }
}

/* Empty state */
.cmap-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 1rem;
    color: var(--text-muted);
    text-align: center;
}
.cmap-empty i { font-size: 2.5rem; margin-bottom: 0.75rem; opacity: 0.3; }
.cmap-empty p { font-size: 0.83rem; }

/* Toast */
#cmapToast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-left: 4px solid var(--primary);
    border-radius: 10px;
    padding: 0.75rem 1.25rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-primary);
    box-shadow: var(--shadow-lg);
    z-index: 9999;
    transform: translateX(120%);
    transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
#cmapToast.show { transform: translateX(0); }
#cmapToast.error { border-left-color: var(--danger, #dc2626); }
</style>

<div class="page-body" style="padding-top:0;">

<!-- Top bar -->
<div style="display:flex;align-items:center;gap:0.75rem;padding:1rem 1.5rem 0.75rem;flex-wrap:wrap;">
    <div style="flex:1;">
        <h1 style="font-size:1.1rem;font-weight:700;color:var(--text-primary);margin:0;display:flex;align-items:center;gap:0.4rem;">
            <i class="fas fa-project-diagram" style="color:var(--primary);"></i> Compatibility Matrix
        </h1>
        <p style="font-size:0.78rem;color:var(--text-muted);margin:0.15rem 0 0;">Click chips to toggle product–tool links. Changes save instantly.</p>
    </div>
    <a href="products.php" class="btn btn-outline btn-sm"><i class="fas fa-boxes"></i> Products</a>
    <a href="tools.php" class="btn btn-outline btn-sm"><i class="fas fa-tools"></i> Tools</a>
</div>

<div class="cmap-shell">

    <!-- ── LEFT: Tool Selector ─────────────────────────── -->
    <div class="cmap-tools-panel">
        <div class="cmap-panel-head">
            <h3><i class="fas fa-tools"></i> Power Tools</h3>
            <div class="cmap-search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="toolSearch" placeholder="Filter tools..." oninput="filterTools(this.value)">
            </div>
        </div>
        <div class="cmap-tool-list">
            <button class="cmap-tool-btn all-tools-btn active" id="toolBtn_all" onclick="setActiveTool('all', this)">
                <div class="cmap-tool-icon" style="background:linear-gradient(135deg,#64748b,#94a3b8);">
                    <i class="fas fa-th"></i>
                </div>
                <span>All Tools</span>
                <span class="cmap-tool-count" id="toolCount_all"><?= $totalLinks ?></span>
            </button>
            <?php foreach ($tools as $t): ?>
            <button class="cmap-tool-btn" id="toolBtn_<?= $t['id'] ?>" onclick="setActiveTool(<?= $t['id'] ?>, this)" data-name="<?= strtolower(htmlspecialchars($t['name'])) ?>">
                <div class="cmap-tool-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <span><?= htmlspecialchars($t['name']) ?></span>
                <span class="cmap-tool-count" id="toolCount_<?= $t['id'] ?>"><?= $toolCounts[$t['id']] ?? 0 ?></span>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── CENTER: Matrix ─────────────────────────────── -->
    <div class="cmap-matrix-panel">
        <div class="cmap-matrix-head">
            <h2 id="matrixTitle"><i class="fas fa-link"></i> All Compatibility Links</h2>
            <div class="cmap-search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="productSearch" placeholder="Search products..." oninput="filterProducts(this.value)">
            </div>
        </div>
        <div class="cmap-matrix-body" id="matrixBody">
            <?php if (empty($products)): ?>
            <div class="cmap-empty">
                <i class="fas fa-box-open"></i>
                <p>No active products found.<br>Add products first.</p>
            </div>
            <?php else: ?>
            <?php foreach ($byCategory as $catName => $catProducts): ?>
            <div class="cmap-cat-group" data-cat="<?= strtolower(htmlspecialchars($catName)) ?>">
                <div class="cmap-cat-title">
                    <i class="fas fa-layer-group" style="color:var(--primary);font-size:0.65rem;"></i>
                    <?= htmlspecialchars($catName) ?>
                    <span style="font-size:0.65rem;color:var(--text-muted);font-weight:500;"><?= count($catProducts) ?> items</span>
                </div>
                <?php foreach ($catProducts as $p):
                    $linked = $prodCompat[$p['id']] ?? [];
                    $hasAny = !empty($linked);
                ?>
                <div class="cmap-product-row <?= $hasAny ? 'has-match' : '' ?>" id="prow_<?= $p['id'] ?>" data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>" data-sku="<?= strtolower(htmlspecialchars($p['sku'] ?? '')) ?>">
                    <div class="cmap-product-info">
                        <div class="cmap-product-name"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="cmap-product-sku"><?= htmlspecialchars($p['sku'] ?? '') ?></div>
                    </div>
                    <div class="cmap-tool-chips" id="chips_<?= $p['id'] ?>">
                        <?php foreach ($tools as $t):
                            $isLinked = in_array($t['id'], $linked);
                        ?>
                        <span class="cmap-chip <?= $isLinked ? 'linked' : '' ?>"
                              id="chip_<?= $p['id'] ?>_<?= $t['id'] ?>"
                              data-pid="<?= $p['id'] ?>"
                              data-tid="<?= $t['id'] ?>"
                              data-tname="<?= strtolower(htmlspecialchars($t['name'])) ?>"
                              onclick="toggleChip(this)"
                              title="<?= htmlspecialchars($t['name']) ?>">
                            <?= strlen($t['name']) > 10 ? substr(htmlspecialchars($t['name']), 0, 9) . '…' : htmlspecialchars($t['name']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <span class="cmap-save-indicator" id="save_<?= $p['id'] ?>"></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <div class="cmap-empty hidden-row" id="noResults" style="display:none;">
                <i class="fas fa-search"></i>
                <p>No products match your search.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── RIGHT: Summary Panel ───────────────────────── -->
    <div class="cmap-summary-panel">
        <div class="cmap-panel-head" style="padding:1rem 1.1rem 0.75rem;border-bottom:1px solid var(--border-color);">
            <h3><i class="fas fa-chart-pie"></i> Coverage Stats</h3>
        </div>
        <div class="cmap-stat-block">
            <div class="cmap-stat-num" id="statLinks"><?= $totalLinks ?></div>
            <div class="cmap-stat-label">Total Links</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid var(--border-color);">
            <div class="cmap-stat-block" style="border-bottom:none;border-right:1px solid var(--border-color);">
                <div class="cmap-stat-num" style="font-size:1.4rem;color:var(--accent, #06b6d4);"><?= $totalProducts ?></div>
                <div class="cmap-stat-label">Products</div>
            </div>
            <div class="cmap-stat-block" style="border-bottom:none;">
                <div class="cmap-stat-num" style="font-size:1.4rem;color:#7c3aed;"><?= $totalTools ?></div>
                <div class="cmap-stat-label">Tools</div>
            </div>
        </div>
        <!-- Coverage meter -->
        <?php
        $mappedProducts = 0;
        foreach ($products as $p) { if (!empty($prodCompat[$p['id']])) $mappedProducts++; }
        $coverage = $totalProducts > 0 ? round(($mappedProducts / $totalProducts) * 100) : 0;
        ?>
        <div class="cmap-meter-wrap">
            <div class="cmap-meter-label">
                <span>Product Coverage</span>
                <span id="coveragePct"><?= $coverage ?>%</span>
            </div>
            <div class="cmap-meter-bar">
                <div class="cmap-meter-fill" id="coverageFill" style="width:<?= $coverage ?>%"></div>
            </div>
            <div style="font-size:0.7rem;color:var(--text-muted);">
                <span id="mappedCount"><?= $mappedProducts ?></span> of <?= $totalProducts ?> products mapped
            </div>
        </div>
        <!-- Active Tool Detail -->
        <div class="cmap-active-tool-info">
            <h4>Selected Tool</h4>
            <div id="activeToolDetail">
                <div class="cmap-tips">
                    <strong>How to use:</strong><br>
                    1. Click a tool on the left to filter.<br>
                    2. Toggle colored chips on each product row.<br>
                    3. Links save <strong>automatically</strong> — no button needed.
                </div>
            </div>
        </div>
    </div>

</div><!-- .cmap-shell -->
</div><!-- .page-body -->

<!-- Toast notification -->
<div id="cmapToast"><i class="fas fa-check-circle"></i> <span id="toastMsg">Saved!</span></div>

<script>
// ── State ─────────────────────────────────────────────────
const toolData = <?= json_encode(array_values($tools)) ?>;
const toolCounts = <?= json_encode($toolCounts) ?>;
let activeTool = 'all';
let saveTimer = null;
let pendingSaves = {}; // pid => timeout

// ── Tool filter ───────────────────────────────────────────
function filterTools(q) {
    document.querySelectorAll('.cmap-tool-btn[data-name]').forEach(btn => {
        btn.style.display = btn.dataset.name.includes(q.toLowerCase()) ? '' : 'none';
    });
}

// ── Set active tool (left panel) ─────────────────────────
function setActiveTool(toolId, btn) {
    activeTool = toolId;
    // Update buttons
    document.querySelectorAll('.cmap-tool-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // Highlight chips & filter rows
    const chips = document.querySelectorAll('.cmap-chip');
    chips.forEach(chip => {
        chip.classList.remove('highlighted');
        if (toolId !== 'all' && parseInt(chip.dataset.tid) === parseInt(toolId)) {
            chip.classList.add('highlighted');
        }
    });

    // Update title
    const titleEl = document.getElementById('matrixTitle');
    if (toolId === 'all') {
        titleEl.innerHTML = '<i class="fas fa-link"></i> All Compatibility Links';
        updateActiveToolDetail(null);
    } else {
        const tool = toolData.find(t => t.id == toolId);
        const count = parseInt(document.getElementById('toolCount_' + toolId).textContent) || 0;
        titleEl.innerHTML = `<i class="fas fa-tools"></i> ${tool ? tool.name : ''} <span style="font-size:0.78rem;color:var(--text-muted);font-weight:400;">&nbsp;— ${count} compatible products</span>`;
        updateActiveToolDetail(tool, count);
    }

    // Filter product rows to show only those with/without this tool if needed
    filterProducts(document.getElementById('productSearch').value);
}

function updateActiveToolDetail(tool, count) {
    const el = document.getElementById('activeToolDetail');
    if (!tool) {
        el.innerHTML = `<div class="cmap-tips"><strong>How to use:</strong><br>1. Click a tool on the left to filter.<br>2. Toggle colored chips on each product row.<br>3. Links save <strong>automatically</strong> — no button needed.</div>`;
        return;
    }
    el.innerHTML = `
        <div class="cmap-detail-tool-card">
            <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,var(--primary),#7c3aed);display:flex;align-items:center;justify-content:center;margin:0 auto 0.5rem;"><i class="fas fa-tools" style="color:#fff;font-size:1rem;"></i></div>
            <div class="cmap-detail-tool-name">${tool.name}</div>
            ${tool.model ? `<div style="font-size:0.7rem;color:var(--text-muted);">${tool.model}</div>` : ''}
            <div style="margin-top:0.75rem;">
                <div class="cmap-detail-tool-compat">${count}</div>
                <div class="cmap-detail-tool-compat-label">compatible products</div>
            </div>
        </div>
        <div class="cmap-tips" style="margin-top:0.75rem;">
            Showing highlighted chips for <strong>${tool.name}</strong>. Click any chip to toggle compatibility.
        </div>
    `;
}

// ── Product search filter ─────────────────────────────────
function filterProducts(q) {
    q = q.toLowerCase();
    let visible = 0;
    document.querySelectorAll('.cmap-product-row').forEach(row => {
        const nameMatch = row.dataset.name.includes(q);
        const skuMatch  = row.dataset.sku.includes(q);
        // Also filter by active tool if not 'all'
        let toolMatch = true;
        if (activeTool !== 'all') {
            const chip = row.querySelector(`.cmap-chip[data-tid="${activeTool}"]`);
            // We show ALL products when a tool is selected, not just linked ones
            // But we can mark them. toolMatch stays true.
        }
        const show = (nameMatch || skuMatch) && toolMatch;
        row.classList.toggle('hidden-row', !show);
        if (show) visible++;
    });

    // Hide empty category groups
    document.querySelectorAll('.cmap-cat-group').forEach(group => {
        const anyVisible = group.querySelectorAll('.cmap-product-row:not(.hidden-row)').length > 0;
        group.style.display = anyVisible ? '' : 'none';
    });

    document.getElementById('noResults').style.display = (visible === 0) ? 'flex' : 'none';
}

// ── Toggle a chip (link/unlink) ───────────────────────────
function toggleChip(chip) {
    const pid = parseInt(chip.dataset.pid);
    const tid = parseInt(chip.dataset.tid);
    const isLinked = chip.classList.contains('linked');

    // Toggle UI immediately
    chip.classList.toggle('linked', !isLinked);

    // Update row highlight
    const row = document.getElementById('prow_' + pid);
    const anyLinked = row.querySelectorAll('.cmap-chip.linked').length > 0;
    row.classList.toggle('has-match', anyLinked);

    // Update tool count in sidebar
    const countEl = document.getElementById('toolCount_' + tid);
    if (countEl) {
        let c = parseInt(countEl.textContent) || 0;
        c = isLinked ? c - 1 : c + 1;
        countEl.textContent = Math.max(0, c);
    }

    // Update total links
    const statEl = document.getElementById('statLinks');
    if (statEl) {
        let c = parseInt(statEl.textContent) || 0;
        c = isLinked ? c - 1 : c + 1;
        statEl.textContent = Math.max(0, c);
    }

    // Update coverage
    updateCoverage();

    // Show saving indicator
    const saveEl = document.getElementById('save_' + pid);
    saveEl.textContent = '⏳ saving…';
    saveEl.className = 'cmap-save-indicator saving show';

    // Debounce save per product
    if (pendingSaves[pid]) clearTimeout(pendingSaves[pid]);
    pendingSaves[pid] = setTimeout(() => saveProduct(pid), 600);
}

// ── AJAX save ─────────────────────────────────────────────
function saveProduct(pid) {
    const row = document.getElementById('prow_' + pid);
    const linkedChips = row.querySelectorAll('.cmap-chip.linked');
    const tids = Array.from(linkedChips).map(c => c.dataset.tid);

    const body = new URLSearchParams();
    body.append('ajax_save', '1');
    body.append('product_id', pid);
    tids.forEach(t => body.append('tool_ids[]', t));

    fetch('compatibility.php', { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            const saveEl = document.getElementById('save_' + pid);
            if (data.ok) {
                saveEl.textContent = '✓ saved';
                saveEl.className = 'cmap-save-indicator show';
                showToast('Link updated!', false);
            } else {
                saveEl.textContent = '✗ error';
                saveEl.className = 'cmap-save-indicator error show';
                showToast('Save failed', true);
            }
            setTimeout(() => { saveEl.className = 'cmap-save-indicator'; }, 2500);
        })
        .catch(() => {
            showToast('Network error', true);
        });
}

// ── Coverage stats ────────────────────────────────────────
function updateCoverage() {
    const allRows = document.querySelectorAll('.cmap-product-row');
    const total = allRows.length;
    let mapped = 0;
    allRows.forEach(row => {
        if (row.querySelectorAll('.cmap-chip.linked').length > 0) mapped++;
    });
    const pct = total > 0 ? Math.round((mapped / total) * 100) : 0;
    const fillEl = document.getElementById('coverageFill');
    const pctEl  = document.getElementById('coveragePct');
    const cntEl  = document.getElementById('mappedCount');
    if (fillEl) fillEl.style.width = pct + '%';
    if (pctEl)  pctEl.textContent = pct + '%';
    if (cntEl)  cntEl.textContent = mapped;
}

// ── Toast notification ────────────────────────────────────
let toastTimer;
function showToast(msg, isError = false) {
    const toast = document.getElementById('cmapToast');
    const msgEl = document.getElementById('toastMsg');
    msgEl.textContent = msg;
    toast.className = isError ? 'error show' : 'show';
    toast.querySelector('i').className = isError ? 'fas fa-times-circle' : 'fas fa-check-circle';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.className = ''; }, 2200);
}

// ── Init active tool button count from current all-tools count ──
document.getElementById('toolCount_all').textContent = <?= $totalLinks ?>;
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
