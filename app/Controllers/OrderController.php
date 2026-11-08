<?php
/**
 * PharmaCare — Order & Fulfillment Controller
 * Coordinates order placement, checkout rendering, customer history, staff queues, and status transitions.
 *
 * Namespace: App\Controllers
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use DomainException;
use Throwable;

class OrderController
{
    /**
     * Render the checkout form view.
     */
    public function checkout(): void
    {
        require_customer();
        $cart = cart_get_items();
        if (empty($cart)) {
            flash('warning', 'Your cart is empty. Please add medicines before checkout.');
            redirect('catalog');
        }

        $customer = user();
        include dirname(__DIR__, 2) . '/pages/checkout.php';
    }

    /**
     * Handle checkout order submission.
     */
    public function store(): void
    {
        require_customer();
        $cart = cart_get_items();
        $prescriptionFile = $_FILES['prescription'] ?? null;

        $input = [
            'customer_id' => (int)user()['id'],
            'customer_name' => required_text('customer_name', 100),
            'customer_phone' => trim((string)($_POST['customer_phone'] ?? '')),
            'delivery_address' => required_text('delivery_address', 500),
            'payment_method' => strtoupper(trim((string)($_POST['payment_method'] ?? 'COD'))),
            'notes' => optional_text('notes', 1000),
        ];

        $result = OrderService::createOrder($input, $cart, $prescriptionFile);

        flash('success', "Order #{$result['order_number']} placed successfully!");
        redirect('order_confirmation', ['id' => $result['order_id']]);
    }

    /**
     * Render the order confirmation view.
     */
    public function confirmation(): void
    {
        require_customer();
        include dirname(__DIR__, 2) . '/pages/order_confirmation.php';
    }

    /**
     * Render customer order history view (My Orders).
     */
    public function myOrders(): void
    {
        require_customer();
        include dirname(__DIR__, 2) . '/pages/my_orders.php';
    }

    /**
     * Render staff fulfillment orders queue.
     */
    public function index(): void
    {
        require_staff_or_admin();
        include dirname(__DIR__, 2) . '/pages/orders.php';
    }

    /**
     * Render order detail inspection and lifecycle transition view.
     */
    public function show(): void
    {
        require_login();
        include dirname(__DIR__, 2) . '/pages/order_detail.php';
    }

    /**
     * Handle staff order lifecycle status transition action.
     */
    public function updateStatus(): void
    {
        require_staff_or_admin();
        $orderId = positive_int($_POST['order_id'] ?? 0, 'Order ID');
        $newStatus = trim((string)($_POST['new_status'] ?? ''));
        $remarks = optional_text('remarks', 1000);
        $actorId = (int)user()['id'];

        OrderService::updateOrderStatus($orderId, $newStatus, $remarks, $actorId);

        $order = Order::findById($orderId);
        $orderNum = $order ? $order['order_number'] : $orderId;

        flash('success', "Order #{$orderNum} status updated to {$newStatus}.");

        $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        if (str_contains($referer, 'page=orders') && !str_contains($referer, 'page=order_detail')) {
            redirect('orders');
        }

        redirect('order_detail', ['id' => $orderId]);
    }

    /**
     * Handle customer self-cancellation of pending order.
     */
    public function cancelByCustomer(): void
    {
        require_customer();
        $orderId = positive_int($_POST['order_id'] ?? 0, 'Order ID');
        $remarks = optional_text('remarks', 1000);
        $customerId = (int)user()['id'];

        OrderService::cancelOrderByCustomer($orderId, $customerId, $remarks);

        $order = Order::findById($orderId);
        $orderNum = $order ? $order['order_number'] : $orderId;

        flash('success', "Order #{$orderNum} has been cancelled.");
        redirect('order_detail', ['id' => $orderId]);
    }

    /**
     * Handle pharmacist prescription verification action.
     */
    public function reviewPrescription(): void
    {
        require_staff_or_admin();
        $orderId = positive_int($_POST['order_id'] ?? 0, 'Order ID');
        $status = strtoupper(trim((string)($_POST['verification_status'] ?? '')));
        $notes = optional_text('review_notes', 1000);
        $reviewerId = (int)user()['id'];

        OrderService::reviewPrescription($orderId, $status, $notes, $reviewerId);

        $decision = $status === 'APPROVED' ? 'approved' : 'rejected';
        flash('success', "Prescription has been {$decision}.");
        redirect('order_detail', ['id' => $orderId]);
    }

    /**
     * Handle asynchronous JSON API order status tracking (api/orders/status.php).
     */
    public function statusApi(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            api_json_response(false, 'Method not allowed. Expected GET request.', null, 405);
        }

        if (!user()) {
            api_json_response(false, 'Authentication required. Please sign in to track orders.', null, 401);
        }

        try {
            $orderId = positive_int($_GET['id'] ?? 0, 'Order ID', true);
            $orderNumber = trim((string)($_GET['order_number'] ?? ''));

            if (!$orderId && $orderNumber === '') {
                api_json_response(false, 'Order ID or Order Number is required.', null, 400);
            }

            $order = $orderId ? Order::findById($orderId) : Order::findByOrderNumber($orderNumber);

            if (!$order) {
                api_json_response(false, 'Order not found.', null, 404);
            }

            // Strict IDOR Protection
            if (is_customer() && (int)$order['customer_id'] !== (int)user()['id']) {
                api_json_response(false, 'Access denied. You are not authorized to track this order.', null, 403);
            }

            $realOrderId = (int)$order['id'];
            $currentStatus = (string)$order['order_status'];
            $historyRows = Order::getHistory($realOrderId);

            $timeline = array_map(function (array $h): array {
                return [
                    'id' => (int)$h['id'],
                    'previous_status' => $h['previous_status'],
                    'new_status' => (string)$h['new_status'],
                    'new_status_label' => order_status_label((string)$h['new_status']),
                    'new_status_badge' => order_status_badge((string)$h['new_status']),
                    'actor_name' => (string)($h['changed_by_name'] ?? 'System'),
                    'actor_role' => (string)($h['changed_by_role'] ?? ''),
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
    }

    /**
     * Handle authenticated prescription file streaming.
     */
    public function downloadPrescription(): void
    {
        include dirname(__DIR__, 2) . '/pages/download_prescription.php';
    }
}
