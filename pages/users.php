<?php
/**
 * PharmaCare — User Account Administration View
 * Manages administrative and staff credentials, permissions, and active statuses.
 */

declare(strict_types=1);

$users = fetch_all('SELECT id, full_name, username, role, enabled FROM app_users ORDER BY full_name');
?>

<div class="row g-4">
    <!-- User Creation Form -->
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">
                <strong>Create User Account</strong>
            </div>
            <div class="card-body">
                <form action="actions.php" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="user_create">

                    <div class="mb-3">
                        <label class="form-label" for="u_name">Full Name *</label>
                        <input id="u_name" name="full_name" class="form-control" maxlength="80" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="u_username">Username *</label>
                        <input id="u_username" name="username" class="form-control" minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]+" autocomplete="off" required>
                        <div class="form-hint">3–50 alphanumeric characters, hyphens, dots, or underscores.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="u_pass">Password *</label>
                        <input id="u_pass" type="password" name="password" class="form-control" minlength="8" maxlength="72" autocomplete="new-password" required>
                        <div class="form-hint">Minimum 8 characters (hashed with Bcrypt).</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="u_role">Role Authority *</label>
                        <select class="form-select" name="role" id="u_role">
                            <option value="STAFF">STAFF (Dispensary &amp; Sales)</option>
                            <option value="ADMIN">ADMIN (Full Permissions)</option>
                        </select>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" id="u_enabled" name="enabled" value="1" type="checkbox" checked>
                        <label class="form-check-label" for="u_enabled">Account Active</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-plus me-1"></i>Create Account
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Authorized Users Directory -->
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Authorized Users</strong>
                <span class="data-meta"><?= count($users) ?> accounts</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>User Name</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <strong><?= e($u['full_name']) ?></strong>
                                    <span class="subtext">@<?= e($u['username']) ?></span>
                                </td>
                                <td>
                                    <span class="badge text-bg-<?= $u['role'] === 'ADMIN' ? 'primary' : 'secondary' ?>">
                                        <?= e($u['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge text-bg-<?= $u['enabled'] ? 'success' : 'danger' ?>">
                                        <?= $u['enabled'] ? 'Enabled' : 'Disabled' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <form action="actions.php" method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="user_toggle">
                                        <input type="hidden" name="id" value="<?= e((string)$u['id']) ?>">
                                        <button class="btn btn-sm btn-outline-<?= $u['enabled'] ? 'danger' : 'success' ?>"
                                                data-confirm="Change status for user '@<?= e($u['username']) ?>'?"
                                                <?= (int)$u['id'] === (int)user()['id'] ? 'disabled title="Cannot disable your own session account"' : '' ?>>
                                            <?= $u['enabled'] ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
