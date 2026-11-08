<?php
/**
 * PharmaCare API — Asynchronous Cart Quantity Update Endpoint
 * Method: POST
 * Updates or removes a line item in the session cart, returning recalculated metrics.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json_response(false, 'Method not allowed. Expected POST request.', null, 405);
}

// Authentication & Role Authorization
if (!user()) {
    api_json_response(false, 'Authentication required. Please sign in.', null, 401);
}

if (!is_customer()) {
    api_json_response(false, 'Access forbidden. Staff accounts cannot use customer cart.', null, 403);
}

// CSRF Protection
api_csrf_verify();

try {
    $input = api_get_json_input();
    $medicineId = (int)($input['medicine_id'] ?? 0);
    $quantity = isset($input['quantity']) ? (int)$input['quantity'] : -1;

    if ($medicineId <= 0 || $quantity < 0) {
        api_json_response(false, 'Invalid medicine ID or quantity.', null, 400);
    }

    $cart = cart_get_items();
    if (!isset($cart[$medicineId])) {
        api_json_response(false, 'Item is not currently in your cart.', null, 404);
    }

    if ($quantity === 0) {
        $itemName = $cart[$medicineId]['name'];
        cart_remove_item($medicineId);
        api_json_response(true, "Removed {$itemName} from your cart.", [
            'item_removed' => true,
            'medicine_id' => $medicineId,
            'cart_count' => cart_get_count(),
            'line_count' => cart_get_line_count(),
            'cart_total' => cart_get_total(),
            'formatted_cart_total' => money(cart_get_total()),
            'requires_prescription' => cart_requires_prescription(),
        ], 200);
    }

    $batch = fetch_one('SELECT id, name, active, expiry_date, quantity, selling_price FROM medicines WHERE id = ?', [$medicineId]);
    if (!$batch || !(int)$batch['active'] || $batch['expiry_date'] < date('Y-m-d')) {
        cart_remove_item($medicineId);
        api_json_response(false, 'This medicine is no longer available and was removed from your cart.', [
            'item_removed' => true,
            'medicine_id' => $medicineId,
            'cart_count' => cart_get_count(),
            'cart_total' => cart_get_total(),
        ], 400);
    }

    $maxStock = (int)$batch['quantity'];
    cart_update_item($medicineId, $quantity, $maxStock);
    $updatedCart = cart_get_items();
    $updatedItem = $updatedCart[$medicineId];

    $lineTotal = round(((float)$updatedItem['price']) * ((int)$updatedItem['quantity']), 2);

    api_json_response(true, 'Cart quantity updated.', [
        'item' => [
            'id' => $medicineId,
            'name' => $updatedItem['name'],
            'quantity' => (int)$updatedItem['quantity'],
            'max_stock' => $maxStock,
            'price' => (float)$updatedItem['price'],
            'line_total' => $lineTotal,
            'formatted_line_total' => money($lineTotal),
        ],
        'cart_count' => cart_get_count(),
        'line_count' => cart_get_line_count(),
        'cart_total' => cart_get_total(),
        'formatted_cart_total' => money(cart_get_total()),
        'requires_prescription' => cart_requires_prescription(),
    ], 200);

} catch (DomainException $e) {
    api_json_response(false, $e->getMessage(), null, 400);
} catch (Throwable $e) {
    api_json_response(false, 'Failed to update cart: ' . $e->getMessage(), null, 500);
}
