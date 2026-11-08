<?php
/**
 * PharmaCare — Medicine Inventory Catalog View
 * Filterable table with multi-attribute search and role-based actions.
 */

declare(strict_types=1);

$keyword = trim((string)($_GET['keyword'] ?? ''));
$categoryId = (int)($_GET['category_id'] ?? 0);
$selectedStatus = strtoupper(trim((string)($_GET['status'] ?? '')));
$categories = fetch_all('SELECT id, name FROM categories ORDER BY name');

$params = [];
$sql = medicine_query() . ' WHERE 1=1';

if ($keyword !== '') {
    $sql .= ' AND (m.name LIKE ? OR m.generic_name LIKE ? OR m.brand_name LIKE ? OR m.batch_number LIKE ?)';
    $needle = '%' . $keyword . '%';
    array_push($params, $needle, $needle, $needle, $needle);
}

if ($categoryId) {
    $sql .= ' AND m.category_id = ?';
    $params[] = $categoryId;
}

$allMedicines = fetch_all($sql . ' ORDER BY m.name, m.batch_number', $params);
$medicines = $selectedStatus !== ''
    ? array_values(array_filter($allMedicines, fn($m) => medicine_status($m) === $selectedStatus))
    : $allMedicines;
?>

<div class="page-intro">
    <div>
        <h2>Medicine Inventory</h2>
        <p><?= count($medicines) ?> record(s) found</p>
    </div>
    <?php if (is_admin()): ?>
        <a href="<?= e(url('medicine_form')) ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add Medicine
        </a>
    <?php endif; ?>
</div>

<!-- Search & Filter Controls -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="index.php" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="medicines">

            <div class="col-lg-5">
                <label for="keyword" class="form-label">Search</label>
                <input id="keyword" name="keyword" class="form-control" value="<?= e($keyword) ?>" placeholder="Search name, generic, brand or batch...">
            </div>

            <div class="col-md-4 col-lg-3">
                <label for="category" class="form-label">Category</label>
                <select name="category_id" id="category" class="form-select">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= e((string)$c['id']) ?>" <?= $categoryId === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 col-lg-2">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="">All statuses</option>
                    <?php foreach (['AVAILABLE', 'LOW_STOCK', 'OUT_OF_STOCK', 'EXPIRING_SOON', 'EXPIRED', 'INACTIVE'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= $s === $selectedStatus ? 'selected' : '' ?>>
                            <?= e(status_label($s)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 col-lg-2 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" title="Search" type="submit">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
                <a class="btn btn-light border" title="Reset Filters" href="<?= e(url('medicines')) ?>">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Inventory Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th>Category &amp; Supplier</th>
                    <th>Batch</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                    <?php if (is_admin()): ?>
                        <th class="text-end">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($medicines as $m):
                    $status = medicine_status($m);
                ?>
                    <tr>
                        <td>
                            <strong><?= e($m['name']) ?></strong>
                            <span class="subtext"><?= e($m['generic_name']) ?> &middot; <?= e(ucfirst(strtolower($m['medicine_type']))) ?></span>
                        </td>
                        <td>
                            <?= e($m['category_name']) ?>
                            <span class="subtext"><?= e($m['supplier_company'] ?: $m['supplier_name']) ?></span>
                        </td>
                        <td><span class="fw-semibold"><?= e($m['batch_number']) ?></span></td>
                        <td>
                            <strong><?= money($m['selling_price']) ?></strong>
                            <span class="subtext">Cost <?= money($m['purchase_price']) ?></span>
                        </td>
                        <td>
                            <strong><?= e($m['quantity']) ?></strong>
                            <span class="subtext">Min: <?= e($m['minimum_stock_level']) ?></span>
                        </td>
                        <td><?= e(nice_date($m['expiry_date'])) ?></td>
                        <td>
                            <span class="badge text-bg-<?= e(status_badge($status)) ?>">
                                <?= e(status_label($status)) ?>
                            </span>
                            <?php if ($m['prescription_required']): ?>
                                <span class="subtext text-danger">Prescription</span>
                            <?php endif; ?>
                        </td>
                        <?php if (is_admin()): ?>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-light border" title="Edit" href="<?= e(url('medicine_form', ['id' => $m['id']])) ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form class="d-inline" action="actions.php" method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="medicine_toggle">
                                    <input type="hidden" name="id" value="<?= e($m['id']) ?>">
                                    <button class="btn btn-sm btn-outline-<?= $m['active'] ? 'danger' : 'success' ?>"
                                            title="<?= $m['active'] ? 'Deactivate' : 'Activate' ?>"
                                            data-confirm="Change active status for <?= e($m['name']) ?>?">
                                        <i class="bi bi-<?= $m['active'] ? 'slash-circle' : 'check-circle' ?>"></i>
                                    </button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$medicines): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <i class="bi bi-search"></i>
                                <p>No medicines matched your search criteria.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
