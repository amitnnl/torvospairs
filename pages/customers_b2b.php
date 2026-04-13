<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle      = 'Partner Management';
$pageIcon       = 'fas fa-handshake';
$activePage     = 'customers_b2b';
$pageBreadcrumb = 'Partner Management';
include BASE_PATH . '/includes/header.php';
requireAdmin();

$db = getDB();

// ── Ensure B2B tables exist (in case portal wasn't visited first) ─────────────
$db->exec("CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_name` VARCHAR(150) NOT NULL,
    `contact_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20),
    `gstin` VARCHAR(20),
    `address` TEXT,
    `city` VARCHAR(80),
    `state` VARCHAR(80),
    `pin` VARCHAR(10),
    `tier` ENUM('standard','silver','gold') DEFAULT 'standard',
    `status` ENUM('pending','active','suspended') DEFAULT 'pending',
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->exec("CREATE TABLE IF NOT EXISTS `rfqs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL DEFAULT 0,
    `rfq_number` VARCHAR(30) UNIQUE,
    `status` ENUM('submitted','reviewing','quoted','accepted','rejected','closed') DEFAULT 'submitted',
    `customer_notes` TEXT,
    `admin_notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->exec("CREATE TABLE IF NOT EXISTS `rfq_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `rfq_id` INT NOT NULL DEFAULT 0,
    `product_id` INT NOT NULL DEFAULT 0,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) DEFAULT 0.00,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->exec("CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(30) UNIQUE,
    `rfq_id` INT DEFAULT NULL,
    `customer_id` INT NOT NULL DEFAULT 0,
    `status` ENUM('pending','confirmed','processing','dispatched','delivered','cancelled') DEFAULT 'pending',
    `subtotal` DECIMAL(10,2) DEFAULT 0.00,
    `gst_rate` DECIMAL(5,2) DEFAULT 18.00,
    `gst_amount` DECIMAL(10,2) DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) DEFAULT 0.00,
    `shipping_address` TEXT,
    `payment_status` ENUM('unpaid','paid','partial') DEFAULT 'unpaid',
    `tracking_info` VARCHAR(255),
    `admin_notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->exec("CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL DEFAULT 0,
    `product_id` INT NOT NULL DEFAULT 0,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) DEFAULT 0.00,
    `total_price` DECIMAL(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Handle Actions ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cid    = (int)($_POST['customer_id'] ?? 0);

    if ($action === 'update_customer' && $cid) {
        $tier   = sanitize($_POST['tier'] ?? 'standard');
        $status = sanitize($_POST['status'] ?? 'active');
        $notes  = sanitize($_POST['notes'] ?? '');
        $db->prepare("UPDATE customers SET tier=?, status=?, notes=? WHERE id=?")->execute([$tier, $status, $notes, $cid]);
        setFlash('success', 'Customer updated successfully.');
    }

    if ($action === 'delete' && $cid) {
        $db->prepare("DELETE FROM customers WHERE id=?")->execute([$cid]);
        setFlash('success', 'Customer removed.');
    }

    header('Location: customers_b2b.php'); exit;
}

// ── Filters ───────────────────────────────────────────────────────────────────
$statusFilter = sanitize($_GET['status'] ?? '');
$sql = "SELECT c.*, (SELECT COUNT(*) FROM rfqs WHERE customer_id=c.id) AS rfq_count FROM customers c";
$params = [];
if ($statusFilter) { $sql .= " WHERE c.status=?"; $params[] = $statusFilter; }
$sql .= " ORDER BY c.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Stats
$pendingCount   = $db->query("SELECT COUNT(*) FROM customers WHERE status='pending'")->fetchColumn();
$activeCount    = $db->query("SELECT COUNT(*) FROM customers WHERE status='active'")->fetchColumn();
$suspendedCount = $db->query("SELECT COUNT(*) FROM customers WHERE status='suspended'")->fetchColumn();
?>

<div class="page-body">

<!-- Stats Row -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;">
    <?php foreach ([
        ['All',       count($customers), 'fas fa-users',           'var(--primary)',  ''],
        ['Pending',   $pendingCount,     'fas fa-hourglass-half',  'var(--warning)',  'pending'],
        ['Active',    $activeCount,      'fas fa-check-circle',    'var(--success)',  'active'],
        ['Suspended', $suspendedCount,   'fas fa-ban',             'var(--danger)',   'suspended'],
    ] as [$label, $count, $icon, $color, $filter]): ?>
    <a href="?status=<?= $filter ?>" style="text-decoration:none;">
        <div class="card" style="padding:1rem;display:flex;align-items:center;gap:0.85rem;border-left:3px solid <?= $color ?>;transition:all 0.25s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
            <div style="width:40px;height:40px;border-radius:10px;background:<?= $color ?>22;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="<?= $icon ?>" style="color:<?= $color ?>;"></i>
            </div>
            <div>
                <div style="font-size:1.4rem;font-weight:800;color:var(--text-primary);"><?= $count ?></div>
                <div style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;"><?= $label ?></div>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-building"></i> B2B Customers <?php if ($statusFilter): ?><span style="font-size:0.75rem;color:var(--text-muted);">· <?= ucfirst($statusFilter) ?></span><?php endif; ?></div>
        <div style="display:flex;gap:0.5rem;">
            <a href="customers_b2b.php" class="btn btn-outline btn-sm"><i class="fas fa-sync"></i> All</a>
            <a href="../portal/catalogue.php" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-external-link-alt"></i> Preview Portal</a>
        </div>
    </div>

    <?php if (empty($customers)): ?>
    <div class="empty-state">
        <i class="fas fa-building"></i>
        <h3>No B2B customers yet</h3>
        <p>Customers who register on the portal will appear here for approval.</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Company</th><th>Contact</th><th>Location</th><th>GSTIN</th><th>Tier</th><th>Status</th><th>RFQs</th><th>Joined</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
            <tr>
                <td>
                    <div style="font-weight:700;font-size:0.875rem;"><?= htmlspecialchars($c['company_name']) ?></div>
                    <?php if ($c['notes']): ?>
                    <div style="font-size:0.7rem;color:var(--text-muted);margin-top:2px;" title="<?= htmlspecialchars($c['notes']) ?>">
                        <i class="fas fa-sticky-note"></i> Has notes
                    </div>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="font-size:0.85rem;"><?= htmlspecialchars($c['contact_name']) ?></div>
                    <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($c['email']) ?></div>
                    <?php if ($c['phone']): ?>
                    <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($c['phone']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-size:0.82rem;color:var(--text-muted);"><?= htmlspecialchars(implode(', ', array_filter([$c['city'], $c['state']]))) ?: '—' ?></td>
                <td style="font-size:0.8rem;">
                    <?= $c['gstin'] ? '<code style="font-size:0.72rem;background:var(--bg-card2);padding:2px 5px;border-radius:4px;">' . htmlspecialchars($c['gstin']) . '</code>' : '<span style="color:var(--text-muted);">—</span>' ?>
                </td>
                <td>
                    <?php $tierColors = ['standard'=>'badge-gray','silver'=>'badge-info','gold'=>'badge-warning']; ?>
                    <span class="badge-pill <?= $tierColors[$c['tier']] ?? 'badge-gray' ?>">
                        <i class="fas fa-<?= $c['tier']==='gold'?'crown':($c['tier']==='silver'?'medal':'star') ?>"></i>
                        <?= ucfirst($c['tier']) ?>
                    </span>
                </td>
                <td>
                    <?php $statusColors = ['pending'=>'badge-warning','active'=>'badge-success','suspended'=>'badge-danger']; ?>
                    <span class="badge-pill <?= $statusColors[$c['status']] ?? 'badge-gray' ?>">
                        <?= ucfirst($c['status']) ?>
                    </span>
                </td>
                <td style="text-align:center;font-weight:700;color:var(--primary);"><?= $c['rfq_count'] ?></td>
                <td style="font-size:0.75rem;color:var(--text-muted);"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:0.4rem;">
                        <button class="btn btn-outline btn-sm btn-icon" onclick='editCustomer(<?= json_encode($c) ?>)' data-tooltip="Manage">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="rfq_manager.php?customer=<?= $c['id'] ?>" class="btn btn-outline btn-sm btn-icon" data-tooltip="View RFQs">
                            <i class="fas fa-file-invoice"></i>
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
</div>

<!-- Edit Customer Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-building" style="color:var(--primary);"></i> Manage Customer</div>
            <button class="modal-close" onclick="closeModal('editModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_customer">
            <input type="hidden" name="customer_id" id="editId">
            <div class="modal-body">
                <div style="background:var(--bg-card2);border-radius:var(--radius-sm);padding:0.9rem;margin-bottom:1rem;">
                    <div id="editCompany" style="font-weight:700;font-size:0.95rem;"></div>
                    <div id="editContact" style="font-size:0.8rem;color:var(--text-muted);"></div>
                    <div id="editEmail" style="font-size:0.8rem;color:var(--text-muted);"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Partner Tier</label>
                        <select name="tier" id="editTier" class="form-control">
                            <option value="standard">Standard</option>
                            <option value="silver">Silver</option>
                            <option value="gold">Gold</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <select name="status" id="editStatus" class="form-control">
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Internal Notes</label>
                    <textarea name="notes" id="editNotes" class="form-control" rows="3" placeholder="Internal notes about this customer..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Remove this customer account?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="customer_id" id="deleteId">
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Remove</button>
                </form>
                <div style="display:flex;gap:0.5rem;margin-left:auto;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function editCustomer(c) {
    document.getElementById('editId').value      = c.id;
    document.getElementById('deleteId').value    = c.id;
    document.getElementById('editCompany').textContent = c.company_name;
    document.getElementById('editContact').textContent = c.contact_name;
    document.getElementById('editEmail').textContent   = c.email;
    document.getElementById('editTier').value    = c.tier;
    document.getElementById('editStatus').value  = c.status;
    document.getElementById('editNotes').value   = c.notes || '';
    openModal('editModal');
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
