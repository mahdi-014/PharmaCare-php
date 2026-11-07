<?php
/**
 * PharmaCare — Database Connectivity & Relational Integrity Test
 * Academic Verification Test Suite
 *
 * Verifies PDO MySQL connectivity, table schemas, foreign key counts,
 * and pre-seeded record assertions.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$passed = 0;

function assert_test(bool $condition, string $message): void
{
    global $passed;
    if (!$condition) {
        fwrite(STDERR, "[FAIL] $message\n");
        exit(1);
    }
    echo "[PASS] $message\n";
    $passed++;
}

echo "============================================================\n";
echo "   PharmaCare Database & Relational Integrity Verification   \n";
echo "============================================================\n\n";

try {
    $db = db();
    $currentDb = (string)$db->query('SELECT DATABASE()')->fetchColumn();
    assert_test($currentDb === 'pharmacare', "Connected to target database: '$currentDb'");

    // Check all 6 required tables
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $requiredTables = ['app_users', 'categories', 'suppliers', 'medicines', 'sales', 'sale_items'];
    foreach ($requiredTables as $tbl) {
        assert_test(in_array($tbl, $tables, true), "Relational table '$tbl' exists");
    }

    // Verify seeded users
    $users = fetch_all("SELECT id, username, role, enabled FROM app_users ORDER BY id");
    assert_test(count($users) >= 2, "Pre-seeded users verified (count: " . count($users) . ")");
    assert_test($users[0]['username'] === 'admin' && $users[0]['role'] === 'ADMIN', "Administrator account verified");
    assert_test($users[1]['username'] === 'staff' && $users[1]['role'] === 'STAFF', "Staff account verified");

    // Verify categories and suppliers
    $catCount = (int)scalar("SELECT COUNT(*) FROM categories");
    assert_test($catCount >= 5, "Category taxonomy seeded (count: $catCount)");

    $supCount = (int)scalar("SELECT COUNT(*) FROM suppliers");
    assert_test($supCount >= 3, "Supplier directory seeded (count: $supCount)");

    // Verify 120 medicine batches
    $medCount = (int)scalar("SELECT COUNT(*) FROM medicines");
    assert_test($medCount === 120, "120 medicine inventory batches verified");

    // Verify foreign key integrity
    $orphanMedicines = (int)scalar("SELECT COUNT(*) FROM medicines WHERE category_id NOT IN (SELECT id FROM categories) OR supplier_id NOT IN (SELECT id FROM suppliers)");
    assert_test($orphanMedicines === 0, "No orphaned medicine records (foreign key constraints intact)");

    echo "\nAll $passed database integrity verification tests passed successfully!\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\n[FATAL ERROR] " . $e->getMessage() . "\n");
    exit(1);
}

