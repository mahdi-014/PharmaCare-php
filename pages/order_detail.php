<?php
/**
 * PharmaCare — Order Detail & Status Transition View
 * Provides complete operational breakdown of a customer order:
 * - Line items and price snapshots
 * - Delivery address and contact coordinates
 * - Doctor's prescription inspection, authenticated download, and pharmacist review
 * - Status transition engine enforcing business state machine rules
 * - Audit timeline tracking all historical lifecycle transitions
 * - Strict IDOR protection for customer access
 */

declare(strict_types=1);

require_login();

$orderId = positive_int($_GET['id'] ?? 0, 'Order ID');
$order = \App\Models\Order::findById($orderId);

if (!$order) {
    echo '<div class="alert alert-danger shadow-sm">Order not found.</div>';
    return;
}

// Strict IDOR Protection: Customers may only view their own orders
if (is_customer() && (int)$order['customer_id'] !== (int)user()['id']) {
    http_response_code(403);
    include __DIR__ . '/forbidden.php';
    exit;
}

// Fetch ordered items
$items = \App\Models\Order::getItems($orderId);

// Fetch prescription record if present
$prescription = \App\Models\Order::getPrescription($orderId);

// Fetch audit status history
$history = \App\Models\Order::getHistory($orderId);

$currentStatus = (string)$order['order_status'];
$allowedTransitions = get_allowed_order_transitions($currentStatus);
$rxRequired = (int)$order['prescription_required'] === 1;
$rxVerified = $order['prescription_status'] === 'VERIFIED';
?>

