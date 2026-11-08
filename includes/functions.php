<?php
declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $page = 'dashboard', array $params = []): string { return 'index.php?' . http_build_query(['page' => $page] + $params); }
function money(mixed $amount): string { return '৳' . number_format((float)$amount, 2); }
function nice_date(?string $date, bool $time = false): string { return $date ? date($time ? 'd M Y, h:i A' : 'd M Y', strtotime($date)) : '—'; }
function fetch_all(string $sql, array $params = []): array { $stmt = db()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(); }
function fetch_one(string $sql, array $params = []): ?array { $stmt = db()->prepare($sql); $stmt->execute($params); $data = $stmt->fetch(); return $data ?: null; }
function scalar(string $sql, array $params = []): mixed { $stmt = db()->prepare($sql); $stmt->execute($params); return $stmt->fetchColumn(); }
function execute_sql(string $sql, array $params = []): int { $stmt = db()->prepare($sql); $stmt->execute($params); return $stmt->rowCount(); }
function redirect(string $page, array $params = []): never { header('Location: ' . url($page, $params)); exit; }
function csrf(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf()) . '">'; }
function csrf_verify(): void {
    if (!isset($_POST['_csrf']) || !hash_equals(csrf(), (string)$_POST['_csrf'])) {
        http_response_code(403);
        exit('Invalid CSRF session token. Go back, refresh the form and try again.');
    }
}

