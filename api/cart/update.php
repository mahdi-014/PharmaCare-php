<?php
/**
 * PharmaCare API — Asynchronous Cart Quantity Update Endpoint
 * Delegated to App\Controllers\CartController::updateApi()
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

(new \App\Controllers\CartController())->updateApi();
