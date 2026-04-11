<?php
define('BASE_PATH', dirname(__DIR__));
$pageTitle   = 'Products';
$pageIcon    = 'fas fa-boxes';
$activePage  = 'products';
$pageBreadcrumb = 'Products';
include BASE_PATH . '/includes/header.php';

$db = getDB();

// ---- Handle POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name        = sanitize($_POST['name'] ?? '');
        $sku         = sanitize($_POST['sku'] ?? generateSKU());
        $category_id = (int)($_POST['category_id'] ?? 0);
        $brand       = sanitize($_POST['brand'] ?? '');
        $price       = (float)($_POST['price'] ?? 0);
        $quantity    = (int)($_POST['quantity'] ?? 0);
        $min_stock   = (int)($_POST['min_stock'] ?? 5);
        $description = sanitize($_POST['description'] ?? '');
        $barcode     = sanitize($_POST['barcode'] ?? '');
        $status      = in_array($_POST['status'] ?? 'active', ['active','inactive']) ? $_POST['status'] : 'active';
        $tools_sel   = $_POST['tools'] ?? [];

        if (empty($name) || $category_id < 1) {
            setFlash('error', 'Product name and category are required.');
        } else {
            // Handle image upload
            $image = null;
            if (!empty($_FILES['image']['tmp_name'])) {
                $image = uploadImage($_FILES['image'], 'product');
            }

            if ($action === 'create') {
                if (empty($sku)) $sku = generateSKU();
                $stmt = $db->prepare("INSERT INTO products (name,sku,category_id,brand,price,quantity,min_stock,description,image,barcode,status) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$name,$sku,$category_id,$brand,$price,$quantity,$min_stock,$description,$image,$barcode,$status]);
                $pid = $db->lastInsertId();
            } else {
                $pid = (int)($_POST['product_id'] ?? 0);
                if ($image) {
                    $stmt = $db->prepare("UPDATE products SET name=?,sku=?,category_id=?,brand=?,price=?,quantity=?,min_stock=?,description=?,image=?,barcode=?,status=? WHERE id=?");
                    $stmt->execute([$name,$sku,$category_id,$brand,$price,$quantity,$min_stock,$description,$image,$barcode,$status,$pid]);
                } else {
                    $stmt = $db->prepare("UPDATE products SET name=?,sku=?,category_id=?,brand=?,price=?,quantity=?,min_stock=?,description=?,barcode=?,status=? WHERE id=?");
                    $stmt->execute([$name,$sku,$category_id,$brand,$price,$quantity,$min_stock,$description,$barcode,$status,$pid]);
                }
                // If quantity changed, log it (simple approach: log as 'in')
            }

            // Update compatibility
            $db->prepare("DELETE FROM product_compatibility WHERE product_id = ?")->execute([$pid]);
            if (!empty($tools_sel)) {
                $ins = $db->prepare("INSERT IGNORE INTO product_compatibility (product_id, tool_id) VALUES (?,?)");
                foreach ($tools_sel as $tid) $ins->execute([$pid, (int)$tid]);
            }

            setFlash('success', 'Product ' . ($action === 'create' ? 'created' : 'updated') . ' successfully.');
            header('Location: products.php');
            exit;
        }
    }

    if ($action === 'delete') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $db->prepare("DELETE FROM products WHERE id = ?")->execute([$pid]);
        setFlash('success', 'Product deleted successfully.');
        header('Location: products.php');
        exit;
    }
}

// ---- Filters ----
$search   = sanitize($_GET['search'] ?? '');
$cat_f    = (int)($_GET['category'] ?? 0);
$tool_f   = (int)($_GET['tool'] ?? 0);
$brand_f  = sanitize($_GET['brand'] ?? '');
$status_f = sanitize($_GET['status'] ?? 'active');

$where = ['1=1'];
$params = [];

