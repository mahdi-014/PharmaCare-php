<?php
/**
 * PharmaCare — Category Management View
 * Allows creation, updating, and deletion with in-use relational protection.
 */

declare(strict_types=1);

$id = (int)($_GET['edit'] ?? 0);
$edit = $id ? fetch_one('SELECT * FROM categories WHERE id = ?', [$id]) : null;
$categories = fetch_all('SELECT c.*, (SELECT COUNT(*) FROM medicines WHERE category_id = c.id) AS medicines_count FROM categories c ORDER BY c.name');
?>

<div class="row g-4">
    <!-- Category Form -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <strong><?= $edit ? 'Edit Category' : 'Add New Category' ?></strong>
            </div>
            <div class="card-body">
                <form action="actions.php" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="category_save">
                    <input type="hidden" name="id" value="<?= e((string)($edit['id'] ?? '')) ?>">

                    <div class="mb-3">
                        <label class="form-label" for="cat_name">Category Name *</label>
                        <input class="form-control" id="cat_name" name="name" value="<?= e($edit['name'] ?? '') ?>" maxlength="80" required>
                    </div>

                    <div class="mb-3">
                        <label for="cat_desc" class="form-label">Description</label>
                        <textarea class="form-control" rows="3" id="cat_desc" name="description" maxlength="255"><?= e($edit['description'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-check-lg me-1"></i>Save Category
                        </button>
                        <?php if ($edit): ?>
                            <a href="<?= e(url('categories')) ?>" class="btn btn-light border">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Category Directory -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Category Directory</strong>
                <span class="data-meta"><?= count($categories) ?> categories</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th>Attached Medicines</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $c): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($c['name']) ?></td>
                                <td class="text-secondary"><?= e($c['description'] ?? '—') ?></td>
                                <td>
                                    <span class="badge text-bg-light border"><?= e((string)$c['medicines_count']) ?> items</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= e(url('categories', ['edit' => $c['id']])) ?>" class="btn btn-sm btn-light border" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="actions.php" method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="category_delete">
                                        <input type="hidden" name="id" value="<?= e((string)$c['id']) ?>">
                                        <button class="btn btn-sm btn-outline-danger"
                                                title="Delete"
                                                data-confirm="Delete category '<?= e($c['name']) ?>'?"
                                                <?= $c['medicines_count'] ? 'disabled title="Cannot delete: attached to active medicines"' : '' ?>>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$categories): ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="bi bi-tags"></i>
                                        <p>No categories configured yet.</p>
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
