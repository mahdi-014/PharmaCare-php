<?php
/**
 * PharmaCare API — Asynchronous Order Status & Fulfillment Tracking Endpoint
 * Delegated to App\Controllers\OrderController::statusApi()
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

(new \App\Controllers\OrderController())->statusApi();
