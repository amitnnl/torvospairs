<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle      = 'Order Management';
$pageIcon       = 'fas fa-truck';
$activePage     = 'orders_admin';
$pageBreadcrumb = 'Order Management';
include BASE_PATH . '/includes/header.php';
requireAdmin();

$db = getDB();

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

// ── Handle Actions ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Create order from RFQ
    if ($action === 'create_order') {
        $rfqId     = (int)$_POST['rfq_id'];
        $custId    = (int)$_POST['customer_id'];
        $gstRate   = (float)($_POST['gst_rate'] ?? 18);
        $shipAddr  = sanitize($_POST['shipping_address'] ?? '');
        $notes     = sanitize($_POST['admin_notes'] ?? '');

        // Fetch RFQ items
        $items = $db->prepare("SELECT ri.*, p.price FROM rfq_items ri JOIN products p ON ri.product_id=p.id WHERE ri.rfq_id=?");
        $items->execute([$rfqId]);
        $rfqItems = $items->fetchAll();

        $subtotal = 0;
        foreach ($rfqItems as $item) {
            $price = $item['unit_price'] > 0 ? $item['unit_price'] : $item['price'];
            $subtotal += $price * $item['quantity'];
        }
        $gstAmt = round($subtotal * $gstRate / 100, 2);
        $total  = $subtotal + $gstAmt;
        $orderNum = 'ORD-' . strtoupper(substr(uniqid(), -7));

        $db->prepare("INSERT INTO orders (order_number,rfq_id,customer_id,subtotal,gst_rate,gst_amount,total_amount,shipping_address,admin_notes) VALUES (?,?,?,?,?,?,?,?,?)")
           ->execute([$orderNum, $rfqId, $custId, $subtotal, $gstRate, $gstAmt, $total, $shipAddr, $notes]);
        $orderId = $db->lastInsertId();

        $insItem = $db->prepare("INSERT INTO order_items (order_id,product_id,quantity,unit_price,total_price) VALUES (?,?,?,?,?)");
        foreach ($rfqItems as $item) {
            $price = $item['unit_price'] > 0 ? $item['unit_price'] : $item['price'];
            $insItem->execute([$orderId, $item['product_id'], $item['quantity'], $price, $price * $item['quantity']]);
        }

        // Update RFQ status to quoted
        $db->prepare("UPDATE rfqs SET status='quoted', admin_notes=? WHERE id=?")->execute(["Order $orderNum created.", $rfqId]);
        setFlash('success', "Order <strong>$orderNum</strong> created successfully.");
        header('Location: orders_admin.php'); exit;
    }

    // Update order status
    if ($action === 'update_order') {
        $oid        = (int)$_POST['order_id'];
        $newStatus  = sanitize($_POST['status'] ?? '');
        $payment    = sanitize($_POST['payment_status'] ?? '');
        $track      = sanitize($_POST['tracking_info'] ?? '');
        $notes      = sanitize($_POST['admin_notes'] ?? '');

        // Fetch previous status
        $prevRow = $db->prepare("SELECT status FROM orders WHERE id=?");
        $prevRow->execute([$oid]);
        $prevStatus = $prevRow->fetchColumn();

        $db->prepare("UPDATE orders SET status=?,payment_status=?,tracking_info=?,admin_notes=? WHERE id=?")
           ->execute([$newStatus, $payment, $track, $notes, $oid]);

        // ── Auto stock deduction when marked DELIVERED ────────────────────────
        if ($newStatus === 'delivered' && $prevStatus !== 'delivered') {
            $uid   = currentUser()['id'];
            $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
            $items->execute([$oid]);
            foreach ($items->fetchAll() as $item) {
                $pid = $item['product_id'];
                $qty = $item['quantity'];

                // Get current stock
                $cur = $db->prepare("SELECT quantity FROM products WHERE id=?");
                $cur->execute([$pid]);
                $before = (int)$cur->fetchColumn();
                $after  = max(0, $before - $qty);

                // Update stock
                $db->prepare("UPDATE products SET quantity=? WHERE id=?")->execute([$after, $pid]);

                // Log it
                $orderNum = $db->query("SELECT order_number FROM orders WHERE id=$oid")->fetchColumn();
                $db->prepare("INSERT INTO stock_logs (product_id,user_id,type,quantity,previous_stock,current_stock,notes)
                              VALUES (?,?,?,?,?,?,?)")
                   ->execute([$pid, $uid, 'out', $qty, $before, $after, "Auto: Order $orderNum delivered"]);
            }
        }

        setFlash('success', 'Order updated.' . ($newStatus === 'delivered' && $prevStatus !== 'delivered' ? ' Stock auto-deducted.' : ''));
        header('Location: orders_admin.php'); exit;
    }
}

