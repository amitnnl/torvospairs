<?php
/**
 * TORVO SPAIR — Full Database Seeder
 * Visit: http://localhost/torvo_spair/seed_data.php
 * Run this once to populate all tables with sample data.
 */

$steps = [];

try {
    $pdo = new PDO("mysql:host=localhost;dbname=torvo_spair;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $steps[] = ['ok', 'Connected to <strong>torvo_spair</strong>'];

    // ── Disable FK checks during table creation ────────────────────────────────
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $steps[] = ['ok', 'Foreign key checks disabled for setup'];

    // ── Drop existing tables (clears corrupted InnoDB engine files) ────────────
    $pdo->exec("DROP TABLE IF EXISTS `stock_logs`");
    $pdo->exec("DROP TABLE IF EXISTS `product_compatibility`");
    $pdo->exec("DROP TABLE IF EXISTS `products`");
    $pdo->exec("DROP TABLE IF EXISTS `tools`");
    $pdo->exec("DROP TABLE IF EXISTS `categories`");
    $steps[] = ['ok', 'Old tables dropped — rebuilding fresh'];

    // ── Create all tables (no FK constraints — PHP enforces integrity) ─────────

    $pdo->exec("CREATE TABLE IF NOT EXISTS `categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `status` ENUM('active','inactive') DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `tools` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `model` VARCHAR(100),
        `brand` VARCHAR(100),
        `description` TEXT,
        `image` VARCHAR(255),
        `status` ENUM('active','inactive') DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `sku` VARCHAR(100),
        `category_id` INT NOT NULL DEFAULT 0,
        `brand` VARCHAR(100),
        `price` DECIMAL(10,2) DEFAULT 0.00,
        `quantity` INT DEFAULT 0,
        `min_stock` INT DEFAULT 5,
        `description` TEXT,
        `image` VARCHAR(255),
        `barcode` VARCHAR(100),
        `status` ENUM('active','inactive') DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add SKU unique index if not already there
    try {
        $pdo->exec("ALTER TABLE `products` ADD UNIQUE KEY `sku_unique` (`sku`)");
    } catch (PDOException $e) { /* already exists */ }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_compatibility` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `product_id` INT NOT NULL DEFAULT 0,
        `tool_id` INT NOT NULL DEFAULT 0,
        UNIQUE KEY `unique_mapping` (`product_id`, `tool_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `stock_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `product_id` INT NOT NULL DEFAULT 0,
        `user_id` INT NOT NULL DEFAULT 0,
        `type` ENUM('in','out') NOT NULL,
        `quantity` INT NOT NULL DEFAULT 0,
        `previous_stock` INT NOT NULL DEFAULT 0,
        `current_stock` INT NOT NULL DEFAULT 0,
        `notes` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Re-enable FK checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    $steps[] = ['ok', 'All <strong>6 tables</strong> verified / created'];

    // ── Seed Categories ────────────────────────────────────────────────────────
    $pdo->exec("INSERT INTO `categories` (`name`,`description`) VALUES
        ('Drill Parts',          'Spare parts and accessories for drill machines'),
        ('Grinder Accessories',  'Discs, wheels and accessories for angle grinders'),
        ('Cutting Tool Parts',   'Blades and parts for cutting tools'),
        ('Safety Accessories',   'Protective gear and safety items'),
        ('Electrical Components','Motors, switches, and electrical parts')");
    $steps[] = ['ok', 'Seeded <strong>5 categories</strong>'];

    // ── Seed Tools ─────────────────────────────────────────────────────────────
    $pdo->exec("INSERT INTO `tools` (`name`,`model`,`brand`,`description`) VALUES
        ('Drill Machine', 'DM-500', 'Bosch',          'Heavy duty rotary drill for masonry and metal'),
        ('Angle Grinder', 'AG-100', 'Makita',         '100mm angle grinder for cutting and grinding'),
        ('Marble Cutter', 'MC-180', 'DeWalt',         '180mm marble and tile cutting machine'),
        ('Jigsaw',        'JS-60',  'Black and Decker','Variable speed jigsaw for wood and metal'),
        ('Impact Driver', 'ID-18V', 'Milwaukee',      '18V cordless impact driver for heavy fastening')");
    $steps[] = ['ok', 'Seeded <strong>5 power tools</strong>'];

    // ── Seed Products ──────────────────────────────────────────────────────────
    // Fetch category IDs by name
    $catRows = $pdo->query("SELECT id, name FROM categories")->fetchAll();
    $catMap = [];
    foreach ($catRows as $r) $catMap[$r['name']] = $r['id'];

    $products = [
        ['Carbon Brush Set 6mm',    'SKU-001', 'Drill Parts',           'Bosch',     120.00, 45, 10, 'Replacement carbon brushes for Bosch drill machines',    '8901234560001'],
        ['Chuck Key 10mm',          'SKU-002', 'Drill Parts',           'Generic',    85.00, 30,  8, 'Chuck key for 10mm drill chuck',                          '8901234560002'],
        ['Grinding Disc 100mm',     'SKU-003', 'Grinder Accessories',   'Makita',     65.00, 80, 15, 'Metal grinding disc 100mm x 6mm x 16mm',                  '8901234560003'],
        ['Cutting Disc 100mm',      'SKU-004', 'Grinder Accessories',   'Makita',     55.00,120, 20, 'Thin metal cutting disc 100mm x 1mm x 16mm',              '8901234560004'],
        ['Diamond Blade 180mm',     'SKU-005', 'Cutting Tool Parts',    'DeWalt',    350.00, 25,  5, 'Premium diamond blade for marble and tile cutting',        '8901234560005'],
        ['Drill Bit Set HSS 13pcs', 'SKU-006', 'Drill Parts',           'Bosch',     450.00, 35,  8, 'High speed steel drill bit set 1-13mm',                   '8901234560006'],
        ['Safety Goggles',          'SKU-007', 'Safety Accessories',    'Generic',    95.00, 60, 15, 'Anti-fog safety goggles for eye protection',               '8901234560007'],
        ['Armature Coil DM-500',    'SKU-008', 'Electrical Components', 'Bosch',     780.00,  8,  5, 'Replacement armature coil for Bosch DM-500 drill',         '8901234560008'],
        ['Jigsaw Blade Set Wood',   'SKU-009', 'Cutting Tool Parts',    'Bosch',     220.00, 40, 10, 'T-shank jigsaw blades for wood cutting - 5pcs',            '8901234560009'],
        ['Impact Driver Bit Set',   'SKU-010', 'Drill Parts',           'Milwaukee', 380.00, 22,  8, '25-piece impact driver bit set S2 steel',                  '8901234560010'],
        ['Flap Disc 100mm',         'SKU-011', 'Grinder Accessories',   'Generic',    75.00,  3, 10, 'Aluminium oxide flap disc for grinding and finishing',      '8901234560011'],
        ['Work Gloves Heavy Duty',  'SKU-012', 'Safety Accessories',    'Generic',   150.00,  7, 10, 'Cut-resistant work gloves for power tool operations',       '8901234560012'],
        ['Angle Grinder Guard',     'SKU-013', 'Safety Accessories',    'Makita',    220.00, 18, 10, 'Replacement wheel guard for 100mm angle grinder',           '8901234560013'],
        ['Switch Assembly DM-500',  'SKU-014', 'Electrical Components', 'Bosch',     390.00, 12,  5, 'On/off trigger switch for Bosch drill DM-500',             '8901234560014'],
        ['Bearing 608-ZZ',          'SKU-015', 'Electrical Components', 'Generic',    45.00, 50, 15, 'Standard 608-ZZ steel ball bearing for power tools',        '8901234560015'],
    ];
    $stmt = $pdo->prepare("INSERT INTO `products` (`name`,`sku`,`category_id`,`brand`,`price`,`quantity`,`min_stock`,`description`,`barcode`) VALUES (?,?,?,?,?,?,?,?,?)");
    foreach ($products as $pr) {
        $catId = $catMap[$pr[2]] ?? array_values($catMap)[0];
        $stmt->execute([$pr[0], $pr[1], $catId, $pr[3], $pr[4], $pr[5], $pr[6], $pr[7], $pr[8]]);
    }
    $steps[] = ['ok', 'Seeded <strong>15 products</strong>'];

    // ── Seed Compatibility ─────────────────────────────────────────────────────
    $skuToId = [];
    foreach ($pdo->query("SELECT id, sku FROM products")->fetchAll() as $r) $skuToId[$r['sku']] = $r['id'];
    $toolNameToId = [];
    foreach ($pdo->query("SELECT id, name FROM tools")->fetchAll() as $r) $toolNameToId[$r['name']] = $r['id'];

    $compatMap = [
        'SKU-001' => ['Drill Machine'],
        'SKU-002' => ['Drill Machine'],
        'SKU-003' => ['Angle Grinder'],
        'SKU-004' => ['Angle Grinder','Marble Cutter'],
        'SKU-005' => ['Marble Cutter'],
        'SKU-006' => ['Drill Machine'],
        'SKU-007' => ['Drill Machine','Angle Grinder','Marble Cutter','Jigsaw','Impact Driver'],
        'SKU-008' => ['Drill Machine'],
        'SKU-009' => ['Jigsaw'],
        'SKU-010' => ['Impact Driver'],
        'SKU-011' => ['Angle Grinder'],
        'SKU-012' => ['Drill Machine','Angle Grinder','Marble Cutter'],
        'SKU-013' => ['Angle Grinder'],
        'SKU-014' => ['Drill Machine'],
        'SKU-015' => ['Drill Machine','Angle Grinder','Jigsaw','Impact Driver'],
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO `product_compatibility` (`product_id`,`tool_id`) VALUES (?,?)");
    $total = 0;
    foreach ($compatMap as $sku => $toolNames) {
        $pid = $skuToId[$sku] ?? null;
        if (!$pid) continue;
        foreach ($toolNames as $tn) {
            $tid = $toolNameToId[$tn] ?? null;
            if ($tid) { $ins->execute([$pid, $tid]); $total++; }
        }
    }
    $steps[] = ['ok', "Seeded <strong>$total compatibility mappings</strong>"];

    // ── Seed Stock Logs ────────────────────────────────────────────────────────
    $adminId = $pdo->query("SELECT id FROM users WHERE email='admin@torvo.com' LIMIT 1")->fetchColumn() ?: 1;
    $staffId = $pdo->query("SELECT id FROM users WHERE email='staff@torvo.com' LIMIT 1")->fetchColumn() ?: 2;

    $logs = [
        ['SKU-001', $adminId, 'in',  50,  0, 50, 'Initial stock entry'],
        ['SKU-001', $staffId, 'out',  5, 50, 45, 'Issued to workshop'],
        ['SKU-003', $adminId, 'in', 100,  0,100, 'Initial stock entry'],
        ['SKU-003', $staffId, 'out', 20,100, 80, 'Monthly workshop supply'],
        ['SKU-007', $adminId, 'in',  60,  0, 60, 'Initial stock entry'],
        ['SKU-011', $adminId, 'in',  20,  0, 20, 'Initial stock entry'],
        ['SKU-011', $staffId, 'out', 17, 20,  3, 'Bulk issue for project'],
        ['SKU-012', $adminId, 'in',  20,  0, 20, 'Initial stock'],
        ['SKU-012', $staffId, 'out', 13, 20,  7, 'Issued to team members'],
        ['SKU-008', $adminId, 'in',  10,  0, 10, 'Initial stock'],
        ['SKU-008', $staffId, 'out',  2, 10,  8, 'Workshop repair use'],
    ];
    $ins = $pdo->prepare("INSERT INTO `stock_logs` (`product_id`,`user_id`,`type`,`quantity`,`previous_stock`,`current_stock`,`notes`) VALUES (?,?,?,?,?,?,?)");
    foreach ($logs as $l) {
        $pid = $skuToId[$l[0]] ?? null;
        if ($pid) $ins->execute([$pid, $l[1], $l[2], $l[3], $l[4], $l[5], $l[6]]);
    }
    $steps[] = ['ok', 'Seeded <strong>stock activity logs</strong>'];

    // ── Summary ────────────────────────────────────────────────────────────────
    foreach ([
        'users' => 'users', 'categories' => 'categories', 'tools' => 'tools',
        'products' => 'products', 'compatibility maps' => 'product_compatibility',
        'stock log entries' => 'stock_logs'
    ] as $label => $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        $steps[] = ['info', "<strong>$count</strong> $label"];
    }

    $allOk = true;


} catch (PDOException $e) {
    $steps[] = ['err', 'Error: ' . htmlspecialchars($e->getMessage())];
    $allOk = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Seed Data – TORVO SPAIR</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#0f0c29,#24243e);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}
.card{background:rgba(255,255,255,0.06);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.12);border-radius:20px;padding:2rem;width:100%;max-width:600px;box-shadow:0 25px 60px rgba(0,0,0,0.4)}
h1{font-size:1.3rem;font-weight:800;color:#fff;margin-bottom:1.5rem}
.step{display:flex;align-items:flex-start;gap:0.75rem;padding:0.6rem 1rem;border-radius:8px;margin-bottom:0.4rem;font-size:0.84rem;line-height:1.5}
.step.ok  {background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.25);color:#4ade80}
.step.err {background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.25);color:#fca5a5}
.step.info{background:rgba(59,130,246,0.12);border:1px solid rgba(59,130,246,0.25);color:#93c5fd}
.step.warn{background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.25);color:#fbbf24}
.actions{display:flex;gap:0.75rem;margin-top:1.5rem;flex-wrap:wrap}
.btn{padding:0.8rem 1.75rem;border-radius:10px;font-size:0.9rem;font-weight:700;text-decoration:none;display:inline-block;transition:all 0.3s}
.btn-p{background:linear-gradient(135deg,#6c63ff,#48daf5);color:#fff}
.btn-p:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(108,99,255,0.3)}
.btn-o{background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.7);border:1px solid rgba(255,255,255,0.15)}
.note{font-size:0.73rem;color:rgba(255,255,255,0.25);margin-top:1rem}
</style>
</head>
<body>
<div class="card">
    <h1>🌱 Database Seeder</h1>
    <?php foreach ($steps as $s):
        $ico = ['ok'=>'✅','err'=>'❌','info'=>'ℹ️','warn'=>'⚠️'][$s[0]];
    ?>
    <div class="step <?= $s[0] ?>">
        <span><?= $ico ?></span><span><?= $s[1] ?></span>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($allOk)): ?>
    <div class="actions">
        <a href="pages/dashboard.php" class="btn btn-p">→ Go to Dashboard</a>
        <a href="seed_data.php" class="btn btn-o">Run Again</a>
    </div>
    <?php endif; ?>
    <p class="note">⚠️ Delete <code>seed_data.php</code> in production.</p>
</div>
</body>
</html>
