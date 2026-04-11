<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle  = 'Power Tools';
$pageIcon   = 'fas fa-tools';
$activePage = 'tools';
$pageBreadcrumb = 'Power Tools';
include BASE_PATH . '/includes/header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name  = sanitize($_POST['name'] ?? '');
        $model = sanitize($_POST['model'] ?? '');
        $brand = sanitize($_POST['brand'] ?? '');
        $desc  = sanitize($_POST['description'] ?? '');
        $status= in_array($_POST['status']??'active',['active','inactive']) ? $_POST['status'] : 'active';

        if (empty($name)) {
            setFlash('error', 'Tool name is required.');
        } else {
            $image = null;
            if (!empty($_FILES['image']['tmp_name'])) $image = uploadImage($_FILES['image'], 'tool');

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO tools (name,model,brand,description,image,status) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$name,$model,$brand,$desc,$image,$status]);
            } else {
                $tid = (int)($_POST['tool_id'] ?? 0);
                if ($image) {
                    $db->prepare("UPDATE tools SET name=?,model=?,brand=?,description=?,image=?,status=? WHERE id=?")->execute([$name,$model,$brand,$desc,$image,$status,$tid]);
                } else {
                    $db->prepare("UPDATE tools SET name=?,model=?,brand=?,description=?,status=? WHERE id=?")->execute([$name,$model,$brand,$desc,$status,$tid]);
                }
            }
            setFlash('success', 'Tool ' . ($action==='create' ? 'added' : 'updated') . ' successfully.');
            header('Location: tools.php'); exit;
        }
    }

    if ($action === 'delete') {
        $tid = (int)($_POST['tool_id'] ?? 0);
        $db->prepare("DELETE FROM tools WHERE id=?")->execute([$tid]);
        setFlash('success', 'Tool deleted.');
        header('Location: tools.php'); exit;
    }
}

$search = sanitize($_GET['search'] ?? '');
$where = '1=1'; $params = [];
if ($search) { $where .= ' AND (name LIKE ? OR brand LIKE ? OR model LIKE ?)'; $params = ["%$search%","%$search%","%$search%"]; }

$stmt = $db->prepare("SELECT * FROM tools WHERE $where ORDER BY name");
$stmt->execute($params);
$tools = $stmt->fetchAll();
?>

<div class="page-body">

<!-- Search Bar -->
<form method="GET" class="filter-bar">
    <div class="filter-group" style="flex:1;">
        <i class="fas fa-search" style="color:var(--text-muted);"></i>
        <input type="text" name="search" class="form-control" placeholder="Search tools by name, brand, model..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
    <a href="tools.php" class="btn btn-outline">Reset</a>
</form>

