<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle = 'Dashboard';
$pageIcon  = 'fas fa-chart-pie';
$activePage = 'dashboard';
include BASE_PATH . '/includes/header.php';
$db = getDB();

// ---- Key Metrics ----
$totalProducts = $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$totalTools    = $db->query("SELECT COUNT(*) FROM tools WHERE status='active'")->fetchColumn();
$totalCats     = $db->query("SELECT COUNT(*) FROM categories WHERE status='active'")->fetchColumn();
$lowStockItems = $db->query("SELECT COUNT(*) FROM products WHERE quantity <= min_stock AND status='active'")->fetchColumn();
$totalStockValue = $db->query("SELECT COALESCE(SUM(price * quantity),0) FROM products WHERE status='active'")->fetchColumn();
$outOfStock    = $db->query("SELECT COUNT(*) FROM products WHERE quantity = 0 AND status='active'")->fetchColumn();

// ---- B2B Metrics (safe with INFORMATION_SCHEMA check) ----
$b2bTables = $db->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('customers','rfqs','orders','enquiries')")->fetchAll(PDO::FETCH_COLUMN);
$pendingCustomers = in_array('customers',$b2bTables) ? $db->query("SELECT COUNT(*) FROM customers WHERE status='pending'")->fetchColumn() : 0;
$pendingRFQs_cnt  = in_array('rfqs',$b2bTables)      ? $db->query("SELECT COUNT(*) FROM rfqs WHERE status IN ('submitted','reviewing')")->fetchColumn() : 0;
$b2bRevenue       = in_array('orders',$b2bTables)    ? $db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status NOT IN ('cancelled')")->fetchColumn() : 0;
$newEnquiries     = in_array('enquiries',$b2bTables) ? $db->query("SELECT COUNT(*) FROM enquiries WHERE status='new'")->fetchColumn() : 0;


