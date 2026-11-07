<?php
/**
 * PharmaCare — Supplier Management View
 * Directory for pharmaceutical vendors and distributors.
 */

declare(strict_types=1);

$id = (int)($_GET['edit'] ?? 0);
$edit = $id ? fetch_one('SELECT * FROM suppliers WHERE id = ?', [$id]) : null;
$suppliers = fetch_all('SELECT s.*, (SELECT COUNT(*) FROM medicines m WHERE m.supplier_id = s.id) AS medicines_count FROM suppliers s ORDER BY s.name');
?>

<div class="row g-4">
    <!-- Supplier Form -->
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">
                <strong><?= $edit ? 'Edit Supplier' : 'Add New Supplier' ?></strong>
            </div>
            <div class="card-body">
                <form action="actions.php" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="supplier_save">
                    <input type="hidden" name="id" value="<?= e((string)($edit['id'] ?? '')) ?>">

                    <div class="mb-3">
                        <label class="form-label" for="sup_name">Contact Person *</label>
                        <input type="text" id="sup_name" name="name" maxlength="100" class="form-control" value="<?= e($edit['name'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="sup_company">Company / Manufacturer Name</label>
                        <input type="text" id="sup_company" name="company_name" maxlength="120" class="form-control" value="<?= e($edit['company_name'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="sup_phone">Phone Number *</label>
                        <input type="tel" id="sup_phone" name="phone" maxlength="30" class="form-control" value="<?= e($edit['phone'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="sup_email">Email Address</label>
                        <input type="email" id="sup_email" name="email" maxlength="120" class="form-control" value="<?= e($edit['email'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="sup_address">Physical Address</label>
                        <textarea class="form-control" rows="2" id="sup_address" maxlength="255" name="address"><?= e($edit['address'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1" type="submit">
                            <i class="bi bi-check-lg me-1"></i>Save Supplier
                        </button>
                        <?php if ($edit): ?>
                            <a class="btn btn-light border" href="<?= e(url('suppliers')) ?>">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Supplier Directory -->
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Supplier Directory</strong>
                <span class="data-meta"><?= count($suppliers) ?> suppliers</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Supplier / Company</th>
                            <th>Contact Details</th>
                            <th>Address</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td>
                                    <strong><?= e($s['company_name'] ?: $s['name']) ?></strong>
                                    <span class="subtext"><?= e($s['name']) ?> &middot; <?= e((string)$s['medicines_count']) ?> medicines</span>
                                </td>
                                <td>
                                    <?= e($s['phone']) ?>
                                    <span class="subtext"><?= e($s['email'] ?: 'No email') ?></span>
                                </td>
                                <td class="text-secondary"><?= e($s['address'] ?? '—') ?></td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= e(url('suppliers', ['edit' => $s['id']])) ?>" class="btn btn-sm btn-light border" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form class="d-inline" action="actions.php" method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="supplier_delete">
                                        <input type="hidden" name="id" value="<?= e((string)$s['id']) ?>">
                                        <button class="btn btn-sm btn-outline-danger"
                                                data-confirm="Delete supplier '<?= e($s['name']) ?>'?"
                                                title="Delete"
                                                <?= $s['medicines_count'] ? 'disabled title="Cannot delete: attached to active medicines"' : '' ?>>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$suppliers): ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="bi bi-truck"></i>
                                        <p>No suppliers registered yet.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
