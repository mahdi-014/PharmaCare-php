<?php
/**
 * PharmaCare — Sales Invoice View
 * Itemized printable receipt with customer and seller information.
 */

declare(strict_types=1);

$id = (int)($_GET['id'] ?? 0);
$orderId = (int)($_GET['order_id'] ?? 0);

$sale = null;
if ($id) {
    $sale = fetch_one('SELECT s.*, u.full_name AS sold_by_name, u.username AS sold_by_username FROM sales s JOIN app_users u ON u.id = s.sold_by_id WHERE s.id = ?', [$id]);
} elseif ($orderId) {
    $sale = fetch_one('SELECT s.*, u.full_name AS sold_by_name, u.username AS sold_by_username FROM sales s JOIN app_users u ON u.id = s.sold_by_id WHERE s.order_id = ?', [$orderId]);
    if ($sale) {
        $id = (int)$sale['id'];
    }
}

if (!$sale) {
    echo '<div class="alert alert-danger">Invoice not found.</div>';
    return;
}

$items = fetch_all('SELECT si.*, m.name AS medicine_name, m.generic_name, m.batch_number FROM sale_items si JOIN medicines m ON m.id = si.medicine_id WHERE si.sale_id = ? ORDER BY si.id', [$id]);
?>

<!-- Action Bar (Hidden during printing) -->
<div class="d-flex justify-content-end gap-2 mb-3 no-print">
    <a href="<?= e(url('sales')) ?>" class="btn btn-light border">
        <i class="bi bi-arrow-left me-1"></i>Sales History
    </a>
    <a href="<?= e(url('sale_form')) ?>" class="btn btn-outline-primary">
        <i class="bi bi-cart-plus me-1"></i>New Sale
    </a>
    <button type="button" onclick="window.print()" class="btn btn-primary">
        <i class="bi bi-printer me-1"></i>Print Invoice
    </button>
</div>

<!-- Printable Invoice Card -->
<section class="invoice-sheet">
    <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 text-success h4 fw-bold">
                <i class="bi bi-capsule-pill"></i>PharmaCare
            </div>
            <div class="text-secondary small">
                Pharmacy Management System<br>
                Dhaka, Bangladesh<br>
                Contact: +880 1XXXXXXXXX
            </div>
        </div>
        <div class="text-end">
            <div class="invoice-title">INVOICE</div>
            <strong><?= e($sale['invoice_number']) ?></strong>
            <div class="small text-secondary"><?= e(nice_date($sale['sale_date'], true)) ?></div>
            <div class="mt-1">
                <?php if (($sale['channel'] ?? 'POS') === 'ONLINE'): ?>
                    <span class="badge text-bg-primary">Online Order</span>
                <?php else: ?>
                    <span class="badge text-bg-secondary">Counter POS</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <div class="small text-secondary text-uppercase fw-semibold">Billed To</div>
            <strong><?= e($sale['customer_name'] ?: 'Walk-in Customer') ?></strong>
            <div class="text-secondary"><?= e($sale['customer_phone'] ?? '') ?></div>
        </div>
        <div class="col-6 text-end">
            <div class="small text-secondary text-uppercase fw-semibold">Dispensed By</div>
            <strong><?= e($sale['sold_by_name']) ?></strong>
            <div class="text-secondary">@<?= e($sale['sold_by_username']) ?></div>
        </div>
    </div>

    <div class="table-responsive mb-4">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 5%">#</th>
                    <th>Medicine Description</th>
                    <th>Batch</th>
                    <th class="text-end">Quantity</th>
                    <th class="text-end">Unit Price</th>
                    <th class="text-end">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i => $it): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <strong><?= e($it['medicine_name']) ?></strong>
                            <span class="subtext"><?= e($it['generic_name']) ?></span>
                        </td>
                        <td><?= e($it['batch_number']) ?></td>
                        <td class="text-end"><?= e((string)$it['quantity']) ?></td>
                        <td class="text-end"><?= money($it['unit_price']) ?></td>
                        <td class="text-end fw-semibold"><?= money($it['line_total']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="row justify-content-end">
        <div class="col-sm-6 col-lg-5">
            <div class="d-flex justify-content-between py-2">
                <span class="text-secondary">Subtotal</span>
                <span><?= money($sale['subtotal']) ?></span>
            </div>
            <div class="d-flex justify-content-between py-2">
                <span class="text-secondary">Discount</span>
                <span>&minus; <?= money($sale['discount']) ?></span>
            </div>
            <div class="d-flex justify-content-between border-top pt-3 mt-1">
                <strong class="h5">Grand Total</strong>
                <strong class="h4 text-success"><?= money($sale['total_amount']) ?></strong>
            </div>
        </div>
    </div>

    <div class="border-top mt-5 pt-3 text-center text-secondary small">
        Thank you for choosing PharmaCare. Please keep this invoice for your dispensary records.
    </div>
</section>
