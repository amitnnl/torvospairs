<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle      = 'Product Detail';
$pageIcon       = 'fas fa-box';
$activePage     = 'products';
$pageBreadcrumb = 'Products / Detail';
include BASE_PATH . '/includes/header.php';

$db  = getDB();
$pid = (int)($_GET['id'] ?? 0);

if (!$pid) { header('Location: products.php'); exit; }

$product = $db->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.id = ?
");
$product->execute([$pid]);
$p = $product->fetch();

if (!$p) { setFlash('error','Product not found.'); header('Location: products.php'); exit; }

// Compatible tools
$tools = $db->prepare("
    SELECT t.* FROM tools t
    JOIN product_compatibility pc ON t.id = pc.tool_id
    WHERE pc.product_id = ?
");
$tools->execute([$pid]);
$compatTools = $tools->fetchAll();

// Stock logs for this product
$logs = $db->prepare("
    SELECT sl.*, u.name AS user_name
    FROM stock_logs sl
    JOIN users u ON sl.user_id = u.id
    WHERE sl.product_id = ?
    ORDER BY sl.created_at DESC
    LIMIT 20
");
$logs->execute([$pid]);
$stockLogs = $logs->fetchAll();

$pct = $p['min_stock'] > 0 ? min(100, ($p['quantity'] / $p['min_stock']) * 100) : ($p['quantity'] > 0 ? 100 : 0);
$stockClass = $p['quantity'] == 0 ? 'stock-zero' : ($p['quantity'] <= $p['min_stock'] ? 'stock-low' : 'stock-ok');
?>

<div class="page-body">

<!-- Back + Actions -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.75rem;">
    <a href="products.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Products</a>
    <div style="display:flex;gap:0.5rem;">
        <a href="?id=<?= $pid ?>&print_barcode=1" target="_blank" class="btn btn-outline"><i class="fas fa-barcode"></i> Print Barcode</a>
        <a href="stock.php" class="btn btn-success"><i class="fas fa-exchange-alt"></i> Stock In/Out</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1.6fr;gap:1.25rem;align-items:start;">

    <!-- Left: Product Info Card -->
    <div>
        <!-- Image / Header -->
        <div class="card" style="margin-bottom:1.25rem;">
            <div style="background:linear-gradient(135deg,#1e1b4b,#312e81);min-height:200px;display:flex;align-items:center;justify-content:center;border-radius:var(--radius-lg) var(--radius-lg) 0 0;">
                <?php if ($p['image'] && file_exists(UPLOAD_DIR . $p['image'])): ?>
                    <img src="<?= UPLOAD_URL . htmlspecialchars($p['image']) ?>" style="max-height:160px;max-width:90%;object-fit:contain;" alt="">
                <?php else: ?>
                    <i class="fas fa-box" style="font-size:4rem;color:rgba(255,255,255,0.25);"></i>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.5rem;margin-bottom:0.75rem;">
                    <h2 style="font-size:1.15rem;font-weight:800;color:var(--text-primary);line-height:1.3;"><?= htmlspecialchars($p['name']) ?></h2>
                    <span class="badge-pill <?= $p['status']==='active'?'badge-success':'badge-gray' ?>"><?= ucfirst($p['status']) ?></span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;margin-bottom:1rem;">
                    <div style="background:var(--bg-card2);border-radius:var(--radius-sm);padding:0.75rem;">
                        <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">SKU</div>
                        <div style="font-weight:700;font-size:0.9rem;margin-top:0.2rem;"><?= htmlspecialchars($p['sku']) ?></div>
                    </div>
                    <div style="background:var(--bg-card2);border-radius:var(--radius-sm);padding:0.75rem;">
                        <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Price</div>
                        <div style="font-weight:700;font-size:0.9rem;margin-top:0.2rem;color:var(--primary);"><?= formatCurrency($p['price']) ?></div>
                    </div>
                    <div style="background:var(--bg-card2);border-radius:var(--radius-sm);padding:0.75rem;">
                        <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Brand</div>
                        <div style="font-weight:700;font-size:0.9rem;margin-top:0.2rem;"><?= htmlspecialchars($p['brand'] ?: '—') ?></div>
                    </div>
                    <div style="background:var(--bg-card2);border-radius:var(--radius-sm);padding:0.75rem;">
                        <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Category</div>
                        <div style="font-weight:700;font-size:0.9rem;margin-top:0.2rem;"><?= htmlspecialchars($p['category_name']) ?></div>
                    </div>
                </div>

                <!-- Stock Level -->
                <div style="background:var(--bg-card2);border-radius:var(--radius-sm);padding:0.85rem;margin-bottom:0.75rem;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;">
                        <span style="font-size:0.75rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Stock Level</span>
                        <span style="font-size:0.8rem;font-weight:700;color:<?= $p['quantity']==0?'var(--danger)':($p['quantity']<=$p['min_stock']?'var(--warning)':'var(--success)') ?>;">
                            <?= $p['quantity'] ?> / <?= $p['min_stock'] ?> min
                        </span>
                    </div>
                    <div class="stock-bar <?= $stockClass ?>" style="height:10px;">
                        <div class="stock-bar-fill" style="width:<?= $pct ?>%;height:10px;"></div>
                    </div>
                    <?php if ($p['quantity'] == 0): ?>
                        <div style="font-size:0.75rem;color:var(--danger);font-weight:600;margin-top:0.4rem;"><i class="fas fa-exclamation-circle"></i> OUT OF STOCK</div>
                    <?php elseif ($p['quantity'] <= $p['min_stock']): ?>
                        <div style="font-size:0.75rem;color:var(--warning);font-weight:600;margin-top:0.4rem;"><i class="fas fa-exclamation-triangle"></i> LOW STOCK</div>
                    <?php endif; ?>
                </div>

                <?php if ($p['description']): ?>
                <div style="font-size:0.875rem;color:var(--text-secondary);line-height:1.6;"><?= nl2br(htmlspecialchars($p['description'])) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Compatible Tools -->
        <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-tools"></i> Compatible With</div>
            </div>
            <div class="card-body">
                <?php if (empty($compatTools)): ?>
                    <p style="color:var(--text-muted);font-size:0.875rem;">No tool compatibility mapped.</p>
                <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:0.6rem;">
                    <?php foreach ($compatTools as $t): ?>
                    <a href="products.php?tool=<?= $t['id'] ?>" style="display:flex;align-items:center;gap:0.75rem;background:var(--bg-card2);border:1px solid var(--border-color);border-radius:var(--radius-sm);padding:0.65rem 0.9rem;text-decoration:none;transition:var(--transition);" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border-color)'">
                        <div style="width:34px;height:34px;background:linear-gradient(135deg,var(--primary),var(--accent));border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.8rem;flex-shrink:0;">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:0.875rem;color:var(--text-primary);"><?= htmlspecialchars($t['name']) ?></div>
                            <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($t['brand'] . ' ' . $t['model']) ?></div>
                        </div>
                        <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--text-muted);font-size:0.75rem;"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Barcode -->
        <?php if ($p['barcode']): ?>
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-barcode"></i> Barcode</div>
                <button onclick="printBarcode()" class="btn btn-outline btn-sm"><i class="fas fa-print"></i> Print</button>
            </div>
            <div class="card-body" style="text-align:center;">
                <svg id="barcode"></svg>
                <div style="font-size:0.8rem;color:var(--text-muted);margin-top:0.5rem;"><?= htmlspecialchars($p['barcode']) ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right: Stock Log -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-history"></i> Stock History</div>
            <div style="font-size:0.8rem;color:var(--text-muted);">Last 20 transactions</div>
        </div>
        <?php if (empty($stockLogs)): ?>
            <div class="empty-state"><i class="fas fa-inbox"></i><h3>No stock movements yet</h3><p>Stock In/Out transactions will appear here.</p></div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Date</th><th>Type</th><th>Qty</th><th>Before</th><th>After</th><th>By</th><th>Notes</th></tr>
                </thead>
                <tbody>
                <?php foreach ($stockLogs as $log): ?>
                <tr>
                    <td style="font-size:0.75rem;white-space:nowrap;"><?= date('d M Y<br>h:ia', strtotime($log['created_at'])) ?></td>
                    <td>
                        <?php if ($log['type']==='in'): ?>
                            <span class="badge-pill badge-success"><i class="fas fa-arrow-down"></i> IN</span>
                        <?php else: ?>
                            <span class="badge-pill badge-warning"><i class="fas fa-arrow-up"></i> OUT</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight:700;color:<?= $log['type']==='in'?'var(--success)':'var(--warning)' ?>;">
                        <?= $log['type']==='in'?'+':'-' ?><?= $log['quantity'] ?>
                    </td>
                    <td style="font-size:0.85rem;"><?= $log['previous_stock'] ?></td>
                    <td style="font-size:0.85rem;font-weight:600;"><?= $log['current_stock'] ?></td>
                    <td style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($log['user_name']) ?></td>
                    <td style="font-size:0.78rem;color:var(--text-muted);max-width:150px;"><?= htmlspecialchars($log['notes'] ?: '—') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mini chart: stock movement -->
        <div style="padding:1.5rem;">
            <div style="font-size:0.8rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.75rem;">
                Movement Chart
            </div>
            <canvas id="stockChart" height="130"></canvas>
        </div>
        <?php endif; ?>
    </div>
