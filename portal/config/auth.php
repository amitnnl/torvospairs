<?php
/**
 * B2B Portal — Customer Auth & DB Config
 */

// Start session early
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

define('PORTAL_BASE', dirname(__DIR__));

// Re-use main DB config constants
if(!defined('DB_HOST')) define('DB_HOST',    'localhost');
if(!defined('DB_USER')) define('DB_USER',    'root');
if(!defined('DB_PASS')) define('DB_PASS',    '');
if(!defined('DB_NAME')) define('DB_NAME',    'torvo_spair');
if(!defined('APP_NAME')) define('APP_NAME',   'TORVO SPAIR');

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_dir = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) ? '/torvo_spair' : '';
$baseUrl = $protocol . $host . $base_dir;

if(!defined('APP_URL')) define('APP_URL',    $baseUrl);
if(!defined('PORTAL_URL')) define('PORTAL_URL', APP_URL . '/portal');
if(!defined('UPLOAD_URL')) define('UPLOAD_URL', APP_URL . '/assets/uploads/');
if(!defined('UPLOAD_DIR')) define('UPLOAD_DIR', PORTAL_BASE . '/assets/uploads/');
if(!defined('MIN_STOCK_ALERT')) define('MIN_STOCK_ALERT', 10);

// PDO singleton
function portalDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            die("<strong>Portal Database Connection Failed:</strong><br>Please ensure you have configured your live database credentials correctly in <code>portal/config/auth.php</code>.<br><br><i>Error: " . htmlspecialchars($e->getMessage()) . "</i>");
        }
    }
    return $pdo;
}

// ── Customer Auth Helpers ──────────────────────────────────────────────────────

function customerLoggedIn(): bool {
    return !empty($_SESSION['customer_id']);
}

function requireCustomerLogin(): void {
    if (!customerLoggedIn()) {
        header('Location: ' . PORTAL_URL . '/index.php?redirect=1');
        exit;
    }
}

function currentCustomer(): array {
    // Support both old individual keys and new 'portal_customer' array
    if (!empty($_SESSION['portal_customer'])) {
        return $_SESSION['portal_customer'];
    }
    return [
        'id'      => $_SESSION['customer_id']      ?? null,
        'name'    => $_SESSION['customer_name']    ?? 'Guest',
        'company' => $_SESSION['customer_company'] ?? '',
        'email'   => $_SESSION['customer_email']   ?? '',
        'tier'    => $_SESSION['customer_tier']    ?? 'standard',
    ];
}

// ── RFQ (Cart) Helpers ─────────────────────────────────────────────────────────

function getRFQCart(): array {
    return $_SESSION['rfq_cart'] ?? [];
}

function addToRFQ(int $productId, int $qty = 1, string $notes = ''): void {
    if (!isset($_SESSION['rfq_cart'])) $_SESSION['rfq_cart'] = [];
    if (isset($_SESSION['rfq_cart'][$productId])) {
        $_SESSION['rfq_cart'][$productId]['qty'] += $qty;
    } else {
        $_SESSION['rfq_cart'][$productId] = ['product_id' => $productId, 'qty' => $qty, 'notes' => $notes];
    }
}

function removeFromRFQ(int $productId): void {
    unset($_SESSION['rfq_cart'][$productId]);
}

function clearRFQ(): void {
    unset($_SESSION['rfq_cart']);
}

function rfqCount(): int {
    return count($_SESSION['rfq_cart'] ?? []);
}

// ── Flash Messages ─────────────────────────────────────────────────────────────

// Alias: setPortalFlash (used in newer portal pages)
function setPortalFlash(string $type, string $msg): void {
    $_SESSION['portal_flash'][$type] = $msg;
}

function getPortalFlash(string $type): ?string {
    if (!empty($_SESSION['portal_flash'][$type])) {
        $m = $_SESSION['portal_flash'][$type];
        unset($_SESSION['portal_flash'][$type]);
        return $m;
    }
    return null;
}

// ── Utilities ──────────────────────────────────────────────────────────────────

if (!function_exists('getSetting')) {
    function getSetting(string $key, string $default = ''): string {
        try {
            $db = portalDB();
            $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return $val !== false ? $val : $default;
        } catch (PDOException $e) {
            return $default; // Graceful fallback if table is missing
        }
    }
}

if (!function_exists('sanitize')) {
    function sanitize(string $s): string {
        return htmlspecialchars(strip_tags(trim($s)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency(float $v): string {
        return '₹' . number_format($v, 2);
    }
}

