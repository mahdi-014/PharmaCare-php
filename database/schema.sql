-- PharmaCare MySQL 8 / MariaDB 10+ schema; import pharmacare.sql for schema plus seed data.
CREATE DATABASE IF NOT EXISTS pharmacare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pharmacare;

CREATE TABLE IF NOT EXISTS app_users (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(80) NOT NULL,
 username VARCHAR(50) NOT NULL UNIQUE,
 email VARCHAR(120) NULL UNIQUE,
 phone VARCHAR(30) NULL,
 address VARCHAR(255) NULL,
 password VARCHAR(255) NOT NULL,
 role ENUM('ADMIN','STAFF','CUSTOMER') NOT NULL DEFAULT 'CUSTOMER',
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(80) NOT NULL UNIQUE,
 description VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS suppliers (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 company_name VARCHAR(120) NULL,
 phone VARCHAR(30) NOT NULL,
 email VARCHAR(120) NULL,
 address VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS medicines (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 generic_name VARCHAR(120) NOT NULL,
 brand_name VARCHAR(120) NULL,
 batch_number VARCHAR(60) NOT NULL UNIQUE,
 medicine_type ENUM('TABLET','CAPSULE','SYRUP','INJECTION','CREAM','DROP','INHALER','OTHER') NOT NULL,
 purchase_price DECIMAL(12,2) NOT NULL,
 selling_price DECIMAL(12,2) NOT NULL,
 quantity INT UNSIGNED NOT NULL DEFAULT 0,
 minimum_stock_level INT UNSIGNED NOT NULL DEFAULT 10,
 manufacturing_date DATE NULL,
 expiry_date DATE NOT NULL,
 prescription_required TINYINT(1) NOT NULL DEFAULT 0,
 active TINYINT(1) NOT NULL DEFAULT 1,
 category_id BIGINT UNSIGNED NOT NULL,
 supplier_id BIGINT UNSIGNED NOT NULL,
 CONSTRAINT fk_medicine_category FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE RESTRICT,
 CONSTRAINT fk_medicine_supplier FOREIGN KEY(supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
 INDEX idx_medicine_name (name),
 INDEX idx_medicine_expiry (expiry_date),
 INDEX idx_medicine_category (category_id),
 INDEX idx_medicine_supplier (supplier_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sales (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 invoice_number VARCHAR(40) NOT NULL UNIQUE,
 sale_date DATETIME NOT NULL,
 customer_name VARCHAR(100) NULL,
 customer_phone VARCHAR(30) NULL,
 subtotal DECIMAL(12,2) NOT NULL,
 discount DECIMAL(12,2) NOT NULL DEFAULT 0,
 total_amount DECIMAL(12,2) NOT NULL,
 sold_by_id BIGINT UNSIGNED NOT NULL,
 channel VARCHAR(20) NOT NULL DEFAULT 'POS',
 order_id BIGINT UNSIGNED NULL,
 CONSTRAINT fk_sale_user FOREIGN KEY(sold_by_id) REFERENCES app_users(id),
 CONSTRAINT fk_sale_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE SET NULL,
 INDEX idx_sale_date (sale_date),
 INDEX idx_sale_channel (channel),
 INDEX idx_sale_order (order_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sale_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 sale_id BIGINT UNSIGNED NOT NULL,
 medicine_id BIGINT UNSIGNED NOT NULL,
 quantity INT UNSIGNED NOT NULL,
 unit_price DECIMAL(12,2) NOT NULL,
 line_total DECIMAL(12,2) NOT NULL,
 CONSTRAINT fk_item_sale FOREIGN KEY(sale_id) REFERENCES sales(id) ON DELETE CASCADE,
 CONSTRAINT fk_item_medicine FOREIGN KEY(medicine_id) REFERENCES medicines(id),
 INDEX idx_item_sale (sale_id),
 INDEX idx_item_medicine (medicine_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 order_number VARCHAR(40) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NOT NULL,
 customer_name VARCHAR(100) NOT NULL,
 customer_phone VARCHAR(30) NOT NULL,
 delivery_address TEXT NOT NULL,
 subtotal DECIMAL(12,2) NOT NULL,
 shipping_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
 total_amount DECIMAL(12,2) NOT NULL,
 order_status ENUM('PENDING','CONFIRMED','PROCESSING','READY','DISPATCHED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING',
 payment_method ENUM('COD','STORE_PICKUP') NOT NULL DEFAULT 'COD',
 payment_status ENUM('UNPAID','PAID') NOT NULL DEFAULT 'UNPAID',
 prescription_required TINYINT(1) NOT NULL DEFAULT 0,
 prescription_status ENUM('NOT_REQUIRED','PENDING_REVIEW','VERIFIED','REJECTED') NOT NULL DEFAULT 'NOT_REQUIRED',
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

CREATE TABLE IF NOT EXISTS order_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 medicine_id BIGINT UNSIGNED NOT NULL,
 quantity INT UNSIGNED NOT NULL,
 unit_price DECIMAL(12,2) NOT NULL,
 line_total DECIMAL(12,2) NOT NULL,
 CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_order_items_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT,
 INDEX idx_order_items_order (order_id),
 INDEX idx_order_items_medicine (medicine_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_status_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 previous_status VARCHAR(30) NULL,
 new_status VARCHAR(30) NOT NULL,
 changed_by_id BIGINT UNSIGNED NOT NULL,
 remarks TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_history_user FOREIGN KEY (changed_by_id) REFERENCES app_users(id) ON DELETE RESTRICT,
 INDEX idx_history_order (order_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS prescriptions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 customer_id BIGINT UNSIGNED NOT NULL,
 file_path VARCHAR(255) NOT NULL,
 original_filename VARCHAR(255) NOT NULL,
 file_size INT UNSIGNED NOT NULL,
 mime_type VARCHAR(100) NOT NULL,
 verification_status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
 reviewed_by_id BIGINT UNSIGNED NULL,
 review_notes TEXT NULL,
 reviewed_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_prescription_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_prescription_customer FOREIGN KEY (customer_id) REFERENCES app_users(id) ON DELETE RESTRICT,
 CONSTRAINT fk_prescription_reviewer FOREIGN KEY (reviewed_by_id) REFERENCES app_users(id) ON DELETE SET NULL,
 INDEX idx_prescription_order (order_id),
 INDEX idx_prescription_customer (customer_id),
 INDEX idx_prescription_status (verification_status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS schema_migrations (
 version VARCHAR(50) NOT NULL PRIMARY KEY,
 applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 description VARCHAR(255) NOT NULL
) ENGINE=InnoDB;
