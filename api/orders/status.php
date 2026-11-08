<?php
/**
 * PharmaCare API — Asynchronous Order Status & Fulfillment Tracking Endpoint
 * Method: GET
 * Returns live lifecycle status, payment status, and chronological audit timeline.
 * Enforces strict IDOR protection: Customers can only query their own orders.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_json_response(false, 'Method not allowed. Expected GET request.', null, 405);
}

// Authentication Check
if (!user()) {
    api_json_response(false, 'Authentication required. Please sign in to track orders.', null, 401);
}

try {
    $orderId = positive_int($_GET['id'] ?? 0, 'Order ID', true);
    $orderNumber = trim((string)($_GET['order_number'] ?? ''));

    if (!$orderId && $orderNumber === '') {
        api_json_response(false, 'Order ID or Order Number is required.', null, 400);
    }

    $sql = 'SELECT * FROM orders WHERE ' . ($orderId ? 'id = ?' : 'order_number = ?') . ' LIMIT 1';
    $order = fetch_one($sql, [$orderId ?: $orderNumber]);

    if (!$order) {
        api_json_response(false, 'Order not found.', null, 404);
    }

    // Strict IDOR Protection
    if (is_customer() && (int)$order['customer_id'] !== (int)user()['id']) {
        api_json_response(false, 'Access denied. You are not authorized to track this order.', null, 403);
    }

    $realOrderId = (int)$order['id'];
    $currentStatus = (string)$order['order_status'];

    // Fetch audit timeline
    $historyRows = fetch_all(
        'SELECT h.*, u.full_name AS actor_name, u.role AS actor_role 
         FROM order_status_history h 
         LEFT JOIN app_users u ON u.id = h.changed_by_id 
         WHERE h.order_id = ? 
         ORDER BY h.id ASC',
        [$realOrderId]
    );

    $timeline = array_map(function (array $h): array {
        return [
            'id' => (int)$h['id'],
            'previous_status' => $h['previous_status'],
            'new_status' => (string)$h['new_status'],
            'new_status_label' => order_status_label((string)$h['new_status']),
            'new_status_badge' => order_status_badge((string)$h['new_status']),
            'actor_name' => (string)($h['actor_name'] ?? 'System'),
            'actor_role' => (string)($h['actor_role'] ?? ''),
            'remarks' => (string)($h['remarks'] ?? ''),
            'created_at' => (string)$h['created_at'],
            'formatted_time' => nice_date($h['created_at'], true),
        ];
    }, $historyRows);

    $isTerminal = in_array($currentStatus, ['COMPLETED', 'CANCELLED'], true);

    api_json_response(true, 'Order status retrieved successfully.', [
        'order_id' => $realOrderId,
        'order_number' => (string)$order['order_number'],
        'order_status' => $currentStatus,
        'order_status_label' => order_status_label($currentStatus),
        'order_status_badge' => order_status_badge($currentStatus),
        'payment_method' => (string)$order['payment_method'],
        'payment_status' => (string)$order['payment_status'],
        'prescription_required' => (bool)$order['prescription_required'],
        'prescription_status' => (string)$order['prescription_status'],
        'prescription_status_label' => prescription_status_label((string)$order['prescription_status']),
        'prescription_status_badge' => prescription_status_badge((string)$order['prescription_status']),
        'is_terminal' => $isTerminal,
        'total_amount' => (float)$order['total_amount'],
        'formatted_total' => money($order['total_amount']),
        'recipient_name' => (string)$order['customer_name'],
        'delivery_address' => (string)$order['delivery_address'],
        'timeline' => $timeline,
        'last_updated' => nice_date($order['updated_at'] ?? $order['created_at'], true),
    ], 200);

} catch (Throwable $e) {
    api_json_response(false, 'Failed to retrieve order status: ' . $e->getMessage(), null, 500);
}
