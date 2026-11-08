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
        <?php if ($currentStatus === 'COMPLETED'): ?>
            <a href="index.php?page=invoice&order_id=<?= (int)$order['id'] ?>" class="btn btn-success btn-sm" title="View official printable tax invoice">
                <i class="bi bi-receipt me-1"></i>Sales Invoice
            </a>
        <?php endif; ?>
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
        <!-- Staff Status Operations Controller -->
        <?php if (is_staff_or_admin()): ?>
            <div class="card shadow-sm mb-4 border-primary">
                <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                    <strong class="d-flex align-items-center">
                        <i class="bi bi-lightning-charge-fill me-2"></i>Order Actions &amp; Fulfillment
                    </strong>
                    <span class="badge text-bg-light small"><?= e(order_status_label($currentStatus)) ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($allowedTransitions)): ?>
                        <div class="alert alert-secondary small mb-0">
                            <i class="bi bi-lock-fill me-1"></i>
                            This order is in a terminal state (<strong><?= e($currentStatus) ?></strong>) and cannot undergo further status transitions.
                        </div>
                    <?php else: ?>
                        <?php 
                        $isBlockedByRx = ($rxRequired && !$rxVerified);
                        ?>

                        <?php if ($isBlockedByRx): ?>
                            <div class="alert alert-warning small py-2 mb-3">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                <strong>Prescription Gate:</strong> Pharmacist must inspect and approve the prescription below before confirming or completing this order.
                            </div>
                        <?php endif; ?>

                        <!-- Quick Single-Click Actions -->
                        <div class="d-grid gap-2 mb-3">
                            <?php if (in_array('COMPLETED', $allowedTransitions, true)): ?>
                                <form action="actions.php" method="post" class="m-0">
                                    <input type="hidden" name="action" value="order_update_status">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                    <input type="hidden" name="new_status" value="COMPLETED">
                                    <input type="hidden" name="remarks" value="Order verified, delivered, and completed via quick fulfillment">
                                    <button type="submit" class="btn btn-success btn-sm w-100 fw-bold py-2" <?= $isBlockedByRx ? 'disabled title="Requires prescription approval"' : '' ?> onclick="return confirm('Fulfill and complete Order #<?= e($order['order_number']) ?>? This will generate the sales invoice and finalize payment.');">
                                        <i class="bi bi-check-circle-fill me-1"></i>Deliver &amp; Complete Order
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array('CONFIRMED', $allowedTransitions, true)): ?>
                                <form action="actions.php" method="post" class="m-0">
                                    <input type="hidden" name="action" value="order_update_status">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                    <input type="hidden" name="new_status" value="CONFIRMED">
                                    <input type="hidden" name="remarks" value="Order confirmed and queued for preparation">
                                    <button type="submit" class="btn btn-outline-primary btn-sm w-100" <?= $isBlockedByRx ? 'disabled title="Requires prescription approval"' : '' ?> onclick="return confirm('Confirm Order #<?= e($order['order_number']) ?>?');">
                                        <i class="bi bi-check2-circle me-1"></i>Confirm &amp; Prepare Order
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array('CANCELLED', $allowedTransitions, true)): ?>
                                <form action="actions.php" method="post" class="m-0">
                                    <input type="hidden" name="action" value="order_update_status">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                    <input type="hidden" name="new_status" value="CANCELLED">
                                    <input type="hidden" name="remarks" value="Cancelled by dispensary staff">
                                    <button type="submit" class="btn btn-outline-danger btn-sm w-100" onclick="return confirm('Cancel Order #<?= e($order['order_number']) ?>? Reserved batch stock will be restored automatically.');">
                                        <i class="bi bi-x-circle me-1"></i>Cancel Order
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <!-- Expandable Custom Status Transition & Notes -->
                        <div class="accordion" id="advStatusAccordion">
                            <div class="accordion-item border-0">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-1 px-2 small bg-light text-secondary rounded" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvStatus">
                                        <i class="bi bi-sliders me-1"></i>Custom Status / Custom Remarks
                                    </button>
                                </h2>
                                <div id="collapseAdvStatus" class="accordion-collapse collapse" data-bs-parent="#advStatusAccordion">
                                    <div class="accordion-body px-0 pb-0 pt-2">
                                        <form action="actions.php" method="post">
                                            <input type="hidden" name="action" value="order_update_status">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

                                            <div class="mb-2">
                                                <label for="st_new" class="form-label small fw-semibold">Target Status</label>
                                                <select name="new_status" id="st_new" class="form-select form-select-sm" required>
                                                    <option value="">Select Status</option>
                                                    <?php foreach ($allowedTransitions as $nextSt): 
                                                        $blocked = ($rxRequired && !$rxVerified && in_array($nextSt, ['CONFIRMED', 'PROCESSING', 'READY', 'DISPATCHED', 'COMPLETED'], true));
                                                    ?>
                                                        <option value="<?= e($nextSt) ?>" <?= $blocked ? 'disabled' : '' ?>>
                                                            <?= e(order_status_label($nextSt)) ?><?= $blocked ? ' (Rx Required)' : '' ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="mb-2">
                                                <label for="st_remarks" class="form-label small fw-semibold">Custom Remarks</label>
                                                <textarea name="remarks" id="st_remarks" class="form-control form-control-sm" rows="2" placeholder="e.g. Special packaging instructions"></textarea>
                                            </div>

                                            <button type="submit" class="btn btn-secondary btn-sm w-100">
                                                Apply Transition
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
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
                    <div class="p-3 bg-light rounded border mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-file-earmark-medical fs-2 text-danger"></i>
                            <div class="flex-grow-1 text-truncate">
                                <strong class="text-dark d-block text-truncate"><?= e($prescription['original_filename']) ?></strong>
                                <span class="text-muted small"><?= round($prescription['file_size'] / 1024, 1) ?> KB &middot; <?= e($prescription['mime_type']) ?></span>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#rxPreviewModal">
                                <i class="bi bi-eye me-1"></i>Inspect Inline
                            </button>
                            <a href="index.php?page=download_prescription&order_id=<?= (int)$order['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Open in raw tab">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        </div>
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

