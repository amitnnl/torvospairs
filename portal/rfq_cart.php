<?php
require_once __DIR__ . '/config/auth.php';


$db      = portalDB();
$rfqCart = getRFQCart();

// Handle remove
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'remove') { removeFromRFQ((int)$_POST['product_id']); }
    if ($action === 'update') {
        foreach ($_POST['qty'] ?? [] as $pid => $qty) {
            $qty = max(1, (int)$qty);
            if (isset($_SESSION['rfq_cart'][$pid])) $_SESSION['rfq_cart'][$pid]['qty'] = $qty;
        }
    }
    
    // Bulk Upload Handler
    if ($action === 'bulk_upload') {
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            $added = 0; $failed = 0;
            $header = true;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if ($header) { $header = false; continue; } // Skip header row
                
                $sku = sanitize($data[0] ?? '');
                $qty = max(1, (int)($data[1] ?? 1));
                
                if ($sku) {
                    $stmt = $db->prepare("SELECT id FROM products WHERE sku = ? AND status='active' LIMIT 1");
                    $stmt->execute([$sku]);
                    $pid = $stmt->fetchColumn();
                    
                    if ($pid) {
                        addToRFQ((int)$pid, $qty);
                        $added++;
                    } else {
                        $failed++;
                    }
                }
            }
            fclose($handle);
            
            if ($added > 0) {
                setPortalFlash('success', "Successfully added <strong>$added</strong> items from CSV. " . ($failed > 0 ? "$failed items were not found." : ""));
            } else if ($failed > 0) {
                setPortalFlash('error', "Could not add any items. $failed SKUs were not found in our system.");
            }
        }
    }

    if ($action === 'submit') {
        requireCustomerLogin();
        if (!empty($_SESSION['rfq_cart'])) {
            $customer = currentCustomer();
            $rfqNum   = 'RFQ-' . strtoupper(substr(uniqid(), -6));
            $notes    = sanitize($_POST['notes'] ?? '');
            $db->prepare("INSERT INTO rfqs (customer_id, rfq_number, status, customer_notes) VALUES (?,?,?,?)")
               ->execute([$customer['id'], $rfqNum, 'submitted', $notes]);
            $rfqId = $db->lastInsertId();

            $ins = $db->prepare("INSERT INTO rfq_items (rfq_id, product_id, quantity, unit_price) VALUES (?,?,?,?)");
            foreach ($_SESSION['rfq_cart'] as $item) {
                $pRow = $db->prepare("SELECT price FROM products WHERE id=?");
                $pRow->execute([$item['product_id']]);
                $price = $pRow->fetchColumn() ?: 0;
                $ins->execute([$rfqId, $item['product_id'], $item['qty'], $price]);
            }
            clearRFQ();
            portalFlash('success', "RFQ <strong>$rfqNum</strong> submitted successfully! We'll get back to you within 24 hours.");
            header('Location: rfqs.php'); exit;
        }
    }

    $rfqCart = getRFQCart();
    header('Location: rfq_cart.php'); exit;
}

// Load product details for cart items
$cartProducts = [];
if (!empty($rfqCart)) {
    $ids  = implode(',', array_map('intval', array_keys($rfqCart)));
    $rows = $db->query("SELECT p.*, c.name AS cat FROM products p JOIN categories c ON p.category_id=c.id WHERE p.id IN ($ids)")->fetchAll();
    foreach ($rows as $r) $cartProducts[$r['id']] = $r;
}

$subtotal = 0;
foreach ($rfqCart as $pid => $item) {
    $subtotal += ($cartProducts[$pid]['price'] ?? 0) * $item['qty'];
}

$pageTitle  = 'RFQ Cart';
$activePage = '';
include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb"><div class="breadcrumb-inner">
    <a href="catalogue.php">Catalogue</a> <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
    <span>RFQ Cart</span>
</div></div>

