<?php
require_once __DIR__ . '/config/auth.php';
ensureB2BTables();
requireCustomerLogin();

$customer = currentCustomer();
$db  = portalDB();
$cid = $customer['id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $company = sanitize($_POST['company_name']  ?? '');
        $contact = sanitize($_POST['contact_name']  ?? '');
        $phone   = sanitize($_POST['phone']          ?? '');
        $gstin   = sanitize($_POST['gstin']          ?? '');
        $address = sanitize($_POST['address']        ?? '');
        $city    = sanitize($_POST['city']           ?? '');
        $state   = sanitize($_POST['state']          ?? '');
        $pin     = sanitize($_POST['pin']            ?? '');

        $errors = [];
        if (empty($company)) $errors[] = 'Company name is required.';
        if (empty($contact)) $errors[] = 'Contact name is required.';

        if (empty($errors)) {
            $db->prepare("UPDATE customers SET company_name=?,contact_name=?,phone=?,gstin=?,address=?,city=?,state=?,pin=?,updated_at=NOW() WHERE id=?")
               ->execute([$company, $contact, $phone, $gstin, $address, $city, $state, $pin, $cid]);
            // Refresh session data
            $_SESSION['portal_customer']['name']    = $contact;
            $_SESSION['portal_customer']['company'] = $company;
            setPortalFlash('success', 'Profile updated successfully.');
        } else {
            setPortalFlash('error', implode(' ', $errors));
        }
        header('Location: profile.php'); exit;
    }

    if ($action === 'change_password') {
        $current  = $_POST['current_password']  ?? '';
        $new      = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';

        $custRow = $db->prepare("SELECT password FROM customers WHERE id=?");
        $custRow->execute([$cid]);
        $hash = $custRow->fetchColumn();

        if (!password_verify($current, $hash)) {
            setPortalFlash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            setPortalFlash('error', 'New password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            setPortalFlash('error', 'Passwords do not match.');
        } else {
            $db->prepare("UPDATE customers SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_BCRYPT), $cid]);
            setPortalFlash('success', 'Password changed successfully.');
        }
        header('Location: profile.php'); exit;
    }
}

// Reload fresh data
$cust = $db->prepare("SELECT * FROM customers WHERE id=?");
$cust->execute([$cid]);
$cust = $cust->fetch();

// Stats
$db->exec("CREATE TABLE IF NOT EXISTS `rfqs` (`id` INT AUTO_INCREMENT PRIMARY KEY,`customer_id` INT NOT NULL DEFAULT 0,`rfq_number` VARCHAR(30) UNIQUE,`status` ENUM('submitted','reviewing','quoted','accepted','rejected','closed') DEFAULT 'submitted',`customer_notes` TEXT,`admin_notes` TEXT,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS `orders` (`id` INT AUTO_INCREMENT PRIMARY KEY,`order_number` VARCHAR(30) UNIQUE,`rfq_id` INT DEFAULT NULL,`customer_id` INT NOT NULL DEFAULT 0,`status` ENUM('pending','confirmed','processing','dispatched','delivered','cancelled') DEFAULT 'pending',`subtotal` DECIMAL(10,2) DEFAULT 0.00,`gst_rate` DECIMAL(5,2) DEFAULT 18.00,`gst_amount` DECIMAL(10,2) DEFAULT 0.00,`total_amount` DECIMAL(10,2) DEFAULT 0.00,`shipping_address` TEXT,`payment_status` ENUM('unpaid','paid','partial') DEFAULT 'unpaid',`tracking_info` VARCHAR(255),`admin_notes` TEXT,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$totalRFQs   = $db->prepare("SELECT COUNT(*) FROM rfqs WHERE customer_id=?"); $totalRFQs->execute([$cid]); $totalRFQs = $totalRFQs->fetchColumn();
$totalOrders = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id=?"); $totalOrders->execute([$cid]); $totalOrders = $totalOrders->fetchColumn();
$totalSpent  = $db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE customer_id=? AND status='delivered'"); $totalSpent->execute([$cid]); $totalSpent = $totalSpent->fetchColumn();

$pageTitle  = 'My Profile';
$activePage = 'profile';
include __DIR__ . '/includes/header.php';

$tierColors = ['standard'=>'#6b7280','silver'=>'#94a3b8','gold'=>'#d97706'];
$tierLabels = ['standard'=>'Standard Partner','silver'=>'Silver Partner','gold'=>'Gold Partner ⭐'];
$states = ['Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Goa','Gujarat','Haryana','Himachal Pradesh','Jharkhand','Karnataka','Kerala','Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura','Uttar Pradesh','Uttarakhand','West Bengal','Andaman & Nicobar','Chandigarh','D&NH & DD','Delhi','Jammu & Kashmir','Ladakh','Lakshadweep','Puducherry'];
?>

<div class="breadcrumb"><div class="breadcrumb-inner">
    <a href="dashboard.php">Dashboard</a>
    <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
    <span>My Profile</span>
</div></div>

<div class="section container" style="max-width:900px;">
    <h1 style="font-size:1.3rem;font-weight:800;margin-bottom:1.5rem;color:var(--text-dark);">My Profile</h1>

    <div style="display:grid;grid-template-columns:260px 1fr;gap:1.5rem;align-items:start;">

        <!-- Profile Card -->
        <div>
            <div class="card" style="text-align:center;padding:1.75rem 1.25rem;margin-bottom:1rem;">
                <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-light));display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:1.75rem;font-weight:900;color:#fff;">
                    <?= strtoupper(substr($cust['contact_name'], 0, 1)) ?>
                </div>
                <div style="font-weight:800;font-size:1rem;color:var(--text-dark);margin-bottom:0.25rem;"><?= htmlspecialchars($cust['contact_name']) ?></div>
                <div style="font-size:0.82rem;color:var(--text-light);margin-bottom:1rem;"><?= htmlspecialchars($cust['company_name']) ?></div>
                <div style="display:inline-flex;align-items:center;gap:0.4rem;background:<?= $tierColors[$cust['tier']] ?>10;color:<?= $tierColors[$cust['tier']] ?>;border:1px solid <?= $tierColors[$cust['tier']] ?>30;padding:0.35rem 0.85rem;border-radius:20px;font-size:0.75rem;font-weight:700;margin-bottom:1rem;">
                    <?= $tierLabels[$cust['tier']] ?>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;text-align:center;padding-top:1rem;border-top:1px solid var(--border);">
                    <div><div style="font-size:1.1rem;font-weight:800;color:var(--primary);"><?= $totalRFQs ?></div><div style="font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;">RFQs</div></div>
                    <div><div style="font-size:1.1rem;font-weight:800;color:var(--primary);"><?= $totalOrders ?></div><div style="font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;">Orders</div></div>
                </div>
                <?php if ($totalSpent > 0): ?>
                <div style="margin-top:0.75rem;padding:0.5rem;background:rgba(22,163,74,0.05);border-radius:8px;">
                    <div style="font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;">Total Spent</div>
                    <div style="font-weight:800;color:var(--success);">₹<?= number_format($totalSpent,0) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Account status -->
            <div class="card" style="padding:1rem;">
                <div style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.6rem;">Account</div>
                <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.82rem;">
                    <span>Status</span>
                    <span class="status-badge status-<?= $cust['status'] ?>"><?= ucfirst($cust['status']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.82rem;margin-top:0.5rem;">
                    <span>Member Since</span>
                    <span style="color:var(--text-muted);"><?= date('M Y', strtotime($cust['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Forms -->
        <div style="display:flex;flex-direction:column;gap:1rem;">

            <!-- Update Profile -->
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-building"></i> Company Information</div></div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Company Name *</label>
                                <input type="text" name="company_name" class="form-control" required value="<?= htmlspecialchars($cust['company_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Contact Person *</label>
                                <input type="text" name="contact_name" class="form-control" required value="<?= htmlspecialchars($cust['contact_name']) ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($cust['phone'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">GSTIN</label>
                                <input type="text" name="gstin" class="form-control" placeholder="27AABCT1332L1ZQ" maxlength="15" value="<?= htmlspecialchars($cust['gstin'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($cust['address'] ?? '') ?></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($cust['city'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">State</label>
                                <select name="state" class="form-control">
                                    <option value="">— Select State —</option>
                                    <?php foreach ($states as $s): ?>
                                    <option value="<?= $s ?>" <?= ($cust['state']??'')===$s?'selected':'' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="max-width:120px;">
                                <label class="form-label">PIN Code</label>
                                <input type="text" name="pin" class="form-control" maxlength="6" value="<?= htmlspecialchars($cust['pin'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    <div style="padding:0.75rem 1.25rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Profile</button>
                    </div>
                </form>
            </div>

            <!-- Change Password -->
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-lock"></i> Change Password</div></div>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" required autocomplete="new-password">
                            </div>
                        </div>
                        <div style="font-size:0.75rem;color:var(--text-muted);"><i class="fas fa-info-circle"></i> Minimum 8 characters</div>
                    </div>
                    <div style="padding:0.75rem 1.25rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
