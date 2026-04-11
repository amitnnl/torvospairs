<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle  = 'Stock Management';
$pageIcon   = 'fas fa-warehouse';
$activePage = 'stock';
$pageBreadcrumb = 'Stock Management';
include BASE_PATH . '/includes/header.php';

$db = getDB();

// ---- Handle Stock In/Out ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity   = (int)($_POST['quantity'] ?? 0);
    $type       = in_array($_POST['type']??'', ['in','out']) ? $_POST['type'] : null;
    $notes      = sanitize($_POST['notes'] ?? '');

    if ($action === 'stock_update' && $product_id && $quantity > 0 && $type) {
        $prod = $db->prepare("SELECT quantity FROM products WHERE id=?");
        $prod->execute([$product_id]);
        $current = (int)$prod->fetchColumn();

        if ($type === 'out' && $quantity > $current) {
            setFlash('error', "Cannot issue $quantity units — only $current in stock.");
        } else {
            $new_qty = $type === 'in' ? $current + $quantity : $current - $quantity;
            $db->prepare("UPDATE products SET quantity=? WHERE id=?")->execute([$new_qty, $product_id]);
            $user = currentUser();
            $db->prepare("INSERT INTO stock_logs (product_id, user_id, type, quantity, previous_stock, current_stock, notes) VALUES (?,?,?,?,?,?,?)")
               ->execute([$product_id, $user['id'], $type, $quantity, $current, $new_qty, $notes]);
            setFlash('success', 'Stock ' . ($type==='in' ? 'added' : 'issued') . ' successfully. New balance: ' . $new_qty);
        }
        header('Location: stock.php'); exit;
    }
}

// ---- Filters ----
$filter = sanitize($_GET['filter'] ?? 'all'); // all | low | out
$type_f = sanitize($_GET['type'] ?? '');

$where = ['p.status = "active"'];
$params = [];
if ($filter === 'low') { $where[] = 'p.quantity > 0 AND p.quantity <= p.min_stock'; }
if ($filter === 'out') { $where[] = 'p.quantity = 0'; }
$whereStr = implode(' AND ', $where);

