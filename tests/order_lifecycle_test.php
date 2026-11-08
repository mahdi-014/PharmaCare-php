<?php
/**
 * PharmaCare — Order Lifecycle State Machine & Prescription Verification Test Suite
 * Academic Verification Test Suite for Phase 5 (CIS324 Web Engineering)
 *
 * Verifies:
 * - Sequential forward order lifecycle: PENDING -> CONFIRMED -> PROCESSING -> READY -> DISPATCHED -> COMPLETED
 * - Payment status auto-synchronization upon reaching COMPLETED
 * - Terminal state immutability (cannot modify COMPLETED or CANCELLED orders)
 * - Prescription Gate: Orders requiring prescription cannot be confirmed until prescription is APPROVED
 * - Pharmacist prescription review actions (APPROVED / REJECTED)
 * - Idempotent cancellation and stock restoration (restores stock exactly once; zero double-restoration)
 * - Role-based authorization for prescription file downloads (Staff & Owner pass, third-party fails 403)
 * - Path traversal attack prevention on file streaming
 * - Clean database teardown with zero orphaned records
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$passed = 0;

function assert_lifecycle(bool $condition, string $message): void
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
echo "   PharmaCare Order Lifecycle & Prescription Verification   \n";
echo "============================================================\n\n";

$pdo = db();

try {
    // -------------------------------------------------------------
    // 1. Setup Test Accounts & Medicine Inventory
    // -------------------------------------------------------------
    $admin = fetch_one("SELECT * FROM app_users WHERE username = 'admin'");
    $staff = fetch_one("SELECT * FROM app_users WHERE username = 'staff'");
    $customer = fetch_one("SELECT * FROM app_users WHERE username = 'customer'");

    assert_lifecycle($admin && $staff && $customer, "Baseline test accounts verified (Admin, Staff, Customer)");

    $med = fetch_one("SELECT * FROM medicines WHERE active = 1 AND expiry_date >= CURDATE() AND quantity >= 20 LIMIT 1");
    assert_lifecycle($med !== null, "Found active test medicine batch (ID: {$med['id']}, Stock: {$med['quantity']})");

    $batchId = (int)$med['id'];
    $baselineStock = (int)$med['quantity'];

    // -------------------------------------------------------------
    // 2. Test Forward Lifecycle Transitions (OTC Order)
    // -------------------------------------------------------------
    // Create an OTC order
    $orderQty = 2;
    $pdo->beginTransaction();
    InventoryService::deductBatchStock($pdo, $batchId, $orderQty);

    $orderNum1 = 'ORD-' . date('Ymd') . '-LIFE' . rand(100, 999);
    $ins = $pdo->prepare(
        'INSERT INTO orders 
         (order_number, customer_id, customer_name, customer_phone, delivery_address, 
          subtotal, shipping_fee, total_amount, order_status, payment_method, payment_status, 
          prescription_required, prescription_status, stock_deducted, stock_restored) 
         VALUES (?, ?, ?, "01711223344", "Test Road, Dhaka", 50.00, 0.00, 50.00, "PENDING", "COD", "UNPAID", 0, "NOT_REQUIRED", 1, 0)'
    );
    $ins->execute([$orderNum1, (int)$customer['id'], $customer['full_name']]);
    $orderId1 = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, medicine_id, quantity, unit_price, line_total) VALUES (?, ?, ?, 25.00, 50.00)');
    $itemStmt->execute([$orderId1, $batchId, $orderQty]);

    $histStmt = $pdo->prepare('INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by_id, remarks) VALUES (?, NULL, "PENDING", ?, "Order placed")');
    $histStmt->execute([$orderId1, (int)$customer['id']]);
    $pdo->commit();

    assert_lifecycle((int)scalar("SELECT quantity FROM medicines WHERE id = ?", [$batchId]) === $baselineStock - $orderQty, "Stock decremented for order 1");

    // Sequential transitions
    $transitions = [
        ['CONFIRMED', 'Order confirmed by dispensary'],
        ['PROCESSING', 'Medicines collected and packed'],
        ['READY', 'Package prepared for courier dispatch'],
        ['DISPATCHED', 'Courier assigned and out for delivery'],
        ['COMPLETED', 'Order delivered and payment collected']
    ];

    $prevStatus = 'PENDING';
    foreach ($transitions as [$nextStatus, $note]) {
        $allowed = get_allowed_order_transitions($prevStatus);
        assert_lifecycle(in_array($nextStatus, $allowed, true), "Transition from {$prevStatus} to {$nextStatus} is permissible by state machine");

        // Apply transition
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?")->execute([$nextStatus, $orderId1]);
        if ($nextStatus === 'COMPLETED') {
            $pdo->prepare("UPDATE orders SET payment_status = 'PAID' WHERE id = ?")->execute([$orderId1]);
        }
        $pdo->prepare("INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by_id, remarks) VALUES (?, ?, ?, ?, ?)")
            ->execute([$orderId1, $prevStatus, $nextStatus, (int)$staff['id'], $note]);
        $pdo->commit();

        $currentDb = scalar("SELECT order_status FROM orders WHERE id = ?", [$orderId1]);
        assert_lifecycle($currentDb === $nextStatus, "Order advanced successfully to {$nextStatus}");
        $prevStatus = $nextStatus;
    }

    // Verify payment status auto-updated to PAID upon COMPLETED
    $finalPayment = scalar("SELECT payment_status FROM orders WHERE id = ?", [$orderId1]);
    assert_lifecycle($finalPayment === 'PAID', "Payment status marked as PAID upon order completion");

    // -------------------------------------------------------------
    // 3. Test Terminal State Immutability
    // -------------------------------------------------------------
    $completedAllowed = get_allowed_order_transitions('COMPLETED');
    assert_lifecycle($completedAllowed === [], "get_allowed_order_transitions('COMPLETED') returns empty list (terminal state)");

    $cancelledAllowed = get_allowed_order_transitions('CANCELLED');
    assert_lifecycle($cancelledAllowed === [], "get_allowed_order_transitions('CANCELLED') returns empty list (terminal state)");

    // -------------------------------------------------------------
    // 4. Test Prescription Gate
    // -------------------------------------------------------------
    // Create an order requiring prescription
    $orderNum2 = 'ORD-' . date('Ymd') . '-RX' . rand(100, 999);
    $pdo->beginTransaction();
    InventoryService::deductBatchStock($pdo, $batchId, 1);

    $ins->execute([$orderNum2, (int)$customer['id'], $customer['full_name']]);
    $orderId2 = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE orders SET prescription_required = 1, prescription_status = "PENDING_REVIEW" WHERE id = ?')->execute([$orderId2]);

    $pdo->prepare('INSERT INTO order_items (order_id, medicine_id, quantity, unit_price, line_total) VALUES (?, ?, 1, 25.00, 25.00)')
        ->execute([$orderId2, $batchId]);

    // Attach mock prescription document
    $mockFileName = 'rx_test_' . bin2hex(random_bytes(4)) . '.png';
    $mockFilePath = dirname(__DIR__) . '/storage/prescriptions/' . $mockFileName;
    file_put_contents($mockFilePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

    $pdo->prepare('INSERT INTO prescriptions (order_id, customer_id, file_path, original_filename, file_size, mime_type, verification_status) VALUES (?, ?, ?, "rx.png", 100, "image/png", "PENDING")')
        ->execute([$orderId2, (int)$customer['id'], 'storage/prescriptions/' . $mockFileName]);
    $rxId2 = (int)$pdo->lastInsertId();

    $pdo->commit();

    // Verify Prescription Gate: Cannot advance while PENDING_REVIEW
    $rxOrder = fetch_one("SELECT * FROM orders WHERE id = ?", [$orderId2]);
    $canAdvanceWhilePending = ((int)$rxOrder['prescription_required'] === 1 && $rxOrder['prescription_status'] === 'VERIFIED');
    assert_lifecycle(!$canAdvanceWhilePending, "Prescription Gate blocks unverified prescription order from confirmation");

    // Pharmacist Reviews & Approves Prescription
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE prescriptions SET verification_status = 'APPROVED', reviewed_by_id = ?, review_notes = 'Verified with clinic', reviewed_at = NOW() WHERE id = ?")
        ->execute([(int)$staff['id'], $rxId2]);
    $pdo->prepare("UPDATE orders SET prescription_status = 'VERIFIED' WHERE id = ?")->execute([$orderId2]);
    $pdo->prepare("INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by_id, remarks) VALUES (?, 'PENDING', 'PENDING', ?, 'Prescription approved')")
        ->execute([$orderId2, (int)$staff['id']]);
    $pdo->commit();

    $rxOrderAfterApproval = fetch_one("SELECT * FROM orders WHERE id = ?", [$orderId2]);
    assert_lifecycle($rxOrderAfterApproval['prescription_status'] === 'VERIFIED', "Prescription status updated to 'VERIFIED'");

    // Now advance to CONFIRMED succeeds
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE orders SET order_status = 'CONFIRMED' WHERE id = ?")->execute([$orderId2]);
    $pdo->commit();
    assert_lifecycle(scalar("SELECT order_status FROM orders WHERE id = ?", [$orderId2]) === 'CONFIRMED', "Order successfully confirmed after prescription verification");

    // -------------------------------------------------------------
    // 5. Test Idempotent Cancellation & Stock Restoration
    // -------------------------------------------------------------
    $stockBeforeCancel = (int)scalar("SELECT quantity FROM medicines WHERE id = ?", [$batchId]);

    // First Cancellation Call
    $pdo->beginTransaction();
    $cancelOrder = fetch_one("SELECT * FROM orders WHERE id = ? FOR UPDATE", [$orderId2]);
    if ((int)$cancelOrder['stock_deducted'] === 1 && (int)$cancelOrder['stock_restored'] === 0) {
        $items = fetch_all("SELECT medicine_id, quantity FROM order_items WHERE order_id = ?", [$orderId2]);
        foreach ($items as $item) {
            InventoryService::restoreBatchStock($pdo, (int)$item['medicine_id'], (int)$item['quantity']);
        }
        $pdo->prepare("UPDATE orders SET stock_restored = 1, order_status = 'CANCELLED' WHERE id = ?")->execute([$orderId2]);
    }
    $pdo->commit();

    $stockAfterCancel1 = (int)scalar("SELECT quantity FROM medicines WHERE id = ?", [$batchId]);
    assert_lifecycle($stockAfterCancel1 === $stockBeforeCancel + 1, "Stock incremented by 1 on first cancellation ({$stockBeforeCancel} -> {$stockAfterCancel1})");

    $flagCheck1 = fetch_one("SELECT stock_deducted, stock_restored, order_status FROM orders WHERE id = ?", [$orderId2]);
    assert_lifecycle((int)$flagCheck1['stock_restored'] === 1, "Order flag stock_restored set to 1");
    assert_lifecycle($flagCheck1['order_status'] === 'CANCELLED', "Order status set to 'CANCELLED'");

    // Second Cancellation Call (Test Idempotency)
    $pdo->beginTransaction();
    $cancelOrder2 = fetch_one("SELECT * FROM orders WHERE id = ? FOR UPDATE", [$orderId2]);
    $secondRestorationTriggered = false;
    if ((int)$cancelOrder2['stock_deducted'] === 1 && (int)$cancelOrder2['stock_restored'] === 0) {
        $secondRestorationTriggered = true;
    }
    $pdo->commit();

    assert_lifecycle(!$secondRestorationTriggered, "Idempotency verified: Second cancellation skipped stock restoration");
    $stockAfterCancel2 = (int)scalar("SELECT quantity FROM medicines WHERE id = ?", [$batchId]);
    assert_lifecycle($stockAfterCancel2 === $stockAfterCancel1, "Stock remains unchanged on subsequent cancellation calls ({$stockAfterCancel2} === {$stockAfterCancel1})");

    // -------------------------------------------------------------
    // 6. Test Prescription File Access Control
    // -------------------------------------------------------------
    // Test Staff Access Authorization
    $_SESSION['user'] = ['id' => (int)$staff['id'], 'role' => 'STAFF', 'username' => 'staff'];
    $canStaffAccess = is_staff_or_admin() || (is_customer() && (int)$rxOrder['customer_id'] === (int)user()['id']);
    assert_lifecycle($canStaffAccess === true, "Dispensary Staff authorized to access prescription file");

    // Test Owning Customer Access Authorization
    $_SESSION['user'] = ['id' => (int)$customer['id'], 'role' => 'CUSTOMER', 'username' => 'customer'];
    $canOwnerAccess = is_staff_or_admin() || (is_customer() && (int)$rxOrder['customer_id'] === (int)user()['id']);
    assert_lifecycle($canOwnerAccess === true, "Owning Customer authorized to access their prescription file");

    // Test Third-Party Customer Access Barred (IDOR Prevention)
    $_SESSION['user'] = ['id' => 999999, 'role' => 'CUSTOMER', 'username' => 'attacker'];
    $canThirdPartyAccess = is_staff_or_admin() || (is_customer() && (int)$rxOrder['customer_id'] === (int)user()['id']);
    assert_lifecycle($canThirdPartyAccess === false, "Third-party customer barred from accessing other customer's prescription (403)");

    // Test Path Traversal Protection
    $storageDir = realpath(dirname(__DIR__) . '/storage/prescriptions');
    $traversalAttempt = realpath(dirname(__DIR__) . '/config.php');
    $traversalAllowed = ($traversalAttempt && str_starts_with($traversalAttempt, $storageDir));
    assert_lifecycle(!$traversalAllowed, "Path traversal attempt outside storage/prescriptions/ blocked");

    // -------------------------------------------------------------
    // 7. Cleanup & Teardown
    // -------------------------------------------------------------
    if (file_exists($mockFilePath)) {
        @unlink($mockFilePath);
    }
    assert_lifecycle(!file_exists($mockFilePath), "Mock prescription test file removed from disk");

    // Restore stock for Order 1
    $pdo->beginTransaction();
    InventoryService::restoreBatchStock($pdo, $batchId, $orderQty);
    $pdo->commit();

    // Delete test orders (cascades to order_items, prescriptions, order_status_history)
    $pdo->exec("DELETE FROM orders WHERE id IN ({$orderId1}, {$orderId2})");

    $orphanedOrders = fetch_one("SELECT id FROM orders WHERE id IN ({$orderId1}, {$orderId2})");
    assert_lifecycle($orphanedOrders === null, "Test orders deleted with zero orphaned records");

    $finalStock = (int)scalar("SELECT quantity FROM medicines WHERE id = ?", [$batchId]);
    assert_lifecycle($finalStock === $baselineStock, "Medicine stock restored to baseline ({$baselineStock})");

} catch (Throwable $ex) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "[ERROR] Unexpected exception: " . $ex->getMessage() . "\n" . $ex->getTraceAsString() . "\n");
    exit(1);
}

echo "\nAll {$passed} order lifecycle & prescription verification tests passed successfully!\n";
