<?php
/**
 * PharmaCare — Bootstrap & Initialization Module
 * Handles session parameters, timezone, HTTP security headers, and PDO connection.
 */

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config.php';
date_default_timezone_set($config['timezone'] ?? 'Asia/Dhaka');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Enforce 30-minute idle session timeout (Q7 Session Security)
if (!empty($_SESSION['user'])) {
    $now = time();
    $maxIdleSeconds = 1800; // 30 minutes
    if (isset($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity'] > $maxIdleSeconds)) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        session_start();
        $_SESSION['_flash'][] = ['type' => 'error', 'text' => 'Session expired due to 30 minutes of inactivity. Please sign in again.'];
        header('Location: index.php?page=login');
        exit;
    }
    $_SESSION['last_activity'] = $now;
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

require_once __DIR__ . '/functions.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        global $config;
        $dsn = 'mysql:host=' . $config['db_host'] . ';port=' . (int)($config['db_port'] ?? 3306) . ';dbname=' . $config['db_name'] . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, (string)$config['db_user'], (string)$config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET time_zone = '+06:00'");
        } catch (PDOException $e) {
            http_response_code(500);
            die("
            <!doctype html>
            <html lang='en'>
            <head>
                <meta charset='utf-8'>
                <title>Database Connection Issue | PharmaCare</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f8fafc; color: #1e293b; padding: 40px 20px; }
                    .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
                    h2 { color: #dc2626; margin-top: 0; font-size: 20px; }
                    .alert { background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
                    ul { padding-left: 20px; font-size: 14px; line-height: 1.6; }
                    code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-family: monospace; color: #0f172a; }
                </style>
            </head>
            <body>
                <div class='card'>
                    <h2>Database Connection Failed</h2>
                    <div class='alert'>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>
                    <p>Please check your <code>config.php</code> credentials:</p>
                    <ul>
                        <li><strong>Host:</strong> <code>" . htmlspecialchars((string)($config['db_host'] ?? ''), ENT_QUOTES, 'UTF-8') . "</code></li>
                        <li><strong>Database:</strong> <code>" . htmlspecialchars((string)($config['db_name'] ?? ''), ENT_QUOTES, 'UTF-8') . "</code></li>
                        <li><strong>User:</strong> <code>" . htmlspecialchars((string)($config['db_user'] ?? ''), ENT_QUOTES, 'UTF-8') . "</code></li>
                    </ul>
                    <p style='font-size: 13px; color: #64748b;'>Make sure your database is created in InfinityFree cPanel and <code>infinityfree_import.sql</code> has been imported.</p>
                </div>
            </body>
            </html>
            ");
        }
    }
    return $pdo;
}
