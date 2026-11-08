-- ========================================================
-- PharmaCare: Pharmacy Management & Online Ordering System
-- CIS324 Web Engineering Semester Project (Topic 13)
-- Normalized MySQL / MariaDB InnoDB Schema & Realistic Dataset
-- ========================================================

CREATE DATABASE IF NOT EXISTS pharmacare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pharmacare;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS prescriptions;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS medicines;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS app_users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. User Accounts (Admin, Staff, Customer)
CREATE TABLE app_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NULL UNIQUE,
    phone VARCHAR(30) NULL,
    address TEXT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('ADMIN', 'STAFF', 'CUSTOMER') NOT NULL DEFAULT 'CUSTOMER',
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Pharmacological Categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Pharmaceutical Suppliers
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    company_name VARCHAR(120) NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(120) NULL,
    address VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. Medicine Inventory & Batches
CREATE TABLE medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    generic_name VARCHAR(120) NOT NULL,
    brand_name VARCHAR(120) NULL,
    batch_number VARCHAR(50) NOT NULL UNIQUE,
    medicine_type ENUM('TABLET', 'CAPSULE', 'SYRUP', 'INJECTION', 'CREAM', 'DROP', 'INHALER', 'OTHER') NOT NULL DEFAULT 'TABLET',
    purchase_price DECIMAL(10,2) NOT NULL,
    selling_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    minimum_stock_level INT NOT NULL DEFAULT 10,
    manufacturing_date DATE NULL,
    expiry_date DATE NOT NULL,
    prescription_required TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    category_id INT NOT NULL,
    supplier_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_medicine_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    CONSTRAINT fk_medicine_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    INDEX idx_medicine_name (name),
    INDEX idx_medicine_generic (generic_name),
    INDEX idx_medicine_expiry (expiry_date)
) ENGINE=InnoDB;

-- 5. Sales Transactions (POS & Online Bridge)
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    sale_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    customer_name VARCHAR(100) NULL,
    customer_phone VARCHAR(30) NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    sold_by_id INT NOT NULL,
    channel VARCHAR(20) NOT NULL DEFAULT 'POS',
    order_id INT NULL,
    CONSTRAINT fk_sale_sold_by FOREIGN KEY (sold_by_id) REFERENCES app_users(id) ON DELETE RESTRICT,
    INDEX idx_sale_date (sale_date),
    INDEX idx_sale_channel (channel),
    INDEX idx_sale_order (order_id)
) ENGINE=InnoDB;

-- 6. Sales Itemized Lines
CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    CONSTRAINT fk_sale_items_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
    INDEX idx_sale_items_sale (sale_id)
) ENGINE=InnoDB;

-- 7. Customer Online Orders
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(30) NOT NULL,
    delivery_address TEXT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    order_status ENUM('PENDING', 'CONFIRMED', 'PROCESSING', 'READY', 'DISPATCHED', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    payment_method ENUM('COD', 'STORE_PICKUP') NOT NULL DEFAULT 'COD',
    payment_status ENUM('UNPAID', 'PAID') NOT NULL DEFAULT 'UNPAID',
    prescription_required TINYINT(1) NOT NULL DEFAULT 0,
    prescription_status ENUM('NOT_REQUIRED', 'PENDING_REVIEW', 'VERIFIED', 'REJECTED') NOT NULL DEFAULT 'NOT_REQUIRED',
    notes TEXT NULL,
    stock_deducted TINYINT(1) NOT NULL DEFAULT 1,
    stock_restored TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_customer FOREIGN KEY (customer_id) REFERENCES app_users(id) ON DELETE RESTRICT,
    INDEX idx_order_customer (customer_id),
    INDEX idx_order_status (order_status),
    INDEX idx_order_created (created_at)
) ENGINE=InnoDB;

-- 8. Order Itemized Lines
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    medicine_id INT NOT NULL,
    medicine_name VARCHAR(100) NOT NULL,
    batch_number VARCHAR(50) NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
    INDEX idx_order_items_order (order_id)
) ENGINE=InnoDB;

-- 9. Order Audit Lifecycle Trail
CREATE TABLE order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    previous_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NOT NULL,
    changed_by_id INT NOT NULL,
    remarks TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_history_user FOREIGN KEY (changed_by_id) REFERENCES app_users(id) ON DELETE RESTRICT,
    INDEX idx_history_order (order_id)
) ENGINE=InnoDB;

