<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$page = (string)($_GET['page'] ?? (user() && is_customer() ? 'catalog' : 'dashboard'));
$routes = [
    'login', 'register', 'dashboard', 'medicines', 'medicine_form',
    'categories', 'suppliers', 'sales', 'sale_form', 'invoice', 'users',
    'catalog', 'profile', 'cart', 'checkout', 'order_confirmation'
];

if (!in_array($page, $routes, true)) {
    http_response_code(404);
    $page = 'not_found';
}

// Public unauthenticated routes
if ($page === 'login') {
    if (user()) redirect(is_customer() ? 'catalog' : 'dashboard');
    include __DIR__ . '/pages/login.php';
    exit;
}

if ($page === 'register') {
    if (user()) redirect(is_customer() ? 'catalog' : 'dashboard');
    include __DIR__ . '/pages/register.php';
    exit;
}

// Allow guests to browse the medicine catalog without logging in
if ($page === 'catalog' && !user()) {
    require __DIR__ . '/includes/header.php';
    include __DIR__ . '/pages/catalog.php';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Protected routes require authentication
require_login();

// Strict RBAC Enforcement
$adminOnlyPages = ['users', 'categories', 'suppliers', 'medicine_form'];
$staffOrAdminPages = ['dashboard', 'medicines', 'sales', 'sale_form', 'invoice'];
$customerOnlyPages = ['cart', 'profile', 'checkout', 'order_confirmation'];

if (in_array($page, $adminOnlyPages, true)) {
    require_admin();
} elseif (in_array($page, $staffOrAdminPages, true)) {
    require_staff_or_admin();
} elseif (in_array($page, $customerOnlyPages, true)) {
    require_customer();
}

// Render allowlisted page wrapped in standard layout
require __DIR__ . '/includes/header.php';
include __DIR__ . '/pages/' . $page . '.php';
require __DIR__ . '/includes/footer.php';
