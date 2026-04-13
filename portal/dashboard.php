<?php
require_once __DIR__ . '/config/auth.php';
requireCustomerLogin();

$customer = currentCustomer();
$db  = portalDB();
$cid = $customer['id'];

// Stats
$totalRfqs   = (int)$db->prepare("SELECT COUNT(*) FROM rfqs WHERE customer_id=?")->execute([$cid]) ? $db->query("SELECT COUNT(*) FROM rfqs WHERE customer_id=$cid")->fetchColumn() : 0;
$stmt = $db->prepare("SELECT COUNT(*) FROM rfqs WHERE customer_id=?"); $stmt->execute([$cid]); $totalRfqs = (int)$stmt->fetchColumn();
$stmt = $db->prepare("SELECT COUNT(*) FROM rfqs WHERE customer_id=? AND status='quoted'"); $stmt->execute([$cid]); $quotedRfqs = (int)$stmt->fetchColumn();
$stmt = $db->prepare("SELECT COUNT(*) FROM rfqs WHERE customer_id=? AND status='invoiced'"); $stmt->execute([$cid]); $invoicedRfqs = (int)$stmt->fetchColumn();
$stmt = $db->prepare("SELECT COALESCE(SUM(ri.quantity),0) FROM rfq_items ri JOIN rfqs r ON ri.rfq_id=r.id WHERE r.customer_id=?"); $stmt->execute([$cid]); $totalItems = (int)$stmt->fetchColumn();

// Notifications (unread)
$stmt = $db->prepare("SELECT * FROM notifications WHERE customer_id=? ORDER BY created_at DESC LIMIT 10"); $stmt->execute([$cid]);
$notifs = $stmt->fetchAll();
$unread = array_filter($notifs, fn($n) => !$n['is_read']);

// Recent RFQs
$stmt = $db->prepare("SELECT r.*, (SELECT COUNT(*) FROM rfq_items WHERE rfq_id=r.id) AS item_count FROM rfqs r WHERE r.customer_id=? ORDER BY r.created_at DESC LIMIT 6");
$stmt->execute([$cid]); $rfqs = $stmt->fetchAll();

// Customer full row
$stmt = $db->prepare("SELECT * FROM customers WHERE id=?"); $stmt->execute([$cid]); $custData = $stmt->fetch();

// Mark notifications read
if (!empty($unread)) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE customer_id=? AND is_read=0")->execute([$cid]);
}

$pageTitle  = 'My Dashboard';
$activePage = 'dashboard';
include __DIR__ . '/includes/header.php';
?>


<?php if (($custData['status'] ?? '') === 'pending'): ?>
<div style="background:linear-gradient(135deg,rgba(217,119,6,0.12),rgba(217,119,6,0.06));border:1px solid rgba(217,119,6,0.25);border-radius:12px;padding:1.25rem 1.5rem;margin:1rem var(--section-px) 0;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
    <i class="fas fa-hourglass-half" style="font-size:1.75rem;color:var(--warning);"></i>
    <div>
        <strong style="font-size:0.95rem;color:var(--text-dark);">Account Pending Approval</strong>
        <p style="font-size:0.82rem;color:var(--text-light);margin-top:0.2rem;">Your partner application is under review. You can browse the catalogue but cannot submit RFQs until approved.</p>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($unread)): ?>
<!-- Notifications -->
<div style="margin:1rem var(--section-px) 0;">
<?php foreach ($unread as $n): ?>
<div style="background:linear-gradient(135deg,rgba(124,58,237,0.08),rgba(124,58,237,0.04));border:1px solid rgba(124,58,237,0.2);border-radius:10px;padding:0.85rem 1.25rem;display:flex;align-items:flex-start;gap:1rem;margin-bottom:0.6rem;">
    <i class="fas <?= $n['type']==='rfq_quoted'?'fa-file-invoice-dollar':($n['type']==='rfq_invoiced'?'fa-receipt':'fa-bell') ?>" style="color:#7c3aed;margin-top:2px;font-size:1rem;"></i>
    <div style="flex:1;">
        <div style="font-weight:700;font-size:0.875rem;color:var(--text-dark);"><?= htmlspecialchars($n['title']) ?></div>
        <div style="font-size:0.8rem;color:var(--text-light);margin-top:0.2rem;"><?= htmlspecialchars($n['message']) ?></div>
    </div>
    <?php if ($n['rfq_id']): ?>
    <a href="rfq_view.php?id=<?= $n['rfq_id'] ?>" class="btn btn-sm" style="background:#7c3aed;color:#fff;white-space:nowrap;flex-shrink:0;">
        <?= $n['type']==='rfq_quoted' ? 'Review Quote' : 'View' ?> <i class="fas fa-arrow-right"></i>
    </a>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="breadcrumb"><div class="breadcrumb-inner">
    <a href="catalogue.php">Catalogue</a> <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
    <span>My Dashboard</span>
