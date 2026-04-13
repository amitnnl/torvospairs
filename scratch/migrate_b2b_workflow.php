<?php
/**
 * TORVO SPAIR — B2B Workflow Migration
 * Run once to add all tables and columns needed for:
 * - Partner approval gate
 * - Full RFQ → Quote → Accept → Invoice → Inventory flow
 */

require_once __DIR__ . '/../portal/config/auth.php';
$db = portalDB();

$db->exec("SET FOREIGN_KEY_CHECKS=0");
$migrations = [];

// ── 1. Add approval columns to customers ──────────────────────────
$cols = $db->query("SHOW COLUMNS FROM customers")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('rejection_reason', $cols)) {
    $db->exec("ALTER TABLE customers ADD COLUMN rejection_reason VARCHAR(500) NULL AFTER notes");
    $migrations[] = "✅ Added customers.rejection_reason";
}
if (!in_array('approved_at', $cols)) {
    $db->exec("ALTER TABLE customers ADD COLUMN approved_at DATETIME NULL AFTER rejection_reason");
    $migrations[] = "✅ Added customers.approved_at";
}
if (!in_array('approved_by', $cols)) {
    $db->exec("ALTER TABLE customers ADD COLUMN approved_by INT NULL AFTER approved_at");
    $migrations[] = "✅ Added customers.approved_by";
}

// ── 2. Enhance rfqs table ──────────────────────────────────────────
$rfqCols = $db->query("SHOW COLUMNS FROM rfqs")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('quoted_at', $rfqCols)) {
    $db->exec("ALTER TABLE rfqs ADD COLUMN quoted_at DATETIME NULL");
    $migrations[] = "✅ Added rfqs.quoted_at";
}
if (!in_array('accepted_at', $rfqCols)) {
    $db->exec("ALTER TABLE rfqs ADD COLUMN accepted_at DATETIME NULL");
    $migrations[] = "✅ Added rfqs.accepted_at";
}
if (!in_array('invoiced_at', $rfqCols)) {
    $db->exec("ALTER TABLE rfqs ADD COLUMN invoiced_at DATETIME NULL");
    $migrations[] = "✅ Added rfqs.invoiced_at";
}
// Make sure status includes 'invoiced'
$db->exec("ALTER TABLE rfqs MODIFY COLUMN status ENUM('submitted','reviewing','quoted','accepted','rejected','invoiced','closed') DEFAULT 'submitted'");
$migrations[] = "✅ Updated rfqs.status ENUM";

// ── 3. Enhance rfq_items: add quoted_price ─────────────────────────
$riCols = $db->query("SHOW COLUMNS FROM rfq_items")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('unit_price', $riCols)) {
    $db->exec("ALTER TABLE rfq_items ADD COLUMN unit_price DECIMAL(10,2) DEFAULT NULL");
    $migrations[] = "✅ Added rfq_items.unit_price";
}

// ── 4. Create invoices table ───────────────────────────────────────
$db->exec("
CREATE TABLE IF NOT EXISTS invoices (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number  VARCHAR(30) NOT NULL UNIQUE,
    rfq_id          INT NOT NULL,
    customer_id     INT NOT NULL,
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_status  ENUM('unpaid','paid') DEFAULT 'unpaid',
    notes           TEXT NULL,
    created_at      DATETIME DEFAULT NOW(),
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
$migrations[] = "✅ Created invoices table";

// ── 5. Create notifications table ─────────────────────────────────
$db->exec("
CREATE TABLE IF NOT EXISTS notifications (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    type        ENUM('rfq_quoted','rfq_invoiced','rfq_rejected','general') DEFAULT 'general',
    title       VARCHAR(200) NOT NULL,
    message     TEXT NOT NULL,
    rfq_id      INT NULL,
    is_read     TINYINT(1) DEFAULT 0,
    created_at  DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
$migrations[] = "✅ Created notifications table";

// ── 6. Extend stock_logs to support invoice source ─────────────────
$slCols = $db->query("SHOW COLUMNS FROM stock_logs")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('invoice_id', $slCols)) {
    $db->exec("ALTER TABLE stock_logs MODIFY COLUMN user_id INT NOT NULL DEFAULT 0");
    $db->exec("ALTER TABLE stock_logs ADD COLUMN invoice_id INT NULL AFTER notes");
    $migrations[] = "✅ Added stock_logs.invoice_id";
}

$db->exec("SET FOREIGN_KEY_CHECKS=1");

echo "<h2>Migration Results</h2><ul>";
foreach ($migrations as $m) echo "<li>$m</li>";
echo "</ul><p><strong>Done. <a href='../portal/home.php'>Go to Portal</a></strong></p>";
