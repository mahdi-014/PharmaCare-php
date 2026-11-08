<?php
/**
 * PharmaCare — Order Processing & Lifecycle Domain Service
 * Encapsulates checkout verification, atomic order placement, inventory batch deduction,
 * state machine status progression, and idempotent cancellation stock restoration.
 *
 * Namespace: App\Services
 */

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Medicine;
use App\Services\InventoryService;
use PDO;
use DomainException;
use Throwable;

class OrderService
{
    /**
     * Atomically creates an online order from the session cart, deducting batch inventory.
     *
     * @param array $input Sanitized checkout input parameters
     * @param array $cart Active session cart items
     * @param array|null $prescriptionFile $_FILES['prescription'] if provided
     * @return array Array containing 'order_id' and 'order_number'
     * @throws DomainException On validation or business rule violation
     */
    public static function createOrder(array $input, array $cart, ?array $prescriptionFile = null): array
    {
        if (empty($cart)) {
            throw new DomainException('Your shopping cart is empty. Please add medicines before checkout.');
        }

        $customerId = (int)($input['customer_id'] ?? 0);
        if ($customerId <= 0) {
            throw new DomainException('Valid customer authentication is required to place an order.');
        }

        $customerName = trim((string)($input['customer_name'] ?? ''));
        if ($customerName === '' || strlen($customerName) > 100) {
            throw new DomainException('Recipient Name is required (up to 100 characters).');
        }

        $customerPhone = trim((string)($input['customer_phone'] ?? ''));
        if (!preg_match('/^(?:\+?880|0)?1[3-9]\d{8}$/', $customerPhone)) {
            throw new DomainException('Please enter a valid 11-digit mobile number (e.g., 017XXXXXXXX).');
        }

        $deliveryAddress = trim((string)($input['delivery_address'] ?? ''));
        if ($deliveryAddress === '' || strlen($deliveryAddress) > 500) {
            throw new DomainException('Shipping Address is required (up to 500 characters).');
        }

        $rawPayment = strtoupper(trim((string)($input['payment_method'] ?? 'COD')));
        $paymentMethod = match($rawPayment) {
            'COD', 'CASH_ON_DELIVERY' => 'COD',
            'STORE_PICKUP', 'PAY_AT_PHARMACY' => 'STORE_PICKUP',
            default => throw new DomainException('Please select a valid payment method.')
        };

        $notes = trim((string)($input['notes'] ?? ''));
        if (strlen($notes) > 1000) {
            throw new DomainException('Customer notes cannot exceed 1000 characters.');
        }

        $requiresRx = cart_requires_prescription();
        $prescriptionRecord = self::processPrescriptionUpload($prescriptionFile, $requiresRx);
        $uploadedFilePath = $prescriptionRecord ? dirname(__DIR__, 2) . '/' . $prescriptionRecord['file_path'] : null;

        $pdo = db();
        $pdo->beginTransaction();

        try {
            // Sort cart batches by ID ascending to guarantee deterministic lock acquisition order and prevent deadlocks
            $sortedCart = $cart;
            ksort($sortedCart, SORT_NUMERIC);

            $lineItems = [];
            $subtotal = 0.0;

            foreach ($sortedCart as $medId => $cItem) {
                $qty = (int)$cItem['quantity'];
                $deducted = InventoryService::deductBatchStock($pdo, (int)$medId, $qty);
                $unitPrice = (float)$deducted['selling_price'];
                $lineTotal = round($unitPrice * $qty, 2);
                $subtotal += $lineTotal;

                $lineItems[] = [
                    'medicine_id' => (int)$medId,
                    'medicine_name' => (string)$deducted['name'],
                    'batch_number' => (string)$deducted['batch_number'],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal
                ];
            }

            $shippingFee = 0.00;
            $totalAmount = round($subtotal + $shippingFee, 2);
            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $prescriptionStatus = $requiresRx ? 'PENDING_REVIEW' : 'NOT_REQUIRED';

            $orderId = Order::create($pdo, [
                'order_number' => $orderNumber,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'delivery_address' => $deliveryAddress,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'prescription_required' => $requiresRx ? 1 : 0,
                'prescription_status' => $prescriptionStatus,
                'notes' => $notes !== '' ? $notes : null
            ]);

            Order::addItems($pdo, $orderId, $lineItems);

            if ($prescriptionRecord) {
                Order::addPrescription($pdo, $orderId, $customerId, $prescriptionRecord);
            }

            Order::addStatusHistory($pdo, $orderId, null, 'PENDING', $customerId, 'Order placed by customer');

            $pdo->commit();

            // Clear cart upon successful commit
            cart_clear();

            return [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
            ];

        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($uploadedFilePath && file_exists($uploadedFilePath)) {
                @unlink($uploadedFilePath);
            }
            throw $ex;
        }
    }

