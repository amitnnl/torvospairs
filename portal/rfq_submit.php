<?php
/**
 * Portal — Submit RFQ / Demand List
 * Active partners submit their cart as a formal RFQ
 */
require_once __DIR__ . '/config/auth.php';
requireActivePartner();

$customer = currentCustomer();
$db       = portalDB();
$error    = '';
$cart     = getRFQCart();

if (empty($cart)) {
    setPortalFlash('warning', 'Your RFQ cart is empty. Add products before submitting.');
    header('Location: catalogue.php'); exit;
}

// Load product details for cart items
$ids  = array_keys($cart);
$ph   = implode(',', array_fill(0, count($ids), '?'));
$prods = $db->prepare("SELECT p.id, p.name, p.sku, p.brand, p.quantity AS stock, c.name AS category FROM products p JOIN categories c ON p.category_id=c.id WHERE p.id IN ($ph) AND p.status='active'");
$prods->execute($ids);
$products = [];
foreach ($prods->fetchAll() as $p) $products[$p['id']] = $p;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notes = trim($_POST['customer_notes'] ?? '');

    // Generate RFQ number
    $rfqNum = 'RFQ-' . date('Y') . '-' . str_pad(
        ($db->query("SELECT COUNT(*)+1 FROM rfqs")->fetchColumn()), 4, '0', STR_PAD_LEFT
    );

    // Insert RFQ
    $stmt = $db->prepare("INSERT INTO rfqs (customer_id, rfq_number, status, customer_notes) VALUES (?,?,?,?)");
    $stmt->execute([$customer['id'], $rfqNum, 'submitted', $notes]);
    $rfqId = $db->lastInsertId();

    // Insert items
    $iStmt = $db->prepare("INSERT INTO rfq_items (rfq_id, product_id, quantity) VALUES (?,?,?)");
    foreach ($cart as $pid => $item) {
        $iStmt->execute([$rfqId, $pid, max(1, (int)$item['qty'])]);
    }

    // Clear cart
    clearRFQ();

    setPortalFlash('success', "RFQ #$rfqNum submitted! Our team will review and send you a quotation shortly.");
    header('Location: rfqs.php'); exit;
}

$pageTitle  = 'Submit RFQ';
$activePage = 'rfqs';
include __DIR__ . '/includes/header.php';

$total_items = array_sum(array_column($cart, 'qty'));
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="breadcrumb-inner">
        <a href="catalogue.php"><i class="fas fa-th-large"></i> Catalogue</a>
        <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
        <a href="rfq_cart.php"><i class="fas fa-shopping-cart"></i> RFQ Cart</a>
        <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
        <span>Submit Request</span>
    </div>
</div>

<div style="max-width:900px;margin:2rem auto;padding:0 var(--section-px);">
    <!-- Header -->
    <div style="margin-bottom:1.5rem;">
        <h1 style="font-size:1.5rem;font-weight:800;color:var(--text-dark);"><i class="fas fa-file-invoice" style="color:var(--primary);"></i> Submit Demand List</h1>
        <p style="color:var(--text-light);font-size:0.875rem;">Review your items and submit for quotation. Admin will set prices and send you a quote.</p>
    </div>

    <form method="POST">
        <!-- Items Review -->
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-list"></i> Items Requested (<?= $total_items ?> units)</span>
                <a href="rfq_cart.php" style="font-size:0.8rem;color:var(--primary);"><i class="fas fa-edit"></i> Edit Cart</a>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:var(--bg-gray);">
                            <th style="padding:0.75rem 1rem;text-align:left;font-size:0.75rem;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.5px;">Product</th>
                            <th style="padding:0.75rem 1rem;text-align:left;font-size:0.75rem;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.5px;">SKU</th>
                            <th style="padding:0.75rem 1rem;text-align:center;font-size:0.75rem;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.5px;">Qty Requested</th>
                            <th style="padding:0.75rem 1rem;text-align:center;font-size:0.75rem;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.5px;">Quoted Price</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cart as $pid => $item):
                        $p = $products[$pid] ?? null;
                        if (!$p) continue;
                    ?>
                    <tr style="border-top:1px solid var(--border);">
                        <td style="padding:0.85rem 1rem;">
                            <div style="font-weight:700;color:var(--text-dark);font-size:0.875rem;"><?= htmlspecialchars($p['name']) ?></div>
                            <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($p['category']) ?><?= $p['brand'] ? ' · '.$p['brand'] : '' ?></div>
                        </td>
                        <td style="padding:0.85rem 1rem;font-size:0.8rem;color:var(--text-medium);"><?= htmlspecialchars($p['sku']) ?></td>
                        <td style="padding:0.85rem 1rem;text-align:center;">
                            <span style="background:var(--primary);color:#fff;padding:4px 14px;border-radius:20px;font-size:0.85rem;font-weight:700;"><?= (int)$item['qty'] ?></span>
                        </td>
                        <td style="padding:0.85rem 1rem;text-align:center;">
                            <span style="color:var(--text-muted);font-size:0.8rem;"><i class="fas fa-lock"></i> Pending Quote</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Notes -->
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card-body">
                <label class="form-label"><i class="fas fa-comment-alt"></i> Additional Notes <span style="font-weight:400;color:var(--text-muted);">(Optional)</span></label>
                <textarea name="customer_notes" class="form-control" rows="3"
                    placeholder="e.g. Delivery location preference, urgency, specific requirements..."></textarea>
                <div class="form-hint">This note will be shared with the admin to help prepare your quotation.</div>
            </div>
        </div>

        <!-- Partner Info -->
        <div style="background:var(--bg-gray);border:1px solid var(--border);border-radius:12px;padding:1.25rem;margin-bottom:1.5rem;">
            <div style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:0.75rem;">Submitting as</div>
            <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
                <div style="width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;">
                    <?= strtoupper(substr($customer['company_name'] ?? $customer['name'], 0, 1)) ?>
                </div>
                <div>
                    <div style="font-weight:700;color:var(--text-dark);"><?= htmlspecialchars($customer['company_name'] ?? $customer['name']) ?></div>
                    <div style="font-size:0.8rem;color:var(--text-light);"><?= htmlspecialchars($customer['email']) ?> · Tier: <?= ucfirst($customer['tier'] ?? 'standard') ?></div>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:1rem;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-paper-plane"></i> Submit Demand List
            </button>
            <a href="rfq_cart.php" class="btn btn-outline btn-lg">
                <i class="fas fa-arrow-left"></i> Back to Cart
            </a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
