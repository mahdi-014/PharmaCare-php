<?php
/**
 * PharmaCare — Staff Order Management Queue
 * Displays online customer orders with multi-parameter filtering,
 * prescription review status badges, and dispatch fulfillment triggers.
 */

declare(strict_types=1);

require_staff_or_admin();

// Filter parameters
$statusFilter = trim((string)($_GET['status'] ?? ''));
$paymentFilter = trim((string)($_GET['payment'] ?? ''));
$rxFilter = trim((string)($_GET['prescription'] ?? ''));
$searchKeyword = trim((string)($_GET['search'] ?? ''));

// Summary counts
$counts = [
    'total' => (int)scalar('SELECT COUNT(*) FROM orders'),
    'pending' => (int)scalar("SELECT COUNT(*) FROM orders WHERE order_status = 'PENDING'"),
    'processing' => (int)scalar("SELECT COUNT(*) FROM orders WHERE order_status IN ('CONFIRMED', 'PROCESSING', 'READY')"),
    'needs_rx' => (int)scalar("SELECT COUNT(*) FROM orders WHERE prescription_status = 'PENDING_REVIEW'"),
    'completed' => (int)scalar("SELECT COUNT(*) FROM orders WHERE order_status = 'COMPLETED'"),
];

// Build filtered query
$sql = 'SELECT o.*, 
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count 
        FROM orders o WHERE 1=1';
$params = [];

if ($statusFilter !== '') {
    $sql .= ' AND o.order_status = ?';
    $params[] = $statusFilter;
}

if ($paymentFilter !== '') {
    $sql .= ' AND o.payment_status = ?';
    $params[] = $paymentFilter;
}

if ($rxFilter === 'required') {
    $sql .= ' AND o.prescription_required = 1';
} elseif ($rxFilter === 'needs_review') {
    $sql .= " AND o.prescription_status = 'PENDING_REVIEW'";
} elseif ($rxFilter === 'otc') {
    $sql .= ' AND o.prescription_required = 0';
}

if ($searchKeyword !== '') {
    $sql .= ' AND (o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)';
    $needle = '%' . $searchKeyword . '%';
    array_push($params, $needle, $needle, $needle);
}

$sql .= ' ORDER BY o.id DESC';
$orders = fetch_all($sql, $params);
?>

<div class="page-intro mb-4">
    <div>
        <h2>Online Orders Management</h2>
        <p>Review customer orders, verify doctor prescriptions, and manage dispensary fulfillment workflow.</p>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-warning">
            <div class="card-body">
                <div class="text-secondary small fw-semibold text-uppercase">Pending Orders</div>
                <div class="h3 mb-0 mt-1 text-warning fw-bold"><?= $counts['pending'] ?></div>
                <small class="text-muted">Awaiting confirmation</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-danger">
            <div class="card-body">
                <div class="text-secondary small fw-semibold text-uppercase">Prescriptions to Review</div>
                <div class="h3 mb-0 mt-1 text-danger fw-bold"><?= $counts['needs_rx'] ?></div>
                <small class="text-muted">Requires pharmacist sign-off</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-primary">
            <div class="card-body">
                <div class="text-secondary small fw-semibold text-uppercase">In Fulfillment</div>
                <div class="h3 mb-0 mt-1 text-primary fw-bold"><?= $counts['processing'] ?></div>
                <small class="text-muted">Confirmed, packing or ready</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-secondary small fw-semibold text-uppercase">Total Orders</div>
                <div class="h3 mb-0 mt-1 text-success fw-bold"><?= $counts['total'] ?></div>
                <small class="text-muted"><?= $counts['completed'] ?> fulfilled</small>
            </div>
        </div>
    </div>
</div>

