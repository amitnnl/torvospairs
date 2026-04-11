<?php
/**
 * TORVO SPAIR REST API
 * Base URL: /torvo_spair/api/
 *
 * Endpoints:
 *   GET  /api/products.php          → List / filter products
 *   GET  /api/products.php?id=1     → Single product with compatibility
 *   GET  /api/search.php?q=drill    → Quick search (products + tools)
 *   GET  /api/tools.php             → List all tools
 *   GET  /api/categories.php        → List all categories
 *   GET  /api/stock.php?sku=XXX     → Stock check by SKU
 *
 * Authentication:
 *   Pass header:  X-API-Key: <your-key>
 *   Or query param: ?api_key=<your-key>
 *   Public endpoints (search, product list) work without auth.
 *   Stock check & write operations require a valid API key.
 *
 * Demo key: torvo_api_2024x (store in DB for production)
 */

define('TORVO_API', true);
define('API_VERSION', '1.0.0');
define('DEMO_API_KEY', 'torvo_api_2024x');

require_once __DIR__ . '/../config/database.php';

function apiResponse(int $code, mixed $data, string $message = ''): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    echo json_encode([
        'status'  => $code < 400 ? 'success' : 'error',
        'code'    => $code,
        'message' => $message,
        'data'    => $data,
        'version' => API_VERSION,
        'ts'      => date('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function apiAuth(): bool {
    $key = $_SERVER['HTTP_X_API_KEY']
        ?? $_SERVER['HTTP_X_Api_Key']
        ?? ($_GET['api_key'] ?? '');
    return $key === DEMO_API_KEY;
}

function requireApiAuth(): void {
    if (!apiAuth()) {
        apiResponse(401, null, 'Unauthorized: Invalid or missing API key. Pass X-API-Key header.');
    }
}

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    exit;
}
