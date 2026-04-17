<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle  = 'Reports & Export';
$pageIcon   = 'fas fa-file-chart-column';
$activePage = 'reports';
$pageBreadcrumb = 'Reports';
include BASE_PATH . '/includes/header.php';

$db = getDB();

$reportType = sanitize($_GET['report'] ?? 'stock_summary');
$cat_f      = (int)($_GET['category'] ?? 0);
$tool_f     = (int)($_GET['tool'] ?? 0);
$date_from  = sanitize($_GET['from'] ?? date('Y-m-01'));
$date_to    = sanitize($_GET['to'] ?? date('Y-m-d'));
$export     = sanitize($_GET['export'] ?? '');

$categories = $db->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();
$tools      = $db->query("SELECT * FROM tools WHERE status='active' ORDER BY name")->fetchAll();

// ---- Build Report Data ----
$reportData = [];
$reportCols = [];
$reportTitle = '';

if ($reportType === 'stock_summary') {
    $reportTitle = 'Stock Summary Report';
    $reportCols  = ['SKU','Product','Category','Brand','Price','Quantity','Min Stock','Status','Value'];
    $where = ['p.status="active"'];
    $params = [];
    if ($cat_f) { $where[] = 'p.category_id=?'; $params[] = $cat_f; }

    $stmt = $db->prepare("SELECT p.*, c.name AS cat_name, (p.price * p.quantity) AS stock_value FROM products p JOIN categories c ON p.category_id=c.id WHERE " . implode(' AND ', $where) . " ORDER BY p.name");
    $stmt->execute($params);
    $reportData = $stmt->fetchAll();

} elseif ($reportType === 'low_stock') {
    $reportTitle = 'Low Stock Report';
    $reportCols  = ['SKU','Product','Category','Brand','Current Stock','Min Stock','Deficit'];
    $stmt = $db->query("SELECT p.*, c.name AS cat_name, (p.min_stock - p.quantity) AS deficit FROM products p JOIN categories c ON p.category_id=c.id WHERE p.quantity <= p.min_stock AND p.status='active' ORDER BY p.quantity ASC");
    $reportData = $stmt->fetchAll();

} elseif ($reportType === 'stock_log') {
    $reportTitle = 'Stock Log Report';
    $reportCols  = ['Date','Product','SKU','Type','Qty','Before','After','By','Notes'];
    $where = ["sl.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)"];
    $params = [$date_from, $date_to];
    if ($cat_f) { $where[] = 'p.category_id=?'; $params[] = $cat_f; }

    $stmt = $db->prepare("SELECT sl.*, p.name AS pname, p.sku, u.name AS uname FROM stock_logs sl JOIN products p ON sl.product_id=p.id JOIN users u ON sl.user_id=u.id WHERE " . implode(' AND ',$where) . " ORDER BY sl.created_at DESC");
    $stmt->execute($params);
    $reportData = $stmt->fetchAll();

} elseif ($reportType === 'compatibility') {
    $reportTitle = 'Compatibility Report';
    $reportCols  = ['Product','SKU','Category','Compatible Tools'];
    $where = ['p.status="active"'];
    $params = [];
    if ($tool_f) {
        $where[] = 'EXISTS (SELECT 1 FROM product_compatibility pc2 WHERE pc2.product_id=p.id AND pc2.tool_id=?)';
        $params[] = $tool_f;
    }
    $stmt = $db->prepare("SELECT p.*, c.name AS cat_name FROM products p JOIN categories c ON p.category_id=c.id WHERE " . implode(' AND ',$where) . " ORDER BY p.name");
    $stmt->execute($params);
    $reportData = $stmt->fetchAll();
    // Add tool names
    foreach ($reportData as &$row) {
        $ts = $db->prepare("SELECT t.name FROM tools t JOIN product_compatibility pc ON t.id=pc.tool_id WHERE pc.product_id=?");
        $ts->execute([$row['id']]);
        $row['tool_names'] = implode(', ', $ts->fetchAll(PDO::FETCH_COLUMN));
    }
    unset($row);
}

