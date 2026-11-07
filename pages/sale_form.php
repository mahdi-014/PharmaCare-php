<?php
/**
 * PharmaCare — Point-of-Sale (POS) Billing View
 * Dynamic multi-item checkout interface with real-time total calculations.
 */

declare(strict_types=1);

$medicines = fetch_all('SELECT id, name, brand_name, batch_number, quantity, selling_price, prescription_required FROM medicines WHERE active = 1 AND expiry_date >= CURDATE() AND quantity > 0 ORDER BY name, batch_number');
?>

<div class="page-intro">
    <div>
        <h2>Point of Sale (New Invoice)</h2>
        <p>Only active, in-stock, and unexpired medicines are available for sale.</p>
    </div>
    <a href="<?= e(url('sales')) ?>" class="btn btn-light border">
        <i class="bi bi-arrow-left me-1"></i>Sales History
    </a>
</div>

<form action="actions.php" method="post" id="saleForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="sale_create">

    <!-- Customer Information -->
    <div class="card mb-4">
        <div class="card-header">
            <strong>Customer Details</strong> <span class="text-secondary small">(Optional)</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="customer_name">Customer Name</label>
                    <input class="form-control" id="customer_name" name="customer_name" maxlength="100" placeholder="Walk-in Customer">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="customer_phone">Contact Phone</label>
                    <input class="form-control" id="customer_phone" name="customer_phone" maxlength="30" placeholder="01XXXXXXXXX">
                </div>
            </div>
        </div>
    </div>

    <!-- Medicine Items Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <strong>Dispensed Medicines</strong>
                <div class="small text-secondary">Inventory quantities are re-verified with row locks on submit.</div>
            </div>
            <button type="button" id="addRow" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-plus-lg me-1"></i>Add Item Row
            </button>
        </div>

        <div class="table-responsive">
            <table class="table" id="saleTable">
                <thead>
                    <tr>
                        <th style="min-width: 285px">Medicine &amp; Batch</th>
                        <th>In Stock</th>
                        <th style="width: 120px">Quantity</th>
                        <th>Unit Price</th>
                        <th>Line Total</th>
                        <th style="width: 40px"></th>
                    </tr>
                </thead>
                <tbody id="saleRows"></tbody>
            </table>
        </div>
    </div>

    <!-- Financial Summary & Submission -->
    <div class="row justify-content-end">
        <div class="col-lg-5 col-xl-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-secondary">Subtotal</span>
                        <strong id="subtotalText">৳0.00</strong>
                    </div>

                    <div class="mb-3">
                        <label for="discountInput" class="form-label">Discount (৳)</label>
                        <input type="number" min="0" step="0.01" name="discount" value="0" id="discountInput" class="form-control">
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="h5 mb-0">Grand Total</span>
                        <strong class="h4 text-success mb-0" id="grandTotalText">৳0.00</strong>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100" <?= !$medicines ? 'disabled' : '' ?>>
                        <i class="bi bi-check-circle me-1"></i>Complete Sale
                    </button>

                    <?php if (!$medicines): ?>
                        <div class="form-hint text-danger mt-2">
                            No saleable medicines available in stock.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Dynamic Sale Row Template -->
<template id="saleRowTemplate">
    <tr class="sale-row">
        <td>
            <select class="form-select medicine-select" name="medicine_id[]" required>
                <option value="">Select Medicine</option>
                <?php foreach ($medicines as $m): ?>
                    <option value="<?= e((string)$m['id']) ?>"
                            data-price="<?= e((string)$m['selling_price']) ?>"
                            data-stock="<?= e((string)$m['quantity']) ?>">
                        <?= e($m['name']) ?> — <?= e($m['batch_number']) ?><?= $m['prescription_required'] ? ' (Rx)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="stock-cell text-secondary">—</td>
        <td>
            <input name="quantity[]" type="number" min="1" step="1" value="1" class="form-control quantity-input" required>
        </td>
        <td class="price-cell">৳0.00</td>
        <td class="line-total fw-semibold">৳0.00</td>
        <td>
            <button class="btn btn-sm btn-light border remove-row" type="button" aria-label="Remove item">
                <i class="bi bi-x-lg"></i>
            </button>
        </td>
    </tr>
</template>
