<?php
/**
 * PharmaCare — Unified Inventory Service
 * Single source of truth for medicine inventory stock management.
 * Enforces pessimistic row-level locking (SELECT ... FOR UPDATE),
 * atomic stock deductions, FEFO allocation, and stock restorations.
 *
 * Consumed by:
 * 1. Customer Online Order Placement (actions.php -> order_create)
 * 2. In-Store Point of Sale (POS) (actions.php -> sale_create)
 * 3. Order Cancellation & Stock Restoration (actions.php -> order_status_update)
 */

declare(strict_types=1);

class InventoryService
{
    /**
     * Deduct stock for a specific medicine batch using pessimistic row-level locking.
     *
     * @param PDO $pdo Active PDO connection with an ongoing transaction.
     * @param int $batchId The medicine ID (batch primary key).
     * @param int $quantity The quantity to deduct (must be a positive integer).
     * @return array The medicine record prior to deduction.
     * @throws DomainException If inactive, expired, or insufficient stock.
     */
    public static function deduct_batch_stock(PDO $pdo, int $batchId, int $quantity): array
    {
        if ($quantity <= 0) {
            throw new DomainException('Deduction quantity must be a positive integer.');
        }

        $stmt = $pdo->prepare(
            'SELECT id, name, generic_name, brand_name, batch_number, quantity, selling_price, active, expiry_date, prescription_required
             FROM medicines
             WHERE id = ?
             FOR UPDATE'
        );
        $stmt->execute([$batchId]);
        $batch = $stmt->fetch();

        if (!$batch) {
            throw new DomainException('Medicine batch not found.');
        }

        if (!(int)$batch['active']) {
            throw new DomainException("Medicine '{$batch['name']}' (Batch {$batch['batch_number']}) is currently inactive and cannot be ordered.");
        }

        $today = date('Y-m-d');
        if (!empty($batch['expiry_date']) && $batch['expiry_date'] < $today) {
            throw new DomainException("Medicine '{$batch['name']}' (Batch {$batch['batch_number']}) expired on {$batch['expiry_date']} and cannot be sold.");
        }

        $currentStock = (int)$batch['quantity'];
        if ($currentStock < $quantity) {
            throw new DomainException("Insufficient stock for '{$batch['name']}'. Requested: {$quantity}, Available: {$currentStock}.");
        }

        $update = $pdo->prepare('UPDATE medicines SET quantity = quantity - ? WHERE id = ?');
        $update->execute([$quantity, $batchId]);

        return $batch;
    }

    /**
     * Restore stock for a specific medicine batch (e.g., upon order cancellation).
     *
     * @param PDO $pdo Active PDO connection with an ongoing transaction.
     * @param int $batchId The medicine ID (batch primary key).
     * @param int $quantity The quantity to restore (must be a positive integer).
     * @return int The updated stock quantity.
     * @throws DomainException If batch not found.
     */
    public static function restore_batch_stock(PDO $pdo, int $batchId, int $quantity): int
    {
        if ($quantity <= 0) {
            throw new DomainException('Restoration quantity must be a positive integer.');
        }

        $stmt = $pdo->prepare('SELECT id, name, quantity FROM medicines WHERE id = ? FOR UPDATE');
        $stmt->execute([$batchId]);
        $batch = $stmt->fetch();

        if (!$batch) {
            throw new DomainException('Medicine batch not found for stock restoration.');
        }

        $update = $pdo->prepare('UPDATE medicines SET quantity = quantity + ? WHERE id = ?');
        $update->execute([$quantity, $batchId]);

        return (int)$batch['quantity'] + $quantity;
    }

    /**
     * First-Expiry, First-Out (FEFO) automated batch allocation.
     * Allocates stock across active, non-expired batches for a medicine generic name.
     *
     * @param PDO $pdo Active PDO connection with an ongoing transaction.
     * @param int $medicineId A medicine ID to find generic batches for.
     * @param int $requestedQuantity Total quantity required.
     * @return array List of allocations: [['batch_id' => int, 'batch_number' => string, 'name' => string, 'quantity' => int, ...]]
     * @throws DomainException If combined active stock across batches is insufficient.
     */
    public static function allocate_fefo_stock(PDO $pdo, int $medicineId, int $requestedQuantity): array
    {
        if ($requestedQuantity <= 0) {
            throw new DomainException('Requested quantity must be positive.');
        }

        $target = $pdo->prepare('SELECT generic_name FROM medicines WHERE id = ?');
        $target->execute([$medicineId]);
        $row = $target->fetch();
        if (!$row) {
            throw new DomainException('Target medicine not found.');
        }
        $genericName = $row['generic_name'];

        $stmt = $pdo->prepare(
            'SELECT id, name, batch_number, quantity, expiry_date, selling_price
             FROM medicines
             WHERE generic_name = ?
               AND active = 1
               AND expiry_date >= CURDATE()
               AND quantity > 0
             ORDER BY expiry_date ASC, id ASC
             FOR UPDATE'
        );
        $stmt->execute([$genericName]);
        $candidateBatches = $stmt->fetchAll();

        $allocations = [];
        $remaining = $requestedQuantity;

        foreach ($candidateBatches as $b) {
            if ($remaining <= 0) {
                break;
            }
            $take = min((int)$b['quantity'], $remaining);
            $allocations[] = [
                'batch_id'     => (int)$b['id'],
                'batch_number' => $b['batch_number'],
                'name'         => $b['name'],
                'quantity'     => $take,
                'selling_price'=> (float)$b['selling_price'],
                'expiry_date'  => $b['expiry_date']
            ];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new DomainException("Insufficient aggregate inventory for generic '{$genericName}'. Short by {$remaining} units.");
        }

        return $allocations;
    }
}
