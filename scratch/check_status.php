<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();
echo "--- Schema for 'customers' ---\n";
$stmt = $db->query("DESCRIBE customers");
print_r($stmt->fetchAll());

echo "\n--- Recent Customers ---\n";
$stmt = $db->query("SELECT id, company_name, email, status FROM customers ORDER BY created_at DESC LIMIT 5");
print_r($stmt->fetchAll());
