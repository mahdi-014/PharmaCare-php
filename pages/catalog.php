<?php
/**
 * PharmaCare — Customer Medicine Catalog View
 * Public/Customer browsing interface displaying active and unexpired medicine inventory.
 */

declare(strict_types=1);

require_login();

$keyword = trim((string)($_GET['keyword'] ?? ''));
$categoryId = (int)($_GET['category_id'] ?? 0);
$categories = fetch_all('SELECT id, name FROM categories ORDER BY name');

$params = [];
$sql = medicine_query() . ' WHERE m.active = 1 AND m.expiry_date >= CURDATE()';

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
        <p>Explore verified inventory batches with live pricing and availability status.</p>
    </div>
</div>

<!-- Search & Category Filter Controls -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="index.php" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="catalog">

            <div class="col-lg-6">
                <label for="catalog_keyword" class="form-label">Search Medicine</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input id="catalog_keyword" name="keyword" class="form-control" value="<?= e($keyword) ?>" placeholder="Search by trade name, generic or brand...">
                </div>
            </div>

            <div class="col-md-4 col-lg-4">
                <label for="catalog_cat" class="form-label">Category</label>
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
</div>

<!-- Medicine Cards Grid -->
<div class="row g-3">
    <?php foreach ($medicines as $m):
        $status = medicine_status($m);
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
                        <div><i class="bi bi-building me-1"></i><strong>Manufacturer:</strong> <?= e($m['supplier_company'] ?: $m['supplier_name']) ?></div>
                        <div><i class="bi bi-calendar-check me-1"></i><strong>Expiry:</strong> <?= e(nice_date($m['expiry_date'])) ?></div>
                    </div>

                    <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-secondary small">Unit Price</span>
                            <div class="h5 mb-0 text-dark fw-bold"><?= money($m['selling_price']) ?></div>
                        </div>

                        <div>
                            <?php if ($m['prescription_required']): ?>
                                <span class="badge text-bg-danger-subtle text-danger border border-danger-subtle mb-1 d-block text-center" style="font-size: 10px;">
                                    <i class="bi bi-prescription2 me-1"></i>Prescription Required
                                </span>
                            <?php endif; ?>
                            <span class="badge text-bg-light border text-secondary">
                                In Stock: <?= e((string)$m['quantity']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (!$medicines): ?>
        <div class="col-12">
            <div class="card">
                <div class="empty-state py-5">
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
