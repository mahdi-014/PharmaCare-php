<?php
/**
 * PharmaCare — Customer Order History & Self-Service Tracking View
 * Allows authenticated customers to view their order history,
 * check real-time fulfillment status, and access order details.
 * Strictly bound to session user ID to guarantee IDOR prevention.
 */

declare(strict_types=1);

require_customer();

$customerId = (int)user()['id'];
$orders = fetch_all(
    'SELECT o.*, 
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count 
     FROM orders o 
     WHERE o.customer_id = ? 
     ORDER BY o.id DESC',
    [$customerId]
);
?>

<div class="page-intro mb-4">
    <div>
        <h2>My Medicine Orders</h2>
        <p>Track the real-time fulfillment status and review past prescription and medicine orders.</p>
    </div>
    <div>
        <a href="<?= e(url('catalog')) ?>" class="btn btn-outline-success btn-sm">
            <i class="bi bi-cart-plus me-1"></i>New Order
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <strong>Order History (<?= count($orders) ?>)</strong>
        <span class="text-secondary small">Sorted by newest order</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-secondary">
                <tr>
                    <th>Order Reference</th>
                    <th>Date Placed</th>
                    <th>Items</th>
                    <th class="text-end">Total Amount</th>
                    <th>Payment Method</th>
                    <th>Prescription</th>
                    <th>Order Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('order_detail', ['id' => $o['id']])) ?>" class="fw-bold text-success text-decoration-none">
                                <?= e($o['order_number']) ?>
                            </a>
                        </td>
                        <td class="small text-secondary">
                            <?= e(nice_date($o['created_at'], true)) ?>
                        </td>
                        <td class="small">
                            <?= (int)$o['item_count'] ?> item(s)
                        </td>
                        <td class="text-end fw-bold text-dark">
                            <?= money($o['total_amount']) ?>
                        </td>
                        <td>
                            <span class="badge text-bg-light border text-dark" style="font-size: 11px;">
                                <?= $o['payment_method'] === 'COD' ? 'Cash on Delivery' : 'Store Pickup' ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!(int)$o['prescription_required']): ?>
                                <span class="badge text-bg-light border text-muted" style="font-size: 11px;">
                                    OTC / No Rx
                                </span>
                            <?php else: ?>
                                <span class="badge text-bg-<?= e(prescription_status_badge($o['prescription_status'])) ?>" style="font-size: 11px;">
                                    <i class="bi bi-prescription2 me-1"></i><?= e(prescription_status_label($o['prescription_status'])) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge text-bg-<?= e(order_status_badge($o['order_status'])) ?>">
                                <?= e(order_status_label($o['order_status'])) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="<?= e(url('order_detail', ['id' => $o['id']])) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>View Details
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-secondary">
                            <i class="bi bi-bag-x fs-1 d-block mb-2 text-muted"></i>
                            <h5>No Orders Placed Yet</h5>
                            <p class="small mb-3">You have not placed any online medicine orders so far.</p>
                            <a href="<?= e(url('catalog')) ?>" class="btn btn-sm btn-success">
                                <i class="bi bi-capsule-pill me-1"></i>Browse Medicine Catalog
                            </a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
