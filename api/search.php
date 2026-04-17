<?php
require_once __DIR__ . '/_core.php';

$db = getDB();
$q  = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    apiResponse(400, null, 'Query must be at least 2 characters.');
}

$like = '%' . $q . '%';

// Determine base URL for links
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_dir = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) ? '/torvo_spair' : '';
$appUrl = $protocol . $host . $base_dir;

// Products
$stmt = $db->prepare("
    SELECT p.id, p.name, p.sku, p.price, p.quantity, p.image, 'product' AS type,
           c.name AS category
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.status='active' AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)
    ORDER BY p.name LIMIT 8
");
$stmt->execute([$like, $like, $like, $like]);
$products = $stmt->fetchAll();

// Tools
$stmt2 = $db->prepare("
    SELECT id, name, brand, '' AS sku, 0 AS price, 0 AS quantity, '' AS image, 'tool' AS type,
           'Power Tool' AS category
    FROM tools
    WHERE status='active' AND (name LIKE ? OR brand LIKE ? OR description LIKE ?)
    LIMIT 4
");
$stmt2->execute([$like, $like, $like]);
$tools = $stmt2->fetchAll();

// Merge and add computed fields for frontend dropdown
$results = [];
foreach ($products as $r) {
    $results[] = [
        'id'       => $r['id'],
        'type'     => 'product',
        'title'    => $r['name'],
        'sub'      => $r['sku'] . ' · ' . $r['category'],
        'badge'    => $r['quantity'] > 0 ? 'In Stock' : 'Out of Stock',
        'url'      => $appUrl . '/pages/product_detail.php?id=' . $r['id'],
        'name'     => $r['name'],
        'sku'      => $r['sku'],
        'price'    => $r['price'],
        'quantity' => $r['quantity'],
        'image'    => $r['image'],
        'category' => $r['category'],
    ];
}
foreach ($tools as $r) {
    $results[] = [
        'id'       => $r['id'],
        'type'     => 'tool',
        'title'    => $r['name'],
        'sub'      => $r['brand'] ?: 'Power Tool',
        'badge'    => 'Tool',
        'url'      => $appUrl . '/pages/products.php?tool=' . $r['id'],
        'name'     => $r['name'],
        'sku'      => '',
        'price'    => 0,
        'quantity' => 0,
        'image'    => '',
        'category' => 'Power Tool',
    ];
}

apiResponse(200, [
    'query'   => $q,
    'count'   => count($results),
    'results' => $results,
]);

