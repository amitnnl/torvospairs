<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle  = 'Compatibility Map';
$pageIcon   = 'fas fa-link';
$activePage = 'compatibility';
$pageBreadcrumb = 'Compatibility Mapping';
include BASE_PATH . '/includes/header.php';

$db = getDB();

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $tool_ids   = $_POST['tool_ids'] ?? [];

    if ($product_id > 0) {
        $db->prepare("DELETE FROM product_compatibility WHERE product_id=?")->execute([$product_id]);
        if (!empty($tool_ids)) {
            $ins = $db->prepare("INSERT IGNORE INTO product_compatibility (product_id, tool_id) VALUES (?,?)");
            foreach ($tool_ids as $tid) $ins->execute([$product_id, (int)$tid]);
        }
        setFlash('success', 'Compatibility mapping updated successfully.');
        header('Location: compatibility.php'); exit;
    }
}

$selectedTool = (int)($_GET['tool'] ?? 0);
$searchQ      = sanitize($_GET['search'] ?? '');

$tools    = $db->query("SELECT * FROM tools WHERE status='active' ORDER BY name")->fetchAll();
$products = $db->query("SELECT p.*, c.name AS cat_name FROM products p JOIN categories c ON p.category_id=c.id WHERE p.status='active' ORDER BY p.name")->fetchAll();

// For the map view
$mapWhere = '1=1';
$mapParams = [];
if ($selectedTool) { $mapWhere = "pc.tool_id=?"; $mapParams[] = $selectedTool; }
if ($searchQ) { $mapWhere .= " AND p.name LIKE ?"; $mapParams[] = "%$searchQ%"; }

$compatMap = $db->prepare("
    SELECT p.id AS pid, p.name AS pname, p.sku, c.name AS cat,
           t.id AS tid, t.name AS tname
    FROM product_compatibility pc
    JOIN products p ON pc.product_id = p.id
    JOIN tools t ON pc.tool_id = t.id
    JOIN categories c ON p.category_id = c.id
    WHERE $mapWhere
    ORDER BY t.name, p.name
");
$compatMap->execute($mapParams);
$mapRows = $compatMap->fetchAll();

// Group by tool
$groupedByTool = [];
foreach ($mapRows as $row) {
    $groupedByTool[$row['tname']][] = $row;
}

// Each product's current tool IDs
$prodCompatStmt = $db->query("SELECT product_id, tool_id FROM product_compatibility");
$prodCompat = [];
foreach ($prodCompatStmt->fetchAll() as $r) {
    $prodCompat[$r['product_id']][] = $r['tool_id'];
}
?>

<div class="page-body">

<!-- Filter -->
<div class="filter-bar">
    <div class="filter-group" style="flex:1;">
        <i class="fas fa-search" style="color:var(--text-muted);"></i>
        <input type="text" id="searchInput" class="form-control" placeholder="Search product..." value="<?= htmlspecialchars($searchQ) ?>" onkeypress="if(event.key==='Enter')applyFilter()">
    </div>
    <div class="filter-group">
        <select id="toolFilter" class="form-control" onchange="applyFilter()">
            <option value="">All Tools</option>
            <?php foreach ($tools as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $selectedTool==$t['id']?'selected':'' ?>><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn-primary" onclick="applyFilter()"><i class="fas fa-filter"></i> Filter</button>
    <a href="compatibility.php" class="btn btn-outline">Reset</a>
    <button class="btn btn-success" onclick="openModal('mapModal')"><i class="fas fa-plus"></i> Map Product</button>
</div>

<!-- Compatibility Matrix -->
<?php if (empty($groupedByTool)): ?>
    <div class="card"><div class="empty-state"><i class="fas fa-link"></i><h3>No compatibility mappings found</h3><p>Use the "Map Product" button to link products to tools.</p></div></div>
<?php else: ?>
    <?php foreach ($groupedByTool as $toolName => $items): ?>
    <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-tools"></i><?= htmlspecialchars($toolName) ?>
                <span style="margin-left:0.5rem;font-size:0.8rem;color:var(--text-muted);font-weight:400;"><?= count($items) ?> compatible products</span>
            </div>
            <?php $tool_id_for_btn = $items[0]['tid'] ?? 0; ?>
            <a href="../pages/products.php?tool=<?= $tool_id_for_btn ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-eye"></i> View Products
            </a>
        </div>
        <div class="card-body">
            <div style="display:flex;flex-wrap:wrap;gap:0.75rem;">
            <?php foreach ($items as $item): ?>
            <div style="display:flex;align-items:center;gap:0.5rem;background:var(--bg-card2);border:1px solid var(--border-color);border-radius:var(--radius-sm);padding:0.5rem 0.85rem;font-size:0.82rem;">
                <i class="fas fa-box" style="color:var(--primary);"></i>
                <div>
                    <div style="font-weight:600;"><?= htmlspecialchars($item['pname']) ?></div>
                    <div style="font-size:0.7rem;color:var(--text-muted);"><?= htmlspecialchars($item['sku']) ?> · <?= htmlspecialchars($item['cat']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- MAP PRODUCT MODAL -->
<div class="modal-overlay" id="mapModal">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-link" style="color:var(--primary)"></i> Map Product to Tools</div>
            <button class="modal-close" onclick="closeModal('mapModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Product *</label>
                    <select name="product_id" id="mapProductId" class="form-control" required onchange="loadExistingMap()">
                        <option value="">Select a product...</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" data-compat='<?= json_encode($prodCompat[$p['id']] ?? []) ?>'>
                            <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['sku']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Compatible Tools (select all that apply)</label>
                    <div class="check-group" id="mapToolsGroup">
                        <?php foreach ($tools as $t): ?>
                        <label class="check-item" id="mapTool_<?= $t['id'] ?>">
                            <input type="checkbox" name="tool_ids[]" value="<?= $t['id'] ?>">
                            <i class="fas fa-tools"></i> <?= htmlspecialchars($t['name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('mapModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Mapping</button>
            </div>
        </form>
    </div>
</div>

</div>

<script>
function applyFilter() {
    const s = document.getElementById('searchInput').value;
    const t = document.getElementById('toolFilter').value;
    window.location.href = `compatibility.php?search=${encodeURIComponent(s)}&tool=${t}`;
}

document.querySelectorAll('.check-item').forEach(item => {
    const cb = item.querySelector('input[type="checkbox"]');
    if (cb) cb.addEventListener('change', () => item.classList.toggle('checked', cb.checked));
});

function loadExistingMap() {
    const sel = document.getElementById('mapProductId');
    const opt = sel.selectedOptions[0];
    // Reset all
    document.querySelectorAll('#mapToolsGroup .check-item').forEach(item => {
        item.querySelector('input').checked = false;
        item.classList.remove('checked');
    });
    if (opt && opt.dataset.compat) {
        const ids = JSON.parse(opt.dataset.compat);
        ids.forEach(id => {
            const el = document.getElementById('mapTool_' + id);
            if (el) { el.querySelector('input').checked = true; el.classList.add('checked'); }
        });
    }
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
