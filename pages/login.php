<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In | PharmaCare</title>
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
                <span class="badge text-bg-light text-success mb-3">WEB ENGINEERING PROJECT</span>
                <h1>Smarter pharmacy operations, from stock to sale.</h1>
                <p>Track medicine inventory, prevent expired sales, receive low-stock alerts and create clean invoices from one secure system.</p>
                <div class="login-feature">
                    <i class="bi bi-shield-check"></i>Role-based access for administrators and pharmacy staff
                </div>
                <div class="login-feature">
                    <i class="bi bi-activity"></i>Real-time stock and expiry status
                </div>
            </div>
            <small class="text-white-50">Powered by PHP 8 &amp; MySQL</small>
        </section>

        <section class="login-form-wrap">
            <div class="login-form">
                <div class="mb-4">
                    <span class="text-success fw-semibold">WELCOME BACK</span>
                    <h2 class="mt-1">Sign in to PharmaCare</h2>
                    <p class="text-secondary">Use your authorized account to continue.</p>
                </div>

                <?php show_flashes(); ?>

                <form action="actions.php" method="post">
                    <input type="hidden" name="action" value="login">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-person"></i></span>
                            <input class="form-control border-start-0" name="username" id="username" autocomplete="username" placeholder="Enter username" autofocus required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-lock"></i></span>
                            <input class="form-control border-start-0" type="password" name="password" id="password" autocomplete="current-password" placeholder="Enter password" required>
                        </div>
                    </div>

                    <button class="btn btn-primary btn-lg w-100" type="submit">
                        Sign In <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </form>

                <div class="demo-credentials mt-4">
                    <div class="fw-semibold mb-1"><i class="bi bi-key me-1"></i>Demo credentials</div>
                    <div><strong>Admin:</strong> admin / admin123</div>
                    <div><strong>Staff:</strong> staff / staff123</div>
                    <small class="text-secondary">For coursework/demo only. Change passwords before hosting.</small>
                </div>
            </div>
        </section>
    </div>
</body>
</html>
