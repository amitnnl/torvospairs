<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/database.php';
requireLogin();
requireAdmin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process Logo Upload
    if (!empty($_FILES['logo_image']['tmp_name'])) {
        $logo = uploadImage($_FILES['logo_image'], 'logo');
        if ($logo) {
            $db->prepare("UPDATE settings SET setting_value=? WHERE setting_key='logo_image'")->execute([$logo]);
        }
    }
    
    // Process Sliding Background Uploads
    $slides = ['hero_slide_1', 'hero_slide_2', 'hero_slide_3'];
    foreach ($slides as $slideKey) {
        if (!empty($_FILES[$slideKey]['tmp_name'])) {
            $uploadedPath = uploadImage($_FILES[$slideKey], 'slider');
            if ($uploadedPath) {
                // Insert or update since we just might have inserted them
                $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, category) VALUES (?, ?, 'frontend') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute([$slideKey, $uploadedPath]);
            }
        }
    }
    
    // Process all other flat POST vars corresponding to settings
    $stmt = $db->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?");
    foreach ($_POST as $k => $v) {
        if ($k === 'action' || empty($k)) continue;
        $stmt->execute([sanitize($v), $k]);
    }
    
    setFlash('success', 'Settings updated successfully.');
    header('Location: settings.php');
    exit;
}

$pageTitle  = 'Site Settings';
$pageIcon   = 'fas fa-cogs';
$activePage = 'settings';
$pageBreadcrumb = 'Site Settings & Customization';
include BASE_PATH . '/includes/header.php';

// Fetch all settings into a structured array
$allSettingsRaw = $db->query("SELECT setting_key, setting_value, category FROM settings")->fetchAll();
$s = [];
foreach ($allSettingsRaw as $row) {
    if (!isset($s[$row['category']])) $s[$row['category']] = [];
    $s[$row['category']][$row['setting_key']] = $row['setting_value'];
}
?>

