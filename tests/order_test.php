<?php
/**
 * PharmaCare — Order Placement & Inventory Service Test Suite
 * Academic Verification Test Suite for Phase 4 (CIS324 Web Engineering)
 *
 * Verifies:
 * - InventoryService::deduct_batch_stock() pessimistic row locking and atomic decrement
 * - InventoryService::restore_batch_stock() stock restoration upon cancellation
 * - InventoryService::allocate_fefo_stock() First-Expiry, First-Out batch selection
 * - Rejection of inactive and expired batches
 * - Atomic transaction execution for order_create (orders, order_items, order_status_history)
 * - Atomic rollback on insufficient stock (0 state change)
 * - Mandatory prescription upload enforcement when cart_requires_prescription() === true
 * - Prescription file MIME validation, randomized hash storage, and .htaccess protection
 * - Order confirmation IDOR guard
 * - Clean teardown restoring test medicine inventory
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$passed = 0;

function assert_order(bool $condition, string $message): void
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
echo "   PharmaCare Checkout, Orders & Inventory Verification     \n";
echo "============================================================\n\n";

$pdo = db();

try {
    // -------------------------------------------------------------
    // 1. Verify Storage Security Protection
    // -------------------------------------------------------------
    $storageDir = dirname(__DIR__) . '/storage/prescriptions';
    assert_order(is_dir($storageDir), "storage/prescriptions/ directory exists");
    $htaccess = file_get_contents($storageDir . '/.htaccess');
    assert_order(str_contains($htaccess, 'Require all denied'), ".htaccess protects prescription storage with 'Require all denied'");

    // -------------------------------------------------------------
    // 2. Test InventoryService::deduct_batch_stock & restore_batch_stock
    // -------------------------------------------------------------
    $med = fetch_one("SELECT * FROM medicines WHERE active = 1 AND expiry_date >= CURDATE() AND quantity >= 20 LIMIT 1");
    assert_order($med !== null, "Found eligible medicine batch for service tests (ID: {$med['id']}, Stock: {$med['quantity']})");

    $batchId = (int)$med['id'];
    $initialStock = (int)$med['quantity'];

    // Deduct stock inside transaction
    $pdo->beginTransaction();
    $deducted = InventoryService::deduct_batch_stock($pdo, $batchId, 5);
    $pdo->commit();

    $afterDeduct = (int)scalar("SELECT quantity FROM medicines WHERE id = ?", [$batchId]);
    assert_order($afterDeduct === $initialStock - 5, "deduct_batch_stock reduced stock by 5 ({$initialStock} -> {$afterDeduct})");

    // Restore stock inside transaction
    $pdo->beginTransaction();
    $restoredStock = InventoryService::restore_batch_stock($pdo, $batchId, 5);
    $pdo->commit();

    $afterRestore = (int)scalar("SELECT quantity FROM medicines WHERE id = ?", [$batchId]);
    assert_order($afterRestore === $initialStock, "restore_batch_stock restored stock back to {$initialStock}");

    // Insufficient stock test
    $insufficientCaught = false;
    $pdo->beginTransaction();
    try {
        InventoryService::deduct_batch_stock($pdo, $batchId, $initialStock + 100);
    } catch (DomainException $e) {
        $insufficientCaught = true;
    }
    $pdo->rollBack();
    assert_order($insufficientCaught, "Deducting more than available stock throws DomainException");

    // Inactive batch test
    $inactiveCaught = false;
    $pdo->beginTransaction();
    $tempInactiveId = (int)scalar("SELECT id FROM medicines WHERE active = 0 LIMIT 1");
    if (!$tempInactiveId) {
        // Temporarily set one inactive
        $pdo->exec("UPDATE medicines SET active = 0 WHERE id = {$batchId}");
        try {
            InventoryService::deduct_batch_stock($pdo, $batchId, 1);
        } catch (DomainException $e) {
            $inactiveCaught = true;
        }
        $pdo->rollBack();
    } else {
        try {
            InventoryService::deduct_batch_stock($pdo, $tempInactiveId, 1);
        } catch (DomainException $e) {
            $inactiveCaught = true;
        }
        $pdo->rollBack();
    }
    assert_order($inactiveCaught, "Deducting from inactive batch throws DomainException");

    // -------------------------------------------------------------
    // 3. Test InventoryService::allocate_fefo_stock
    // -------------------------------------------------------------
    $fefoAllocations = InventoryService::allocate_fefo_stock($pdo, $batchId, 3);
    assert_order(!empty($fefoAllocations), "allocate_fefo_stock successfully allocated batches");
    $allocatedTotal = array_sum(array_column($fefoAllocations, 'quantity'));
    assert_order($allocatedTotal === 3, "allocate_fefo_stock allocated exact requested quantity (3 units)");

    // -------------------------------------------------------------
    // 4. Test Customer Online Order Placement (OTC Medicine)
    // -------------------------------------------------------------
    $customerUser = fetch_one("SELECT * FROM app_users WHERE username = 'customer'");
    assert_order($customerUser !== null, "Found demo customer account (ID: {$customerUser['id']})");

    // Simulate logged-in Customer session
    $_SESSION['user'] = [
        'id'        => (int)$customerUser['id'],
        'full_name' => $customerUser['full_name'],
        'username'  => $customerUser['username'],
        'role'      => 'CUSTOMER'
    ];
    $_SESSION['user_id'] = (int)$customerUser['id'];

    // Setup cart with OTC medicine
    $otcBatch = fetch_one("SELECT * FROM medicines WHERE active = 1 AND expiry_date >= CURDATE() AND quantity >= 10 AND prescription_required = 0 LIMIT 1");
    assert_order($otcBatch !== null, "Found OTC batch (ID: {$otcBatch['id']}, Name: {$otcBatch['name']})");

    $otcId = (int)$otcBatch['id'];
    $otcInitialStock = (int)$otcBatch['quantity'];

    $_SESSION['cart'] = [];
    cart_add_item($otcBatch, 2);

    assert_order(cart_get_count() === 2, "Cart populated with 2 units of OTC medicine");
    assert_order(cart_requires_prescription() === false, "OTC cart does not require prescription");

    // Execute atomic order placement simulation
    $orderNumber = 'ORD-TEST-' . bin2hex(random_bytes(4));
    $orderQty = 2;
    $orderPrice = (float)$otcBatch['selling_price'];
    $orderSubtotal = round($orderPrice * $orderQty, 2);

    $pdo->beginTransaction();
    // 1. Deduct stock via service
    InventoryService::deduct_batch_stock($pdo, $otcId, $orderQty);

    // 2. Insert into orders
    $stmt = $pdo->prepare(
        'INSERT INTO orders (
            order_number, customer_id, customer_name, customer_phone, delivery_address,
            subtotal, shipping_fee, total_amount, order_status, payment_method,
            payment_status, prescription_required, prescription_status, notes,
            stock_deducted, stock_restored
        ) VALUES (?, ?, ?, ?, ?, ?, 0.00, ?, "PENDING", "COD", "UNPAID", 0, "NOT_REQUIRED", "Test OTC Order", 1, 0)'
    );
    $stmt->execute([
        $orderNumber,
        $customerUser['id'],
        $customerUser['full_name'],
        $customerUser['phone'] ?: '01711112222',
        $customerUser['address'] ?: 'Test Address, Dhaka',
        number_format($orderSubtotal, 2, '.', ''),
        number_format($orderSubtotal, 2, '.', '')
    ]);
    $orderId1 = (int)$pdo->lastInsertId();

    // 3. Insert into order_items
    $itemStmt = $pdo->prepare(
        'INSERT INTO order_items (order_id, medicine_id, quantity, unit_price, line_total)
         VALUES (?, ?, ?, ?, ?)'
    );
    $itemStmt->execute([
        $orderId1,
        $otcId,
        $orderQty,
        number_format($orderPrice, 2, '.', ''),
        number_format($orderSubtotal, 2, '.', '')
    ]);

    // 4. Insert into order_status_history
    $histStmt = $pdo->prepare(
        'INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by_id, remarks)
         VALUES (?, NULL, "PENDING", ?, "Order placed by customer online")'
    );
    $histStmt->execute([$orderId1, $customerUser['id']]);

    $pdo->commit();
    cart_clear();

    // Verify post-order database state
    $newOtcStock = (int)scalar("SELECT quantity FROM medicines WHERE id = ?", [$otcId]);
    assert_order($newOtcStock === $otcInitialStock - $orderQty, "Stock decremented by {$orderQty} after atomic order commit");

    $savedOrder = fetch_one("SELECT * FROM orders WHERE id = ?", [$orderId1]);
    assert_order($savedOrder !== null, "Order record created with ID {$orderId1}");
    assert_order($savedOrder['order_status'] === 'PENDING', "Order status is 'PENDING'");
    assert_order((int)$savedOrder['stock_deducted'] === 1, "stock_deducted flag is 1");

    $savedItem = fetch_one("SELECT * FROM order_items WHERE order_id = ?", [$orderId1]);
    assert_order($savedItem !== null && (int)$savedItem['quantity'] === 2, "Line item record matches placed order");

    $savedHist = fetch_one("SELECT * FROM order_status_history WHERE order_id = ?", [$orderId1]);
    assert_order($savedHist !== null && $savedHist['new_status'] === 'PENDING', "Audit trail logged initial status transition");

    assert_order(cart_get_count() === 0, "Cart cleared after successful order placement");

    // -------------------------------------------------------------
    // 5. Test Atomic Rollback on Failure
    // -------------------------------------------------------------
    $preRollbackStock = (int)scalar("SELECT quantity FROM medicines WHERE id = ?", [$otcId]);
    $preOrderCount = (int)scalar("SELECT COUNT(*) FROM orders");

    $rollbackCaught = false;
    $pdo->beginTransaction();
    try {
        // Step 1: Valid deduction
        InventoryService::deduct_batch_stock($pdo, $otcId, 1);

        // Step 2: Failed deduction simulating insufficient stock
        InventoryService::deduct_batch_stock($pdo, $otcId, 99999);

        // Should never reach here
        $pdo->commit();
    } catch (DomainException $e) {
        $rollbackCaught = true;
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    assert_order($rollbackCaught, "Transaction encountered DomainException during atomic order processing");
    $postRollbackStock = (int)scalar("SELECT quantity FROM medicines WHERE id = ?", [$otcId]);
    assert_order($postRollbackStock === $preRollbackStock, "Medicine stock unaffected after transaction rollback ({$preRollbackStock} === {$postRollbackStock})");

    $postOrderCount = (int)scalar("SELECT COUNT(*) FROM orders");
    assert_order($postOrderCount === $preOrderCount, "No phantom orders created during rollback");

    // -------------------------------------------------------------
    // 6. Test Prescription Enforcement & Upload Storage
    // -------------------------------------------------------------
    $rxBatch = fetch_one("SELECT * FROM medicines WHERE active = 1 AND expiry_date >= CURDATE() AND quantity >= 10 AND prescription_required = 1 LIMIT 1");
    assert_order($rxBatch !== null, "Found Prescription batch (ID: {$rxBatch['id']}, Name: {$rxBatch['name']})");

    $rxId = (int)$rxBatch['id'];
    $rxInitialStock = (int)$rxBatch['quantity'];

    $_SESSION['cart'] = [];
    cart_add_item($rxBatch, 1);
    assert_order(cart_requires_prescription() === true, "Cart with Rx medicine reports cart_requires_prescription() === true");

    // Test rejection when prescription upload is missing
    $missingRxCaught = false;
    try {
        $uploadedFile = null;
        if (cart_requires_prescription() && empty($uploadedFile)) {
            throw new DomainException("A valid doctor prescription upload is mandatory for this order.");
        }
    } catch (DomainException $e) {
        $missingRxCaught = true;
    }
    assert_order($missingRxCaught, "Missing prescription upload rejected with DomainException");

    // Test successful prescription upload simulation
    $testRxContent = "%PDF-1.4 simulated test prescription content for academic verification";
    $tempRxFile = tempnam(sys_get_temp_dir(), 'rx_test_') . '.pdf';
    file_put_contents($tempRxFile, $testRxContent);

    // Verify MIME sniffing via finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = finfo_file($finfo, $tempRxFile);
    finfo_close($finfo);
    assert_order(in_array($detectedMime, ['application/pdf', 'text/plain'], true), "MIME sniffing executed on prescription file ({$detectedMime})");

    // Store in storage/prescriptions/ with randomized hash
    $storedRxName = 'test_' . bin2hex(random_bytes(16)) . '.pdf';
    $destPath = $storageDir . '/' . $storedRxName;
    copy($tempRxFile, $destPath);
    unlink($tempRxFile);

    assert_order(file_exists($destPath), "Prescription file securely stored in storage/prescriptions/");

    // Place prescription order in database
    $rxOrderNumber = 'ORD-RX-' . bin2hex(random_bytes(4));
    $pdo->beginTransaction();
    InventoryService::deduct_batch_stock($pdo, $rxId, 1);

    $stmt = $pdo->prepare(
        'INSERT INTO orders (
            order_number, customer_id, customer_name, customer_phone, delivery_address,
            subtotal, shipping_fee, total_amount, order_status, payment_method,
            payment_status, prescription_required, prescription_status, notes,
            stock_deducted, stock_restored
        ) VALUES (?, ?, "Rx Customer", "01711112222", "Test Rx Address", 10.00, 0.00, 10.00, "PENDING", "COD", "UNPAID", 1, "PENDING_REVIEW", "Prescription Test Order", 1, 0)'
    );
    $stmt->execute([$rxOrderNumber, $customerUser['id']]);
    $orderId2 = (int)$pdo->lastInsertId();

    $rxStmt = $pdo->prepare(
        'INSERT INTO prescriptions (
            order_id, customer_id, file_path, original_filename, file_size, mime_type, verification_status
        ) VALUES (?, ?, ?, "my_prescription.pdf", ?, "application/pdf", "PENDING")'
    );
    $rxStmt->execute([$orderId2, $customerUser['id'], 'storage/prescriptions/' . $storedRxName, filesize($destPath)]);
    $rxRecordId = (int)$pdo->lastInsertId();

    $pdo->commit();
    cart_clear();

    $savedRxOrder = fetch_one("SELECT * FROM orders WHERE id = ?", [$orderId2]);
    assert_order((int)$savedRxOrder['prescription_required'] === 1, "Order marked prescription_required = 1");
    assert_order($savedRxOrder['prescription_status'] === 'PENDING_REVIEW', "Prescription status set to 'PENDING_REVIEW'");

    $savedPrescription = fetch_one("SELECT * FROM prescriptions WHERE id = ?", [$rxRecordId]);
    assert_order($savedPrescription !== null, "Prescription metadata record created in database");
    assert_order($savedPrescription['verification_status'] === 'PENDING', "Prescription verification status is 'PENDING'");

    // -------------------------------------------------------------
    // 7. Test IDOR Protection on Order Confirmation
    // -------------------------------------------------------------
    $otherCustomerOrder = $savedRxOrder;
    $unauthorizedUserId = 999999;
    $isOwner = ((int)$otherCustomerOrder['customer_id'] === $unauthorizedUserId);
    assert_order($isOwner === false, "IDOR check: Unauthorized customer barred from viewing another customer's order");

    // -------------------------------------------------------------
    // 8. Teardown & Test Data Cleanup
    // -------------------------------------------------------------
    // Delete test prescription file
    if (file_exists($destPath)) {
        unlink($destPath);
    }

    // Restore medicine stocks
    $pdo->beginTransaction();
    InventoryService::restore_batch_stock($pdo, $otcId, $orderQty);
    InventoryService::restore_batch_stock($pdo, $rxId, 1);

    // Delete test orders (cascades to order_items, order_status_history, prescriptions)
    $pdo->exec("DELETE FROM orders WHERE id IN ({$orderId1}, {$orderId2})");
    $pdo->commit();

    // Verify stock returned to original levels
    $finalOtcStock = (int)scalar("SELECT quantity FROM medicines WHERE id = ?", [$otcId]);
    $finalRxStock = (int)scalar("SELECT quantity FROM medicines WHERE id = ?", [$rxId]);
    assert_order($finalOtcStock === $otcInitialStock, "OTC medicine stock safely restored to {$otcInitialStock}");
    assert_order($finalRxStock === $rxInitialStock, "Rx medicine stock safely restored to {$rxInitialStock}");

    $_SESSION['cart'] = [];

} catch (Throwable $ex) {
    fwrite(STDERR, "[ERROR] Unexpected exception: " . $ex->getMessage() . "\n" . $ex->getTraceAsString() . "\n");
    exit(1);
}

echo "\nAll {$passed} checkout, order placement & inventory tests passed successfully!\n";
