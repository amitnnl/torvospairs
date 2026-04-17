<?php
require_once __DIR__ . '/_core.php';

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = (int)($_GET['id'] ?? 0);

// ── GET Single Category ──────────────────────────────────────────────────────
if ($method === 'GET' && $id) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id=? AND status='active'");
    $stmt->execute([$id]);
    $cat = $stmt->fetch();
    if (!$cat) apiResponse(404, null, 'Category not found.');

    // Products in this category
    $prods = $db->prepare("
        SELECT id, name, sku, brand, price, quantity, min_stock, image
        FROM products
        WHERE category_id = ? AND status='active'
        ORDER BY name
    ");
    $prods->execute([$id]);
    $cat['products'] = $prods->fetchAll();
    $cat['product_count'] = count($cat['products']);

    apiResponse(200, $cat);
}

// ── GET List Categories ──────────────────────────────────────────────────────
if ($method === 'GET') {
    $withCounts = isset($_GET['with_counts']);

    if ($withCounts) {
        $cats = $db->query("
            SELECT c.id, c.name, c.description, c.status,
                   COUNT(p.id) AS product_count
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.id AND p.status='active'
            WHERE c.status='active'
            GROUP BY c.id, c.name, c.description, c.status
            ORDER BY c.name
        ")->fetchAll();
    } else {
        $cats = $db->query("SELECT id, name, description FROM categories WHERE status='active' ORDER BY name")->fetchAll();
    }

    apiResponse(200, [
        'count'      => count($cats),
        'categories' => $cats,
    ]);
}

apiResponse(405, null, 'Method not allowed.');