if ($search) { $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($cat_f)  { $where[] = 'p.category_id = ?'; $params[] = $cat_f; }
if ($brand_f){ $where[] = 'p.brand = ?'; $params[] = $brand_f; }
if ($status_f && $status_f !== 'all') { $where[] = 'p.status = ?'; $params[] = $status_f; }

$toolJoin = '';
if ($tool_f) {
    $toolJoin = "JOIN product_compatibility pc ON pc.product_id = p.id AND pc.tool_id = ?";
    array_unshift($params, $tool_f);
}

$whereStr = implode(' AND ', $where);
$sql = "SELECT p.*, c.name AS category_name FROM products p
        JOIN categories c ON p.category_id = c.id
        $toolJoin
        WHERE $whereStr ORDER BY p.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Auxiliary data for forms
$categories = $db->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();
$tools      = $db->query("SELECT * FROM tools WHERE status='active' ORDER BY name")->fetchAll();
$brands     = $db->query("SELECT DISTINCT brand FROM products WHERE brand != '' ORDER BY brand")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="page-body">

<!-- Filter Bar -->
<form method="GET" class="filter-bar" id="filterForm">
    <div class="filter-group" style="flex:2;min-width:200px;">
        <i class="fas fa-search" style="color:var(--text-muted);"></i>
        <input type="text" name="search" class="form-control" placeholder="Search by name, SKU, brand..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="filter-group">
        <select name="tool" class="form-control" onchange="document.getElementById('filterForm').submit()">
            <option value="">All Tools</option>
            <?php foreach ($tools as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $tool_f == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <select name="category" class="form-control" onchange="document.getElementById('filterForm').submit()">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $cat_f == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <select name="brand" class="form-control" onchange="document.getElementById('filterForm').submit()">
            <option value="">All Brands</option>
            <?php foreach ($brands as $b): ?>
            <option value="<?= htmlspecialchars($b) ?>" <?= $brand_f === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <select name="status" class="form-control" onchange="document.getElementById('filterForm').submit()">
            <option value="all" <?= $status_f==='all' ? 'selected' : '' ?>>All Status</option>
            <option value="active" <?= $status_f==='active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $status_f==='inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
    <a href="products.php" class="btn btn-outline">Reset</a>
</form>

<!-- Products Table Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-boxes"></i> Products <span style="font-size:0.85rem;color:var(--text-muted);font-weight:400;">(<?= count($products) ?> found)</span></div>
        <button class="btn btn-primary" onclick="openModal('addProductModal')">
            <i class="fas fa-plus"></i> Add Product
        </button>
    </div>

    <?php if (empty($products)): ?>
        <div class="empty-state">
            <i class="fas fa-boxes"></i>
            <h3>No products found</h3>
            <p>Try adjusting your filters or add a new product.</p>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Brand</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Compatibility</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $i => $p):
                // Get compatible tools for this product
                $compSt = $db->prepare("SELECT t.name FROM tools t JOIN product_compatibility pc ON t.id=pc.tool_id WHERE pc.product_id=?");
                $compSt->execute([$p['id']]);
                $compatTools = $compSt->fetchAll(PDO::FETCH_COLUMN);

                $pct = $p['min_stock'] > 0 ? min(100, ($p['quantity'] / $p['min_stock']) * 100) : 100;
                $stockClass = $p['quantity'] == 0 ? 'stock-zero' : ($p['quantity'] <= $p['min_stock'] ? 'stock-low' : 'stock-ok');
            ?>
            <tr>
                <td style="color:var(--text-muted);font-size:0.8rem;"><?= $i+1 ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        <div class="product-thumb">
                            <?php if ($p['image'] && file_exists(UPLOAD_DIR . $p['image'])): ?>
                                <img src="<?= UPLOAD_URL . htmlspecialchars($p['image']) ?>" alt="">
                            <?php else: ?>
                                <i class="fas fa-box"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:0.875rem;"><?= htmlspecialchars($p['name']) ?></div>
                            <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($p['sku']) ?></div>
                        </div>
                    </div>
                </td>
                <td><span class="badge-pill badge-info"><?= htmlspecialchars($p['category_name']) ?></span></td>
                <td style="font-size:0.85rem;"><?= htmlspecialchars($p['brand']) ?: '–' ?></td>
                <td style="font-weight:600;"><?= formatCurrency($p['price']) ?></td>
                <td>
                    <div class="stock-bar-wrap <?= $stockClass ?>">
                        <div class="stock-bar"><div class="stock-bar-fill" style="width:<?= min(100,$pct) ?>%"></div></div>
                        <span class="stock-qty"><?= $p['quantity'] ?> / <?= $p['min_stock'] ?></span>
                    </div>
                </td>
                <td>
                    <?php if (empty($compatTools)): ?>
                        <span style="color:var(--text-muted);font-size:0.78rem;">None</span>
                    <?php else: ?>
                        <?php foreach (array_slice($compatTools, 0, 2) as $ct): ?>
                            <span class="compat-tag"><i class="fas fa-wrench"></i><?= htmlspecialchars($ct) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($compatTools) > 2): ?>
                            <span class="badge-pill badge-gray">+<?= count($compatTools)-2 ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge-pill <?= $p['status']==='active' ? 'badge-success' : 'badge-gray' ?>">
                        <?= ucfirst($p['status']) ?>
                    </span>
                </td>
                <td>
                    <div style="display:flex;gap:0.4rem;">
                        <a href="product_detail.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm btn-icon" data-tooltip="View Detail">
                            <i class="fas fa-eye"></i>
                        </a>
                        <button class="btn btn-outline btn-sm btn-icon"
                            onclick='editProduct(<?= json_encode($p) ?>, <?= json_encode($compatTools) ?>)'
                            data-tooltip="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" style="display:inline;" onsubmit="return confirmDelete('Delete this product?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm btn-icon" data-tooltip="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
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

