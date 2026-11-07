<?php
/**
 * PharmaCare — Customer Profile View & Editor
 * Allows authenticated customers to manage their profile and delivery contact information.
 * Enforces strict IDOR protection by binding queries exclusively to authenticated session user ID.
 */

declare(strict_types=1);

require_login();

// Strict IDOR guard: Bind query solely to session user ID
$userId = (int)user()['id'];
$u = fetch_one('SELECT id, full_name, username, email, phone, address, role, created_at FROM app_users WHERE id = ?', [$userId]);

if (!$u) {
    echo '<div class="alert alert-danger">User profile could not be retrieved.</div>';
    return;
}
?>

<div class="page-intro mb-4">
    <div>
        <h2>My Account Profile</h2>
        <p>View your account credentials and update your delivery contact information.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Account Information Card (Read-only Metadata) -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <strong>Account Details</strong>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="avatar avatar-lg mx-auto mb-2" style="width: 64px; height: 64px; font-size: 28px; background: rgba(16, 185, 129, 0.1); color: #059669; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <h5 class="mb-0"><?= e($u['full_name']) ?></h5>
                    <span class="text-secondary small">@<?= e($u['username']) ?></span>
                </div>

                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-secondary">Account Role</span>
                        <span class="badge text-bg-success"><?= e($u['role']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-secondary">Email</span>
                        <span class="fw-semibold text-truncate ms-2"><?= e($u['email'] ?: 'Not set') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-secondary">Member Since</span>
                        <span><?= e(nice_date($u['created_at'])) ?></span>
                    </li>
                </ul>

                <div class="alert alert-light border mt-4 mb-0 small text-secondary">
                    <i class="bi bi-shield-check text-success me-1"></i>
                    Your account is protected with Bcrypt encryption and strict session security.
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Form -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <strong>Edit Personal &amp; Delivery Information</strong>
            </div>
            <div class="card-body">
                <form action="actions.php" method="post">
                    <input type="hidden" name="action" value="profile_update">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="prof_fullname">Full Name *</label>
                            <input class="form-control" id="prof_fullname" name="full_name" value="<?= e($u['full_name']) ?>" maxlength="80" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="prof_username">Username</label>
                            <input class="form-control bg-light" id="prof_username" value="<?= e($u['username']) ?>" readonly disabled>
                            <div class="form-hint">Username is your permanent login identifier.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="prof_email">Email Address</label>
                            <input class="form-control bg-light" id="prof_email" value="<?= e($u['email'] ?? '') ?>" readonly disabled>
                            <div class="form-hint">Email is linked to order notifications.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="prof_phone">Contact Phone *</label>
                            <input class="form-control" id="prof_phone" name="phone" type="tel" value="<?= e($u['phone'] ?? '') ?>" maxlength="15" pattern="(01|\+8801)[3-9][0-9]{8}" placeholder="017XXXXXXXX" required>
                            <div class="form-hint">Primary number for delivery coordination.</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="prof_address">Default Delivery Address *</label>
                        <textarea class="form-control" id="prof_address" name="address" rows="3" maxlength="255" placeholder="House / Flat / Road, Area, City" required><?= e($u['address'] ?? '') ?></textarea>
                        <div class="form-hint">Used as default address during online checkout.</div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Save Profile Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
