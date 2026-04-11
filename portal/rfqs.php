<?php
require_once __DIR__ . '/config/auth.php';
ensureB2BTables();
requireCustomerLogin();

$customer = currentCustomer();
$db  = portalDB();
$cid = $customer['id'];

$rfqs = $db->prepare("SELECT r.*, (SELECT COUNT(*) FROM rfq_items WHERE rfq_id=r.id) AS item_count FROM rfqs r WHERE r.customer_id=? ORDER BY r.created_at DESC");
$rfqs->execute([$cid]);
$rfqList = $rfqs->fetchAll();

$pageTitle  = 'My RFQs';
$activePage = 'rfqs';
include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb"><div class="breadcrumb-inner">
    <a href="dashboard.php">Dashboard</a> <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i> <span>My RFQs</span>
</div></div>

<div class="section container">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
        <div>
            <h1 class="section-title" style="margin:0;">My RFQ History</h1>
            <p style="font-size:0.875rem;color:var(--text-light);"><?= count($rfqList) ?> quotation request<?= count($rfqList)!=1?'s':'' ?></p>
        </div>
        <a href="catalogue.php" class="btn btn-primary"><i class="fas fa-plus"></i> New RFQ</a>
    </div>

    <div class="card">
        <?php if (empty($rfqList)): ?>
        <div class="empty-state" style="padding:3rem;">
            <i class="fas fa-file-alt"></i>
            <h3>No RFQs submitted yet</h3>
            <p>Browse our catalogue and submit your first request for quotation.</p>
            <a href="catalogue.php" class="btn btn-primary" style="margin-top:1rem;">Browse Catalogue</a>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="portal-table">
            <thead>
                <tr><th>RFQ Number</th><th>Items</th><th>Status</th><th>Admin Notes</th><th>Submitted</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rfqList as $r): ?>
            <tr>
                <td style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($r['rfq_number']) ?></td>
                <td><?= $r['item_count'] ?> item<?= $r['item_count']!=1?'s':'' ?></td>
                <td><span class="status-badge status-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                <td style="font-size:0.8rem;color:var(--text-muted);max-width:200px;">
                    <?= $r['admin_notes'] ? htmlspecialchars(substr($r['admin_notes'],0,80)) . (strlen($r['admin_notes'])>80?'…':'') : '—' ?>
                </td>
                <td style="font-size:0.78rem;color:var(--text-muted);white-space:nowrap;"><?= date('d M Y, h:ia', strtotime($r['created_at'])) ?></td>
                <td><a href="rfq_detail.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> View</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