<div class="page-body">
    <div style="display:flex;gap:1.5rem;align-items:start;flex-wrap:wrap;">
        
        <!-- Sidebar Navigation for Tabs (Frontend Simulation) -->
        <div class="card" style="width:250px;flex-shrink:0;">
            <div class="card-header"><div class="card-title">Categories</div></div>
            <div style="display:flex;flex-direction:column;padding:0.5rem 0;">
                <button type="button" class="tab-btn active" onclick="switchTab('general', this)"><i class="fas fa-globe"></i> General</button>
                <button type="button" class="tab-btn" onclick="switchTab('appearance', this)"><i class="fas fa-paint-brush"></i> Appearance & Logo</button>
                <button type="button" class="tab-btn" onclick="switchTab('frontend', this)"><i class="fas fa-desktop"></i> Portal Hero Panel</button>
                <button type="button" class="tab-btn" onclick="switchTab('contact', this)"><i class="fas fa-address-card"></i> Contact Details</button>
            </div>
        </div>

        <!-- Settings Content -->
        <div style="flex:1;min-width:300px;">
            <form method="POST" enctype="multipart/form-data">
                
                <!-- General Section -->
                <div class="card tab-pane active" id="tab-general">
                    <div class="card-header"><div class="card-title"><i class="fas fa-globe"></i> General Settings</div></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Site Title (Main Name)</label>
                            <input type="text" name="site_title" class="form-control" required value="<?= htmlspecialchars($s['general']['site_title'] ?? '') ?>">
                            <small style="color:var(--text-muted);">E.g. TORVO SPAIR</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">B2B Subtitle</label>
                            <input type="text" name="b2b_subtitle" class="form-control" value="<?= htmlspecialchars($s['general']['b2b_subtitle'] ?? '') ?>">
                            <small style="color:var(--text-muted);">E.g. B2B PORTAL</small>
                        </div>
                    </div>
                </div>

                <!-- Appearance Section -->
                <div class="card tab-pane" id="tab-appearance" style="display:none;">
                    <div class="card-header"><div class="card-title"><i class="fas fa-paint-brush"></i> Appearance & Colors</div></div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Primary Color</label>
                                <div style="display:flex;gap:10px;">
                                    <input type="color" name="primary_color" class="form-control" style="width:60px;padding:2px;" value="<?= htmlspecialchars($s['appearance']['primary_color'] ?? '#2563eb') ?>">
                                    <input type="text" class="form-control" id="hexView1" readonly value="<?= htmlspecialchars($s['appearance']['primary_color'] ?? '#2563eb') ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Primary Color Light</label>
                                <div style="display:flex;gap:10px;">
                                    <input type="color" name="primary_color_light" class="form-control" style="width:60px;padding:2px;" value="<?= htmlspecialchars($s['appearance']['primary_color_light'] ?? '#1d4ed8') ?>">
                                    <input type="text" class="form-control" id="hexView2" readonly value="<?= htmlspecialchars($s['appearance']['primary_color_light'] ?? '#1d4ed8') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Logo Image Upload</label>
                            <div style="display:flex;gap:1rem;align-items:center;">
                                <?php if(!empty($s['appearance']['logo_image'])): ?>
                                <img src="<?= UPLOAD_URL . $s['appearance']['logo_image'] ?>" alt="Logo" style="height:60px;background:var(--bg-gray);border-radius:8px;padding:5px;">
                                <?php endif; ?>
                                <input type="file" name="logo_image" class="form-control" accept="image/*">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Frontend Text Section -->
                <div class="card tab-pane" id="tab-frontend" style="display:none;">
                    <div class="card-header"><div class="card-title"><i class="fas fa-desktop"></i> Portal Landing Page</div></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Hero Title</label>
                            <input type="text" name="hero_title" class="form-control" value="<?= htmlspecialchars($s['frontend']['hero_title'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Hero Subtitle</label>
                            <textarea name="hero_subtitle" class="form-control" rows="3"><?= htmlspecialchars($s['frontend']['hero_subtitle'] ?? '') ?></textarea>
                        </div>
                        <hr style="margin:2rem 0; border:none; border-top:1px solid var(--border);">
                        <h4 style="margin-bottom:1rem; font-size:1rem; color:var(--text-dark);">Sliding Background Images</h4>
                        <div class="form-row" style="grid-template-columns: 1fr 1fr 1fr;">
                            <?php for ($i=1; $i<=3; $i++): $sk = "hero_slide_$i"; ?>
                            <div class="form-group">
                                <label class="form-label">Slide <?= $i ?> Image</label>
                                <?php if(!empty($s['frontend'][$sk])): ?>
                                <img src="<?= UPLOAD_URL . $s['frontend'][$sk] ?>" alt="Slide <?= $i ?>" style="width:100%; height:120px; object-fit:cover; border-radius:8px; margin-bottom:0.8rem; background:var(--bg-gray);">
                                <?php else: ?>
                                <div style="width:100%; height:120px; border-radius:8px; margin-bottom:0.8rem; background:var(--bg-gray); display:flex; align-items:center; justify-content:center; color:var(--text-muted); font-size:0.8rem;">Default Image</div>
                                <?php endif; ?>
                                <input type="file" name="<?= $sk ?>" class="form-control" accept="image/*">
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <!-- Contact Details Section -->
                <div class="card tab-pane" id="tab-contact" style="display:none;">
                    <div class="card-header"><div class="card-title"><i class="fas fa-address-card"></i> Contact Details</div></div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Public Email</label>
                                <input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($s['contact']['contact_email'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Public Phone</label>
                                <input type="text" name="contact_phone" class="form-control" value="<?= htmlspecialchars($s['contact']['contact_phone'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">WhatsApp Number (inc. Country Code)</label>
                            <input type="text" name="whatsapp_number" class="form-control" placeholder="919800000000" value="<?= htmlspecialchars($s['contact']['whatsapp_number'] ?? '') ?>">
                            <small style="color:var(--text-muted);">Numbers only, e.g. 919800000000</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Physical Address</label>
                            <textarea name="contact_address" class="form-control" rows="2"><?= htmlspecialchars($s['contact']['contact_address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div style="margin-top:1.5rem;">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save All Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.tab-btn {
    display:flex; align-items:center; gap:0.75rem; width:100%; text-align:left; padding:0.85rem 1.25rem; font-size:0.9rem; font-weight:600; color:var(--text-medium); border:none; background:transparent; cursor:pointer; border-left:3px solid transparent; transition:all 0.2s;
}
.tab-btn:hover { background:var(--bg-card2); }
.tab-btn.active { background:rgba(37,99,235,0.05); color:var(--primary); border-left-color:var(--primary); }
</style>

<script>
function switchTab(tabId, el) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
    document.getElementById('tab-' + tabId).style.display = 'block';
}

document.querySelector('input[name="primary_color"]').addEventListener('input', function(e) { document.getElementById('hexView1').value = e.target.value; });
document.querySelector('input[name="primary_color_light"]').addEventListener('input', function(e) { document.getElementById('hexView2').value = e.target.value; });
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
