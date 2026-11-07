-- ============================================================================
-- PharmaCare V2 Database Rollback Script
-- Academic Project: CIS324 Web Engineering
-- Engine: MariaDB 10.11+ / MySQL 8.0+ (InnoDB Engine)
-- Safety: Guards against data loss. Aborts if live business records exist.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- STEP 1: Rollback Safety Verification
-- Aborts rollback if orders, prescriptions, or customer business data exist.
-- ----------------------------------------------------------------------------
DELIMITER //

CREATE OR REPLACE PROCEDURE safe_rollback_v2_checks()
BEGIN
  -- 1. Abort if orders table contains records
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'orders') THEN
    IF (SELECT COUNT(*) FROM orders) > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'ROLLBACK ABORTED: orders table contains customer records. Automated deletion blocked to protect business data.';
    END IF;
  END IF;

  -- 2. Abort if prescriptions table contains records
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'prescriptions') THEN
    IF (SELECT COUNT(*) FROM prescriptions) > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'ROLLBACK ABORTED: prescriptions table contains uploaded records. Automated deletion blocked.';
    END IF;
  END IF;

  -- 3. Abort if customer accounts other than demo ID 3 exist
  IF EXISTS (SELECT 1 FROM app_users WHERE role = 'CUSTOMER' AND id <> 3) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'ROLLBACK ABORTED: Registered customer accounts exist in app_users. Manual data review required.';
  END IF;
END //

DELIMITER ;

-- Execute safety validation
CALL safe_rollback_v2_checks();
DROP PROCEDURE IF EXISTS safe_rollback_v2_checks;

-- ----------------------------------------------------------------------------
-- STEP 2: Drop Online Ordering Tables (Reverse Dependency Order)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS prescriptions;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;

-- ----------------------------------------------------------------------------
-- STEP 3: Remove Demo Customer Account (ID 3 Only)
-- Verifies username is 'customer' to prevent deleting any other account.
-- ----------------------------------------------------------------------------
DELETE FROM app_users WHERE id = 3 AND username = 'customer';

-- ----------------------------------------------------------------------------
-- STEP 4: Revert app_users Table Columns & Role ENUM
-- Restores app_users table definition to baseline state.
-- ----------------------------------------------------------------------------
ALTER TABLE app_users 
  DROP COLUMN IF EXISTS created_at,
  DROP COLUMN IF EXISTS address,
  DROP COLUMN IF EXISTS phone,
  DROP COLUMN IF EXISTS email,
  MODIFY COLUMN role ENUM('ADMIN', 'STAFF') NOT NULL;

-- ----------------------------------------------------------------------------
-- STEP 5: Clear Migration Version Record
-- ----------------------------------------------------------------------------
DELETE FROM schema_migrations WHERE version = 'v2_online_ordering';
