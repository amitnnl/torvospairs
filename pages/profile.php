<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle      = 'My Profile';
$pageIcon       = 'fas fa-user-circle';
$activePage     = 'profile';
$pageBreadcrumb = 'Profile';
include BASE_PATH . '/includes/header.php';

$db   = getDB();
$user = currentUser();
$uid  = $user['id'];

// Fetch full user record
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uid]);
$profile = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');

        if (empty($name) || empty($email)) {
            setFlash('error', 'Name and email are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Invalid email address.');
        } else {
            // Check duplicate email (exclude self)
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk->execute([$email, $uid]);
            if ($chk->fetch()) {
                setFlash('error', 'That email is already in use by another account.');
            } else {
                $db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?")->execute([$name, $email, $uid]);
                $_SESSION['user_name']  = $name;
                $_SESSION['user_email'] = $email;
                setFlash('success', 'Profile updated successfully.');
            }
        }
        header('Location: profile.php'); exit;
    }

    if ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (empty($current) || empty($new) || empty($confirm)) {
            setFlash('error', 'All password fields are required.');
        } elseif (!password_verify($current, $profile['password'])) {
            setFlash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            setFlash('error', 'New password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            setFlash('error', 'New passwords do not match.');
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $uid]);
            setFlash('success', 'Password changed successfully.');
        }
        header('Location: profile.php'); exit;
    }
}

// Activity stats
$stockActions = $db->prepare("SELECT COUNT(*) FROM stock_logs WHERE user_id = ?");
$stockActions->execute([$uid]);
$totalActions = $stockActions->fetchColumn();

$lastLog = $db->prepare("SELECT sl.created_at, p.name AS pname, sl.type, sl.quantity FROM stock_logs sl JOIN products p ON sl.product_id = p.id WHERE sl.user_id = ? ORDER BY sl.created_at DESC LIMIT 5");
$lastLog->execute([$uid]);
$recentActions = $lastLog->fetchAll();
?>

<div class="page-body">

<div style="display:grid;grid-template-columns:1fr 1.6fr;gap:1.25rem;align-items:start;">

    <!-- Profile Summary Card -->
    <div>
        <div class="card" style="margin-bottom:1.25rem;">
            <div style="background:linear-gradient(135deg,#1e1b4b,#312e81);padding:2.5rem;text-align:center;border-radius:var(--radius-lg) var(--radius-lg) 0 0;">
                <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;font-size:2rem;color:#fff;font-weight:800;margin:0 auto 1rem;border:4px solid rgba(255,255,255,0.2);">
                    <?= strtoupper(substr($profile['name'], 0, 1)) ?>
                </div>
                <h2 style="font-size:1.1rem;font-weight:800;color:#fff;margin-bottom:0.25rem;"><?= htmlspecialchars($profile['name']) ?></h2>
                <p style="font-size:0.8rem;color:rgba(255,255,255,0.55);"><?= htmlspecialchars($profile['email']) ?></p>
            </div>
            <div class="card-body">
                <div style="display:flex;justify-content:space-between;margin-bottom:0.75rem;">
                    <span style="font-size:0.8rem;color:var(--text-muted);">Role</span>
                    <span class="badge-pill <?= $profile['role']==='admin'?'badge-primary':'badge-info' ?>">
                        <i class="fas fa-<?= $profile['role']==='admin'?'shield-alt':'user' ?>"></i>
                        <?= ucfirst($profile['role']) ?>
                    </span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:0.75rem;">
                    <span style="font-size:0.8rem;color:var(--text-muted);">Status</span>
                    <span class="badge-pill badge-success">Active</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:0.75rem;">
                    <span style="font-size:0.8rem;color:var(--text-muted);">Member Since</span>
                    <span style="font-size:0.8rem;font-weight:600;"><?= date('d M Y', strtotime($profile['created_at'])) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="font-size:0.8rem;color:var(--text-muted);">Stock Actions</span>
                    <span style="font-size:0.8rem;font-weight:700;color:var(--primary);"><?= $totalActions ?></span>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-history"></i> My Recent Activity</div>
            </div>
            <?php if (empty($recentActions)): ?>
                <div class="empty-state" style="padding:1.5rem;"><i class="fas fa-inbox"></i><h3 style="font-size:0.9rem;">No activity yet</h3></div>
            <?php else: ?>
            <div style="padding:0;">
                <?php foreach ($recentActions as $ra): ?>
                <div style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem 1.25rem;border-bottom:1px solid var(--border-color);">
                    <div style="width:32px;height:32px;border-radius:8px;background:<?= $ra['type']==='in'?'rgba(34,197,94,0.1)':'rgba(245,158,11,0.1)' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-arrow-<?= $ra['type']==='in'?'down':'up' ?>" style="font-size:0.75rem;color:<?= $ra['type']==='in'?'var(--success)':'var(--warning)' ?>;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:0.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($ra['pname']) ?></div>
                        <div style="font-size:0.72rem;color:var(--text-muted);">
                            <?= strtoupper($ra['type']) ?> <?= $ra['quantity'] ?> units · <?= date('d M', strtotime($ra['created_at'])) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Forms Column -->
    <div>
        <!-- Update Profile -->
        <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-user-edit"></i> Edit Profile</div>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($profile['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($profile['email']) ?>" required>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-lock"></i> Change Password</div>
            </div>
            <div class="card-body">
                <form method="POST" id="pwdForm">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label class="form-label">Current Password *</label>
                        <div style="position:relative;">
                            <input type="password" name="current_password" id="curPwd" class="form-control" required placeholder="Enter current password">
                            <button type="button" onclick="toggleField('curPwd','eyeCur')" style="position:absolute;right:0.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);">
                                <i class="fas fa-eye" id="eyeCur"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">New Password *</label>
                            <input type="password" name="new_password" id="newPwd" class="form-control" required placeholder="Min 6 characters" oninput="checkStrength(this.value)">
                            <div id="strengthBar" style="height:4px;border-radius:2px;margin-top:0.4rem;background:var(--border-color);overflow:hidden;">
                                <div id="strengthFill" style="height:100%;width:0%;border-radius:2px;transition:all 0.3s;"></div>
                            </div>
                            <div id="strengthLabel" style="font-size:0.7rem;color:var(--text-muted);margin-top:0.2rem;"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" name="confirm_password" id="confPwd" class="form-control" required placeholder="Repeat new password">
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</div>

<script>
function toggleField(id, iconId) {
    const el   = document.getElementById(id);
    const icon = document.getElementById(iconId);
    if (el.type === 'password') { el.type = 'text'; icon.className = 'fas fa-eye-slash'; }
    else { el.type = 'password'; icon.className = 'fas fa-eye'; }
}

function checkStrength(val) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (val.length >= 6) score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        {w:'0%',   c:'#ef4444', t:''},
        {w:'20%',  c:'#ef4444', t:'Very Weak'},
        {w:'40%',  c:'#f59e0b', t:'Weak'},
        {w:'60%',  c:'#eab308', t:'Fair'},
        {w:'80%',  c:'#22c55e', t:'Good'},
        {w:'100%', c:'#16a34a', t:'Strong'},
    ];
    const lv = levels[Math.min(score, 5)];
    fill.style.width = lv.w;
    fill.style.background = lv.c;
    label.textContent = lv.t;
    label.style.color = lv.c;
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
