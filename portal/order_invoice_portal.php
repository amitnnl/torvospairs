<?php
require_once __DIR__ . '/config/auth.php';
ensureB2BTables();
requireCustomerLogin();

$customer = currentCustomer();
$db  = portalDB();
$cid = $customer['id'];
$oid = (int)($_GET['id'] ?? 0);

// Ensure tables
$db->exec("CREATE TABLE IF NOT EXISTS `orders` (`id` INT AUTO_INCREMENT PRIMARY KEY, `order_number` VARCHAR(30) UNIQUE, `rfq_id` INT DEFAULT NULL, `customer_id` INT NOT NULL DEFAULT 0, `status` ENUM('pending','confirmed','processing','dispatched','delivered','cancelled') DEFAULT 'pending', `subtotal` DECIMAL(10,2) DEFAULT 0.00, `gst_rate` DECIMAL(5,2) DEFAULT 18.00, `gst_amount` DECIMAL(10,2) DEFAULT 0.00, `total_amount` DECIMAL(10,2) DEFAULT 0.00, `shipping_address` TEXT, `payment_status` ENUM('unpaid','paid','partial') DEFAULT 'unpaid', `tracking_info` VARCHAR(255), `admin_notes` TEXT, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS `order_items` (`id` INT AUTO_INCREMENT PRIMARY KEY, `order_id` INT NOT NULL DEFAULT 0, `product_id` INT NOT NULL DEFAULT 0, `quantity` INT NOT NULL DEFAULT 1, `unit_price` DECIMAL(10,2) DEFAULT 0.00, `total_price` DECIMAL(10,2) DEFAULT 0.00) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$order = $db->prepare("SELECT * FROM orders WHERE id=? AND customer_id=?");
$order->execute([$oid, $cid]);
$o = $order->fetch();
if (!$o) { header('Location: orders.php'); exit; }

$custData = $db->prepare("SELECT * FROM customers WHERE id=?");
$custData->execute([$cid]);
$cust = $custData->fetch();

$items = $db->prepare("SELECT oi.*, p.name AS pname, p.sku FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?");
$items->execute([$oid]);
$lineItems = $items->fetchAll();

$pageTitle  = 'Invoice ' . $o['order_number'];
$activePage = 'orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice <?= htmlspecialchars($o['order_number']) ?> — TORVO SPAIR</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',Arial,sans-serif;font-size:13px;color:#111;background:#f0f4ff}
.top-bar{background:#1e3a8a;color:#fff;padding:0.75rem 1.5rem;display:flex;align-items:center;justify-content:center;gap:1.5rem;font-size:0.85rem}
.top-bar button{background:#f97316;color:#fff;border:none;padding:0.5rem 1.25rem;border-radius:6px;cursor:pointer;font-weight:700;font-size:0.82rem}
.top-bar a{color:rgba(255,255,255,0.65);font-size:0.8rem}
.wrap{max-width:840px;margin:1.5rem auto;background:#fff;padding:2rem;box-shadow:0 2px 16px rgba(0,0,0,0.1);border-radius:12px}
.hd{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.75rem;padding-bottom:1.25rem;border-bottom:2px solid #1e3a8a}
.brand{font-size:1.2rem;font-weight:900;color:#1e3a8a;letter-spacing:1px}
.brand-sub{font-size:0.72rem;color:#666;margin-top:2px}
.inv-label{font-size:1.4rem;font-weight:800;color:#1e3a8a;text-align:right}
.inv-meta{font-size:0.78rem;color:#555;text-align:right;margin-top:4px}
.parties{display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-bottom:1.5rem;padding:1rem;background:#f8fafc;border-radius:8px}
.party h4{font-size:0.68rem;text-transform:uppercase;letter-spacing:0.5px;color:#888;margin-bottom:0.5rem}
.party p{font-size:0.82rem;line-height:1.6}
table{width:100%;border-collapse:collapse;margin-bottom:1.25rem}
thead th{background:#1e3a8a;color:#fff;padding:0.55rem 0.75rem;font-size:0.72rem;text-align:left}
tbody td{padding:0.55rem 0.75rem;border-bottom:1px solid #e2e8f0;font-size:0.8rem}
.num{text-align:right}
.totals-box{width:280px;margin-left:auto}
.tot-row{display:flex;justify-content:space-between;padding:0.3rem 0;border-bottom:1px solid #e2e8f0;font-size:0.82rem}
.tot-row.grand{font-size:0.95rem;font-weight:800;color:#1e3a8a;border-top:2px solid #1e3a8a;border-bottom:2px solid #1e3a8a;padding:0.5rem 0;margin-top:4px}
.footer{display:flex;justify-content:space-between;align-items:flex-end;margin-top:2rem;padding-top:1.25rem;border-top:1px solid #e2e8f0}
.terms{font-size:0.7rem;color:#666;line-height:1.6;max-width:380px}
.terms h4{font-size:0.72rem;font-weight:700;margin-bottom:0.3rem;color:#111}
.seal{text-align:center;width:160px}
.sig{border-bottom:1.5px solid #111;padding-bottom:32px;margin-bottom:0.3rem}
.seal p{font-size:0.7rem;color:#555}
.chip{display:inline-block;padding:2px 8px;border-radius:20px;font-size:0.68rem;font-weight:700}
.chip-paid{background:#d1fae5;color:#065f46}
.chip-unpaid{background:#fee2e2;color:#991b1b}
.chip-partial{background:#fef3c7;color:#92400e}
@media print{body{background:#fff}.top-bar{display:none}.wrap{box-shadow:none;margin:0;border-radius:0}}
</style>
</head>
<body>
<div class="top-bar">
    <span style="font-weight:700;">Invoice — <?= htmlspecialchars($o['order_number']) ?></span>
    <button onclick="window.print()">🖨 Print / Save PDF</button>
    <a href="orders.php">← Back to Orders</a>
</div>
<div class="wrap">
    <div class="hd">
        <div>
            <div class="brand">⚙ TORVO SPAIR</div>
            <div class="brand-sub">Power Tool Spare Parts & Accessories</div>
            <div class="brand-sub">Mumbai, Maharashtra · sales@torvo.com</div>
            <div class="brand-sub">GSTIN: 27AABCT1332L1ZQ</div>
        </div>
        <div>
            <div class="inv-label">TAX INVOICE</div>
            <div class="inv-meta"><?= htmlspecialchars($o['order_number']) ?></div>
            <div class="inv-meta">Date: <?= date('d M Y', strtotime($o['created_at'])) ?></div>
            <div style="margin-top:0.3rem;text-align:right;"><span class="chip chip-<?= $o['payment_status'] ?>"><?= ucfirst($o['payment_status']) ?></span></div>
        </div>
    </div>

    <div class="parties">
        <div class="party">
            <h4>Billed To</h4>
            <p><strong><?= htmlspecialchars($cust['company_name']) ?></strong><br>
            <?= htmlspecialchars($cust['contact_name']) ?><br>
            <?= htmlspecialchars($cust['email']) ?>
            <?php if ($cust['gstin']): ?><br>GSTIN: <?= htmlspecialchars($cust['gstin']) ?><?php endif; ?></p>
        </div>
        <div class="party">
            <h4>Order Info</h4>
            <p><strong>Order:</strong> <?= htmlspecialchars($o['order_number']) ?><br>
            <strong>Date:</strong> <?= date('d M Y', strtotime($o['created_at'])) ?><br>
            <strong>Status:</strong> <?= ucfirst($o['status']) ?><br>
            <?php if ($o['tracking_info']): ?><strong>Tracking:</strong> <?= htmlspecialchars($o['tracking_info']) ?><?php endif; ?></p>
        </div>
    </div>

    <table>
        <thead>
            <tr><th>#</th><th>Product</th><th>SKU</th><th class="num">Unit Price</th><th class="num">Qty</th><th class="num">Taxable</th><th class="num">GST (<?= $o['gst_rate'] ?>%)</th><th class="num">Total</th></tr>
        </thead>
        <tbody>
        <?php $sn=1; foreach ($lineItems as $item):
            $taxable = $item['unit_price'] * $item['quantity'];
            $gst     = round($taxable * $o['gst_rate'] / 100, 2);
        ?>
        <tr>
            <td><?= $sn++ ?></td>
            <td style="font-weight:600;"><?= htmlspecialchars($item['pname']) ?></td>
            <td style="color:#555;"><?= htmlspecialchars($item['sku']) ?></td>
            <td class="num">₹<?= number_format($item['unit_price'],2) ?></td>
            <td class="num" style="font-weight:700;"><?= $item['quantity'] ?></td>
            <td class="num">₹<?= number_format($taxable,2) ?></td>
            <td class="num">₹<?= number_format($gst,2) ?></td>
            <td class="num" style="font-weight:700;">₹<?= number_format($taxable+$gst,2) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals-box">
        <div class="tot-row"><span>Subtotal</span><span>₹<?= number_format($o['subtotal'],2) ?></span></div>
        <div class="tot-row"><span>GST (<?= $o['gst_rate'] ?>%)</span><span>₹<?= number_format($o['gst_amount'],2) ?></span></div>
        <div class="tot-row grand"><span>Grand Total</span><span>₹<?= number_format($o['total_amount'],2) ?></span></div>
    </div>

    <div class="footer">
        <div class="terms">
            <h4>Terms & Conditions</h4>
            <?= $o['admin_notes'] ? '<p style="background:#eff6ff;padding:6px 10px;border-radius:6px;margin-bottom:6px;color:#1e3a8a;font-weight:600;">' . htmlspecialchars($o['admin_notes']) . '</p>' : '' ?>
            <p>1. Goods once sold will not be taken back unless defective.<br>
               2. All disputes subject to Mumbai jurisdiction.<br>
               3. E. &amp; O.E.</p>
        </div>
        <div class="seal">
            <div class="sig"></div>
            <p><strong>For TORVO SPAIR</strong></p>
            <p>Authorised Signatory</p>
        </div>
    </div>
</div>
</body>
</html>
