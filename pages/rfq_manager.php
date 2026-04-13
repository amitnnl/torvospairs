<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle      = 'RFQ Management';
$pageIcon       = 'fas fa-file-invoice';
$activePage     = 'rfqs';
$pageBreadcrumb = 'RFQ Management';
include BASE_PATH . '/includes/header.php';
requireAdmin();

$db = getDB();

// ═══════════════════════════════════════════════════════════════════
//  HANDLE POST ACTIONS
// ═══════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $rfqId  = (int)$_POST['rfq_id'];

    // ── 1. Save prices + send quotation to partner ─────────────────
    if ($action === 'send_quotation' && $rfqId) {
        $prices     = $_POST['prices'] ?? [];
        $adminNotes = trim($_POST['admin_notes'] ?? '');

        // Save each price
        foreach ($prices as $itemId => $price) {
            $price = round((float)$price, 2);
            if ($price >= 0) {
                $db->prepare("UPDATE rfq_items SET unit_price=? WHERE id=? AND rfq_id=?")
                   ->execute([$price, (int)$itemId, $rfqId]);
            }
        }

        // Set status to quoted
        $db->prepare("UPDATE rfqs SET status='quoted', quoted_at=NOW(), admin_notes=? WHERE id=?")
           ->execute([$adminNotes ?: 'Prices quoted. Please review and confirm.', $rfqId]);

        // Fetch RFQ info for notification
        $rfqInfo = $db->prepare("SELECT r.rfq_number, r.customer_id, c.contact_name, c.email
                                  FROM rfqs r JOIN customers c ON c.id=r.customer_id WHERE r.id=?")->execute([$rfqId])
                      ? null : null;
        $stmt2 = $db->prepare("SELECT r.rfq_number, r.customer_id, c.contact_name, c.email FROM rfqs r JOIN customers c ON c.id=r.customer_id WHERE r.id=?");
        $stmt2->execute([$rfqId]);
        $rfqInfo = $stmt2->fetch();

        if ($rfqInfo) {
            // In-portal notification
            try {
                $db->prepare("INSERT INTO notifications (customer_id, type, title, message, rfq_id) VALUES (?,?,?,?,?)")
                   ->execute([
                       $rfqInfo['customer_id'],
                       'rfq_quoted',
                       "Quotation Ready: {$rfqInfo['rfq_number']}",
                       "Your quotation for RFQ #{$rfqInfo['rfq_number']} is ready. Please log in to review the prices and accept or request changes.",
                       $rfqId
                   ]);
            } catch (PDOException $e) { /* ignore */ }

            // Email notification
            $siteName   = defined('APP_NAME') ? APP_NAME : 'TORVO SPAIR';
            $portalUrl  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
            $portalUrl .= (str_contains($_SERVER['HTTP_HOST'], 'localhost') ? '/torvo_spair' : '') . '/portal';
            $rfqUrl     = "$portalUrl/rfq_view.php?id=$rfqId";

            $to      = $rfqInfo['email'];
            $subject = "[$siteName] Quotation Ready — {$rfqInfo['rfq_number']}";
            $body    = "Dear {$rfqInfo['contact_name']},\n\n"
                     . "Your quotation for RFQ #{$rfqInfo['rfq_number']} is ready for review.\n\n"
                     . "Please login to your portal account to view the prices and confirm:\n$rfqUrl\n\n"
                     . "Once you accept, we will generate your invoice and process the order.\n\n"
                     . "— $siteName Team";
            $headers = "From: no-reply@torvo.com\r\nX-Mailer: PHP/" . phpversion();
            @mail($to, $subject, $body, $headers);
        }

        setFlash('success', "Quotation sent to partner for RFQ #{$rfqInfo['rfq_number']}.");
        header("Location: rfq_manager.php?rfq=$rfqId"); exit;
    }

    // ── 2. Save prices only (draft, don't send) ────────────────────
    if ($action === 'save_prices' && $rfqId) {
        $prices = $_POST['prices'] ?? [];
        foreach ($prices as $itemId => $price) {
            $price = round((float)$price, 2);
            if ($price >= 0) {
                $db->prepare("UPDATE rfq_items SET unit_price=? WHERE id=? AND rfq_id=?")
                   ->execute([$price, (int)$itemId, $rfqId]);
            }
        }
        setFlash('success', 'Prices saved (draft — not yet sent to partner).');
        header("Location: rfq_manager.php?rfq=$rfqId"); exit;
    }

    // ── 3. Generate Invoice (only for accepted RFQs) ───────────────
    if ($action === 'generate_invoice' && $rfqId) {
        // Verify status is accepted
        $rfqCheck = $db->prepare("SELECT * FROM rfqs WHERE id=? AND status='accepted'");
        $rfqCheck->execute([$rfqId]);
        $rfqData = $rfqCheck->fetch();

        if (!$rfqData) {
            setFlash('error', 'Invoice can only be generated for Accepted RFQs.');
            header("Location: rfq_manager.php?rfq=$rfqId"); exit;
        }

        // Check if invoice already exists
        $existing = $db->prepare("SELECT id FROM invoices WHERE rfq_id=?");
        $existing->execute([$rfqId]);
        if ($existing->fetch()) {
            setFlash('warning', 'Invoice already exists for this RFQ.');
            header("Location: rfq_manager.php?rfq=$rfqId"); exit;
        }

        // Load items with prices
        $items = $db->prepare("SELECT ri.*, p.name, p.quantity AS stock FROM rfq_items ri JOIN products p ON p.id=ri.product_id WHERE ri.rfq_id=?");
        $items->execute([$rfqId]);
        $items = $items->fetchAll();

        // Check all items are priced
        $allPriced = true;
        foreach ($items as $item) {
            if ($item['unit_price'] === null || $item['unit_price'] <= 0) {
                $allPriced = false; break;
            }
        }
        if (!$allPriced) {
            setFlash('error', 'All items must have a quoted price before generating invoice.');
            header("Location: rfq_manager.php?rfq=$rfqId"); exit;
        }

        // Calculate totals
        $subtotal = 0;
        foreach ($items as $item) $subtotal += $item['unit_price'] * $item['quantity'];
        $total = $subtotal; // No GST as per requirement

        // Generate invoice number
        $invCount = (int)$db->query("SELECT COUNT(*)+1 FROM invoices")->fetchColumn();
        $invNumber = 'INV-' . date('Y') . '-' . str_pad($invCount, 4, '0', STR_PAD_LEFT);

        // Insert invoice
        $invNotes = trim($_POST['invoice_notes'] ?? '');
        $db->prepare("INSERT INTO invoices (invoice_number, rfq_id, customer_id, subtotal, total_amount, notes) VALUES (?,?,?,?,?,?)")
           ->execute([$invNumber, $rfqId, $rfqData['customer_id'], $subtotal, $total, $invNotes]);
        $invoiceId = $db->lastInsertId();

        // Update RFQ status to invoiced
        $db->prepare("UPDATE rfqs SET status='invoiced', invoiced_at=NOW() WHERE id=?")->execute([$rfqId]);

        // Auto-deduct stock + log
        foreach ($items as $item) {
            // Fetch current stock
            $prod = $db->prepare("SELECT quantity FROM products WHERE id=?");
            $prod->execute([$item['product_id']]);
            $prod = $prod->fetch();
            $prevStock = (int)($prod['quantity'] ?? 0);
            $newStock  = max(0, $prevStock - $item['quantity']);

            $db->prepare("UPDATE products SET quantity=? WHERE id=?")->execute([$newStock, $item['product_id']]);

            // Log the stock movement
            try {
                $db->prepare("INSERT INTO stock_logs (product_id, user_id, type, quantity, previous_stock, current_stock, notes, invoice_id) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([
                       $item['product_id'],
                       $_SESSION['user_id'],
                       'out',
                       $item['quantity'],
                       $prevStock,
                       $newStock,
                       "Auto-deducted — Invoice $invNumber",
                       $invoiceId
                   ]);
            } catch (PDOException $e) { /* ignore if column mismatch */ }
        }

        // Notify partner
        try {
            $db->prepare("INSERT INTO notifications (customer_id, type, title, message, rfq_id) VALUES (?,?,?,?,?)")
               ->execute([
                   $rfqData['customer_id'],
                   'rfq_invoiced',
                   "Invoice Ready: $invNumber",
                   "Invoice $invNumber has been generated for your order. You can download it from your RFQ history.",
                   $rfqId
               ]);
        } catch (PDOException $e) { /* ignore */ }

        // Email notification
        $stmt3 = $db->prepare("SELECT email, contact_name FROM customers WHERE id=?");
        $stmt3->execute([$rfqData['customer_id']]);
        $cust = $stmt3->fetch();
        if ($cust) {
            $siteName = defined('APP_NAME') ? APP_NAME : 'TORVO SPAIR';
            $portalUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
            $portalUrl .= (str_contains($_SERVER['HTTP_HOST'], 'localhost') ? '/torvo_spair' : '') . '/portal';
            $subject = "[$siteName] Invoice $invNumber is Ready";
            $body    = "Dear {$cust['contact_name']},\n\n"
                     . "Your invoice $invNumber has been generated.\n"
                     . "Login to download your invoice: $portalUrl/rfqs.php\n\n"
                     . "Total Amount: ₹" . number_format($total, 2) . "\n\n"
                     . "— $siteName Team";
            @mail($cust['email'], $subject, $body, "From: no-reply@torvo.com\r\n");
        }

        setFlash('success', "Invoice $invNumber generated! Stock auto-deducted. Partner notified.");
        header("Location: rfq_manager.php?rfq=$rfqId"); exit;
    }

    // ── 4. Update status + admin notes ────────────────────────────
    if ($action === 'update_rfq' && $rfqId) {
        $status = sanitize($_POST['status'] ?? '');
        $notes  = sanitize($_POST['admin_notes'] ?? '');
        $validStatuses = ['submitted','reviewing','quoted','accepted','rejected','closed','invoiced'];
        if (in_array($status, $validStatuses)) {
            $db->prepare("UPDATE rfqs SET status=?, admin_notes=? WHERE id=?")->execute([$status, $notes, $rfqId]);
            setFlash('success', 'RFQ updated.');
        }
        header("Location: rfq_manager.php?rfq=$rfqId"); exit;
    }

    header('Location: rfq_manager.php'); exit;
}

