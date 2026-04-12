<?php
require_once __DIR__ . '/config/auth.php';

requireCustomerLogin();

$customer = currentCustomer();
$db  = portalDB();
$cid = $customer['id'];
$rid = (int)($_GET['id'] ?? 0);

$rfq = $db->prepare("SELECT * FROM rfqs WHERE id=? AND customer_id=?");
$rfq->execute([$rid, $cid]);
$rfq = $rfq->fetch();

if (!$rfq) { header('Location: rfqs.php'); exit; }

$items = $db->prepare("
    SELECT ri.*, p.name AS pname, p.sku, p.image, c.name AS cat
    FROM rfq_items ri
    JOIN products p ON ri.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE ri.rfq_id = ?
");
$items->execute([$rid]);
$rfqItems = $items->fetchAll();

$grandTotal = array_sum(array_map(fn($i) => $i['unit_price'] * $i['quantity'], $rfqItems));

$pageTitle  = 'RFQ ' . $rfq['rfq_number'];
$activePage = 'rfqs';
include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb"><div class="breadcrumb-inner">
    <a href="dashboard.php">Dashboard</a> <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
    <a href="rfqs.php">My RFQs</a> <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
    <span><?= htmlspecialchars($rfq['rfq_number']) ?></span>
</div></div>

<div class="section container" style="max-width:900px;">

    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.4rem;font-weight:800;color:var(--text-dark);">
                <?= htmlspecialchars($rfq['rfq_number']) ?>
            </h1>
            <div style="display:flex;align-items:center;gap:0.75rem;margin-top:0.3rem;">
                <span class="status-badge status-<?= $rfq['status'] ?>"><?= ucfirst($rfq['status']) ?></span>
                <span style="font-size:0.78rem;color:var(--text-muted);">
                    Submitted <?= date('d M Y, h:ia', strtotime($rfq['created_at'])) ?>
                </span>
            </div>
        </div>
        <div style="display:flex;gap:0.75rem;">
            <a href="rfqs.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
            <?php 
                $waText = "Hello TORVO SPAIR Support, I've just submitted an RFQ (" . $rfq['rfq_number'] . "). Please review it.\n\nItems: " . count($rfqItems) . "\nLink: " . APP_URL . "/portal/rfq_detail.php?id=" . $rid;
                $waLink = "https://wa.me/" . preg_replace('/[^0-9]/', '', getSetting('contact_phone', '919876543210')) . "?text=" . urlencode($waText);
            ?>
            <a href="<?= $waLink ?>" target="_blank" class="btn btn-sm" style="background-color:#25d366;color:#fff;">
                <i class="fab fa-whatsapp"></i> Share to WhatsApp
            </a>
        </div>
    </div>

    <!-- Items -->
    <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-list"></i> Requested Items (<?= count($rfqItems) ?>)</div>
        </div>
        <div style="overflow-x:auto;">
            <table class="portal-table">
                <thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Unit Price</th><th>Qty</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($rfqItems as $item): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:0.6rem;">
                            <div style="width:36px;height:36px;border-radius:6px;background:var(--bg-gray2);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
                                <?php if ($item['image'] && file_exists(UPLOAD_DIR.$item['image'])): ?>
                                <img src="<?= UPLOAD_URL.htmlspecialchars($item['image']) ?>" style="width:100%;height:100%;object-fit:contain;">
                                <?php else: ?><i class="fas fa-cog" style="color:var(--text-muted);font-size:0.8rem;"></i><?php endif; ?>
                            </div>
                            <span style="font-weight:600;"><?= htmlspecialchars($item['pname']) ?></span>
                        </div>
                    </td>
                    <td style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($item['sku']) ?></td>
                    <td style="font-size:0.8rem;"><?= htmlspecialchars($item['cat']) ?></td>
                    <td><?= $item['unit_price'] > 0 ? formatCurrency($item['unit_price']) : '<em style="color:var(--text-muted);font-size:0.8rem;">TBD</em>' ?></td>
                    <td style="font-weight:700;"><?= $item['quantity'] ?></td>
                    <td style="font-weight:700;color:var(--primary);">
                        <?= $item['unit_price'] > 0 ? formatCurrency($item['unit_price'] * $item['quantity']) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if ($grandTotal > 0): ?>
                <tfoot>
                    <tr>
                        <td colspan="5" style="text-align:right;font-weight:700;padding:0.85rem 1rem;border-top:2px solid var(--border);">Indicative Total:</td>
                        <td style="font-weight:800;font-size:1rem;color:var(--primary);border-top:2px solid var(--border);"><?= formatCurrency($grandTotal) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- Notes -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <?php if ($rfq['customer_notes']): ?>
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fas fa-comment"></i> Your Notes</div></div>
            <div class="card-body" style="font-size:0.875rem;color:var(--text-medium);">
                <?= nl2br(htmlspecialchars($rfq['customer_notes'])) ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($rfq['admin_notes']): ?>
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fas fa-reply"></i> Response from TORVO SPAIR</div></div>
            <div class="card-body">
                <div class="alert alert-info" style="margin:0;font-size:0.875rem;">
                    <i class="fas fa-info-circle"></i>
                    <?= nl2br(htmlspecialchars($rfq['admin_notes'])) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$rfq['admin_notes']): ?>
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fas fa-clock"></i> Status</div></div>
            <div class="card-body">
                <?php if ($rfq['status'] === 'submitted'): ?>
                <div class="alert alert-info" style="margin:0;">
                    <i class="fas fa-hourglass-half"></i>
                    Your RFQ has been received and is being reviewed. We'll respond within 24 business hours.
                </div>
                <?php elseif ($rfq['status'] === 'reviewing'): ?>
                <div class="alert alert-warning" style="margin:0;">
                    <i class="fas fa-search"></i>
                    Our team is reviewing your request and preparing a quotation.
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
