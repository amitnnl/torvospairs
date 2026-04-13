<?php
/**
 * Admin — Partner Applications
 * Approve / Reject pending B2B customer registrations
 */
define('BASE_PATH', dirname(__DIR__));
$pageTitle = 'Partner Applications';
$activePage = 'partner_applications';
include BASE_PATH . '/includes/header.php';
requireAdmin();

$db = getDB();

// Handle approve / reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid    = (int)($_POST['customer_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['rejection_reason'] ?? '');

    if ($cid && $action === 'approve') {
        $adminId = $user['id'] ?? 0;
        $db->prepare("UPDATE customers SET status='active', approved_at=NOW(), approved_by=?, rejection_reason=NULL WHERE id=?")
           ->execute([$adminId, $cid]);
        // Send in-portal notification
        try {
            $db->prepare("INSERT INTO notifications (customer_id, type, title, message) VALUES (?, 'general', 'Account Approved!', 'Congratulations! Your TORVO SPAIR partner account has been approved. You can now submit RFQs and receive quotations.')")
               ->execute([$cid]);
        } catch (PDOException $e) { }
        setFlash('success', 'Partner account approved successfully.');
    } elseif ($cid && $action === 'reject') {
        $db->prepare("UPDATE customers SET status='suspended', rejection_reason=? WHERE id=?")
           ->execute([$reason ?: 'Application not approved.', $cid]);
        try {
            $db->prepare("INSERT INTO notifications (customer_id, type, title, message) VALUES (?, 'general', 'Application Update', 'Your partner application was not approved at this time. Please contact us for more information.')")
               ->execute([$cid]);
        } catch (PDOException $e) { }
        setFlash('error', 'Partner application rejected.');
    }
    header('Location: partner_applications.php'); exit;
}

// Tabs: all / pending / active / suspended
$tab    = $_GET['tab'] ?? 'pending';
$where  = match($tab) {
    'active'    => "status = 'active'",
    'suspended' => "status = 'suspended'",
    default     => "status = 'pending'",
};

$customers = $db->query("SELECT * FROM customers WHERE $where ORDER BY created_at DESC")->fetchAll();
$counts    = $db->query("SELECT status, COUNT(*) cnt FROM customers GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-handshake"></i> Partner Applications</h1>
        <p class="page-subtitle">Review and approve B2B partner registrations</p>
    </div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:0.5rem;margin-bottom:1.5rem;flex-wrap:wrap;">
    <?php foreach (['pending'=>'warning','active'=>'success','suspended'=>'danger'] as $t => $color): ?>
    <a href="?tab=<?= $t ?>" style="padding:0.5rem 1.25rem;border-radius:8px;font-size:0.875rem;font-weight:600;text-decoration:none;
        background:<?= $tab===$t ? "var(--$color)" : '#f1f5f9' ?>;
        color:<?= $tab===$t ? '#fff' : 'var(--text-medium)' ?>;">
        <?= ucfirst($t) ?>
        <span style="background:rgba(255,255,255,0.3);padding:1px 7px;border-radius:10px;font-size:0.75rem;margin-left:4px;">
            <?= $counts[$t] ?? 0 ?>
        </span>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($customers)): ?>
<div class="empty-state">
    <i class="fas fa-inbox"></i>
    <h3>No <?= $tab ?> applications</h3>
    <p>When partners register, they'll appear here for review.</p>