-- 10. Uploaded Prescription Documents
CREATE TABLE prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    customer_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    verification_status ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    reviewed_by_id INT NULL,
    review_notes TEXT NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_prescription_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_prescription_customer FOREIGN KEY (customer_id) REFERENCES app_users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_prescription_reviewer FOREIGN KEY (reviewed_by_id) REFERENCES app_users(id) ON DELETE SET NULL,
    INDEX idx_prescription_order (order_id),
    INDEX idx_prescription_status (verification_status)
) ENGINE=InnoDB;

-- ========================================================
-- SEED DATA (Minimal Realistic Academic Dataset)
-- ========================================================

-- Users: Admin (admin123), Staff (staff123), Customer (customer123)
INSERT INTO app_users (id, full_name, username, email, phone, address, password, role, enabled) VALUES
(1, 'System Administrator', 'admin', 'admin@pharmacare.local', '01700000001', 'Dhaka, Bangladesh', '$2y$12$omB/X6xjsvd5nIuWA4M/c.MAo9lZEZpJYN0EBzThgDcQ4n8YaBgf6', 'ADMIN', 1),
(2, 'Dispensary Pharmacist', 'staff', 'staff@pharmacare.local', '01700000002', 'Dhaka, Bangladesh', '$2y$12$vdhnzv.JusCaCBLycG1b6eb4gMWzLLNJGOujf.FU7oZR381rXnerW', 'STAFF', 1),
(3, 'Demo Customer', 'customer', 'customer@example.com', '01811000001', 'House 12, Road 5, Dhanmondi, Dhaka', '$2y$12$6lIKLrqJO6RhAHvDw6hhyOwQ3pI0ioF/Di.K7mhuG8WAUgIpjX6zK', 'CUSTOMER', 1);

-- Categories (5 Types)
INSERT INTO categories (id, name, description) VALUES
(1, 'Antibiotic', 'Bacterial infection treatments requiring strict dosage regimens'),
(2, 'Painkiller', 'Analgesics and antipyretics for fever and pain relief'),
(3, 'Gastric', 'Proton pump inhibitors and antacids for gastrointestinal health'),
(4, 'Vitamin', 'Essential dietary supplements, multivitamins, and minerals'),
(5, 'Allergy', 'Antihistamines for allergic rhinitis, asthma, and skin reactions');

-- Suppliers (3 Major Bangladeshi Manufacturers)
INSERT INTO suppliers (id, name, company_name, phone, email, address) VALUES
(1, 'Rahim Ahmed', 'Square Pharmaceuticals Ltd.', '01711000001', 'orders@squarepharma.com.bd', 'Square Centre, 48 Mohakhali C/A, Dhaka'),
(2, 'Nusrat Jahan', 'Incepta Pharmaceuticals Ltd.', '01711000002', 'contact@inceptapharma.com', '40 Shahid Tajuddin Ahmed Sarani, Tejgaon, Dhaka'),
(3, 'Kamal Hossain', 'Beximco Pharmaceuticals Ltd.', '01711000003', 'info@bpl.net', '19 Dhanmondi R/A, Road 7, Dhaka');

