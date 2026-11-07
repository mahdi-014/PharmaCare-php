<?php
/**
 * PharmaCare — Unified Inventory Engine
 * Centralized stock deduction and restoration service for POS sales and Customer Online Orders.
 * Enforces pessimistic concurrency locking (SELECT ... FOR UPDATE) to eliminate race conditions
 * and maintain relational inventory integrity across concurrent transactions.
 */

declare(strict_types=1);

class InventoryService
{
    /**
     * Atomically locks a medicine batch and decrements its stock.
     * Must be called within an active PDO transaction.
     *
     * @param PDO $pdo Active PDO connection with open transaction
     * @param int $batchId Medicine ID / inventory batch identifier
     * @param int $quantity Quantity to deduct (must be >= 1)
     * @return array The medicine record with previous and updated stock levels
     * @throws DomainException If batch not found, inactive, expired, or stock insufficient
     */
    public static function deductBatchStock(PDO $pdo, int $batchId, int $quantity): array
    {
        if ($quantity <= 0) {
            throw new DomainException('Deduction quantity must be a positive integer.');
        }

        $stmt = $pdo->prepare(
            'SELECT id, name, generic_name, brand_name, batch_number, medicine_type, 
                    selling_price, purchase_price, quantity, minimum_stock_level, 
                    expiry_date, prescription_required, active 
             FROM medicines 
             WHERE id = ? 
             FOR UPDATE'
        );
        $stmt->execute([$batchId]);
        $batch = $stmt->fetch();

        if (!$batch) {
            throw new DomainException("Medicine batch #{$batchId} was not found.");
        }

        if (!(int)$batch['active']) {
            throw new DomainException("Medicine '{$batch['name']}' (Batch {$batch['batch_number']}) is inactive and cannot be dispensed.");
        }

        $today = date('Y-m-d');
        if ($batch['expiry_date'] < $today) {
            throw new DomainException("Medicine '{$batch['name']}' (Batch {$batch['batch_number']}) expired on {$batch['expiry_date']} and cannot be dispensed.");
        }

        $currentStock = (int)$batch['quantity'];
        if ($currentStock < $quantity) {
            throw new DomainException("Insufficient stock for '{$batch['name']}' (Batch {$batch['batch_number']}). Requested: {$quantity}, Available: {$currentStock}.");
        }

        $updateStmt = $pdo->prepare('UPDATE medicines SET quantity = quantity - ? WHERE id = ?');
        $updateStmt->execute([$quantity, $batchId]);

        $batch['previous_quantity'] = $currentStock;
        $batch['remaining_quantity'] = $currentStock - $quantity;
        return $batch;
    }

    /**
     * Atomically restores stock to a medicine batch upon order cancellation.
     * Must be called within an active PDO transaction.
     *
     * @param PDO $pdo Active PDO connection with open transaction
     * @param int $batchId Medicine ID / inventory batch identifier
     * @param int $quantity Quantity to restore (must be >= 1)
     * @return int The updated stock quantity
     * @throws DomainException If batch not found or quantity invalid
     */
    public static function restoreBatchStock(PDO $pdo, int $batchId, int $quantity): int
    {
        if ($quantity <= 0) {
            throw new DomainException('Restoration quantity must be a positive integer.');
        }

        $stmt = $pdo->prepare('SELECT id, name, batch_number, quantity FROM medicines WHERE id = ? FOR UPDATE');
        $stmt->execute([$batchId]);
        $batch = $stmt->fetch();

        if (!$batch) {
            throw new DomainException("Medicine batch #{$batchId} was not found.");
        }

        $updateStmt = $pdo->prepare('UPDATE medicines SET quantity = quantity + ? WHERE id = ?');
        $updateStmt->execute([$quantity, $batchId]);

        return ((int)$batch['quantity']) + $quantity;
    }

    /**
     * Allocates stock across batches of a medicine using First Expiry, First Out (FEFO).
     * Must be called within an active PDO transaction.
     *
     * @param PDO $pdo Active PDO connection with open transaction
     * @param int $medicineId Medicine template ID or target medicine ID
     * @param int $requestedQuantity Total units requested
     * @return array Array of allocated batch items
     * @throws DomainException If total available stock across unexpired batches is insufficient
     */
    public static function allocateFefoStock(PDO $pdo, int $medicineId, int $requestedQuantity): array
    {
        if ($requestedQuantity <= 0) {
            throw new DomainException('Requested allocation quantity must be a positive integer.');
        }

        $nameStmt = $pdo->prepare('SELECT name FROM medicines WHERE id = ?');
        $nameStmt->execute([$medicineId]);
        $medName = $nameStmt->fetchColumn();

        if (!$medName) {
            throw new DomainException("Medicine #{$medicineId} was not found.");
        }

        // Lock all active, unexpired batches sorted by earliest expiry date first
        $batchStmt = $pdo->prepare(
            'SELECT id, name, batch_number, quantity, selling_price, expiry_date 
             FROM medicines 
             WHERE name = ? AND active = 1 AND expiry_date >= CURDATE() AND quantity > 0 
             ORDER BY expiry_date ASC, id ASC 
             FOR UPDATE'
        );
        $batchStmt->execute([$medName]);
        $batches = $batchStmt->fetchAll();

        $allocations = [];
        $remainingNeeded = $requestedQuantity;

        foreach ($batches as $b) {
            if ($remainingNeeded <= 0) break;
            $batchAvailable = (int)$b['quantity'];
            $take = min($remainingNeeded, $batchAvailable);

            self::deductBatchStock($pdo, (int)$b['id'], $take);

            $allocations[] = [
                'batch_id' => (int)$b['id'],
                'batch_number' => $b['batch_number'],
                'quantity' => $take,
                'unit_price' => (float)$b['selling_price'],
                'expiry_date' => $b['expiry_date']
            ];

            $remainingNeeded -= $take;
        }

        if ($remainingNeeded > 0) {
            throw new DomainException("Insufficient total stock across batches for '{$medName}'. Requested: {$requestedQuantity}, Unfulfilled: {$remainingNeeded}.");
        }

        return $allocations;
    }
}

// Procedural helper functions matching specification
function deduct_batch_stock(PDO $pdo, int $batch_id, int $quantity): array
{
    return InventoryService::deductBatchStock($pdo, $batch_id, $quantity);
}

function restore_batch_stock(PDO $pdo, int $batch_id, int $quantity): int
{
    return InventoryService::restoreBatchStock($pdo, $batch_id, $quantity);
}

function allocate_fefo_stock(PDO $pdo, int $medicine_id, int $requested_quantity): array
{
    return InventoryService::allocateFefoStock($pdo, $medicine_id, $requested_quantity);
}
