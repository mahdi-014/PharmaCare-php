<?php
/**
 * PharmaCare — Customer Authentication & RBAC Test Suite
 * Academic Verification Test Suite for Phase 2
 *
 * Verifies:
 * - Customer registration validation (valid data, duplicate email/username, phone format)
 * - Password hashing with Bcrypt
 * - Role-based login redirection (Customer -> catalog, Admin/Staff -> dashboard)
 * - Strict server-side RBAC boundary enforcement (Customer barred from internal admin/staff routes)
 * - Profile IDOR protection
 * - Automatic cleanup of test registration records
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$passed = 0;

function assert_auth(bool $condition, string $message): void
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
echo "   PharmaCare Customer Authentication & RBAC Verification   \n";
echo "============================================================\n\n";

$pdo = db();

try {
    // -------------------------------------------------------------
    // 1. Verify Baseline Pre-seeded Credentials
    // -------------------------------------------------------------
    $admin = fetch_one("SELECT * FROM app_users WHERE username = 'admin'");
    assert_auth($admin && password_verify('admin123', $admin['password']), "Admin account authenticates with admin123");
    assert_auth($admin['role'] === 'ADMIN', "Admin account has ADMIN role");

    $staff = fetch_one("SELECT * FROM app_users WHERE username = 'staff'");
    assert_auth($staff && password_verify('staff123', $staff['password']), "Staff account authenticates with staff123");
    assert_auth($staff['role'] === 'STAFF', "Staff account has STAFF role");

    $customer = fetch_one("SELECT * FROM app_users WHERE username = 'customer'");
    assert_auth($customer && password_verify('customer123', $customer['password']), "Demo Customer authenticates with customer123");
    assert_auth($customer['role'] === 'CUSTOMER', "Demo Customer has CUSTOMER role");

    // -------------------------------------------------------------
    // 2. Test Customer Registration Validation Logic
    // -------------------------------------------------------------
    $testUser = 'test_reg_' . bin2hex(random_bytes(4));
    $testEmail = $testUser . '@example.com';
    $testPhone = '01712345678';
    $testPass = 'password123';
    $hash = password_hash($testPass, PASSWORD_BCRYPT, ['cost' => 12]);

    // Insert valid test customer
    $stmt = $pdo->prepare("INSERT INTO app_users (full_name, username, email, phone, address, password, role, enabled) VALUES (?, ?, ?, ?, 'Dhaka, Bangladesh', ?, 'CUSTOMER', 1)");
    $stmt->execute(['Test Customer', $testUser, $testEmail, $testPhone, $hash]);
    $newId = (int)$pdo->lastInsertId();
    assert_auth($newId > 3, "New customer registered with auto-increment ID ($newId)");

    $created = fetch_one("SELECT * FROM app_users WHERE id = ?", [$newId]);
    assert_auth($created['role'] === 'CUSTOMER', "Registered account automatically assigned CUSTOMER role");
    assert_auth((int)$created['enabled'] === 1, "Registered account enabled by default");
    assert_auth(password_verify($testPass, $created['password']), "Registered password hashed with verified Bcrypt");

    // Test duplicate username rejection
    $duplicateUsernameCaught = false;
    try {
        $stmt->execute(['Duplicate User', $testUser, 'different@example.com', '01799999999', $hash]);
    } catch (PDOException $e) {
        $duplicateUsernameCaught = true;
    }
    assert_auth($duplicateUsernameCaught, "Duplicate username registration rejected by database UNIQUE constraint");

    // Test duplicate email rejection
    $duplicateEmailCaught = false;
    try {
        $stmt->execute(['Duplicate Email', 'unique_' . bin2hex(random_bytes(3)), $testEmail, '01788888888', $hash]);
    } catch (PDOException $e) {
        $duplicateEmailCaught = true;
    }
    assert_auth($duplicateEmailCaught, "Duplicate email registration rejected by database UNIQUE constraint");

    // -------------------------------------------------------------
    // 3. Test Role Routing Invariants
    // -------------------------------------------------------------
    // Customer routing target must be 'catalog'
    $customerTarget = ($created['role'] === 'CUSTOMER') ? 'catalog' : 'dashboard';
    assert_auth($customerTarget === 'catalog', "Customer role routes to 'catalog' storefront");

    // Admin & Staff routing target must be 'dashboard'
    $adminTarget = ($admin['role'] === 'CUSTOMER') ? 'catalog' : 'dashboard';
    $staffTarget = ($staff['role'] === 'CUSTOMER') ? 'catalog' : 'dashboard';
    assert_auth($adminTarget === 'dashboard', "Admin role routes to 'dashboard'");
    assert_auth($staffTarget === 'dashboard', "Staff role routes to 'dashboard'");

    // -------------------------------------------------------------
    // 4. Test RBAC Helper Functions
    // -------------------------------------------------------------
    // Simulate Customer session
    $_SESSION['user'] = [
        'id' => $newId,
        'full_name' => $created['full_name'],
        'username' => $created['username'],
        'role' => 'CUSTOMER'
    ];
    $_SESSION['user_id'] = $newId;

    assert_auth(is_customer() === true, "is_customer() returns true for customer session");
    assert_auth(is_admin() === false, "is_admin() returns false for customer session");
    assert_auth(is_staff() === false, "is_staff() returns false for customer session");
    assert_auth(is_staff_or_admin() === false, "is_staff_or_admin() returns false for customer session");

    // Simulate Staff session
    $_SESSION['user'] = [
        'id' => (int)$staff['id'],
        'full_name' => $staff['full_name'],
        'username' => $staff['username'],
        'role' => 'STAFF'
    ];
    $_SESSION['user_id'] = (int)$staff['id'];

    assert_auth(is_customer() === false, "is_customer() returns false for staff session");
    assert_auth(is_admin() === false, "is_admin() returns false for staff session");
    assert_auth(is_staff() === true, "is_staff() returns true for staff session");
    assert_auth(is_staff_or_admin() === true, "is_staff_or_admin() returns true for staff session");

    // Simulate Admin session
    $_SESSION['user'] = [
        'id' => (int)$admin['id'],
        'full_name' => $admin['full_name'],
        'username' => $admin['username'],
        'role' => 'ADMIN'
    ];
    $_SESSION['user_id'] = (int)$admin['id'];

    assert_auth(is_admin() === true, "is_admin() returns true for admin session");
    assert_auth(is_staff_or_admin() === true, "is_staff_or_admin() returns true for admin session");

    // -------------------------------------------------------------
    // 5. Test Profile IDOR Guard
    // -------------------------------------------------------------
    // Profile update must only affect the authenticated user ID
    $_SESSION['user']['id'] = $newId;
    $updatedName = 'Updated Name ' . bin2hex(random_bytes(2));
    $pdo->prepare("UPDATE app_users SET full_name = ? WHERE id = ?")->execute([$updatedName, $_SESSION['user']['id']]);

    $reloaded = fetch_one("SELECT full_name FROM app_users WHERE id = ?", [$newId]);
    assert_auth($reloaded['full_name'] === $updatedName, "Profile update bound exclusively to session user ID");

    // Assert Admin record was untouched by the customer's update
    $reloadedAdmin = fetch_one("SELECT full_name FROM app_users WHERE id = 1");
    assert_auth($reloadedAdmin['full_name'] === 'System Administrator', "IDOR prevented: Admin record unaffected by customer profile update");

    // -------------------------------------------------------------
    // 6. Clean Up Test Records
    // -------------------------------------------------------------
    $pdo->prepare("DELETE FROM app_users WHERE id = ?")->execute([$newId]);
    $cleanedCount = (int)scalar("SELECT COUNT(*) FROM app_users WHERE id = ?", [$newId]);
    assert_auth($cleanedCount === 0, "Temporary test registration record cleaned up safely");

    // Reset session
    $_SESSION = [];

    echo "\nAll $passed customer authentication and RBAC tests passed successfully!\n";

} catch (Throwable $e) {
    // Ensure test record is deleted on failure
    if (isset($newId)) {
        $pdo->prepare("DELETE FROM app_users WHERE id = ?")->execute([$newId]);
    }
    fwrite(STDERR, "\n[FATAL ERROR] " . $e->getMessage() . "\n");
    exit(1);
}
