<?php
/**
 * PharmaCare — Customer Order Checkout Page
 * Handles shipping contact details, payment selection, prescription upload,
 * order review, and atomic order placement submission.
 */

declare(strict_types=1);

require_customer();

if (cart_get_count() <= 0) {
    flash('error', 'Your shopping cart is empty. Please select medicines before proceeding to checkout.');
    redirect('cart');
}

$userId = (int)user()['id'];
$customer = fetch_one('SELECT full_name, phone, address FROM app_users WHERE id = ?', [$userId]);

$items = cart_get_items();
$itemCount = cart_get_count();
$lineCount = cart_get_line_count();
$cartTotal = cart_get_total();
$requiresPrescription = cart_requires_prescription();
?>

<div class="page-intro mb-4">
    <div>
        <h2>Order Checkout</h2>
        <p>Confirm your delivery address, select payment options, and finalize your medicine order.</p>
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
                    <strong class="d-flex align-items-center gap-2">
                        <i class="bi bi-geo-alt-fill text-success"></i>
                        1. Delivery &amp; Contact Information
                    </strong>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="cust_name">Full Name *</label>
                            <input class="form-control" id="cust_name" name="customer_name" value="<?= e($customer['full_name'] ?? '') ?>" maxlength="100" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="cust_phone">Contact Phone *</label>
                            <input class="form-control" id="cust_phone" name="customer_phone" type="tel" value="<?= e($customer['phone'] ?? '') ?>" maxlength="15" pattern="(01|\+8801)[3-9][0-9]{8}" placeholder="017XXXXXXXX" required>
                            <div class="form-hint">Used for delivery status calls and verification.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="cust_address">Delivery Address *</label>
                        <textarea class="form-control" id="cust_address" name="delivery_address" rows="3" maxlength="500" placeholder="House/Flat #, Road #, Sector/Area, City..." required><?= e($customer['address'] ?? '') ?></textarea>
                        <div class="form-hint">Provide full residential or office address in Bangladesh.</div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label" for="order_notes">Delivery Notes / Instructions (Optional)</label>
                        <textarea class="form-control" id="order_notes" name="notes" rows="2" maxlength="1000" placeholder="e.g., Please call before arriving or deliver between 2 PM and 5 PM."></textarea>
                    </div>
                </div>
            </div>

            <!-- Payment Method Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <strong class="d-flex align-items-center gap-2">
                        <i class="bi bi-credit-card-2-front-fill text-success"></i>
                        2. Payment Method
                    </strong>
                </div>
                <div class="card-body">
                    <div class="form-check p-3 border rounded mb-2">
                        <input class="form-check-input" type="radio" name="payment_method" id="pay_cod" value="COD" checked>
                        <label class="form-check-label w-100 ms-2 cursor-pointer" for="pay_cod">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>Cash on Delivery (COD)</strong>
                                <span class="badge text-bg-light border">Standard</span>
                            </div>
                            <small class="text-secondary d-block mt-1">Pay with cash upon receiving your medicine package at your doorstep.</small>
                        </label>
                    </div>

                    <div class="form-check p-3 border rounded">
                        <input class="form-check-input" type="radio" name="payment_method" id="pay_pickup" value="STORE_PICKUP">
                        <label class="form-check-label w-100 ms-2 cursor-pointer" for="pay_pickup">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>Pay at Pharmacy (Store Pickup)</strong>
                                <span class="badge text-bg-light border">Direct Collection</span>
                            </div>
                            <small class="text-secondary d-block mt-1">Collect your package from our licensed pharmacy counter and pay in-store.</small>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Prescription Upload Section -->
            <div class="card shadow-sm mb-4 <?= $requiresPrescription ? 'border-warning' : '' ?>">
                <div class="card-header bg-white py-3">
                    <strong class="d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-medical-fill text-danger"></i>
                        3. Doctor's Prescription
                        <?php if ($requiresPrescription): ?>
                            <span class="badge text-bg-danger ms-auto">MANDATORY</span>
                        <?php else: ?>
                            <span class="badge text-bg-success ms-auto">NOT REQUIRED</span>
                        <?php endif; ?>
                    </strong>
                </div>
                <div class="card-body">
                    <?php if ($requiresPrescription): ?>
                        <div class="alert alert-warning border border-warning-subtle mb-3 small" role="alert">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-exclamation-triangle-fill fs-5 text-warning flex-shrink-0"></i>
                                <div>
                                    <strong>Prescription Verification Required:</strong>
                                    One or more medicines in your cart are regulated prescription drugs. Bangladesh Directorate General of Drug Administration (DGDA) regulations require a valid prescription before these medicines can be dispensed.
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="prescription">Upload Doctor's Prescription *</label>
                            <input class="form-control" type="file" id="prescription" name="prescription" accept=".jpg,.jpeg,.png,.pdf" required>
                            <div class="form-hint mt-1">
                                Supported file formats: <strong>JPG, JPEG, PNG, PDF</strong> (Maximum size: <strong>5 MB</strong>).
                            </div>
                        </div>

                        <div class="small text-muted border-top pt-2">
                            <i class="bi bi-shield-lock me-1"></i>
                            Your prescription is stored in a private restricted directory and reviewed exclusively by registered pharmacists.
                        </div>
                    <?php else: ?>
                        <div class="text-success small d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill fs-5"></i>
                            <div>
                                <strong>All items are Over-The-Counter (OTC):</strong>
                                No prescription is required for this order. You may proceed directly to place your order.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Order Review & Place Order Button -->
        <div class="col-lg-5">
            <div class="card shadow-sm sticky-top" style="top: 20px;">
                <div class="card-header bg-white py-3">
                    <strong>Review Order Items (<?= $lineCount ?>)</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                <?php foreach ($items as $item):
                                    $line = ((float)$item['price']) * ((int)$item['quantity']);
                                ?>
                                    <tr class="border-bottom">
                                        <td class="ps-3 py-2">
                                            <div class="fw-semibold text-dark small"><?= e($item['name']) ?></div>
                                            <div class="text-muted" style="font-size: 11px;">
                                                <?= (int)$item['quantity'] ?> × <?= money($item['price']) ?>
                                                <?php if (!empty($item['requires_prescription'])): ?>
                                                    &middot; <span class="text-danger fw-semibold">Rx</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="text-end pe-3 py-2 fw-semibold small text-success">
                                            <?= money($line) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3 bg-light border-top">
                        <ul class="list-group list-group-flush small mb-3">
                            <li class="list-group-item bg-transparent d-flex justify-content-between px-0">
                                <span class="text-secondary">Total Units</span>
                                <span class="fw-semibold"><?= $itemCount ?> units</span>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between px-0">
                                <span class="text-secondary">Subtotal</span>
                                <span class="fw-semibold"><?= money($cartTotal) ?></span>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between px-0">
                                <span class="text-secondary">Delivery Fee</span>
                                <span class="badge text-bg-success-subtle text-success border border-success-subtle">FREE</span>
                            </li>
                            <li class="list-group-item bg-transparent d-flex justify-content-between px-0 pt-2 border-top">
                                <span class="fw-bold fs-6">Order Total</span>
                                <span class="fw-bold fs-6 text-success"><?= money($cartTotal) ?></span>
                            </li>
                        </ul>

                        <button type="submit" class="btn btn-success w-100 py-2 fw-bold shadow-sm">
                            <i class="bi bi-bag-check-fill me-1"></i>Confirm &amp; Place Order
                        </button>

                        <a href="<?= e(url('cart')) ?>" class="btn btn-outline-secondary btn-sm w-100 mt-2">
                            <i class="bi bi-arrow-left me-1"></i>Return to Shopping Cart
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
