<?php
/**
 * PharmaCare — Customer Medicine Catalog View
 * Public/Customer browsing interface displaying active and unexpired medicine inventory.
 * Supports keyword search, category filter, stock status, Rx badge, and add-to-cart controls.
 */

declare(strict_types=1);

$keyword = trim((string)($_GET['keyword'] ?? ''));
$categoryId = (int)($_GET['category_id'] ?? 0);
$categories = fetch_all('SELECT id, name FROM categories ORDER BY name');

$params = [];
$sql = medicine_query() . ' WHERE m.active = 1 AND m.expiry_date >= CURDATE() AND m.quantity > 0';

if ($keyword !== '') {
    $sql .= ' AND (m.name LIKE ? OR m.generic_name LIKE ? OR m.brand_name LIKE ?)';
    $needle = '%' . $keyword . '%';
    array_push($params, $needle, $needle, $needle);
}

if ($categoryId) {
    $sql .= ' AND m.category_id = ?';
    $params[] = $categoryId;
}

$sql .= ' ORDER BY m.name, m.batch_number';
$medicines = fetch_all($sql, $params);
?>

<div class="page-intro mb-4">
    <div>
        <h2>Online Medicine Catalog</h2>
        <p>Explore verified inventory batches with live pricing, stock availability, and secure ordering.</p>
    </div>
</div>

<!-- Search & Category Filter Controls -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="get" action="index.php" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="catalog">

            <div class="col-lg-6">
                <label for="catalog_keyword" class="form-label fw-semibold">Search Medicine</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input id="catalog_keyword" name="keyword" class="form-control" value="<?= e($keyword) ?>" placeholder="Search by trade name, generic or brand...">
                </div>
            </div>

            <div class="col-md-4 col-lg-4">
                <label for="catalog_cat" class="form-label fw-semibold">Category</label>
                <select name="category_id" id="catalog_cat" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= e((string)$c['id']) ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 col-lg-2 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" type="submit">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a class="btn btn-light border" title="Reset Search" href="<?= e(url('catalog')) ?>">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Results Header -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="text-secondary small fw-semibold">
        SHOWING <?= count($medicines) ?> AVAILABLE MEDICINE(S)
    </div>
    <?php if (is_customer() && cart_get_count() > 0): ?>
        <a href="<?= e(url('cart')) ?>" class="btn btn-sm btn-outline-success">
            <i class="bi bi-cart3 me-1"></i>View Cart (<?= cart_get_count() ?>)
        </a>
    <?php endif; ?>
</div>

<!-- Medicine Cards Grid -->
<div class="row g-3">
    <?php foreach ($medicines as $m):
        $status = medicine_status($m);
        $inCart = (int)($_SESSION['cart'][$m['id']]['quantity'] ?? 0);
        $maxStock = (int)$m['quantity'];
    ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 shadow-sm border">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge text-bg-light border text-uppercase" style="font-size: 11px;">
                            <i class="bi bi-tag me-1"></i><?= e($m['category_name']) ?>
                        </span>
                        <span class="badge text-bg-<?= e(status_badge($status)) ?>">
                            <?= e(status_label($status)) ?>
                        </span>
                    </div>

                    <h5 class="card-title text-success mb-1"><?= e($m['name']) ?></h5>
                    <div class="text-secondary small mb-2">
                        <strong>Generic:</strong> <?= e($m['generic_name']) ?>
                        <?php if ($m['brand_name']): ?>
                            &middot; <span class="text-dark"><?= e($m['brand_name']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="small text-muted mb-3">
                        <div><i class="bi bi-box-seam me-1"></i><strong>Dosage:</strong> <?= e(ucfirst(strtolower($m['medicine_type']))) ?></div>
                        <div><i class="bi bi-upc-scan me-1"></i><strong>Batch:</strong> <code><?= e($m['batch_number']) ?></code></div>
                        <div><i class="bi bi-building me-1"></i><strong>Manufacturer:</strong> <?= e($m['supplier_company'] ?: $m['supplier_name']) ?></div>
                        <div><i class="bi bi-calendar-check me-1"></i><strong>Expiry:</strong> <?= e(nice_date($m['expiry_date'])) ?></div>
                    </div>

                    <div class="mt-auto pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <span class="text-secondary small d-block">Unit Price</span>
                                <div class="h5 mb-0 text-dark fw-bold"><?= money($m['selling_price']) ?></div>
                            </div>
                            <div class="text-end">
                                <?php if ($m['prescription_required']): ?>
                                    <span class="badge text-bg-danger-subtle text-danger border border-danger-subtle mb-1 d-block text-center" style="font-size: 10px;">
                                        <i class="bi bi-prescription2 me-1"></i>Prescription Required
                                    </span>
                                <?php else: ?>
                                    <span class="badge text-bg-success-subtle text-success border border-success-subtle mb-1 d-block text-center" style="font-size: 10px;">
                                        <i class="bi bi-check-circle me-1"></i>OTC / No Rx
                                    </span>
                                <?php endif; ?>
                                <span class="badge text-bg-light border text-secondary" style="font-size: 11px;">
                                    In Stock: <?= $maxStock ?> units
                                </span>
                            </div>
                        </div>

                        <!-- Add to Cart Controls -->
                        <?php if (!user()): ?>
                            <a href="index.php?page=login" class="btn btn-sm btn-outline-success w-100">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Sign In to Order
                            </a>
                        <?php elseif (is_customer()): ?>
                            <form action="actions.php" method="post" class="d-flex align-items-center gap-2">
                                <input type="hidden" name="action" value="cart_add">
                                <?= csrf_field() ?>
                                <input type="hidden" name="medicine_id" value="<?= (int)$m['id'] ?>">
                                <input type="hidden" name="redirect_to" value="catalog">

                                <div class="input-group input-group-sm" style="max-width: 105px;">
                                    <span class="input-group-text bg-light text-secondary">Qty</span>
                                    <input type="number" name="quantity" class="form-control text-center" min="1" max="<?= $maxStock ?>" value="1" <?= $inCart >= $maxStock ? 'disabled' : '' ?>>
                                </div>

                                <button type="submit" class="btn btn-sm btn-success flex-grow-1" <?= $inCart >= $maxStock ? 'disabled' : '' ?>>
                                    <i class="bi bi-cart-plus me-1"></i><?= $inCart > 0 ? 'Add More' : 'Add to Cart' ?>
                                </button>
                            </form>
                            <?php if ($inCart > 0): ?>
                                <div class="mt-2 text-center">
                                    <span class="badge text-bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 11px;">
                                        <i class="bi bi-cart-check me-1"></i><?= $inCart ?> in <a href="<?= e(url('cart')) ?>" class="text-primary text-decoration-underline">cart</a>
                                        <?= $inCart >= $maxStock ? '(Max available)' : '' ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-1">
                                <span class="badge text-bg-secondary-subtle text-secondary small">
                                    <i class="bi bi-shield-lock me-1"></i>Staff Mode &middot; <a href="<?= e(url('sale_form')) ?>" class="text-secondary text-decoration-underline">POS New Sale</a>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (!$medicines): ?>
        <div class="col-12">
            <div class="card">
                <div class="empty-state py-5 text-center">
                    <i class="bi bi-search fs-1 text-secondary mb-2"></i>
                    <h4>No Medicines Found</h4>
                    <p class="text-secondary mb-3">No available medicines matched your search criteria.</p>
                    <a href="<?= e(url('catalog')) ?>" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>View All Medicines
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
