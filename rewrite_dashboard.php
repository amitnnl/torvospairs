<?php
$file = 'C:/xampp/htdocs/public_html/dashboard.php';
$html = file_get_contents($file);

// 1. Inject db.php include and POST handling logic at the very top
$logic = <<<PHP
<?php
session_start();
require 'db.php';

if (!isset(\$_SESSION['login']) || \$_SESSION['login'] != 'yes') {
    header("Location: index.php");
    exit;
}

// Helper to handle lookup insertion
function getOrInsert(\$conn, \$table, \$val_id, \$val_new) {
    if (\$val_id === '+ Add New' && !empty(\$val_new)) {
        \$stmt = \$conn->prepare("INSERT INTO `\$table` (name) VALUES (?)");
        \$stmt->execute([\$val_new]);
        return \$stmt->insert_id;
    }
    return (int)\$val_id;
}

// POST Handler
if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
    \$action = \$_POST['action'] ?? '';
    
    if (\$action === 'save_part_and_machine') {
        // Spare Part
        \$pb = getOrInsert(\$conn, 'spare_part_brands', \$_POST['sp_brand'] ?? '', \$_POST['new_sp_brand'] ?? '');
        \$pn = getOrInsert(\$conn, 'spare_part_names', \$_POST['sp_name'] ?? '', \$_POST['new_sp_name'] ?? '');
        \$pm = getOrInsert(\$conn, 'spare_part_models', \$_POST['sp_model'] ?? '', \$_POST['new_sp_model'] ?? '');
        \$cost = (float)(\$_POST['sp_cost'] ?? 0);
        \$note = \$_POST['sp_note'] ?? '';
        
        \$photo = '';
        if (!empty(\$_FILES['sp_photo']['name'])) {
            \$ext = pathinfo(\$_FILES['sp_photo']['name'], PATHINFO_EXTENSION);
            \$photo = time() . '_' . rand(100,999) . '.' . \$ext;
            move_uploaded_file(\$_FILES['sp_photo']['tmp_name'], 'uploads/' . \$photo);
        }
        
        \$stmt = \$conn->prepare("INSERT INTO spare_parts (spare_part_brand_id, spare_part_name_id, spare_part_model_id, cost, note, photo, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        \$stmt->execute([\$pb, \$pn, \$pm, \$cost, \$note, \$photo]);
        \$part_id = \$stmt->insert_id;
        
        // Fitment (if provided)
        if (isset(\$_POST['mb']) && is_array(\$_POST['mb'])) {
            \$fitment_stmt = \$conn->prepare("INSERT INTO spare_part_fitments (spare_part_id, machine_brand_id, machine_name_id, machine_model_id, machine_size_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            foreach (\$_POST['mb'] as \$k => \$mb_val) {
                // If it was 'abhipatanahi', skip
                if (!isset(\$_POST['m_unknown']) || \$_POST['m_unknown'] != '1') {
                    \$mb = getOrInsert(\$conn, 'machine_brands', \$mb_val, \$_POST['new_mb'][\$k] ?? '');
                    \$mn = getOrInsert(\$conn, 'machine_names', \$_POST['mn'][\$k] ?? '', \$_POST['new_mn'][\$k] ?? '');
                    \$mm = getOrInsert(\$conn, 'machine_models', \$_POST['mm'][\$k] ?? '', \$_POST['new_mm'][\$k] ?? '');
                    \$ms = getOrInsert(\$conn, 'machine_sizes', \$_POST['ms'][\$k] ?? '', \$_POST['new_ms'][\$k] ?? '');
                    if (\$mb || \$mn || \$mm) {
                        \$fitment_stmt->execute([\$part_id, \$mb, \$mn, \$mm, \$ms]);
                    }
                }
            }
        }
        header('Location: dashboard.php?success=1');
        exit;
    }
}

