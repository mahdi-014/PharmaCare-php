<?php
/**
 * PharmaCare — Layered MVC Architecture Verification Test Suite
 * Academic Verification Test Suite for Phase 7 (CIS324 Web Engineering Q8 Alignment)
 *
 * Verifies:
 * - PSR-4 Class Autoloader resolving App\ namespace
 * - Domain Models (Order, Medicine, User) query accuracy and persistence
 * - Business Services (InventoryService, OrderService) transactional integrity and idempotent cancellation
 * - Application Controllers (CatalogController, CartController, OrderController) instantiation and delegation
 * - Full backward compatibility with global InventoryService class alias
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$passed = 0;

function assert_mvc(bool $condition, string $message): void
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
echo "   PharmaCare Layered MVC Architecture Verification Suite  \n";
echo "============================================================\n\n";

$pdo = db();

try {
    // -------------------------------------------------------------
    // SECTION 1: PSR-4 Autoloader Resolution
    // -------------------------------------------------------------
    echo "--- Section 1: PSR-4 Autoloader & Namespaces ---\n";

    assert_mvc(class_exists(\App\Models\Order::class), "App\\Models\\Order resolved via PSR-4 autoloader");
    assert_mvc(class_exists(\App\Models\Medicine::class), "App\\Models\\Medicine resolved via PSR-4 autoloader");
    assert_mvc(class_exists(\App\Models\User::class), "App\\Models\\User resolved via PSR-4 autoloader");
    assert_mvc(class_exists(\App\Services\InventoryService::class), "App\\Services\\InventoryService resolved via PSR-4 autoloader");
    assert_mvc(class_exists(\App\Services\OrderService::class), "App\\Services\\OrderService resolved via PSR-4 autoloader");
    assert_mvc(class_exists(\App\Controllers\CatalogController::class), "App\\Controllers\\CatalogController resolved via PSR-4 autoloader");
    assert_mvc(class_exists(\App\Controllers\CartController::class), "App\\Controllers\\CartController resolved via PSR-4 autoloader");
    assert_mvc(class_exists(\App\Controllers\OrderController::class), "App\\Controllers\\OrderController resolved via PSR-4 autoloader");
    assert_mvc(class_exists('InventoryService'), "Global InventoryService alias maintains backward compatibility");

    // -------------------------------------------------------------
    // SECTION 2: Domain Models (Medicine & User)
    // -------------------------------------------------------------
    echo "\n--- Section 2: Domain Models (Medicine & User) ---\n";

    // Medicine Model
    $activeMeds = \App\Models\Medicine::findActive();
    assert_mvc(count($activeMeds) > 0, "Medicine::findActive() returns active medicine inventory (" . count($activeMeds) . " batches)");

    $sampleMed = $activeMeds[0];
    $fetchedMed = \App\Models\Medicine::findById((int)$sampleMed['id']);
    assert_mvc($fetchedMed !== null && (int)$fetchedMed['id'] === (int)$sampleMed['id'], "Medicine::findById() retrieves batch #{$sampleMed['id']}");

    $categories = \App\Models\Medicine::getAllCategories();
    assert_mvc(count($categories) >= 5, "Medicine::getAllCategories() returns category taxonomy (count: " . count($categories) . ")");

    // User Model
    $adminUser = \App\Models\User::findByUsernameOrEmail('admin');
    assert_mvc($adminUser !== null && $adminUser['role'] === 'ADMIN', "User::findByUsernameOrEmail('admin') finds Administrator account");

    $custUser = \App\Models\User::findByUsernameOrEmail('customer');
    assert_mvc($custUser !== null && $custUser['role'] === 'CUSTOMER', "User::findByUsernameOrEmail('customer') finds Customer account");

    $userById = \App\Models\User::findById((int)$custUser['id']);
    assert_mvc($userById !== null && $userById['username'] === 'customer', "User::findById() finds user by primary key ID");

    assert_mvc(\App\Models\User::existsByUsername('admin') === true, "User::existsByUsername('admin') returns true");
    assert_mvc(\App\Models\User::existsByUsername('non_existent_usr_999') === false, "User::existsByUsername('non_existent') returns false");
    assert_mvc(\App\Models\User::existsByEmail('customer@example.com') === true, "User::existsByEmail('customer@example.com') returns true");
    assert_mvc(\App\Models\User::existsByEmail('non_existent_email@example.com') === false, "User::existsByEmail('non_existent') returns false");

    // -------------------------------------------------------------
    // SECTION 3: Business Service (OrderService & InventoryService)
    // -------------------------------------------------------------
    echo "\n--- Section 3: Domain Services (OrderService & InventoryService) ---\n";

    $targetMed = \App\Models\Medicine::findById((int)$sampleMed['id']);
    $initialStock = (int)$targetMed['quantity'];
    $orderQty = 2;

    $mockCart = [
        $targetMed['id'] => [
            'id' => (int)$targetMed['id'],
            'name' => $targetMed['name'],
            'category_name' => $targetMed['category_name'],
            'price' => (float)$targetMed['selling_price'],
            'quantity' => $orderQty,
            'batch_number' => $targetMed['batch_number'],
            'max_stock' => $initialStock,
            'requires_prescription' => false,
        ]
    ];

    // Create an order via OrderService
    $orderInput = [
        'customer_id' => (int)$custUser['id'],
        'customer_name' => 'MVC Test Customer',
        'customer_phone' => '01711223344',
        'delivery_address' => '123 MVC Architecture Blvd, Dhaka',
        'payment_method' => 'COD',
        'notes' => 'Automated MVC verification order'
    ];

    $created = \App\Services\OrderService::createOrder($orderInput, $mockCart);
    assert_mvc(isset($created['order_id']) && $created['order_id'] > 0, "OrderService::createOrder() atomically placed order #{$created['order_number']}");

    $testOrderId = (int)$created['order_id'];

    // Verify stock was deducted
    $postDeductMed = \App\Models\Medicine::findById((int)$targetMed['id']);
    assert_mvc((int)$postDeductMed['quantity'] === ($initialStock - $orderQty), "InventoryService::deductBatchStock() decremented inventory ({$initialStock} -> {$postDeductMed['quantity']})");

    // Verify Order Model queries
    $orderRecord = \App\Models\Order::findById($testOrderId);
    assert_mvc($orderRecord !== null, "Order::findById() retrieved newly placed order record");
    assert_mvc($orderRecord['order_status'] === 'PENDING', "Initial order status is PENDING");
    assert_mvc((int)$orderRecord['stock_deducted'] === 1, "Order flag stock_deducted is 1");
    assert_mvc((int)$orderRecord['stock_restored'] === 0, "Order flag stock_restored is 0");

    $items = \App\Models\Order::getItems($testOrderId);
    assert_mvc(count($items) === 1 && (int)$items[0]['quantity'] === $orderQty, "Order::getItems() returns order items");

    $history = \App\Models\Order::getHistory($testOrderId);
    assert_mvc(count($history) >= 1 && $history[0]['new_status'] === 'PENDING', "Order::getHistory() returns initial lifecycle audit event");

    // Test status transition via OrderService
    \App\Services\OrderService::updateOrderStatus($testOrderId, 'CONFIRMED', 'Confirmed by pharmacist', (int)$adminUser['id']);
    $orderConfirmed = \App\Models\Order::findById($testOrderId);
    assert_mvc($orderConfirmed['order_status'] === 'CONFIRMED', "OrderService::updateOrderStatus() advanced order to CONFIRMED");

    // Test customer cancellation with idempotent stock restoration
    // First reset to PENDING to test customer self-cancel
    $pdo->prepare("UPDATE orders SET order_status = 'PENDING' WHERE id = ?")->execute([$testOrderId]);
    \App\Services\OrderService::cancelOrderByCustomer($testOrderId, (int)$custUser['id'], 'Ordered duplicate by mistake');

    $orderCancelled = \App\Models\Order::findById($testOrderId);
    assert_mvc($orderCancelled['order_status'] === 'CANCELLED', "OrderService::cancelOrderByCustomer() set order status to CANCELLED");
    assert_mvc((int)$orderCancelled['stock_restored'] === 1, "Order flag stock_restored set to 1");

    // Verify stock was restored
    $restoredMed = \App\Models\Medicine::findById((int)$targetMed['id']);
    assert_mvc((int)$restoredMed['quantity'] === $initialStock, "InventoryService::restoreBatchStock() restored stock back to initial level ({$initialStock})");

    // Test Idempotency & Terminal State: second cancel call is rejected and does NOT touch stock
    try {
        \App\Services\OrderService::updateOrderStatus($testOrderId, 'CANCELLED', 'Second cancel attempt', (int)$adminUser['id']);
        assert_mvc(false, "Second cancel should have thrown DomainException");
    } catch (DomainException $e) {
        assert_mvc(str_contains($e->getMessage(), 'already in \'CANCELLED\' status') || str_contains($e->getMessage(), 'Cannot modify terminal order'), "State machine blocks modifying terminal cancelled order: " . $e->getMessage());
    }

    $medAfterSecondCancel = \App\Models\Medicine::findById((int)$targetMed['id']);
    assert_mvc((int)$medAfterSecondCancel['quantity'] === $initialStock, "Idempotency guaranteed: Stock remains unchanged ({$initialStock} === {$medAfterSecondCancel['quantity']})");

    // Clean up test order
    $pdo->prepare('DELETE FROM order_status_history WHERE order_id = ?')->execute([$testOrderId]);
    $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$testOrderId]);
    $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$testOrderId]);

    // -------------------------------------------------------------
    // SECTION 4: Application Controllers
    // -------------------------------------------------------------
    echo "\n--- Section 4: Application Controllers ---\n";

    $catalogController = new \App\Controllers\CatalogController();
    assert_mvc(method_exists($catalogController, 'index'), "CatalogController has method index()");
    assert_mvc(method_exists($catalogController, 'searchApi'), "CatalogController has method searchApi()");

    $cartController = new \App\Controllers\CartController();
    assert_mvc(method_exists($cartController, 'index'), "CartController has method index()");
    assert_mvc(method_exists($cartController, 'add'), "CartController has method add()");
    assert_mvc(method_exists($cartController, 'addApi'), "CartController has method addApi()");
    assert_mvc(method_exists($cartController, 'update'), "CartController has method update()");
    assert_mvc(method_exists($cartController, 'updateApi'), "CartController has method updateApi()");
    assert_mvc(method_exists($cartController, 'remove'), "CartController has method remove()");
    assert_mvc(method_exists($cartController, 'clear'), "CartController has method clear()");

    $orderController = new \App\Controllers\OrderController();
    assert_mvc(method_exists($orderController, 'checkout'), "OrderController has method checkout()");
    assert_mvc(method_exists($orderController, 'store'), "OrderController has method store()");
    assert_mvc(method_exists($orderController, 'confirmation'), "OrderController has method confirmation()");
    assert_mvc(method_exists($orderController, 'myOrders'), "OrderController has method myOrders()");
    assert_mvc(method_exists($orderController, 'index'), "OrderController has method index()");
    assert_mvc(method_exists($orderController, 'show'), "OrderController has method show()");
    assert_mvc(method_exists($orderController, 'updateStatus'), "OrderController has method updateStatus()");
    assert_mvc(method_exists($orderController, 'cancelByCustomer'), "OrderController has method cancelByCustomer()");
    assert_mvc(method_exists($orderController, 'reviewPrescription'), "OrderController has method reviewPrescription()");
    assert_mvc(method_exists($orderController, 'statusApi'), "OrderController has method statusApi()");
    assert_mvc(method_exists($orderController, 'downloadPrescription'), "OrderController has method downloadPrescription()");

    echo "\n============================================================\n";
    echo "  PHASE 7 MVC TEST SUITE COMPLETE: $passed/$passed ASSERTIONS PASSED\n";
    echo "============================================================\n";

} catch (Throwable $e) {
    if (isset($testOrderId)) {
        try {
            $pdo->prepare('DELETE FROM order_status_history WHERE order_id = ?')->execute([$testOrderId]);
            $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$testOrderId]);
            $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$testOrderId]);
        } catch (Throwable $ignored) {}
    }
    fwrite(STDERR, "\n[FATAL ERROR] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