// ═══════════════════════════════════════════════════════════════════
//  FETCH DATA
// ═══════════════════════════════════════════════════════════════════
$statusFilter = sanitize($_GET['status']   ?? '');
$selectedRFQ  = (int)($_GET['rfq']         ?? 0);

$sql    = "SELECT r.*, c.company_name, c.contact_name, c.email, c.phone, c.tier,
           (SELECT COUNT(*) FROM rfq_items WHERE rfq_id=r.id) AS item_count
           FROM rfqs r JOIN customers c ON r.customer_id = c.id";
$where  = []; $params = [];
if ($statusFilter) { $where[] = "r.status=?"; $params[] = $statusFilter; }
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY r.created_at DESC";
$stmt = $db->prepare($sql); $stmt->execute($params);
$rfqs = $stmt->fetchAll();

$statuses     = ['submitted','reviewing','quoted','accepted','rejected','invoiced','closed'];
$statusColors = [
    'submitted' => 'badge-info',
    'reviewing' => 'badge-warning',
    'quoted'    => 'badge-primary',
    'accepted'  => 'badge-success',
    'rejected'  => 'badge-danger',
    'invoiced'  => 'badge-success',
    'closed'    => 'badge-gray',
];

$rfqCounts = ['all' => $db->query("SELECT COUNT(*) FROM rfqs")->fetchColumn()];
foreach ($statuses as $s) {
    $c = $db->prepare("SELECT COUNT(*) FROM rfqs WHERE status=?"); $c->execute([$s]);
    $rfqCounts[$s] = (int)$c->fetchColumn();
}

