<?php
/**
 * PharmaCare — Sales Ledger & Invoice History View
 * Displays all completed transactions, invoice IDs, and financial summaries.
 */

declare(strict_types=1);

$sales = fetch_all('SELECT s.*, u.full_name AS sold_by_name, u.username AS sold_by_username, COALESCE((SELECT SUM(si.quantity) FROM sale_items si WHERE si.sale_id = s.id), 0) AS total_quantity FROM sales s JOIN app_users u ON u.id = s.sold_by_id ORDER BY s.sale_date DESC, s.id DESC');
?>

<div class="page-intro">
    <div>
        <h2>Sales History</h2>
        <p><?= count($sales) ?> completed invoice(s)</p>
    </div>
    <a href="<?= e(url('sale_form')) ?>" class="btn btn-primary">
        <i class="bi bi-cart-plus me-1"></i>New Sale
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date &amp; Time</th>
                    <th>Customer</th>
                    <th>Sold By</th>
                    <th>Items Sold</th>
                    <th>Total Amount</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $s): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($s['invoice_number']) ?></td>
                        <td><?= e(nice_date($s['sale_date'], true)) ?></td>
                        <td>
                            <?= e($s['customer_name'] ?: 'Walk-in Customer') ?>
                            <span class="subtext"><?= e($s['customer_phone'] ?? '') ?></span>
                        </td>
                        <td><?= e($s['sold_by_name']) ?></td>
                        <td><?= e((string)$s['total_quantity']) ?></td>
                        <td>
                            <strong><?= money($s['total_amount']) ?></strong>
                            <?php if ((float)$s['discount'] > 0): ?>
                                <span class="subtext text-success">Discount: <?= money($s['discount']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?= e(url('invoice', ['id' => $s['id']])) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-receipt me-1"></i>Invoice
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$sales): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <i class="bi bi-receipt"></i>
                                <p>No transactions have been recorded yet.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
