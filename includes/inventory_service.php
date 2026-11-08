<?php
/**
 * PharmaCare — Unified Inventory Service Legacy Bridge
 * Bridges legacy global InventoryService and procedural functions to App\Services\InventoryService.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Services/InventoryService.php';

if (!class_exists('InventoryService', false)) {
    class_alias(\App\Services\InventoryService::class, 'InventoryService');
}

// Procedural helper functions matching specification
function deduct_batch_stock(PDO $pdo, int $batch_id, int $quantity): array
{
    return \App\Services\InventoryService::deductBatchStock($pdo, $batch_id, $quantity);
}

function restore_batch_stock(PDO $pdo, int $batch_id, int $quantity): int
{
    return \App\Services\InventoryService::restoreBatchStock($pdo, $batch_id, $quantity);
}

function allocate_fefo_stock(PDO $pdo, int $medicine_id, int $requested_quantity): array
{
    return \App\Services\InventoryService::allocateFefoStock($pdo, $medicine_id, $requested_quantity);
}