    /**
     * Validates and securely stores an uploaded doctor's prescription file.
     */
    public static function processPrescriptionUpload(?array $file, bool $isRequired): ?array
    {
        $hasFile = $file !== null && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE;

        if ($isRequired && !$hasFile) {
            throw new DomainException("Your cart contains prescription-required medications. A valid doctor's prescription file is required.");
        }

        if (!$hasFile) {
            return null;
        }

        $fileError = $file['error'];
        if ($fileError !== UPLOAD_ERR_OK) {
            throw new DomainException('Prescription upload failed with error code ' . $fileError . '. Please try again.');
        }

        $fileSize = (int)$file['size'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($fileSize <= 0 || $fileSize > $maxSize) {
            throw new DomainException('Prescription file size exceeds the 5MB limit.');
        }

        $origName = basename((string)$file['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($ext, $allowedExts, true)) {
            throw new DomainException('Prescription must be an image (JPG, PNG) or PDF document.');
        }

        $tmpPath = (string)$file['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($mime, $allowedMimes, true)) {
            throw new DomainException('Invalid file type (' . $mime . '). Only JPG, PNG, and PDF files are accepted.');
        }

        $storageDir = dirname(__DIR__, 2) . '/storage/prescriptions';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $randomName = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetDiskPath = $storageDir . '/' . $randomName;

        if (!move_uploaded_file($tmpPath, $targetDiskPath)) {
            throw new DomainException('Failed to store the uploaded prescription. Please check permissions and try again.');
        }

        return [
            'file_path' => 'storage/prescriptions/' . $randomName,
            'original_filename' => $origName,
            'file_size' => $fileSize,
            'mime_type' => $mime,
        ];
    }

    /**
     * Executes order status transition enforcing state machine and prescription approval gates.
     */
    public static function updateOrderStatus(int $orderId, string $newStatus, ?string $remarks = null, ?int $actorId = null): void
    {
        $validStatuses = ['PENDING', 'CONFIRMED', 'PROCESSING', 'READY', 'DISPATCHED', 'COMPLETED', 'CANCELLED'];
        if (!in_array($newStatus, $validStatuses, true)) {
            throw new DomainException('Invalid order status selected.');
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new DomainException('Order not found.');
            }

            $currentStatus = (string)$order['order_status'];

            if ($currentStatus === $newStatus) {
                throw new DomainException("Order is already in '{$newStatus}' status.");
            }

            if (in_array($currentStatus, ['COMPLETED', 'CANCELLED'], true)) {
                throw new DomainException("Cannot modify terminal order (Current status: {$currentStatus}).");
            }

            $allowed = get_allowed_order_transitions($currentStatus);
            if (!in_array($newStatus, $allowed, true)) {
                throw new DomainException("Invalid status transition from {$currentStatus} to {$newStatus}.");
            }

            // Prescription Gate: cannot advance to CONFIRMED or beyond if unverified
            if ((int)$order['prescription_required'] === 1 && in_array($newStatus, ['CONFIRMED', 'PROCESSING', 'READY', 'DISPATCHED', 'COMPLETED'], true)) {
                if ($order['prescription_status'] !== 'VERIFIED') {
                    throw new DomainException("Cannot advance order: Prescription must be approved by a pharmacist first (Current prescription status: {$order['prescription_status']}).");
                }
            }

            // Idempotent Stock Restoration upon Cancellation
            if ($newStatus === 'CANCELLED') {
                if ((int)$order['stock_deducted'] === 1 && (int)$order['stock_restored'] === 0) {
                    $itemsStmt = $pdo->prepare('SELECT medicine_id, quantity FROM order_items WHERE order_id = ?');
                    $itemsStmt->execute([$orderId]);
                    $orderItems = $itemsStmt->fetchAll();

                    foreach ($orderItems as $item) {
                        InventoryService::restoreBatchStock($pdo, (int)$item['medicine_id'], (int)$item['quantity']);
                    }

                    Order::markStockRestored($pdo, $orderId);
                }
            }

            // Payment synchronization & Sales Ledger Bridge upon Order Completion
            if ($newStatus === 'COMPLETED') {
                Order::markPaymentStatus($pdo, $orderId, 'PAID');

                // Idempotently bridge completed online order to sales and sale_items
                $checkSale = $pdo->prepare('SELECT id FROM sales WHERE order_id = ? FOR UPDATE');
                $checkSale->execute([$orderId]);
                $existingSale = $checkSale->fetch();

                if (!$existingSale) {
                    $orderNum = (string)$order['order_number'];
                    $invSuffix = str_starts_with($orderNum, 'ORD-') ? substr($orderNum, 4) : $orderNum;
                    $invoiceNumber = 'INV-ORD-' . $invSuffix;
                    $soldById = $actorId ?: (int)$order['customer_id'];

                    $saleStmt = $pdo->prepare(
                        'INSERT INTO sales 
                         (invoice_number, sale_date, customer_name, customer_phone, subtotal, discount, total_amount, sold_by_id, channel, order_id) 
                         VALUES (?, NOW(), ?, ?, ?, 0.00, ?, ?, "ONLINE", ?)'
                    );
                    $saleStmt->execute([
                        $invoiceNumber,
                        $order['customer_name'],
                        $order['customer_phone'],
                        $order['subtotal'],
                        $order['total_amount'],
                        $soldById,
                        $orderId
                    ]);
                    $saleId = (int)$pdo->lastInsertId();

                    $itemsStmt = $pdo->prepare('SELECT medicine_id, quantity, unit_price, line_total FROM order_items WHERE order_id = ?');
                    $itemsStmt->execute([$orderId]);
                    $orderItems = $itemsStmt->fetchAll();

                    $itemInsert = $pdo->prepare('INSERT INTO sale_items (sale_id, medicine_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)');
                    foreach ($orderItems as $oi) {
                        $itemInsert->execute([
                            $saleId,
                            (int)$oi['medicine_id'],
                            (int)$oi['quantity'],
                            $oi['unit_price'],
                            $oi['line_total']
                        ]);
                    }
                }
            }

            Order::updateStatus($pdo, $orderId, $newStatus);

            $histRemarks = $remarks ?: "Status transitioned from {$currentStatus} to {$newStatus}";
            Order::addStatusHistory($pdo, $orderId, $currentStatus, $newStatus, $actorId, $histRemarks);

            $pdo->commit();

        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $ex;
        }
    }

    /**
     * Handles customer self-cancellation of pending orders.
     */
    public static function cancelOrderByCustomer(int $orderId, int $customerId, ?string $remarks = null): void
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new DomainException('Order not found.');
            }

