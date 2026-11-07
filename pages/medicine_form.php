<?php
/**
 * PharmaCare — Add/Edit Medicine Form View
 * Academic-style clean form with client-side & server-side validation bindings.
 */

declare(strict_types=1);

$id = (int)($_GET['id'] ?? 0);
$medicine = $id ? fetch_one('SELECT * FROM medicines WHERE id = ?', [$id]) : null;

if ($id && !$medicine) {
    echo '<div class="alert alert-danger">Medicine not found.</div>';
    return;
}

$m = $medicine ?? [
    'id' => '',
    'name' => '',
    'generic_name' => '',
    'brand_name' => '',
    'batch_number' => '',
    'medicine_type' => 'TABLET',
    'purchase_price' => '',
    'selling_price' => '',
    'quantity' => 0,
    'minimum_stock_level' => 10,
    'manufacturing_date' => '',
    'expiry_date' => '',
    'prescription_required' => 0,
    'category_id' => '',
    'supplier_id' => ''
];

$categories = fetch_all('SELECT * FROM categories ORDER BY name');
$suppliers = fetch_all('SELECT * FROM suppliers ORDER BY name');

if (!$categories || !$suppliers) {
    echo '<div class="alert alert-warning">Please add at least one category and supplier before managing medicines.</div>';
}

function med_input(array $m, string $key, string $label, string $type = 'text', string $extra = ''): void
{
    echo '<div class="mb-3">';
    echo '<label class="form-label" for="m_' . e($key) . '">' . e($label) . '</label>';
    echo '<input class="form-control" type="' . e($type) . '" id="m_' . e($key) . '" name="' . e($key) . '" value="' . e((string)$m[$key]) . '" ' . $extra . '>';
    echo '</div>';
}
?>

<div class="page-intro">
    <div>
        <h2><?= $id ? 'Edit Medicine' : 'Add New Medicine' ?></h2>
        <p>Fields marked with * are required.</p>
    </div>
    <a href="<?= e(url('medicines')) ?>" class="btn btn-light border">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<form action="actions.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="medicine_save">
    <input type="hidden" name="id" value="<?= e((string)$m['id']) ?>">

    <div class="row g-4">
        <!-- Main Information -->
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header"><strong>Medicine Information</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <?php med_input($m, 'name', 'Medicine Name *', 'text', 'required maxlength="120"'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php med_input($m, 'generic_name', 'Generic Name *', 'text', 'required maxlength="120"'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php med_input($m, 'brand_name', 'Brand Name', 'text', 'maxlength="120"'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php med_input($m, 'batch_number', 'Batch Number *', 'text', 'required maxlength="60"'); ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="m_type">Dosage Type *</label>
                            <select id="m_type" name="medicine_type" class="form-select" required>
                                <?php foreach (medicine_types() as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $value === $m['medicine_type'] ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="m_category">Category *</label>
                            <select id="m_category" class="form-select" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= e((string)$c['id']) ?>" <?= (string)$m['category_id'] === (string)$c['id'] ? 'selected' : '' ?>>
                                        <?= e($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="m_supplier">Supplier *</label>
                            <select id="m_supplier" class="form-select" name="supplier_id" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= e((string)$s['id']) ?>" <?= (string)$m['supplier_id'] === (string)$s['id'] ? 'selected' : '' ?>>
                                        <?= e($s['company_name'] ?: $s['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock & Expiry -->
            <div class="card">
                <div class="card-header"><strong>Stock &amp; Expiry Controls</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <?php med_input($m, 'quantity', 'Initial Quantity *', 'number', 'min="0" step="1" required'); ?>
                        </div>
                        <div class="col-md-3">
                            <?php med_input($m, 'minimum_stock_level', 'Minimum Stock Alert *', 'number', 'min="0" step="1" required'); ?>
                        </div>
                        <div class="col-md-3">
                            <?php med_input($m, 'manufacturing_date', 'Manufacturing Date', 'date'); ?>
                        </div>
                        <div class="col-md-3">
                            <?php med_input($m, 'expiry_date', 'Expiry Date *', 'date', 'required'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pricing & Regulatory Sidebar -->
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header"><strong>Pricing Configuration</strong></div>
                <div class="card-body">
                    <?php med_input($m, 'purchase_price', 'Cost / Purchase Price (৳) *', 'number', 'min="0.01" step="0.01" required'); ?>
                    <?php med_input($m, 'selling_price', 'Retail / Selling Price (৳) *', 'number', 'min="0.01" step="0.01" required'); ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" id="rx" type="checkbox" name="prescription_required" value="1" <?= $m['prescription_required'] ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="rx">Prescription Required</label>
                    </div>
                    <div class="small text-secondary mt-2">
                        Flag items requiring a valid physician prescription during dispensing.
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100" <?= (!$categories || !$suppliers) ? 'disabled' : '' ?>>
                <i class="bi bi-check-lg me-1"></i>Save Medicine
            </button>
        </div>
    </div>
</form>
