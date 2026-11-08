<?php
/**
 * PharmaCare — Secure Authenticated Prescription File Streaming Endpoint
 * Authenticates user, prevents direct URL access, enforces strict RBAC / IDOR checks,
 * validates against directory traversal, and streams files with proper MIME headers.
 */

declare(strict_types=1);

require_login();

$orderId = positive_int($_GET['order_id'] ?? 0, 'Order ID', true);
$rxId = positive_int($_GET['id'] ?? 0, 'Prescription ID', true);

if (!$orderId && !$rxId) {
    http_response_code(400);
    exit('Order ID or Prescription ID is required.');
}

$sql = 'SELECT p.*, o.customer_id, o.order_number 
        FROM prescriptions p 
        JOIN orders o ON o.id = p.order_id 
        WHERE ' . ($rxId ? 'p.id = ?' : 'p.order_id = ?') . ' LIMIT 1';
$prescription = fetch_one($sql, [$rxId ?: $orderId]);

if (!$prescription) {
    http_response_code(404);
    exit('Prescription record not found.');
}

// Strict RBAC / IDOR Authorization Gate
// Only Staff/Admin or the owning Customer may view/stream this prescription
if (is_customer() && (int)$prescription['customer_id'] !== (int)user()['id']) {
    http_response_code(403);
    exit('Access denied: You are not authorized to view this prescription document.');
}

if (!is_staff_or_admin() && !is_customer()) {
    http_response_code(403);
    exit('Access denied.');
}

// Strict Directory Traversal Protection & Resilient Dual-Path Storage Resolution
$canonicalBase = realpath(dirname(__DIR__) . '/storage/prescriptions');
$legacyBase = realpath(dirname(__DIR__, 2) . '/storage/prescriptions');

$relativePath = ltrim((string)$prescription['file_path'], '/\\');
$filePath = dirname(__DIR__) . '/' . $relativePath;
$realFilePath = realpath($filePath);

// Check legacy parent storage directory fallback if not found in canonical location
if ((!$realFilePath || !file_exists($realFilePath)) && $legacyBase) {
    $legacyFilePath = dirname(__DIR__, 2) . '/' . $relativePath;
    $legacyReal = realpath($legacyFilePath);
    if ($legacyReal && file_exists($legacyReal) && str_starts_with($legacyReal, $legacyBase)) {
        $realFilePath = $legacyReal;
        // Self-heal: copy to canonical storage directory for long-term consistency
        if ($canonicalBase && is_dir($canonicalBase)) {
            $destPath = dirname(__DIR__) . '/' . $relativePath;
            $destDir = dirname($destPath);
            if (!is_dir($destDir)) {
                @mkdir($destDir, 0755, true);
            }
            @copy($legacyReal, $destPath);
        }
    }
}

$isAllowed = $realFilePath && file_exists($realFilePath) && (
    ($canonicalBase && str_starts_with($realFilePath, $canonicalBase)) ||
    ($legacyBase && str_starts_with($realFilePath, $legacyBase))
);

if (!$isAllowed) {
    http_response_code(404);
    exit('Prescription file could not be located on disk.');
}

// Stream file securely to browser
$mimeType = (string)$prescription['mime_type'];
$fileName = (string)$prescription['original_filename'];
$fileSize = filesize($realFilePath);

// Clean any previous output buffer to avoid corrupting binary payloads
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . rawurlencode($fileName) . '"');
header('Content-Length: ' . (string)$fileSize);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('X-Content-Type-Options: nosniff');

readfile($realFilePath);
exit;
