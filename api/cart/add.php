<?php
/**
 * PharmaCare API — Asynchronous Add-to-Cart Endpoint
 * Method: POST
 * Accepts medicine ID and requested quantity, executes stock validation,
 * updates session cart state, and returns new cart metrics.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json_response(false, 'Method not allowed. Expected POST request.', null, 405);
}

// Authentication & Role Authorization
if (!user()) {
    api_json_response(false, 'Authentication required. Please sign in to order medicines.', null, 401);
}

if (!is_customer()) {
    api_json_response(false, 'Access forbidden. Staff and admin accounts cannot use customer cart.', null, 403);
}

// CSRF Protection (X-CSRF-Token or payload _csrf)
api_csrf_verify();

try {
    $input = api_get_json_input();
    $medicineId = (int)($input['medicine_id'] ?? 0);
    $quantity = (int)($input['quantity'] ?? 1);

    if ($medicineId <= 0 || $quantity <= 0) {
        api_json_response(false, 'Invalid medicine ID or quantity. Quantity must be at least 1.', null, 400);
    }

    $medicine = fetch_one(medicine_query() . ' WHERE m.id = ?', [$medicineId]);
    if (!$medicine) {
        api_json_response(false, 'Medicine batch not found.', null, 404);
    }

    $finalQty = cart_add_item($medicine, $quantity);
    $cart = cart_get_items();

    api_json_response(true, "Added {$quantity} × {$medicine['name']} to your cart.", [
        'item' => [
            'id' => $medicineId,
            'name' => $medicine['name'],
            'quantity' => $finalQty,
            'max_stock' => (int)$medicine['quantity'],
            'price' => (float)$medicine['selling_price'],
            'line_total' => round(((float)$medicine['selling_price']) * $finalQty, 2),
            'formatted_line_total' => money(((float)$medicine['selling_price']) * $finalQty),
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
    api_json_response(false, 'Failed to add item to cart: ' . $e->getMessage(), null, 500);
}
