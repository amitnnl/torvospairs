<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle      = 'B2B Sales Analytics';
$pageIcon       = 'fas fa-chart-line';
$activePage     = 'sales_report';
$pageBreadcrumb = 'Sales Analytics';
include BASE_PATH . '/includes/header.php';
requireAdmin();
?>
<!-- Load Chart.js for analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php
$db = getDB();

// Ensure tables
$db->exec("CREATE TABLE IF NOT EXISTS `orders` (`id` INT AUTO_INCREMENT PRIMARY KEY,`order_number` VARCHAR(30) UNIQUE,`rfq_id` INT DEFAULT NULL,`customer_id` INT NOT NULL DEFAULT 0,`status` ENUM('pending','confirmed','processing','dispatched','delivered','cancelled') DEFAULT 'pending',`subtotal` DECIMAL(10,2) DEFAULT 0.00,`gst_rate` DECIMAL(5,2) DEFAULT 18.00,`gst_amount` DECIMAL(10,2) DEFAULT 0.00,`total_amount` DECIMAL(10,2) DEFAULT 0.00,`shipping_address` TEXT,`payment_status` ENUM('unpaid','paid','partial') DEFAULT 'unpaid',`tracking_info` VARCHAR(255),`admin_notes` TEXT,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS `order_items` (`id` INT AUTO_INCREMENT PRIMARY KEY,`order_id` INT NOT NULL DEFAULT 0,`product_id` INT NOT NULL DEFAULT 0,`quantity` INT NOT NULL DEFAULT 1,`unit_price` DECIMAL(10,2) DEFAULT 0.00,`total_price` DECIMAL(10,2) DEFAULT 0.00) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS `customers` (`id` INT AUTO_INCREMENT PRIMARY KEY,`company_name` VARCHAR(150) NOT NULL,`contact_name` VARCHAR(100) NOT NULL,`email` VARCHAR(150) NOT NULL UNIQUE,`password` VARCHAR(255) NOT NULL,`phone` VARCHAR(20),`gstin` VARCHAR(20),`address` TEXT,`city` VARCHAR(80),`state` VARCHAR(80),`pin` VARCHAR(10),`tier` ENUM('standard','silver','gold') DEFAULT 'standard',`status` ENUM('pending','active','suspended') DEFAULT 'pending',`notes` TEXT,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS `rfqs` (`id` INT AUTO_INCREMENT PRIMARY KEY,`customer_id` INT NOT NULL DEFAULT 0,`rfq_number` VARCHAR(30) UNIQUE,`status` ENUM('submitted','reviewing','quoted','accepted','rejected','closed') DEFAULT 'submitted',`customer_notes` TEXT,`admin_notes` TEXT,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── KPI Metrics ───────────────────────────────────────────────────────────────
$totalRevenue   = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$totalOrders    = $db->query("SELECT COUNT(*) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$totalCustomers = $db->query("SELECT COUNT(*) FROM customers WHERE status='active'")->fetchColumn();
$totalRFQs      = $db->query("SELECT COUNT(*) FROM rfqs")->fetchColumn();
$pendingRFQs    = $db->query("SELECT COUNT(*) FROM rfqs WHERE status IN ('submitted','reviewing')")->fetchColumn();
$pendingPayment = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status='unpaid' AND status!='cancelled'")->fetchColumn();
$avgOrderValue  = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

// ── Monthly Revenue (last 6 months) ──────────────────────────────────────────
$monthlyRevenue = $db->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS month,
           DATE_FORMAT(created_at,'%Y-%m') AS ym,
           COALESCE(SUM(total_amount),0) AS revenue,
           COUNT(*) AS orders
    FROM orders
    WHERE status != 'cancelled'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY ym ORDER BY ym
")->fetchAll();

// ── Top Customers ─────────────────────────────────────────────────────────────
$topCustomers = $db->query("
    SELECT c.company_name, c.tier, c.email,
           COUNT(o.id) AS order_count,
           COALESCE(SUM(o.total_amount),0) AS total_spent
    FROM customers c
    LEFT JOIN orders o ON o.customer_id=c.id AND o.status!='cancelled'
    WHERE c.status='active'
    GROUP BY c.id ORDER BY total_spent DESC LIMIT 10
")->fetchAll();

// ── Top Products (by order items) ────────────────────────────────────────────
$topProducts = $db->query("
    SELECT p.name, p.sku, p.brand,
           COALESCE(SUM(oi.quantity),0) AS total_qty,
           COALESCE(SUM(oi.total_price),0) AS total_revenue
    FROM products p
    LEFT JOIN order_items oi ON oi.product_id=p.id
    GROUP BY p.id ORDER BY total_qty DESC LIMIT 10
")->fetchAll();

// ── Order Status Distribution ─────────────────────────────────────────────────
$statusDist = $db->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status")->fetchAll();

// ── RFQ Conversion Rate ───────────────────────────────────────────────────────
$quotedRFQs   = $db->query("SELECT COUNT(*) FROM rfqs WHERE status='quoted'")->fetchColumn();
$convRate     = $totalRFQs > 0 ? round(($totalOrders / $totalRFQs) * 100) : 0;

// ── New Customers (last 30 days) ──────────────────────────────────────────────
$newCustomers = $db->query("SELECT COUNT(*) FROM customers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
?>

<div class="page-body">

<!-- KPI Cards -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;">
<?php
$kpis = [
    ['Total Revenue',       '₹'.number_format($totalRevenue,0), 'fas fa-indian-rupee-sign', '#6c63ff', '+B2B sales'],
    ['Total Orders',        $totalOrders,                        'fas fa-truck',             '#48daf5', 'Confirmed'],
    ['Active Customers',    $totalCustomers,                     'fas fa-building',          '#22c55e', '+'. $newCustomers.' this month'],
    ['Pending Payment',     '₹'.number_format($pendingPayment,0),'fas fa-clock',             '#f59e0b', 'Outstanding'],
];
foreach ($kpis as [$label, $val, $icon, $color, $sub]):
?>
<div class="card" style="padding:1.25rem;border-top:3px solid <?= $color ?>;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.6rem;">
        <span style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);"><?= $label ?></span>
        <div style="width:34px;height:34px;border-radius:8px;background:<?= $color ?>22;display:flex;align-items:center;justify-content:center;">
            <i class="<?= $icon ?>" style="color:<?= $color ?>;font-size:0.9rem;"></i>
        </div>
    </div>
    <div style="font-size:1.5rem;font-weight:800;color:var(--text-primary);margin-bottom:0.2rem;"><?= $val ?></div>
    <div style="font-size:0.72rem;color:var(--text-muted);"><?= $sub ?></div>
</div>
<?php endforeach; ?>
</div>

<!-- Second row KPIs -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;">
    <div class="card" style="padding:1.1rem;text-align:center;border-top:3px solid #ef4444;">
        <div style="font-size:1.3rem;font-weight:800;color:var(--text-primary);"><?= $pendingRFQs ?></div>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.2rem;">Pending RFQs</div>
    </div>
    <div class="card" style="padding:1.1rem;text-align:center;border-top:3px solid #6366f1;">
        <div style="font-size:1.3rem;font-weight:800;color:var(--text-primary);"><?= $convRate ?>%</div>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.2rem;">RFQ→Order Rate</div>
    </div>
    <div class="card" style="padding:1.1rem;text-align:center;border-top:3px solid #0891b2;">
        <div style="font-size:1.3rem;font-weight:800;color:var(--text-primary);">₹<?= number_format($avgOrderValue,0) ?></div>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.2rem;">Avg Order Value</div>
    </div>
    <div class="card" style="padding:1.1rem;text-align:center;border-top:3px solid #16a34a;">
        <div style="font-size:1.3rem;font-weight:800;color:var(--text-primary);"><?= $totalRFQs ?></div>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.2rem;">Total RFQs</div>
    </div>
</div>

<!-- Charts Row -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;margin-bottom:1.25rem;">
    <!-- Monthly Revenue Chart -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-chart-line"></i> Monthly Revenue (Last 6 Months)</div>
        </div>
        <div style="padding:1.25rem;">
            <canvas id="revenueChart" height="200"></canvas>
        </div>
    </div>

    <!-- Order Status Pie -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-chart-pie"></i> Order Status</div>
        </div>
        <div style="padding:1.25rem;">
            <canvas id="statusChart" height="200"></canvas>
        </div>
    </div>
</div>

<!-- Tables Row -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;">
    <!-- Top Customers -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-trophy"></i> Top Customers</div>
            <a href="customers_b2b.php" style="font-size:0.78rem;color:var(--primary);">View All</a>
        </div>
        <?php if (empty($topCustomers)): ?>
        <div class="empty-state" style="padding:2rem;"><i class="fas fa-building"></i><h3>No data yet</h3></div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>#</th><th>Company</th><th>Tier</th><th>Orders</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($topCustomers as $idx => $c): ?>
                <tr>
                    <td style="font-weight:700;color:var(--text-muted);"><?= $idx+1 ?></td>
                    <td>
                        <div style="font-weight:600;font-size:0.82rem;"><?= htmlspecialchars($c['company_name']) ?></div>
                        <div style="font-size:0.7rem;color:var(--text-muted);"><?= htmlspecialchars($c['email']) ?></div>
                    </td>
                    <td>
                        <?php $tc=['standard'=>'badge-gray','silver'=>'badge-info','gold'=>'badge-warning']; ?>
                        <span class="badge-pill <?= $tc[$c['tier']]??'badge-gray' ?>"><?= ucfirst($c['tier']) ?></span>
                    </td>
                    <td style="font-weight:700;text-align:center;"><?= $c['order_count'] ?></td>
                    <td style="font-weight:700;color:var(--primary);">₹<?= number_format($c['total_spent'],0) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Top Products -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-box"></i> Top Products Ordered</div>
            <a href="products.php" style="font-size:0.78rem;color:var(--primary);">View All</a>
        </div>
        <?php if (empty($topProducts) || $topProducts[0]['total_qty'] == 0): ?>
        <div class="empty-state" style="padding:2rem;"><i class="fas fa-box"></i><h3>No order data yet</h3></div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>#</th><th>Product</th><th>Brand</th><th>Units</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($topProducts as $idx => $p): if (!$p['total_qty']) continue; ?>
                <tr>
                    <td style="font-weight:700;color:var(--text-muted);"><?= $idx+1 ?></td>
                    <td>
                        <div style="font-weight:600;font-size:0.82rem;"><?= htmlspecialchars($p['name']) ?></div>
                        <div style="font-size:0.7rem;color:var(--text-muted);"><?= htmlspecialchars($p['sku']) ?></div>
                    </td>
                    <td style="font-size:0.8rem;"><?= htmlspecialchars($p['brand'] ?: '—') ?></td>
                    <td style="font-weight:700;text-align:center;"><?= $p['total_qty'] ?></td>
                    <td style="font-weight:700;color:var(--primary);">₹<?= number_format($p['total_revenue'],0) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Export Row -->
<div class="card">
    <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div style="font-size:0.875rem;font-weight:600;color:var(--text-primary);">
            <i class="fas fa-download" style="color:var(--primary);"></i>
            Export B2B Data
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <a href="?export=orders_csv" class="btn btn-outline btn-sm"><i class="fas fa-file-csv"></i> Orders CSV</a>
            <a href="?export=customers_csv" class="btn btn-outline btn-sm"><i class="fas fa-file-csv"></i> Customers CSV</a>
            <a href="?export=rfqs_csv" class="btn btn-outline btn-sm"><i class="fas fa-file-csv"></i> RFQs CSV</a>
            <button onclick="window.print()" class="btn btn-outline btn-sm"><i class="fas fa-print"></i> Print Report</button>
        </div>
    </div>
</div>

</div><!-- .page-body -->

<?php
// Handle CSV exports
$export = $_GET['export'] ?? '';
if ($export === 'orders_csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="orders_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order #','Customer','Total','Status','Payment','Date']);
    $rows = $db->query("SELECT o.order_number,c.company_name,o.total_amount,o.status,o.payment_status,o.created_at FROM orders o JOIN customers c ON o.customer_id=c.id ORDER BY o.created_at DESC")->fetchAll();
    foreach ($rows as $r) fputcsv($out, array_values($r));
    fclose($out); exit;
}
if ($export === 'customers_csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="customers_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Company','Contact','Email','Phone','GSTIN','City','State','Tier','Status','Joined']);
    $rows = $db->query("SELECT company_name,contact_name,email,phone,gstin,city,state,tier,status,created_at FROM customers ORDER BY created_at DESC")->fetchAll();
    foreach ($rows as $r) fputcsv($out, array_values($r));
    fclose($out); exit;
}
if ($export === 'rfqs_csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="rfqs_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['RFQ #','Customer','Status','Notes','Date']);
    $rows = $db->query("SELECT r.rfq_number,c.company_name,r.status,r.customer_notes,r.created_at FROM rfqs r JOIN customers c ON r.customer_id=c.id ORDER BY r.created_at DESC")->fetchAll();
    foreach ($rows as $r) fputcsv($out, array_values($r));
    fclose($out); exit;
}
?>

