<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

$email = 'test_partner_' . time() . '@example.com';
echo "1. Registering pending customer with email: $email\n";
$db->prepare("INSERT INTO customers (company_name, contact_name, email, password, status) VALUES (?,?,?,?,?)")
   ->execute(['Test Co', 'John Test', $email, 'hashed_pass', 'pending']);
$cid = $db->lastInsertId();

$check = $db->prepare("SELECT status FROM customers WHERE id = ?");
$check->execute([$cid]);
echo "Status after insert: " . $check->fetchColumn() . "\n";

echo "2. Simulating approval logic (status='active')\n";
$db->prepare("UPDATE customers SET status='active', approved_at=NOW(), approved_by=? WHERE id=?")
   ->execute([1, $cid]);

$check->execute([$cid]);
$status = $check->fetchColumn();
echo "Status after update: " . $status . "\n";

if ($status === 'active') {
    echo "SUCCESS: Status correctly changed to active.\n";
} else {
    echo "FAILURE: Status is still $status.\n";
}

// Cleanup
$db->prepare("DELETE FROM customers WHERE id = ?")->execute([$cid]);
echo "Cleaned up test record.\n";
