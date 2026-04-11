<?php
require_once __DIR__ . '/_core.php';

$db = getDB();
$q  = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    apiResponse(400, null, 'Query must be at least 2 characters.');
}

$like = '%' . $q . '%';

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

$results = array_merge($products, $tools);

apiResponse(200, [
    'query'   => $q,
    'count'   => count($results),
    'results' => $results,
]);