<!-- Tools Grid -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-tools"></i> Power Tools <span style="font-size:0.85rem;color:var(--text-muted);font-weight:400;">(<?= count($tools) ?>)</span></div>
        <button class="btn btn-primary" onclick="openModal('addToolModal')"><i class="fas fa-plus"></i> Add Tool</button>
    </div>

    <?php if (empty($tools)): ?>
        <div class="empty-state"><i class="fas fa-tools"></i><h3>No tools found</h3><p>Add your first power tool to get started.</p></div>
    <?php else: ?>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem;">
        <?php foreach ($tools as $t):
            $prodCount = $db->prepare("SELECT COUNT(*) FROM product_compatibility WHERE tool_id=?");
            $prodCount->execute([$t['id']]);
            $pc = $prodCount->fetchColumn();
        ?>
        <div style="border:1px solid var(--border-color);border-radius:var(--radius-lg);overflow:hidden;background:var(--bg-card);transition:var(--transition);" onmouseover="this.style.boxShadow='var(--shadow-md)';this.style.transform='translateY(-3px)'" onmouseout="this.style.boxShadow='';this.style.transform=''">
            <!-- Tool Image / Header -->
            <div style="background:linear-gradient(135deg,#1e1b4b,#312e81);padding:2rem;display:flex;align-items:center;justify-content:center;min-height:120px;">
                <?php if ($t['image'] && file_exists(UPLOAD_DIR . $t['image'])): ?>
                    <img src="<?= UPLOAD_URL . htmlspecialchars($t['image']) ?>" style="max-height:80px;object-fit:contain;" alt="">
                <?php else: ?>
                    <i class="fas fa-tools" style="font-size:3rem;color:rgba(255,255,255,0.4);"></i>
                <?php endif; ?>
            </div>
            <div style="padding:1.25rem;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.5rem;margin-bottom:0.75rem;">
                    <div>
                        <h3 style="font-size:0.95rem;font-weight:700;color:var(--text-primary);margin:0 0 0.2rem;"><?= htmlspecialchars($t['name']) ?></h3>
                        <?php if ($t['model']): ?><div style="font-size:0.75rem;color:var(--text-muted);">Model: <?= htmlspecialchars($t['model']) ?></div><?php endif; ?>
                    </div>
                    <span class="badge-pill <?= $t['status']==='active' ? 'badge-success' : 'badge-gray' ?>"><?= ucfirst($t['status']) ?></span>
                </div>
                <?php if ($t['brand']): ?>
                    <div style="margin-bottom:0.75rem;">
                        <span class="badge-pill badge-primary"><i class="fas fa-tag"></i> <?= htmlspecialchars($t['brand']) ?></span>
                    </div>
                <?php endif; ?>
                <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:1rem;">
                    <i class="fas fa-boxes"></i> <?= $pc ?> compatible products
                </div>
                <?php if ($t['description']): ?>
                    <p style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:1rem;line-height:1.5;"><?= htmlspecialchars(substr($t['description'],0,100)) ?>...</p>
                <?php endif; ?>
                <div style="display:flex;gap:0.5rem;">
                    <button class="btn btn-outline btn-sm" style="flex:1;" onclick='editTool(<?= json_encode($t) ?>)'>
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <form method="POST" onsubmit="return confirmDelete('Delete this tool?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="tool_id" value="<?= $t['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm btn-icon"><i class="fas fa-trash"></i></button>
                    </form>
                    <a href="products.php?tool=<?= $t['id'] ?>" class="btn btn-primary btn-sm btn-icon" data-tooltip="View Compatible Products">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ADD TOOL MODAL -->
<div class="modal-overlay" id="addToolModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-plus-circle" style="color:var(--primary)"></i> Add Power Tool</div>
            <button class="modal-close" onclick="closeModal('addToolModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Tool Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Drill Machine" required></div>
                    <div class="form-group"><label class="form-label">Model</label>
                        <input type="text" name="model" class="form-control" placeholder="e.g. DM-500"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Brand</label>
                        <input type="text" name="brand" class="form-control" placeholder="e.g. Bosch"></div>
                    <div class="form-group"><label class="form-label">Status</label>
                        <select name="status" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                </div>
                <div class="form-group"><label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Brief description..."></textarea></div>
                <div class="form-group"><label class="form-label">Tool Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addToolModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Tool</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT TOOL MODAL -->
<div class="modal-overlay" id="editToolModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-edit" style="color:var(--primary)"></i> Edit Tool</div>
            <button class="modal-close" onclick="closeModal('editToolModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="tool_id" id="editToolId">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Tool Name *</label>
                        <input type="text" name="name" id="editToolName" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Model</label>
                        <input type="text" name="model" id="editToolModel" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Brand</label>
                        <input type="text" name="brand" id="editToolBrand" class="form-control"></div>
                    <div class="form-group"><label class="form-label">Status</label>
                        <select name="status" id="editToolStatus" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                </div>
                <div class="form-group"><label class="form-label">Description</label>
                    <textarea name="description" id="editToolDesc" class="form-control" rows="3"></textarea></div>
                <div class="form-group"><label class="form-label">Replace Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editToolModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Tool</button>
            </div>
        </form>
    </div>
</div>

</div><!-- .page-body -->

<script>
function editTool(t) {
    document.getElementById('editToolId').value     = t.id;
    document.getElementById('editToolName').value   = t.name;
    document.getElementById('editToolModel').value  = t.model || '';
    document.getElementById('editToolBrand').value  = t.brand || '';
    document.getElementById('editToolDesc').value   = t.description || '';
    document.getElementById('editToolStatus').value = t.status;
    openModal('editToolModal');
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
