<?php
require_once __DIR__ . '/config/auth.php';
ensureB2BTables();
requireCustomerLogin();

$customer = currentCustomer();
$db = portalDB();
$cid = $customer['id'];

// Stats
$totalRfqs  = $db->prepare("SELECT COUNT(*) FROM rfqs WHERE customer_id=?"); $totalRfqs->execute([$cid]);
$totalRfqs  = $totalRfqs->fetchColumn();
$openRfqs   = $db->prepare("SELECT COUNT(*) FROM rfqs WHERE customer_id=? AND status NOT IN ('accepted','rejected','closed')"); $openRfqs->execute([$cid]);
$openRfqs   = $openRfqs->fetchColumn();
$totalItems = $db->prepare("SELECT COALESCE(SUM(ri.quantity),0) FROM rfq_items ri JOIN rfqs r ON ri.rfq_id=r.id WHERE r.customer_id=?"); $totalItems->execute([$cid]);
$totalItems = $totalItems->fetchColumn();

// Recent RFQs
$recentRfqs = $db->prepare("SELECT r.*, (SELECT COUNT(*) FROM rfq_items WHERE rfq_id=r.id) AS item_count FROM rfqs r WHERE r.customer_id=? ORDER BY r.created_at DESC LIMIT 5");
$recentRfqs->execute([$cid]);
$rfqs = $recentRfqs->fetchAll();

// Customer info
$custRow = $db->prepare("SELECT * FROM customers WHERE id=?");
$custRow->execute([$cid]);
$custData = $custRow->fetch();

$pageTitle  = 'My Dashboard';
$activePage = 'dashboard';
include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb"><div class="breadcrumb-inner">
    <a href="catalogue.php">Catalogue</a> <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
    <span>My Dashboard</span>
</div></div>

<div class="portal-dashboard">

    <!-- Welcome -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.75rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.5rem;font-weight:800;color:var(--text-dark);margin-bottom:0.25rem;">
                Welcome back, <?= htmlspecialchars(explode(' ', $customer['name'])[0]) ?>! 👋
            </h1>
            <p style="font-size:0.875rem;color:var(--text-light);">
                <?= htmlspecialchars($customer['company']) ?> ·
                <span class="status-badge tier-<?= $custData['tier'] ??'standard' ?>" style="font-size:0.72rem;">
                    <i class="fas fa-<?= $custData['tier']==='gold'?'crown':($custData['tier']==='silver'?'medal':'star') ?>"></i>
                    <?= ucfirst($custData['tier'] ?? 'Standard') ?> Partner
                </span>
            </p>
        </div>
        <a href="catalogue.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> New RFQ
        </a>
    </div>

    <!-- Stats -->
    <div class="dash-grid">
        <div class="dash-stat">
            <div class="dash-stat-icon" style="background:rgba(37,99,235,0.1);color:var(--primary);">
                <i class="fas fa-file-alt"></i>
            </div>
            <div>
                <div class="dash-stat-num"><?= $totalRfqs ?></div>
                <div class="dash-stat-label">Total RFQs</div>
            </div>
        </div>
        <div class="dash-stat">
            <div class="dash-stat-icon" style="background:rgba(217,119,6,0.1);color:var(--warning);">
                <i class="fas fa-clock"></i>
            </div>
            <div>
                <div class="dash-stat-num"><?= $openRfqs ?></div>
                <div class="dash-stat-label">Open RFQs</div>
            </div>
        </div>
        <div class="dash-stat">
            <div class="dash-stat-icon" style="background:rgba(22,163,74,0.1);color:var(--success);">
                <i class="fas fa-boxes"></i>
            </div>
            <div>
                <div class="dash-stat-num"><?= $totalItems ?></div>
                <div class="dash-stat-label">Units Requested</div>
            </div>
        </div>
        <div class="dash-stat">
            <div class="dash-stat-icon" style="background:rgba(249,115,22,0.1);color:var(--accent);">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div>
                <div class="dash-stat-num"><?= rfqCount() ?></div>
                <div class="dash-stat-label">Items in Cart</div>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:1.25rem;align-items:start;">

        <!-- Recent RFQs -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-history"></i> My Recent RFQs</div>
                <a href="rfqs.php" style="font-size:0.8rem;color:var(--primary);">View All</a>
            </div>
            <?php if (empty($rfqs)): ?>
            <div class="empty-state" style="padding:2rem;">
                <i class="fas fa-file-alt"></i>
                <h3>No RFQs yet</h3>
                <p>Browse the catalogue and add products to request a quotation.</p>
                <a href="catalogue.php" class="btn btn-primary btn-sm" style="margin-top:0.75rem;">Browse Catalogue</a>
            </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="portal-table">
                    <thead><tr><th>RFQ #</th><th>Items</th><th>Status</th><th>Date</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rfqs as $rfq): ?>
                    <tr>
                        <td style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($rfq['rfq_number']) ?></td>
                        <td><?= $rfq['item_count'] ?> items</td>
                        <td><span class="status-badge status-<?= $rfq['status'] ?>"><?= ucfirst($rfq['status']) ?></span></td>
                        <td style="font-size:0.78rem;color:var(--text-muted);"><?= date('d M Y', strtotime($rfq['created_at'])) ?></td>
                        <td><a href="rfq_detail.php?id=<?= $rfq['id'] ?>" style="font-size:0.78rem;color:var(--primary);">View →</a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Account Info -->
        <div>
            <div class="card" style="margin-bottom:1rem;">
                <div class="card-header"><div class="card-title"><i class="fas fa-building"></i> Business Info</div></div>
                <div class="card-body" style="font-size:0.85rem;">
                    <div style="margin-bottom:0.6rem;">
                        <span style="color:var(--text-muted);font-size:0.75rem;display:block;">Company</span>
                        <strong><?= htmlspecialchars($custData['company_name']) ?></strong>
                    </div>
                    <div style="margin-bottom:0.6rem;">
                        <span style="color:var(--text-muted);font-size:0.75rem;display:block;">Contact</span>
                        <?= htmlspecialchars($custData['contact_name']) ?>
                    </div>
                    <div style="margin-bottom:0.6rem;">
                        <span style="color:var(--text-muted);font-size:0.75rem;display:block;">Email</span>
                        <?= htmlspecialchars($custData['email']) ?>
                    </div>
                    <?php if ($custData['phone']): ?>
                    <div style="margin-bottom:0.6rem;">
                        <span style="color:var(--text-muted);font-size:0.75rem;display:block;">Phone</span>
                        <?= htmlspecialchars($custData['phone']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($custData['gstin']): ?>
                    <div style="margin-bottom:0.6rem;">
                        <span style="color:var(--text-muted);font-size:0.75rem;display:block;">GSTIN</span>
                        <code style="font-size:0.8rem;background:var(--bg-gray);padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($custData['gstin']) ?></code>
                    </div>
                    <?php endif; ?>
                    <?php if ($custData['city']): ?>
                    <div>
                        <span style="color:var(--text-muted);font-size:0.75rem;display:block;">Location</span>
                        <?= htmlspecialchars($custData['city'] . ', ' . $custData['state']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-bolt"></i> Quick Actions</div></div>
                <div class="card-body" style="display:flex;flex-direction:column;gap:0.6rem;">
                    <a href="catalogue.php" class="btn btn-primary"><i class="fas fa-search"></i> Browse Catalogue</a>
                    <a href="rfq_cart.php" class="btn btn-accent"><i class="fas fa-shopping-cart"></i> View RFQ Cart</a>
                    <a href="rfqs.php" class="btn btn-outline"><i class="fas fa-list"></i> All My RFQs</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