            if ((int)$order['customer_id'] !== $customerId) {
                throw new DomainException('Access denied. You can only cancel your own orders.');
            }

            $currentStatus = (string)$order['order_status'];
            if ($currentStatus !== 'PENDING') {
                throw new DomainException("Order has already progressed to '{$currentStatus}' and cannot be self-cancelled. Please contact dispensary staff.");
            }

            if ((int)$order['stock_deducted'] === 1 && (int)$order['stock_restored'] === 0) {
                $itemsStmt = $pdo->prepare('SELECT medicine_id, quantity FROM order_items WHERE order_id = ?');
                $itemsStmt->execute([$orderId]);
                $orderItems = $itemsStmt->fetchAll();

                foreach ($orderItems as $item) {
                    InventoryService::restoreBatchStock($pdo, (int)$item['medicine_id'], (int)$item['quantity']);
                }

                Order::markStockRestored($pdo, $orderId);
            }

            Order::updateStatus($pdo, $orderId, 'CANCELLED');

            $cancelRemarks = "Order cancelled by customer. " . ($remarks ? "Reason: {$remarks}" : '');
            Order::addStatusHistory($pdo, $orderId, $currentStatus, 'CANCELLED', $customerId, $cancelRemarks);

            $pdo->commit();

        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $ex;
        }
    }

    /**
     * Pharmacist prescription verification (APPROVED or REJECTED).
     */
    public static function reviewPrescription(int $orderId, string $status, ?string $notes = null, ?int $reviewerId = null): void
    {
        $status = strtoupper(trim($status));
        if (!in_array($status, ['APPROVED', 'REJECTED'], true)) {
            throw new DomainException('Please select a valid review decision (Approved or Rejected).');
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $order = Order::findById($orderId);
            if (!$order) {
                throw new DomainException('Order not found.');
            }

            if (in_array($order['order_status'], ['COMPLETED', 'CANCELLED'], true)) {
                throw new DomainException('Cannot review prescription for a completed or cancelled order.');
            }

            Order::updatePrescriptionStatus($pdo, $orderId, $status, $reviewerId, $notes);

            $actionText = $status === 'APPROVED' ? 'approved' : 'rejected';
            $auditRemarks = "Prescription {$actionText} by pharmacist." . ($notes ? " Notes: {$notes}" : '');
            Order::addStatusHistory($pdo, $orderId, (string)$order['order_status'], (string)$order['order_status'], $reviewerId, $auditRemarks);

            $pdo->commit();

        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $ex;
        }
    }
}
