<?php
/**
 * PharmaCare — Unified Executive Dashboard & Real-Time Analytics View
 * Displays high-level analytics, unified channel revenue, inventory health,
 * and operational fulfillment alerts across POS and online ordering modules.
 */

declare(strict_types=1);

use App\Services\DashboardService;

require_staff_or_admin();

$summary = DashboardService::getSummary();
$fin = $summary['financial'];
$inv = $summary['inventory'];
$ops = $summary['operations'];
$topSelling = $summary['top_selling'];
$recentSales = $summary['recent_transactions'];
$alerts = $summary['inventory_alerts'];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <div class="text-secondary small fw-bold text-uppercase">Operational Analytics &amp; Control</div>
        <h2 class="h4 mb-0 fw-bold">Welcome, <?= e(user()['full_name']) ?> <span class="fs-5">👋</span></h2>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= e(url('catalog')) ?>" class="btn btn-outline-secondary" title="View customer storefront catalog">
            <i class="bi bi-shop me-1"></i>Storefront View
        </a>
        <a href="<?= e(url('orders')) ?>" class="btn btn-outline-primary" title="View online fulfillment queue">
            <i class="bi bi-box-seam me-1"></i>Orders Queue
            <?php if ($ops['pending_orders'] > 0): ?>
                <span class="badge text-bg-warning ms-1"><?= $ops['pending_orders'] ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= e(url('sale_form')) ?>" class="btn btn-primary shadow-sm">
            <i class="bi bi-cart-plus me-1"></i>Create New Sale
        </a>
    </div>
</div>