function api_json_response(bool $success, string $message, mixed $data = null, int $statusCode = 200): never {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function api_csrf_verify(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? null;
    if (!$token) {
        $body = file_get_contents('php://input');
        if (!empty($body)) {
            $parsed = json_decode($body, true);
            if (is_array($parsed) && isset($parsed['_csrf'])) {
                $token = (string)$parsed['_csrf'];
            }
        }
    }
    if (!$token || !hash_equals(csrf(), (string)$token)) {
        api_json_response(false, 'Invalid or missing CSRF token.', null, 403);
    }
}

function api_get_json_input(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}
function user(): ?array { return $_SESSION['user'] ?? null; }
function is_admin(): bool { return (user()['role'] ?? '') === 'ADMIN'; }
function is_staff(): bool { return (user()['role'] ?? '') === 'STAFF'; }
function is_customer(): bool { return (user()['role'] ?? '') === 'CUSTOMER'; }
function is_staff_or_admin(): bool { return in_array(user()['role'] ?? '', ['STAFF', 'ADMIN'], true); }

function require_login(): void {
    static $verified = false;
    if (!user()) redirect('login');
    if ($verified) return;
    $row = fetch_one('SELECT id,full_name,username,role,enabled FROM app_users WHERE id=?', [user()['id']]);
    if (!$row || !(int)$row['enabled']) {
        unset($_SESSION['user']);
        flash('error', 'This account is no longer active. Please contact an administrator.');
        redirect('login');
    }
    $_SESSION['user'] = [
        'id' => (int)$row['id'], 'full_name' => $row['full_name'],
        'username' => $row['username'], 'role' => $row['role'],
    ];
    $verified = true;
}
function require_admin(): void { require_login(); if (!is_admin()) { http_response_code(403); include __DIR__ . '/../pages/forbidden.php'; exit; } }
function require_staff_or_admin(): void { require_login(); if (!is_staff_or_admin()) { http_response_code(403); include __DIR__ . '/../pages/forbidden.php'; exit; } }
function require_customer(): void { require_login(); if (!is_customer()) { http_response_code(403); include __DIR__ . '/../pages/forbidden.php'; exit; } }
function flash(string $type, string $text): void { $_SESSION['_flash'][] = ['type' => $type, 'text' => $text]; }
function show_flashes(): void {
    foreach ($_SESSION['_flash'] ?? [] as $f) {
        $kind = $f['type'] === 'success' ? 'success' : 'danger';
        echo '<div class="alert alert-' . $kind . ' alert-dismissible fade show" role="alert"><i class="bi bi-' . ($kind === 'success' ? 'check-circle' : 'exclamation-triangle') . ' me-2"></i>' . e($f['text']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['_flash']);
}
function medicine_status(array $m): string {
    if (!(int)$m['active']) return 'INACTIVE';
    $today = date('Y-m-d');
    if ($m['expiry_date'] < $today) return 'EXPIRED';
    if ((int)$m['quantity'] <= 0) return 'OUT_OF_STOCK';
    if ($m['expiry_date'] <= date('Y-m-d', strtotime('+30 days'))) return 'EXPIRING_SOON';
    if ((int)$m['quantity'] <= (int)$m['minimum_stock_level']) return 'LOW_STOCK';
    return 'AVAILABLE';
}
function status_label(string $status): string { return [
    'AVAILABLE' => 'Available', 'LOW_STOCK' => 'Low stock', 'OUT_OF_STOCK' => 'Out of stock',
    'EXPIRING_SOON' => 'Expiring soon', 'EXPIRED' => 'Expired', 'INACTIVE' => 'Inactive'
][$status] ?? $status; }
function status_badge(string $status): string { return [
    'AVAILABLE' => 'success', 'LOW_STOCK' => 'warning', 'OUT_OF_STOCK' => 'secondary',
    'EXPIRING_SOON' => 'info', 'EXPIRED' => 'danger', 'INACTIVE' => 'dark'
][$status] ?? 'secondary'; }
function medicine_query(): string { return 'SELECT m.*, c.name AS category_name, s.name AS supplier_name, s.company_name AS supplier_company FROM medicines m JOIN categories c ON c.id=m.category_id JOIN suppliers s ON s.id=m.supplier_id'; }
function medicine_types(): array { return ['TABLET' => 'Tablet','CAPSULE' => 'Capsule','SYRUP' => 'Syrup','INJECTION' => 'Injection','CREAM' => 'Cream','DROP' => 'Drop','INHALER' => 'Inhaler','OTHER' => 'Other']; }
function positive_int(mixed $value, string $label, bool $allowZero = false): int {
    if (!is_scalar($value) || !filter_var((string)$value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $allowZero ? 0 : 1]])) {
        // PHP filter_var returns integer 0 when allowZero=true; strict comparison handles it.
        if ($allowZero && (string)$value === '0') return 0;
        throw new DomainException($label . ' must be ' . ($allowZero ? 'zero or a positive integer.' : 'a positive integer.'));
    }
    return (int)$value;
}
function required_text(string $name, int $limit = 120): string {
    $value = trim((string)($_POST[$name] ?? ''));
    if ($value === '' || (function_exists('mb_strlen') ? mb_strlen($value) : strlen($value)) > $limit) throw new DomainException(ucwords(str_replace('_', ' ', $name)) . ' is required (max ' . $limit . ' characters).');
    return $value;
}
function optional_text(string $name, int $limit = 255): ?string {
    $value = trim((string)($_POST[$name] ?? ''));
    if ((function_exists('mb_strlen') ? mb_strlen($value) : strlen($value)) > $limit) throw new DomainException(ucwords(str_replace('_', ' ', $name)) . ' is too long.');
    return $value !== '' ? $value : null;
}
function price_value(mixed $value, string $label, bool $zero = false): string {
    if (!is_scalar($value) || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', (string)$value) || (float)$value < ($zero ? 0 : 0.01)) {
        throw new DomainException($label . ' must be a valid amount with up to 2 decimals.');
    }
    return number_format((float)$value, 2, '.', '');
}
function page_title(string $page): string { return [
    'dashboard' => 'Dashboard', 'medicines' => 'Medicine Inventory', 'medicine_form' => 'Medicine Details',
    'sales' => 'Sales History', 'sale_form' => 'Create New Sale', 'invoice' => 'Sales Invoice',
    'categories' => 'Medicine Categories', 'suppliers' => 'Suppliers', 'users' => 'User Management',
    'register' => 'Customer Registration', 'catalog' => 'Medicine Catalog', 'profile' => 'My Account Profile',
    'cart' => 'Shopping Cart', 'checkout' => 'Order Checkout', 'order_confirmation' => 'Order Confirmation',
    'orders' => 'Orders Queue', 'order_detail' => 'Order Details', 'my_orders' => 'My Orders'
][$page] ?? 'PharmaCare'; }

// -------------------------------------------------------------
// Order Lifecycle & Prescription Status Helpers
// -------------------------------------------------------------

function order_status_badge(string $status): string
{
    return match($status) {
        'PENDING' => 'warning text-dark',
        'CONFIRMED' => 'info text-dark',
        'PROCESSING' => 'primary',
        'READY' => 'secondary',
        'DISPATCHED' => 'dark',
        'COMPLETED' => 'success',
        'CANCELLED' => 'danger',
        default => 'secondary'
    };
}

function order_status_label(string $status): string
{
    return match($status) {
        'PENDING' => 'Pending Review',
        'CONFIRMED' => 'Confirmed',
        'PROCESSING' => 'Processing / Packing',
        'READY' => 'Ready for Pickup/Dispatch',
        'DISPATCHED' => 'Out for Delivery',
        'COMPLETED' => 'Completed',
        'CANCELLED' => 'Cancelled',
        default => $status
    };
}

