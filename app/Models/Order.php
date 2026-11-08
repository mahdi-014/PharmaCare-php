<?php
/**
 * PharmaCare — Order Domain Model
 * Encapsulates order persistence, queries, item associations, and status history.
 *
 * Namespace: App\Models
 */

declare(strict_types=1);

namespace App\Models;

use PDO;

class Order
{
    /**
     * Fetch order record by its primary key ID.
     */
    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Fetch order record by unique human-readable order number.
     */
    public static function findByOrderNumber(string $orderNumber): ?array
    {
        $stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ? LIMIT 1');
        $stmt->execute([$orderNumber]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Fetch customer's orders with item counts and summary info.
     */
    public static function findByCustomerId(int $customerId, string $statusFilter = ''): array
    {
        $sql = 'SELECT o.*, COUNT(oi.id) AS total_items, COUNT(oi.id) AS item_count 
                FROM orders o 
                LEFT JOIN order_items oi ON oi.order_id = o.id 
                WHERE o.customer_id = ?';
        $params = [$customerId];

        if ($statusFilter !== '') {
            $sql .= ' AND o.order_status = ?';
            $params[] = $statusFilter;
        }

        $sql .= ' GROUP BY o.id ORDER BY o.id DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch all orders for staff fulfillment queue with optional status, payment, and search filters.
     */
    public static function findAll(string $statusFilter = '', string $paymentFilter = '', string $search = ''): array
    {
        $sql = 'SELECT o.*, COUNT(oi.id) AS total_items, COUNT(oi.id) AS item_count 
                FROM orders o 
                LEFT JOIN order_items oi ON oi.order_id = o.id 
                WHERE 1=1';
        $params = [];

        if ($statusFilter !== '') {
            $sql .= ' AND o.order_status = ?';
            $params[] = $statusFilter;
        }

        if ($paymentFilter !== '') {
            $sql .= ' AND o.payment_status = ?';
            $params[] = $paymentFilter;
        }

        if ($search !== '') {
            $sql .= ' AND (o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)';
            $needle = '%' . $search . '%';
            array_push($params, $needle, $needle, $needle);
        }

        $sql .= ' GROUP BY o.id ORDER BY o.id DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch line items associated with an order.
     */
    public static function getItems(int $orderId): array
    {
        $stmt = db()->prepare(
            'SELECT oi.*, m.name AS medicine_name, m.generic_name, m.batch_number, m.medicine_type, c.name AS category_name 
             FROM order_items oi 
             JOIN medicines m ON m.id = oi.medicine_id 
             LEFT JOIN categories c ON c.id = m.category_id 
             WHERE oi.order_id = ? 
             ORDER BY oi.id ASC'
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    /**
     * Fetch prescription record for an order.
     */
    public static function getPrescription(int $orderId): ?array
    {
        $stmt = db()->prepare(
            'SELECT p.*, u.full_name AS reviewer_name 
             FROM prescriptions p 
             LEFT JOIN app_users u ON u.id = p.reviewed_by_id 
             WHERE p.order_id = ? LIMIT 1'
        );
        $stmt->execute([$orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Fetch chronological lifecycle audit history for an order.
     */
    public static function getHistory(int $orderId): array
    {
        $stmt = db()->prepare(
            'SELECT h.*, u.full_name AS changed_by_name, u.role AS changed_by_role 
             FROM order_status_history h 
             LEFT JOIN app_users u ON u.id = h.changed_by_id 
             WHERE h.order_id = ? 
             ORDER BY h.id ASC'
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    /**
     * Count active pending orders for dispensary notifications.
     */
    public static function countPending(): int
    {
        $stmt = db()->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('PENDING', 'CONFIRMED', 'PROCESSING')");
        return (int)$stmt->fetchColumn();
    }

    /**
     * Insert a new order within an active transaction.
     */
    public static function create(PDO $pdo, array $data): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO orders 
             (order_number, customer_id, customer_name, customer_phone, delivery_address, 
              subtotal, shipping_fee, total_amount, order_status, payment_method, payment_status, 
              prescription_required, prescription_status, notes, stock_deducted, stock_restored) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "PENDING", ?, "UNPAID", ?, ?, ?, 1, 0)'
        );
        $stmt->execute([
            $data['order_number'],
            (int)$data['customer_id'],
            $data['customer_name'],
            $data['customer_phone'],
            $data['delivery_address'],
            number_format((float)$data['subtotal'], 2, '.', ''),
            number_format((float)($data['shipping_fee'] ?? 0), 2, '.', ''),
            number_format((float)$data['total_amount'], 2, '.', ''),
            $data['payment_method'],
            !empty($data['prescription_required']) ? 1 : 0,
            $data['prescription_status'] ?? 'NOT_REQUIRED',
            $data['notes'] ?? null
        ]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * Insert order line items within an active transaction.
     */
    public static function addItems(PDO $pdo, int $orderId, array $items): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO order_items (order_id, medicine_id, medicine_name, batch_number, quantity, unit_price, line_total) 
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $stmt->execute([
                $orderId,
                (int)$item['medicine_id'],
                (string)($item['medicine_name'] ?? 'Medicine'),
                (string)($item['batch_number'] ?? 'BATCH-001'),
                (int)$item['quantity'],
                number_format((float)$item['unit_price'], 2, '.', ''),
                number_format((float)$item['line_total'], 2, '.', '')
            ]);
        }
    }

    /**
     * Insert prescription record within an active transaction.
     */
    public static function addPrescription(PDO $pdo, int $orderId, int $customerId, array $rxData): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO prescriptions 
             (order_id, customer_id, file_path, original_filename, file_size, mime_type, verification_status) 
             VALUES (?, ?, ?, ?, ?, ?, "PENDING")'
        );
        $stmt->execute([
            $orderId,
            $customerId,
            $rxData['file_path'],
            $rxData['original_filename'],
            (int)$rxData['file_size'],
            $rxData['mime_type']
        ]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * Record a lifecycle transition into audit history.
     */
    public static function addStatusHistory(PDO $pdo, int $orderId, ?string $prevStatus, string $newStatus, ?int $userId, ?string $remarks): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by_id, remarks) 
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $orderId,
            $prevStatus,
            $newStatus,
            $userId,
            $remarks
        ]);
    }

    /**
     * Update order status.
     */
    public static function updateStatus(PDO $pdo, int $orderId, string $newStatus): void
    {
        $stmt = $pdo->prepare('UPDATE orders SET order_status = ? WHERE id = ?');
        $stmt->execute([$newStatus, $orderId]);
    }

    /**
     * Synchronize payment status.
     */
    public static function markPaymentStatus(PDO $pdo, int $orderId, string $paymentStatus): void
    {
        $stmt = $pdo->prepare('UPDATE orders SET payment_status = ? WHERE id = ?');
        $stmt->execute([$paymentStatus, $orderId]);
    }

    /**
     * Mark stock as restored for an order upon cancellation.
     */
    public static function markStockRestored(PDO $pdo, int $orderId): void
    {
        $stmt = $pdo->prepare('UPDATE orders SET stock_restored = 1 WHERE id = ?');
        $stmt->execute([$orderId]);
    }

    /**
     * Update prescription status and reviewer action.
     */
    public static function updatePrescriptionStatus(PDO $pdo, int $orderId, string $status, ?int $reviewerId, ?string $notes): void
    {
        $rxStmt = $pdo->prepare(
            'UPDATE prescriptions 
             SET verification_status = ?, reviewed_by_id = ?, review_notes = ?, reviewed_at = NOW() 
             WHERE order_id = ?'
        );
        $rxStmt->execute([$status, $reviewerId, $notes, $orderId]);

        $orderPrescriptionStatus = ($status === 'APPROVED') ? 'VERIFIED' : 'REJECTED';
        $orderStmt = $pdo->prepare('UPDATE orders SET prescription_status = ? WHERE id = ?');
        $orderStmt->execute([$orderPrescriptionStatus, $orderId]);
    }

    /**
     * Retrieve linked sale ID if this order has been bridged upon completion.
     */
    public static function getLinkedSaleId(PDO $pdo, int $orderId): ?int
    {
        $stmt = $pdo->prepare('SELECT id FROM sales WHERE order_id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int)$val : null;
    }
}
