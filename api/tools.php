<?php
require_once __DIR__ . '/_core.php';

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = (int)($_GET['id'] ?? 0);

// ── GET Single Tool ──────────────────────────────────────────────────────────
if ($method === 'GET' && $id) {
    $stmt = $db->prepare("SELECT * FROM tools WHERE id=? AND status='active'");
    $stmt->execute([$id]);
    $tool = $stmt->fetch();
    if (!$tool) apiResponse(404, null, 'Tool not found.');

    // Compatible products
    $comp = $db->prepare("
        SELECT p.id, p.name, p.sku, p.brand, p.quantity
        FROM product_compatibility pc
        JOIN products p ON pc.product_id = p.id
        WHERE pc.tool_id = ? AND p.status='active'
        ORDER BY p.name
    ");
    $comp->execute([$id]);
    $tool['compatible_products'] = $comp->fetchAll();

    apiResponse(200, $tool);
}

// ── GET List Tools ───────────────────────────────────────────────────────────
if ($method === 'GET') {
    $search = $_GET['search'] ?? '';
    $brand  = $_GET['brand'] ?? '';

    $where  = ["status='active'"];
    $params = [];

    if ($search) {
        $where[] = "(name LIKE ? OR brand LIKE ? OR model LIKE ?)";
        $like = "%$search%";
        $params = array_merge($params, [$like, $like, $like]);
    }
    if ($brand) {
        $where[] = "brand = ?";
        $params[] = $brand;
    }

    $sql  = "SELECT id, name, model, brand, description, image FROM tools WHERE " . implode(' AND ', $where) . " ORDER BY name";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tools = $stmt->fetchAll();

    apiResponse(200, [
        'count' => count($tools),
        'tools' => $tools,
    ]);
}

apiResponse(405, null, 'Method not allowed.');
