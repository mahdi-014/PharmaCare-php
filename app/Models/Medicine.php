<?php
/**
 * PharmaCare — Medicine Domain Model
 * Encapsulates active inventory queries, batch lookups, and stock manipulation.
 *
 * Namespace: App\Models
 */

declare(strict_types=1);

namespace App\Models;

use PDO;

class Medicine
{
    /**
     * Retrieve base SQL query joining categories and suppliers.
     */
    public static function baseQuery(): string
    {
        return medicine_query();
    }

    /**
     * Fetch a specific medicine batch by primary key ID.
     */
    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare(self::baseQuery() . ' WHERE m.id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Fetch active, non-expired medicine batches with stock > 0 for storefront browsing and live search.
     */
    public static function findActive(string $keyword = '', int $categoryId = 0): array
    {
        $sql = self::baseQuery() . ' WHERE m.active = 1 AND m.expiry_date >= CURDATE() AND m.quantity > 0';
        $params = [];

        if ($keyword !== '') {
            $sql .= ' AND (m.name LIKE ? OR m.generic_name LIKE ? OR m.brand_name LIKE ?)';
            $needle = '%' . $keyword . '%';
            array_push($params, $needle, $needle, $needle);
        }

        if ($categoryId > 0) {
            $sql .= ' AND m.category_id = ?';
            $params[] = $categoryId;
        }

        $sql .= ' ORDER BY m.name ASC, m.batch_number ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch active batches of a medicine by trade name for FEFO allocation.
     */
    public static function findActiveByName(PDO $pdo, string $name): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, name, batch_number, quantity, selling_price, expiry_date 
             FROM medicines 
             WHERE name = ? AND active = 1 AND expiry_date >= CURDATE() AND quantity > 0 
             ORDER BY expiry_date ASC, id ASC 
             FOR UPDATE'
        );
        $stmt->execute([$name]);
        return $stmt->fetchAll();
    }

    /**
     * Lock a medicine batch record with pessimistic FOR UPDATE lock.
     */
    public static function lockBatchForUpdate(PDO $pdo, int $batchId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, name, generic_name, brand_name, batch_number, medicine_type, 
                    selling_price, purchase_price, quantity, minimum_stock_level, 
                    expiry_date, prescription_required, active 
             FROM medicines 
             WHERE id = ? 
             FOR UPDATE'
        );
        $stmt->execute([$batchId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Decrement batch stock by quantity.
     */
    public static function decrementStock(PDO $pdo, int $batchId, int $quantity): void
    {
        $stmt = $pdo->prepare('UPDATE medicines SET quantity = quantity - ? WHERE id = ?');
        $stmt->execute([$quantity, $batchId]);
    }

    /**
     * Increment batch stock by quantity.
     */
    public static function incrementStock(PDO $pdo, int $batchId, int $quantity): void
    {
        $stmt = $pdo->prepare('UPDATE medicines SET quantity = quantity + ? WHERE id = ?');
        $stmt->execute([$quantity, $batchId]);
    }

    /**
     * Retrieve all active categories for catalog filtering dropdowns.
     */
    public static function getAllCategories(): array
    {
        $stmt = db()->query('SELECT id, name FROM categories ORDER BY name ASC');
        return $stmt->fetchAll();
    }
}
