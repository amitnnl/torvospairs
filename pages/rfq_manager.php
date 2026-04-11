<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle      = 'RFQ Management';
$pageIcon       = 'fas fa-file-invoice';
$activePage     = 'rfqs';
$pageBreadcrumb = 'RFQ Management';
include BASE_PATH . '/includes/header.php';
requireAdmin();

$db = getDB();

// ── Handle Actions ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_rfq';
    $rfqId  = (int)$_POST['rfq_id'];

    if ($action === 'update_rfq') {
        $status = sanitize($_POST['status'] ?? '');
        $notes  = sanitize($_POST['admin_notes'] ?? '');
        $validStatuses = ['submitted','reviewing','quoted','accepted','rejected','closed'];
        if (in_array($status, $validStatuses) && $rfqId) {
            $db->prepare("UPDATE rfqs SET status=?, admin_notes=? WHERE id=?")->execute([$status, $notes, $rfqId]);
            setFlash('success', 'RFQ updated.');
        }
    }

    // ── Save unit prices for RFQ items ────────────────────────────────────────
    if ($action === 'price_items' && $rfqId) {
        $prices = $_POST['prices'] ?? [];
        foreach ($prices as $itemId => $price) {
            $price = round((float)$price, 2);
            if ($price >= 0) {
                $db->prepare("UPDATE rfq_items SET unit_price=? WHERE id=? AND rfq_id=?")
                   ->execute([$price, (int)$itemId, $rfqId]);
            }
        }
        // Auto-set status to 'quoted'
        $db->prepare("UPDATE rfqs SET status='quoted',admin_notes=COALESCE(NULLIF(admin_notes,''), 'Prices quoted. Please review and confirm.') WHERE id=?")
           ->execute([$rfqId]);
        setFlash('success', 'Item prices saved. RFQ status set to Quoted.');
    }

    header('Location: rfq_manager.php'); exit;
}

// ── Filters ───────────────────────────────────────────────────────────────────
$statusFilter  = sanitize($_GET['status']   ?? '');
$custFilter    = (int)($_GET['customer']    ?? 0);
$selectedRFQ   = (int)($_GET['rfq']         ?? 0);

$sql = "SELECT r.*, c.company_name, c.contact_name, c.email, c.phone, c.tier,
        (SELECT COUNT(*) FROM rfq_items WHERE rfq_id=r.id) AS item_count
        FROM rfqs r JOIN customers c ON r.customer_id = c.id";
$where = []; $params = [];
if ($statusFilter) { $where[] = "r.status=?"; $params[] = $statusFilter; }
if ($custFilter)   { $where[] = "r.customer_id=?"; $params[] = $custFilter; }
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY r.created_at DESC";
$stmt = $db->prepare($sql); $stmt->execute($params);
$rfqs = $stmt->fetchAll();

$statuses      = ['submitted','reviewing','quoted','accepted','rejected','closed'];
$statusColors  = ['submitted'=>'badge-info','reviewing'=>'badge-warning','quoted'=>'badge-primary','accepted'=>'badge-success','rejected'=>'badge-danger','closed'=>'badge-gray'];

// RFQ counts per status
$rfqCounts = [];
foreach ($statuses as $s) {
    $c = $db->prepare("SELECT COUNT(*) FROM rfqs WHERE status=?"); $c->execute([$s]);
    $rfqCounts[$s] = $c->fetchColumn();
}
$rfqCounts['all'] = $db->query("SELECT COUNT(*) FROM rfqs")->fetchColumn();

