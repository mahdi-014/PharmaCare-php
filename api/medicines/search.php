<?php
/**
 * PharmaCare API — Asynchronous Medicine Search & Catalog Filtering Endpoint
 * Method: GET
 * Returns active, unexpired, and available medicine batches matching search criteria.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_json_response(false, 'Method not allowed. Expected GET request.', null, 405);
}

try {
    $keyword = trim((string)($_GET['q'] ?? $_GET['keyword'] ?? ''));
    $categoryId = (int)($_GET['category_id'] ?? 0);

    $sql = medicine_query() . ' WHERE m.active = 1 AND m.expiry_date >= CURDATE() AND m.quantity > 0';
    $params = [];

    if ($keyword !== '') {
        $sql .= ' AND (m.name LIKE ? OR m.generic_name LIKE ? OR m.brand_name LIKE ?)';
        $needle = '%' . $keyword . '%';
        array_push($params, $needle, $needle, $needle);
    }

    if ($categoryId) {
        $sql .= ' AND m.category_id = ?';
        $params[] = $categoryId;
    }

    $sql .= ' ORDER BY m.name ASC, m.batch_number ASC';
    $rows = fetch_all($sql, $params);

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