</div>

</div><!-- .page-body -->

<?php if ($p['barcode']): ?>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
JsBarcode("#barcode", "<?= htmlspecialchars($p['barcode']) ?>", {
    format: "CODE128",
    width: 2,
    height: 60,
    displayValue: true,
    fontSize: 12,
    margin: 10,
});

function printBarcode() {
    const barcodeHtml = document.getElementById('barcode').outerHTML;
    const win = window.open('', '', 'width=400,height=300');
    win.document.write(`
        <html><head><title>Barcode – <?= htmlspecialchars($p['name']) ?></title>
        <style>body{font-family:sans-serif;display:flex;flex-direction:column;align-items:center;padding:2rem;}
        h3{font-size:1rem;margin-bottom:0.5rem;} p{font-size:0.8rem;color:#666;}</style></head>
        <body>
        <h3><?= htmlspecialchars($p['name']) ?></h3>
        <p>SKU: <?= htmlspecialchars($p['sku']) ?></p>
        ${barcodeHtml}
        <script>window.print();<\/script>
        </body></html>
    `);
    win.document.close();
}
</script>
<?php endif; ?>

<?php if (!empty($stockLogs)):
    $chartLabels = [];
    $chartBalances = [];
    $logsReversed = array_reverse($stockLogs);
    foreach ($logsReversed as $l) {
        $chartLabels[]   = date('d M', strtotime($l['created_at']));
        $chartBalances[] = $l['current_stock'];
    }
?>
<script>
new Chart(document.getElementById('stockChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Stock Balance',
            data: <?= json_encode($chartBalances) ?>,
            borderColor: '#6c63ff',
            backgroundColor: 'rgba(108,99,255,0.08)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#6c63ff',
            pointRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
            x: { grid: { display: false } }
        }
    }
});
</script>
<?php endif; ?>

<?php include BASE_PATH . '/includes/footer.php'; ?>
