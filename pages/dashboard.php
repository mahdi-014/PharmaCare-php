<?php
/**
 * PharmaCare — Executive Dashboard View
 * Displays high-level analytics, stock/expiry alerts, and recent sales invoices.
 */

declare(strict_types=1);

$medicines = fetch_all(medicine_query() . ' ORDER BY m.name, m.batch_number');
$counts = array_fill_keys(['AVAILABLE', 'LOW_STOCK', 'OUT_OF_STOCK', 'EXPIRED', 'EXPIRING_SOON', 'INACTIVE'], 0);
$alerts = [];

foreach ($medicines as $m) {
    $status = medicine_status($m);
    $counts[$status]++;
    if ((int)$m['active'] && $status !== 'AVAILABLE' && count($alerts) < 8) {
        $alerts[] = $m + ['computed_status' => $status];
    }
}

$totalActive = count($medicines) - $counts['INACTIVE'];
$stats = fetch_one('SELECT COUNT(*) AS invoices, COALESCE(SUM(CASE WHEN DATE(sale_date) = CURDATE() THEN total_amount ELSE 0 END), 0) AS today_sales FROM sales');
$recentSales = fetch_all('SELECT s.*, u.username FROM sales s JOIN app_users u ON s.sold_by_id = u.id ORDER BY s.sale_date DESC, s.id DESC LIMIT 10');
$pendingOrdersCount = 0;
try {
    $pendingOrdersCount = (int)scalar("SELECT COUNT(*) FROM orders WHERE order_status IN ('PENDING', 'CONFIRMED')");
} catch (Throwable $e) {}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <div class="text-secondary small fw-bold">TODAY'S OVERVIEW</div>
        <h2 class="h4 mb-0">Welcome, <?= e(user()['full_name']) ?> <span class="fs-5">👋</span></h2>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(url('catalog')) ?>" class="btn btn-outline-secondary" title="View customer storefront catalog">
            <i class="bi bi-shop me-1"></i>Storefront View
        </a>
        <a href="<?= e(url('sale_form')) ?>" class="btn btn-primary">
            <i class="bi bi-cart-plus me-1"></i>Create New Sale
        </a>
    </div>
</div>

<?php if ($pendingOrdersCount > 0): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center mb-4 shadow-sm border-warning">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-box-seam-fill fs-4 text-warning"></i>
            <div>
                <strong><?= $pendingOrdersCount ?> Online Order(s) Awaiting Fulfillment!</strong>
                <div class="small text-secondary d-none d-md-block">New customer orders require prescription review and dispatch in the orders queue.</div>
            </div>
        </div>
        <a href="<?= e(url('orders')) ?>" class="btn btn-sm btn-warning fw-bold text-nowrap ms-3">
            <i class="bi bi-arrow-right-circle me-1"></i>Orders Queue
        </a>
    </div>
<?php endif; ?>

<!-- Primary Metric Cards -->
<div class="row g-3 mb-4">
    <?php
    $metricCards = [
        ['capsule', 'brand', $totalActive, 'Active medicines'],
        ['exclamation-triangle', 'warning', $counts['LOW_STOCK'], 'Low-stock items'],
        ['calendar-x', 'danger', $counts['EXPIRED'], 'Expired medicines'],
        ['cash-stack', 'info', money($stats['today_sales']), "Today's sales"]
    ];
    foreach ($metricCards as $i => $s): ?>
        <div class="col-6 col-xl-3 animate-fade-up <?= $i ? 'delay-' . min($i * 100, 300) : '' ?>">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="icon icon-<?= e($s[1]) ?>">
                        <i class="bi bi-<?= e($s[0]) ?>"></i>
                    </div>
                    <div class="value"><?= e((string)$s[2]) ?></div>
                    <div class="label"><?= e($s[3]) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Stock & Expiry Alerts -->
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong>Stock &amp; Expiry Alerts</strong>
                    <div class="small text-secondary">Items requiring immediate attention</div>
                </div>
                <a href="<?= e(url('medicines')) ?>" class="btn btn-sm btn-outline-primary">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Status</th>
                            <th>Stock</th>
                            <th>Expiry</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alerts as $m): ?>
                            <tr>
                                <td>
                                    <strong><?= e($m['name']) ?></strong>
                                    <span class="subtext"><?= e($m['generic_name']) ?></span>
                                </td>
                                <td>
                                    <span class="badge text-bg-<?= e(status_badge($m['computed_status'])) ?>">
                                        <?= e(status_label($m['computed_status'])) ?>
                                    </span>
                                </td>
                                <td><?= e($m['quantity']) ?></td>
                                <td><?= e(nice_date($m['expiry_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$alerts): ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state py-4">
                                        <i class="bi bi-check2-circle"></i>
                                        <p>No inventory alerts at this time.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Completed Invoices -->
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong>Recent Sales</strong>
                    <div class="small text-secondary">Latest completed transactions</div>
                </div>
                <a href="<?= e(url('sales')) ?>" class="btn btn-sm btn-outline-primary">History</a>
            </div>
            <div class="card-body p-0">
                <?php foreach ($recentSales as $s): ?>
                    <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <a class="fw-semibold text-dark" href="<?= e(url('invoice', ['id' => $s['id']])) ?>">
                                    <?= e($s['invoice_number']) ?>
                                </a>
                                <?php if (($s['channel'] ?? 'POS') === 'ONLINE'): ?>
                                    <span class="badge text-bg-primary" style="font-size: 10px;">Online</span>
                                <?php endif; ?>
                            </div>
                            <span class="subtext"><?= e(nice_date($s['sale_date'], true)) ?></span>
                        </div>
                        <div class="text-end">
                            <strong><?= money($s['total_amount']) ?></strong>
                            <span class="subtext"><?= e($s['username']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$recentSales): ?>
                    <div class="empty-state">
                        <i class="bi bi-receipt"></i>
                        <p>No sales completed yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Secondary Metric Badges -->
<div class="row g-3 mt-2">
    <?php
    $secondaryStats = [
        ['Available for Sale', $counts['AVAILABLE']],
        ['Out of Stock', $counts['OUT_OF_STOCK']],
        ['Expiring in 30 Days', $counts['EXPIRING_SOON']],
        ['Total Invoices Issued', $stats['invoices']]
    ];
    foreach ($secondaryStats as $c): ?>
        <div class="col-6 col-md-3">
            <div class="card">
                <div class="card-body">
                    <small class="text-secondary"><?= e($c[0]) ?></small>
                    <div class="h4 mb-0"><?= e((string)$c[1]) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
