<?php
/**
 * PharmaCare — Shopping Cart & Medicine Catalog Test Suite
 * Academic Verification Test Suite for Phase 3 (CIS324 Web Engineering)
 *
 * Verifies:
 * - Cart item structure and session isolation
 * - Cart item addition, retrieval, line counting, and unit counting
 * - Accurate decimal subtotal and grand total calculations
 * - Strict inventory stock boundary clamping (cannot exceed available batch stock)
 * - Quantity updates (increment, decrement, clamped upper bound)
 * - Zero-quantity item removal
 * - Explicit item removal and cart clearing
 * - Prescription requirement detection (cart_requires_prescription)
 * - Rejection of inactive, expired, and out-of-stock medicine batches
 * - Integration with live MariaDB medicine inventory
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$passed = 0;

function assert_cart(bool $condition, string $message): void
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
echo "   PharmaCare Shopping Cart & Catalog State Verification    \n";
echo "============================================================\n\n";

$pdo = db();

try {
    // Reset session cart state for clean test run
    $_SESSION['cart'] = [];

    // -------------------------------------------------------------
    // 1. Initial State Assertions
    // -------------------------------------------------------------
    assert_cart(cart_get_items() === [], "Initial cart is empty");
    assert_cart(cart_get_count() === 0, "Initial unit count is 0");
    assert_cart(cart_get_line_count() === 0, "Initial line item count is 0");
    assert_cart(cart_get_total() === 0.0, "Initial grand total is 0.00");
    assert_cart(cart_requires_prescription() === false, "Initial cart does not require prescription");

    // -------------------------------------------------------------
    // 2. Fetch Live Seeded Inventory Batches (OTC & Rx)
    // -------------------------------------------------------------
    $otcMed = fetch_one(
        medicine_query() . " WHERE m.active = 1 AND m.expiry_date >= CURDATE() AND m.quantity >= 10 AND m.prescription_required = 0 LIMIT 1"
    );
    assert_cart($otcMed !== null, "Found live active OTC medicine batch (ID: {$otcMed['id']}, Name: '{$otcMed['name']}')");

    $rxMed = fetch_one(
        medicine_query() . " WHERE m.active = 1 AND m.expiry_date >= CURDATE() AND m.quantity >= 10 AND m.prescription_required = 1 LIMIT 1"
    );
    assert_cart($rxMed !== null, "Found live active Prescription medicine batch (ID: {$rxMed['id']}, Name: '{$rxMed['name']}')");

    // -------------------------------------------------------------
    // 3. Test Adding Valid OTC Item
    // -------------------------------------------------------------
    $addedQty = cart_add_item($otcMed, 3);
    assert_cart($addedQty === 3, "Added 3 units of OTC medicine to cart");
    assert_cart(cart_get_count() === 3, "Cart unit count reflects 3 units");
    assert_cart(cart_get_line_count() === 1, "Cart line count reflects 1 unique medicine");
    assert_cart(cart_requires_prescription() === false, "Cart with only OTC medicine does not require prescription");

    $expectedTotal = round(((float)$otcMed['selling_price']) * 3, 2);
    assert_cart(abs(cart_get_total() - $expectedTotal) < 0.001, "Cart total matches unit price * 3 (" . money($expectedTotal) . ")");

    // -------------------------------------------------------------
    // 4. Test Adding Prescription-Required Medicine
    // -------------------------------------------------------------
    $rxQty = cart_add_item($rxMed, 2);
    assert_cart($rxQty === 2, "Added 2 units of Prescription medicine to cart");
    assert_cart(cart_get_count() === 5, "Cart unit count reflects 3 + 2 = 5 units");
    assert_cart(cart_get_line_count() === 2, "Cart line count reflects 2 unique medicines");
    assert_cart(cart_requires_prescription() === true, "cart_requires_prescription() returns true when Rx item is present");

    $expectedTotal2 = round((((float)$otcMed['selling_price']) * 3) + (((float)$rxMed['selling_price']) * 2), 2);
    assert_cart(abs(cart_get_total() - $expectedTotal2) < 0.001, "Cart total accurately calculates multi-item sum (" . money($expectedTotal2) . ")");

    // -------------------------------------------------------------
    // 5. Test Adding More Units to Existing Line Item
    // -------------------------------------------------------------
    $newOtcQty = cart_add_item($otcMed, 2);
    assert_cart($newOtcQty === 5, "Incremented existing OTC medicine by 2 (3 + 2 = 5 units in cart)");
    assert_cart(cart_get_count() === 7, "Total units in cart updated to 7 (5 + 2)");

    // -------------------------------------------------------------
    // 6. Test Stock Boundary Clamping
    // -------------------------------------------------------------
    $stockLimit = (int)$otcMed['quantity'];
    // Attempt to add a huge quantity that exceeds available stock
    $clampedQty = cart_add_item($otcMed, 99999);
    assert_cart($clampedQty === $stockLimit, "Oversized quantity clamped to available batch stock ({$stockLimit})");
    assert_cart($_SESSION['cart'][$otcMed['id']]['quantity'] === $stockLimit, "Line item quantity clamped in session state");

    // Further additions beyond max stock should be rejected
    $maxLimitCaught = false;
    try {
        cart_add_item($otcMed, 1);
    } catch (DomainException $e) {
        $maxLimitCaught = true;
    }
    assert_cart($maxLimitCaught, "Attempting to exceed maximum batch stock throws DomainException");

    // -------------------------------------------------------------
    // 7. Test Quantity Update Operations
    // -------------------------------------------------------------
    // Update OTC item to 4 units
    cart_update_item((int)$otcMed['id'], 4, $stockLimit);
    assert_cart($_SESSION['cart'][$otcMed['id']]['quantity'] === 4, "Updated OTC item quantity to 4");

    // Update with quantity higher than max stock clamps to max stock
    cart_update_item((int)$otcMed['id'], $stockLimit + 50, $stockLimit);
    assert_cart($_SESSION['cart'][$otcMed['id']]['quantity'] === $stockLimit, "cart_update_item clamps quantity exceeding max stock");

    // Update with quantity 0 removes the item
    cart_update_item((int)$otcMed['id'], 0, $stockLimit);
    assert_cart(!isset($_SESSION['cart'][$otcMed['id']]), "Updating quantity to 0 removes item from cart");
    assert_cart(cart_get_line_count() === 1, "Line count decremented to 1");

    // Now only the Rx item remains in cart
    assert_cart(cart_requires_prescription() === true, "Prescription requirement remains true while Rx item is present");

    // -------------------------------------------------------------
    // 8. Test Item Removal
    // -------------------------------------------------------------
    cart_remove_item((int)$rxMed['id']);
    assert_cart(!isset($_SESSION['cart'][$rxMed['id']]), "Removed Rx medicine from cart");
    assert_cart(cart_get_line_count() === 0, "Cart is now empty after removing last item");
    assert_cart(cart_get_count() === 0, "Unit count is 0");
    assert_cart(cart_get_total() === 0.0, "Grand total is 0.00");
    assert_cart(cart_requires_prescription() === false, "cart_requires_prescription() resets to false when Rx item is removed");

    // -------------------------------------------------------------
    // 9. Test Rejection of Inactive, Expired & Zero-Stock Batches
    // -------------------------------------------------------------
    $inactiveMed = [
        'id' => 9991,
        'name' => 'Inactive Med',
        'active' => 0,
        'expiry_date' => date('Y-m-d', strtotime('+100 days')),
        'quantity' => 50,
        'selling_price' => '10.00',
        'prescription_required' => 0,
    ];
    $inactiveCaught = false;
    try {
        cart_add_item($inactiveMed, 1);
    } catch (DomainException $e) {
        $inactiveCaught = true;
    }
    assert_cart($inactiveCaught, "Adding inactive medicine batch rejected with DomainException");

    $expiredMed = [
        'id' => 9992,
        'name' => 'Expired Med',
        'active' => 1,
        'expiry_date' => date('Y-m-d', strtotime('-5 days')),
        'quantity' => 50,
        'selling_price' => '10.00',
        'prescription_required' => 0,
    ];
    $expiredCaught = false;
    try {
        cart_add_item($expiredMed, 1);
    } catch (DomainException $e) {
        $expiredCaught = true;
    }
    assert_cart($expiredCaught, "Adding expired medicine batch rejected with DomainException");

    $zeroStockMed = [
        'id' => 9993,
        'name' => 'Out of Stock Med',
        'active' => 1,
        'expiry_date' => date('Y-m-d', strtotime('+100 days')),
        'quantity' => 0,
        'selling_price' => '10.00',
        'prescription_required' => 0,
    ];
    $zeroStockCaught = false;
    try {
        cart_add_item($zeroStockMed, 1);
    } catch (DomainException $e) {
        $zeroStockCaught = true;
    }
    assert_cart($zeroStockCaught, "Adding out-of-stock medicine batch (qty=0) rejected with DomainException");

    // -------------------------------------------------------------
    // 10. Test Cart Clear
    // -------------------------------------------------------------
    cart_add_item($otcMed, 2);
    assert_cart(cart_get_count() === 2, "Re-added items for clear test");
    cart_clear();
    assert_cart(cart_get_items() === [], "cart_clear() resets session cart array to empty");
    assert_cart(cart_get_count() === 0, "cart_get_count() returns 0 after cart_clear()");
    assert_cart(cart_get_total() === 0.0, "cart_get_total() returns 0.00 after cart_clear()");

    // Cleanup session
    $_SESSION['cart'] = [];

} catch (Throwable $ex) {
    fwrite(STDERR, "[ERROR] Unexpected exception: " . $ex->getMessage() . "\n" . $ex->getTraceAsString() . "\n");
    exit(1);
}

echo "\nAll {$passed} shopping cart & catalog verification tests passed successfully!\n";
