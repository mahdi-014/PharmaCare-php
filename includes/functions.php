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
        http_response_code(419);
        exit('Invalid session token. Go back, refresh the form and try again.');
    }
}
function user(): ?array { return $_SESSION['user'] ?? null; }
function is_admin(): bool { return (user()['role'] ?? '') === 'ADMIN'; }
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
    'categories' => 'Medicine Categories', 'suppliers' => 'Suppliers', 'users' => 'User Management'
][$page] ?? 'PharmaCare'; }