</div></div>

<div class="portal-dashboard">

    <!-- Welcome -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.75rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.5rem;font-weight:800;color:var(--text-dark);margin-bottom:0.25rem;">
                Welcome back, <?= htmlspecialchars(explode(' ', $customer['contact_name'] ?? $customer['name'])[0]) ?>! 👋
            </h1>
            <p style="font-size:0.875rem;color:var(--text-light);">
                <?= htmlspecialchars($custData['company_name'] ?? '') ?> ·
                <span class="status-badge" style="font-size:0.72rem;background:rgba(22,163,74,0.1);color:var(--success);">
                    <?= ucfirst($custData['tier'] ?? 'standard') ?> Partner
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
        <div class="dash-stat" style="<?= $quotedRfqs > 0 ? 'border:2px solid rgba(124,58,237,0.3);background:rgba(124,58,237,0.04);' : '' ?>">
            <div class="dash-stat-icon" style="background:rgba(124,58,237,0.1);color:#7c3aed;">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div>
                <div class="dash-stat-num" style="<?= $quotedRfqs > 0 ? 'color:#7c3aed;' : '' ?>"><?= $quotedRfqs ?></div>
                <div class="dash-stat-label">Quotations Ready <?= $quotedRfqs > 0 ? '<a href="rfqs.php" style="font-size:0.7rem;color:#7c3aed;">Review →</a>' : '' ?></div>
            </div>
        </div>
        <div class="dash-stat">
            <div class="dash-stat-icon" style="background:rgba(22,163,74,0.1);color:var(--success);">
                <i class="fas fa-receipt"></i>
            </div>
            <div>
                <div class="dash-stat-num"><?= $invoicedRfqs ?></div>
                <div class="dash-stat-label">Invoiced Orders</div>
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

    <div class="dash-main-grid" style="display:grid;grid-template-columns:1.5fr 1fr;gap:1.25rem;align-items:start;">

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
                    <?php foreach ($rfqs as $rfq):
                        $isQuoted   = ($rfq['status'] === 'quoted');
                        $isInvoiced = ($rfq['status'] === 'invoiced');
                    ?>
                    <tr style="<?= $isQuoted ? 'background:rgba(124,58,237,0.04);' : '' ?>">
                        <td style="font-weight:700;color:var(--primary);">
                            <?= htmlspecialchars($rfq['rfq_number']) ?>
                            <?php if ($isQuoted): ?><span style="background:#7c3aed;color:#fff;font-size:0.6rem;padding:1px 6px;border-radius:8px;margin-left:4px;">QUOTE</span><?php endif; ?>
                        </td>
                        <td><?= $rfq['item_count'] ?> items</td>
                        <td><span class="status-badge status-<?= $rfq['status'] ?>"><?= ucfirst($rfq['status']) ?></span></td>
                        <td style="font-size:0.78rem;color:var(--text-muted);"><?= date('d M Y', strtotime($rfq['created_at'])) ?></td>
                        <td style="display:flex;gap:0.35rem;flex-wrap:wrap;">
                            <a href="rfq_view.php?id=<?= $rfq['id'] ?>" style="font-size:0.78rem;color:var(--primary);white-space:nowrap;"><?= $isQuoted ? 'Review →' : 'View →' ?></a>
                            <?php if ($isInvoiced): ?>
                            <a href="invoice_view.php?rfq=<?= $rfq['id'] ?>" style="font-size:0.78rem;color:var(--success);white-space:nowrap;" target="_blank">Invoice ↗</a>
                            <?php endif; ?>
                        </td>
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