<div class="section container">
<div style="display:grid;grid-template-columns:1.6fr 1fr;gap:1.5rem;align-items:start;">

    <!-- Cart Items -->
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-shopping-cart"></i> Request for Quotation Cart</div>
                <span style="font-size:0.8rem;color:var(--text-light);"><?= count($rfqCart) ?> item<?= count($rfqCart)!=1?'s':'' ?></span>
            </div>

            <?php if (empty($rfqCart)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <h3>Your RFQ Cart is Empty</h3>
                <p>Add products from the catalogue to request a quotation.</p>
                <a href="catalogue.php" class="btn btn-primary" style="margin-top:1rem;">Browse Catalogue</a>
            </div>
            <?php else: ?>
            <form method="POST" id="cartForm">
                <input type="hidden" name="action" value="update">
                <?php foreach ($rfqCart as $pid => $item):
                    $p = $cartProducts[$pid] ?? null;
                    if (!$p) continue;
                    $itemTotal = $p['price'] * $item['qty'];
                ?>
                <div class="rfq-item">
                    <div class="rfq-item-img">
                        <?php if ($p['image'] && file_exists(UPLOAD_DIR.$p['image'])): ?>
                            <img src="<?= UPLOAD_URL . htmlspecialchars($p['image']) ?>" alt="">
                        <?php else: ?>
                            <i class="fas fa-cog" style="font-size:1.5rem;color:var(--text-muted);"></i>
                        <?php endif; ?>
                    </div>
                    <div class="rfq-item-info">
                        <div class="rfq-item-name"><?= htmlspecialchars($p['name']) ?></div>
                        <div class="rfq-item-sku"><?= htmlspecialchars($p['sku']) ?> · <?= htmlspecialchars($p['cat']) ?></div>
                        <div style="display:flex;align-items:center;gap:1.5rem;margin-top:0.6rem;flex-wrap:wrap;">
                            <!-- Qty -->
                            <div class="qty-input">
                                <button type="button" class="qty-btn" onclick="changeQty(<?= $pid ?>, -1)">−</button>
                                <input type="number" name="qty[<?= $pid ?>]" id="qty_<?= $pid ?>" value="<?= $item['qty'] ?>" min="1" class="qty-num" onchange="updateSubtotal(<?= $pid ?>, <?= $p['price'] ?>)">
                                <button type="button" class="qty-btn" onclick="changeQty(<?= $pid ?>, 1)">+</button>
                            </div>
                            <div style="font-size:0.85rem;color:var(--text-light);">
                                <?= formatCurrency($p['price']) ?> / unit
                            </div>
                            <div style="font-weight:700;color:var(--primary);font-size:0.95rem;" id="total_<?= $pid ?>">
                                <?= formatCurrency($itemTotal) ?>
                            </div>
                        </div>
                    </div>
                    <form method="POST" style="flex-shrink:0;">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="product_id" value="<?= $pid ?>">
                        <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--text-muted);padding:0.25rem;" title="Remove">
                            <i class="fas fa-times"></i>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>

                <div style="padding:1rem;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                    <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-sync-alt"></i> Update Quantities</button>
                    <a href="catalogue.php" style="font-size:0.82rem;color:var(--primary);"><i class="fas fa-plus"></i> Add More Items</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary & Submit -->
    <div>
        <!-- Bulk Add Card -->
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="card-header"><div class="card-title"><i class="fas fa-file-upload"></i> Bulk Add Items</div></div>
            <div class="card-body">
                <p style="font-size:0.75rem;color:var(--text-light);margin-bottom:1rem;">Have a list of parts? Upload a CSV file with <strong>SKU</strong> and <strong>Quantity</strong> columns to add them all at once.</p>
                <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:0.75rem;">
                    <input type="hidden" name="action" value="bulk_upload">
                    <div style="position:relative;border:2px dashed var(--border);border-radius:10px;padding:1.5rem;text-align:center;transition:var(--transition);" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                        <input type="file" name="csv_file" accept=".csv" required style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;">
                        <i class="fas fa-cloud-upload-alt" style="font-size:1.5rem;color:var(--primary);margin-bottom:0.5rem;display:block;"></i>
                        <span style="font-size:0.8rem;color:var(--text-medium);font-weight:600;">Click or Drag CSV File</span>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm btn-full"><i class="fas fa-plus-circle"></i> Upload & Add Items</button>
                    <a href="data:text/csv;charset=utf-8,SKU,Quantity%0A601001,5%0A701002,12" download="torvo_bulk_rfq_template.csv" style="font-size:0.7rem;color:var(--text-light);text-align:center;text-decoration:underline;">
                        <i class="fas fa-download"></i> Download Sample CSV
                    </a>
                </form>
            </div>
        </div>

        <div class="card" style="position:sticky;top:84px;">
            <div class="card-header"><div class="card-title"><i class="fas fa-file-invoice"></i> Order Summary</div></div>
            <div class="card-body">
                <div style="display:flex;justify-content:space-between;margin-bottom:0.6rem;font-size:0.875rem;">
                    <span style="color:var(--text-light);">Items</span>
                    <span><?= count($rfqCart) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:0.6rem;font-size:0.875rem;">
                    <span style="color:var(--text-light);">Indicative Total</span>
                    <span id="grandTotal" style="font-weight:700;color:var(--primary);"><?= formatCurrency($subtotal) ?></span>
                </div>
                <div style="background:rgba(37,99,235,0.06);border:1px solid rgba(37,99,235,0.15);border-radius:8px;padding:0.75rem;font-size:0.78rem;color:var(--text-medium);margin:1rem 0;">
                    <i class="fas fa-info-circle" style="color:var(--primary);"></i>
                    Prices shown are indicative. Final pricing will be confirmed in our quotation.
                </div>

                <?php if (!empty($rfqCart)): ?>
                <?php if (customerLoggedIn()): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="submit">
                    <div class="form-group">
                        <label class="form-label">Additional Notes / Requirements</label>
                        <textarea name="notes" class="form-control" rows="3"
                            placeholder="Delivery timeline, special requirements, delivery location..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-accent btn-full btn-lg">
                        <i class="fas fa-paper-plane"></i> Submit RFQ
                    </button>
                </form>
                <?php else: ?>
                <div style="text-align:center;padding:0.5rem 0;">
                    <p style="font-size:0.82rem;color:var(--text-light);margin-bottom:1rem;">Login to submit your RFQ</p>
                    <a href="index.php?redirect=1" class="btn btn-primary btn-full"><i class="fas fa-sign-in-alt"></i> Login to Submit</a>
                    <a href="register.php" class="btn btn-outline btn-full" style="margin-top:0.5rem;"><i class="fas fa-building"></i> Register as Partner</a>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>

<script>
function changeQty(pid, delta) {
    const input = document.getElementById('qty_' + pid);
    const newVal = Math.max(1, parseInt(input.value) + delta);
    input.value = newVal;
    updateSubtotal(pid, parseFloat(input.dataset.price || 0));
}

function updateSubtotal(pid, price) {
    const qty = parseInt(document.getElementById('qty_' + pid).value) || 1;
    const total = qty * price;
    const el = document.getElementById('total_' + pid);
    if (el) el.textContent = '₹' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