// Load orders with customer info
$statusFilter = sanitize($_GET['status'] ?? '');
$sql = "SELECT o.*, c.company_name, c.contact_name, c.email
        FROM orders o
        JOIN customers c ON o.customer_id = c.id";
$params = [];
if ($statusFilter) { $sql .= " WHERE o.status=?"; $params[] = $statusFilter; }
$sql .= " ORDER BY o.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Load RFQs with no order yet (for creating new orders)
$pendingRFQs = $db->query("SELECT r.*, c.company_name FROM rfqs r JOIN customers c ON r.customer_id=c.id WHERE r.status IN ('submitted','reviewing') ORDER BY r.created_at DESC")->fetchAll();

$orderStatuses   = ['pending','confirmed','processing','dispatched','delivered','cancelled'];
$paymentStatuses = ['unpaid','paid','partial'];
$statusColors    = ['pending'=>'badge-warning','confirmed'=>'badge-info','processing'=>'badge-primary','dispatched'=>'badge-warning','delivered'=>'badge-success','cancelled'=>'badge-danger'];
$paymentColors   = ['unpaid'=>'badge-danger','paid'=>'badge-success','partial'=>'badge-warning'];

// Summary counts
$counts = [];
foreach ($orderStatuses as $s) {
    $cnt = $db->prepare("SELECT COUNT(*) FROM orders WHERE status=?");
    $cnt->execute([$s]);
    $counts[$s] = $cnt->fetchColumn();
}
$totalRevenue = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status NOT IN ('cancelled')")->fetchColumn();
?>

<div class="page-body">

<!-- Stats -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:1rem;margin-bottom:1.5rem;">
    <?php
    $statItems = [
        ['Total Revenue', '₹'.number_format($totalRevenue,0), 'fas fa-indian-rupee-sign', 'var(--primary)'],
        ['Pending',   $counts['pending'],   'fas fa-hourglass-half', 'var(--warning)'],
        ['Processing',$counts['processing'],'fas fa-cog',            'var(--primary)'],
        ['Dispatched',$counts['dispatched'],'fas fa-truck',          '#6366f1'],
        ['Delivered', $counts['delivered'], 'fas fa-check-double',   'var(--success)'],
    ];
    foreach ($statItems as [$label, $val, $icon, $color]):
    ?>
    <div class="card" style="padding:1rem;text-align:center;border-top:3px solid <?= $color ?>;">
        <i class="<?= $icon ?>" style="color:<?= $color ?>;font-size:1.25rem;margin-bottom:0.4rem;display:block;"></i>
        <div style="font-size:1.3rem;font-weight:800;color:var(--text-primary);"><?= $val ?></div>
        <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.4px;"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr;gap:1.25rem;">

    <!-- Orders Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-truck"></i> Orders
                <?php if ($statusFilter): ?><span style="font-size:0.75rem;color:var(--text-muted);">· <?= ucfirst($statusFilter) ?></span><?php endif; ?>
            </div>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <form method="GET">
                    <select name="status" class="form-control" style="padding:0.4rem 0.75rem;font-size:0.8rem;" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <?php foreach ($orderStatuses as $s): ?>
                        <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if (!empty($pendingRFQs)): ?>
                <button onclick="openModal('createOrderModal')" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Create Order from RFQ
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($orders)): ?>
        <div class="empty-state">
            <i class="fas fa-truck"></i>
            <h3>No orders yet</h3>
            <p>Create orders from accepted RFQs using the button above.</p>
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Order #</th><th>Customer</th><th>Amount</th><th>GST</th><th>Total</th><th>Status</th><th>Payment</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($o['order_number']) ?></td>
                    <td>
                        <div style="font-weight:600;font-size:0.85rem;"><?= htmlspecialchars($o['company_name']) ?></div>
                        <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($o['contact_name']) ?></div>
                    </td>
                    <td>₹<?= number_format($o['subtotal'],2) ?></td>
                    <td style="font-size:0.8rem;color:var(--text-muted);"><?= $o['gst_rate'] ?>% · ₹<?= number_format($o['gst_amount'],2) ?></td>
                    <td style="font-weight:700;color:var(--text-primary);">₹<?= number_format($o['total_amount'],2) ?></td>
                    <td><span class="badge-pill <?= $statusColors[$o['status']] ?? 'badge-gray' ?>"><?= ucfirst($o['status']) ?></span></td>
                    <td><span class="badge-pill <?= $paymentColors[$o['payment_status']] ?? 'badge-gray' ?>"><?= ucfirst($o['payment_status']) ?></span></td>
                    <td style="font-size:0.75rem;color:var(--text-muted);white-space:nowrap;"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                    <td>
                        <div style="display:flex;gap:0.35rem;">
                            <button class="btn btn-outline btn-sm btn-icon" onclick='editOrder(<?= json_encode($o) ?>)' data-tooltip="Update">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="order_invoice.php?id=<?= $o['id'] ?>" target="_blank" class="btn btn-outline btn-sm btn-icon" data-tooltip="Invoice">
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
</div>

