<?php
/**
 * PharmaCare — Customer Order Confirmation Page
 * Displays confirmed order details, assigned order number, itemized pricing snapshots,
 * prescription review status, delivery information, and operational next steps.
 * Strict IDOR protection: only accessible to the customer who placed the order.
 */

declare(strict_types=1);

require_customer();

$orderId = (int)($_GET['id'] ?? 0);
$userId = (int)user()['id'];

$order = fetch_one('SELECT * FROM orders WHERE id = ?', [$orderId]);

if (!$order || (int)$order['customer_id'] !== $userId) {
    flash('error', 'Order not found or access denied.');
    redirect('catalog');
}

$items = fetch_all(
    'SELECT oi.*, m.name AS medicine_name, m.generic_name, m.batch_number, m.prescription_required
     FROM order_items oi
     JOIN medicines m ON m.id = oi.medicine_id
     WHERE oi.order_id = ?',
    [$orderId]
);

$prescription = fetch_one('SELECT * FROM prescriptions WHERE order_id = ?', [$orderId]);
?>

<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <!-- Success Celebration Card -->
        <div class="card shadow-sm border-0 mb-4 text-center py-4 px-3" style="background: linear-gradient(180deg, #ecfdf5 0%, #ffffff 100%);">
            <div class="avatar avatar-xl mx-auto mb-3" style="width: 72px; height: 72px; font-size: 36px; background: #10b981; color: #ffffff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                <i class="bi bi-check-lg"></i>
            </div>
            <h2 class="text-success fw-bold mb-1">Order Placed Successfully!</h2>
            <p class="text-secondary mb-3">
                Thank you for choosing PharmaCare. Your order has been submitted and is currently queued for pharmacy staff processing.
            </p>
            <div class="d-inline-flex align-items-center justify-content-center gap-2 bg-white px-3 py-2 rounded-pill border mx-auto shadow-sm">
                <span class="text-secondary small">Order Reference:</span>
                <strong class="font-monospace text-dark fs-6"><?= e($order['order_number']) ?></strong>
            </div>
        </div>

        <?php if ($order['prescription_required']): ?>
            <!-- Prescription Status Notice -->
            <div class="alert alert-info border border-info-subtle shadow-sm mb-4">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-file-earmark-medical fs-2 text-info flex-shrink-0"></i>
                    <div>
                        <h6 class="alert-heading mb-1 fw-bold text-dark">Prescription Review in Progress</h6>
                        <p class="mb-0 text-secondary small">
                            A registered pharmacist will verify your uploaded prescription (<code><?= e($prescription['original_filename'] ?? 'prescription document') ?></code>) prior to packing. You will be contacted if any dosage or doctor clarification is needed.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Order Information & Delivery Details -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <strong class="text-dark">Order Information</strong>
                <span class="badge text-bg-warning text-dark px-3 py-2">
                    <i class="bi bi-clock me-1"></i>Status: <?= e($order['order_status']) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6 border-end-md">
                        <h6 class="text-uppercase text-secondary small fw-bold mb-3">Delivery Information</h6>
                        <ul class="list-unstyled small mb-0">
                            <li class="mb-2">
                                <span class="text-secondary">Recipient:</span>
                                <strong class="text-dark ms-1"><?= e($order['customer_name']) ?></strong>
                            </li>
                            <li class="mb-2">
                                <span class="text-secondary">Contact Phone:</span>
                                <strong class="text-dark ms-1"><?= e($order['customer_phone']) ?></strong>
                            </li>
                            <li class="mb-2">
                                <span class="text-secondary">Delivery Address:</span>
                                <div class="text-dark mt-1 p-2 bg-light rounded border">
                                    <?= nl2br(e($order['delivery_address'])) ?>
                                </div>
                            </li>
                            <?php if ($order['notes']): ?>
                                <li>
                                    <span class="text-secondary">Delivery Notes:</span>
                                    <div class="text-muted mt-1 fst-italic">"<?= e($order['notes']) ?>"</div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="col-md-6">
                        <h6 class="text-uppercase text-secondary small fw-bold mb-3">Payment &amp; Logistics</h6>
                        <ul class="list-unstyled small mb-0">
                            <li class="mb-2 d-flex justify-content-between">
                                <span class="text-secondary">Payment Method:</span>
                                <strong class="text-dark">
                                    <?= $order['payment_method'] === 'STORE_PICKUP' ? 'Pay at Pharmacy (Store Pickup)' : 'Cash on Delivery (COD)' ?>
                                </strong>
                            </li>
                            <li class="mb-2 d-flex justify-content-between">
                                <span class="text-secondary">Payment Status:</span>
                                <span class="badge text-bg-secondary"><?= e($order['payment_status']) ?></span>
                            </li>
                            <li class="mb-2 d-flex justify-content-between">
                                <span class="text-secondary">Order Placed On:</span>
                                <span class="text-dark"><?= e(nice_date($order['created_at'], true)) ?></span>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-secondary">Inventory Deducted:</span>
                                <span class="badge text-bg-success-subtle text-success border border-success-subtle">
                                    <i class="bi bi-check-circle me-1"></i>Stock Secured (FEFO/Locked)
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ordered Items Table -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <strong class="text-dark">Purchased Medicines (<?= count($items) ?> items)</strong>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-secondary">
                        <tr>
                            <th>Medicine</th>
                            <th class="text-center">Batch</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($it['medicine_name']) ?></div>
                                    <div class="text-secondary small">
                                        <?= e($it['generic_name']) ?>
                                        <?php if ($it['prescription_required']): ?>
                                            &middot; <span class="badge text-bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 10px;">Rx Required</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <code><?= e($it['batch_number']) ?></code>
                                </td>
                                <td class="text-end">
                                    <?= money($it['unit_price']) ?>
                                </td>
                                <td class="text-center fw-semibold">
                                    <?= (int)$it['quantity'] ?>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    <?= money($it['line_total']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="4" class="text-end text-secondary">Subtotal:</td>
                            <td class="text-end fw-semibold"><?= money($order['subtotal']) ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end text-secondary">Shipping / Delivery Fee:</td>
                            <td class="text-end text-success fw-semibold">FREE</td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end fw-bold fs-6">Grand Total:</td>
                            <td class="text-end fw-bold fs-6 text-success"><?= money($order['total_amount']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Next Steps Card -->
        <div class="card shadow-sm border-0 mb-4 bg-light">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-signpost-2 me-2 text-primary"></i>What Happens Next?</h6>
                <div class="row g-3 small">
                    <div class="col-md-4">
                        <div class="p-2 border rounded bg-white h-100">
                            <strong>1. Verification</strong>
                            <p class="text-secondary mb-0 mt-1">Pharmacist reviews inventory and prescription validity.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-2 border rounded bg-white h-100">
                            <strong>2. Dispatch / Packing</strong>
                            <p class="text-secondary mb-0 mt-1">Medication is sealed in tamper-evident packaging.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-2 border rounded bg-white h-100">
                            <strong>3. Delivery &amp; Payment</strong>
                            <p class="text-secondary mb-0 mt-1">Pay upon safe delivery or counter collection.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Links -->
        <div class="d-flex justify-content-between align-items-center mb-5">
            <a href="<?= e(url('catalog')) ?>" class="btn btn-outline-success">
                <i class="bi bi-capsule-pill me-1"></i>Continue Shopping
            </a>
            <a href="<?= e(url('profile')) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-person-circle me-1"></i>View My Account Profile
            </a>
        </div>
    </div>
</div>
