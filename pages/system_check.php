<?php
/**
 * TORVO SPAIR — System Readiness Check
 * Use this to verify your Online vs Offline setup.
 */
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'System Readiness Check';
include __DIR__ . '/../includes/header.php';

$checks = [];

// 1. Database Connection
try {
    $db = getDB();
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

// 5. Critical Tables
$requiredTables = ['products', 'categories', 'customers', 'rfqs', 'notifications', 'settings'];
$missing = [];
foreach ($requiredTables as $t) {
    if (!$db->query("SHOW TABLES LIKE '$t'")->fetch()) $missing[] = $t;
}
if (empty($missing)) {
    $checks[] = ['label' => 'Database Schema', 'status' => 'success', 'msg' => 'All critical tables exist.'];
} else {
    $checks[] = ['label' => 'Database Schema', 'status' => 'danger', 'msg' => 'Missing tables: ' . implode(', ', $missing) . '. Please run setup.php or import database.sql.'];
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
                    <div style="font-weight:800;color:var(--primary);margin-bottom:0.5rem;"><i class="fas fa-lightbulb"></i> Pro-Tip for Online Deployment</div>
                    <div style="font-size:0.85rem;color:var(--text-medium);line-height:1.6;">
                        If you are moving from Local to Online:
                        <ol style="margin-top:0.5rem;display:grid;gap:0.4rem;">
                            <li>Create a file named <code>config/db_config.php</code> on your online server.</li>
                            <li>Copy your online cPanel database credentials into that file.</li>
                            <li>This file is ignored by Git, so your local machine can keep its local settings while the server stays connected online.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
