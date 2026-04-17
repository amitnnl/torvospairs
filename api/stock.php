<?php
require_once __DIR__ . '/_core.php';

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET Stock Check by SKU ───────────────────────────────────────────────────
if ($method === 'GET') {
    $sku = trim($_GET['sku'] ?? '');
    $pid = (int)($_GET['product_id'] ?? 0);

    if (!$sku && !$pid) {
        apiResponse(400, null, 'Provide ?sku=XXX or ?product_id=N to check stock.');
    }

    if ($sku) {
        $stmt = $db->prepare("SELECT id, name, sku, quantity, min_stock, price, status FROM products WHERE sku = ? AND status='active'");
        $stmt->execute([$sku]);
    } else {
        $stmt = $db->prepare("SELECT id, name, sku, quantity, min_stock, price, status FROM products WHERE id = ? AND status='active'");
        $stmt->execute([$pid]);
    }

    $p = $stmt->fetch();
    if (!$p) apiResponse(404, null, 'Product not found.');

    $p['in_stock']  = $p['quantity'] > 0;
    $p['low_stock'] = $p['quantity'] > 0 && $p['quantity'] <= $p['min_stock'];
    $p['out_of_stock'] = $p['quantity'] == 0;

    apiResponse(200, $p);
}

// ── POST Stock Update (requires auth) ────────────────────────────────────────
if ($method === 'POST') {
    requireApiAuth();

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $sku  = trim($body['sku'] ?? $_POST['sku'] ?? '');
    $type = strtolower(trim($body['type'] ?? $_POST['type'] ?? ''));
    $qty  = (int)($body['quantity'] ?? $_POST['quantity'] ?? 0);
    $notes = trim($body['notes'] ?? $_POST['notes'] ?? '');

    if (!$sku) apiResponse(400, null, 'SKU is required.');
    if (!in_array($type, ['in', 'out'])) apiResponse(400, null, "Type must be 'in' or 'out'.");
    if ($qty <= 0) apiResponse(400, null, 'Quantity must be a positive integer.');

    $stmt = $db->prepare("SELECT id, quantity FROM products WHERE sku = ? AND status='active'");
    $stmt->execute([$sku]);
    $p = $stmt->fetch();
    if (!$p) apiResponse(404, null, 'Product not found for SKU: ' . $sku);

    $prevStock = (int)$p['quantity'];
    $newStock  = $type === 'in' ? $prevStock + $qty : max(0, $prevStock - $qty);

    if ($type === 'out' && $qty > $prevStock) {
        apiResponse(400, null, "Insufficient stock. Current: $prevStock, Requested out: $qty");
    }

    // Update product quantity
    $db->prepare("UPDATE products SET quantity = ?, updated_at = NOW() WHERE id = ?")->execute([$newStock, $p['id']]);

    // Log the transaction (user_id = 0 for API operations)
    $db->prepare("INSERT INTO stock_logs (product_id, user_id, type, quantity, previous_stock, current_stock, notes) VALUES (?,?,?,?,?,?,?)")
       ->execute([$p['id'], 0, $type, $qty, $prevStock, $newStock, $notes ?: 'API stock update']);

    apiResponse(200, [
        'product_id'     => $p['id'],
        'sku'            => $sku,
        'type'           => $type,
        'quantity_changed' => $qty,
        'previous_stock' => $prevStock,
        'current_stock'  => $newStock,
    ], "Stock $type recorded successfully.");
}

apiResponse(405, null, 'Method not allowed.');
