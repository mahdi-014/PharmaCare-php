-- PharmaCare MySQL 8 / MariaDB 10+ schema; import pharmacare.sql for schema plus seed data.
CREATE DATABASE IF NOT EXISTS pharmacare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pharmacare;

CREATE TABLE IF NOT EXISTS app_users (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(80) NOT NULL,
 username VARCHAR(50) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL,
 role ENUM('ADMIN','STAFF') NOT NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 1
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
 CONSTRAINT fk_sale_user FOREIGN KEY(sold_by_id) REFERENCES app_users(id),
 INDEX idx_sale_date (sale_date)
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
