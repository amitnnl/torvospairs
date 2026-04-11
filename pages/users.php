<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle  = 'User Management';
$pageIcon   = 'fas fa-users';
$activePage = 'users';
$pageBreadcrumb = 'Users';
include BASE_PATH . '/includes/header.php';
requireAdmin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name  = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $role  = in_array($_POST['role']??'staff',['admin','staff'])?$_POST['role']:'staff';

        if (empty($name)||empty($email)||empty($pass)) {
            setFlash('error', 'All fields are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Invalid email address.');
        } elseif (strlen($pass) < 6) {
            setFlash('error', 'Password must be at least 6 characters.');
        } else {
            // Check duplicate email
            $chk = $db->prepare("SELECT id FROM users WHERE email=?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                setFlash('error', 'Email already exists.');
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $db->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)")->execute([$name,$email,$hash,$role]);
                setFlash('success', 'User created successfully.');
            }
        }
        header('Location: users.php'); exit;
    }

    if ($action === 'update') {
        $uid   = (int)($_POST['user_id']??0);
        $name  = sanitize($_POST['name']??'');
        $email = sanitize($_POST['email']??'');
        $role  = in_array($_POST['role']??'staff',['admin','staff'])?$_POST['role']:'staff';
        $status= in_array($_POST['status']??'active',['active','inactive'])?$_POST['status']:'active';
        $pass  = $_POST['password']??'';

        if ($uid > 0) {
            if (!empty($pass) && strlen($pass) >= 6) {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $db->prepare("UPDATE users SET name=?,email=?,role=?,status=?,password=? WHERE id=?")->execute([$name,$email,$role,$status,$hash,$uid]);
            } else {
                $db->prepare("UPDATE users SET name=?,email=?,role=?,status=? WHERE id=?")->execute([$name,$email,$role,$status,$uid]);
            }
            setFlash('success', 'User updated successfully.');
        }
        header('Location: users.php'); exit;
    }

    if ($action === 'delete') {
        $uid = (int)($_POST['user_id']??0);
        $cur = currentUser();
        if ($uid === (int)$cur['id']) {
            setFlash('error', 'You cannot delete your own account.');
        } else {
            $db->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
            setFlash('success', 'User deleted.');
        }
        header('Location: users.php'); exit;
    }
}

$users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
?>

<div class="page-body">
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-users"></i> Users <span style="font-size:0.85rem;color:var(--text-muted);font-weight:400;">(<?= count($users) ?>)</span></div>
        <button class="btn btn-primary" onclick="openModal('addUserModal')"><i class="fas fa-user-plus"></i> Add User</button>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>#</th><th>User</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $i => $u): $cur = currentUser(); ?>
            <tr>
                <td style="color:var(--text-muted);"><?= $i+1 ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.9rem;flex-shrink:0;">
                            <?= strtoupper(substr($u['name'],0,1)) ?>
                        </div>
                        <div>
                            <div style="font-weight:600;"><?= htmlspecialchars($u['name']) ?></div>
                            <?php if ($u['id']==$cur['id']): ?><div style="font-size:0.7rem;color:var(--primary);">You</div><?php endif; ?>
                        </div>
                    </div>
                </td>
                <td style="font-size:0.85rem;"><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <span class="badge-pill <?= $u['role']==='admin'?'badge-primary':'badge-info' ?>">
                        <i class="fas fa-<?= $u['role']==='admin'?'shield-alt':'user' ?>"></i> <?= ucfirst($u['role']) ?>
                    </span>
                </td>
                <td><span class="badge-pill <?= $u['status']==='active'?'badge-success':'badge-gray' ?>"><?= ucfirst($u['status']) ?></span></td>
                <td style="font-size:0.8rem;color:var(--text-muted);"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:0.4rem;">
                        <button class="btn btn-outline btn-sm btn-icon" onclick='editUser(<?= json_encode($u) ?>)' data-tooltip="Edit"><i class="fas fa-edit"></i></button>
                        <?php if ($u['id']!=$cur['id']): ?>
                        <form method="POST" onsubmit="return confirmDelete('Delete this user?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm btn-icon" data-tooltip="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD USER MODAL -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-user-plus" style="color:var(--primary)"></i> Add User</div>
            <button class="modal-close" onclick="closeModal('addUserModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="John Doe"></div>
                <div class="form-group"><label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" required placeholder="john@example.com"></div>
                <div class="form-group"><label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required placeholder="Min 6 characters"></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Role</label>
                        <select name="role" class="form-control">
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT USER MODAL -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-edit" style="color:var(--primary)"></i> Edit User</div>
            <button class="modal-close" onclick="closeModal('editUserModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="user_id" id="editUserId">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Full Name *</label>
                    <input type="text" name="name" id="editUserName" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Email *</label>
                    <input type="email" name="email" id="editUserEmail" class="form-control" required></div>
                <div class="form-group"><label class="form-label">New Password <span style="color:var(--text-muted);font-weight:400;">(leave blank to keep)</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current"></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Role</label>
                        <select name="role" id="editUserRole" class="form-control">
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select></div>
                    <div class="form-group"><label class="form-label">Status</label>
                        <select name="status" id="editUserStatus" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
            </div>
        </form>
    </div>
</div>

</div>

<script>
function editUser(u) {
    document.getElementById('editUserId').value     = u.id;
    document.getElementById('editUserName').value   = u.name;
    document.getElementById('editUserEmail').value  = u.email;
    document.getElementById('editUserRole').value   = u.role;
    document.getElementById('editUserStatus').value = u.status;
    openModal('editUserModal');
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
