<?php
/**
 * Portal — Invoice View & Print/PDF
 * Generates a clean invoice page that partners can print to PDF
 */
require_once __DIR__ . '/config/auth.php';
requireActivePartner();

$customer = currentCustomer();
$db       = portalDB();
$rfqId    = (int)($_GET['rfq'] ?? 0);

// Load RFQ (must belong to this customer and be invoiced)
$rfq = $db->prepare("SELECT r.*, c.company_name, c.contact_name, c.email, c.phone, c.address, c.city, c.state, c.pin, c.gstin FROM rfqs r JOIN customers c ON c.id = r.customer_id WHERE r.id = ? AND r.customer_id = ? AND r.status = 'invoiced'");
$rfq->execute([$rfqId, $customer['id']]);
$rfq = $rfq->fetch();

if (!$rfq) {
    setPortalFlash('error', 'Invoice not found or not yet generated.');
    header('Location: rfqs.php'); exit;
}

// Load invoice
$inv = $db->prepare("SELECT * FROM invoices WHERE rfq_id = ?");
$inv->execute([$rfqId]);
$inv = $inv->fetch();

// Load items
$items = $db->prepare("SELECT ri.*, p.name, p.sku FROM rfq_items ri JOIN products p ON p.id = ri.product_id WHERE ri.rfq_id = ?");
$items->execute([$rfqId]);
$items = $items->fetchAll();

