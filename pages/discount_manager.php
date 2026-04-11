<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle      = 'Pricing & Discounts';
$pageIcon       = 'fas fa-tags';
$activePage     = 'discounts';
$pageBreadcrumb = 'Pricing & Discounts';
include BASE_PATH . '/includes/header.php';
requireAdmin();

$db = getDB();

// Ensure discount table
$db->exec("CREATE TABLE IF NOT EXISTS `price_tiers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tier` ENUM('standard','silver','gold','custom') NOT NULL,
    `discount_pct` DECIMAL(5,2) DEFAULT 0.00,
    `min_order_amount` DECIMAL(10,2) DEFAULT 0.00,
    `label` VARCHAR(80),
    `description` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure table exists with default data
$cnt = $db->query("SELECT COUNT(*) FROM price_tiers")->fetchColumn();
if ($cnt == 0) {
    $db->exec("INSERT INTO price_tiers (tier,discount_pct,min_order_amount,label,description) VALUES
        ('standard',  0.00,     0, 'Standard Partner',     'Default pricing for all registered partners'),
        ('silver',    5.00,  5000, 'Silver Partner',       '5% discount on all orders above ₹5,000'),
        ('gold',     10.00, 20000, 'Gold Partner',         '10% discount on all orders. Priority support.')");
}

// Ensure customer_discounts table for individual overrides
$db->exec("CREATE TABLE IF NOT EXISTS `customer_discounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL UNIQUE,
    `discount_pct` DECIMAL(5,2) DEFAULT 0.00,
    `valid_until` DATE DEFAULT NULL,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_tier') {
        $id    = (int)$_POST['tier_id'];
        $disc  = (float)$_POST['discount_pct'];
        $moa   = (float)$_POST['min_order_amount'];
        $label = sanitize($_POST['label'] ?? '');
        $desc  = sanitize($_POST['description'] ?? '');
        $db->prepare("UPDATE price_tiers SET discount_pct=?,min_order_amount=?,label=?,description=? WHERE id=?")
           ->execute([$disc, $moa, $label, $desc, $id]);
        setFlash('success', 'Pricing tier updated.');
    }

    if ($action === 'set_customer_discount') {
        $cid   = (int)$_POST['customer_id'];
        $disc  = (float)$_POST['discount_pct'];
        $valid = sanitize($_POST['valid_until'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        $db->prepare("INSERT INTO customer_discounts (customer_id,discount_pct,valid_until,notes) VALUES (?,?,?,?)
                      ON DUPLICATE KEY UPDATE discount_pct=?,valid_until=?,notes=?")
           ->execute([$cid, $disc, $valid ?: null, $notes, $disc, $valid ?: null, $notes]);
        setFlash('success', 'Custom discount saved.');
    }

    if ($action === 'remove_discount') {
        $cid = (int)$_POST['customer_id'];
        $db->prepare("DELETE FROM customer_discounts WHERE customer_id=?")->execute([$cid]);
        setFlash('success', 'Custom discount removed.');
    }

    header('Location: discount_manager.php'); exit;
}

$tiers = $db->query("SELECT * FROM price_tiers ORDER BY discount_pct")->fetchAll();
$customers = $db->query("SELECT c.*, cd.discount_pct AS custom_disc, cd.valid_until, cd.notes AS disc_notes
    FROM customers c LEFT JOIN customer_discounts cd ON cd.customer_id=c.id
    WHERE c.status='active' ORDER BY c.company_name")->fetchAll();

$tierColors = ['standard'=>'#6b7280','silver'=>'#94a3b8','gold'=>'#d97706'];
$tierIcons  = ['standard'=>'star','silver'=>'medal','gold'=>'crown'];
?>

<div class="page-body">

<!-- Tier Cards -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.25rem;margin-bottom:1.5rem;">
<?php foreach ($tiers as $tier): ?>
<div class="card" style="border-top:3px solid <?= $tierColors[$tier['tier']] ?? '#6b7280' ?>;">
    <div class="card-header" style="align-items:flex-start;">
        <div>
            <div class="card-title" style="gap:0.5rem;">
                <i class="fas fa-<?= $tierIcons[$tier['tier']] ?? 'tag' ?>" style="color:<?= $tierColors[$tier['tier']] ?? '#6b7280' ?>;"></i>
                <?= htmlspecialchars($tier['label']) ?>
            </div>
            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.2rem;"><?= htmlspecialchars($tier['description']) ?></div>
        </div>
        <div style="font-size:1.5rem;font-weight:900;color:<?= $tierColors[$tier['tier']] ?? '#6b7280' ?>;">
            <?= $tier['discount_pct'] > 0 ? $tier['discount_pct'].'%' : 'MRP' ?>
        </div>
    </div>
    <div class="card-body">
        <?php if ($tier['min_order_amount'] > 0): ?>
        <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.75rem;">
            <i class="fas fa-info-circle"></i> Minimum order: ₹<?= number_format($tier['min_order_amount'],0) ?>
        </div>
        <?php endif; ?>
        <button onclick='editTier(<?= json_encode($tier) ?>)' class="btn btn-outline btn-sm">
            <i class="fas fa-edit"></i> Edit
        </button>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Customer Discounts Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-percent"></i> Customer-Specific Discounts</div>
        <button onclick="openModal('addDiscountModal')" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Custom Discount
        </button>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Company</th><th>Tier</th><th>Custom Disc.</th><th>Valid Until</th><th>Notes</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
            <tr>
                <td>
                    <div style="font-weight:600;font-size:0.875rem;"><?= htmlspecialchars($c['company_name']) ?></div>
                    <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($c['email']) ?></div>
                </td>
                <td><span class="badge-pill badge-<?= $c['tier']==='gold'?'warning':($c['tier']==='silver'?'info':'gray') ?>"><?= ucfirst($c['tier']) ?></span></td>
                <td>
                    <?php if ($c['custom_disc'] !== null): ?>
                    <span style="font-weight:800;font-size:1rem;color:var(--success);"><?= $c['custom_disc'] ?>%</span>
                    <?php else: ?>
                    <span style="color:var(--text-muted);font-size:0.8rem;">— (tier default)</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:0.8rem;color:var(--text-muted);">
                    <?= $c['valid_until'] ? date('d M Y', strtotime($c['valid_until'])) : '—' ?>
                </td>
                <td style="font-size:0.8rem;color:var(--text-muted);max-width:150px;"><?= htmlspecialchars($c['disc_notes'] ?: '—') ?></td>
                <td>
                    <div style="display:flex;gap:0.35rem;">
                        <button class="btn btn-outline btn-sm btn-icon" onclick='setDiscount(<?= $c['id'] ?>, "<?= htmlspecialchars($c['company_name']) ?>", <?= $c['custom_disc'] ?? 0 ?>, "<?= $c['valid_until'] ?? '' ?>")' data-tooltip="Set Discount">
                            <i class="fas fa-percent"></i>
                        </button>
                        <?php if ($c['custom_disc'] !== null): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="remove_discount">
                            <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm btn-icon" data-tooltip="Remove Discount" onclick="return confirm('Remove custom discount?')">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<!-- Edit Tier Modal -->
<div class="modal-overlay" id="editTierModal">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-tags" style="color:var(--primary);"></i> Edit Pricing Tier</div>
            <button class="modal-close" onclick="closeModal('editTierModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_tier">
            <input type="hidden" name="tier_id" id="editTierId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Tier Label</label>
                    <input type="text" name="label" id="editTierLabel" class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Discount (%)</label>
                        <input type="number" name="discount_pct" id="editTierDisc" class="form-control" step="0.5" min="0" max="50">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Min. Order (₹)</label>
                        <input type="number" name="min_order_amount" id="editTierMOA" class="form-control" step="100" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="editTierDesc" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editTierModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Set Discount Modal -->
<div class="modal-overlay" id="addDiscountModal">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-percent" style="color:var(--primary);"></i> Set Custom Discount</div>
            <button class="modal-close" onclick="closeModal('addDiscountModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="set_customer_discount">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" id="discCustomerId" class="form-control" required>
                        <option value="">— Select Customer —</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Discount (%)</label>
                        <input type="number" name="discount_pct" id="discPct" class="form-control" step="0.5" min="0" max="50" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Valid Until</label>
                        <input type="date" name="valid_until" id="discValid" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Internal Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="Reason for special discount...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addDiscountModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Discount</button>
            </div>
        </form>
    </div>
</div>

<script>
function editTier(t) {
    document.getElementById('editTierId').value    = t.id;
    document.getElementById('editTierLabel').value = t.label;
    document.getElementById('editTierDisc').value  = t.discount_pct;
    document.getElementById('editTierMOA').value   = t.min_order_amount;
    document.getElementById('editTierDesc').value  = t.description || '';
    openModal('editTierModal');
}
function setDiscount(cid, name, disc, valid) {
    document.getElementById('discCustomerId').value = cid;
    document.getElementById('discPct').value        = disc || 0;
    document.getElementById('discValid').value      = valid || '';
    openModal('addDiscountModal');
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
