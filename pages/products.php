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

    // ---- Add Machine (Quick action) ----
    if ($action === 'add_machine') {
        $pid = (int)($_POST['product_id'] ?? 0);
        $tools_sel = $_POST['tool_ids'] ?? [];
        if ($pid > 0) {
            $db->prepare("DELETE FROM product_compatibility WHERE product_id = ?")->execute([$pid]);
            if (!empty($tools_sel)) {
                $ins = $db->prepare("INSERT IGNORE INTO product_compatibility (product_id, tool_id) VALUES (?,?)");
                foreach ($tools_sel as $tid) $ins->execute([$pid, (int)$tid]);
            }
            setFlash('success', 'Suitable machines updated.');
        }
        header('Location: products.php');
        exit;
    }

    // ---- CSV Import ----
    if ($action === 'csv_import' && !empty($_FILES['csv_file']['tmp_name'])) {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $header = fgetcsv($handle); // skip header row
        $imported = 0; $skipped = 0; $errors = [];

        // Pre-load categories for name matching
        $catMap = [];
        $catRows = $db->query("SELECT id, LOWER(name) AS name FROM categories")->fetchAll();
        foreach ($catRows as $cr) $catMap[$cr['name']] = $cr['id'];

        $ins = $db->prepare("INSERT INTO products (name, sku, category_id, brand, price, quantity, min_stock, description, status) VALUES (?,?,?,?,?,?,?,?,?)");

        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) < 2) continue;

            $pName   = trim($row[0] ?? '');
            $pSku    = trim($row[1] ?? '') ?: generateSKU();
            $pCat    = strtolower(trim($row[2] ?? ''));
            $pBrand  = trim($row[3] ?? '');
            $pPrice  = (float)($row[4] ?? 0);
            $pQty    = (int)($row[5] ?? 0);
            $pMin    = (int)($row[6] ?? 5);
            $pDesc   = trim($row[7] ?? '');

            if (!$pName) { $skipped++; continue; }

            $catId = $catMap[$pCat] ?? null;
            if (!$catId) {
                // Try partial match
                foreach ($catMap as $catName => $cid) {
                    if (strpos($catName, $pCat) !== false || strpos($pCat, $catName) !== false) {
                        $catId = $cid; break;
                    }
                }
            }
            if (!$catId) {
                // Use the first category as fallback
                $catId = reset($catMap) ?: 1;
                $errors[] = "Row '$pName': category '$pCat' not found, used default.";
            }

            try {
                $ins->execute([$pName, $pSku, $catId, $pBrand, $pPrice, $pQty, $pMin, $pDesc, 'active']);
                $imported++;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    $errors[] = "SKU '$pSku' already exists, skipped.";
                    $skipped++;
                } else {
                    $errors[] = "Error importing '$pName': " . $e->getMessage();
                    $skipped++;
                }
            }
        }
        fclose($handle);

        $msg = "Imported <strong>$imported</strong> products.";
        if ($skipped) $msg .= " Skipped: $skipped.";
        if ($errors) $msg .= '<br><small>' . implode('<br>', array_slice($errors, 0, 5)) . '</small>';
        setFlash($imported > 0 ? 'success' : 'error', $msg);
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
        <div style="display:flex;gap:0.5rem;">
            <button class="btn btn-outline" onclick="openModal('importCsvModal')">
                <i class="fas fa-file-upload"></i> CSV Import
            </button>
            <button class="btn btn-primary" onclick="openModal('addProductModal')">
                <i class="fas fa-plus"></i> Add Product
            </button>
        </div>
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
                    <th>Suitable</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $i => $p):
                // Get compatible tools for this product
                $compSt = $db->prepare("SELECT t.id, t.name FROM tools t JOIN product_compatibility pc ON t.id=pc.tool_id WHERE pc.product_id=?");
                $compSt->execute([$p['id']]);
                $compatResult = $compSt->fetchAll();
                $compatTools = array_column($compatResult, 'name');
                $compatToolIds = array_column($compatResult, 'id');

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
                        <span style="color:var(--text-muted);font-size:0.75rem;">None</span>
                    <?php else: ?>
                        <button class="btn btn-outline btn-sm" style="color:var(--primary);border-color:var(--primary);" onclick='viewSuitable(<?= json_encode($p['name']) ?>, <?= json_encode($compatTools) ?>)'>
                            <i class="fas fa-eye"></i> View
                        </button>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge-pill <?= $p['status']==='active' ? 'badge-success' : 'badge-gray' ?>">
                        <?= ucfirst($p['status']) ?>
                    </span>
                </td>
                <td>
                    <div style="display:flex;gap:0.4rem;align-items:center;">
                        <button class="btn btn-outline btn-sm" style="color:#2563eb;border-color:#2563eb;font-weight:600;"
                            onclick='addMachineToProduct(<?= $p['id'] ?>, <?= json_encode($p['name']) ?>, <?= json_encode($compatToolIds) ?>)'
                            data-tooltip="Add Machine">
                            <i class="fas fa-plug"></i> Add Machine
                        </button>
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

<!-- ================================================================
     CSV IMPORT MODAL
