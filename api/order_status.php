<?php
/**
 * CIS324 Web Engineering - Topic 13: Pharmacy Management System
 * API Endpoint: Live Order Status Tracking (Q6 Vanilla JS Fetch)
 *
 * GET /api/order_status.php?id={order_id}
 * Returns order lifecycle status, prescription status, and historical timeline for live polling.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

(new \App\Controllers\OrderController())->statusApi();
