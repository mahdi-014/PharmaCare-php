<?php
/**
 * PharmaCare — Customer Order Confirmation View
 * Displays finalized order receipt, status, items snapshot, delivery coordinates,
 * and dispensary fulfillment workflow steps.
 * Enforces strict IDOR protection: customers can only view their own orders.
 */

declare(strict_types=1);

require_login();

$orderId = positive_int($_GET['id'] ?? 0, 'Order ID');
$order = fetch_one('SELECT * FROM orders WHERE id = ?', [$orderId]);

if (!$order) {
    echo '<div class="alert alert-danger">Order not found.</div>';
    return;
}

// Strict IDOR Protection: Customers may only view their own orders
if (is_customer() && (int)$order['customer_id'] !== (int)user()['id']) {
    http_response_code(403);
    include __DIR__ . '/forbidden.php';
    exit;
}

$items = fetch_all(
    'SELECT oi.*, m.name AS medicine_name, m.generic_name, m.batch_number, m.medicine_type, c.name AS category_name 
     FROM order_items oi 
     JOIN medicines m ON m.id = oi.medicine_id 
     LEFT JOIN categories c ON c.id = m.category_id 
     WHERE oi.order_id = ? 
     ORDER BY oi.id ASC',
    [$orderId]
);

$prescription = fetch_one('SELECT * FROM prescriptions WHERE order_id = ? LIMIT 1', [$orderId]);
?>

<div class="page-intro mb-4">
    <div>
        <h2>Order Confirmation</h2>
        <p>Your order has been recorded and safely placed in our dispensary queue.</p>
    </div>
    <div>
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print Receipt
        </button>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Order Success Banner -->
        <div class="card shadow-sm border-0 mb-4" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: white;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 28px;">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                    <div>
                        <h4 class="mb-1 text-white">Order Successfully Placed!</h4>
                        <p class="mb-0 text-white-50 small">
                            Order Reference: <strong class="text-white"><?= e($order['order_number']) ?></strong> &middot; Placed on <?= e(nice_date($order['created_at'], true)) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Snapshot Table -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <strong>Ordered Medicines (<?= count($items) ?> items)</strong>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-secondary">
                        <tr>
                            <th>Medicine</th>
                            <th class="text-center">Dosage</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($item['medicine_name']) ?></div>
                                    <div class="small text-secondary">
                                        <span class="badge text-bg-light border"><?= e($item['category_name']) ?></span>
                                        &middot; Batch: <code><?= e($item['batch_number']) ?></code>
                                    </div>
                                </td>
                                <td class="text-center small text-secondary">
                                    <?= e(ucfirst(strtolower($item['medicine_type']))) ?>
                                </td>
                                <td class="text-end">
                                    <?= money($item['unit_price']) ?>
                                </td>
                                <td class="text-center fw-semibold">
                                    <?= (int)$item['quantity'] ?>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    <?= money($item['line_total']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="4" class="text-end text-secondary">Subtotal</td>
                            <td class="text-end fw-semibold"><?= money($order['subtotal']) ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end text-secondary">Delivery Fee</td>
                            <td class="text-end fw-semibold"><?= money($order['shipping_fee']) ?></td>
                        </tr>
                        <tr class="fw-bold fs-6">
                            <td colspan="4" class="text-end">Grand Total</td>
                            <td class="text-end text-success"><?= money($order['total_amount']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Fulfillment Timeline / Next Steps -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <strong>Dispensary Fulfillment Process</strong>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-md-3">
                        <div class="p-3 rounded bg-success-subtle border border-success-subtle h-100">
                            <i class="bi bi-cart-check-fill text-success fs-3 d-block mb-1"></i>
                            <strong class="small text-success d-block">1. Order Placed</strong>
                            <span class="text-muted" style="font-size: 11px;">Stock decremented</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 rounded bg-light border h-100">
                            <i class="bi bi-file-medical text-primary fs-3 d-block mb-1"></i>
                            <strong class="small d-block">2. Verification</strong>
                            <span class="text-muted" style="font-size: 11px;">Pharmacist review</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 rounded bg-light border h-100">
                            <i class="bi bi-box-seam text-secondary fs-3 d-block mb-1"></i>
                            <strong class="small d-block">3. Packing</strong>
                            <span class="text-muted" style="font-size: 11px;">Batch sealed</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 rounded bg-light border h-100">
                            <i class="bi bi-truck text-secondary fs-3 d-block mb-1"></i>
                            <strong class="small d-block">4. Dispatch</strong>
                            <span class="text-muted" style="font-size: 11px;">To your doorstep</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Metadata & Delivery Sidebar -->
    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <strong>Order Details</strong>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-secondary">Order Status</span>
                        <span class="badge text-bg-warning"><?= e($order['order_status']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-secondary">Payment Method</span>
                        <span class="fw-semibold">
                            <?= $order['payment_method'] === 'COD' ? 'Cash on Delivery (COD)' : 'Store Pickup' ?>
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-secondary">Payment Status</span>
                        <span class="badge text-bg-secondary"><?= e($order['payment_status']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-secondary">Prescription Status</span>
                        <span class="badge text-bg-<?= $order['prescription_status'] === 'NOT_REQUIRED' ? 'success' : 'info' ?>">
                            <?= e(str_replace('_', ' ', $order['prescription_status'])) ?>
                        </span>
                    </li>
                    <?php if ($prescription): ?>
                        <li class="list-group-item px-0">
                            <span class="text-secondary d-block mb-1">Uploaded Prescription:</span>
                            <div class="d-flex align-items-center gap-2 p-2 bg-light rounded border">
                                <i class="bi bi-file-earmark-medical fs-4 text-danger"></i>
                                <div class="text-truncate" style="font-size: 12px;">
                                    <strong><?= e($prescription['original_filename']) ?></strong>
                                    <div class="text-muted"><?= round($prescription['file_size'] / 1024, 1) ?> KB</div>
                                </div>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <strong>Delivery Recipient</strong>
            </div>
            <div class="card-body small">
                <div class="mb-2">
                    <span class="text-secondary d-block">Recipient Name</span>
                    <strong class="text-dark"><?= e($order['customer_name']) ?></strong>
                </div>
                <div class="mb-2">
                    <span class="text-secondary d-block">Contact Phone</span>
                    <span class="text-dark"><?= e($order['customer_phone']) ?></span>
                </div>
                <div class="mb-2">
                    <span class="text-secondary d-block">Shipping Address</span>
                    <span class="text-dark"><?= nl2br(e($order['delivery_address'])) ?></span>
                </div>
                <?php if ($order['notes']): ?>
                    <div>
                        <span class="text-secondary d-block">Special Instructions</span>
                        <span class="text-dark italic"><?= nl2br(e($order['notes'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-grid gap-2">
            <a href="<?= e(url('order_detail', ['id' => $order['id']])) ?>" class="btn btn-success">
                <i class="bi bi-clock-history me-1"></i>View &amp; Track Order Status
            </a>
            <a href="<?= e(url('catalog')) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-cart-plus me-1"></i>Continue Shopping
            </a>
        </div>
    </div>
</div>