// ---- CSV Export ----
if ($export === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="torvo_spair_' . $reportType . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $reportCols);

    foreach ($reportData as $row) {
        if ($reportType === 'stock_summary') {
            fputcsv($out, [$row['sku'], $row['name'], $row['cat_name'], $row['brand'], $row['price'], $row['quantity'], $row['min_stock'], $row['status'], $row['stock_value']]);
        } elseif ($reportType === 'low_stock') {
            fputcsv($out, [$row['sku'], $row['name'], $row['cat_name'], $row['brand'], $row['quantity'], $row['min_stock'], $row['deficit']]);
        } elseif ($reportType === 'stock_log') {
            fputcsv($out, [$row['created_at'], $row['pname'], $row['sku'], strtoupper($row['type']), $row['quantity'], $row['previous_stock'], $row['current_stock'], $row['uname'], $row['notes']]);
        } elseif ($reportType === 'compatibility') {
            fputcsv($out, [$row['name'], $row['sku'], $row['cat_name'], $row['tool_names']]);
        }
    }
    fclose($out);
    exit;
}
?>

<div class="page-body">

<!-- Report Selector + Filters -->
<form method="GET" class="filter-bar" id="reportForm">
    <div class="filter-group">
        <select name="report" class="form-control" onchange="document.getElementById('reportForm').submit()">
            <?php $rpts = ['stock_summary'=>'Stock Summary','low_stock'=>'Low Stock','stock_log'=>'Stock Log','compatibility'=>'Compatibility']; ?>
            <?php foreach ($rpts as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $reportType===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if (in_array($reportType, ['stock_summary','stock_log','compatibility'])): ?>
    <div class="filter-group">
        <select name="category" class="form-control">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $cat_f==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <?php if ($reportType === 'compatibility'): ?>
    <div class="filter-group">
        <select name="tool" class="form-control">
            <option value="">All Tools</option>
            <?php foreach ($tools as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $tool_f==$t['id']?'selected':'' ?>><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <?php if ($reportType === 'stock_log'): ?>
    <div class="filter-group">
        <input type="date" name="from" class="form-control" value="<?= $date_from ?>">
    </div>
    <div class="filter-group">
        <input type="date" name="to" class="form-control" value="<?= $date_to ?>">
    </div>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
</form>

<!-- Report Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-file-alt"></i> <?= htmlspecialchars($reportTitle) ?>
            <span style="font-size:0.8rem;color:var(--text-muted);font-weight:400;">(<?= count($reportData) ?> records)</span>
        </div>
        <div style="display:flex;gap:0.5rem;">
            <a href="?report=<?= $reportType ?>&category=<?= $cat_f ?>&tool=<?= $tool_f ?>&from=<?= $date_from ?>&to=<?= $date_to ?>&export=csv"
               class="btn btn-success btn-sm"><i class="fas fa-file-csv"></i> Export CSV</a>
            <button onclick="exportPDF()" class="btn btn-sm" style="background:#ef4444;color:#fff;border:none;"><i class="fas fa-file-pdf"></i> Export PDF</button>
            <button onclick="window.print()" class="btn btn-outline btn-sm"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>

    <?php if (empty($reportData)): ?>
        <div class="empty-state"><i class="fas fa-inbox"></i><h3>No data for this report</h3></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table" id="reportTable">
            <thead>
                <tr><?php foreach ($reportCols as $col): ?><th><?= $col ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
            <?php foreach ($reportData as $row): ?>
            <tr>
                <?php if ($reportType === 'stock_summary'): ?>
                    <td style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($row['sku']) ?></td>
                    <td style="font-weight:600;font-size:0.875rem;"><?= htmlspecialchars($row['name']) ?></td>
                    <td><span class="badge-pill badge-info"><?= htmlspecialchars($row['cat_name']) ?></span></td>
                    <td><?= htmlspecialchars($row['brand']?:'–') ?></td>
                    <td><?= formatCurrency($row['price']) ?></td>
                    <td style="font-weight:700;<?= $row['quantity']==0?'color:var(--danger)':($row['quantity']<=$row['min_stock']?'color:var(--warning)':'color:var(--success)'); ?>">
                        <?= $row['quantity'] ?>
                    </td>
                    <td><?= $row['min_stock'] ?></td>
                    <td><span class="badge-pill <?= $row['status']==='active'?'badge-success':'badge-gray' ?>"><?= ucfirst($row['status']) ?></span></td>
                    <td><?= formatCurrency($row['stock_value']) ?></td>

                <?php elseif ($reportType === 'low_stock'): ?>
                    <td style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($row['sku']) ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($row['name']) ?></td>
                    <td><span class="badge-pill badge-info"><?= htmlspecialchars($row['cat_name']) ?></span></td>
                    <td><?= htmlspecialchars($row['brand']?:'–') ?></td>
                    <td style="color:<?= $row['quantity']==0?'var(--danger)':'var(--warning)' ?>;font-weight:700;"><?= $row['quantity'] ?></td>
                    <td><?= $row['min_stock'] ?></td>
                    <td><span class="badge-pill badge-danger"><?= $row['deficit'] ?></span></td>

                <?php elseif ($reportType === 'stock_log'): ?>
                    <td style="font-size:0.78rem;white-space:nowrap;"><?= date('d M Y h:ia', strtotime($row['created_at'])) ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($row['pname']) ?></td>
                    <td style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($row['sku']) ?></td>
                    <td><span class="badge-pill <?= $row['type']==='in'?'badge-success':'badge-warning' ?>"><?= strtoupper($row['type']) ?></span></td>
                    <td style="font-weight:700;"><?= $row['quantity'] ?></td>
                    <td><?= $row['previous_stock'] ?></td>
                    <td><?= $row['current_stock'] ?></td>
                    <td><?= htmlspecialchars($row['uname']) ?></td>
                    <td style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($row['notes']?:'—') ?></td>

                <?php elseif ($reportType === 'compatibility'): ?>
                    <td style="font-weight:600;"><?= htmlspecialchars($row['name']) ?></td>
                    <td style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($row['sku']) ?></td>
                    <td><span class="badge-pill badge-info"><?= htmlspecialchars($row['cat_name']) ?></span></td>
                    <td>
                        <?php foreach (explode(', ', $row['tool_names']) as $tn): if (trim($tn)): ?>
                            <span class="compat-tag"><i class="fas fa-wrench"></i><?= htmlspecialchars(trim($tn)) ?></span>
                        <?php endif; endforeach; ?>
                    </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($reportType === 'stock_summary'): ?>
            <tfoot>
                <tr style="background:var(--bg-card2);font-weight:700;">
                    <td colspan="5">Total</td>
                    <td><?= array_sum(array_column($reportData, 'quantity')) ?></td>
                    <td></td>
                    <td></td>
                    <td><?= formatCurrency(array_sum(array_column($reportData, 'stock_value'))) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php endif; ?>
</div>

</div>

<!-- PDF Export Styles -->
<style>
@media print {
    .sidebar, .sidebar-overlay, .top-header, .filter-bar, .page-header,
    .btn, button, a.btn { display: none !important; }
    .app-layout { display: block !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .card { border: 1px solid #ddd !important; box-shadow: none !important; }
    .data-table { font-size: 0.75rem; }
    .data-table th { background: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .badge-pill { border: 1px solid #ccc !important; }
}
</style>

<script>
function exportPDF() {
    const table = document.getElementById('reportTable');
    if (!table) { alert('No report data to export.'); return; }

    const title = <?= json_encode($reportTitle) ?>;
    const date  = new Date().toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' });

    const win = window.open('', '_blank', 'width=900,height=700');
    win.document.write(`<!DOCTYPE html>
<html><head><title>${title} - TORVO SPAIR</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1e293b; padding: 2rem; }
    .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:2px solid #1e3a8a; }
    .header h1 { font-size:1.3rem; color:#1e3a8a; }
    .header .meta { font-size:0.8rem; color:#64748b; text-align:right; }
    table { width:100%; border-collapse:collapse; margin-top:1rem; font-size:0.82rem; }
    th { background:#1e3a8a; color:#fff; padding:0.6rem 0.75rem; text-align:left; font-weight:600; font-size:0.72rem; text-transform:uppercase; letter-spacing:0.3px; }
    td { padding:0.5rem 0.75rem; border-bottom:1px solid #e2e8f0; }
    tbody tr:nth-child(even) { background:#f8fafc; }
    tfoot tr { background:#f1f5f9; font-weight:700; }
    .footer { margin-top:2rem; padding-top:1rem; border-top:1px solid #e2e8f0; font-size:0.72rem; color:#94a3b8; text-align:center; }
</style>
</head><body>
    <div class="header">
        <div><h1>TORVO SPAIR</h1><div style="font-size:0.75rem;color:#64748b;margin-top:0.25rem;">${title}</div></div>
        <div class="meta">Generated: ${date}<br>Records: ${table.querySelectorAll('tbody tr').length}</div>
    </div>
    ${table.outerHTML}
    <div class="footer">This report was generated by TORVO SPAIR Inventory Management System &bull; torvotools.com</div>
    <script>window.onload=function(){window.print();}<\/script>
</body></html>`);
    win.document.close();
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