// Load selected RFQ detail if any
$rfqDetail = null; $rfqItems = [];
if ($selectedRFQ) {
    $d = $db->prepare("SELECT r.*,c.company_name,c.contact_name,c.email,c.phone,c.gstin,c.address,c.city,c.state,c.tier FROM rfqs r JOIN customers c ON r.customer_id=c.id WHERE r.id=?");
    $d->execute([$selectedRFQ]);
    $rfqDetail = $d->fetch();
    if ($rfqDetail) {
        $i = $db->prepare("SELECT ri.*, p.name AS pname, p.sku, p.price AS list_price, p.image, c.name AS cat FROM rfq_items ri JOIN products p ON ri.product_id=p.id JOIN categories c ON p.category_id=c.id WHERE ri.rfq_id=?");
        $i->execute([$selectedRFQ]);
        $rfqItems = $i->fetchAll();
    }
}
?>

<div class="page-body">

<!-- Status filter pills -->
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1.25rem;align-items:center;">
    <a href="rfq_manager.php" class="badge-pill <?= !$statusFilter?'badge-primary':'badge-gray' ?>" style="cursor:pointer;text-decoration:none;padding:0.4rem 0.85rem;">
        All <span style="margin-left:4px;opacity:0.7;"><?= $rfqCounts['all'] ?></span>
    </a>
    <?php foreach ($statuses as $s): ?>
    <a href="?status=<?= $s ?>" class="badge-pill <?= $statusFilter===$s?$statusColors[$s]:'badge-gray' ?>" style="cursor:pointer;text-decoration:none;padding:0.4rem 0.85rem;">
        <?= ucfirst($s) ?> <span style="margin-left:4px;opacity:0.7;"><?= $rfqCounts[$s] ?></span>
    </a>
    <?php endforeach; ?>
    <div style="margin-left:auto;display:flex;gap:0.5rem;">
        <a href="../portal/catalogue.php" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-external-link-alt"></i> Portal</a>
        <a href="orders_admin.php" class="btn btn-outline btn-sm"><i class="fas fa-truck"></i> Orders</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:<?= $rfqDetail ? '1fr 1.4fr' : '1fr' ?>;gap:1.25rem;align-items:start;">

<!-- RFQ Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-file-invoice"></i> RFQs <span style="font-size:0.78rem;color:var(--text-muted);font-weight:400;"><?= count($rfqs) ?> found</span></div>
    </div>
    <?php if (empty($rfqs)): ?>
    <div class="empty-state"><i class="fas fa-inbox"></i><h3>No RFQs found</h3><p>Customer RFQs will appear here.</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>RFQ #</th><th>Company</th><th>Items</th><th>Status</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rfqs as $r): ?>
            <tr style="<?= $selectedRFQ==$r['id']?'background:rgba(37,99,235,0.05);':'' ?>">
                <td style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($r['rfq_number']) ?></td>
                <td>
                    <div style="font-weight:600;font-size:0.82rem;"><?= htmlspecialchars($r['company_name']) ?></div>
                    <div style="font-size:0.7rem;color:var(--text-muted);"><?= htmlspecialchars($r['contact_name']) ?></div>
                </td>
                <td style="text-align:center;font-weight:700;color:var(--primary);"><?= $r['item_count'] ?></td>
                <td><span class="badge-pill <?= $statusColors[$r['status']] ?? 'badge-gray' ?>"><?= ucfirst($r['status']) ?></span></td>
                <td style="font-size:0.72rem;color:var(--text-muted);white-space:nowrap;"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:0.3rem;">
                        <a href="rfq_manager.php?rfq=<?= $r['id'] ?><?= $statusFilter?"&status=$statusFilter":'' ?>" class="btn btn-outline btn-sm btn-icon" data-tooltip="Open Detail">
                            <i class="fas fa-folder-open"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php if ($rfqDetail): ?>
