<?php
/**
 * PharmaCare API — Asynchronous Add-to-Cart Endpoint
 * Delegated to App\Controllers\CartController::addApi()
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

(new \App\Controllers\CartController())->addApi();