<!-- ================================================================
     ADD PRODUCT MODAL
================================================================ -->
<div class="modal-overlay" id="addProductModal">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-plus-circle" style="color:var(--primary)"></i> Add New Product</div>
            <button class="modal-close" onclick="closeModal('addProductModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Carbon Brush Set" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" class="form-control" placeholder="Auto-generated if blank">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category_id" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Brand</label>
                        <input type="text" name="brand" class="form-control" placeholder="e.g. Bosch">
                    </div>
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">Price (₹)</label>
                        <input type="number" name="price" class="form-control" placeholder="0.00" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Initial Qty</label>
                        <input type="number" name="quantity" class="form-control" placeholder="0" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Min Stock Alert</label>
                        <input type="number" name="min_stock" class="form-control" placeholder="5" min="0" value="5">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Barcode</label>
                        <input type="text" name="barcode" class="form-control" placeholder="Optional barcode">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Product description..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Product Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                <div class="form-group">
                    <label class="form-label">Compatible Power Tools</label>
                    <div class="check-group" id="addToolsGroup">
                        <?php foreach ($tools as $t): ?>
                        <label class="check-item">
                            <input type="checkbox" name="tools[]" value="<?= $t['id'] ?>">
                            <i class="fas fa-wrench"></i> <?= htmlspecialchars($t['name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addProductModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Product</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================
     EDIT PRODUCT MODAL
================================================================ -->
<div class="modal-overlay" id="editProductModal">
    <div class="modal-box modal-lg">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-edit" style="color:var(--primary)"></i> Edit Product</div>
            <button class="modal-close" onclick="closeModal('editProductModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data" id="editProductForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="product_id" id="editPid">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">SKU</label>
                        <input type="text" name="sku" id="editSku" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category_id" id="editCat" class="form-control" required>
                            <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Brand</label>
                        <input type="text" name="brand" id="editBrand" class="form-control">
                    </div>
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label class="form-label">Price (₹)</label>
                        <input type="number" name="price" id="editPrice" class="form-control" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" id="editQty" class="form-control" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Min Stock Alert</label>
                        <input type="number" name="min_stock" id="editMinStock" class="form-control" min="0">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Barcode</label>
                        <input type="text" name="barcode" id="editBarcode" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="editStatus" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="editDesc" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Replace Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                <div class="form-group">
                    <label class="form-label">Compatible Power Tools</label>
                    <div class="check-group" id="editToolsGroup">
                        <?php foreach ($tools as $t): ?>
                        <label class="check-item" id="editTool_<?= $t['id'] ?>">
                            <input type="checkbox" name="tools[]" value="<?= $t['id'] ?>">
                            <i class="fas fa-wrench"></i> <?= htmlspecialchars($t['name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editProductModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Product</button>
            </div>
        </form>
    </div>
</div>

</div><!-- .page-body -->

<script>
// Checkbox check-item style
document.querySelectorAll('.check-item').forEach(item => {
    const cb = item.querySelector('input[type="checkbox"]');
    if (cb) {
        cb.addEventListener('change', () => {
            item.classList.toggle('checked', cb.checked);
        });
        if (cb.checked) item.classList.add('checked');
    }
});

const allTools = <?= json_encode(array_column($tools, null, 'id')) ?>;

function editProduct(p, compatTools) {
    document.getElementById('editPid').value     = p.id;
    document.getElementById('editName').value    = p.name;
    document.getElementById('editSku').value     = p.sku;
    document.getElementById('editCat').value     = p.category_id;
    document.getElementById('editBrand').value   = p.brand;
    document.getElementById('editPrice').value   = p.price;
    document.getElementById('editQty').value     = p.quantity;
    document.getElementById('editMinStock').value= p.min_stock;
    document.getElementById('editBarcode').value = p.barcode;
    document.getElementById('editDesc').value    = p.description;
    document.getElementById('editStatus').value  = p.status;

    // Reset all checkboxes
    document.querySelectorAll('#editToolsGroup .check-item').forEach(item => {
        const cb = item.querySelector('input');
        cb.checked = false;
        item.classList.remove('checked');
    });
    // Check compatible ones
    compatTools.forEach(toolName => {
        const match = Object.values(allTools).find(t => t.name === toolName);
        if (match) {
            const el = document.getElementById('editTool_' + match.id);
            if (el) {
                el.querySelector('input').checked = true;
                el.classList.add('checked');
            }
        }
    });

    openModal('editProductModal');
}
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