<!-- Operational Warning Banners -->
<?php if ($ops['pending_prescriptions'] > 0 || $inv['low_stock_count'] > 0 || $ops['pending_orders'] > 0): ?>
    <div class="mb-4">
        <?php if ($ops['pending_prescriptions'] > 0): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex justify-content-between align-items-center mb-2 shadow-sm border-danger" role="alert">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-file-earmark-medical-fill fs-3 text-danger"></i>
                    <div>
                        <strong class="d-block text-danger">Prescription Review Required (<?= $ops['pending_prescriptions'] ?> Pending)</strong>
                        <span class="small text-secondary">Customer orders containing prescription medicines require pharmacist verification before fulfillment.</span>
                    </div>
                </div>
                <div class="d-flex gap-2 align-items-center ms-3">
                    <a href="<?= e(url('orders', ['filter_rx' => 'needs_review'])) ?>" class="btn btn-sm btn-danger fw-bold text-nowrap">
                        <i class="bi bi-check2-circle me-1"></i>Review Rx Queue
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($inv['low_stock_count'] > 0): ?>
            <div class="alert alert-warning alert-dismissible fade show d-flex justify-content-between align-items-center mb-2 shadow-sm border-warning" role="alert">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
                    <div>
                        <strong class="d-block text-dark">Low Stock Alert (<?= $inv['low_stock_count'] ?> Batches &le; 10 Units)</strong>
                        <span class="small text-secondary">Active medicine inventory batches have reached critical replenishment thresholds.</span>
                    </div>
                </div>
                <div class="d-flex gap-2 align-items-center ms-3">
                    <a href="<?= e(url('medicines', ['status' => 'low_stock'])) ?>" class="btn btn-sm btn-warning fw-bold text-nowrap">
                        <i class="bi bi-arrow-repeat me-1"></i>Restock Inventory
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($ops['pending_orders'] > 0 && $ops['pending_prescriptions'] === 0): ?>
            <div class="alert alert-info alert-dismissible fade show d-flex justify-content-between align-items-center mb-2 shadow-sm border-info" role="alert">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-box-seam-fill fs-3 text-info"></i>
                    <div>
                        <strong class="d-block text-dark">Online Orders Awaiting Fulfillment (<?= $ops['pending_orders'] ?> Orders)</strong>
                        <span class="small text-secondary">Customer online orders are awaiting confirmation, packing, or dispatch in the fulfillment queue.</span>
                    </div>
                </div>
                <div class="d-flex gap-2 align-items-center ms-3">
                    <a href="<?= e(url('orders')) ?>" class="btn btn-sm btn-info text-white fw-bold text-nowrap">
                        <i class="bi bi-arrow-right-circle me-1"></i>Fulfillment Queue
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- 5 Primary Metric Cards Row -->
<div class="row g-3 mb-4">
    <!-- Card 1: Today's Sales -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card stat-card h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="label text-muted small fw-bold text-uppercase">Today's Sales</div>
                        <div class="value text-primary fs-3 fw-bold mt-1 mb-1"><?= money($fin['today_total_sales']) ?></div>
                    </div>
                    <div class="icon icon-brand">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>
                <div class="pt-2 border-top mt-2 small text-muted">
                    <div class="d-flex justify-content-between">
                        <span>POS: <strong class="text-dark"><?= money($fin['today_pos_sales']) ?></strong></span>
                        <span>Online: <strong class="text-primary"><?= money($fin['today_online_sales']) ?></strong></span>
                    </div>
                    <div class="text-secondary mt-1" style="font-size: 11px;">
                        <i class="bi bi-receipt me-1"></i><?= $fin['today_invoices_count'] ?> transaction(s) today
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Total Revenue -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card stat-card h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="label text-muted small fw-bold text-uppercase">Total Revenue</div>
                        <div class="value text-success fs-3 fw-bold mt-1 mb-1"><?= money($fin['total_revenue']) ?></div>
                    </div>
                    <div class="icon icon-success">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
                <div class="pt-2 border-top mt-2 small text-muted">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Invoices: <strong class="text-dark"><?= number_format($fin['total_invoices_all']) ?></strong></span>
                        <span class="badge text-bg-light border text-secondary" style="font-size: 10px;">All Channels</span>
                    </div>
                    <div class="text-secondary mt-1" style="font-size: 11px;">
                        <i class="bi bi-shield-check me-1"></i>Synchronized ledger
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: Stock Alerts -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card stat-card h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="label text-muted small fw-bold text-uppercase">Stock Alerts</div>
                        <div class="value text-warning fs-3 fw-bold mt-1 mb-1"><?= $inv['low_stock_count'] + $inv['expired_count'] ?></div>
                    </div>
                    <div class="icon icon-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="pt-2 border-top mt-2 small">
                    <div class="d-flex flex-wrap gap-1">
                        <span class="badge text-bg-warning"><?= $inv['low_stock_count'] ?> Low Stock</span>
                        <span class="badge text-bg-danger"><?= $inv['expired_count'] ?> Expired</span>
                    </div>
                    <div class="text-secondary mt-1" style="font-size: 11px;">
                        <i class="bi bi-clock-history me-1"></i><?= $inv['expiring_soon_count'] ?> expiring within 60 days
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 4: Fulfillment -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card stat-card h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="label text-muted small fw-bold text-uppercase">Fulfillment</div>
                        <div class="value text-info fs-3 fw-bold mt-1 mb-1"><?= $ops['pending_orders'] ?></div>
                    </div>
                    <div class="icon icon-info">
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>
                <div class="pt-2 border-top mt-2 small">
                    <div class="d-flex flex-wrap gap-1">
                        <a href="<?= e(url('orders')) ?>" class="badge text-bg-primary text-decoration-none"><?= $ops['pending_orders'] ?> Orders</a>
                        <a href="<?= e(url('orders', ['filter_rx' => 'needs_review'])) ?>" class="badge text-bg-info text-dark text-decoration-none"><?= $ops['pending_prescriptions'] ?> Rx Pending</a>
                    </div>
                    <div class="text-secondary mt-1" style="font-size: 11px;">
                        <i class="bi bi-arrow-right-circle me-1"></i>Awaiting fulfillment
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 5: Total Inventory -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card stat-card h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="label text-muted small fw-bold text-uppercase">Total Inventory</div>
                        <div class="value text-dark fs-3 fw-bold mt-1 mb-1"><?= number_format($inv['active_batches']) ?></div>
                    </div>
                    <div class="icon icon-dark">
                        <i class="bi bi-capsule"></i>
                    </div>
                </div>
                <div class="pt-2 border-top mt-2 small text-muted">
                    <div>
                        Physical Units: <strong class="text-dark"><?= number_format($inv['active_stock_units']) ?></strong>
                    </div>
                    <div class="text-secondary mt-1" style="font-size: 11px;">
                        <i class="bi bi-check2-circle me-1"></i>Active batches available
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 2-Column Analytics Layout -->
<div class="row g-4 mb-4">
    <!-- Left Column: Channel Comparison & Top Selling Medicines -->
    <div class="col-xl-5">
        <!-- Sales by Channel Comparison -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong>Sales by Channel</strong>
                    <div class="small text-secondary">All-time revenue &amp; volume distribution</div>
                </div>
                <span class="badge text-bg-light border text-secondary">
                    Total: <?= money($fin['total_revenue']) ?>
                </span>
            </div>
            <div class="card-body">
                <!-- Visual Progress Bar -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between small fw-bold mb-1">
                        <span class="text-success"><i class="bi bi-shop me-1"></i>POS: <?= $fin['pos_percentage'] ?>%</span>
                        <span class="text-primary"><i class="bi bi-globe me-1"></i>Online: <?= $fin['online_percentage'] ?>%</span>
                    </div>
                    <div class="progress" style="height: 12px; border-radius: 6px; background-color: #e2e8f0;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $fin['pos_percentage'] ?>%" aria-valuenow="<?= $fin['pos_percentage'] ?>" aria-valuemin="0" aria-valuemax="100" title="POS: <?= $fin['pos_percentage'] ?>%"></div>
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $fin['online_percentage'] ?>%" aria-valuenow="<?= $fin['online_percentage'] ?>" aria-valuemin="0" aria-valuemax="100" title="Online: <?= $fin['online_percentage'] ?>%"></div>
                    </div>
                </div>

                <!-- Channel Breakdown Cards -->
                <div class="row g-2">
                    <div class="col-6">
                        <div class="p-3 border rounded-3 bg-light">
                            <div class="d-flex align-items-center gap-1 text-success fw-bold small text-uppercase">
                                <i class="bi bi-shop"></i> POS Counter
                            </div>
                            <div class="h5 mb-0 fw-bold mt-1 text-dark"><?= money($fin['all_pos_sales']) ?></div>
                            <div class="small text-secondary mt-1" style="font-size: 11px;">
                                <?= $fin['all_pos_count'] ?> sales (<?= $fin['pos_percentage'] ?>%)
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 border rounded-3 bg-light">
                            <div class="d-flex align-items-center gap-1 text-primary fw-bold small text-uppercase">
                                <i class="bi bi-globe"></i> Online Orders
                            </div>
                            <div class="h5 mb-0 fw-bold mt-1 text-dark"><?= money($fin['all_online_sales']) ?></div>
                            <div class="small text-secondary mt-1" style="font-size: 11px;">
                                <?= $fin['all_online_count'] ?> orders (<?= $fin['online_percentage'] ?>%)
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Selling Medicines -->
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong>Top-Selling Medicines</strong>
                    <div class="small text-secondary">Highest dispensing volume across all sales</div>
                </div>
                <a href="<?= e(url('medicines')) ?>" class="btn btn-sm btn-outline-primary">Catalog</a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($topSelling)): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($topSelling as $rank => $item): ?>
                            <li class="list-group-item d-flex align-items-center justify-content-between px-3 py-2">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge rounded-pill bg-<?= $rank === 0 ? 'warning text-dark' : ($rank === 1 ? 'secondary' : 'light text-dark border') ?>" style="width: 24px; height: 24px; display: grid; place-items: center; font-size: 11px;">
                                        <?= $rank + 1 ?>
                                    </span>
                                    <div>
                                        <strong class="d-block text-dark"><?= e($item['name']) ?></strong>
                                        <div class="small text-secondary">
                                            <?= e($item['generic_name']) ?> &bull; <span class="badge text-bg-light border text-secondary" style="font-size: 10px;"><?= e($item['category_name']) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge text-bg-primary fs-6"><?= number_format((int)$item['total_units_sold']) ?> sold</span>
                                    <div class="small text-muted mt-1"><?= money($item['total_revenue_generated']) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state py-4 text-center text-muted">
                        <i class="bi bi-cart-x fs-2"></i>
                        <p class="mb-0 mt-2 small">No sales transaction items recorded yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Recent Unified Transactions Feed -->
    <div class="col-xl-7">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong>Recent Unified Transactions</strong>
                    <div class="small text-secondary">POS counter sales &amp; bridged customer online orders</div>
                </div>
                <a href="<?= e(url('sales')) ?>" class="btn btn-sm btn-outline-primary">All Sales History</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice / Order #</th>
                            <th>Channel</th>
                            <th>Customer / Seller</th>
                            <th>Date &amp; Time</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td>
                                    <a class="fw-semibold text-dark text-decoration-none" href="<?= e(url('invoice', ['id' => $sale['id']])) ?>">
                                        <?= e($sale['invoice_number']) ?>
                                    </a>
                                    <?php if (!empty($sale['order_number'])): ?>
                                        <div class="small text-muted" style="font-size: 11px;">
                                            Ref: <a href="<?= e(url('order_detail', ['id' => $sale['order_id']])) ?>" class="text-secondary"><?= e($sale['order_number']) ?></a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($sale['channel'] === 'ONLINE'): ?>
                                        <span class="badge text-bg-primary">ONLINE</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-success">POS</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($sale['customer_name'] ?: 'Counter Walk-in') ?></div>
                                    <div class="small text-muted" style="font-size: 11px;">
                                        Seller: <?= e($sale['seller_name'] ?? 'System') ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-nowrap small text-secondary">
                                        <?= e(nice_date($sale['sale_date'], true)) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <strong class="text-dark"><?= money($sale['total_amount']) ?></strong>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= e(url('invoice', ['id' => $sale['id']])) ?>" class="btn btn-outline-secondary" title="View Printable Invoice">
                                            <i class="bi bi-receipt"></i>
                                        </a>
                                        <?php if (!empty($sale['order_id'])): ?>
                                            <a href="<?= e(url('order_detail', ['id' => $sale['order_id']])) ?>" class="btn btn-outline-primary" title="View Fulfillment Order">
                                                <i class="bi bi-box-seam"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentSales)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state py-5 text-center text-muted">
                                        <i class="bi bi-receipt fs-1"></i>
                                        <p class="mb-0 mt-2">No completed sales transactions yet.</p>
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

