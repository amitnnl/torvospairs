<?php
require_once __DIR__ . '/config/auth.php';
ensureB2BTables();
requireCustomerLogin();

$customer = currentCustomer();
$db  = portalDB();
$cid = $customer['id'];

// Ensure orders tables
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

$orders = $db->prepare("SELECT * FROM orders WHERE customer_id=? ORDER BY created_at DESC");
$orders->execute([$cid]);
$orderList = $orders->fetchAll();

$statusSteps = ['pending','confirmed','processing','dispatched','delivered'];
$statusColors = ['pending'=>'#f97316','confirmed'=>'#2563eb','processing'=>'#6366f1','dispatched'=>'#0891b2','delivered'=>'#16a34a','cancelled'=>'#dc2626'];

$pageTitle  = 'My Orders';
$activePage = 'orders';
include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb"><div class="breadcrumb-inner">
    <a href="dashboard.php">Dashboard</a>
    <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
    <span>My Orders</span>
</div></div>

<div class="section container">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 class="section-title" style="margin:0;">My Orders</h1>
            <p style="font-size:0.875rem;color:var(--text-light);"><?= count($orderList) ?> order<?= count($orderList)!=1?'s':'' ?></p>
        </div>
        <a href="catalogue.php" class="btn btn-primary"><i class="fas fa-plus"></i> New RFQ</a>
    </div>

    <?php if (empty($orderList)): ?>
    <div class="empty-state card" style="padding:3rem;">
        <i class="fas fa-truck"></i>
        <h3>No orders yet</h3>
        <p>Submit an RFQ and our team will create your order once we confirm pricing.</p>
        <a href="rfq_cart.php" class="btn btn-primary" style="margin-top:1rem;">View RFQ Cart</a>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:1.25rem;">
    <?php foreach ($orderList as $o):
        $stepIdx = array_search($o['status'], $statusSteps);
        $isCancelled = $o['status'] === 'cancelled';
    ?>
    <div class="card" style="overflow:visible;">
        <!-- Order Header -->
        <div style="padding:1.1rem 1.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
            <div style="display:flex;align-items:center;gap:1rem;">
                <div>
                    <div style="font-weight:800;font-size:1rem;color:var(--primary);"><?= htmlspecialchars($o['order_number']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-light);">Placed <?= date('d M Y', strtotime($o['created_at'])) ?></div>
                </div>
                <span class="status-badge status-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span>
                <span class="status-badge <?= $o['payment_status']==='paid'?'status-accepted':($o['payment_status']==='partial'?'status-reviewing':'status-rejected') ?>">
                    <i class="fas fa-<?= $o['payment_status']==='paid'?'check-circle':'clock' ?>"></i>
                    <?= ucfirst($o['payment_status']) ?>
                </span>
            </div>
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <div style="text-align:right;">
                    <div style="font-size:0.72rem;color:var(--text-light);">Order Total</div>
                    <div style="font-size:1.15rem;font-weight:800;color:var(--primary);">₹<?= number_format($o['total_amount'],2) ?></div>
                </div>
                <a href="order_track.php?id=<?= $o['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-map-marker-alt"></i> Track</a>
                <a href="order_invoice_portal.php?id=<?= $o['id'] ?>" class="btn btn-outline btn-sm"><i class="fas fa-file-invoice"></i> Invoice</a>
            </div>
        </div>

        <!-- Progress Tracker (not for cancelled) -->
        <?php if (!$isCancelled): ?>
        <div style="padding:1.25rem 1.5rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;position:relative;">
                <!-- Line -->
                <div style="position:absolute;top:14px;left:5%;right:5%;height:3px;background:var(--border);z-index:0;border-radius:2px;">
                    <div style="width:<?= min(100, max(0, is_int($stepIdx) ? ($stepIdx / (count($statusSteps)-1)) * 100 : 0)) ?>%;height:100%;background:var(--primary);border-radius:2px;transition:width 0.5s;"></div>
                </div>
                <?php foreach ($statusSteps as $idx => $step):
                    $done   = is_int($stepIdx) && $idx <= $stepIdx;
                    $active = is_int($stepIdx) && $idx === $stepIdx;
                    $icons  = ['pending'=>'clock','confirmed'=>'check','processing'=>'cog','dispatched'=>'truck','delivered'=>'box-open'];
                ?>
                <div style="display:flex;flex-direction:column;align-items:center;position:relative;z-index:1;flex:1;">
                    <div style="width:28px;height:28px;border-radius:50%;background:<?= $done?'var(--primary)':'var(--border)' ?>;border:2px solid <?= $done?'var(--primary)':'var(--border)' ?>;display:flex;align-items:center;justify-content:center;transition:all 0.3s;<?= $active?'box-shadow:0 0 0 4px rgba(37,99,235,0.2);':'' ?>">
                        <i class="fas fa-<?= $icons[$step] ?>" style="font-size:0.7rem;color:<?= $done?'#fff':'var(--text-muted)' ?>;"></i>
                    </div>
                    <div style="font-size:0.68rem;font-weight:<?= $active?'700':'500' ?>;color:<?= $done?'var(--primary)':'var(--text-muted)' ?>;margin-top:0.35rem;text-align:center;">
                        <?= ucfirst($step) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($o['tracking_info']): ?>
            <div style="margin-top:1rem;background:rgba(37,99,235,0.05);border:1px solid rgba(37,99,235,0.15);border-radius:8px;padding:0.65rem 1rem;font-size:0.82rem;color:var(--text-medium);">
                <i class="fas fa-truck" style="color:var(--primary);"></i>
                <strong>Tracking:</strong> <?= htmlspecialchars($o['tracking_info']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="padding:1rem 1.5rem;">
            <div class="alert alert-error" style="margin:0;font-size:0.82rem;">
                <i class="fas fa-times-circle"></i> This order has been cancelled.
                <?= $o['admin_notes'] ? htmlspecialchars($o['admin_notes']) : '' ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
