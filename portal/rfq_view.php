<?php
/**
 * Portal — RFQ Detail & Quotation View
 * Partner reviews admin's quoted prices and accepts or rejects
 */
require_once __DIR__ . '/config/auth.php';
requireActivePartner();

$customer = currentCustomer();
$db       = portalDB();
$rfqId    = (int)($_GET['id'] ?? 0);

$rfq = $db->prepare("SELECT * FROM rfqs WHERE id = ? AND customer_id = ?");
$rfq->execute([$rfqId, $customer['id']]);
$rfq = $rfq->fetch();

if (!$rfq) {
    setPortalFlash('error', 'RFQ not found.');
    header('Location: rfqs.php'); exit;
}

// Load items with product info
$items = $db->prepare("
    SELECT ri.*, p.name, p.sku, p.brand, c.name AS category
    FROM rfq_items ri
    JOIN products p ON p.id = ri.product_id
    JOIN categories c ON c.id = p.category_id
    WHERE ri.rfq_id = ?
");
$items->execute([$rfqId]);
$items = $items->fetchAll();

// Handle Accept / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rfq['status'] === 'quoted') {
    $action = $_POST['action'] ?? '';
    if ($action === 'accept') {
        $db->prepare("UPDATE rfqs SET status='accepted', accepted_at=NOW() WHERE id=?")->execute([$rfqId]);
        setPortalFlash('success', 'Quotation accepted! The admin will generate your invoice shortly.');
    } elseif ($action === 'reject') {
        $db->prepare("UPDATE rfqs SET status='reviewing', customer_notes=CONCAT(IFNULL(customer_notes,''),' | Partner requested changes: ', ?) WHERE id=?")
           ->execute([trim($_POST['reject_note'] ?? 'Please revise pricing.'), $rfqId]);
        setPortalFlash('info', 'Rejection sent. Admin will review and resend a revised quotation.');
    }
    header("Location: rfq_view.php?id=$rfqId"); exit;
}

// Mark notifications as read for this rfq
$db->prepare("UPDATE notifications SET is_read=1 WHERE customer_id=? AND rfq_id=?")->execute([$customer['id'], $rfqId]);

$canAct     = ($rfq['status'] === 'quoted');
$quoted_total = 0;
foreach ($items as $item) $quoted_total += ($item['unit_price'] ?? 0) * $item['quantity'];

$statusLabels = [
    'submitted' => ['Submitted',  'var(--primary)',  'fa-paper-plane'],
    'reviewing' => ['Under Review','var(--warning)', 'fa-search'],
    'quoted'    => ['Quotation Ready','#7c3aed',     'fa-file-invoice-dollar'],
    'accepted'  => ['Accepted',   'var(--success)',  'fa-check-circle'],
    'rejected'  => ['Rejected',   'var(--danger)',   'fa-times-circle'],
    'invoiced'  => ['Invoiced',   'var(--success)',  'fa-receipt'],
];
[$slabel, $scolor, $sicon] = $statusLabels[$rfq['status']] ?? ['Unknown','var(--text-muted)','fa-question'];

$pageTitle  = 'RFQ #' . $rfq['rfq_number'];
$activePage = 'rfqs';
include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
    <div class="breadcrumb-inner">
        <a href="rfqs.php">My RFQs</a>
        <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
        <span><?= htmlspecialchars($rfq['rfq_number']) ?></span>
    </div>
</div>