<!-- Inventory Stock & Expiry Health Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <strong>Inventory Stock &amp; Expiry Alerts</strong>
            <div class="small text-secondary">Batches requiring restock replenishment or disposal review</div>
        </div>
        <a href="<?= e(url('medicines')) ?>" class="btn btn-sm btn-outline-primary">Manage Inventory</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Medicine</th>
                    <th>Batch #</th>
                    <th>Category</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th>Available Stock</th>
                    <th>Expiry Date</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alerts as $m): ?>
                    <tr>
                        <td>
                            <strong class="text-dark"><?= e($m['name']) ?></strong>
                            <div class="small text-secondary"><?= e($m['generic_name']) ?></div>
                        </td>
                        <td>
                            <code class="text-dark"><?= e($m['batch_number']) ?></code>
                        </td>
                        <td>
                            <span class="badge text-bg-light border text-secondary"><?= e($m['category_name']) ?></span>
                        </td>
                        <td>
                            <span class="small text-secondary"><?= e($m['supplier_name'] ?? '—') ?></span>
                        </td>
                        <td>
                            <span class="badge text-bg-<?= e(status_badge($m['computed_status'])) ?>">
                                <?= e(status_label($m['computed_status'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="fw-bold <?= (int)$m['quantity'] <= 10 ? 'text-danger' : 'text-dark' ?>">
                                <?= e((string)$m['quantity']) ?> units
                            </span>
                        </td>
                        <td>
                            <span class="small text-secondary"><?= e(nice_date($m['expiry_date'])) ?></span>
                        </td>
                        <td class="text-center">
                            <?php if (is_admin()): ?>
                                <a href="<?= e(url('medicine_form', ['id' => $m['id']])) ?>" class="btn btn-sm btn-outline-secondary" title="Edit Batch">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            <?php else: ?>
                                <a href="<?= e(url('medicines', ['keyword' => $m['batch_number']])) ?>" class="btn btn-sm btn-outline-secondary" title="View Batch">
                                    <i class="bi bi-eye"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($alerts)): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state py-4 text-center text-muted">
                                <i class="bi bi-check2-circle fs-2 text-success"></i>
                                <p class="mb-0 mt-2">All medicine batches are in healthy stock and valid expiry conditions.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