function prescription_status_badge(string $status): string
{
    return match($status) {
        'NOT_REQUIRED' => 'light text-muted border',
        'PENDING_REVIEW' => 'warning text-dark',
        'VERIFIED' => 'success',
        'REJECTED' => 'danger',
        default => 'secondary'
    };
}

function prescription_status_label(string $status): string
{
    return match($status) {
        'NOT_REQUIRED' => 'Not Required',
        'PENDING_REVIEW' => 'Needs Pharmacist Review',
        'VERIFIED' => 'Verified & Approved',
        'REJECTED' => 'Rejected',
        default => $status
    };
}

/**
 * Returns allowed next statuses from a given current status.
 * Enforces forward progression and blocks terminal state mutations.
 */
function get_allowed_order_transitions(string $currentStatus): array
{
    return match($currentStatus) {
        // Simplified Streamlined Flow with Fast-Track Completion and Backward Compatibility
        'PENDING' => ['CONFIRMED', 'COMPLETED', 'CANCELLED'],
        'CONFIRMED' => ['COMPLETED', 'CANCELLED', 'PROCESSING', 'READY', 'DISPATCHED'],
        'PROCESSING' => ['COMPLETED', 'CANCELLED', 'READY'],
        'READY' => ['COMPLETED', 'CANCELLED', 'DISPATCHED'],
        'DISPATCHED' => ['COMPLETED', 'CANCELLED'],
        'COMPLETED' => [], // Terminal state
        'CANCELLED' => [], // Terminal state
        default => []
    };
}

// -------------------------------------------------------------
// Customer Shopping Cart Session Helpers
// -------------------------------------------------------------

function cart_get_items(): array
{
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    return $_SESSION['cart'];
}

function cart_get_count(): int
{
    $totalUnits = 0;
    foreach (cart_get_items() as $item) {
        $totalUnits += (int)($item['quantity'] ?? 0);
    }
    return $totalUnits;
}

function cart_get_line_count(): int
{
    return count(cart_get_items());
}

function cart_get_total(): float
{
    $grandTotal = 0.0;
    foreach (cart_get_items() as $item) {
        $grandTotal += ((float)($item['price'] ?? 0)) * ((int)($item['quantity'] ?? 0));
    }
    return round($grandTotal, 2);
}

function cart_requires_prescription(): bool
{
    foreach (cart_get_items() as $item) {
        if (!empty($item['requires_prescription'])) {
            return true;
        }
    }
    return false;
}

function cart_add_item(array $medicine, int $quantity = 1): int
{
    $medicineId = (int)$medicine['id'];
    $maxStock = (int)$medicine['quantity'];

    if ($maxStock <= 0) {
        throw new DomainException('This medicine is currently out of stock.');
    }
    if (!(int)$medicine['active']) {
        throw new DomainException('This medicine is currently inactive and cannot be ordered.');
    }
    if (!empty($medicine['expiry_date']) && $medicine['expiry_date'] < date('Y-m-d')) {
        throw new DomainException('This medicine batch has expired and cannot be ordered.');
    }

    $cart = cart_get_items();
    $currentQty = isset($cart[$medicineId]) ? (int)$cart[$medicineId]['quantity'] : 0;
    $targetQty = $currentQty + $quantity;

    if ($targetQty > $maxStock) {
        if ($currentQty >= $maxStock) {
            throw new DomainException('Cannot add more units. You already have the maximum available stock (' . $maxStock . ' units) in your cart.');
        }
        $targetQty = $maxStock;
    }

    $_SESSION['cart'][$medicineId] = [
        'id' => $medicineId,
        'name' => (string)$medicine['name'],
        'category_name' => (string)($medicine['category_name'] ?? 'General'),
        'price' => (float)$medicine['selling_price'],
        'quantity' => $targetQty,
        'batch_number' => (string)($medicine['batch_number'] ?? ''),
        'max_stock' => $maxStock,
        'requires_prescription' => !empty($medicine['prescription_required']),
    ];

    return $targetQty;
}

function cart_update_item(int $medicineId, int $quantity, int $maxStock): void
{
    if (!isset($_SESSION['cart'][$medicineId])) {
        throw new DomainException('Item is not in your cart.');
    }

    if ($quantity <= 0) {
        unset($_SESSION['cart'][$medicineId]);
        return;
    }

    $clampedQty = min($quantity, $maxStock);
    $_SESSION['cart'][$medicineId]['quantity'] = $clampedQty;
    $_SESSION['cart'][$medicineId]['max_stock'] = $maxStock;
}

function cart_remove_item(int $medicineId): void
{
    unset($_SESSION['cart'][$medicineId]);
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
}
