<?php
/**
 * TORVO SPAIR — System Readiness Check
 * Use this to verify your Online vs Offline setup.
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();

// ═══ HANDLE DATABASE REPAIR (Must be before output) ═══════════════
if (isset($_POST['repair_db'])) {
    // Migration Logic
    $db->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `setting_key` varchar(100) NOT NULL UNIQUE,
        `setting_value` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `categories` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `name` varchar(100) NOT NULL,
        `description` text DEFAULT NULL,
        `image` varchar(255) DEFAULT NULL,
        `status` enum('active','inactive') DEFAULT 'active',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `products` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `category_id` int(11) NOT NULL,
        `name` varchar(200) NOT NULL,
        `sku` varchar(50) NOT NULL UNIQUE,
        `brand` varchar(100) DEFAULT NULL,
        `description` text DEFAULT NULL,
        `image` varchar(255) DEFAULT NULL,
        `price` decimal(10,2) NOT NULL DEFAULT 0.00,
        `quantity` int(11) NOT NULL DEFAULT 0,
        `min_stock` int(11) NOT NULL DEFAULT 5,
        `status` enum('active','inactive') DEFAULT 'active',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `customers` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `company_name` varchar(150) NOT NULL,
        `contact_name` varchar(100) NOT NULL,
        `email` varchar(150) NOT NULL UNIQUE,
        `password` varchar(255) NOT NULL,
        `phone` varchar(20) DEFAULT NULL,
        `gstin` varchar(20) DEFAULT NULL,
        `address` text DEFAULT NULL,
        `city` varchar(80) DEFAULT NULL,
        `state` varchar(80) DEFAULT NULL,
        `pin` varchar(10) DEFAULT NULL,
        `tier` enum('standard','silver','gold') DEFAULT 'standard',
        `status` enum('pending','active','suspended') DEFAULT 'pending',
        `notes` text DEFAULT NULL,
        `rejection_reason` varchar(500) DEFAULT NULL,
        `approved_at` datetime DEFAULT NULL,
        `approved_by` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `rfqs` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `customer_id` int(11) NOT NULL,
        `rfq_number` varchar(30) UNIQUE,
        `status` enum('submitted','reviewing','quoted','accepted','rejected','invoiced','closed') DEFAULT 'submitted',
        `customer_notes` text DEFAULT NULL,
        `admin_notes` text DEFAULT NULL,
        `accepted_at` datetime DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `rfq_items` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `rfq_id` int(11) NOT NULL,
        `product_id` int(11) NOT NULL,
        `quantity` int(11) NOT NULL DEFAULT 1,
        `unit_price` decimal(10,2) DEFAULT NULL,
        `notes` text DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `notifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `customer_id` int(11) NOT NULL,
        `type` enum('rfq_quoted','rfq_invoiced','rfq_rejected','general') DEFAULT 'general',
        `title` varchar(200) NOT NULL,
        `message` text NOT NULL,
        `rfq_id` int(11) DEFAULT NULL,
        `is_read` tinyint(1) DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `stock_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `product_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `type` enum('in','out') NOT NULL,
        `quantity` int(11) NOT NULL,
        `notes` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $db->exec("CREATE TABLE IF NOT EXISTS `invoices` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `rfq_id` int(11) NOT NULL,
        `invoice_number` varchar(50) UNIQUE,
        `discount_amount` decimal(10,2) DEFAULT 0.00,
        `tax_amount` decimal(10,2) DEFAULT 0.00,
        `total_amount` decimal(10,2) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp()
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Add missing columns to customers if they don't exist
    try {
        $db->exec("ALTER TABLE customers ADD COLUMN IF NOT EXISTS status ENUM('pending','active','suspended') DEFAULT 'pending'");
        $db->exec("ALTER TABLE customers ADD COLUMN IF NOT EXISTS tier ENUM('standard','silver','gold') DEFAULT 'standard'");
        $db->exec("ALTER TABLE customers ADD COLUMN IF NOT EXISTS approved_at DATETIME DEFAULT NULL");
        $db->exec("ALTER TABLE customers ADD COLUMN IF NOT EXISTS approved_by INT DEFAULT NULL");
    } catch (Exception $e) { /* Some SQL versions don't support ADD COLUMN IF NOT EXISTS, ignore if already exists */ }

    setFlash('success', 'Database schema repaired and columns synced successfully!');
    header("Location: system_check.php"); exit;
}

$pageTitle = 'System Readiness Check';
include __DIR__ . '/../includes/header.php';

$checks = [];

// 1. Database Connection
try {
    $db->query("SELECT 1");
    $checks[] = ['label' => 'Database Connection', 'status' => 'success', 'msg' => 'Connected to ' . DB_NAME . ' @ ' . DB_HOST];
} catch (Exception $e) {
    $checks[] = ['label' => 'Database Connection', 'status' => 'danger', 'msg' => 'Failed: ' . $e->getMessage()];
}

