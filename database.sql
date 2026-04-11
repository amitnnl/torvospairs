-- ============================================
-- TORVO SPAIR - Inventory Management System
-- Database Schema + Sample Data
-- ============================================

CREATE DATABASE IF NOT EXISTS torvo_spair CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE torvo_spair;

-- ============================================
-- TABLE: users
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') DEFAULT 'staff',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- TABLE: categories
-- ============================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- TABLE: tools
-- ============================================
CREATE TABLE IF NOT EXISTS tools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    model VARCHAR(100),
    brand VARCHAR(100),
    description TEXT,
    image VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================
-- TABLE: products
-- ============================================
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    sku VARCHAR(100) UNIQUE,
    category_id INT NOT NULL,
    brand VARCHAR(100),
    price DECIMAL(10,2) DEFAULT 0.00,
    quantity INT DEFAULT 0,
    min_stock INT DEFAULT 5,
    description TEXT,
    image VARCHAR(255),
    barcode VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- TABLE: product_compatibility
-- ============================================
CREATE TABLE IF NOT EXISTS product_compatibility (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    tool_id INT NOT NULL,
    UNIQUE KEY unique_mapping (product_id, tool_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- TABLE: stock_logs
-- ============================================
CREATE TABLE IF NOT EXISTS stock_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    type ENUM('in', 'out') NOT NULL,
    quantity INT NOT NULL,
    previous_stock INT NOT NULL,
    current_stock INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- SAMPLE DATA: users
-- ============================================
INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@torvo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Staff Member', 'staff@torvo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff');
-- Default password for both: password

-- ============================================
-- SAMPLE DATA: categories
-- ============================================
INSERT INTO categories (name, description) VALUES
('Drill Parts', 'Spare parts and accessories for drill machines'),
('Grinder Accessories', 'Discs, wheels and accessories for angle grinders'),
('Cutting Tool Parts', 'Blades and parts for cutting tools'),
('Safety Accessories', 'Protective gear and safety items'),
('Electrical Components', 'Motors, switches, and electrical parts');

-- ============================================
-- SAMPLE DATA: tools
-- ============================================
INSERT INTO tools (name, model, brand, description) VALUES
('Drill Machine', 'DM-500', 'Bosch', 'Heavy duty rotary drill machine for masonry and metal'),
('Angle Grinder', 'AG-100', 'Makita', '100mm angle grinder for cutting and grinding'),
('Marble Cutter', 'MC-180', 'DeWalt', '180mm marble and tile cutting machine'),
('Jigsaw', 'JS-60', 'Black & Decker', 'Variable speed jigsaw for wood and metal cutting'),
('Impact Driver', 'ID-18V', 'Milwaukee', '18V cordless impact driver for heavy fastening');

-- ============================================
-- SAMPLE DATA: products
-- ============================================
INSERT INTO products (name, sku, category_id, brand, price, quantity, min_stock, description, barcode) VALUES
('Carbon Brush Set - 6mm', 'SKU-001', 1, 'Bosch', 120.00, 45, 10, 'Replacement carbon brushes for Bosch drill machines', '8901234560001'),
('Chuck Key 10mm', 'SKU-002', 1, 'Generic', 85.00, 30, 8, 'Chuck key for 10mm drill chuck', '8901234560002'),
('Grinding Disc 100mm', 'SKU-003', 2, 'Makita', 65.00, 80, 15, 'Metal grinding disc 100mm x 6mm x 16mm', '8901234560003'),
('Cutting Disc 100mm', 'SKU-004', 2, 'Makita', 55.00, 120, 20, 'Thin metal cutting disc 100mm x 1mm x 16mm', '8901234560004'),
('Diamond Blade 180mm', 'SKU-005', 3, 'DeWalt', 350.00, 25, 5, 'Premium diamond blade for marble and tile cutting', '8901234560005'),
('Drill Bit Set HSS 13pcs', 'SKU-006', 1, 'Bosch', 450.00, 35, 8, 'High speed steel drill bit set 1-13mm', '8901234560006'),
('Safety Goggles', 'SKU-007', 4, 'Generic', 95.00, 60, 15, 'Anti-fog safety goggles for eye protection', '8901234560007'),
('Armature Coil DM-500', 'SKU-008', 5, 'Bosch', 780.00, 8, 5, 'Replacement armature coil for Bosch DM-500 drill', '8901234560008'),
('Jigsaw Blade Set Wood', 'SKU-009', 3, 'Bosch', 220.00, 40, 10, 'T-shank jigsaw blades for wood cutting, 5pcs', '8901234560009'),
('Impact Driver Bit Set', 'SKU-010', 1, 'Milwaukee', 380.00, 22, 8, '25-piece impact driver bit set S2 steel', '8901234560010'),
('Flap Disc 100mm', 'SKU-011', 2, 'Generic', 75.00, 3, 10, 'Aluminium oxide flap disc for grinding and finishing', '8901234560011'),
('Work Gloves Heavy Duty', 'SKU-012', 4, 'Generic', 150.00, 7, 10, 'Cut-resistant work gloves for power tool operations', '8901234560012');

-- ============================================
-- SAMPLE DATA: product_compatibility
-- ============================================
INSERT INTO product_compatibility (product_id, tool_id) VALUES
(1, 1), -- Carbon Brush -> Drill Machine
(2, 1), -- Chuck Key -> Drill Machine
(3, 2), -- Grinding Disc -> Angle Grinder
(4, 2), -- Cutting Disc -> Angle Grinder
(4, 3), -- Cutting Disc -> Marble Cutter
(5, 3), -- Diamond Blade -> Marble Cutter
(6, 1), -- Drill Bit Set -> Drill Machine
(7, 1), -- Safety Goggles -> Drill Machine
(7, 2), -- Safety Goggles -> Angle Grinder
(7, 3), -- Safety Goggles -> Marble Cutter
(7, 4), -- Safety Goggles -> Jigsaw
(7, 5), -- Safety Goggles -> Impact Driver
(8, 1), -- Armature Coil -> Drill Machine
(9, 4), -- Jigsaw Blade -> Jigsaw
(10, 5),-- Impact Bit -> Impact Driver
(11, 2),-- Flap Disc -> Angle Grinder
(12, 1),-- Work Gloves -> Drill Machine
(12, 2),-- Work Gloves -> Angle Grinder
(12, 3);-- Work Gloves -> Marble Cutter

-- ============================================
-- SAMPLE DATA: stock_logs
-- ============================================
INSERT INTO stock_logs (product_id, user_id, type, quantity, previous_stock, current_stock, notes) VALUES
(1, 1, 'in', 50, 0, 50, 'Initial stock entry'),
(1, 2, 'out', 5, 50, 45, 'Issued to workshop'),
(3, 1, 'in', 100, 0, 100, 'Initial stock entry'),
(3, 2, 'out', 20, 100, 80, 'Monthly workshop supply'),
(11, 1, 'in', 20, 0, 20, 'Initial stock entry'),
(11, 2, 'out', 17, 20, 3, 'Bulk issue for project'),
(12, 1, 'in', 20, 0, 20, 'Initial stock'),
(12, 2, 'out', 13, 20, 7, 'Issued to team members');
