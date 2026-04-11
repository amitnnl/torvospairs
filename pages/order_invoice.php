<?php
/**
 * GST-Compliant Invoice Print — TORVO SPAIR
 * URL: /pages/order_invoice.php?id=ORDER_ID
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/database.php';
requireLogin();

$db  = getDB();
$oid = (int)($_GET['id'] ?? 0);
if (!$oid) { header('Location: orders_admin.php'); exit; }

$order = $db->prepare("SELECT o.*, c.company_name, c.contact_name, c.email, c.phone, c.gstin, c.address, c.city, c.state, c.pin FROM orders o JOIN customers c ON o.customer_id=c.id WHERE o.id=?");
$order->execute([$oid]);
$o = $order->fetch();
if (!$o) { header('Location: orders_admin.php'); exit; }

$items = $db->prepare("SELECT oi.*, p.name AS pname, p.sku, p.barcode FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?");
$items->execute([$oid]);
$lineItems = $items->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?= htmlspecialchars($o['order_number']) ?> — TORVO SPAIR</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; font-size: 13px; color: #111; background: #f0f0f0; }
.invoice-wrap { background: #fff; max-width: 860px; margin: 1.5rem auto; padding: 2rem; box-shadow: 0 2px 12px rgba(0,0,0,0.1); }
.inv-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; padding-bottom: 1.25rem; border-bottom: 2px solid #1e3a8a; }
.logo-block { display: flex; align-items: center; gap: 0.75rem; }
.logo-icon { width: 44px; height: 44px; background: linear-gradient(135deg, #1e3a8a, #f97316); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.1rem; font-weight: 900; }
.company-name { font-size: 1.3rem; font-weight: 900; color: #1e3a8a; letter-spacing: 1px; }
.company-sub  { font-size: 0.7rem; color: #666; }
.inv-label { font-size: 1.5rem; font-weight: 800; color: #1e3a8a; }
.inv-num   { font-size: 0.9rem; color: #555; }

.inv-parties { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 1.75rem; }
.party h4    { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: #888; margin-bottom: 0.5rem; }
.party p     { font-size: 0.82rem; line-height: 1.6; }
.party strong { font-size: 0.9rem; color: #111; }

.inv-meta     { background: #f0f4ff; border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1.5rem; display: flex; gap: 2rem; flex-wrap: wrap; }
.meta-item    { display: flex; flex-direction: column; }
.meta-label   { font-size: 0.68rem; text-transform: uppercase; color: #888; letter-spacing: 0.5px; }
.meta-value   { font-weight: 700; font-size: 0.85rem; color: #111; }

table { width: 100%; border-collapse: collapse; margin-bottom: 1.25rem; }
thead th { background: #1e3a8a; color: #fff; padding: 0.6rem 0.75rem; font-size: 0.75rem; text-align: left; }
tbody td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #e2e8f0; font-size: 0.82rem; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:nth-child(even) td { background: #fafafa; }
td.num { text-align: right; }

.totals { display: flex; justify-content: flex-end; margin-bottom: 1.5rem; }
.totals-box { width: 300px; }
.totals-row { display: flex; justify-content: space-between; padding: 0.35rem 0; border-bottom: 1px solid #e2e8f0; font-size: 0.83rem; }
.totals-row.grand { font-size: 1rem; font-weight: 800; color: #1e3a8a; border-top: 2px solid #1e3a8a; border-bottom: 2px solid #1e3a8a; padding: 0.5rem 0; }

.inv-footer { display: flex; justify-content: space-between; align-items: flex-start; margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid #e2e8f0; }
.terms h4 { font-size: 0.75rem; font-weight: 700; margin-bottom: 0.4rem; }
.terms p  { font-size: 0.72rem; color: #666; line-height: 1.5; }
.seal { text-align: center; width: 180px; }
.seal .sig-line { border-bottom: 1.5px solid #111; padding-bottom: 40px; margin-bottom: 0.3rem; }
.seal p { font-size: 0.72rem; color: #555; }
.status-chip { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; }
.chip-paid  { background: #d1fae5; color: #065f46; }
.chip-unpaid{ background: #fee2e2; color: #991b1b; }
.chip-partial{ background: #fef3c7; color: #92400e; }

@media print {
    body { background: #fff; }
    .invoice-wrap { box-shadow: none; margin: 0; }
    .no-print { display: none !important; }
}
</style>
</head>
<body>

<div class="no-print" style="text-align:center;padding:1rem;background:#1e3a8a;color:#fff;display:flex;align-items:center;justify-content:center;gap:1rem;">
    <span style="font-weight:700;">Invoice Preview — <?= htmlspecialchars($o['order_number']) ?></span>
    <button onclick="window.print()" style="background:#f97316;color:#fff;border:none;padding:0.5rem 1.25rem;border-radius:6px;cursor:pointer;font-weight:700;font-size:0.875rem;">
        🖨 Print / Save PDF
    </button>
    <a href="orders_admin.php" style="color:rgba(255,255,255,0.7);font-size:0.8rem;">← Back to Orders</a>
</div>

<div class="invoice-wrap">
    <!-- Header -->
    <div class="inv-header">
        <div class="logo-block">
            <div class="logo-icon">TS</div>
            <div>
                <div class="company-name">TORVO SPAIR</div>
                <div class="company-sub">Power Tool Spare Parts & Accessories</div>
                <div class="company-sub">Mumbai, Maharashtra | sales@torvo.com | +91 98000 00000</div>
                <div class="company-sub">GSTIN: 27AABCT1332L1ZQ</div>
            </div>
        </div>
        <div style="text-align:right;">
            <div class="inv-label">TAX INVOICE</div>
            <div class="inv-num"><?= htmlspecialchars($o['order_number']) ?></div>
            <div style="margin-top:0.25rem;font-size:0.78rem;color:#555;">Date: <?= date('d M Y', strtotime($o['created_at'])) ?></div>
            <div style="margin-top:0.3rem;">
                <span class="status-chip chip-<?= $o['payment_status'] ?>"><?= ucfirst($o['payment_status']) ?></span>
            </div>
        </div>
    </div>

    <!-- Parties -->
    <div class="inv-parties">
        <div class="party">
            <h4>Billed To</h4>
            <p>
                <strong><?= htmlspecialchars($o['company_name']) ?></strong><br>
                <?= htmlspecialchars($o['contact_name']) ?><br>
                <?= htmlspecialchars($o['email']) ?><br>
                <?= htmlspecialchars($o['phone'] ?? '') ?>
                <?php if ($o['gstin']): ?><br>GSTIN: <?= htmlspecialchars($o['gstin']) ?><?php endif; ?>
            </p>
        </div>
        <div class="party">
            <h4>Ship To</h4>
            <p><?= $o['shipping_address'] ? nl2br(htmlspecialchars($o['shipping_address'])) : nl2br(htmlspecialchars(implode(', ', array_filter([$o['address'], $o['city'], $o['state'], $o['pin']])))) ?></p>
        </div>
        <div class="party">
            <h4>Order Details</h4>
            <p>
                <strong>Order:</strong> <?= htmlspecialchars($o['order_number']) ?><br>
                <strong>Date:</strong> <?= date('d M Y', strtotime($o['created_at'])) ?><br>
                <?php if ($o['rfq_id']): ?>
                <strong>RFQ Ref:</strong> <?= $db->query("SELECT rfq_number FROM rfqs WHERE id={$o['rfq_id']}")->fetchColumn() ?><br>
                <?php endif; ?>
                <strong>Status:</strong> <?= ucfirst($o['status']) ?>
            </p>
        </div>
    </div>

    <!-- Meta -->
    <div class="inv-meta">
        <div class="meta-item"><span class="meta-label">HSN / SAC</span><span class="meta-value">8467 / 9987</span></div>
        <div class="meta-item"><span class="meta-label">GST Rate</span><span class="meta-value"><?= $o['gst_rate'] ?>%</span></div>
        <div class="meta-item"><span class="meta-label">Payment Terms</span><span class="meta-value"><?= htmlspecialchars($o['admin_notes'] ?: 'Net 30 days') ?></span></div>
        <?php if ($o['tracking_info']): ?>
        <div class="meta-item"><span class="meta-label">Tracking</span><span class="meta-value"><?= htmlspecialchars($o['tracking_info']) ?></span></div>
        <?php endif; ?>
    </div>

    <!-- Line Items -->
    <table>
        <thead>
            <tr>
                <th>#</th><th>Product / Description</th><th>SKU</th>
                <th class="num">Unit Price</th><th class="num">Qty</th>
                <th class="num">Taxable Value</th>
                <th class="num">CGST (<?= $o['gst_rate']/2 ?>%)</th>
                <th class="num">SGST (<?= $o['gst_rate']/2 ?>%)</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $srNo = 1;
        foreach ($lineItems as $item):
            $taxable = $item['unit_price'] * $item['quantity'];
            $cgst    = round($taxable * ($o['gst_rate']/2) / 100, 2);
            $sgst    = $cgst;
            $lineTotal = $taxable + $cgst + $sgst;
        ?>
        <tr>
            <td><?= $srNo++ ?></td>
            <td style="font-weight:600;"><?= htmlspecialchars($item['pname']) ?></td>
            <td style="color:#555;"><?= htmlspecialchars($item['sku']) ?></td>
            <td class="num">₹<?= number_format($item['unit_price'],2) ?></td>
            <td class="num" style="font-weight:700;"><?= $item['quantity'] ?></td>
            <td class="num">₹<?= number_format($taxable,2) ?></td>
            <td class="num">₹<?= number_format($cgst,2) ?></td>
            <td class="num">₹<?= number_format($sgst,2) ?></td>
            <td class="num" style="font-weight:700;">₹<?= number_format($lineTotal,2) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals">
        <div class="totals-box">
            <div class="totals-row">
                <span>Subtotal (Taxable)</span>
                <span>₹<?= number_format($o['subtotal'],2) ?></span>
            </div>
            <div class="totals-row">
                <span>CGST (<?= $o['gst_rate']/2 ?>%)</span>
                <span>₹<?= number_format($o['gst_amount']/2,2) ?></span>
            </div>
            <div class="totals-row">
                <span>SGST (<?= $o['gst_rate']/2 ?>%)</span>
                <span>₹<?= number_format($o['gst_amount']/2,2) ?></span>
            </div>
            <div class="totals-row grand">
                <span>GRAND TOTAL</span>
                <span>₹<?= number_format($o['total_amount'],2) ?></span>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="inv-footer">
        <div class="terms">
            <h4>Terms & Conditions</h4>
            <p>
                1. Goods once sold will not be taken back unless defective.<br>
                2. All disputes subject to Mumbai jurisdiction.<br>
                3. Interest @2% per month charged on overdue payments.<br>
                4. E. &amp; O.E.
            </p>
        </div>
        <div class="seal">
            <div class="sig-line"></div>
            <p><strong>For TORVO SPAIR</strong></p>
            <p>Authorised Signatory</p>
        </div>
    </div>
</div>
</body>
</html>
