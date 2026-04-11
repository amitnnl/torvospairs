<?php
require_once __DIR__ . '/_core.php';

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = (int)($_GET['id'] ?? 0);

// ── GET Single Product ────────────────────────────────────────────────────────
if ($method === 'GET' && $id) {
    $stmt = $db->prepare("
        SELECT p.*, c.name AS category_name
        FROM products p JOIN categories c ON p.category_id=c.id
        WHERE p.id=? AND p.status='active'
    ");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) apiResponse(404, null, 'Product not found.');

    // Compatible tools
    $comp = $db->prepare("SELECT t.id, t.name, t.brand FROM product_compatibility pc JOIN tools t ON pc.tool_id=t.id WHERE pc.product_id=?");
    $comp->execute([$id]);
    $product['compatible_tools'] = $comp->fetchAll();

    // Stock logs (last 5)
    $logs = $db->prepare("SELECT action, quantity, note, created_at FROM stock_logs WHERE product_id=? ORDER BY created_at DESC LIMIT 5");
    $logs->execute([$id]);
    $product['stock_history'] = $logs->fetchAll();

    apiResponse(200, $product);
}

// ── GET List Products ─────────────────────────────────────────────────────────
if ($method === 'GET') {
    $page     = max(1, (int)($_GET['page']     ?? 1));
    $limit    = min(100, max(1, (int)($_GET['limit']  ?? 20)));
    $offset   = ($page - 1) * $limit;
    $catId    = (int)($_GET['category'] ?? 0);
    $toolId   = (int)($_GET['tool']     ?? 0);
    $brand    = $_GET['brand']  ?? '';
    $search   = $_GET['search'] ?? '';
    $inStock  = isset($_GET['in_stock']) ? (bool)$_GET['in_stock'] : null;

    $where  = ["p.status='active'"];
    $params = [];

    if ($catId)  { $where[] = "p.category_id=?";   $params[] = $catId; }
    if ($brand)  { $where[] = "p.brand=?";          $params[] = $brand; }
    if ($search) { $where[] = "(p.name LIKE ? OR p.sku LIKE ?)"; $like="%$search%"; $params=array_merge($params,[$like,$like]); }
    if ($inStock !== null) { $where[] = $inStock ? "p.quantity>0" : "p.quantity=0"; }
    if ($toolId) {
        $where[]  = "EXISTS (SELECT 1 FROM product_compatibility pc WHERE pc.product_id=p.id AND pc.tool_id=?)";
        $params[] = $toolId;
    }

    $sql = "SELECT p.id,p.name,p.sku,p.brand,p.price,p.quantity,p.min_stock,p.image,c.name AS category
            FROM products p JOIN categories c ON p.category_id=c.id
            WHERE " . implode(' AND ', $where);

    // Count
    $cntStmt = $db->prepare("SELECT COUNT(*) FROM products p WHERE " . implode(' AND ', $where));
    $cntStmt->execute($params);
    $total = $cntStmt->fetchColumn();

    $sql .= " ORDER BY p.name LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    apiResponse(200, [
        'page'       => $page,
        'limit'      => $limit,
        'total'      => (int)$total,
        'pages'      => ceil($total / $limit),
        'products'   => $products,
    ]);
}

// ── POST Stock Check by SKU (requires auth) ───────────────────────────────────
if ($method === 'POST') {
    requireApiAuth();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $sku  = trim($body['sku'] ?? $_POST['sku'] ?? '');
    if (!$sku) apiResponse(400, null, 'SKU is required.');
    $stmt = $db->prepare("SELECT id,name,sku,quantity,min_stock FROM products WHERE sku=? AND status='active'");
    $stmt->execute([$sku]);
    $p = $stmt->fetch();
    if (!$p) apiResponse(404, null, 'Product not found for SKU: ' . $sku);
    $p['in_stock'] = $p['quantity'] > 0;
    $p['low_stock'] = $p['quantity'] > 0 && $p['quantity'] <= $p['min_stock'];
    apiResponse(200, $p);
}

apiResponse(405, null, 'Method not allowed.');