<div style="max-width:900px;margin:2rem auto;padding:0 var(--section-px);">

    <!-- Header row -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
        <div>
            <h1 style="font-size:1.4rem;font-weight:800;color:var(--text-dark);">
                <i class="fas fa-file-alt" style="color:var(--primary);"></i> <?= htmlspecialchars($rfq['rfq_number']) ?>
            </h1>
            <p style="font-size:0.82rem;color:var(--text-light);">Submitted: <?= date('d M Y, h:i A', strtotime($rfq['created_at'])) ?></p>
        </div>
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <span style="background:<?= $scolor ?>20;color:<?= $scolor ?>;padding:6px 16px;border-radius:20px;font-size:0.8rem;font-weight:700;">
                <i class="fas <?= $sicon ?>"></i> <?= $slabel ?>
            </span>
            <?php if ($rfq['status'] === 'invoiced'): ?>
            <a href="invoice_view.php?rfq=<?= $rfqId ?>" class="btn btn-sm btn-primary" target="_blank">
                <i class="fas fa-file-pdf"></i> Download Invoice
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quotation Banner (when quoted) -->
    <?php if ($rfq['status'] === 'quoted'): ?>
    <div style="background:linear-gradient(135deg,#7c3aed,#6d28d9);color:#fff;border-radius:12px;padding:1.25rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
        <i class="fas fa-bell" style="font-size:1.5rem;opacity:0.9;"></i>
        <div style="flex:1;">
            <div style="font-weight:800;font-size:1rem;">Your Quotation is Ready!</div>
            <div style="font-size:0.85rem;opacity:0.8;margin-top:0.2rem;">Review the prices below. You can <strong>Accept</strong> to proceed to invoice, or <strong>Request Changes</strong> if you'd like admin to revise.</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Items Table -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-list"></i> Items</span>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:var(--bg-gray);">
                        <th style="padding:0.75rem 1rem;text-align:left;font-size:0.75rem;font-weight:700;color:var(--text-light);text-transform:uppercase;">Product</th>
                        <th style="padding:0.75rem 1rem;text-align:center;font-size:0.75rem;font-weight:700;color:var(--text-light);text-transform:uppercase;">Qty</th>
                        <th style="padding:0.75rem 1rem;text-align:right;font-size:0.75rem;font-weight:700;color:var(--text-light);text-transform:uppercase;">Unit Price</th>
                        <th style="padding:0.75rem 1rem;text-align:right;font-size:0.75rem;font-weight:700;color:var(--text-light);text-transform:uppercase;">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item):
                    $lineTotal = ($item['unit_price'] ?? 0) * $item['quantity'];
                ?>
                <tr style="border-top:1px solid var(--border);">
                    <td style="padding:0.85rem 1rem;">
                        <div style="font-weight:700;color:var(--text-dark);"><?= htmlspecialchars($item['name']) ?></div>
                        <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($item['sku']) ?> · <?= htmlspecialchars($item['category']) ?></div>
                    </td>
                    <td style="padding:0.85rem 1rem;text-align:center;font-weight:700;"><?= $item['quantity'] ?></td>
                    <td style="padding:0.85rem 1rem;text-align:right;">
                        <?php if ($item['unit_price'] !== null && $rfq['status'] !== 'submitted' && $rfq['status'] !== 'reviewing'): ?>
                            <strong><?= formatCurrency((float)$item['unit_price']) ?></strong>
                        <?php else: ?>
                            <span style="color:var(--text-muted);font-size:0.8rem;"><i class="fas fa-clock"></i> Pending</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:0.85rem 1rem;text-align:right;font-weight:700;color:var(--primary);">
                        <?php if ($item['unit_price'] !== null && $rfq['status'] !== 'submitted' && $rfq['status'] !== 'reviewing'): ?>
                            <?= formatCurrency($lineTotal) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if ($rfq['status'] !== 'submitted' && $rfq['status'] !== 'reviewing' && $quoted_total > 0): ?>
                <tfoot>
                    <tr style="border-top:2px solid var(--border);">
                        <td colspan="3" style="padding:0.85rem 1rem;text-align:right;font-weight:700;font-size:0.9rem;">Total Amount:</td>
                        <td style="padding:0.85rem 1rem;text-align:right;font-size:1.1rem;font-weight:800;color:var(--primary);"><?= formatCurrency($quoted_total) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- Notes -->
    <?php if ($rfq['customer_notes'] || $rfq['admin_notes']): ?>
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-body" style="display:flex;flex-direction:column;gap:1rem;">
            <?php if ($rfq['customer_notes']): ?>
            <div>
                <div style="font-size:0.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.4rem;">Your Notes</div>
                <div style="background:var(--bg-gray);padding:0.75rem;border-radius:8px;font-size:0.875rem;"><?= nl2br(htmlspecialchars($rfq['customer_notes'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($rfq['admin_notes']): ?>
            <div>
                <div style="font-size:0.75rem;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.4rem;"><i class="fas fa-headset"></i> Note from TORVO SPAIR</div>
                <div style="background:rgba(37,99,235,0.05);border:1px solid rgba(37,99,235,0.15);padding:0.75rem;border-radius:8px;font-size:0.875rem;"><?= nl2br(htmlspecialchars($rfq['admin_notes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Action Buttons (only when quoted) -->
    <?php if ($canAct): ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <!-- Accept -->
        <form method="POST">
            <input type="hidden" name="action" value="accept">
            <button type="submit" class="btn btn-full btn-lg"
                style="background:var(--success);color:#fff;border:none;"
                onclick="return confirm('Accept this quotation and proceed to invoice?')">
                <i class="fas fa-check-circle"></i> Accept Quotation
            </button>
        </form>
        <!-- Reject / Request Changes -->
        <div>
            <button type="button" class="btn btn-full btn-lg btn-outline"
                onclick="document.getElementById('reject-form').style.display='block';this.style.display='none'">
                <i class="fas fa-times-circle" style="color:var(--danger);"></i> Request Changes
            </button>
            <div id="reject-form" style="display:none;margin-top:0.75rem;">
                <form method="POST">
                    <input type="hidden" name="action" value="reject">
                    <textarea name="reject_note" class="form-control" rows="2"
                        placeholder="Tell us what to change (e.g. price too high for item X)..."></textarea>
                    <button type="submit" class="btn btn-full btn-danger" style="margin-top:0.5rem;">
                        <i class="fas fa-paper-plane"></i> Send Request
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
