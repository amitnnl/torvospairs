-- ============================================================
-- TORVO SPAIR — Schema Migration Fix
-- Run this SQL in phpMyAdmin or MySQL CLI after starting XAMPP
-- ============================================================

USE torvo_spair;

-- 1. Fix rfqs table: add missing timestamp columns + fix status ENUM
ALTER TABLE rfqs 
  ADD COLUMN IF NOT EXISTS quoted_at DATETIME NULL AFTER admin_notes,
  ADD COLUMN IF NOT EXISTS accepted_at DATETIME NULL AFTER quoted_at,
  ADD COLUMN IF NOT EXISTS invoiced_at DATETIME NULL AFTER accepted_at;

ALTER TABLE rfqs 
  MODIFY COLUMN status ENUM('submitted','reviewing','quoted','accepted','rejected','invoiced','closed') DEFAULT 'submitted';

-- 2. Fix customers table: add approval/rejection tracking columns
ALTER TABLE customers 
  ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER notes,
  ADD COLUMN IF NOT EXISTS approved_by INT DEFAULT NULL AFTER approved_at,
  ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL AFTER approved_by;

-- 3. Fix stock_logs table: add invoice_id for auto-deduction tracking
ALTER TABLE stock_logs 
  ADD COLUMN IF NOT EXISTS invoice_id INT DEFAULT NULL AFTER notes;

-- 4. Create invoices table (was entirely missing)
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) UNIQUE,
    rfq_id INT NOT NULL,
    customer_id INT NOT NULL,
    subtotal DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    payment_status ENUM('unpaid','paid','partial') DEFAULT 'unpaid',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Create notifications table (was entirely missing)
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    type VARCHAR(50) DEFAULT 'general',
    title VARCHAR(200),
    message TEXT,
    rfq_id INT DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Create orders table (existed only via inline PHP CREATE TABLE)
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) UNIQUE,
    rfq_id INT DEFAULT NULL,
    customer_id INT NOT NULL DEFAULT 0,
    status ENUM('pending','confirmed','processing','dispatched','delivered','cancelled') DEFAULT 'pending',
    subtotal DECIMAL(10,2) DEFAULT 0.00,
    gst_rate DECIMAL(5,2) DEFAULT 18.00,
    gst_amount DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    shipping_address TEXT,
    payment_status ENUM('unpaid','paid','partial') DEFAULT 'unpaid',
    tracking_info VARCHAR(255),
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Create order_items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL DEFAULT 0,
    product_id INT NOT NULL DEFAULT 0,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) DEFAULT 0.00,
    total_price DECIMAL(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Create enquiries table (existed only via inline PHP CREATE TABLE)
CREATE TABLE IF NOT EXISTS enquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    company VARCHAR(150),
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(200),
    message TEXT NOT NULL,
    status ENUM('new','replied','closed') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify all tables exist
SHOW TABLES;
