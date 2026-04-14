<?php
require_once __DIR__ . '/config/auth.php';

requireCustomerLogin();

$customer = currentCustomer();
$db  = portalDB();
$cid = $customer['id'];
$oid = (int)($_GET['id'] ?? 0);

$db->exec("CREATE TABLE IF NOT EXISTS `orders` (`id` INT AUTO_INCREMENT PRIMARY KEY, `order_number` VARCHAR(30) UNIQUE, `rfq_id` INT DEFAULT NULL, `customer_id` INT NOT NULL DEFAULT 0, `status` ENUM('pending','confirmed','processing','dispatched','delivered','cancelled') DEFAULT 'pending', `subtotal` DECIMAL(10,2) DEFAULT 0.00, `gst_rate` DECIMAL(5,2) DEFAULT 18.00, `gst_amount` DECIMAL(10,2) DEFAULT 0.00, `total_amount` DECIMAL(10,2) DEFAULT 0.00, `shipping_address` TEXT, `payment_status` ENUM('unpaid','paid','partial') DEFAULT 'unpaid', `tracking_info` VARCHAR(255), `admin_notes` TEXT, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$order = $db->prepare("SELECT * FROM orders WHERE id=? AND customer_id=?");
$order->execute([$oid, $cid]);
$o = $order->fetch();
if (!$o) { header('Location: orders.php'); exit; }

// Handle Razorpay Callback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['razorpay_payment_id'])) {
    $payId = $_POST['razorpay_payment_id'];
    if ($payId && $o['payment_status'] !== 'paid') {
        $db->prepare("UPDATE orders SET payment_status='paid', updated_at=NOW() WHERE id=?")->execute([$oid]);
        setPortalFlash('success', "Payment successful! Ref: $payId");
        header('Location: order_track.php?id=' . $oid); exit;
    }
}

$steps = ['pending','confirmed','processing','dispatched','delivered'];
$stepIdx = array_search($o['status'], $steps);

$pageTitle  = 'Track Order ' . $o['order_number'];
$activePage = 'orders';
include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb"><div class="breadcrumb-inner">
    <a href="orders.php">My Orders</a>
    <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
    <span>Track <?= htmlspecialchars($o['order_number']) ?></span>
</div></div>