// Load selected RFQ detail
$rfqDetail = null; $rfqItems = []; $existingInvoice = null;
if ($selectedRFQ) {
    $d = $db->prepare("SELECT r.*,c.company_name,c.contact_name,c.email,c.phone,c.gstin,c.address,c.city,c.state,c.tier FROM rfqs r JOIN customers c ON r.customer_id=c.id WHERE r.id=?");
    $d->execute([$selectedRFQ]);
    $rfqDetail = $d->fetch();
    if ($rfqDetail) {
        $i = $db->prepare("SELECT ri.*, p.name AS pname, p.sku, p.price AS list_price, p.quantity AS stock, c.name AS cat FROM rfq_items ri JOIN products p ON ri.product_id=p.id JOIN categories c ON p.category_id=c.id WHERE ri.rfq_id=?");
        $i->execute([$selectedRFQ]);
        $rfqItems = $i->fetchAll();

        $inv = $db->prepare("SELECT * FROM invoices WHERE rfq_id=?");
        $inv->execute([$selectedRFQ]);
        $existingInvoice = $inv->fetch();
    }
}
?>

<div class="page-body">

<!-- Status Filter Pills -->
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1.25rem;align-items:center;">
    <a href="rfq_manager.php" class="badge-pill <?= !$statusFilter?'badge-primary':'badge-gray' ?>" style="cursor:pointer;text-decoration:none;padding:0.4rem 0.85rem;">
        All <span style="margin-left:4px;opacity:0.7;"><?= $rfqCounts['all'] ?></span>
    </a>
    <?php foreach ($statuses as $s): ?>
    <a href="?status=<?= $s ?>" class="badge-pill <?= $statusFilter===$s?$statusColors[$s]:'badge-gray' ?>" style="cursor:pointer;text-decoration:none;padding:0.4rem 0.85rem;">
        <?= ucfirst($s) ?> <span style="margin-left:4px;opacity:0.7;"><?= $rfqCounts[$s] ?? 0 ?></span>
    </a>
    <?php endforeach; ?>
    <div style="margin-left:auto;display:flex;gap:0.5rem;">
        <a href="partner_applications.php" class="btn btn-accent btn-sm">
            <i class="fas fa-handshake"></i> Partner Applications
            <?php
            $pendingCount = $db->query("SELECT COUNT(*) FROM customers WHERE status='pending'")->fetchColumn();
            if ($pendingCount > 0): ?>
            <span style="background:#fff;color:var(--accent);border-radius:10px;padding:1px 7px;font-size:0.7rem;font-weight:800;"><?= $pendingCount ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<div style="display:grid;grid-template-columns:<?= $rfqDetail ? '1fr 1.5fr' : '1fr' ?>;gap:1.25rem;align-items:start;">

