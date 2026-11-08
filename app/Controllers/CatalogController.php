<?php
/**
 * PharmaCare — Catalog Controller
 * Handles storefront catalog browsing, category filtering, and live search API queries.
 *
 * Namespace: App\Controllers
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Medicine;
use Throwable;

class CatalogController
{
    /**
     * Render the storefront medicine catalog view.
     */
    public function index(): void
    {
        $keyword = trim((string)($_GET['keyword'] ?? ''));
        $categoryId = (int)($_GET['category_id'] ?? 0);

        $categories = Medicine::getAllCategories();
        $medicines = Medicine::findActive($keyword, $categoryId);

        include dirname(__DIR__, 2) . '/pages/catalog.php';
    }

    /**
     * Handle asynchronous JSON search & filtering endpoint (api/medicines/search.php).
     */
    public function searchApi(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            api_json_response(false, 'Method not allowed. Expected GET request.', null, 405);
        }

        try {
            $keyword = trim((string)($_GET['q'] ?? $_GET['keyword'] ?? ''));
            $categoryId = (int)($_GET['category_id'] ?? 0);

            $rows = Medicine::findActive($keyword, $categoryId);
            $cart = cart_get_items();

            $medicines = array_map(function (array $m) use ($cart): array {
                $status = medicine_status($m);
                $cartQty = isset($cart[$m['id']]) ? (int)$cart[$m['id']]['quantity'] : 0;
                return [
                    'id' => (int)$m['id'],
                    'name' => (string)$m['name'],
                    'generic_name' => (string)$m['generic_name'],
                    'brand_name' => (string)($m['brand_name'] ?? ''),
                    'batch_number' => (string)$m['batch_number'],
                    'medicine_type' => (string)$m['medicine_type'],
                    'dosage_form' => ucfirst(strtolower((string)$m['medicine_type'])),
                    'selling_price' => (float)$m['selling_price'],
                    'formatted_price' => money($m['selling_price']),
                    'quantity' => (int)$m['quantity'],
                    'prescription_required' => (bool)$m['prescription_required'],
                    'category_id' => (int)$m['category_id'],
                    'category_name' => (string)$m['category_name'],
                    'manufacturer' => (string)($m['supplier_company'] ?: $m['supplier_name']),
                    'expiry_date' => (string)$m['expiry_date'],
                    'formatted_expiry' => nice_date($m['expiry_date']),
                    'status' => $status,
                    'status_label' => status_label($status),
                    'status_badge' => status_badge($status),
                    'in_cart_quantity' => $cartQty,
                ];
            }, $rows);

            api_json_response(true, 'Medicines retrieved successfully.', [
                'count' => count($medicines),
                'medicines' => $medicines,
                'cart_count' => cart_get_count(),
                'is_logged_in' => user() !== null,
                'is_customer' => is_customer(),
            ], 200);

        } catch (Throwable $e) {
            api_json_response(false, 'Failed to query medicines: ' . $e->getMessage(), null, 500);
        }
    }
}