<div class="section container" style="max-width:700px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.3rem;font-weight:800;color:var(--text-dark);"><?= htmlspecialchars($o['order_number']) ?></h1>
            <p style="font-size:0.82rem;color:var(--text-light);">Placed <?= date('d M Y', strtotime($o['created_at'])) ?></p>
        </div>
        <a href="orders.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All Orders</a>
    </div>

    <!-- Status Card -->
    <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-body">
            <?php if ($o['status'] === 'cancelled'): ?>
            <div class="alert alert-error" style="margin:0;">
                <i class="fas fa-times-circle"></i> This order was cancelled. Please contact us for assistance.
            </div>
            <?php else: ?>
            <!-- Steps -->
            <div style="position:relative;padding:0 1rem;margin-bottom:1.5rem;">
                <div style="position:absolute;top:18px;left:8%;right:8%;height:4px;background:var(--border);border-radius:2px;z-index:0;">
                    <div style="width:<?= is_int($stepIdx) ? ($stepIdx/4)*100 : 0 ?>%;height:100%;background:linear-gradient(90deg,var(--primary),var(--primary-light));border-radius:2px;transition:width 0.6s;"></div>
                </div>
                <div style="display:flex;justify-content:space-between;position:relative;z-index:1;">
                <?php
                $icons = ['pending'=>'clock','confirmed'=>'check-circle','processing'=>'cog','dispatched'=>'shipping-fast','delivered'=>'box-open'];
                $labels = ['pending'=>'Order Placed','confirmed'=>'Confirmed','processing'=>'Processing','dispatched'=>'Dispatched','delivered'=>'Delivered'];
                foreach ($steps as $idx => $step):
                    $done   = is_int($stepIdx) && $idx <= $stepIdx;
                    $active = is_int($stepIdx) && $idx === $stepIdx;
                ?>
                <div style="display:flex;flex-direction:column;align-items:center;flex:1;">
                    <div style="width:36px;height:36px;border-radius:50%;background:<?= $done?'linear-gradient(135deg,var(--primary),var(--primary-light))':'var(--bg-gray2)' ?>;display:flex;align-items:center;justify-content:center;margin-bottom:0.6rem;transition:all 0.3s;<?= $active?'box-shadow:0 0 0 4px rgba(37,99,235,0.2);':'' ?>">
                        <i class="fas fa-<?= $icons[$step] ?>" style="font-size:0.85rem;color:<?= $done?'#fff':'var(--text-muted)' ?>;"></i>
                    </div>
                    <div style="font-size:0.68rem;font-weight:<?= $active?'700':'500' ?>;color:<?= $done?'var(--primary)':'var(--text-muted)' ?>;text-align:center;max-width:70px;">
                        <?= $labels[$step] ?>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>

            <!-- Current status message -->
            <?php
            $msgs = [
                'pending'    => ['We have received your order and it\'s awaiting confirmation.', 'info'],
                'confirmed'  => ['Your order has been confirmed and is being prepared.', 'info'],
                'processing' => ['Your order is currently being picked and packed.', 'info'],
                'dispatched' => ['Your order has been dispatched!', 'success'],
                'delivered'  => ['Your order has been delivered. Thank you for your business!', 'success'],
            ];
            [$msg, $type] = $msgs[$o['status']] ?? ['Status unknown.', 'info'];
            ?>
            <div class="alert alert-<?= $type ?>" style="margin-bottom:0;margin-top:0.5rem;">
                <i class="fas fa-<?= $type==='success'?'check-circle':'info-circle' ?>"></i>
                <?= $msg ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tracking Info -->
    <?php if ($o['tracking_info']): ?>
    <div class="card" style="margin-bottom:1.25rem;border-left:4px solid var(--primary);">
        <div class="card-body">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(37,99,235,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-truck" style="color:var(--primary);"></i>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Tracking Number</div>
                    <div style="font-weight:700;font-size:1rem;color:var(--text-dark);"><?= htmlspecialchars($o['tracking_info']) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Order Summary -->
    <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-header"><div class="card-title"><i class="fas fa-receipt"></i> Order Summary</div></div>
        <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:0.5rem;font-size:0.875rem;">
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-light);">Order Number</span>
                    <span style="font-weight:700;"><?= htmlspecialchars($o['order_number']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-light);">Subtotal</span>
                    <span>₹<?= number_format($o['subtotal'],2) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-light);">GST (<?= $o['gst_rate'] ?>%)</span>
                    <span>₹<?= number_format($o['gst_amount'],2) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-weight:800;font-size:1rem;color:var(--primary);border-top:1px solid var(--border);padding-top:0.5rem;margin-top:0.25rem;">
                    <span>Total</span>
                    <span>₹<?= number_format($o['total_amount'],2) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:var(--text-light);">Payment</span>
                    <span class="status-badge <?= $o['payment_status']==='paid'?'status-accepted':($o['payment_status']==='partial'?'status-reviewing':'status-rejected') ?>">
                        <?= ucfirst($o['payment_status']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;">
        <?php if ($o['payment_status'] !== 'paid' && $o['status'] !== 'cancelled'): ?>
        <form action="order_track.php?id=<?= $oid ?>" method="POST" style="margin:0;">
            <script
                src="https://checkout.razorpay.com/v1/checkout.js"
                data-key="rzp_test_YourTestKeyHere" 
                data-amount="<?= (int)($o['total_amount'] * 100) ?>" 
                data-currency="INR"
                data-order_id=""
                data-buttontext="Pay Now (Razorpay)"
                data-name="TORVO SPAIR"
                data-description="Order #<?= htmlspecialchars($o['order_number']) ?>"
                data-image="<?= PORTAL_URL ?>/../assets/images/logo.png"
                data-prefill.name="<?= htmlspecialchars($customer['name']) ?>"
                data-prefill.email="<?= htmlspecialchars($customer['email']) ?>"
                data-theme.color="#2563eb">
            </script>
            <style>
                /* Style the Razorpay default injected button */
                .razorpay-payment-button {
                    background: linear-gradient(135deg, #2563eb, #1d4ed8);
                    color: white;
                    border: none;
                    border-radius: 8px;
                    padding: 0.8rem 1.25rem;
                    font-size: 0.9rem;
                    font-weight: 700;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                    box-shadow: 0 4px 15px rgba(37,99,235,0.25);
                }
            </style>
        </form>
        <?php endif; ?>

        <a href="order_invoice_portal.php?id=<?= $oid ?>" class="btn btn-primary">
            <i class="fas fa-file-invoice"></i> Download Invoice
        </a>
        <a href="catalogue.php" class="btn btn-outline">
            <i class="fas fa-shopping-cart"></i> Continue Shopping
        </a>
        <a href="https://api.whatsapp.com/send?phone=<?= getSetting('whatsapp_number','919800000000') ?>&text=Hi! I have a query about my order <?= urlencode($o['order_number']) ?>" target="_blank" class="btn btn-outline" style="color:#25d366;border-color:#25d366;">
            <i class="fab fa-whatsapp"></i> WhatsApp Us
        </a>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
