<?php
/**
 * CIS324 Web Engineering - Topic 13: Pharmacy Management System
 * API Endpoint: Shopping Cart Operations (Q6 Vanilla JS Fetch)
 *
 * POST /api/cart.php
 * Supports cart addition (action=add) and quantity updates/removals (action=update).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$controller = new \App\Controllers\CartController();

$action = (string)($_GET['action'] ?? '');
if ($action === '') {
    $input = api_get_json_input();
    $action = (string)($input['action'] ?? 'add');
}

if ($action === 'update') {
    $controller->updateApi();
} else {
    $controller->addApi();
}
