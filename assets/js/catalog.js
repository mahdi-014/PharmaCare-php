/**
 * PharmaCare — Catalog Live Search & Asynchronous Cart Controller
 * Pure Vanilla JavaScript (Fetch API) — No external frameworks.
 * Features:
 * - 300ms debounced input search & category filter fetch
 * - Asynchronous JSON add-to-cart with X-CSRF-Token validation
 * - Dynamic navbar cart counter & catalog summary updates
 * - Bootstrap 5 interactive toast notifications
 * - Full XSS sanitization and graceful fallback support
 */

(function () {
    'use strict';

    // DOM Elements
    const searchForm = document.getElementById('catalogSearchForm');
    const keywordInput = document.getElementById('catalog_keyword');
    const categorySelect = document.getElementById('catalog_cat');
    const gridContainer = document.getElementById('catalogMedicineGrid');
    const resultCount = document.getElementById('catalogResultCount');
    const viewCartBtn = document.getElementById('catalogViewCartBtn');
    const viewCartCount = document.getElementById('catalogCartBtnCount');
    const topbarBadge = document.getElementById('topbarCartBadge');
    const toastEl = document.getElementById('catalogToast');
    const toastMsg = document.getElementById('catalogToastMessage');
    const toastIcon = document.getElementById('catalogToastIcon');

    let toastInstance = null;
    if (toastEl && window.bootstrap && window.bootstrap.Toast) {
        toastInstance = new window.bootstrap.Toast(toastEl, { delay: 3500 });
    }

    // Helper: Retrieve CSRF Token from meta tag
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // Helper: Escape HTML strings to prevent XSS injection
    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Helper: Show floating toast notification
    function showToast(message, isSuccess = true) {
        if (!toastEl) return;
        if (toastMsg) toastMsg.textContent = message;

        toastEl.classList.remove('text-bg-success', 'text-bg-danger', 'text-bg-warning');
        if (isSuccess) {
            toastEl.classList.add('text-bg-success');
            if (toastIcon) toastIcon.className = 'bi bi-check-circle-fill fs-5';
        } else {
            toastEl.classList.add('text-bg-danger');
            if (toastIcon) toastIcon.className = 'bi bi-exclamation-triangle-fill fs-5';
        }

        if (toastInstance) {
            toastInstance.show();
        } else if (window.bootstrap && window.bootstrap.Toast) {
            toastInstance = new window.bootstrap.Toast(toastEl, { delay: 3500 });
            toastInstance.show();
        }
    }

    // Helper: Update all Cart badges throughout the DOM
    function updateCartBadges(cartCount) {
        const count = parseInt(cartCount, 10) || 0;

        // Topbar badge
        if (topbarBadge) {
            topbarBadge.textContent = count;
            if (count > 0) {
                topbarBadge.classList.remove('d-none');
            } else {
                topbarBadge.classList.add('d-none');
            }
        }

        // Catalog View Cart button
        if (viewCartBtn) {
            if (count > 0) {
                viewCartBtn.classList.remove('d-none');
                if (viewCartCount) viewCartCount.textContent = count;
            } else {
                viewCartBtn.classList.add('d-none');
            }
        }
    }

    // -------------------------------------------------------------
    // Live Search & Filter via Fetch
    // -------------------------------------------------------------
    let debounceTimer = null;

    function performSearch() {
        const q = keywordInput ? keywordInput.value.trim() : '';
        const catId = categorySelect ? categorySelect.value : '';

        const params = new URLSearchParams();
        if (q) params.set('q', q);
        if (catId) params.set('category_id', catId);

        if (resultCount) {
            resultCount.innerHTML = '<span class="spinner-border spinner-border-sm text-secondary me-1"></span> Searching inventory...';
        }

        fetch('api/medicines/search.php?' + params.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(payload => {
            if (!payload || !payload.success) {
                throw new Error(payload ? payload.message : 'Unknown error');
            }
            renderMedicines(payload.data);
        })
        .catch(err => {
            console.error('PharmaCare search error:', err);
            if (resultCount) {
                resultCount.textContent = 'Error loading medicines. Please refresh.';
            }
        });
    }

    // Render medicine cards returned by search API
    function renderMedicines(data) {
        const medicines = data.medicines || [];
        const count = data.count || 0;
        const isLoggedIn = data.is_logged_in;
        const isCustomer = data.is_customer;
        const csrfToken = getCsrfToken();

        if (resultCount) {
            resultCount.textContent = 'SHOWING ' + count + ' AVAILABLE MEDICINE(S)';
        }

        if (medicines.length === 0) {
            gridContainer.innerHTML = `
                <div class="col-12" id="catalogEmptyState">
                    <div class="card">
                        <div class="empty-state py-5 text-center">
                            <i class="bi bi-search fs-1 text-secondary mb-2"></i>
                            <h4>No Medicines Found</h4>
                            <p class="text-secondary mb-3">No available medicines matched your search criteria.</p>
                            <button type="button" class="btn btn-outline-primary" id="catalogResetBtn">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>View All Medicines
                            </button>
                        </div>
                    </div>
                </div>
            `;
            const resetBtn = document.getElementById('catalogResetBtn');
            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    if (keywordInput) keywordInput.value = '';
                    if (categorySelect) categorySelect.value = '';
                    performSearch();
                });
            }
            return;
        }

        let html = '';
        medicines.forEach(m => {
            const inCart = parseInt(m.in_cart_quantity, 10) || 0;
            const maxStock = parseInt(m.quantity, 10) || 0;
            const isFull = inCart >= maxStock;

            let actionHtml = '';
            if (!isLoggedIn) {
                actionHtml = `
                    <a href="index.php?page=login" class="btn btn-sm btn-outline-success w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Sign In to Order
                    </a>
                `;
            } else if (isCustomer) {
                actionHtml = `
                    <form action="actions.php" method="post" class="d-flex align-items-center gap-2 ajax-cart-form" data-medicine-id="${m.id}">
                        <input type="hidden" name="action" value="cart_add">
                        <input type="hidden" name="_csrf" value="${escapeHtml(csrfToken)}">
                        <input type="hidden" name="medicine_id" value="${m.id}">
                        <input type="hidden" name="redirect_to" value="catalog">

                        <div class="input-group input-group-sm" style="max-width: 105px;">
                            <span class="input-group-text bg-light text-secondary">Qty</span>
                            <input type="number" name="quantity" class="form-control text-center cart-qty-input" min="1" max="${maxStock}" value="1" ${isFull ? 'disabled' : ''}>
                        </div>

                        <button type="submit" class="btn btn-sm btn-success flex-grow-1 cart-submit-btn" ${isFull ? 'disabled' : ''}>
                            <i class="bi bi-cart-plus me-1"></i><span class="btn-label">${inCart > 0 ? 'Add More' : 'Add to Cart'}</span>
                        </button>
                    </form>
                    <div class="mt-2 text-center in-cart-status-wrap ${inCart > 0 ? '' : 'd-none'}">
                        <span class="badge text-bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 11px;">
                            <i class="bi bi-cart-check me-1"></i><span class="in-cart-number">${inCart}</span> in <a href="index.php?page=cart" class="text-primary text-decoration-underline">cart</a> <span class="max-stock-indicator">${isFull ? '(Max available)' : ''}</span>
                        </span>
                    </div>
                `;
            } else {
                actionHtml = `
                    <div class="text-center py-1">
                        <span class="badge text-bg-secondary-subtle text-secondary small">
                            <i class="bi bi-shield-lock me-1"></i>Staff Mode &middot; <a href="index.php?page=sale_form" class="text-secondary text-decoration-underline">POS New Sale</a>
                        </span>
                    </div>
                `;
            }

            const rxBadge = m.prescription_required
                ? `<span class="badge text-bg-danger-subtle text-danger border border-danger-subtle mb-1 d-block text-center" style="font-size: 10px;"><i class="bi bi-prescription2 me-1"></i>Prescription Required</span>`
                : `<span class="badge text-bg-success-subtle text-success border border-success-subtle mb-1 d-block text-center" style="font-size: 10px;"><i class="bi bi-check-circle me-1"></i>OTC / No Rx</span>`;

            html += `
                <div class="col-md-6 col-xl-4 medicine-card-col" data-medicine-id="${m.id}">
                    <div class="card h-100 shadow-sm border">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge text-bg-light border text-uppercase" style="font-size: 11px;">
                                    <i class="bi bi-tag me-1"></i>${escapeHtml(m.category_name)}
                                </span>
                                <span class="badge text-bg-${escapeHtml(m.status_badge)}">
                                    ${escapeHtml(m.status_label)}
                                </span>
                            </div>

                            <h5 class="card-title text-success mb-1">${escapeHtml(m.name)}</h5>
                            <div class="text-secondary small mb-2">
                                <strong>Generic:</strong> ${escapeHtml(m.generic_name)}
                                ${m.brand_name ? `&middot; <span class="text-dark">${escapeHtml(m.brand_name)}</span>` : ''}
                            </div>

                            <div class="small text-muted mb-3">
                                <div><i class="bi bi-box-seam me-1"></i><strong>Dosage:</strong> ${escapeHtml(m.dosage_form)}</div>
                                <div><i class="bi bi-upc-scan me-1"></i><strong>Batch:</strong> <code>${escapeHtml(m.batch_number)}</code></div>
                                <div><i class="bi bi-building me-1"></i><strong>Manufacturer:</strong> ${escapeHtml(m.manufacturer)}</div>
                                <div><i class="bi bi-calendar-check me-1"></i><strong>Expiry:</strong> ${escapeHtml(m.formatted_expiry)}</div>
                            </div>

                            <div class="mt-auto pt-3 border-top">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <span class="text-secondary small d-block">Unit Price</span>
                                        <div class="h5 mb-0 text-dark fw-bold">${escapeHtml(m.formatted_price)}</div>
                                    </div>
                                    <div class="text-end">
                                        ${rxBadge}
                                        <span class="badge text-bg-light border text-secondary" style="font-size: 11px;">
                                            In Stock: ${maxStock} units
                                        </span>
                                    </div>
                                </div>

                                ${actionHtml}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        gridContainer.innerHTML = html;
        bindCartForms();
    }

    // -------------------------------------------------------------
    // Asynchronous Add-to-Cart Interception
    // -------------------------------------------------------------
    function handleCartSubmit(e) {
        e.preventDefault();
        const form = e.currentTarget;
        const medicineId = parseInt(form.getAttribute('data-medicine-id') || form.querySelector('[name="medicine_id"]').value, 10);
        const qtyInput = form.querySelector('[name="quantity"]');
        const submitBtn = form.querySelector('.cart-submit-btn');
        const btnLabel = form.querySelector('.btn-label');
        const quantity = qtyInput ? parseInt(qtyInput.value, 10) : 1;

        if (isNaN(medicineId) || medicineId <= 0 || isNaN(quantity) || quantity <= 0) {
            showToast('Please enter a valid quantity.', false);
            return;
        }

        // Lock button during asynchronous fetch
        const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Adding...';
        }

        const csrfToken = getCsrfToken();

        fetch('api/cart/add.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                medicine_id: medicineId,
                quantity: quantity,
                _csrf: csrfToken
            })
        })
        .then(response => {
            return response.json().then(data => ({
                status: response.status,
                ok: response.ok,
                data: data
            }));
        })
        .then(res => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }

            if (!res.ok || !res.data || !res.data.success) {
                const msg = res.data && res.data.message ? res.data.message : 'Failed to add item to cart.';
                showToast(msg, false);
                return;
            }

            // Success: update UI
            const item = res.data.data.item;
            const cartCount = res.data.data.cart_count;

            showToast(res.data.message || 'Added to cart successfully!', true);
            updateCartBadges(cartCount);

            // Update card in-cart indicator
            const cardCol = form.closest('.medicine-card-col');
            if (cardCol) {
                const inCartWrap = cardCol.querySelector('.in-cart-status-wrap');
                const inCartNum = cardCol.querySelector('.in-cart-number');
                const maxNote = cardCol.querySelector('.max-stock-indicator');
                const btnText = cardCol.querySelector('.btn-label');

                if (inCartWrap) inCartWrap.classList.remove('d-none');
                if (inCartNum) inCartNum.textContent = item.quantity;
                if (btnText) btnText.textContent = 'Add More';

                if (item.quantity >= item.max_stock) {
                    if (maxNote) maxNote.textContent = '(Max available)';
                    if (qtyInput) qtyInput.disabled = true;
                    if (submitBtn) submitBtn.disabled = true;
                } else {
                    if (maxNote) maxNote.textContent = '';
                }
            }
        })
        .catch(err => {
            console.error('PharmaCare cart add error:', err);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
            showToast('Network error while adding to cart. Please try again.', false);
        });
    }

    function bindCartForms() {
        const forms = document.querySelectorAll('.ajax-cart-form');
        forms.forEach(f => {
            // Remove previous listener by cloning if needed, or simply assign onsubmit
            f.onsubmit = handleCartSubmit;
        });
    }

    // -------------------------------------------------------------
    // Initialization & Event Listeners
    // -------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', () => {
        // Debounced search input
        if (keywordInput) {
            keywordInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(performSearch, 300);
            });
        }

        // Category dropdown instant filter
        if (categorySelect) {
            categorySelect.addEventListener('change', () => {
                performSearch();
            });
        }

        // Prevent standard form submission if JS is active
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                performSearch();
            });
        }

        // Bind existing forms rendered on initial server page load
        bindCartForms();
    });

})();
