<?php
/**
 * PharmaCare — Customer Checkout Page
 * Handles delivery address confirmation, payment method selection,
 * prescription file upload, and order submission.
 */

declare(strict_types=1);

require_customer();

$items = cart_get_items();
if (empty($items)) {
    flash('error', 'Your shopping cart is empty. Please add medicines before checkout.');
    redirect('cart');
}

$customerId = (int)user()['id'];
$u = fetch_one('SELECT full_name, phone, address FROM app_users WHERE id = ?', [$customerId]);

$subtotal = cart_get_total();
$shippingFee = 0.00;
$totalAmount = $subtotal + $shippingFee;
$requiresRx = cart_requires_prescription();
?>

<div class="page-intro mb-4">
    <div>
        <h2>Checkout &amp; Order Placement</h2>
        <p>Confirm your delivery details, payment method, and complete your pharmacy order.</p>
    </div>
</div>

<form action="actions.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="order_create">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- Delivery & Payment Information -->
        <div class="col-lg-7">
            <!-- Delivery Details Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <strong class="d-flex align-items-center">
                        <i class="bi bi-geo-alt text-success me-2 fs-5"></i>Delivery Information
                    </strong>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="co_name">Full Recipient Name *</label>
                            <input class="form-control" id="co_name" name="customer_name" value="<?= e($u['full_name'] ?? '') ?>" maxlength="100" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="co_phone">Contact Phone *</label>
                            <input class="form-control" id="co_phone" name="customer_phone" type="tel" value="<?= e($u['phone'] ?? '') ?>" maxlength="15" pattern="(01|\+8801)[3-9][0-9]{8}" placeholder="017XXXXXXXX" required>
                            <div class="form-hint">Used by the dispensary courier to coordinate delivery.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="co_address">Detailed Delivery Address *</label>
                        <textarea class="form-control" id="co_address" name="delivery_address" rows="3" maxlength="500" placeholder="House/Flat number, Road, Area, Thana, District" required><?= e($u['address'] ?? '') ?></textarea>
                        <div class="form-hint">Provide full residential or corporate delivery address.</div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold" for="co_notes">Delivery Notes / Instructions (Optional)</label>
                        <textarea class="form-control" id="co_notes" name="notes" rows="2" maxlength="1000" placeholder="e.g. Call before arrival, leave with security guard, etc."></textarea>
                    </div>
                </div>
            </div>

            <!-- Prescription Upload Section -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <strong class="d-flex align-items-center">
                        <i class="bi bi-prescription2 text-danger me-2 fs-5"></i>Doctor's Prescription
                    </strong>
                </div>
                <div class="card-body">
                    <?php if ($requiresRx): ?>
                        <div class="alert alert-danger-subtle border border-danger-subtle mb-3">
                            <div class="d-flex">
                                <i class="bi bi-exclamation-triangle-fill text-danger fs-4 me-3"></i>
                                <div>
                                    <strong class="text-danger">Prescription Upload Required</strong>
                                    <p class="mb-0 small text-secondary">
                                        Your cart contains regulated prescription-only medicines. As mandated by Bangladesh Pharmacy Regulations, orders containing prescription medicines cannot be dispatched without a verified prescription from a registered physician (BMDC).
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="co_prescription">Upload Prescription File *</label>
                            <input type="file" class="form-control" id="co_prescription" name="prescription" accept=".jpg,.jpeg,.png,.pdf" required>
                            <div class="form-hint">Accepted file formats: JPG, PNG, or PDF (Max 5MB). Ensure doctor's seal and patient name are clearly legible.</div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success-subtle border border-success-subtle mb-3">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-check-circle-fill text-success fs-4 me-3"></i>
                                <div>
                                    <strong class="text-success">No Prescription Required</strong>
                                    <p class="mb-0 small text-secondary">
                                        All items in your order are general or Over-The-Counter (OTC) medicines. You do not need to attach a doctor's prescription.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label small text-secondary" for="co_prescription_opt">Attach Prescription (Optional)</label>
                            <input type="file" class="form-control form-control-sm" id="co_prescription_opt" name="prescription" accept=".jpg,.jpeg,.png,.pdf">
                            <div class="form-hint">You may optionally attach a prescription for reference (Max 5MB).</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment Method Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <strong class="d-flex align-items-center">
                        <i class="bi bi-credit-card-2-front text-primary me-2 fs-5"></i>Payment Method
                    </strong>
                </div>
                <div class="card-body">
                    <div class="form-check p-3 border rounded mb-3 bg-light">
                        <input class="form-check-input" type="radio" name="payment_method" id="pay_cod" value="COD" checked>
                        <label class="form-check-label d-block" for="pay_cod">
                            <strong class="text-dark">Cash on Delivery (COD)</strong>
                            <span class="d-block text-secondary small">Pay cash to our delivery agent upon receiving and inspecting your medicines.</span>
                        </label>
                    </div>

                    <div class="form-check p-3 border rounded">
                        <input class="form-check-input" type="radio" name="payment_method" id="pay_pickup" value="STORE_PICKUP">
                        <label class="form-check-label d-block" for="pay_pickup">
                            <strong class="text-dark">Pay at Pharmacy / Store Pickup</strong>
                            <span class="d-block text-secondary small">Collect your prepared medicines directly at the pharmacy counter and pay on spot.</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Summary Sidebar -->
        <div class="col-lg-5">
            <div class="card shadow-sm sticky-top" style="top: 20px;">
                <div class="card-header bg-white py-3">
                    <strong>Order Summary (<?= cart_get_count() ?> units)</strong>
                </div>
                <div class="card-body">
                    <div class="table-responsive mb-3" style="max-height: 280px; overflow-y: auto;">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                <?php foreach ($items as $item):
                                    $lineTotal = ((float)$item['price']) * ((int)$item['quantity']);
                                ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-truncate" style="max-width: 180px;"><?= e($item['name']) ?></div>
                                            <small class="text-secondary">
                                                <?= (int)$item['quantity'] ?> &times; <?= money($item['price']) ?>
                                                <?php if (!empty($item['requires_prescription'])): ?>
                                                    &middot; <span class="badge text-bg-danger-subtle text-danger" style="font-size: 9px;">Rx</span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td class="text-end fw-semibold">
                                            <?= money($lineTotal) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <ul class="list-group list-group-flush mb-4 small">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-secondary">Subtotal</span>
                            <span class="fw-semibold"><?= money($subtotal) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-secondary">Delivery Fee</span>
                            <span class="badge text-bg-success-subtle text-success border border-success-subtle">FREE</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 pt-3 border-top fs-6">
                            <span class="fw-bold">Total Amount</span>
                            <span class="fw-bold text-success fs-5"><?= money($totalAmount) ?></span>
                        </li>
                    </ul>

                    <button type="submit" class="btn btn-success w-100 py-3 fw-bold fs-6 shadow-sm">
                        <i class="bi bi-shield-check me-2"></i>Place Order &mdash; <?= money($totalAmount) ?>
                    </button>

                    <div class="text-center mt-3">
                        <a href="<?= e(url('cart')) ?>" class="text-secondary small text-decoration-underline">
                            <i class="bi bi-arrow-left me-1"></i>Back to Shopping Cart
                        </a>
                    </div>

                    <div class="alert alert-light border mt-4 mb-0 small text-secondary">
                        <i class="bi bi-lock-fill text-success me-1"></i>
                        Pessimistic concurrency locking secures your batch stock immediately upon placing the order.
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
