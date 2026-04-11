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

<?php include BASE_PATH . '/includes/footer.php'; ?>