$products = $db->query("
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE $whereStr ORDER BY p.quantity ASC
")->fetchAll();

// ---- Stock Logs ----
$logFilter = sanitize($_GET['log_type'] ?? '');
$logWhere = '1=1';
if ($logFilter === 'in')  $logWhere = "sl.type='in'";
if ($logFilter === 'out') $logWhere = "sl.type='out'";

$logs = $db->query("
    SELECT sl.*, p.name AS product_name, p.sku, u.name AS user_name
    FROM stock_logs sl
    JOIN products p ON sl.product_id = p.id
    JOIN users u ON sl.user_id = u.id
    WHERE $logWhere
    ORDER BY sl.created_at DESC LIMIT 50
")->fetchAll();

// For product dropdown in form
$allProducts = $db->query("SELECT id, name, sku, quantity FROM products WHERE status='active' ORDER BY name")->fetchAll();
?>

<div class="page-body">

<!-- Quick Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
    <?php
    $totalItems = $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
    $lowItems   = $db->query("SELECT COUNT(*) FROM products WHERE quantity > 0 AND quantity <= min_stock AND status='active'")->fetchColumn();
    $outItems   = $db->query("SELECT COUNT(*) FROM products WHERE quantity = 0 AND status='active'")->fetchColumn();
    ?>
    <a href="stock.php?filter=all" class="stat-card blue" style="text-decoration:none;">
        <div class="stat-icon"><i class="fas fa-boxes"></i></div>
        <div class="stat-info"><div class="stat-value"><?= $totalItems ?></div><div class="stat-label">Total Products</div></div>
    </a>
    <a href="stock.php?filter=low" class="stat-card orange" style="text-decoration:none;">
        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-info"><div class="stat-value"><?= $lowItems ?></div><div class="stat-label">Low Stock Items</div></div>
    </a>
    <a href="stock.php?filter=out" class="stat-card red" style="text-decoration:none;">
        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        <div class="stat-info"><div class="stat-value"><?= $outItems ?></div><div class="stat-label">Out of Stock</div></div>
    </a>
</div>

<!-- Filter tabs + New Stock Button -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;gap:1rem;flex-wrap:wrap;">
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <?php foreach (['all'=>'All Items','low'=>'Low Stock','out'=>'Out of Stock'] as $k=>$v): ?>
        <a href="stock.php?filter=<?= $k ?>" class="btn <?= $filter===$k ? 'btn-primary' : 'btn-outline' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:0.5rem;">
        <button class="btn btn-outline" onclick="openModal('scannerModal')" title="Scan Barcode / QR Code">
            <i class="fas fa-barcode"></i> Scan
        </button>
        <button class="btn btn-success" onclick="openModal('stockModal')">
            <i class="fas fa-plus-minus"></i> Stock In / Out
        </button>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;align-items:start;">

<!-- Products Stock Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-boxes"></i> Stock Levels</div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Min</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($products as $p):
                $pct = $p['min_stock'] > 0 ? min(100, ($p['quantity']/$p['min_stock'])*100) : ($p['quantity']>0?100:0);
                $sc  = $p['quantity']==0 ? 'stock-zero' : ($p['quantity']<=$p['min_stock'] ? 'stock-low' : 'stock-ok');
            ?>
            <tr>
                <td>
                    <div style="font-weight:600;font-size:0.85rem;"><?= htmlspecialchars($p['name']) ?></div>
                    <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($p['sku']) ?></div>
                </td>
                <td><span class="badge-pill badge-info" style="font-size:0.7rem;"><?= htmlspecialchars($p['category_name']) ?></span></td>
                <td>
                    <div class="stock-bar-wrap <?= $sc ?>">
                        <div class="stock-bar"><div class="stock-bar-fill" style="width:<?= $pct ?>%"></div></div>
                        <span class="stock-qty" style="color:<?= $p['quantity']==0?'var(--danger)':($p['quantity']<=$p['min_stock']?'var(--warning)':'var(--success)') ?>"><?= $p['quantity'] ?></span>
                    </div>
                </td>
                <td style="font-size:0.8rem;color:var(--text-muted);"><?= $p['min_stock'] ?></td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick="quickStock(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', <?= $p['quantity'] ?>)">
                        <i class="fas fa-edit"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Activity Log -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-history"></i> Stock Log</div>
        <div style="display:flex;gap:0.4rem;">
            <a href="stock.php?log_type=" class="btn btn-outline btn-sm <?= !$logFilter?'btn-primary':'' ?>">All</a>
            <a href="stock.php?log_type=in" class="btn btn-sm <?= $logFilter==='in'?'btn-success':'btn-outline' ?>">In</a>
            <a href="stock.php?log_type=out" class="btn btn-sm <?= $logFilter==='out'?'btn-warning':'btn-outline' ?>">Out</a>
        </div>
    </div>
    <div class="table-wrap" style="max-height:500px;overflow-y:auto;">
        <table class="data-table">
            <thead><tr><th>Product</th><th>Type</th><th>Qty</th><th>Balance</th><th>By</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td>
                    <div style="font-size:0.82rem;font-weight:600;"><?= htmlspecialchars($log['product_name']) ?></div>
                    <div style="font-size:0.7rem;color:var(--text-muted);"><?= htmlspecialchars($log['sku']) ?></div>
                </td>
                <td>
                    <?php if ($log['type']==='in'): ?>
                        <span class="badge-pill badge-success"><i class="fas fa-arrow-alt-circle-down"></i> In</span>
                    <?php else: ?>
                        <span class="badge-pill badge-warning"><i class="fas fa-arrow-alt-circle-up"></i> Out</span>
                    <?php endif; ?>
                </td>
                <td style="font-weight:700;font-size:0.85rem;color:<?= $log['type']==='in'?'var(--success)':'var(--warning)' ?>;">
                    <?= $log['type']==='in'?'+':'-' ?><?= $log['quantity'] ?>
                </td>
                <td style="font-size:0.82rem;"><?= $log['current_stock'] ?></td>
                <td style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($log['user_name']) ?></td>
                <td style="font-size:0.75rem;color:var(--text-muted);white-space:nowrap;"><?= date('d M, h:ia', strtotime($log['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<!-- STOCK IN/OUT MODAL -->
<div class="modal-overlay" id="stockModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-exchange-alt" style="color:var(--primary)"></i> Stock In / Stock Out</div>
            <button class="modal-close" onclick="closeModal('stockModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="stock_update">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Product *</label>
                    <select name="product_id" id="stockProductId" class="form-control" required onchange="updateCurrentStock()">
                        <option value="">Select Product</option>
                        <?php foreach ($allProducts as $ap): ?>
                        <option value="<?= $ap['id'] ?>" data-qty="<?= $ap['quantity'] ?>">
                            <?= htmlspecialchars($ap['name']) ?> — SKU: <?= htmlspecialchars($ap['sku']) ?> (<?= $ap['quantity'] ?> in stock)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="currentStockInfo" style="display:none;background:var(--bg-card2);border:1px solid var(--border-color);border-radius:var(--radius-sm);padding:0.75rem;margin-bottom:1rem;font-size:0.85rem;">
                    Current Stock: <strong id="currentStockVal" style="color:var(--primary);">—</strong>
                </div>
                <div class="form-group">
                    <label class="form-label">Transaction Type *</label>
                    <div style="display:flex;gap:0.75rem;">
                        <label style="flex:1;display:flex;align-items:center;gap:0.5rem;padding:0.75rem;border:1.5px solid #22c55e;border-radius:var(--radius-sm);cursor:pointer;font-weight:600;color:#16a34a;background:rgba(34,197,94,0.05);">
                            <input type="radio" name="type" value="in" required> <i class="fas fa-arrow-alt-circle-down"></i> Stock In
                        </label>
                        <label style="flex:1;display:flex;align-items:center;gap:0.5rem;padding:0.75rem;border:1.5px solid #f59e0b;border-radius:var(--radius-sm);cursor:pointer;font-weight:600;color:#d97706;background:rgba(245,158,11,0.05);">
                            <input type="radio" name="type" value="out" required> <i class="fas fa-arrow-alt-circle-up"></i> Stock Out
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Quantity *</label>
                    <input type="number" name="quantity" class="form-control" min="1" placeholder="Enter quantity" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Optional note (e.g. Issued to workshop)"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('stockModal')">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Confirm</button>
            </div>
        </form>
    </div>
</div>

<!-- BARCODE SCANNER MODAL -->
<div class="modal-overlay" id="scannerModal">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-barcode" style="color:var(--primary);"></i> Scan Barcode / QR</div>
            <button class="modal-close" onclick="stopScanner();closeModal('scannerModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div style="background:var(--bg-card2);border-radius:10px;overflow:hidden;margin-bottom:1rem;">
                <div id="qr-reader" style="width:100%;"></div>
            </div>
            <div id="scanResult" style="display:none;background:rgba(22,163,74,0.08);border:1px solid rgba(22,163,74,0.2);border-radius:8px;padding:0.75rem 1rem;font-size:0.875rem;">
                <i class="fas fa-check-circle" style="color:var(--success);"></i>
                Scanned: <strong id="scanResultText"></strong>
            </div>
            <p style="font-size:0.78rem;color:var(--text-muted);text-align:center;margin-top:0.75rem;">
                <i class="fas fa-info-circle"></i> Point camera at the product barcode or SKU QR code.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="stopScanner();closeModal('scannerModal')">Close</button>
            <button type="button" class="btn btn-primary" id="scanToStock" style="display:none;" onclick="scanToStockModal()">
                <i class="fas fa-plus-minus"></i> Open Stock Form
            </button>
        </div>
    </div>
</div>

</div><!-- .page-body -->

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
// Products lookup (SKU → ID)
const skuMap = <?= json_encode(array_column($allProducts, 'id', 'sku')) ?>;
const skuNameMap = <?= json_encode(array_column($allProducts, 'name', 'sku')) ?>;

let html5QrcodeScanner = null;
let lastScannedSku = null;

function openModal(id) {
    document.getElementById(id).classList.add('active');
    if (id === 'scannerModal') startScanner();
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}
function startScanner() {
    if (html5QrcodeScanner) return;
    html5QrcodeScanner = new Html5QrcodeScanner('qr-reader', { qrbox: { width: 250, height: 180 }, fps: 10, rememberLastUsedCamera: true });
    html5QrcodeScanner.render(onScanSuccess, onScanError);
}
function stopScanner() {
    if (html5QrcodeScanner) {
        html5QrcodeScanner.clear().catch(() => {});
        html5QrcodeScanner = null;
    }
}
function onScanSuccess(text) {
    lastScannedSku = text.trim();
    document.getElementById('scanResultText').textContent = lastScannedSku;
    document.getElementById('scanResult').style.display = 'block';
    document.getElementById('scanToStock').style.display = 'inline-flex';
    stopScanner();
}
function onScanError() {}
function scanToStockModal() {
    stopScanner();
    closeModal('scannerModal');
    // Try to find product by SKU
    const pid = skuMap[lastScannedSku];
    if (pid) {
        document.getElementById('stockProductId').value = pid;
        updateCurrentStock();
        setTimeout(() => openModal('stockModal'), 200);
    } else {
        alert('Product not found for SKU: ' + lastScannedSku + '\nPlease select manually.');
        setTimeout(() => openModal('stockModal'), 200);
    }
}

function updateCurrentStock() {
    const sel = document.getElementById('stockProductId');
    const opt = sel.selectedOptions[0];
    const info = document.getElementById('currentStockInfo');
    if (opt && opt.dataset.qty !== undefined) {
        document.getElementById('currentStockVal').textContent = opt.dataset.qty;
        info.style.display = 'block';
    } else {
        info.style.display = 'none';
    }
}

function quickStock(pid, name, qty) {
    document.getElementById('stockProductId').value = pid;
    updateCurrentStock();
    openModal('stockModal');
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
