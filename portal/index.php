<?php
require_once __DIR__ . '/config/auth.php';

// Redirect if already logged in
if (customerLoggedIn()) {
    $c = currentCustomer();
    if (($c['status'] ?? '') === 'pending') {
        // Show pending page instead of dashboard
    } else {
        header('Location: dashboard.php'); exit;
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (empty($email) || empty($pass)) {
        $error = 'Please enter your email and password.';
    } else {
        $db   = portalDB();
        $stmt = $db->prepare("SELECT * FROM customers WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $cust = $stmt->fetch();

        if ($cust && password_verify($pass, $cust['password'])) {
            if ($cust['status'] === 'pending') {
                // Let them log in — they'll see the pending page
                $_SESSION['customer_id']     = $cust['id'];
                $_SESSION['portal_customer'] = $cust; // full row
                header('Location: index.php?pending=1'); exit;
            } elseif ($cust['status'] === 'suspended') {
                $error = 'Your account has been suspended. Please contact us.';
            } else {
                $_SESSION['customer_id']     = $cust['id'];
                $_SESSION['portal_customer'] = $cust; // full row including status
                $redirect = sanitize($_GET['redirect_to'] ?? '');
                header('Location: ' . ($redirect ?: 'dashboard.php')); exit;
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$pageTitle  = 'Customer Login';
$activePage = '';
include __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-logo">
            <div class="logo-icon"><i class="fas fa-building"></i></div>
            <h1>Partner Login</h1>
            <p>Sign in to your TORVO SPAIR B2B account</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($_GET['redirect'])): ?>
        <div class="alert alert-info"><i class="fas fa-info-circle"></i> Please log in to add items to your RFQ or submit a quotation request.</div>
        <?php endif; ?>

        <?php if (!empty($_GET['pending'])): ?>
        <div class="alert alert-warning" style="text-align:center;">
            <i class="fas fa-hourglass-half"></i>
            <div>
                <strong>Application Under Review</strong><br>
                <span style="font-size:0.85rem;">Your partner account is pending admin approval. You'll receive an email once activated. Meanwhile you can still browse our catalogue.</span>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Business Email *</label>
                <input type="email" name="email" class="form-control" required placeholder="you@company.com"
                    value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Password *</label>
                <input type="password" name="password" class="form-control" required placeholder="Your password">
            </div>
            <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:0.5rem;">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <div class="auth-divider">or</div>

        <p style="text-align:center;font-size:0.875rem;color:var(--text-light);">
            New dealer / distributor?
            <a href="register.php" style="color:var(--primary);font-weight:700;">Apply for Partnership</a>
        </p>
        <p style="text-align:center;margin-top:1rem;">
            <a href="catalogue.php" style="font-size:0.8rem;color:var(--text-muted);">
                <i class="fas fa-arrow-left"></i> Browse Catalogue Without Login
            </a>
        </p>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