<!-- ═══ RFQ List ═══════════════════════════════════════════════════ -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-file-invoice"></i> RFQs <span style="font-size:0.78rem;color:var(--text-muted);font-weight:400;"><?= count($rfqs) ?> found</span></div>
    </div>
    <?php if (empty($rfqs)): ?>
    <div class="empty-state"><i class="fas fa-inbox"></i><h3>No RFQs found</h3><p>Customer RFQs will appear here.</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>RFQ #</th><th>Company</th><th>Items</th><th>Status</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rfqs as $r): ?>
            <tr style="<?= $selectedRFQ==$r['id']?'background:rgba(37,99,235,0.05);':'' ?>">
                <td style="font-weight:700;color:var(--primary);"><?= htmlspecialchars($r['rfq_number']) ?></td>
                <td>
                    <div style="font-weight:600;font-size:0.82rem;"><?= htmlspecialchars($r['company_name']) ?></div>
                    <div style="font-size:0.7rem;color:var(--text-muted);"><?= htmlspecialchars($r['contact_name']) ?></div>
                </td>
                <td style="text-align:center;font-weight:700;color:var(--primary);"><?= $r['item_count'] ?></td>
                <td><span class="badge-pill <?= $statusColors[$r['status']] ?? 'badge-gray' ?>"><?= ucfirst($r['status']) ?></span></td>
                <td style="font-size:0.72rem;color:var(--text-muted);white-space:nowrap;"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                <td>
                    <a href="rfq_manager.php?rfq=<?= $r['id'] ?><?= $statusFilter?"&status=$statusFilter":'' ?>" class="btn btn-outline btn-sm btn-icon" data-tooltip="Open">
                        <i class="fas fa-folder-open"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php if ($rfqDetail): ?>