<div class="page-intro mb-4 d-flex justify-content-between align-items-center" id="orderTrackerMetadata" data-order-id="<?= (int)$order['id'] ?>" data-terminal="<?= in_array($currentStatus, ['COMPLETED', 'CANCELLED'], true) ? '1' : '0' ?>" data-status="<?= e($currentStatus) ?>">
    <div>
        <h2>Order <?= e($order['order_number']) ?></h2>
        <p>Placed on <?= e(nice_date($order['created_at'], true)) ?> &middot; Status: <span id="liveOrderStatusBadge" class="badge text-bg-<?= e(order_status_badge($currentStatus)) ?>"><?= e(order_status_label($currentStatus)) ?></span></p>
    </div>
    <div class="d-flex gap-2">
        <button id="liveOrderRefreshBtn" class="btn btn-outline-primary btn-sm" type="button" title="Check for live status updates">
            <i class="bi bi-arrow-clockwise me-1"></i>Check Status
        </button>
        <?php if (is_staff_or_admin()): ?>
            <a href="<?= e(url('orders')) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to Orders Queue
            </a>
        <?php else: ?>
            <a href="<?= e(url('my_orders')) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to My Orders
            </a>
        <?php endif; ?>
        <button class="btn btn-outline-dark btn-sm" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Order Items, Delivery, & History -->
    <div class="col-lg-8">
        <!-- Order Items Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <strong>Ordered Medicines (<?= count($items) ?> items)</strong>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-secondary">
                        <tr>
                            <th>Medicine</th>
                            <th class="text-center">Dosage</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($item['medicine_name']) ?></div>
                                    <div class="small text-secondary">
                                        <span class="badge text-bg-light border"><?= e($item['category_name']) ?></span>
                                        &middot; Batch: <code><?= e($item['batch_number']) ?></code>
                                    </div>
                                </td>
                                <td class="text-center small text-secondary">
                                    <?= e(ucfirst(strtolower($item['medicine_type']))) ?>
                                </td>
                                <td class="text-end">
                                    <?= money($item['unit_price']) ?>
                                </td>
                                <td class="text-center fw-semibold">
                                    <?= (int)$item['quantity'] ?>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    <?= money($item['line_total']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="4" class="text-end text-secondary">Subtotal</td>
                            <td class="text-end fw-semibold"><?= money($order['subtotal']) ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end text-secondary">Delivery Fee</td>
                            <td class="text-end fw-semibold"><?= money($order['shipping_fee']) ?></td>
                        </tr>
                        <tr class="fw-bold fs-6">
                            <td colspan="4" class="text-end">Grand Total</td>
                            <td class="text-end text-success"><?= money($order['total_amount']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Audit Trail / Order Status History Timeline -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <strong>Order Lifecycle Audit Trail (<span id="liveTimelineEventCount"><?= count($history) ?></span> events)</strong>
                <span id="liveTimelineSyncBadge" class="badge text-bg-light border text-muted small"><i class="bi bi-clock me-1"></i>Realtime Tracker Active</span>
            </div>
            <div class="card-body">
                <div id="liveTimelineContainer" class="timeline">
                    <?php foreach ($history as $h): ?>
                        <div class="d-flex mb-3 pb-3 border-bottom last-border-0">
                            <div class="me-3">
                                <span class="badge text-bg-<?= e(order_status_badge((string)$h['new_status'])) ?> p-2" style="font-size: 13px;">
                                    <i class="bi bi-clock-history"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="text-dark">
                                        <?= e($h['previous_status'] ? "Transitioned from {$h['previous_status']} to {$h['new_status']}" : "Order Initialized ({$h['new_status']})") ?>
                                    </strong>
                                    <small class="text-secondary"><?= e(nice_date($h['created_at'], true)) ?></small>
                                </div>
                                <div class="small text-muted mt-1">
                                    Action by: <strong><?= e($h['changed_by_name'] ?? 'System') ?></strong> 
                                    <?= !empty($h['changed_by_role']) ? '(' . e($h['changed_by_role']) . ')' : '' ?>
                                </div>
                                <?php if (!empty($h['remarks'])): ?>
                                    <div class="p-2 mt-2 bg-light rounded text-secondary small border">
                                        <?= e($h['remarks']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Status Operations, Prescription Inspection, & Customer Info -->
    <div class="col-lg-4">
        <!-- Staff Status Transition Controller -->
        <?php if (is_staff_or_admin()): ?>
            <div class="card shadow-sm mb-4 border-primary">
                <div class="card-header bg-primary text-white py-3">
                    <strong class="d-flex align-items-center">
                        <i class="bi bi-arrow-repeat me-2"></i>Update Order Lifecycle
                    </strong>
                </div>
                <div class="card-body">
                    <?php if (empty($allowedTransitions)): ?>
                        <div class="alert alert-secondary small mb-0">
                            <i class="bi bi-lock-fill me-1"></i>
                            This order is in a terminal state (<strong><?= e($currentStatus) ?></strong>) and cannot undergo further status transitions.
                        </div>
                    <?php else: ?>
                        <form action="actions.php" method="post">
                            <input type="hidden" name="action" value="order_update_status">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

                            <div class="mb-3">
                                <label for="st_new" class="form-label small fw-semibold">Next Stage *</label>
                                <select name="new_status" id="st_new" class="form-select form-select-sm" required>
                                    <option value="">Select Next Status</option>
                                    <?php foreach ($allowedTransitions as $nextSt):
                                        $isBlockedByRx = ($rxRequired && !$rxVerified && in_array($nextSt, ['CONFIRMED', 'PROCESSING', 'READY', 'DISPATCHED', 'COMPLETED'], true));
                                    ?>
                                        <option value="<?= e($nextSt) ?>" <?= $isBlockedByRx ? 'disabled' : '' ?>>
                                            <?= e(order_status_label($nextSt)) ?>
                                            <?= $isBlockedByRx ? ' (Requires Prescription Approval)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($rxRequired && !$rxVerified): ?>
                                <div class="alert alert-warning small py-2 mb-3">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    <strong>Prescription Gate:</strong> You must approve the uploaded prescription below before confirming this order.
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="st_remarks" class="form-label small fw-semibold">Transition Remarks</label>
                                <textarea name="remarks" id="st_remarks" class="form-control form-control-sm" rows="2" placeholder="e.g. Items verified and packed in cold storage box"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm w-100" onclick="return confirm('Advance order to the selected status?');">
                                <i class="bi bi-check2-circle me-1"></i>Apply Status Transition
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif (is_customer() && $currentStatus === 'PENDING'): ?>
            <!-- Customer Self-Cancellation Card -->
            <div class="card shadow-sm mb-4 border-danger">
                <div class="card-header bg-danger-subtle text-danger py-3">
                    <strong>Cancel Order</strong>
                </div>
                <div class="card-body">
                    <p class="small text-secondary mb-3">
                        Your order is currently pending review. If you made an error or no longer need these medicines, you may cancel it now. Any reserved inventory will be immediately restored.
                    </p>
                    <form action="actions.php" method="post" onsubmit="return confirm('Are you sure you want to cancel this order? This action cannot be undone.');">
                        <input type="hidden" name="action" value="order_cancel_customer">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

                        <div class="mb-3">
                            <label for="cancel_reason" class="form-label small fw-semibold">Reason for Cancellation (Optional)</label>
                            <input name="remarks" id="cancel_reason" class="form-control form-control-sm" placeholder="e.g., Ordered by mistake">
                        </div>

                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i class="bi bi-x-circle me-1"></i>Cancel My Order
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Prescription Inspection & Review Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <strong>Doctor's Prescription</strong>
                <?php if ($rxRequired): ?>
                    <span class="badge text-bg-<?= e(prescription_status_badge($order['prescription_status'])) ?>">
                        <?= e(prescription_status_label($order['prescription_status'])) ?>
                    </span>
                <?php else: ?>
                    <span class="badge text-bg-light border text-muted">OTC / Not Required</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($prescription): ?>
                    <div class="d-flex align-items-center gap-3 p-3 bg-light rounded border mb-3">
                        <i class="bi bi-file-earmark-medical fs-2 text-danger"></i>
                        <div class="flex-grow-1 text-truncate">
                            <strong class="text-dark d-block text-truncate"><?= e($prescription['original_filename']) ?></strong>
                            <span class="text-muted small"><?= round($prescription['file_size'] / 1024, 1) ?> KB &middot; <?= e($prescription['mime_type']) ?></span>
                        </div>
                        <a href="index.php?page=download_prescription&order_id=<?= (int)$order['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="View / Download Secure File">
                            <i class="bi bi-box-arrow-up-right me-1"></i>View
                        </a>
                    </div>

                    <?php if (!empty($prescription['review_notes'])): ?>
                        <div class="small p-2 mb-3 rounded bg-light border">
                            <span class="text-secondary d-block">Pharmacist Notes:</span>
                            <span class="text-dark"><?= e($prescription['review_notes']) ?></span>
                            <?php if (!empty($prescription['reviewer_name'])): ?>
                                <small class="text-muted d-block mt-1">&mdash; <?= e($prescription['reviewer_name']) ?> on <?= e(nice_date($prescription['reviewed_at'], true)) ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Pharmacist Verification Action Form (Staff/Admin Only) -->
                    <?php if (is_staff_or_admin() && $order['order_status'] !== 'CANCELLED' && $order['order_status'] !== 'COMPLETED'): ?>
                        <form action="actions.php" method="post" class="border-top pt-3">
                            <input type="hidden" name="action" value="prescription_review">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

                            <div class="mb-2">
                                <label for="rx_notes" class="form-label small fw-semibold">Pharmacist Review Notes</label>
                                <textarea name="review_notes" id="rx_notes" class="form-control form-control-sm" rows="2" placeholder="e.g. Valid BMDC prescription verified with Doctor"><?= e($prescription['review_notes'] ?? '') ?></textarea>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" name="verification_status" value="APPROVED" class="btn btn-success btn-sm flex-grow-1" onclick="return confirm('Approve this doctor prescription?');">
                                    <i class="bi bi-check-circle me-1"></i>Approve Rx
                                </button>
                                <button type="submit" name="verification_status" value="REJECTED" class="btn btn-danger btn-sm flex-grow-1" onclick="return confirm('Reject this prescription? The order cannot proceed without a valid prescription.');">
                                    <i class="bi bi-x-circle me-1"></i>Reject Rx
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>

                <?php else: ?>
                    <p class="small text-secondary mb-0">
                        No prescription file was attached to this order.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Customer & Delivery Coordinates Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <strong>Delivery &amp; Customer Information</strong>
            </div>
            <div class="card-body small">
                <div class="mb-3">
                    <span class="text-secondary d-block">Recipient Name</span>
                    <strong class="text-dark fs-6"><?= e($order['customer_name']) ?></strong>
                </div>
                <div class="mb-3">
                    <span class="text-secondary d-block">Contact Phone</span>
                    <span class="text-dark fw-semibold"><i class="bi bi-telephone me-1"></i><?= e($order['customer_phone']) ?></span>
                </div>
                <div class="mb-3">
                    <span class="text-secondary d-block">Shipping Address</span>
                    <span class="text-dark"><?= nl2br(e($order['delivery_address'])) ?></span>
                </div>
                <div class="mb-3">
                    <span class="text-secondary d-block">Payment Method &amp; Status</span>
                    <span class="badge text-bg-light border text-dark">
                        <?= $order['payment_method'] === 'COD' ? 'Cash on Delivery (COD)' : 'Store Pickup' ?>
                    </span>
                    <span class="badge text-bg-<?= $order['payment_status'] === 'PAID' ? 'success' : 'secondary' ?>">
                        <?= e($order['payment_status']) ?>
                    </span>
                </div>
                <?php if (!empty($order['notes'])): ?>
                    <div class="mb-0">
                        <span class="text-secondary d-block">Customer Notes</span>
                        <span class="text-dark fst-italic"><?= nl2br(e($order['notes'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
