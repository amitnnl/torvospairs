<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

try {
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    file_put_contents(__DIR__ . '/diag.txt', "Tables: " . implode(', ', $tables) . "\n\n");

    if (in_array('settings', $tables)) {
        $cols = $db->query("DESCRIBE settings")->fetchAll();
        file_put_contents(__DIR__ . '/diag.txt', "Settings Columns:\n" . print_r($cols, true) . "\n", FILE_APPEND);
        
        $data = $db->query("SELECT * FROM settings")->fetchAll();
        file_put_contents(__DIR__ . '/diag.txt', "Settings Data:\n" . print_r($data, true) . "\n", FILE_APPEND);
    } else {
        file_put_contents(__DIR__ . '/diag.txt', "Settings table is MISSING!\n", FILE_APPEND);
    }
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/diag.txt', "Error: " . $e->getMessage(), FILE_APPEND);
}
