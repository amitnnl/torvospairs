<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/database.php';
// Override for CLI
$dsn = "mysql:host=127.0.0.1;dbname=torvo_spair;charset=utf8mb4";
$db = new PDO($dsn, 'root', '');

try {
    // 1. Modify the category enum
    $db->exec("ALTER TABLE settings MODIFY category ENUM('general', 'appearance', 'frontend', 'contact', 'admin', 'system') DEFAULT 'general'");
    echo "Table schema updated.\n";

    // 2. Update existing keys to correct categories
    $updates = [
        'general'    => ["'site_title'", "'b2b_subtitle'"],
        'appearance' => ["'primary_color'", "'primary_color_light'", "'logo_image'"],
        'contact'    => ["'contact_email'", "'contact_phone'", "'whatsapp_number'", "'contact_address'"]
    ];

    foreach ($updates as $cat => $keys) {
        $keyStr = implode(',', $keys);
        $db->exec("UPDATE settings SET category='$cat' WHERE setting_key IN ($keyStr)");
        echo "Category '$cat' updated for keys: $keyStr\n";
    }

    echo "Migration complete!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
