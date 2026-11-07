<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Registration | PharmaCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div class="login-page">
        <section class="login-showcase">
            <div class="login-brand">
                <span class="brand-mark"><i class="bi bi-capsule-pill"></i></span>PharmaCare
            </div>
            <div class="login-copy">
                <span class="badge text-bg-light text-success mb-3">ONLINE MEDICINE DISPENSARY</span>
                <h1>Reliable healthcare, delivered to your doorstep.</h1>
                <p>Register as a customer to browse genuine medicines, check batch availability, upload prescriptions, and track your orders in real time.</p>
                <div class="login-feature">
                    <i class="bi bi-shield-check"></i>100% verified pharmacy inventory batches
                </div>
                <div class="login-feature">
                    <i class="bi bi-truck"></i>Fast home delivery &amp; store pickup options
                </div>
                <div class="login-feature">
                    <i class="bi bi-file-medical"></i>Secure prescription verification by licensed staff
                </div>
            </div>
            <small class="text-white-50">Powered by PHP 8 &amp; MySQL</small>
        </section>

        <section class="login-form-wrap">
            <div class="login-form" style="max-width: 520px;">
                <div class="mb-3">
                    <span class="text-success fw-semibold">JOIN PHARMACARE</span>
                    <h2 class="mt-1">Create Customer Account</h2>
                    <p class="text-secondary small">Fill in your information to start ordering medicines.</p>
                </div>

                <?php show_flashes(); ?>

                <form action="actions.php" method="post" id="registerForm">
                    <input type="hidden" name="action" value="register">
                    <?= csrf_field() ?>

                    <div class="row g-2">
                        <div class="col-md-6 mb-2">
                            <label class="form-label" for="full_name">Full Name *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-person"></i></span>
                                <input class="form-control border-start-0" name="full_name" id="full_name" maxlength="80" placeholder="Rahim Ahmed" required>
                            </div>
                        </div>

                        <div class="col-md-6 mb-2">
                            <label class="form-label" for="username">Username *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-at"></i></span>
                                <input class="form-control border-start-0" name="username" id="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]+" autocomplete="username" placeholder="rahim_bd" required>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 mb-2">
                            <label class="form-label" for="email">Email Address *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-envelope"></i></span>
                                <input class="form-control border-start-0" type="email" name="email" id="email" maxlength="120" placeholder="rahim@example.com" required>
                            </div>
                        </div>

                        <div class="col-md-6 mb-2">
                            <label class="form-label" for="phone">Phone Number *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-telephone"></i></span>
                                <input class="form-control border-start-0" type="tel" name="phone" id="phone" maxlength="15" pattern="(01|\+8801)[3-9][0-9]{8}" placeholder="017XXXXXXXX" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label" for="address">Delivery Address *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-geo-alt"></i></span>
                            <input class="form-control border-start-0" name="address" id="address" maxlength="255" placeholder="House 12, Road 5, Dhanmondi, Dhaka" required>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="password">Password *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-lock"></i></span>
                                <input class="form-control border-start-0" type="password" name="password" id="password" minlength="8" maxlength="72" autocomplete="new-password" placeholder="Min. 8 characters" required>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="confirm_password">Confirm Password *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-lock-fill"></i></span>
                                <input class="form-control border-start-0" type="password" name="confirm_password" id="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" placeholder="Repeat password" required>
                            </div>
                        </div>
                    </div>

                    <button class="btn btn-primary btn-lg w-100" type="submit">
                        <i class="bi bi-person-plus me-1"></i>Create Customer Account
                    </button>
                </form>

                <div class="text-center mt-3 pt-2 border-top">
                    <span class="text-secondary small">Already have an account?</span>
                    <a href="index.php?page=login" class="fw-semibold text-success ms-1 small">Sign In here</a>
                </div>
            </div>
        </section>
    </div>
</body>
</html>
