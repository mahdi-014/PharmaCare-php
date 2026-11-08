<?php
/**
 * PharmaCare — User Domain Model
 * Encapsulates user lookups, customer account registration, and profile persistence.
 *
 * Namespace: App\Models
 */

declare(strict_types=1);

namespace App\Models;

class User
{
    /**
     * Find user by primary key ID.
     */
    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare('SELECT id, full_name, username, email, phone, address, role, enabled, created_at FROM app_users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find active user by username or email for authentication.
     */
    public static function findByUsernameOrEmail(string $identifier): ?array
    {
        $stmt = db()->prepare('SELECT id, full_name, username, email, phone, address, password, role, enabled FROM app_users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$identifier, $identifier]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Register a new customer account.
     */
    public static function createCustomer(array $data): int
    {
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $stmt = db()->prepare(
            'INSERT INTO app_users (full_name, username, email, phone, address, password, role, enabled) 
             VALUES (?, ?, ?, ?, ?, ?, "CUSTOMER", 1)'
        );
        $stmt->execute([
            $data['full_name'],
            $data['username'],
            $data['email'],
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $hash
        ]);
        return (int)db()->lastInsertId();
    }

    /**
     * Update user profile information.
     */
    public static function updateProfile(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE app_users 
             SET full_name = ?, email = ?, phone = ?, address = ? 
             WHERE id = ?'
        );
        $stmt->execute([
            $data['full_name'],
            $data['email'],
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $id
        ]);
    }

    /**
     * Check if a username is already registered.
     */
    public static function existsByUsername(string $username, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM app_users WHERE username = ?';
        $params = [$username];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * Check if an email is already registered.
     */
    public static function existsByEmail(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM app_users WHERE email = ?';
        $params = [$email];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return ((int)$stmt->fetchColumn()) > 0;
    }
}