// Fetch lookups
\$sp_brands = \$conn->query("SELECT * FROM spare_part_brands ORDER BY name")->fetch_all(MYSQLI_ASSOC);
\$sp_names = \$conn->query("SELECT * FROM spare_part_names ORDER BY name")->fetch_all(MYSQLI_ASSOC);
\$sp_models = \$conn->query("SELECT * FROM spare_part_models ORDER BY name")->fetch_all(MYSQLI_ASSOC);
\$m_brands = \$conn->query("SELECT * FROM machine_brands ORDER BY name")->fetch_all(MYSQLI_ASSOC);
\$m_names = \$conn->query("SELECT * FROM machine_names ORDER BY name")->fetch_all(MYSQLI_ASSOC);
\$m_models = \$conn->query("SELECT * FROM machine_models ORDER BY name")->fetch_all(MYSQLI_ASSOC);
\$m_sizes = \$conn->query("SELECT * FROM machine_sizes ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Fetch Data
\$parts = \$conn->query("
    SELECT sp.*, 
       b.name as brand, n.name as name, m.name as model
    FROM spare_parts sp
    LEFT JOIN spare_part_brands b ON sp.spare_part_brand_id = b.id
    LEFT JOIN spare_part_names n ON sp.spare_part_name_id = n.id
    LEFT JOIN spare_part_models m ON sp.spare_part_model_id = m.id
    ORDER BY sp.id DESC
")->fetch_all(MYSQLI_ASSOC);

function buildOptions(\$arr) {
    \$out = "<option value=''>Select</option><option value='+ Add New'>+ Add New</option>";
    foreach (\$arr as \$r) \$out .= "<option value='{\$r['id']}'>".htmlspecialchars(\$r['name'])."</option>";
    return \$out;
}
?>
<!DOCTYPE html>
PHP;

// Remove the hardcoded top php block from existing HTML
$html = preg_replace('/<\?php\s+session_start\(\);.*?<!DOCTYPE html>/is', $logic, $html, 1);

// 2. Wrap the forms.
// We'll wrap the inner contents of `addFormPanel` with a <form> and add names to the inputs.
$addFormStart = '<form method="POST" action="dashboard.php" enctype="multipart/form-data"><input type="hidden" name="action" value="save_part_and_machine">';
$html = str_replace('<h2>Add Spare Part</h2>', '<h2>Add Spare Part</h2>' . $addFormStart, $html);

// Close form after fitment box
$html = str_replace('</div>
    </div>

    <div class="view-layout"', '</div></form></div>
    </div>

    <div class="view-layout"', $html);

// Now apply names and dynamic options to Spare Part selects
$replacements = [
    '<select onchange="toggleNewBox(this, \'newBrandBox\')">
                    <option>Select</option>
                    <option>+ Add New</option>
                </select>' => '<select name="sp_brand" required onchange="toggleNewBox(this, \'newBrandBox\')"><?= buildOptions($sp_brands) ?></select>',
    
    '<input type="text" placeholder="Enter New Spare Part Brand">' => '<input type="text" name="new_sp_brand" placeholder="Enter New Spare Part Brand">',
    
    // Skip main machine name for the part, as in logic it belongs to part... wait, the reference form has "Machine Name" in the main panel too? Yes, but it belongs to Fitment inherently. Let's name it unused for now, or link it.
    '<select onchange="toggleNewBox(this, \'newMainMachineNameBox\')">
                    <option>Select</option>
                    <option>+ Add New</option>
                </select>' => '<select name="main_mn" onchange="toggleNewBox(this, \'newMainMachineNameBox\')"><?= buildOptions($m_names) ?></select>',
                
    '<input type="text" placeholder="Enter Machine Name">' => '<input type="text" name="new_main_mn" placeholder="Enter Machine Name">',

    '<select onchange="toggleNewBox(this, \'newPartNameBox\')">
                    <option>Select</option>
                    <option>+ Add New</option>
                </select>' => '<select name="sp_name" required onchange="toggleNewBox(this, \'newPartNameBox\')"><?= buildOptions($sp_names) ?></select>',
                
    '<input type="text" placeholder="Enter New Spare Part Name">' => '<input type="text" name="new_sp_name" placeholder="Enter New Spare Part Name">',

    '<select onchange="toggleNewBox(this, \'newPartModelBox\')">
                    <option>Select</option>
                    <option>+ Add New</option>
                </select>' => '<select name="sp_model" required onchange="toggleNewBox(this, \'newPartModelBox\')"><?= buildOptions($sp_models) ?></select>',
                
    '<input type="text" placeholder="Enter New Spare Part Model">' => '<input type="text" name="new_sp_model" placeholder="Enter New Spare Part Model">',

    '<input type="number" min="0" placeholder="Enter Cost">' => '<input type="number" name="sp_cost" min="0" placeholder="Enter Cost" required>',
    '<input type="text" placeholder="Enter Note">'           => '<input type="text" name="sp_note" placeholder="Enter Note">',
    '<input type="file">'                                   => '<input type="file" name="sp_photo" accept="image/*">',

    // Fitments
    '<input type="checkbox" id="unknownMachine">' => '<input type="checkbox" id="unknownMachine" name="m_unknown" value="1">',
    
    '<select onchange="toggleNewBox(this, \'newMachineBrandBox1\')">
                        <option>Select</option>
                        <option>+ Add New</option>
                    </select>' => '<select name="mb[0]" onchange="toggleNewBox(this, \'newMachineBrandBox1\')"><?= buildOptions($m_brands) ?></select>',
    '<input type="text" placeholder="Enter New Machine Brand">' => '<input type="text" name="new_mb[0]" placeholder="Enter New Machine Brand">',

    '<select onchange="toggleNewBox(this, \'newMachineNameBox1\')">
                        <option>Select</option>
                        <option>+ Add New</option>
                    </select>' => '<select name="mn[0]" onchange="toggleNewBox(this, \'newMachineNameBox1\')"><?= buildOptions($m_names) ?></select>',
    '<input type="text" placeholder="Enter New Machine Name">' => '<input type="text" name="new_mn[0]" placeholder="Enter New Machine Name">',

    '<select onchange="toggleNewBox(this, \'newMachineModelBox1\')">
                        <option>Select</option>
                        <option>+ Add New</option>
                    </select>' => '<select name="mm[0]" onchange="toggleNewBox(this, \'newMachineModelBox1\')"><?= buildOptions($m_models) ?></select>',
    '<input type="text" placeholder="Enter New Machine Model">' => '<input type="text" name="new_mm[0]" placeholder="Enter New Machine Model">',

    '<select onchange="toggleNewBox(this, \'newMachineSizeBox1\')">
                        <option>Select</option>
                        <option>+ Add New</option>
                    </select>' => '<select name="ms[0]" onchange="toggleNewBox(this, \'newMachineSizeBox1\')"><?= buildOptions($m_sizes) ?></select>',
    '<input type="text" placeholder="Enter New Machine Size">' => '<input type="text" name="new_ms[0]" placeholder="Enter New Machine Size">',

    '<button type="button" class="save-btn">Save Suitable Machine</button>' => '<button type="submit" class="save-btn">Save Suitable Machine</button>'
];

$html = str_replace(array_keys($replacements), array_values($replacements), $html);

// 3. Render the View Data table!
$tableTarget = <<<HTML
                        <tr>
                            <td>
                                <img src="https://via.placeholder.com/70x70?text=Photo" alt="Photo" class="photo-thumb">
                            </td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                            <td>
                                <a href="#" class="action-btn view-btn">View</a>
                            </td>
                            <td class="action-line">
                                <a href="#" class="action-btn edit-btn">Edit</a>
                                <a href="#" class="action-btn delete-btn">Delete</a>
                                <a href="#" class="action-btn photo-btn">Photo</a>
                                <a href="#" class="action-btn machine-btn">Add Machine</a>
                            </td>
                        </tr>
HTML;

$tableReplacement = <<<HTML
                        <?php if(empty(\$parts)): ?>
                        <tr><td colspan="8" style="text-align:center;">No spare parts added yet.</td></tr>
                        <?php else: ?>
                        <?php foreach (\$parts as \$p): ?>
                        <tr>
                            <td>
                                <?php if(\$p['photo']): ?>
                                <img src="uploads/<?= htmlspecialchars(\$p['photo']) ?>" alt="Photo" class="photo-thumb">
                                <?php else: ?>
                                <img src="https://via.placeholder.com/70x70?text=Photo" alt="Photo" class="photo-thumb">
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(\$p['brand'] ?? '-') ?></td>
                            <td><?= htmlspecialchars(\$p['name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars(\$p['model'] ?? '-') ?></td>
                            <td><?= htmlspecialchars(\$p['cost']) ?></td>
                            <td><?= htmlspecialchars(\$p['note'] ?: '-') ?></td>
                            <td>
                                <button type="button" class="action-btn view-btn" style="border:none;cursor:pointer;">View</button>
                            </td>
                            <td class="action-line">
                                <button type="button" class="action-btn edit-btn" style="border:none;cursor:pointer;">Edit</button>
                                <button type="button" class="action-btn delete-btn" style="border:none;cursor:pointer;">Delete</button>
                                <button type="button" class="action-btn machine-btn" style="border:none;cursor:pointer;">Add Machine</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
HTML;

$html = str_replace($tableTarget, $tableReplacement, $html);

// Create uploads dir
if (!is_dir('C:/xampp/htdocs/public_html/uploads')) {
    mkdir('C:/xampp/htdocs/public_html/uploads', 0777, true);
}

file_put_contents($file, $html);
echo "Rewritten public_html/dashboard.php successfully!";
?>
