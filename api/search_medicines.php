<?php
/**
 * CIS324 Web Engineering - Topic 13: Pharmacy Management System
 * API Endpoint: Live Medicine Catalog Search (Q6 Vanilla JS Fetch)
 *
 * GET /api/search_medicines.php?q={query}&category_id={id}
 * Returns active medicines matching keyword and/or category.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

(new \App\Controllers\CatalogController())->searchApi();