</div>
<?php else: ?>
<div style="display:grid;gap:1rem;">
<?php foreach ($customers as $c): ?>
<div style="background:#fff;border:1px solid var(--border);border-radius:12px;padding:1.5rem;display:flex;align-items:flex-start;gap:1.5rem;flex-wrap:wrap;">
    <!-- Avatar -->
    <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem;font-weight:800;flex-shrink:0;">
        <?= strtoupper(substr($c['company_name'],0,1)) ?>
    </div>
    <!-- Info -->
    <div style="flex:1;min-width:200px;">
        <div style="font-size:1rem;font-weight:800;color:var(--text-dark);"><?= htmlspecialchars($c['company_name']) ?></div>
        <div style="font-size:0.85rem;color:var(--text-light);margin-bottom:0.5rem;"><?= htmlspecialchars($c['contact_name']) ?></div>
        <div style="display:flex;flex-wrap:wrap;gap:0.75rem;font-size:0.8rem;color:var(--text-medium);">
            <span><i class="fas fa-envelope" style="color:var(--primary);width:14px;"></i> <?= htmlspecialchars($c['email']) ?></span>
            <?php if ($c['phone']): ?><span><i class="fas fa-phone" style="color:var(--primary);width:14px;"></i> <?= htmlspecialchars($c['phone']) ?></span><?php endif; ?>
            <?php if ($c['city']): ?><span><i class="fas fa-map-marker-alt" style="color:var(--primary);width:14px;"></i> <?= htmlspecialchars($c['city']) ?><?= $c['state'] ? ', '.htmlspecialchars($c['state']) : '' ?></span><?php endif; ?>
            <?php if ($c['gstin']): ?><span><i class="fas fa-file-invoice" style="color:var(--primary);width:14px;"></i> GSTIN: <?= htmlspecialchars($c['gstin']) ?></span><?php endif; ?>
        </div>
        <?php if ($c['notes']): ?>
        <div style="margin-top:0.75rem;background:var(--bg-gray);padding:0.6rem 0.85rem;border-radius:8px;font-size:0.82rem;color:var(--text-medium);border-left:3px solid var(--primary);">
            <strong>Partner's Note:</strong> <?= htmlspecialchars($c['notes']) ?>
        </div>
        <?php endif; ?>
        <?php if ($c['rejection_reason'] && $c['status']==='suspended'): ?>
        <div style="margin-top:0.5rem;background:rgba(220,38,38,0.05);padding:0.5rem 0.75rem;border-radius:8px;font-size:0.8rem;color:var(--danger);border-left:3px solid var(--danger);">
            <strong>Rejection reason:</strong> <?= htmlspecialchars($c['rejection_reason']) ?>
        </div>
        <?php endif; ?>
        <div style="margin-top:0.5rem;font-size:0.75rem;color:var(--text-muted);">Applied: <?= date('d M Y, h:i A', strtotime($c['created_at'])) ?></div>
    </div>
    <!-- Actions -->
    <?php if ($c['status'] === 'pending'): ?>
    <div style="display:flex;flex-direction:column;gap:0.5rem;flex-shrink:0;">
        <form method="POST">
            <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="btn btn-success btn-sm" style="background:var(--success);color:#fff;border:none;"
                onclick="return confirm('Approve <?= htmlspecialchars($c['company_name']) ?> as an official partner?')">
                <i class="fas fa-check"></i> Approve
            </button>
        </form>
        <button type="button" class="btn btn-sm" style="background:var(--danger);color:#fff;border:none;"
            onclick="document.getElementById('reject-<?= $c['id'] ?>').style.display='block'">
            <i class="fas fa-times"></i> Reject
        </button>
        <!-- Reject form (hidden) -->
        <div id="reject-<?= $c['id'] ?>" style="display:none;margin-top:0.5rem;">
            <form method="POST">
                <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <textarea name="rejection_reason" placeholder="Reason (optional)" rows="2"
                    style="width:200px;padding:0.5rem;border:1px solid var(--border);border-radius:6px;font-family:inherit;font-size:0.8rem;resize:vertical;"></textarea>
                <button type="submit" style="margin-top:0.35rem;width:100%;padding:0.4rem;background:var(--danger);color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:0.8rem;">
                    Confirm Reject
                </button>
            </form>
        </div>
    </div>
    <?php elseif ($c['status'] === 'active'): ?>
    <div>
        <span style="background:rgba(22,163,74,0.1);color:var(--success);padding:4px 12px;border-radius:20px;font-size:0.75rem;font-weight:700;">
            <i class="fas fa-check-circle"></i> Active Partner
        </span>
        <?php if ($c['approved_at']): ?>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.3rem;">Approved: <?= date('d M Y', strtotime($c['approved_at'])) ?></div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div>
        <span style="background:rgba(220,38,38,0.1);color:var(--danger);padding:4px 12px;border-radius:20px;font-size:0.75rem;font-weight:700;">
            <i class="fas fa-ban"></i> Suspended
        </span>
        <form method="POST" style="margin-top:0.5rem;">
            <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
            <input type="hidden" name="action" value="approve">
            <button type="submit" class="btn btn-sm" style="background:var(--success);color:#fff;border:none;font-size:0.78rem;">
                <i class="fas fa-redo"></i> Re-activate
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
