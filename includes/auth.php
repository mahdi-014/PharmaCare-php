<?php
/**
 * CIS324 Web Engineering - Topic 13: Pharmacy Management System
 * Authentication & Access Control Module (Q7 Alignment)
 *
 * Enforces session lifecycle, 30-minute idle inactivity timeout, and role-based guards.
 */

declare(strict_types=1);

// Enforce 30-minute idle session timeout (Q7 Session Security)
const SESSION_IDLE_TIMEOUT = 1800;

if (!empty($_SESSION['user'])) {
    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity'] > SESSION_IDLE_TIMEOUT)) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        session_start();
        $_SESSION['_flash'][] = [
            'type' => 'error',
            'text' => 'Session expired due to 30 minutes of inactivity. Please sign in again.'
        ];
        header('Location: index.php?page=login&timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = $now;
}

/**
 * Get current authenticated user session data.
 */
function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Check if current user is an Administrator.
 */
function is_admin(): bool
{
    return (user()['role'] ?? '') === 'ADMIN';
}

/**
 * Check if current user is Staff.
 */
function is_staff(): bool
{
    return (user()['role'] ?? '') === 'STAFF';
}

/**
 * Check if current user is a Customer.
 */
function is_customer(): bool
{
    return (user()['role'] ?? '') === 'CUSTOMER';
}

/**
 * Check if current user is Staff or Administrator.
 */
function is_staff_or_admin(): bool
{
    return in_array(user()['role'] ?? '', ['STAFF', 'ADMIN'], true);
}

/**
 * Guard: Require authenticated user session and verify active account in database.
 */
function require_login(): void
{
    static $verified = false;

    if (!user()) {
        redirect('login');
    }

    if ($verified) {
        return;
    }

    $row = fetch_one('SELECT id, full_name, username, role, enabled FROM app_users WHERE id = ?', [user()['id']]);
    if (!$row || !(int)$row['enabled']) {
        unset($_SESSION['user']);
        flash('error', 'This account is no longer active. Please contact an administrator.');
        redirect('login');
    }

    $_SESSION['user'] = [
        'id'        => (int)$row['id'],
        'full_name' => $row['full_name'],
        'username'  => $row['username'],
        'role'      => $row['role'],
    ];

    $verified = true;
}

/**
 * Guard: Require Administrator role.
 */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        include dirname(__DIR__) . '/pages/forbidden.php';
        exit;
    }
}

/**
 * Guard: Require Staff or Administrator role.
 */
function require_staff_or_admin(): void
{
    require_login();
    if (!is_staff_or_admin()) {
        http_response_code(403);
        include dirname(__DIR__) . '/pages/forbidden.php';
        exit;
    }
}

/**
 * Guard: Require Customer role.
 */
function require_customer(): void
{
    require_login();
    if (!is_customer()) {
        http_response_code(403);
        include dirname(__DIR__) . '/pages/forbidden.php';
        exit;
    }
}
