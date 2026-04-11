<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle  = 'Categories';
$pageIcon   = 'fas fa-layer-group';
$activePage = 'categories';
$pageBreadcrumb = 'Categories';
include BASE_PATH . '/includes/header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create' || $action === 'update') {
        $name   = sanitize($_POST['name'] ?? '');
        $desc   = sanitize($_POST['description'] ?? '');
        $status = in_array($_POST['status']??'active',['active','inactive'])?$_POST['status']:'active';
        if (empty($name)) {
            setFlash('error', 'Category name is required.');
        } else {
            if ($action === 'create') {
                $db->prepare("INSERT INTO categories (name,description,status) VALUES (?,?,?)")->execute([$name,$desc,$status]);
                setFlash('success', 'Category created successfully.');
            } else {
                $cid = (int)($_POST['cat_id']??0);
                $db->prepare("UPDATE categories SET name=?,description=?,status=? WHERE id=?")->execute([$name,$desc,$status,$cid]);
                setFlash('success', 'Category updated successfully.');
            }
            header('Location: categories.php'); exit;
        }
    }
    if ($action === 'delete') {
        $cid = (int)($_POST['cat_id']??0);
        // Check if products exist
        $cnt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id=?");
        $cnt->execute([$cid]);
        if ($cnt->fetchColumn() > 0) {
            setFlash('error', 'Cannot delete: category has products linked to it.');
        } else {
            $db->prepare("DELETE FROM categories WHERE id=?")->execute([$cid]);
            setFlash('success', 'Category deleted.');
        }
        header('Location: categories.php'); exit;
    }
}

$categories = $db->query("
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.status='active'
    GROUP BY c.id ORDER BY c.name
")->fetchAll();
?>

<div class="page-body">
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-layer-group"></i> Categories</div>
        <button class="btn btn-primary" onclick="openModal('addCatModal')"><i class="fas fa-plus"></i> Add Category</button>
    </div>
    <?php if (empty($categories)): ?>
        <div class="empty-state"><i class="fas fa-layer-group"></i><h3>No categories yet</h3></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>#</th><th>Category Name</th><th>Description</th><th>Products</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($categories as $i => $c): ?>
            <tr>
                <td style="color:var(--text-muted);"><?= $i+1 ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <div style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.85rem;flex-shrink:0;">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <strong style="font-size:0.9rem;"><?= htmlspecialchars($c['name']) ?></strong>
                    </div>
                </td>
                <td style="font-size:0.85rem;color:var(--text-muted);max-width:250px;"><?= htmlspecialchars($c['description'] ?: '—') ?></td>
                <td>
                    <span class="badge-pill badge-info"><i class="fas fa-box"></i> <?= $c['product_count'] ?> products</span>
                </td>
                <td><span class="badge-pill <?= $c['status']==='active'?'badge-success':'badge-gray' ?>"><?= ucfirst($c['status']) ?></span></td>
                <td>
                    <div style="display:flex;gap:0.4rem;">
                        <button class="btn btn-outline btn-sm btn-icon" onclick='editCat(<?= json_encode($c) ?>)' data-tooltip="Edit"><i class="fas fa-edit"></i></button>
                        <a href="products.php?category=<?= $c['id'] ?>" class="btn btn-primary btn-sm btn-icon" data-tooltip="View Products"><i class="fas fa-eye"></i></a>
                        <form method="POST" onsubmit="return confirmDelete('Delete this category?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm btn-icon" data-tooltip="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ADD CATEGORY MODAL -->
<div class="modal-overlay" id="addCatModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-plus-circle" style="color:var(--primary)"></i> Add Category</div>
            <button class="modal-close" onclick="closeModal('addCatModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Category Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Drill Parts" required></div>
                <div class="form-group"><label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Optional description..."></textarea></div>
                <div class="form-group"><label class="form-label">Status</label>
                    <select name="status" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addCatModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT CATEGORY MODAL -->
<div class="modal-overlay" id="editCatModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-edit" style="color:var(--primary)"></i> Edit Category</div>
            <button class="modal-close" onclick="closeModal('editCatModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="cat_id" id="editCatId">
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Category Name *</label>
                    <input type="text" name="name" id="editCatName" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Description</label>
                    <textarea name="description" id="editCatDesc" class="form-control" rows="3"></textarea></div>
                <div class="form-group"><label class="form-label">Status</label>
                    <select name="status" id="editCatStatus" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editCatModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
            </div>
        </form>
    </div>
</div>
</div>

<script>
function editCat(c) {
    document.getElementById('editCatId').value     = c.id;
    document.getElementById('editCatName').value   = c.name;
    document.getElementById('editCatDesc').value   = c.description || '';
    document.getElementById('editCatStatus').value = c.status;
    openModal('editCatModal');
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