<!-- Create Order from RFQ Modal -->
<div class="modal-overlay" id="createOrderModal">
    <div class="modal-box" style="max-width:580px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-plus" style="color:var(--primary);"></i> Create Order from RFQ</div>
            <button class="modal-close" onclick="closeModal('createOrderModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create_order">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select RFQ *</label>
                    <select name="rfq_id" id="rfqSelect" class="form-control" onchange="loadRfqCustomer(this)" required>
                        <option value="">— Select an RFQ —</option>
                        <?php foreach ($pendingRFQs as $rfq): ?>
                        <option value="<?= $rfq['id'] ?>" data-cid="<?= $rfq['customer_id'] ?>">
                            <?= htmlspecialchars($rfq['rfq_number']) ?> — <?= htmlspecialchars($rfq['company_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="customer_id" id="rfqCustomerId">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">GST Rate (%)</label>
                        <select name="gst_rate" class="form-control">
                            <option value="0">0% (Exempt)</option>
                            <option value="5">5%</option>
                            <option value="12">12%</option>
                            <option value="18" selected>18% (Standard)</option>
                            <option value="28">28%</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Terms</label>
                        <select name="admin_notes" class="form-control">
                            <option>Net 30 days</option>
                            <option>Net 15 days</option>
                            <option>Advance payment required</option>
                            <option>50% advance, 50% on delivery</option>
                            <option>COD</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Shipping Address</label>
                    <textarea name="shipping_address" class="form-control" rows="2" placeholder="Delivery address for this order..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createOrderModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Create Order</button>
            </div>
        </form>
    </div>
</div>

<!-- Update Order Modal -->
<div class="modal-overlay" id="editOrderModal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-truck" style="color:var(--primary);"></i> Update Order</div>
            <button class="modal-close" onclick="closeModal('editOrderModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_order">
            <input type="hidden" name="order_id" id="editOrderId">
            <div class="modal-body">
                <div id="editOrderNum" style="font-weight:700;font-size:1rem;margin-bottom:0.75rem;color:var(--primary);"></div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Order Status</label>
                        <select name="status" id="editOrderStatus" class="form-control">
                            <?php foreach ($orderStatuses as $s): ?>
                            <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payment Status</label>
                        <select name="payment_status" id="editPaymentStatus" class="form-control">
                            <?php foreach ($paymentStatuses as $s): ?>
                            <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Tracking Info / AWB Number</label>
                    <input type="text" name="tracking_info" id="editTracking" class="form-control" placeholder="Courier name, tracking number...">
                </div>
                <div class="form-group">
                    <label class="form-label">Notes to Customer</label>
                    <textarea name="admin_notes" id="editOrderNotes" class="form-control" rows="2" placeholder="Payment terms, dispatch info..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editOrderModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Order</button>
            </div>
        </form>
    </div>
</div>

<script>
function loadRfqCustomer(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('rfqCustomerId').value = opt.dataset.cid || '';
}
function editOrder(o) {
    document.getElementById('editOrderId').value        = o.id;
    document.getElementById('editOrderNum').textContent = o.order_number;
    document.getElementById('editOrderStatus').value   = o.status;
    document.getElementById('editPaymentStatus').value = o.payment_status;
    document.getElementById('editTracking').value      = o.tracking_info || '';
    document.getElementById('editOrderNotes').value    = o.admin_notes || '';
    openModal('editOrderModal');
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
