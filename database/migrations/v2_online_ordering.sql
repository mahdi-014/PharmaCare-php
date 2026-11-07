-- ============================================================================
-- PharmaCare V2 Database Migration: Online Ordering & Customer Role Support
-- Academic Project: CIS324 Web Engineering
-- Engine: MariaDB 10.11+ / MySQL 8.0+ (InnoDB Engine)
-- Character Set: utf8mb4 / utf8mb4_unicode_ci
-- Safety: Non-destructive, idempotent, guarded by precondition validations.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- STEP 1: Version Tracking Table
-- Tracks applied schema migration versions to prevent duplicate execution.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(50) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  description VARCHAR(255) NOT NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- STEP 2: Strict Precondition Checks
-- Validates that the baseline schema is intact, the migration has not already
-- run, and no customer ID 3 / username collision exists in app_users.
-- ----------------------------------------------------------------------------
DELIMITER //

CREATE OR REPLACE PROCEDURE pre_migration_v2_checks()
BEGIN
  -- 1. Ensure migration has not already been executed
  IF EXISTS (SELECT 1 FROM schema_migrations WHERE version = 'v2_online_ordering') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'MIGRATION ABORTED: v2_online_ordering has already been executed on this database.';
  END IF;

  -- 2. Verify all 6 required baseline tables exist
  IF NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'app_users') OR
     NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'categories') OR
     NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'suppliers') OR
     NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'medicines') OR
     NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sales') OR
     NOT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sale_items') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'MIGRATION ABORTED: Missing required baseline tables. Import Phase 0 schema first.';
  END IF;

  -- 3. Collision Protection: Check if ID 3 is occupied by a non-customer account
  IF EXISTS (SELECT 1 FROM app_users WHERE id = 3 AND username <> 'customer') THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'MIGRATION ABORTED: app_users ID 3 is occupied by another account. ID conflict.';
  END IF;

  -- 4. Collision Protection: Check if username 'customer' is registered with a different ID
  IF EXISTS (SELECT 1 FROM app_users WHERE username = 'customer' AND id <> 3) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'MIGRATION ABORTED: Username "customer" exists with an ID other than 3.';
  END IF;
END //

DELIMITER ;

-- Execute validation procedure (will halt execution if any precondition fails)
CALL pre_migration_v2_checks();
DROP PROCEDURE IF EXISTS pre_migration_v2_checks;

-- ----------------------------------------------------------------------------
-- STEP 3: Extend app_users Table
-- Expand role ENUM to include 'CUSTOMER' and add customer profile attributes.
-- Uses standard MariaDB 10.11 syntax without assuming non-portable algorithms.
-- ----------------------------------------------------------------------------
ALTER TABLE app_users 
  MODIFY COLUMN role ENUM('ADMIN', 'STAFF', 'CUSTOMER') NOT NULL DEFAULT 'CUSTOMER',
  ADD COLUMN IF NOT EXISTS email VARCHAR(120) NULL UNIQUE AFTER username,
  ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL AFTER email,
  ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL AFTER phone,
  ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER enabled;

-- ----------------------------------------------------------------------------
-- STEP 4: Create Customer Orders Table
-- Header record for online orders, delivery details, and payment modes.
-- ----------------------------------------------------------------------------
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
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- STEP 5: Create Customer Order Items Table
-- Line items linked to orders and medicine batches with price snapshots.
-- ----------------------------------------------------------------------------
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
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- STEP 6: Create Order Status Audit History Table
-- Historical lifecycle logging for status transitions and staff actions.
-- ----------------------------------------------------------------------------
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
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- STEP 7: Create Prescription Uploads Table
-- Stores metadata and verification states for uploaded prescription files.
-- ----------------------------------------------------------------------------
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
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- STEP 8: Safe Demo Customer Insertion
-- Only inserts if ID 3 does not already exist. Never updates existing rows.
-- Development credentials: customer / customer123 (Bcrypt Cost 12)
-- ----------------------------------------------------------------------------
DELIMITER //

CREATE OR REPLACE PROCEDURE insert_demo_customer_safe()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM app_users WHERE id = 3 OR username = 'customer') THEN
    INSERT INTO app_users (id, full_name, username, email, phone, address, password, role, enabled) 
    VALUES (
      3, 
      'Demo Customer', 
      'customer', 
      'customer@example.com', 
      '01811000001', 
      'House 12, Road 5, Dhanmondi, Dhaka', 
      '$2y$12$6lIKLrqJO6RhAHvDw6hhyOwQ3pI0ioF/Di.K7mhuG8WAUgIpjX6zK', 
      'CUSTOMER', 
      1
    );
  END IF;
END //

DELIMITER ;

CALL insert_demo_customer_safe();
DROP PROCEDURE IF EXISTS insert_demo_customer_safe;

-- ----------------------------------------------------------------------------
-- STEP 9: Record Migration Version
-- Concludes migration and permanently blocks duplicate re-runs.
-- ----------------------------------------------------------------------------
INSERT INTO schema_migrations (version, description)
VALUES ('v2_online_ordering', 'Online medicine ordering, customer role, orders, order_items, order_status_history, prescriptions');