<!-- RFQ Detail Panel -->
<div style="display:flex;flex-direction:column;gap:1rem;">

    <!-- Header Card -->
    <div class="card">
        <div class="card-header" style="flex-wrap:wrap;gap:0.5rem;">
            <div>
                <div class="card-title"><?= htmlspecialchars($rfqDetail['rfq_number']) ?></div>
                <div style="font-size:0.75rem;color:var(--text-muted);">Submitted <?= date('d M Y, h:ia', strtotime($rfqDetail['created_at'])) ?></div>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                <span class="badge-pill <?= $statusColors[$rfqDetail['status']] ?? 'badge-gray' ?>"><?= ucfirst($rfqDetail['status']) ?></span>
                <?php $tc=['standard'=>'badge-gray','silver'=>'badge-info','gold'=>'badge-warning']; ?>
                <span class="badge-pill <?= $tc[$rfqDetail['tier']] ?? 'badge-gray' ?>"><?= ucfirst($rfqDetail['tier']) ?> Partner</span>
            </div>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;font-size:0.83rem;">
                <div>
                    <div style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:0.3rem;">Customer</div>
                    <div style="font-weight:700;"><?= htmlspecialchars($rfqDetail['company_name']) ?></div>
                    <div><?= htmlspecialchars($rfqDetail['contact_name']) ?></div>
                    <div style="color:var(--text-muted);"><?= htmlspecialchars($rfqDetail['email']) ?></div>
                    <?php if ($rfqDetail['phone']): ?>
                    <div style="color:var(--text-muted);"><?= htmlspecialchars($rfqDetail['phone']) ?></div>
                    <?php endif; ?>
                    <?php if ($rfqDetail['gstin']): ?>
                    <div><code style="font-size:0.72rem;background:var(--bg-card2);padding:2px 5px;border-radius:4px;">GSTIN: <?= htmlspecialchars($rfqDetail['gstin']) ?></code></div>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($rfqDetail['customer_notes']): ?>
                    <div style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:0.3rem;">Customer Notes</div>
                    <div style="background:rgba(249,115,22,0.06);border-left:2px solid #f97316;padding:0.5rem 0.75rem;border-radius:0 6px 6px 0;font-style:italic;">
                        <?= nl2br(htmlspecialchars($rfqDetail['customer_notes'])) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($rfqDetail['admin_notes']): ?>
                    <div style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-top:0.6rem;margin-bottom:0.3rem;">Previous Response</div>
                    <div style="background:rgba(37,99,235,0.05);border-left:2px solid var(--primary);padding:0.5rem 0.75rem;border-radius:0 6px 6px 0;">
                        <?= nl2br(htmlspecialchars($rfqDetail['admin_notes'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Pricing Form -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-tags"></i> Item Pricing</div>
            <div style="font-size:0.75rem;color:var(--text-muted);">Set quoted prices for each item</div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="price_items">
            <input type="hidden" name="rfq_id" value="<?= $rfqDetail['id'] ?>">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Product</th><th>SKU</th><th>Req. Qty</th><th>List Price</th><th>Quoted Price</th><th>Subtotal</th></tr>
                    </thead>
                    <tbody>
                    <?php
                    $rfqSubtotal = 0;
                    foreach ($rfqItems as $item):
                        $quotedPrice = $item['unit_price'] > 0 ? $item['unit_price'] : $item['list_price'];
                        $lineTotal   = $quotedPrice * $item['quantity'];
                        $rfqSubtotal += $lineTotal;
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;font-size:0.82rem;"><?= htmlspecialchars($item['pname']) ?></div>
                            <div style="font-size:0.68rem;color:var(--text-muted);"><?= htmlspecialchars($item['cat']) ?></div>
                        </td>
                        <td style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($item['sku']) ?></td>
                        <td style="font-weight:700;text-align:center;"><?= $item['quantity'] ?></td>
                        <td style="color:var(--text-muted);">₹<?= number_format($item['list_price'],2) ?></td>
                        <td style="min-width:110px;">
                            <div style="display:flex;align-items:center;background:var(--bg-card2);border:1px solid var(--border-color);border-radius:7px;overflow:hidden;">
                                <span style="padding:0 0.5rem;color:var(--text-muted);font-size:0.8rem;">₹</span>
                                <input type="number" name="prices[<?= $item['id'] ?>]"
                                       value="<?= number_format($quotedPrice, 2, '.', '') ?>"
                                       step="0.01" min="0"
                                       class="price-input"
                                       data-qty="<?= $item['quantity'] ?>"
                                       data-row="<?= $item['id'] ?>"
                                       style="background:none;border:none;outline:none;padding:0.5rem 0.5rem;font-size:0.85rem;font-weight:600;width:80px;color:var(--text-primary);"
                                       oninput="recalcRow(this)">
                            </div>
                        </td>
                        <td id="line-<?= $item['id'] ?>" style="font-weight:700;color:var(--primary);white-space:nowrap;">
                            ₹<?= number_format($lineTotal, 2) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:var(--bg-card2);font-weight:800;">
                            <td colspan="5" style="text-align:right;">RFQ Subtotal</td>
                            <td id="rfqTotal" style="color:var(--primary);">₹<?= number_format($rfqSubtotal, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div style="padding:1rem 1.25rem;display:flex;justify-content:flex-end;gap:0.5rem;border-top:1px solid var(--border-color);">
                <button type="submit" class="btn btn-primary"><i class="fas fa-tags"></i> Save Prices & Mark Quoted</button>
            </div>
        </form>
    </div>

    <!-- Update Status Form -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-edit"></i> Status & Response</div></div>
        <form method="POST">
            <input type="hidden" name="action" value="update_rfq">
            <input type="hidden" name="rfq_id" value="<?= $rfqDetail['id'] ?>">
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Update Status</label>
                        <select name="status" class="form-control">
                            <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $rfqDetail['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="align-items:flex-end;display:flex;">
                        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                            <a href="https://api.whatsapp.com/send?phone=<?= preg_replace('/[^0-9]/', '', $rfqDetail['phone'] ?? '') ?>&text=Hi+<?= urlencode($rfqDetail['contact_name']) ?>!+Your+RFQ+<?= urlencode($rfqDetail['rfq_number']) ?>+has+been+reviewed." target="_blank" class="btn btn-outline btn-sm" style="color:#25d366;border-color:#25d366;">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </a>
                            <a href="mailto:<?= htmlspecialchars($rfqDetail['email']) ?>?subject=Re: RFQ <?= urlencode($rfqDetail['rfq_number']) ?>" class="btn btn-outline btn-sm">
                                <i class="fas fa-envelope"></i> Email
                            </a>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Admin Response to Customer</label>
                    <textarea name="admin_notes" class="form-control" rows="3" placeholder="Write your response, quote terms, delivery time..."><?= htmlspecialchars($rfqDetail['admin_notes'] ?? '') ?></textarea>
                </div>
            </div>
            <div style="padding:0.75rem 1.25rem;border-top:1px solid var(--border-color);display:flex;gap:0.5rem;justify-content:flex-end;">
                <a href="orders_admin.php" class="btn btn-outline btn-sm"><i class="fas fa-truck"></i> Create Order</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update RFQ</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

</div><!-- grid -->
</div><!-- .page-body -->

<script>
function openRFQ(r) { window.location.href = 'rfq_manager.php?rfq=' + r.id + '<?= $statusFilter?"&status=$statusFilter":'' ?>'; }

function recalcRow(input) {
    const price = parseFloat(input.value) || 0;
    const qty   = parseInt(input.dataset.qty) || 1;
    const rowId = input.dataset.row;
    const line  = document.getElementById('line-' + rowId);
    if (line) line.textContent = '₹' + (price * qty).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});

    // Recalc total
    let total = 0;
    document.querySelectorAll('.price-input').forEach(inp => {
        const p = parseFloat(inp.value) || 0;
        const q = parseInt(inp.dataset.qty) || 1;
        total += p * q;
    });
    const tot = document.getElementById('rfqTotal');
    if (tot) tot.textContent = '₹' + total.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
