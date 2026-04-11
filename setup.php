<?php
/**
 * TORVO SPAIR - One-Click Database Installer
 * Visit: http://localhost/torvo_spair/setup.php
 * Delete this file after setup is complete.
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');

$msgs = [];
$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create DB
        $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS torvo_spair CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE torvo_spair");
        $msgs[] = ['ok', 'Database <strong>torvo_spair</strong> created / verified.'];

        $sql = file_get_contents(__DIR__ . '/database.sql');
        // Remove the CREATE DATABASE and USE statements (already done)
        $sql = preg_replace('/^\s*CREATE DATABASE.*?;/im', '', $sql);
        $sql = preg_replace('/^\s*USE.*?;/im', '', $sql);

        $pdo->exec("USE torvo_spair");
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    // Ignore duplicate key errors during sample data
                    if (strpos($e->getMessage(), '1062') === false && strpos($e->getMessage(), '1050') === false) {
                        $msgs[] = ['warn', 'Skipped: ' . htmlspecialchars(substr($stmt, 0, 80)) . '... — ' . $e->getMessage()];
                    }
                }
            }
        }
        $msgs[] = ['ok', 'All tables and sample data imported successfully!'];

        // Create upload dir
        $uploadDir = __DIR__ . '/assets/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            $msgs[] = ['ok', 'Upload directory created at <code>assets/uploads/</code>'];
        } else {
            $msgs[] = ['ok', 'Upload directory already exists.'];
        }

        $msgs[] = ['ok', '<strong>Setup complete!</strong> You can now <a href="index.php" style="color:#6c63ff;font-weight:700;">Go to Login</a>'];
        $msgs[] = ['warn', 'Please delete <code>setup.php</code> after setup for security.'];

    } catch (PDOException $e) {
        $error = true;
        $msgs[] = ['err', 'Database error: ' . htmlspecialchars($e->getMessage())];
    }
}

// Check if already installed
$alreadyInstalled = false;
try {
    $testPdo = new PDO("mysql:host=" . DB_HOST . ";dbname=torvo_spair;charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $testPdo->query("SELECT 1 FROM users LIMIT 1");
    $alreadyInstalled = true;
} catch (Exception $e) {
    $alreadyInstalled = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Setup – TORVO SPAIR</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#0f0c29,#24243e);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;}
.card{background:rgba(255,255,255,0.06);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.12);border-radius:20px;padding:2.5rem;width:100%;max-width:560px;box-shadow:0 25px 60px rgba(0,0,0,0.4);}
.logo{text-align:center;margin-bottom:2rem;}
.logo-icon{width:70px;height:70px;background:linear-gradient(135deg,#6c63ff,#48daf5);border-radius:18px;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:1.8rem;color:#fff;box-shadow:0 8px 25px rgba(108,99,255,0.4);}
h1{font-size:1.6rem;font-weight:800;color:#fff;letter-spacing:2px;}
.sub{font-size:0.78rem;color:rgba(255,255,255,0.4);letter-spacing:1px;margin-top:0.3rem;}
.info-box{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:1.25rem;margin-bottom:1.5rem;}
.info-box h3{color:rgba(255,255,255,0.8);font-size:0.85rem;margin-bottom:0.75rem;font-weight:700;}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:0.4rem 0;border-bottom:1px solid rgba(255,255,255,0.06);font-size:0.82rem;}
.info-row:last-child{border-bottom:none;}
.info-row .label{color:rgba(255,255,255,0.5);}
.info-row .val{color:rgba(255,255,255,0.85);font-weight:600;}
.btn{width:100%;padding:0.95rem;background:linear-gradient(135deg,#6c63ff,#48daf5);border:none;border-radius:10px;color:#fff;font-size:1rem;font-weight:700;font-family:'Inter',sans-serif;cursor:pointer;transition:all 0.3s;letter-spacing:0.5px;}
.btn:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(108,99,255,0.4);}
.msg{padding:0.7rem 1rem;border-radius:8px;font-size:0.82rem;margin-bottom:0.5rem;display:flex;align-items:center;gap:0.5rem;}
.msg.ok{background:rgba(34,197,94,0.15);border:1px solid rgba(34,197,94,0.3);color:#4ade80;}
.msg.warn{background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.3);color:#fbbf24;}
.msg.err{background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;}
.already{background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.25);border-radius:10px;padding:1rem;color:#4ade80;text-align:center;font-size:0.875rem;margin-bottom:1.5rem;}
a.btn-link{display:block;width:100%;padding:0.95rem;text-align:center;background:linear-gradient(135deg,#6c63ff,#48daf5);border-radius:10px;color:#fff;font-size:1rem;font-weight:700;text-decoration:none;margin-top:1rem;}
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-icon"><i class="fas fa-wrench"></i></div>
        <h1>TORVO SPAIR</h1>
        <div class="sub">DATABASE SETUP WIZARD</div>
    </div>

    <?php if ($alreadyInstalled && empty($msgs)): ?>
    <div class="already">
        <i class="fas fa-check-circle"></i> Database already installed and tables found!
    </div>
    <a href="index.php" class="btn-link"><i class="fas fa-sign-in-alt"></i> Go to Login</a>
    <?php else: ?>

    <div class="info-box">
        <h3><i class="fas fa-database"></i> Database Configuration</h3>
        <div class="info-row"><span class="label">Host</span><span class="val">localhost</span></div>
        <div class="info-row"><span class="label">User</span><span class="val">root</span></div>
        <div class="info-row"><span class="label">Password</span><span class="val">(empty)</span></div>
        <div class="info-row"><span class="label">Database</span><span class="val">torvo_spair</span></div>
    </div>

    <?php if (!empty($msgs)): ?>
    <div style="margin-bottom:1.25rem;">
        <?php foreach ($msgs as $m): ?>
        <div class="msg <?= $m[0] ?>">
            <i class="fas fa-<?= $m[0]==='ok'?'check-circle':($m[0]==='warn'?'exclamation-triangle':'times-circle') ?>"></i>
            <?= $m[1] ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if (!$error): ?>
    <a href="index.php" class="btn-link"><i class="fas fa-sign-in-alt"></i> Go to Login</a>
    <?php endif; ?>
    <?php else: ?>

    <form method="POST">
        <button type="submit" class="btn">
            <i class="fas fa-play"></i> Run Database Setup
        </button>
    </form>
    <p style="color:rgba(255,255,255,0.35);font-size:0.75rem;text-align:center;margin-top:1rem;">
        This will create the database, all tables, and sample data.<br>Safe to run multiple times.
    </p>
    <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
