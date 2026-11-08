<?php
/**
 * PharmaCare API — Asynchronous Medicine Search & Catalog Filtering Endpoint
 * Delegated to App\Controllers\CatalogController::searchApi()
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

(new \App\Controllers\CatalogController())->searchApi();
