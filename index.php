<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$page = (string)($_GET['page'] ?? 'dashboard');
$routes = ['login', 'dashboard', 'medicines', 'medicine_form', 'categories', 'suppliers', 'sales', 'sale_form', 'invoice', 'users'];
if (!in_array($page, $routes, true)) { http_response_code(404); $page='not_found'; }
if ($page === 'login') {
    if (user()) redirect('dashboard');
    include __DIR__ . '/pages/login.php';
    exit;
}
require_login();
if (in_array($page, ['categories','suppliers','users','medicine_form'], true)) require_admin();
// Only known allowlisted page files may be included.
require __DIR__ . '/includes/header.php';
include __DIR__ . '/pages/' . $page . '.php';
require __DIR__ . '/includes/footer.php';