-- 15 Realistic Medicine Batches
-- Covers: In-Stock, Low-Stock (<= 10), Expiring Soon (within 60d), Expired (< CURDATE()), OTC, and Prescription-Required
INSERT INTO medicines (id, name, generic_name, brand_name, batch_number, medicine_type, purchase_price, selling_price, quantity, minimum_stock_level, manufacturing_date, expiry_date, prescription_required, active, category_id, supplier_id) VALUES
(1, 'Napa 500', 'Paracetamol', 'Napa', 'SQ-NP-01', 'TABLET', 1.20, 2.00, 150, 10, DATE_SUB(CURDATE(), INTERVAL 3 MONTH), DATE_ADD(CURDATE(), INTERVAL 12 MONTH), 0, 1, 2, 1),
(2, 'Ace Plus', 'Paracetamol and Caffeine', 'Ace Plus', 'SQ-AP-02', 'TABLET', 2.50, 3.50, 8, 10, DATE_SUB(CURDATE(), INTERVAL 4 MONTH), DATE_ADD(CURDATE(), INTERVAL 8 MONTH), 0, 1, 2, 1),
(3, 'Torax 10', 'Ketorolac Tromethamine', 'Torax', 'SQ-TX-03', 'TABLET', 8.00, 12.00, 45, 10, DATE_SUB(CURDATE(), INTERVAL 5 MONTH), DATE_ADD(CURDATE(), INTERVAL 15 MONTH), 1, 1, 2, 1),
(4, 'Seclo 20', 'Omeprazole', 'Seclo', 'SQ-SC-04', 'CAPSULE', 4.50, 7.00, 120, 10, DATE_SUB(CURDATE(), INTERVAL 6 MONTH), DATE_ADD(CURDATE(), INTERVAL 10 MONTH), 0, 1, 3, 1),
(5, 'Maxpro 20', 'Esomeprazole', 'Maxpro', 'INC-MX-05', 'CAPSULE', 5.50, 8.00, 5, 10, DATE_SUB(CURDATE(), INTERVAL 8 MONTH), DATE_ADD(CURDATE(), INTERVAL 14 MONTH), 0, 1, 3, 2),
(6, 'Antacid Plus', 'Aluminium and Magnesium Hydroxide', 'Antacid Plus', 'BEX-AP-06', 'SYRUP', 45.00, 60.00, 30, 10, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), DATE_ADD(CURDATE(), INTERVAL 18 MONTH), 0, 1, 3, 3),
(7, 'Azithrocin 500', 'Azithromycin', 'Azithrocin', 'SQ-AZ-07', 'TABLET', 28.00, 35.00, 60, 10, DATE_SUB(CURDATE(), INTERVAL 3 MONTH), DATE_ADD(CURDATE(), INTERVAL 18 MONTH), 1, 1, 1, 1),
(8, 'Ciprocin 500', 'Ciprofloxacin', 'Ciprocin', 'SQ-CP-08', 'TABLET', 12.00, 16.00, 40, 10, DATE_SUB(CURDATE(), INTERVAL 6 MONTH), DATE_ADD(CURDATE(), INTERVAL 20 DAY), 1, 1, 1, 1),
(9, 'Moxacil 500', 'Amoxicillin', 'Moxacil', 'INC-MX-09', 'CAPSULE', 8.00, 12.00, 50, 10, DATE_SUB(CURDATE(), INTERVAL 12 MONTH), DATE_SUB(CURDATE(), INTERVAL 15 DAY), 1, 1, 1, 2),
(10, 'Cef-3 200', 'Cefixime', 'Cef-3', 'SQ-CF-10', 'CAPSULE', 22.00, 30.00, 7, 10, DATE_SUB(CURDATE(), INTERVAL 4 MONTH), DATE_ADD(CURDATE(), INTERVAL 16 MONTH), 1, 1, 1, 1),
(11, 'Ceevit 250', 'Vitamin C', 'Ceevit', 'SQ-CV-11', 'TABLET', 1.20, 2.00, 200, 10, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), DATE_ADD(CURDATE(), INTERVAL 14 MONTH), 0, 1, 4, 1),
(12, 'B-50 Forte', 'Vitamin B Complex', 'B-50 Forte', 'SQ-BF-12', 'TABLET', 2.50, 4.00, 0, 10, DATE_SUB(CURDATE(), INTERVAL 5 MONTH), DATE_ADD(CURDATE(), INTERVAL 9 MONTH), 0, 1, 4, 1),
(13, 'Calbo-D', 'Calcium and Vitamin D3', 'Calbo-D', 'SQ-CD-13', 'TABLET', 7.00, 10.00, 90, 10, DATE_SUB(CURDATE(), INTERVAL 3 MONTH), DATE_ADD(CURDATE(), INTERVAL 25 DAY), 0, 1, 4, 1),
(14, 'Alatrol 10', 'Cetirizine', 'Alatrol', 'SQ-AL-14', 'TABLET', 2.00, 3.50, 110, 10, DATE_SUB(CURDATE(), INTERVAL 4 MONTH), DATE_ADD(CURDATE(), INTERVAL 15 MONTH), 0, 1, 5, 1),
(15, 'Montair 10', 'Montelukast', 'Montair', 'INC-MT-15', 'TABLET', 11.00, 16.00, 35, 10, DATE_SUB(CURDATE(), INTERVAL 4 MONTH), DATE_ADD(CURDATE(), INTERVAL 20 MONTH), 1, 1, 5, 2);

-- Sample POS Transaction to initialize sales analytics
INSERT INTO sales (id, invoice_number, sale_date, customer_name, customer_phone, subtotal, discount, total_amount, sold_by_id, channel) VALUES
(1, 'INV-20261109-POS001', DATE_SUB(NOW(), INTERVAL 2 HOUR), 'Walk-in Customer', '01700000000', 50.00, 0.00, 50.00, 2, 'POS');

INSERT INTO sale_items (sale_id, medicine_id, quantity, unit_price, line_total) VALUES
(1, 1, 10, 2.00, 20.00),
(1, 4, 5, 6.00, 30.00);
