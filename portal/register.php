<?php
require_once __DIR__ . '/config/auth.php';
ensureB2BTables();
if (customerLoggedIn()) { header('Location: dashboard.php'); exit; }

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company = sanitize($_POST['company_name'] ?? '');
    $contact = sanitize($_POST['contact_name'] ?? '');
    $email   = sanitize($_POST['email'] ?? '');
    $phone   = sanitize($_POST['phone'] ?? '');
    $gstin   = sanitize($_POST['gstin'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $city    = sanitize($_POST['city'] ?? '');
    $state   = sanitize($_POST['state'] ?? '');
    $pin     = sanitize($_POST['pin'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $conf    = $_POST['confirm_password'] ?? '';

    if (empty($company)) $errors[] = 'Company name is required.';
    if (empty($contact)) $errors[] = 'Contact person name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email address is required.';
    if (empty($phone))   $errors[] = 'Phone number is required.';
    if (strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($pass !== $conf) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $db = portalDB();
        $chk = $db->prepare("SELECT id FROM customers WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $errors[] = 'This email is already registered.';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $db->prepare("INSERT INTO customers (company_name,contact_name,email,password,phone,gstin,address,city,state,pin,status) VALUES (?,?,?,?,?,?,?,?,?,?,'pending')")
               ->execute([$company, $contact, $email, $hash, $phone, $gstin, $address, $city, $state, $pin]);
            header('Location: index.php?registered=1'); exit;
        }
    }
}

$pageTitle  = 'Partner Registration';
$activePage = '';
include __DIR__ . '/includes/header.php';

$states = ['Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Goa','Gujarat','Haryana','Himachal Pradesh','Jharkhand','Karnataka','Kerala','Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura','Uttar Pradesh','Uttarakhand','West Bengal','Delhi','Jammu & Kashmir','Ladakh','Puducherry'];
?>

<div class="auth-wrap" style="align-items:flex-start;padding-top:2rem;">
<div class="auth-card" style="max-width:640px;">
    <div class="auth-logo">
        <div class="logo-icon" style="background:linear-gradient(135deg,#1e3a8a,#f97316);"><i class="fas fa-handshake"></i></div>
        <h1>Become a Partner</h1>
        <p>Register as a dealer or distributor — get access to exclusive B2B pricing</p>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error" style="flex-direction:column;align-items:flex-start;">
        <strong><i class="fas fa-times-circle"></i> Please fix the following:</strong>
        <ul style="margin-top:0.5rem;padding-left:1.2rem;">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST">
        <h3 style="font-size:0.85rem;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.75rem;">Business Details</h3>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Company / Business Name *</label>
                <input type="text" name="company_name" class="form-control" required placeholder="ABC Tools Pvt. Ltd." value="<?= htmlspecialchars($_POST['company_name']??'') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Contact Person *</label>
                <input type="text" name="contact_name" class="form-control" required placeholder="Full Name" value="<?= htmlspecialchars($_POST['contact_name']??'') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Business Email *</label>
                <input type="email" name="email" class="form-control" required placeholder="you@company.com" value="<?= htmlspecialchars($_POST['email']??'') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Phone / Mobile *</label>
                <input type="tel" name="phone" class="form-control" required placeholder="+91 98000 00000" value="<?= htmlspecialchars($_POST['phone']??'') ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">GSTIN <span style="color:var(--text-muted);font-weight:400;">(optional)</span></label>
            <input type="text" name="gstin" class="form-control" placeholder="22AAAAA0000A1Z5" maxlength="15" value="<?= htmlspecialchars($_POST['gstin']??'') ?>">
            <div class="form-hint">GST Registration Number — required for GST-compliant invoices</div>
        </div>
        <div class="form-group">
            <label class="form-label">Business Address</label>
            <textarea name="address" class="form-control" rows="2" placeholder="Street, Area"><?= htmlspecialchars($_POST['address']??'') ?></textarea>
        </div>
        <div class="form-row" style="grid-template-columns:1fr 1fr 100px;">
            <div class="form-group">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control" placeholder="City" value="<?= htmlspecialchars($_POST['city']??'') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">State</label>
                <select name="state" class="form-control">
                    <option value="">Select State</option>
                    <?php foreach ($states as $s): ?>
                    <option value="<?= $s ?>" <?= ($_POST['state']??'')===$s?'selected':'' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">PIN</label>
                <input type="text" name="pin" class="form-control" placeholder="400001" maxlength="6" value="<?= htmlspecialchars($_POST['pin']??'') ?>">
            </div>
        </div>

        <h3 style="font-size:0.85rem;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:0.5px;margin:1.25rem 0 0.75rem;">Login Credentials</h3>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Password *</label>
                <input type="password" name="password" class="form-control" required placeholder="Min 6 characters">
            </div>
            <div class="form-group">
                <label class="form-label">Confirm Password *</label>
                <input type="password" name="confirm_password" class="form-control" required placeholder="Repeat password">
            </div>
        </div>

        <div style="background:rgba(37,99,235,0.05);border:1px solid rgba(37,99,235,0.15);border-radius:8px;padding:0.85rem;margin-bottom:1rem;font-size:0.8rem;color:var(--text-medium);">
            <i class="fas fa-info-circle" style="color:var(--primary);"></i>
            Your account will be <strong>reviewed and activated within 24 hours</strong> by our team.
            You'll receive an email confirmation once approved.
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg">
            <i class="fas fa-paper-plane"></i> Submit Registration
        </button>
    </form>

    <p style="text-align:center;margin-top:1.25rem;font-size:0.875rem;color:var(--text-light);">
        Already registered? <a href="index.php" style="color:var(--primary);font-weight:700;">Sign In</a>
    </p>
</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
