<?php
/**
 * PharmaCare — Customer Shopping Cart Page
 * Displays itemized cart contents, quantity adjustment controls, line totals,
 * prescription warning callout, and checkout summary.
 */

declare(strict_types=1);

require_customer();

$items = cart_get_items();
$itemCount = cart_get_count();
$lineCount = cart_get_line_count();
$cartTotal = cart_get_total();
$requiresPrescription = cart_requires_prescription();
?>

<div class="page-intro mb-4">
    <div>
        <h2>Shopping Cart</h2>
        <p>Review your selected medicines, adjust quantities, and proceed to secure order checkout.</p>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="card shadow-sm">
        <div class="card-body py-5 text-center">
            <div class="avatar avatar-xl mx-auto mb-3" style="width: 72px; height: 72px; font-size: 32px; background: rgba(16, 185, 129, 0.1); color: #059669; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-cart-x"></i>
            </div>
            <h4 class="mb-2">Your Shopping Cart is Empty</h4>
            <p class="text-secondary mb-4">You haven't added any medicines to your cart yet.</p>
            <a href="<?= e(url('catalog')) ?>" class="btn btn-success px-4">
                <i class="bi bi-capsule-pill me-1"></i>Browse Medicine Catalog
            </a>
        </div>
    </div>
<?php else: ?>

    <?php if ($requiresPrescription): ?>
        <div class="alert alert-warning border border-warning-subtle shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-prescription2 fs-2 me-3 text-danger"></i>
                <div>
                    <h5 class="alert-heading mb-1 text-dark fw-bold">Prescription Required Notice</h5>
                    <p class="mb-0 text-secondary small">
                        Your cart contains prescription-required medicines. You will need to upload a clear image or document of your valid doctor's prescription during checkout before your order can be verified and dispatched.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Itemized Cart Table -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <strong>Cart Items (<?= $lineCount ?>)</strong>
                    <form action="actions.php" method="post" onsubmit="return confirm('Are you sure you want to clear your entire cart?');" class="mb-0">
                        <input type="hidden" name="action" value="cart_clear">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash3 me-1"></i>Clear Cart
                        </button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-secondary">
                            <tr>
                                <th>Medicine</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-center" style="width: 160px;">Quantity</th>
                                <th class="text-end">Line Total</th>
                                <th class="text-center" style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $medId => $item):
                                $lineTotal = ((float)$item['price']) * ((int)$item['quantity']);
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= e($item['name']) ?></div>
                                        <div class="small text-secondary">
                                            <span class="badge text-bg-light border"><?= e($item['category_name']) ?></span>
                                            <?php if (!empty($item['batch_number'])): ?>
                                                &middot; Batch: <code><?= e($item['batch_number']) ?></code>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($item['requires_prescription'])): ?>
                                            <span class="badge text-bg-danger-subtle text-danger border border-danger-subtle mt-1" style="font-size: 10px;">
                                                <i class="bi bi-prescription2 me-1"></i>Prescription Required
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-semibold">
                                        <?= money($item['price']) ?>
                                    </td>
                                    <td class="text-center">
                                        <form action="actions.php" method="post" class="d-flex align-items-center justify-content-center gap-1 mb-0">
                                            <input type="hidden" name="action" value="cart_update">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="medicine_id" value="<?= (int)$item['id'] ?>">

                                            <input type="number" name="quantity" class="form-control form-control-sm text-center" style="width: 65px;" min="1" max="<?= (int)$item['max_stock'] ?>" value="<?= (int)$item['quantity'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Update quantity">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </form>
                                        <small class="text-muted d-block mt-1" style="font-size: 11px;">
                                            Max: <?= (int)$item['max_stock'] ?>
                                        </small>
                                    </td>
                                    <td class="text-end fw-bold text-success">
                                        <?= money($lineTotal) ?>
                                    </td>
                                    <td class="text-center">
                                        <form action="actions.php" method="post" onsubmit="return confirm('Remove <?= e(addslashes($item['name'])) ?> from your cart?');" class="mb-0">
                                            <input type="hidden" name="action" value="cart_remove">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="medicine_id" value="<?= (int)$item['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Remove item">
                                                <i class="bi bi-x-circle fs-5"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-light d-flex justify-content-between align-items-center py-3">
                    <a href="<?= e(url('catalog')) ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Continue Shopping
                    </a>
                    <span class="text-secondary small">
                        All prices are in Bangladeshi Taka (BDT) inclusive of VAT.
                    </span>
                </div>
            </div>
        </div>

        <!-- Order Summary Card -->
        <div class="col-lg-4">
            <div class="card shadow-sm sticky-top" style="top: 20px;">
                <div class="card-header bg-white py-3">
                    <strong>Order Summary</strong>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-secondary">Total Quantity</span>
                            <span class="fw-semibold"><?= $itemCount ?> units (<?= $lineCount ?> items)</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-secondary">Subtotal</span>
                            <span class="fw-semibold"><?= money($cartTotal) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-secondary">Estimated Delivery</span>
                            <span class="badge text-bg-success-subtle text-success border border-success-subtle">FREE</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 pt-3 border-top">
                            <span class="fw-bold fs-5">Grand Total</span>
                            <span class="fw-bold fs-5 text-success"><?= money($cartTotal) ?></span>
                        </li>
                    </ul>

                    <a href="<?= e(url('checkout')) ?>" class="btn btn-success w-100 py-2 fw-semibold">
                        <i class="bi bi-lock-fill me-1"></i>Proceed to Checkout
                    </a>

                    <div class="mt-3 text-center small text-secondary">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        Safe &amp; reliable delivery by certified pharmacy staff.
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
