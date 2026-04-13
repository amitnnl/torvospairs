<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
echo "--- Schema for 'notifications' ---\n";
try {
    $stmt = $db->query("DESCRIBE notifications");
    print_r($stmt->fetchAll());
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