================================================================ -->
<div class="modal-overlay" id="importCsvModal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-file-upload" style="color:var(--success)"></i> Import Products from CSV</div>
            <button class="modal-close" onclick="closeModal('importCsvModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="csv_import">
            <div class="modal-body">
                <div style="background:var(--bg-card2);border-radius:var(--radius-md);padding:1rem;margin-bottom:1.25rem;">
                    <div style="font-size:0.82rem;font-weight:700;color:var(--text-primary);margin-bottom:0.5rem;">
                        <i class="fas fa-info-circle" style="color:var(--primary);"></i> CSV File Format
                    </div>
                    <div style="font-size:0.75rem;color:var(--text-muted);line-height:1.6;">
                        Your CSV file should have headers in this order:<br>
                        <code style="background:var(--bg-main);padding:0.2rem 0.5rem;border-radius:4px;font-size:0.72rem;">Name, SKU, Category, Brand, Price, Quantity, Min Stock, Description</code><br>
                        <strong>Name</strong> and <strong>Category</strong> are required. SKU will be auto-generated if blank.
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Select CSV File</label>
                    <div style="position:relative;border:2px dashed var(--border-color);border-radius:12px;padding:2rem;text-align:center;transition:var(--transition);cursor:pointer;"
                         onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border-color)'">
                        <input type="file" name="csv_file" accept=".csv,.txt" required
                               style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;">
                        <i class="fas fa-cloud-upload-alt" style="font-size:2rem;color:var(--primary);margin-bottom:0.75rem;display:block;"></i>
                        <div style="font-weight:600;font-size:0.875rem;color:var(--text-primary);">Click or drag a CSV file</div>
                        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;">Supports .csv and .txt files</div>
                    </div>
                </div>
                <a href="data:text/csv;charset=utf-8,Name,SKU,Category,Brand,Price,Quantity,Min Stock,Description%0ACarbon Brush 6mm,SKU-CB6,Drill Parts,Bosch,120.00,50,10,Replacement carbon brushes%0AGrinding Disc 100mm,,Grinder Accessories,Makita,65.00,100,15,Metal grinding disc" 
                   download="torvo_product_import_template.csv" 
                   style="display:flex;align-items:center;gap:0.5rem;font-size:0.78rem;color:var(--primary);text-decoration:none;margin-top:0.5rem;">
                    <i class="fas fa-download"></i> Download Sample Template
                </a>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('importCsvModal')">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-upload"></i> Import Products</button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================
     VIEW SUITABLE MACHINES MODAL
================================================================ -->
<div class="modal-overlay" id="viewSuitableModal">
    <div class="modal-box" style="max-width:400px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-link" style="color:var(--primary);"></i> Suitable Machines</div>
            <button class="modal-close" onclick="closeModal('viewSuitableModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <h4 id="vsProductName" style="margin-top:0;margin-bottom:1rem;color:var(--text-primary);font-size:0.9rem;"></h4>
            <div id="vsList" style="display:flex;flex-direction:column;gap:0.5rem;max-height:300px;overflow-y:auto;"></div>
        </div>
    </div>
</div>

<!-- ================================================================
     ADD MACHINE MODAL
================================================================ -->
<div class="modal-overlay" id="addMachineModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-plug" style="color:var(--primary);"></i> Add Suitable Machine</div>
            <button class="modal-close" onclick="closeModal('addMachineModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_machine">
            <input type="hidden" name="product_id" id="amProductId">
            <div class="modal-body">
                <h4 id="amProductName" style="margin-top:0;margin-bottom:1rem;color:var(--text-primary);font-size:0.9rem;"></h4>
                <div class="form-group">
                    <label class="form-label">Select Suitable Machines</label>
                    <div class="check-group" id="amToolsGroup" style="max-height:300px;overflow-y:auto;">
                        <?php foreach ($tools as $t): ?>
                        <label class="check-item" id="amTool_<?= $t['id'] ?>">
                            <input type="checkbox" name="tool_ids[]" value="<?= $t['id'] ?>">
                            <i class="fas fa-wrench"></i> <?= htmlspecialchars($t['name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addMachineModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Machines</button>
            </div>
        </form>
    </div>
</div>

<script>
function viewSuitable(productName, tools) {
    document.getElementById('vsProductName').innerText = productName;
    const list = document.getElementById('vsList');
    list.innerHTML = '';
    if (tools.length === 0) {
        list.innerHTML = '<div style="color:var(--text-muted);font-size:0.85rem;">No machines linked.</div>';
    } else {
        tools.forEach(t => {
            list.innerHTML += `<div style="padding:0.6rem;background:var(--bg-main);border-radius:8px;border:1px solid var(--border-color);font-size:0.85rem;display:flex;align-items:center;gap:0.5rem;"><i class="fas fa-wrench" style="color:var(--primary);"></i> ${t}</div>`;
        });
    }
    openModal('viewSuitableModal');
}

function addMachineToProduct(productId, productName, linkedToolIds) {
    document.getElementById('amProductId').value = productId;
    document.getElementById('amProductName').innerText = productName;
    
    // Reset all
    document.querySelectorAll('#amToolsGroup .check-item').forEach(item => {
        item.querySelector('input').checked = false;
        item.classList.remove('checked');
    });
    
    // Check linked ones
    linkedToolIds.forEach(tid => {
        const el = document.getElementById('amTool_' + tid);
        if (el) {
            el.querySelector('input').checked = true;
            el.classList.add('checked');
        }
    });

    openModal('addMachineModal');
}

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