<?php if ($prescription): ?>
<!-- Prescription Inline Inspection Modal -->
<div class="modal fade" id="rxPreviewModal" tabindex="-1" aria-labelledby="rxPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title h6 mb-0" id="rxPreviewModalLabel">
                        <i class="bi bi-file-earmark-medical text-danger me-2"></i>Doctor's Prescription Document
                    </h5>
                    <small class="text-muted"><?= e($prescription['original_filename']) ?> (<?= round($prescription['file_size'] / 1024, 1) ?> KB)</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 text-center bg-light">
                <?php if (str_starts_with((string)$prescription['mime_type'], 'image/')): ?>
                    <img src="index.php?page=download_prescription&order_id=<?= (int)$order['id'] ?>" class="img-fluid rounded shadow-sm border bg-white" alt="Doctor Prescription" style="max-height: 70vh;">
                <?php elseif ($prescription['mime_type'] === 'application/pdf'): ?>
                    <iframe src="index.php?page=download_prescription&order_id=<?= (int)$order['id'] ?>" style="width: 100%; height: 70vh; border: none;" class="rounded border bg-white"></iframe>
                <?php else: ?>
                    <div class="p-4 bg-white rounded border">
                        <i class="bi bi-file-earmark fs-1 text-secondary mb-2 d-block"></i>
                        <p class="mb-3">Preview unavailable for mime type <code><?= e($prescription['mime_type']) ?></code>.</p>
                        <a href="index.php?page=download_prescription&order_id=<?= (int)$order['id'] ?>" target="_blank" class="btn btn-primary btn-sm">
                            <i class="bi bi-download me-1"></i>Download Document
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (is_staff_or_admin() && $order['order_status'] !== 'CANCELLED' && $order['order_status'] !== 'COMPLETED'): ?>
                <div class="modal-footer bg-white d-flex justify-content-between align-items-center">
                    <div class="text-secondary small">
                        Status: <span class="badge text-bg-<?= e(prescription_status_badge($order['prescription_status'])) ?>"><?= e(prescription_status_label($order['prescription_status'])) ?></span>
                    </div>
                    <form action="actions.php" method="post" class="d-flex gap-2 m-0">
                        <input type="hidden" name="action" value="prescription_review">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                        <input type="hidden" name="review_notes" value="Verified and approved via inline inspector">
                        <button type="submit" name="verification_status" value="APPROVED" class="btn btn-success btn-sm" onclick="return confirm('Approve this prescription?');">
                            <i class="bi bi-check-circle me-1"></i>Approve Prescription
                        </button>
                        <button type="submit" name="verification_status" value="REJECTED" class="btn btn-outline-danger btn-sm" onclick="return confirm('Reject this prescription?');">
                            <i class="bi bi-x-circle me-1"></i>Reject
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
