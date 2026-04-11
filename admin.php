<?php
// ============================================
// ALL PHP logic MUST come before any HTML output
// so session_start() can succeed.
// ============================================
require_once 'config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: pages/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_role']  = $user['role'];
                $_SESSION['user_email'] = $user['email'];
                header('Location: pages/dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password. Please try again.';
            }
        } catch (PDOException $e) {
            $error = 'A system error occurred. Please contact your administrator.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – TORVO SPAIR Inventory</title>
    <meta name="description" content="Secure login for TORVO SPAIR Power Tools Inventory Management System">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body.login-body {
            background: linear-gradient(135deg, #0f0c29 0%, #1a1a3e 40%, #24243e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            font-family: 'Inter', sans-serif;
        }
        .login-wrapper {
            width: 100%;
            max-width: 440px;
            animation: slideUp 0.6s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .login-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
        }
        .login-logo { text-align: center; margin-bottom: 2rem; }
        .login-logo .logo-icon {
            width: 70px; height: 70px;
            background: linear-gradient(135deg, #6c63ff, #48daf5);
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.8rem; color: #fff;
            box-shadow: 0 8px 25px rgba(108,99,255,0.4);
        }
        .login-logo h1 {
            font-size: 1.6rem; font-weight: 800; color: #fff;
            letter-spacing: 2px; margin: 0;
        }
        .login-logo p {
            font-size: 0.8rem; color: rgba(255,255,255,0.5);
            margin: 0.3rem 0 0; letter-spacing: 1px;
        }
        .login-form label {
            display: block; font-size: 0.8rem; font-weight: 600;
            color: rgba(255,255,255,0.7); margin-bottom: 0.4rem;
            letter-spacing: 0.5px; text-transform: uppercase;
        }
        .input-group { position: relative; margin-bottom: 1.2rem; }
        .input-group .input-icon {
            position: absolute; left: 1rem; top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.4); font-size: 0.9rem;
        }
        .input-group input {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 2.8rem;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 10px; color: #fff;
            font-size: 0.95rem; font-family: 'Inter', sans-serif;
            transition: all 0.3s; box-sizing: border-box;
        }
        .input-group input:focus {
            outline: none; border-color: #6c63ff;
            background: rgba(108,99,255,0.1);
            box-shadow: 0 0 0 3px rgba(108,99,255,0.2);
        }
        .input-group input::placeholder { color: rgba(255,255,255,0.3); }
        .toggle-password {
            position: absolute; right: 1rem; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: rgba(255,255,255,0.4); cursor: pointer;
            padding: 0; font-size: 0.9rem;
        }
        .btn-login {
            width: 100%; padding: 0.95rem;
            background: linear-gradient(135deg, #6c63ff, #48daf5);
            border: none; border-radius: 10px;
            color: #fff; font-size: 1rem; font-weight: 700;
            font-family: 'Inter', sans-serif; cursor: pointer;
            transition: all 0.3s; letter-spacing: 0.5px; margin-top: 0.5rem;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(108,99,255,0.4); }
        .btn-login:active { transform: translateY(0); }
        .login-error {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.4);
            border-radius: 8px; padding: 0.75rem 1rem;
            color: #fca5a5; font-size: 0.875rem;
            margin-bottom: 1.2rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .login-hint {
            margin-top: 1.5rem; padding: 1rem;
            background: rgba(255,255,255,0.04);
            border-radius: 8px; font-size: 0.78rem;
            color: rgba(255,255,255,0.4); text-align: center; line-height: 1.6;
        }
        .login-hint strong { color: rgba(255,255,255,0.6); }
        .particles {
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none; overflow: hidden; z-index: 0;
        }
        .particle {
            position: absolute; width: 4px; height: 4px;
            background: rgba(108,99,255,0.4);
            border-radius: 50%; animation: float linear infinite;
        }
        @keyframes float {
            0%   { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10%  { opacity: 1; }
            90%  { opacity: 1; }
            100% { transform: translateY(-100px) rotate(720deg); opacity: 0; }
        }
    </style>
</head>
<body class="login-body">

<div class="particles" id="particles"></div>

<div class="login-wrapper" style="position:relative;z-index:1;">
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-icon"><i class="fas fa-wrench"></i></div>
            <h1>TORVO SPAIR</h1>
            <p>INVENTORY MANAGEMENT SYSTEM</p>
        </div>

        <?php if ($error): ?>
        <div class="login-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form class="login-form" method="POST" action="admin.php" id="loginForm">
            <div class="input-group">
                <label for="email">Email Address</label>
                <i class="fas fa-envelope input-icon"></i>
                <input type="email" id="email" name="email" placeholder="admin@torvo.com" required
                    value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>
            <div class="input-group">
                <label for="password">Password</label>
                <i class="fas fa-lock input-icon"></i>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                <button type="button" class="toggle-password" onclick="togglePwd()">
                    <i class="fas fa-eye" id="eyeIcon"></i>
                </button>
            </div>
            <button type="submit" class="btn-login" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <div class="login-hint">
            <strong>Demo Credentials:</strong><br>
            Admin: admin@torvo.com | staff@torvo.com<br>
            Password: <strong>password</strong>
        </div>
    </div>
</div>

<script>
// Particle animation
const container = document.getElementById('particles');
for (let i = 0; i < 20; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    p.style.cssText = `
        left: ${Math.random() * 100}%;
        width: ${Math.random() * 6 + 2}px;
        height: ${Math.random() * 6 + 2}px;
        animation-duration: ${Math.random() * 15 + 8}s;
        animation-delay: ${Math.random() * 5}s;
        opacity: ${Math.random() * 0.5};
        background: ${Math.random() > 0.5 ? 'rgba(108,99,255,0.5)' : 'rgba(72,218,245,0.5)'};
    `;
    container.appendChild(p);
}

function togglePwd() {
    const pwd  = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        pwd.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
    btn.disabled = true;
});
</script>
</body>
</html>
