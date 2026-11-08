<?php
/**
 * PharmaCare — Cart Controller
 * Handles shopping cart session state, quantity updates, calculations, and JSON API mutations.
 *
 * Namespace: App\Controllers
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Medicine;
use DomainException;
use Throwable;

class CartController
{
    /**
     * Render the shopping cart page.
     */
    public function index(): void
    {
        require_customer();
        include dirname(__DIR__, 2) . '/pages/cart.php';
    }

    /**
     * Handle standard HTTP form submission to add an item to the cart.
     */
    public function add(): void
    {
        require_customer();
        $medicineId = positive_int($_POST['medicine_id'] ?? 0, 'Medicine ID');
        $requestedQty = positive_int($_POST['quantity'] ?? 1, 'Quantity', false);

        $medicine = Medicine::findById($medicineId);
        if (!$medicine) {
            throw new DomainException('Selected medicine batch was not found.');
        }

        $currentCartQty = (int)($_SESSION['cart'][$medicineId]['quantity'] ?? 0);
        $maxStock = (int)$medicine['quantity'];

        $finalQty = cart_add_item($medicine, $requestedQty);

        if ($currentCartQty + $requestedQty > $maxStock) {
            flash('warning', 'Only ' . $maxStock . ' unit(s) available in stock. Cart updated to ' . $finalQty . ' unit(s).');
        } else {
            flash('success', 'Added ' . $requestedQty . ' × ' . $medicine['name'] . ' to your cart.');
        }

        $redirectTo = (string)($_POST['redirect_to'] ?? 'catalog');
        redirect($redirectTo === 'cart' ? 'cart' : 'catalog');
    }

    /**
     * Handle asynchronous JSON API request to add an item to the cart (api/cart/add.php).
     */
    public function addApi(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            api_json_response(false, 'Method not allowed. Expected POST request.', null, 405);
        }

        if (!user()) {
            api_json_response(false, 'Authentication required. Please sign in to order medicines.', null, 401);
        }

        if (!is_customer()) {
            api_json_response(false, 'Access forbidden. Staff and admin accounts cannot use customer cart.', null, 403);
        }

        api_csrf_verify();

        try {
            $input = api_get_json_input();
            $medicineId = (int)($input['medicine_id'] ?? 0);
            $quantity = (int)($input['quantity'] ?? 1);

            if ($medicineId <= 0 || $quantity <= 0) {
                api_json_response(false, 'Invalid medicine ID or quantity. Quantity must be at least 1.', null, 400);
            }

            $medicine = Medicine::findById($medicineId);
            if (!$medicine) {
                api_json_response(false, 'Medicine batch not found.', null, 404);
            }

            $finalQty = cart_add_item($medicine, $quantity);

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
    }

    /**
     * Handle standard HTTP form submission to update cart item quantity.
     */
    public function update(): void
    {
        require_customer();
        $medicineId = positive_int($_POST['medicine_id'] ?? 0, 'Medicine ID');
        $quantity = positive_int($_POST['quantity'] ?? 0, 'Quantity', true);

        $medicine = Medicine::findById($medicineId);
        if (!$medicine || !(int)$medicine['active'] || $medicine['expiry_date'] < date('Y-m-d')) {
            cart_remove_item($medicineId);
            throw new DomainException('This medicine is no longer available and has been removed from your cart.');
        }

        $maxStock = (int)$medicine['quantity'];
        if ($quantity <= 0) {
            cart_remove_item($medicineId);
            flash('success', 'Item removed from your cart.');
        } else {
            if ($quantity > $maxStock) {
                cart_update_item($medicineId, $maxStock, $maxStock);
                flash('warning', 'Requested quantity exceeds available stock. Quantity adjusted to ' . $maxStock . ' unit(s).');
            } else {
                cart_update_item($medicineId, $quantity, $maxStock);
                flash('success', 'Cart updated successfully.');
            }
        }
        redirect('cart');
    }

    /**
     * Handle asynchronous JSON API request to update cart quantity (api/cart/update.php).
     */
    public function updateApi(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            api_json_response(false, 'Method not allowed. Expected POST request.', null, 405);
        }

        if (!user()) {
            api_json_response(false, 'Authentication required. Please sign in.', null, 401);
        }

        if (!is_customer()) {
            api_json_response(false, 'Access forbidden. Staff accounts cannot use customer cart.', null, 403);
        }

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

            $medicine = Medicine::findById($medicineId);
            if (!$medicine || !(int)$medicine['active'] || $medicine['expiry_date'] < date('Y-m-d')) {
                cart_remove_item($medicineId);
                api_json_response(false, 'This medicine is no longer available and was removed from your cart.', [
                    'item_removed' => true,
                    'medicine_id' => $medicineId,
                    'cart_count' => cart_get_count(),
                    'cart_total' => cart_get_total(),
                ], 400);
            }

            $maxStock = (int)$medicine['quantity'];
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
    }

    /**
     * Handle cart item removal.
     */
    public function remove(): void
    {
        require_customer();
        $medicineId = positive_int($_POST['medicine_id'] ?? 0, 'Medicine ID');
        $itemName = (string)($_SESSION['cart'][$medicineId]['name'] ?? 'Item');
        cart_remove_item($medicineId);
        flash('success', 'Removed ' . $itemName . ' from your cart.');
        redirect('cart');
    }

    /**
     * Handle clearing the entire cart.
     */
    public function clear(): void
    {
        require_customer();
        cart_clear();
        flash('success', 'Your shopping cart has been cleared.');
        redirect('cart');
    }
}
