<?php
/**
 * TORVO SPAIR - Insert Default Users
 * Visit: http://localhost/torvo_spair/create_users.php
 * DELETE after use!
 */

$steps = [];

try {
    $pdo = new PDO("mysql:host=localhost;dbname=torvo_spair;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $steps[] = ['ok', 'Connected to database <strong>torvo_spair</strong>'];

    // Ensure users table exists with correct types
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(150) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('admin', 'staff') DEFAULT 'staff',
        `status` ENUM('active', 'inactive') DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    $steps[] = ['ok', 'Table <code>users</code> verified / created'];

    // Generate hashes
    $adminHash = password_hash('password', PASSWORD_BCRYPT);
    $staffHash = password_hash('password', PASSWORD_BCRYPT);

    // Delete existing demo users and re-insert cleanly
    $pdo->exec("DELETE FROM `users` WHERE `email` IN ('admin@torvo.com','staff@torvo.com')");

    $stmt = $pdo->prepare("INSERT INTO `users` (`name`,`email`,`password`,`role`,`status`) VALUES (?,?,?,?,?)");

    $stmt->execute(['Admin User',   'admin@torvo.com', $adminHash, 'admin', 'active']);
    $steps[] = ['ok', 'Inserted user: <strong>admin@torvo.com</strong> (role: admin)'];

    $stmt->execute(['Staff Member', 'staff@torvo.com', $staffHash, 'staff', 'active']);
    $steps[] = ['ok', 'Inserted user: <strong>staff@torvo.com</strong> (role: staff)'];

    // Now verify
    $row = $pdo->query("SELECT `password` FROM `users` WHERE `email`='admin@torvo.com'")->fetch(PDO::FETCH_ASSOC);
    if ($row && password_verify('password', $row['password'])) {
        $steps[] = ['ok', '✅ <strong>Login verified!</strong> Password works correctly.'];
        $allOk = true;
    } else {
        $steps[] = ['err', '❌ Hash verification failed after insert.'];
        $allOk = false;
    }

    // Show total users
    $count = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
    $steps[] = ['info', "Total users in database: <strong>$count</strong>"];

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
<title>Create Users – TORVO SPAIR</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#0f0c29,#24243e);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}
.card{background:rgba(255,255,255,0.06);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.12);border-radius:20px;padding:2rem;width:100%;max-width:580px;box-shadow:0 25px 60px rgba(0,0,0,0.4)}
h1{font-size:1.3rem;font-weight:800;color:#fff;margin-bottom:1.5rem}
.step{display:flex;align-items:flex-start;gap:0.75rem;padding:0.7rem 1rem;border-radius:8px;margin-bottom:0.5rem;font-size:0.85rem;line-height:1.5}
.step.ok  {background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.25);color:#4ade80}
.step.err {background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.25);color:#fca5a5}
.step.info{background:rgba(59,130,246,0.12);border:1px solid rgba(59,130,246,0.25);color:#93c5fd}
code{background:rgba(255,255,255,0.1);padding:1px 6px;border-radius:4px;font-size:0.8rem}
.creds{margin:1.5rem 0;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:1.25rem}
.creds h3{color:rgba(255,255,255,0.7);font-size:0.8rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:0.75rem}
.cred-row{display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid rgba(255,255,255,0.06);font-size:0.85rem}
.cred-row:last-child{border-bottom:none}
.cred-row .label{color:rgba(255,255,255,0.4)}
.cred-row .val{color:#fff;font-weight:600}
.actions{display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:1.25rem}
.btn{padding:0.8rem 1.75rem;border-radius:10px;font-size:0.9rem;font-weight:700;text-decoration:none;display:inline-block;transition:all 0.3s}
.btn-primary{background:linear-gradient(135deg,#6c63ff,#48daf5);color:#fff}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(108,99,255,0.3)}
.del-note{font-size:0.73rem;color:rgba(255,255,255,0.25);margin-top:1rem}
</style>
</head>
<body>
<div class="card">
    <h1>👤 Create Default Users</h1>

    <?php
    $icons = ['ok'=>'✅','err'=>'❌','info'=>'ℹ️'];
    foreach ($steps as $s): ?>
    <div class="step <?= $s[0] ?>">
        <span><?= $icons[$s[0]] ?></span>
        <span><?= $s[1] ?></span>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($allOk)): ?>
    <div class="creds">
        <h3>Login Credentials</h3>
        <div class="cred-row"><span class="label">Admin Email</span><span class="val">admin@torvo.com</span></div>
        <div class="cred-row"><span class="label">Staff Email</span><span class="val">staff@torvo.com</span></div>
        <div class="cred-row"><span class="label">Password</span><span class="val">password</span></div>
    </div>
    <div class="actions">
        <a href="index.php" class="btn btn-primary">→ Go to Login</a>
    </div>
    <?php endif; ?>

    <p class="del-note">⚠️ Delete <code>create_users.php</code> after logging in successfully.</p>
</div>
</body>
</html>