$siteName = getSetting('site_title', 'TORVO SPAIR');
$siteAddr = getSetting('contact_address', '');
$sitePhone = getSetting('contact_phone', '');
$siteEmail = getSetting('contact_email', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= htmlspecialchars($inv['invoice_number'] ?? '') ?> — <?= htmlspecialchars($siteName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: #f1f5f9; color: #374151; font-size: 14px; }
    .inv-wrap { max-width: 820px; margin: 2rem auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 30px rgba(0,0,0,0.12); }
    .inv-header { background: linear-gradient(135deg, #1e2d66, #1e3a8a); padding: 2.5rem; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.5rem; }
    .inv-brand { color: #fff; }
    .inv-brand-name { font-size: 1.6rem; font-weight: 900; letter-spacing: 1px; }
    .inv-brand-sub { font-size: 0.7rem; letter-spacing: 2px; text-transform: uppercase; opacity: 0.55; margin-top: 2px; }
    .inv-brand-addr { font-size: 0.78rem; opacity: 0.65; margin-top: 0.75rem; line-height: 1.6; }
    .inv-meta { text-align: right; color: #fff; }
    .inv-meta h2 { font-size: 2rem; font-weight: 900; letter-spacing: 2px; opacity: 0.9; }
    .inv-meta .inv-num { font-size: 0.95rem; font-weight: 700; margin-top: 0.25rem; }
    .inv-meta .inv-date { font-size: 0.78rem; opacity: 0.6; margin-top: 0.15rem; }
    .inv-body { padding: 2rem 2.5rem; }
    .inv-addr-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; }
    .inv-addr h4 { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-bottom: 0.5rem; }
    .inv-addr .company { font-size: 1rem; font-weight: 800; color: #0f172a; }
    .inv-addr p { font-size: 0.82rem; color: #4b5563; line-height: 1.6; margin-top: 0.15rem; }
    .inv-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; }
    .inv-table th { background: #1e3a8a; color: #fff; padding: 0.75rem 1rem; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
    .inv-table th:last-child, .inv-table td:last-child { text-align: right; }
    .inv-table th:nth-child(2), .inv-table td:nth-child(2) { text-align: center; }
    .inv-table td { padding: 0.85rem 1rem; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; }
    .inv-table tr:last-child td { border-bottom: none; }
    .inv-table tbody tr:nth-child(even) td { background: #f8fafc; }
    .inv-total-box { display: flex; justify-content: flex-end; margin-bottom: 2rem; }
    .inv-total-table { min-width: 280px; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
    .inv-total-table tr td { padding: 0.65rem 1rem; font-size: 0.875rem; }
    .inv-total-table tr:last-child td { background: #1e3a8a; color: #fff; font-weight: 800; font-size: 1rem; padding: 0.85rem 1rem; }
    .inv-total-table td:last-child { text-align: right; font-weight: 700; }
    .inv-footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 1.25rem 2.5rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; color: #6b7280; flex-wrap: wrap; gap: 0.5rem; }
    .inv-status { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
    .status-unpaid { background: rgba(217,119,6,0.1); color: #d97706; }
    .status-paid   { background: rgba(22,163,74,0.1); color: #16a34a; }
    .no-print { background: #1e3a8a; padding: 1rem 2.5rem; display: flex; gap: 0.75rem; justify-content: flex-end; }
    @media print {
        body { background: #fff; }
        .no-print { display: none; }
        .inv-wrap { box-shadow: none; margin: 0; border-radius: 0; max-width: 100%; }
    }
    @media (max-width: 600px) {
        .inv-header { flex-direction: column; }
        .inv-meta { text-align: left; }
        .inv-addr-grid { grid-template-columns: 1fr; }
        .inv-body { padding: 1.25rem; }
        .inv-header { padding: 1.5rem; }
    }
    </style>
</head>
<body>

<!-- Print/Download bar -->
<div class="no-print">
    <button onclick="window.print()" style="padding:0.6rem 1.5rem;background:#f97316;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:0.875rem;cursor:pointer;display:flex;align-items:center;gap:0.5rem;font-family:inherit;">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path d="M5 4v3H4a2 2 0 00-2 2v4a2 2 0 002 2h1v2a1 1 0 001 1h8a1 1 0 001-1v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a1 1 0 00-1-1H6a1 1 0 00-1 1zm2 0h6v3H7V4zm-1 9H6v-2h8v2h-1v2H7v-2h-1zm9-4a1 1 0 110 2 1 1 0 010-2z"/></svg>
        Print / Save as PDF
    </button>
    <a href="rfq_view.php?id=<?= $rfqId ?>" style="padding:0.6rem 1.5rem;background:rgba(255,255,255,0.15);color:#fff;border:1px solid rgba(255,255,255,0.25);border-radius:8px;font-weight:600;font-size:0.875rem;text-decoration:none;display:flex;align-items:center;gap:0.5rem;">
        ← Back to RFQ
    </a>
</div>

<div class="inv-wrap">
    <!-- Header -->
    <div class="inv-header">
        <div class="inv-brand">
            <div class="inv-brand-name"><?= htmlspecialchars($siteName) ?></div>
            <div class="inv-brand-sub">B2B Supply Portal</div>
            <div class="inv-brand-addr">
                <?php if ($siteAddr): ?><?= nl2br(htmlspecialchars($siteAddr)) ?><br><?php endif; ?>
                <?php if ($sitePhone): ?><?= htmlspecialchars($sitePhone) ?><?php endif; ?>
                <?php if ($siteEmail): ?> · <?= htmlspecialchars($siteEmail) ?><?php endif; ?>
            </div>
        </div>
        <div class="inv-meta">
            <h2>INVOICE</h2>
            <div class="inv-num"><?= htmlspecialchars($inv['invoice_number'] ?? $rfq['rfq_number']) ?></div>
            <div class="inv-date">Date: <?= $inv ? date('d M Y', strtotime($inv['created_at'])) : date('d M Y') ?></div>
            <div style="margin-top:0.75rem;">
                <span class="inv-status <?= ($inv['payment_status'] ?? 'unpaid') === 'paid' ? 'status-paid' : 'status-unpaid' ?>" style="background:rgba(255,255,255,0.15);color:#fff;">
                    <?= strtoupper($inv['payment_status'] ?? 'UNPAID') ?>
                </span>
            </div>
        </div>
    </div>

    <div class="inv-body">
        <!-- Bill To -->
        <div class="inv-addr-grid">
            <div class="inv-addr">
                <h4>Bill To</h4>
                <div class="company"><?= htmlspecialchars($rfq['company_name']) ?></div>
                <p>Attn: <?= htmlspecialchars($rfq['contact_name']) ?></p>
                <?php if ($rfq['address']): ?><p><?= nl2br(htmlspecialchars($rfq['address'])) ?></p><?php endif; ?>
                <?php if ($rfq['city'] || $rfq['state']): ?><p><?= htmlspecialchars(trim($rfq['city'].', '.$rfq['state'].($rfq['pin']?' - '.$rfq['pin']:''))) ?></p><?php endif; ?>
                <p><?= htmlspecialchars($rfq['email']) ?></p>
                <?php if ($rfq['phone']): ?><p><?= htmlspecialchars($rfq['phone']) ?></p><?php endif; ?>
                <?php if ($rfq['gstin']): ?><p>GSTIN: <?= htmlspecialchars($rfq['gstin']) ?></p><?php endif; ?>
            </div>
            <div class="inv-addr">
                <h4>Invoice Details</h4>
                <p>Invoice No: <strong><?= htmlspecialchars($inv['invoice_number'] ?? '') ?></strong></p>
                <p>RFQ Ref: <?= htmlspecialchars($rfq['rfq_number']) ?></p>
                <p>Invoice Date: <?= $inv ? date('d M Y', strtotime($inv['created_at'])) : date('d M Y') ?></p>
                <?php if ($rfq['accepted_at']): ?><p>Accepted: <?= date('d M Y', strtotime($rfq['accepted_at'])) ?></p><?php endif; ?>
            </div>
        </div>

        <!-- Items -->
        <table class="inv-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>SKU</th>
                    <th style="text-align:center;">Qty</th>
                    <th style="text-align:right;">Unit Price</th>
                    <th style="text-align:right;">Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $i => $item):
                $lineTotal = ($item['unit_price'] ?? 0) * $item['quantity'];
            ?>
            <tr>
                <td style="color:#9ca3af;"><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                <td style="color:#6b7280;"><?= htmlspecialchars($item['sku']) ?></td>
                <td style="text-align:center;"><?= $item['quantity'] ?></td>
                <td style="text-align:right;"><?= formatCurrency((float)$item['unit_price']) ?></td>
                <td style="text-align:right;font-weight:700;"><?= formatCurrency($lineTotal) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="inv-total-box">
            <table class="inv-total-table">
                <tr>
                    <td>Subtotal</td>
                    <td><?= formatCurrency((float)($inv['subtotal'] ?? 0)) ?></td>
                </tr>
                <tr>
                    <td><strong>Total Amount</strong></td>
                    <td><strong><?= formatCurrency((float)($inv['total_amount'] ?? 0)) ?></strong></td>
                </tr>
            </table>
        </div>

        <?php if ($inv['notes']): ?>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:1rem;margin-bottom:1.5rem;font-size:0.82rem;">
            <strong>Notes:</strong> <?= nl2br(htmlspecialchars($inv['notes'])) ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="inv-footer">
        <div>Thank you for your business! — <?= htmlspecialchars($siteName) ?></div>
        <div>For queries: <?= htmlspecialchars($siteEmail) ?></div>
    </div>
</div>

</body>
</html>