<!-- Filter Controls -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="get" action="index.php" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="orders">

            <div class="col-lg-4">
                <label for="order_search" class="form-label small fw-semibold">Search Orders</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input id="order_search" name="search" class="form-control" value="<?= e($searchKeyword) ?>" placeholder="Order #, customer name, phone...">
                </div>
            </div>

            <div class="col-sm-6 col-md-3 col-lg-2">
                <label for="filter_status" class="form-label small fw-semibold">Order Status</label>
                <select name="status" id="filter_status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <?php foreach (['PENDING', 'CONFIRMED', 'PROCESSING', 'READY', 'DISPATCHED', 'COMPLETED', 'CANCELLED'] as $st): ?>
                        <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>>
                            <?= e(order_status_label($st)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-sm-6 col-md-3 col-lg-2">
                <label for="filter_payment" class="form-label small fw-semibold">Payment Status</label>
                <select name="payment" id="filter_payment" class="form-select form-select-sm">
                    <option value="">All Payments</option>
                    <option value="UNPAID" <?= $paymentFilter === 'UNPAID' ? 'selected' : '' ?>>Unpaid</option>
                    <option value="PAID" <?= $paymentFilter === 'PAID' ? 'selected' : '' ?>>Paid</option>
                </select>
            </div>

            <div class="col-sm-6 col-md-3 col-lg-2">
                <label for="filter_rx" class="form-label small fw-semibold">Prescription</label>
                <select name="prescription" id="filter_rx" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="needs_review" <?= $rxFilter === 'needs_review' ? 'selected' : '' ?>>Needs Review</option>
                    <option value="required" <?= $rxFilter === 'required' ? 'selected' : '' ?>>Prescription Required</option>
                    <option value="otc" <?= $rxFilter === 'otc' ? 'selected' : '' ?>>OTC / No Rx</option>
                </select>
            </div>

            <div class="col-sm-6 col-md-3 col-lg-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-grow-1" type="submit">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a class="btn btn-sm btn-light border" title="Reset Filters" href="<?= e(url('orders')) ?>">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <strong>Orders Queue (<?= count($orders) ?>)</strong>
        <span class="text-secondary small">Sorted by newest first</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small text-secondary">
                <tr>
                    <th>Order Reference</th>
                    <th>Customer Details</th>
                    <th>Date Placed</th>
                    <th class="text-end">Total Amount</th>
                    <th>Payment</th>
                    <th>Prescription Status</th>
                    <th>Order Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('order_detail', ['id' => $o['id']])) ?>" class="fw-bold text-success text-decoration-none">
                                <?= e($o['order_number']) ?>
                            </a>
                            <div class="small text-muted"><?= (int)$o['item_count'] ?> item(s)</div>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark"><?= e($o['customer_name']) ?></div>
                            <div class="small text-secondary"><i class="bi bi-telephone me-1"></i><?= e($o['customer_phone']) ?></div>
                        </td>
                        <td class="small text-secondary">
                            <?= e(nice_date($o['created_at'], true)) ?>
                        </td>
                        <td class="text-end fw-bold text-dark">
                            <?= money($o['total_amount']) ?>
                        </td>
                        <td>
                            <span class="badge text-bg-light border text-dark" style="font-size: 11px;">
                                <?= $o['payment_method'] === 'COD' ? 'COD' : 'Store Pickup' ?>
                            </span>
                            <span class="badge text-bg-<?= $o['payment_status'] === 'PAID' ? 'success' : 'secondary' ?>" style="font-size: 10px;">
                                <?= e($o['payment_status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!(int)$o['prescription_required']): ?>
                                <span class="badge text-bg-light border text-muted" style="font-size: 11px;">
                                    OTC / No Rx
                                </span>
                            <?php else: ?>
                                <span class="badge text-bg-<?= e(prescription_status_badge($o['prescription_status'])) ?>" style="font-size: 11px;">
                                    <i class="bi bi-prescription2 me-1"></i><?= e(prescription_status_label($o['prescription_status'])) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge text-bg-<?= e(order_status_badge($o['order_status'])) ?>">
                                <?= e(order_status_label($o['order_status'])) ?>
                            </span>
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="d-inline-flex gap-1 align-items-center">
                                <?php 
                                $isRxPending = ((int)$o['prescription_required'] === 1 && $o['prescription_status'] !== 'VERIFIED');
                                $isActive = in_array($o['order_status'], ['PENDING', 'CONFIRMED', 'PROCESSING', 'READY', 'DISPATCHED'], true);
                                ?>
                                <?php if ($isActive && $isRxPending): ?>
                                    <a href="<?= e(url('order_detail', ['id' => $o['id']])) ?>" class="btn btn-sm btn-warning text-nowrap" title="Prescription verification required">
                                        <i class="bi bi-prescription2 me-1"></i>Review Rx
                                    </a>
                                <?php elseif ($isActive && !$isRxPending): ?>
                                    <form action="actions.php" method="post" class="d-inline m-0">
                                        <input type="hidden" name="action" value="order_update_status">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                                        <input type="hidden" name="new_status" value="COMPLETED">
                                        <input type="hidden" name="remarks" value="Direct fulfillment via orders queue">
                                        <button type="submit" class="btn btn-sm btn-success text-nowrap" onclick="return confirm('Fulfill and complete Order #<?= e($o['order_number']) ?>? Generates sales invoice.');" title="Fast-Track Complete">
                                            <i class="bi bi-check-circle-fill me-1"></i>Complete
                                        </button>
                                    </form>
                                <?php elseif ($o['order_status'] === 'COMPLETED'): ?>
                                    <a href="index.php?page=invoice&order_id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-success text-nowrap" title="Print Sales Invoice">
                                        <i class="bi bi-receipt me-1"></i>Invoice
                                    </a>
                                <?php endif; ?>

                                <a href="<?= e(url('order_detail', ['id' => $o['id']])) ?>" class="btn btn-sm btn-outline-secondary" title="View Order Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-secondary">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                            <h5>No Orders Found</h5>
                            <p class="small mb-0">No customer orders matched your active filter criteria.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