// ---- Recent Stock Logs ----
$recentLogs = $db->query("
    SELECT sl.*, p.name AS product_name, u.name AS user_name, sl.type
    FROM stock_logs sl
    JOIN products p ON sl.product_id = p.id
    JOIN users u ON sl.user_id = u.id
    ORDER BY sl.created_at DESC LIMIT 8
")->fetchAll();

// ---- Low Stock Products ----
$lowStockProducts = $db->query("
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.quantity <= p.min_stock AND p.status='active'
    ORDER BY p.quantity ASC LIMIT 6
")->fetchAll();

// ---- Chart Data: Stock by Category ----
$catChartData = $db->query("
    SELECT c.name, COALESCE(SUM(p.quantity),0) AS total_qty
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.status='active'
    WHERE c.status='active'
    GROUP BY c.id, c.name
")->fetchAll();

// ---- Chart Data: Monthly Stock In/Out (last 6 months) ----
$monthlyData = $db->query("
    SELECT 
        DATE_FORMAT(created_at, '%b %Y') AS month,
        SUM(CASE WHEN type='in' THEN quantity ELSE 0 END) AS stock_in,
        SUM(CASE WHEN type='out' THEN quantity ELSE 0 END) AS stock_out
    FROM stock_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY YEAR(created_at), MONTH(created_at)
")->fetchAll();
?>

<div class="page-body">

<!-- ---- Stats ---- -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-boxes"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($totalProducts) ?></div>
            <div class="stat-label">Total Products</div>
            <div class="stat-change up"><i class="fas fa-arrow-up"></i> Active items</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-tools"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($totalTools) ?></div>
            <div class="stat-label">Power Tools</div>
            <div class="stat-change up"><i class="fas fa-check"></i> <?= $totalCats ?> categories</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($lowStockItems) ?></div>
            <div class="stat-label">Low Stock Alerts</div>
            <div class="stat-change down"><i class="fas fa-arrow-down"></i> <?= $outOfStock ?> out of stock</div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-indian-rupee-sign"></i></div>
        <div class="stat-info">
            <div class="stat-value" style="font-size:1.4rem;"><?= formatCurrency($totalStockValue) ?></div>
            <div class="stat-label">Total Stock Value</div>
            <div class="stat-change up"><i class="fas fa-chart-line"></i> Inventory worth</div>
        </div>
    </div>
</div>

<!-- ---- B2B Portal Stats ---- -->
<div class="card" style="margin-bottom:1.25rem;border-top:3px solid var(--primary);overflow:hidden;">
    <div class="card-header" style="background:linear-gradient(135deg,rgba(37,99,235,0.05),rgba(99,102,241,0.05));">
        <div class="card-title"><i class="fas fa-store" style="color:var(--primary);"></i> B2B Portal Activity</div>
        <a href="<?= APP_URL ?>/portal/home.php" target="_blank" style="font-size:0.78rem;color:var(--primary);display:flex;align-items:center;gap:0.3rem;">
            <i class="fas fa-external-link-alt"></i> Open Portal
        </a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0;">
        <?php foreach ([
            ['Pending Approvals', $pendingCustomers, 'fas fa-user-clock',       '#ef4444', 'customers_b2b.php?status=pending'],
            ['Pending RFQs',      $pendingRFQs_cnt,  'fas fa-file-invoice',     '#f97316', 'rfq_manager.php'],
            ['B2B Revenue',       '₹'.number_format($b2bRevenue,0), 'fas fa-indian-rupee-sign','#16a34a','sales_report.php'],
            ['New Enquiries',     $newEnquiries,     'fas fa-envelope',         '#6366f1', 'enquiries.php'],
        ] as [$label, $val, $icon, $color, $link]):
        ?>
        <a href="<?= APP_URL ?>/pages/<?= $link ?>" style="padding:1.1rem 1.25rem;border-right:1px solid var(--border-color);display:flex;align-items:center;gap:0.85rem;text-decoration:none;transition:background 0.2s;" onmouseover="this.style.background='var(--bg-card2)'" onmouseout="this.style.background=''">
            <div style="width:38px;height:38px;border-radius:9px;background:<?= $color ?>15;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="<?= $icon ?>" style="color:<?= $color ?>;font-size:0.9rem;"></i>
            </div>
            <div>
                <div style="font-size:1.2rem;font-weight:800;color:var(--text-primary);"><?= $val ?><?= (is_numeric($val) && $val > 0) ? '<span style="width:8px;height:8px;border-radius:50%;background:'.$color.';display:inline-block;margin-left:5px;"></span>' : '' ?></div>
                <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;"><?= $label ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- ---- Charts Row ---- -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;">

    <!-- Monthly Activity Chart -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-chart-bar"></i> Stock Activity (6 Months)</div>
        </div>
        <div class="card-body" style="padding:1rem;">
            <canvas id="activityChart" height="200"></canvas>
        </div>
    </div>

    <!-- Category Distribution -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-chart-doughnut"></i> Stock by Category</div>
        </div>
        <div class="card-body" style="padding:1rem;">
            <canvas id="categoryChart" height="200"></canvas>
        </div>
    </div>
</div>

<!-- ---- Low Stock + Recent Logs ---- -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">

    <!-- Low Stock Alert Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-exclamation-triangle" style="color:var(--warning)"></i> Low Stock Items</div>
            <a href="pages/stock.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <?php if (empty($lowStockProducts)): ?>
            <div class="empty-state"><i class="fas fa-check-circle" style="color:var(--success)"></i><h3>All stock levels are healthy!</h3></div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Product</th><th>Category</th><th>Stock</th></tr></thead>
                <tbody>
                <?php foreach ($lowStockProducts as $p):
                    $pct = $p['min_stock'] > 0 ? min(100, ($p['quantity'] / $p['min_stock']) * 100) : 0;
                    $cls = $p['quantity'] == 0 ? 'stock-zero' : 'stock-low';
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:0.85rem;"><?= htmlspecialchars($p['name']) ?></div>
                        <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($p['sku']) ?></div>
                    </td>
                    <td><span class="badge-pill badge-info"><?= htmlspecialchars($p['category_name']) ?></span></td>
                    <td>
                        <div class="stock-bar-wrap <?= $cls ?>">
                            <div class="stock-bar"><div class="stock-bar-fill" style="width:<?= $pct ?>%"></div></div>
                            <span class="stock-qty" style="color:<?= $p['quantity']==0 ? 'var(--danger)' : 'var(--warning)' ?>">
                                <?= $p['quantity'] ?>
                            </span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Activity Log -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-clock"></i> Recent Activity</div>
            <a href="pages/stock.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <?php if (empty($recentLogs)): ?>
            <div class="empty-state"><i class="fas fa-inbox"></i><h3>No activity yet</h3></div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Product</th><th>Type</th><th>Qty</th><th>By</th><th>When</th></tr></thead>
                <tbody>
                <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td style="max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:0.82rem;">
                        <?= htmlspecialchars($log['product_name']) ?>
                    </td>
                    <td>
                        <?php if ($log['type'] === 'in'): ?>
                            <span class="badge-pill badge-success"><i class="fas fa-arrow-down"></i> In</span>
                        <?php else: ?>
                            <span class="badge-pill badge-warning"><i class="fas fa-arrow-up"></i> Out</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-weight:700;font-size:0.85rem;"><?= $log['quantity'] ?></td>
                    <td style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($log['user_name']) ?></td>
                    <td style="font-size:0.75rem;color:var(--text-muted);white-space:nowrap;">
                        <?= date('d M, h:ia', strtotime($log['created_at'])) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

</div><!-- .page-body -->

<script>
// Monthly Activity Chart
const monthlyLabels = <?= json_encode(array_column($monthlyData, 'month')) ?>;
const stockIn  = <?= json_encode(array_column($monthlyData, 'stock_in')) ?>;
const stockOut = <?= json_encode(array_column($monthlyData, 'stock_out')) ?>;

new Chart(document.getElementById('activityChart'), {
    type: 'bar',
    data: {
        labels: monthlyLabels.length ? monthlyLabels : ['No data'],
        datasets: [
            {
                label: 'Stock In',
                data: stockIn.length ? stockIn : [0],
                backgroundColor: 'rgba(34,197,94,0.7)',
                borderRadius: 6,
            },
            {
                label: 'Stock Out',
                data: stockOut.length ? stockOut : [0],
                backgroundColor: 'rgba(245,158,11,0.7)',
                borderRadius: 6,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
            x: { grid: { display: false } }
        }
    }
});

// Category Doughnut Chart
const catLabels = <?= json_encode(array_column($catChartData, 'name')) ?>;
const catQtys   = <?= json_encode(array_column($catChartData, 'total_qty')) ?>;
const catColors = ['#6c63ff','#48daf5','#22c55e','#f59e0b','#ef4444','#3b82f6'];

new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: catLabels,
        datasets: [{
            data: catQtys,
            backgroundColor: catColors,
            borderWidth: 2,
            borderColor: '#fff',
            hoverOffset: 8,
        }]
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: {
            legend: { position: 'right', labels: { font: { size: 11 }, padding: 12 } }
        }
    }
});
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
