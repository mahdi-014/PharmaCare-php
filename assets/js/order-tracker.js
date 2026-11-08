/**
 * PharmaCare — Real-Time Order Lifecycle Tracker
 * Pure Vanilla JavaScript (Fetch API) — No external frameworks.
 * Features:
 * - Polls api/orders/status.php periodically for non-terminal orders
 * - Instant manual refresh on button click
 * - Dynamic status badge and timeline progression updates
 * - Auto-stops polling when order reaches terminal state (COMPLETED or CANCELLED)
 * - Strict XSS escaping and connection resilience
 */

(function () {
    'use strict';

    const metaEl = document.getElementById('orderTrackerMetadata');
    const refreshBtn = document.getElementById('liveOrderRefreshBtn');
    const statusBadge = document.getElementById('liveOrderStatusBadge');
    const timelineContainer = document.getElementById('liveTimelineContainer');
    const eventCountEl = document.getElementById('liveTimelineEventCount');
    const syncBadge = document.getElementById('liveTimelineSyncBadge');

    if (!metaEl) return;

    const orderId = parseInt(metaEl.getAttribute('data-order-id'), 10);
    let isTerminal = metaEl.getAttribute('data-terminal') === '1';
    let pollInterval = null;
    let knownEventCount = parseInt(eventCountEl ? eventCountEl.textContent : '0', 10) || 0;

    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function updateSyncBadge(message, isSpinning = false) {
        if (!syncBadge) return;
        const icon = isSpinning
            ? '<span class="spinner-border spinner-border-sm me-1"></span>'
            : '<i class="bi bi-clock-history me-1"></i>';
        syncBadge.innerHTML = icon + escapeHtml(message);
    }

    function renderTimeline(timelineEvents) {
        if (!timelineContainer || !Array.isArray(timelineEvents)) return;

        let html = '';
        timelineEvents.forEach(h => {
            const roleBadge = h.actor_role ? `(${escapeHtml(h.actor_role)})` : '';
            const remarksHtml = h.remarks
                ? `<div class="p-2 mt-2 bg-light rounded text-secondary small border">${escapeHtml(h.remarks)}</div>`
                : '';

            const title = h.previous_status
                ? `Transitioned from ${escapeHtml(h.previous_status)} to ${escapeHtml(h.new_status)}`
                : `Order Initialized (${escapeHtml(h.new_status)})`;

            html += `
                <div class="d-flex mb-3 pb-3 border-bottom last-border-0">
                    <div class="me-3">
                        <span class="badge text-bg-${escapeHtml(h.new_status_badge)} p-2" style="font-size: 13px;">
                            <i class="bi bi-clock-history"></i>
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong class="text-dark">${title}</strong>
                            <small class="text-secondary">${escapeHtml(h.formatted_time)}</small>
                        </div>
                        <div class="small text-muted mt-1">
                            Action by: <strong>${escapeHtml(h.actor_name)}</strong> ${roleBadge}
                        </div>
                        ${remarksHtml}
                    </div>
                </div>
            `;
        });

        timelineContainer.innerHTML = html;
        if (eventCountEl) {
            eventCountEl.textContent = timelineEvents.length;
        }
        knownEventCount = timelineEvents.length;
    }

    function checkOrderStatus(manual = false) {
        if (!orderId) return;

        if (manual && refreshBtn) {
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Checking...';
        }
        updateSyncBadge('Checking status...', true);

        fetch(`api/orders/status.php?id=${orderId}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(payload => {
            if (manual && refreshBtn) {
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Check Status';
            }

            if (!payload || !payload.success || !payload.data) {
                updateSyncBadge('Sync failed');
                return;
            }

            const data = payload.data;
            const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });

            // Update status badge
            if (statusBadge) {
                statusBadge.className = `badge text-bg-${data.order_status_badge}`;
                statusBadge.textContent = data.order_status_label;
            }

            // Update timeline if new events arrived
            if (Array.isArray(data.timeline) && data.timeline.length !== knownEventCount) {
                renderTimeline(data.timeline);
            }

            // Check terminal state
            if (data.is_terminal) {
                isTerminal = true;
                if (pollInterval) {
                    clearInterval(pollInterval);
                    pollInterval = null;
                }
                updateSyncBadge(`Order ${data.order_status_label.toLowerCase()} &middot; Checked at ${now}`);
            } else {
                updateSyncBadge(`Synced at ${now}`);
            }
        })
        .catch(err => {
            console.error('PharmaCare order status polling error:', err);
            if (manual && refreshBtn) {
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Check Status';
            }
            updateSyncBadge('Connection retry in next cycle');
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Button listener
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                checkOrderStatus(true);
            });
        }

        // Automatic polling every 10 seconds for active orders
        if (!isTerminal) {
            updateSyncBadge('Realtime Tracker Active (10s sync)');
            pollInterval = setInterval(() => {
                // Only poll when browser document tab is visible
                if (document.visibilityState === 'visible') {
                    checkOrderStatus(false);
                }
            }, 10000);
        } else {
            updateSyncBadge('Terminal State &middot; Sync Inactive');
        }
    });

})();