<script>
// Monthly Revenue Chart
<?php
$labels   = array_column($monthlyRevenue, 'month');
$revenues = array_column($monthlyRevenue, 'revenue');
$orderCts = array_column($monthlyRevenue, 'orders');
?>
if (document.getElementById('revenueChart')) {
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels ?: ['No data']) ?>,
        datasets: [{
            label: 'Revenue (₹)',
            data: <?= json_encode($revenues ?: [0]) ?>,
            backgroundColor: 'rgba(108,99,255,0.7)',
            borderColor: '#6c63ff',
            borderWidth: 2,
            borderRadius: 6,
            yAxisID: 'y',
        },{
            label: 'Orders',
            data: <?= json_encode($orderCts ?: [0]) ?>,
            type: 'line',
            borderColor: '#48daf5',
            backgroundColor: 'rgba(72,218,245,0.1)',
            tension: 0.4, fill: true, pointRadius: 4,
            yAxisID: 'y1',
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: {
            y:  { beginAtZero:true, position:'left',  ticks: { callback: v=>'₹'+v } },
            y1: { beginAtZero:true, position:'right', grid:{drawOnChartArea:false} }
        }
    }
}); }

// Status Pie Chart
<?php
$statLabels = array_column($statusDist, 'status');
$statCounts = array_column($statusDist, 'cnt');
$pieColors  = ['#f59e0b','#2563eb','#6366f1','#0891b2','#16a34a','#ef4444'];
?>
if (document.getElementById('statusChart')) {
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map('ucfirst', $statLabels ?: ['No orders'])) ?>,
        datasets: [{ data: <?= json_encode($statCounts ?: [1]) ?>, backgroundColor: <?= json_encode($pieColors) ?>, borderWidth: 0 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
}); }
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
