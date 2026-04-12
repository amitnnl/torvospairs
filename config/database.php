<?php
// ============================================
// Database Configuration
// ============================================

// Start session FIRST before any output
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

if(!defined('DB_HOST')) define('DB_HOST', 'localhost');
if(!defined('DB_USER')) define('DB_USER', 'root');
if(!defined('DB_PASS')) define('DB_PASS', '');
if(!defined('DB_NAME')) define('DB_NAME', 'torvo_spair');
if(!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Application Settings
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_dir = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) ? '/torvo_spair' : '';
$baseUrl = $protocol . $host . $base_dir;

if(!defined('APP_NAME')) define('APP_NAME', 'TORVO SPAIR');
if(!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');
if(!defined('APP_URL')) define('APP_URL', $baseUrl);
if(!defined('UPLOAD_DIR')) define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
if(!defined('UPLOAD_URL')) define('UPLOAD_URL', APP_URL . '/assets/uploads/');
if(!defined('MIN_STOCK_ALERT')) define('MIN_STOCK_ALERT', 10); // Global low stock threshold

// PDO Database Connection
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            $msg = htmlspecialchars($e->getMessage());
            http_response_code(503);
            die("<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Database Error – TORVO SPAIR</title>
            <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap' rel='stylesheet'>
            <style>body{font-family:'Inter',sans-serif;background:#0f0c29;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;flex-direction:column;gap:1rem;text-align:center;padding:2rem;}
            .icon{font-size:3rem;margin-bottom:1rem;} h1{font-size:1.4rem;} p{color:rgba(255,255,255,0.5);font-size:0.875rem;max-width:500px;}
            code{background:rgba(255,255,255,0.08);padding:0.5rem 1rem;border-radius:8px;font-size:0.8rem;display:block;margin-top:1rem;color:#f87171;}
            a{display:inline-block;margin-top:1.5rem;padding:0.75rem 2rem;background:linear-gradient(135deg,#6c63ff,#48daf5);border-radius:8px;color:#fff;text-decoration:none;font-weight:700;}</style></head>
            <body><div class='icon'>⚠️</div><h1>Database Not Available</h1>
            <p>Could not connect to MySQL. Please make sure XAMPP is running and MySQL is started.</p>
            <code>$msg</code>
            <a href='/torvo_spair/setup.php'>Go to Setup</a></body></html>");
        }
    }
    return $pdo;
}

// Authentication Helpers
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/admin.php');
        exit;
    }
}

function isAdmin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . APP_URL . '/pages/dashboard.php');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'   => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? 'Guest',
        'role' => $_SESSION['user_role'] ?? 'staff',
    ];
}

// Sanitize input
if (!function_exists('sanitize')) {
    function sanitize(string $input): string {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
}

// Flash messages
function setFlash(string $type, string $message): void {
    $_SESSION['flash'][$type] = $message;
}

function getFlash(string $type): ?string {
    if (isset($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

// Format currency
if (!function_exists('formatCurrency')) {
    function formatCurrency(float $amount): string {
        return '₹' . number_format($amount, 2);
    }
}

// Fetch Site Setting
if (!function_exists('getSetting')) {
    function getSetting(string $key, string $default = ''): string {
        $db = getDB();
        $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    }
}

// Format date

function formatDate(string $date): string {
    return date('d M Y, h:i A', strtotime($date));
}

// Generate SKU
function generateSKU(): string {
    return 'SKU-' . strtoupper(substr(uniqid(), -6));
}

// Upload image helper
function uploadImage(array $file, string $prefix = 'img'): ?string {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) return null;
    
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    
    if (!in_array($mime, $allowed)) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null; // 5MB limit
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    
    if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
        return $filename;
    }
    return null;
}