// 2. Upload Directory Permissions
$uploadDir = UPLOAD_DIR;
if (is_dir($uploadDir)) {
    if (is_writable($uploadDir)) {
        $checks[] = ['label' => 'Uploads Directory', 'status' => 'success', 'msg' => 'Writable: ' . realpath($uploadDir)];
    } else {
        $checks[] = ['label' => 'Uploads Directory', 'status' => 'danger', 'msg' => 'NOT Writable. Please set permissions to 755 or 777 on your server.'];
    }
} else {
    // Try to create it
    if (@mkdir($uploadDir, 0755, true)) {
        $checks[] = ['label' => 'Uploads Directory', 'status' => 'success', 'msg' => 'Created successfully.'];
    } else {
        $checks[] = ['label' => 'Uploads Directory', 'status' => 'danger', 'msg' => 'Missing and could not be created at ' . $uploadDir];
    }
}

// 3. PHP Version
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    $checks[] = ['label' => 'PHP Version', 'status' => 'success', 'msg' => 'v' . PHP_VERSION . ' (Requirement: 8.0+)'];
} else {
    $checks[] = ['label' => 'PHP Version', 'status' => 'warning', 'msg' => 'v' . PHP_VERSION . '. Some features like "match" expressions require PHP 8.0+.'];
}

// 4. URL Configuration
$checks[] = ['label' => 'Base URL Detect', 'status' => 'info', 'msg' => 'Detected: ' . APP_URL];

// 5. Critical Tables Checks
$requiredTables = ['products', 'categories', 'customers', 'rfqs', 'rfq_items', 'notifications', 'settings', 'invoices', 'stock_logs'];
$missing = [];
foreach ($requiredTables as $t) {
    if (!$db->query("SHOW TABLES LIKE '$t'")->fetch()) $missing[] = $t;
}

// Check for missing columns in customers
$missingCols = [];
if (!in_array('customers', $missing)) {
    $cols = $db->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('status', $cols)) $missingCols[] = 'status';
    if (!in_array('tier', $cols)) $missingCols[] = 'tier';
}

if (empty($missing) && empty($missingCols)) {
    $checks[] = ['label' => 'Database Schema', 'status' => 'success', 'msg' => 'All critical tables and columns exist.'];
} else {
    $msg = '';
    if (!empty($missing)) $msg .= 'Missing tables: ' . implode(', ', $missing) . '. ';
    if (!empty($missingCols)) $msg .= 'Missing columns in customers: ' . implode(', ', $missingCols) . '.';
    $checks[] = ['label' => 'Database Schema', 'status' => 'danger', 'msg' => $msg ?: 'Schema error.'];
}

?>

<div class="page-body">
    <div style="max-width:800px;margin:0 auto;">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-microscope"></i> Environment Readiness Check</div>
                <div style="font-size:0.75rem;color:var(--text-muted);"><?= (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) ? '🏠 Running Locally' : '🌐 Running Online' ?></div>
            </div>
            <div class="card-body">
                <div style="display:grid;gap:1rem;">
                    <?php foreach ($checks as $c): ?>
                    <div style="display:flex;align-items:center;gap:1.25rem;padding:1rem;background:var(--bg-gray);border-radius:12px;border:1px solid var(--border-color);">
                        <div style="width:40px;height:40px;border-radius:50%;background:<?= $c['status']==='success'?'var(--success)':'var(--'.$c['status'].')' ?>22;display:flex;align-items:center;justify-content:center;color:var(--<?= $c['status']==='success'?'success':$c['status'] ?>);flex-shrink:0;">
                            <i class="fas <?= $c['status']==='success'?'fa-check-circle':($c['status']==='danger'?'fa-times-circle':'fa-info-circle') ?>"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="font-weight:700;color:var(--text-primary);font-size:0.95rem;"><?= $c['label'] ?></div>
                            <div style="font-size:0.8rem;color:var(--text-muted);"><?= $c['msg'] ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top:2rem;background:rgba(37,99,235,0.05);padding:1.5rem;border-radius:12px;border:1px dashed var(--primary);">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
                        <div style="flex:1;min-width:250px;">
                            <div style="font-weight:800;color:var(--primary);margin-bottom:0.4rem;"><i class="fas fa-magic"></i> Database Repair</div>
                            <div style="font-size:0.85rem;color:var(--text-medium);">Click the button to automatically create any missing tables. This will not delete existing data.</div>
                        </div>
                        <form method="POST">
                            <button type="submit" name="repair_db" class="btn btn-primary">
                                <i class="fas fa-tools"></i> Repair Database Schema
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