<!-- ═══ RFQ Detail Panel ═════════════════════════════════════════════ -->
<div style="display:flex;flex-direction:column;gap:1rem;">

    <!-- Header / Customer Info -->
    <div class="card">
        <div class="card-header" style="flex-wrap:wrap;gap:0.5rem;">
            <div>
                <div class="card-title"><?= htmlspecialchars($rfqDetail['rfq_number']) ?></div>
                <div style="font-size:0.75rem;color:var(--text-muted);">Submitted <?= date('d M Y, h:ia', strtotime($rfqDetail['created_at'])) ?></div>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
                <span class="badge-pill <?= $statusColors[$rfqDetail['status']] ?? 'badge-gray' ?>"><?= ucfirst($rfqDetail['status']) ?></span>
                <?php if ($rfqDetail['tier'] !== 'standard'): ?>
                <span class="badge-pill badge-warning"><?= ucfirst($rfqDetail['tier']) ?> Partner</span>
                <?php endif; ?>
                <!-- Invoice link if exists -->
                <?php if ($existingInvoice): ?>
                <a href="<?= (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https://':'http://').$_SERVER['HTTP_HOST'].(str_contains($_SERVER['HTTP_HOST'],'localhost')?'/torvo_spair':'')?>/portal/invoice_view.php?rfq=<?= $selectedRFQ ?>" target="_blank" class="btn btn-sm" style="background:var(--success);color:#fff;">
                    <i class="fas fa-file-pdf"></i> <?= htmlspecialchars($existingInvoice['invoice_number']) ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;font-size:0.83rem;">
                <div>
                    <div style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:0.35rem;">Partner</div>
                    <div style="font-weight:700;"><?= htmlspecialchars($rfqDetail['company_name']) ?></div>
                    <div><?= htmlspecialchars($rfqDetail['contact_name']) ?></div>
                    <div style="color:var(--text-muted);"><?= htmlspecialchars($rfqDetail['email']) ?></div>
                    <?php if ($rfqDetail['phone']): ?><div style="color:var(--text-muted);"><?= htmlspecialchars($rfqDetail['phone']) ?></div><?php endif; ?>
                    <?php if ($rfqDetail['gstin']): ?><div><code style="font-size:0.72rem;">GSTIN: <?= htmlspecialchars($rfqDetail['gstin']) ?></code></div><?php endif; ?>
                </div>
                <div>
                    <?php if ($rfqDetail['customer_notes']): ?>
                    <div style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:0.3rem;">Partner's Note</div>
                    <div style="background:rgba(249,115,22,0.06);border-left:3px solid #f97316;padding:0.5rem 0.75rem;border-radius:0 6px 6px 0;font-style:italic;font-size:0.82rem;">
                        <?= nl2br(htmlspecialchars($rfqDetail['customer_notes'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php
    $rfqSubtotal = 0;
    $allPriced   = true;
    foreach ($rfqItems as $item) {
        $qp = $item['unit_price'] > 0 ? $item['unit_price'] : 0;
        $rfqSubtotal += $qp * $item['quantity'];
        if ($qp <= 0) $allPriced = false;
    }
    $canInvoice = ($rfqDetail['status'] === 'accepted' && $allPriced && !$existingInvoice);
    $canQuote   = !in_array($rfqDetail['status'], ['invoiced', 'closed']);
    ?>

    <!-- ═══ Price Items + Send Quotation ══════════════════════════ -->
    <?php if ($canQuote): ?>
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-tags"></i> Item Pricing & Quotation</div>
            <div style="font-size:0.75rem;color:var(--text-muted);">Set prices → Save Draft or Send to Partner</div>
        </div>
        <form method="POST" id="pricingForm">
            <input type="hidden" name="rfq_id" value="<?= $rfqDetail['id'] ?>">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th style="text-align:center;">Req. Qty</th>
                            <th style="text-align:center;">In Stock</th>
                            <th style="text-align:right;">List Price</th>
                            <th>Your Price (₹)</th>
                            <th style="text-align:right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rfqItems as $item):
                        $quotedPrice = $item['unit_price'] > 0 ? $item['unit_price'] : $item['list_price'];
                        $lineTotal   = $quotedPrice * $item['quantity'];
                        $stockAlert  = $item['stock'] < $item['quantity'];
                    ?>
                    <tr style="<?= $stockAlert ? 'background:rgba(220,38,38,0.03);' : '' ?>">
                        <td>
                            <div style="font-weight:600;font-size:0.82rem;"><?= htmlspecialchars($item['pname']) ?></div>
                            <div style="font-size:0.68rem;color:var(--text-muted);"><?= htmlspecialchars($item['cat']) ?></div>
                        </td>
                        <td style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($item['sku']) ?></td>
                        <td style="text-align:center;font-weight:700;"><?= $item['quantity'] ?></td>
                        <td style="text-align:center;">
                            <span style="font-size:0.78rem;font-weight:600;color:<?= $stockAlert ? 'var(--danger)' : 'var(--success)' ?>;">
                                <?= $item['stock'] ?>
                                <?php if ($stockAlert): ?> <i class="fas fa-exclamation-triangle" title="Low stock!"></i><?php endif; ?>
                            </span>
                        </td>
                        <td style="text-align:right;color:var(--text-muted);font-size:0.82rem;">₹<?= number_format($item['list_price'],2) ?></td>
                        <td style="min-width:120px;">
                            <div style="display:flex;align-items:center;background:var(--bg-card2);border:1px solid var(--border-color);border-radius:7px;overflow:hidden;">
                                <span style="padding:0 0.5rem;color:var(--text-muted);font-size:0.8rem;">₹</span>
                                <input type="number"
                                       name="prices[<?= $item['id'] ?>]"
                                       value="<?= number_format($quotedPrice, 2, '.', '') ?>"
                                       step="0.01" min="0"
                                       class="price-input"
                                       data-qty="<?= $item['quantity'] ?>"
                                       data-row="<?= $item['id'] ?>"
                                       oninput="recalcRow(this)"
                                       style="background:none;border:none;outline:none;padding:0.5rem;font-size:0.875rem;font-weight:600;width:90px;color:var(--text-primary);">
                            </div>
                        </td>
                        <td id="line-<?= $item['id'] ?>" style="text-align:right;font-weight:700;color:var(--primary);white-space:nowrap;">
                            ₹<?= number_format($lineTotal, 2) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:var(--bg-card2);font-weight:800;">
                            <td colspan="6" style="text-align:right;padding:0.75rem 1rem;">Total Quoted Amount:</td>
                            <td id="rfqTotal" style="color:var(--primary);padding:0.75rem 1rem;text-align:right;white-space:nowrap;">₹<?= number_format($rfqSubtotal, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Admin Notes -->
            <div style="padding:1rem 1.25rem;border-top:1px solid var(--border-color);">
                <label style="font-size:0.78rem;font-weight:700;color:var(--text-muted);display:block;margin-bottom:0.4rem;">Message to Partner (optional)</label>
                <textarea name="admin_notes" class="form-control" rows="2"
                    placeholder="e.g. Prices valid for 7 days. Delivery in 5-7 business days..."
                ><?= htmlspecialchars($rfqDetail['admin_notes'] ?? '') ?></textarea>
            </div>

            <!-- Action Buttons -->
            <div style="padding:0.75rem 1.25rem;border-top:1px solid var(--border-color);display:flex;gap:0.5rem;justify-content:flex-end;flex-wrap:wrap;">
                <button type="submit" name="action" value="save_prices" class="btn btn-outline btn-sm">
                    <i class="fas fa-save"></i> Save Draft
                </button>
                <button type="submit" name="action" value="send_quotation" class="btn btn-primary"
                    onclick="return confirm('Send this quotation to the partner? They will receive an email and in-portal notification.')">
                    <i class="fas fa-paper-plane"></i> Send Quotation to Partner
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- ═══ Generate Invoice (only when accepted) ══════════════════ -->
    <?php if ($rfqDetail['status'] === 'accepted'): ?>
    <div class="card" style="border:2px solid var(--success);border-radius:12px;">
        <div class="card-header" style="background:rgba(22,163,74,0.06);">
            <div class="card-title" style="color:var(--success);"><i class="fas fa-receipt"></i> Generate Invoice</div>
            <div style="font-size:0.75rem;color:var(--text-muted);">Partner accepted the quotation. Generate invoice and auto-deduct stock.</div>
        </div>
        <?php if ($existingInvoice): ?>
        <div class="card-body">
            <div style="text-align:center;padding:1rem;">
                <i class="fas fa-check-circle" style="font-size:2rem;color:var(--success);margin-bottom:0.5rem;display:block;"></i>
                <strong>Invoice <?= htmlspecialchars($existingInvoice['invoice_number']) ?> already generated</strong>
                <div style="margin-top:0.5rem;">
                    <a href="<?= (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https://':'http://').$_SERVER['HTTP_HOST'].(str_contains($_SERVER['HTTP_HOST'],'localhost')?'/torvo_spair':'')?>/portal/invoice_view.php?rfq=<?= $selectedRFQ ?>" target="_blank" class="btn btn-sm btn-primary">
                        <i class="fas fa-external-link-alt"></i> View Invoice
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="action" value="generate_invoice">
            <input type="hidden" name="rfq_id" value="<?= $rfqDetail['id'] ?>">
            <div class="card-body">
                <?php if (!$allPriced): ?>
                <div class="alert alert-danger" style="margin-bottom:1rem;">
                    <i class="fas fa-exclamation-triangle"></i> All items must have a quoted price before generating the invoice.
                </div>
                <?php else: ?>
                <div style="background:rgba(22,163,74,0.06);border:1px solid rgba(22,163,74,0.2);border-radius:8px;padding:1rem;margin-bottom:1rem;">
                    <div style="font-size:0.82rem;color:var(--text-medium);margin-bottom:0.5rem;">Invoice will include:</div>
                    <ul style="font-size:0.82rem;color:var(--text-medium);padding-left:1.2rem;line-height:1.8;">
                        <li><?= count($rfqItems) ?> item(s) — Total: <strong>₹<?= number_format($rfqSubtotal, 2) ?></strong></li>
                        <li>Stock will be auto-deducted for each item</li>
                        <li>Partner will receive email + in-portal notification</li>
                    </ul>
                </div>
                <div class="form-group">
                    <label class="form-label">Invoice Notes (optional)</label>
                    <textarea name="invoice_notes" class="form-control" rows="2"
                        placeholder="Payment terms, bank details, etc."></textarea>
                </div>
                <?php endif; ?>
            </div>
            <div style="padding:0.75rem 1.25rem;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;">
                <button type="submit" class="btn btn-success" <?= !$allPriced ? 'disabled' : '' ?>
                    onclick="return confirm('Generate invoice and deduct stock? This cannot be undone.')">
                    <i class="fas fa-file-invoice"></i> Generate Invoice & Deduct Stock
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ═══ Quick Status Update ════════════════════════════════════ -->
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-edit"></i> Status Override</div></div>
        <form method="POST">
            <input type="hidden" name="action" value="update_rfq">
            <input type="hidden" name="rfq_id" value="<?= $rfqDetail['id'] ?>">
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $rfqDetail['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;gap:0.5rem;">
                        <?php if ($rfqDetail['phone']): ?>
                        <a href="https://api.whatsapp.com/send?phone=<?= preg_replace('/[^0-9]/', '', $rfqDetail['phone']) ?>&text=Hi+<?= urlencode($rfqDetail['contact_name']) ?>!+Your+RFQ+<?= urlencode($rfqDetail['rfq_number']) ?>+has+been+processed." target="_blank" class="btn btn-outline btn-sm" style="color:#25d366;border-color:#25d366;">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <?php endif; ?>
                        <a href="mailto:<?= htmlspecialchars($rfqDetail['email']) ?>?subject=Re: RFQ <?= urlencode($rfqDetail['rfq_number']) ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-envelope"></i>
                        </a>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Admin Notes to Customer</label>
                    <textarea name="admin_notes" class="form-control" rows="2" placeholder="Optional message to partner..."><?= htmlspecialchars($rfqDetail['admin_notes'] ?? '') ?></textarea>
                </div>
            </div>
            <div style="padding:0.6rem 1.25rem;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Update</button>
            </div>
        </form>
    </div>

</div><!-- end detail column -->
<?php endif; ?>

</div><!-- grid -->
</div><!-- page-body -->

<script>
function recalcRow(input) {
    const price = parseFloat(input.value) || 0;
    const qty   = parseInt(input.dataset.qty) || 1;
    const line  = document.getElementById('line-' + input.dataset.row);
    if (line) line.textContent = '₹' + (price * qty).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});

    let total = 0;
    document.querySelectorAll('.price-input').forEach(inp => {
        total += (parseFloat(inp.value) || 0) * (parseInt(inp.dataset.qty) || 1);
    });
    const tot = document.getElementById('rfqTotal');
    if (tot) tot.textContent = '₹' + total.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
