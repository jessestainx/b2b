define([
    'Magento_Customer/js/customer-data'
], function (customerData) {
    'use strict';

    let PDP_ICON_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">'
        + '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>'
        + '<polyline points="16 17 21 12 16 7"></polyline>'
        + '<line x1="21" y1="12" x2="9" y2="12"></line>'
        + '</svg>';

    let PENDING_ICON_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">'
        + '<circle cx="12" cy="12" r="10"></circle>'
        + '<polyline points="12 6 12 12 16 14"></polyline>'
        + '</svg>';

    function isElementVisible(el)
    {
        return !!(el && (el.offsetWidth || el.offsetHeight || el.getClientRects().length));
    }

    function createLoginButton(options)
    {
        let btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'b2b-login-to-buy-btn' + (options && options.variantClass ? (' ' + options.variantClass) : '');
        if (options && options.html) {
            btn.innerHTML = options.html; // intentional — contains trusted SVG icon markup
        } else {
            btn.textContent = (options && options.text) ? options.text : 'Entrar para Comprar';
        }
        if (options && options.disabled) {
            btn.disabled = true;
            btn.classList.add('b2b--disabled');
        }
        return btn;
    }

    /**
     * Check if customer is actually logged in using customer-data sections.
     * This is the authoritative source of truth, not the server-side mode from cached HTML.
     */
    function isCustomerDataLoggedIn(customer)
    {
        if (!customer || typeof customer !== 'object') {
            return false;
        }

        return !!(
            customer.firstname
            || customer.fullname
            || customer.email
            || customer.id
            || customer.entity_id
        );
    }

    function getCustomerDataPayload()
    {
        try {
            return customerData.get('customer')();
        } catch (e) {
            return {};
        }
    }

    function isCustomerLoggedIn()
    {
        try {
            return isCustomerDataLoggedIn(getCustomerDataPayload());
        } catch (e) {
            return false;
        }
    }

    /**
     * Restore original add-to-cart buttons that were hidden by this script.
     * Called when we detect the customer is actually logged in.
     */
    function restoreOriginalButtons()
    {
        // Remove body classes
        document.body.classList.remove('b2b-guest-mode', 'b2b-pending-mode', 'b2b-restricted-mode');

        document.querySelectorAll('.b2b-login-to-buy-mode').forEach(function (container) {
            container.classList.remove('b2b-login-to-buy-mode');
        });

        // Remove all injected B2B buttons
        document.querySelectorAll('[data-b2b-injected]').forEach(function (btn) {
            btn.parentNode.removeChild(btn);
        });

        // Restore all hidden original buttons
        document.querySelectorAll('[data-b2b-original-hidden]').forEach(function (btn) {
            btn.style.display = '';
            btn.removeAttribute('data-b2b-original-hidden');
        });

        // Hide pending banner
        let pendingBanner = document.getElementById('b2b-pending-banner');
        if (pendingBanner) {
            pendingBanner.hidden = true;
        }

        // Hide login modal
        let overlay = document.getElementById('b2b-login-modal');
        if (overlay) {
            overlay.classList.remove('active');
            overlay.setAttribute('aria-hidden', 'true');
            overlay.setAttribute('inert', '');
            overlay.hidden = true;
        }
    }

    function init(config)
    {
        // Skip on pages with no product add-to-cart buttons (homepage, checkout, etc.)
        if (!document.querySelector('.product-item-actions, .product-add-form, .product-info-cart')) {
            return;
        }

        if (!config) {
            return;
        }

        // Determine mode from server: 'guest', 'pending' or 'approved_restricted'
        let serverMode = config.mode || 'guest';
        let activeMode = serverMode; // May be overridden by customer-data
        let isRestricted = true; // Assume restricted until customer-data confirms otherwise
        let bodyClass = (serverMode === 'guest') ? 'b2b-guest-mode' : 'b2b-pending-mode';

        let overlay = document.getElementById('b2b-login-modal');
        let pendingBanner = document.getElementById('b2b-pending-banner');
        let dialog = overlay ? overlay.querySelector('.b2b-login-modal') : null;
        let closeBtn = overlay ? overlay.querySelector('[data-b2b-login-close]') : null;
        let lastActiveElement = null;
        let lastTriggerButton = null;
        let previousBodyOverflow = null;
        let observerInstance = null;
        let priceSyncStarted = false;

        function hasHiddenPriceMarkers()
        {
            return !!document.querySelector(
                '.b2b-login-to-see-price, [data-awa-gate-state="guest"], .product .price-box .price-label a[href*="login"]'
            );
        }

        function escapeHtml(value)
        {
            return String(value || '').replace(/[&<>"']/g, function (char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[char];
            });
        }

        function syncPendingPriceMarkers(root)
        {
            let scope = root && root.querySelectorAll ? root : document;
            let label = (config && config.pendingPriceText) ? config.pendingPriceText : 'Aguardando aprovação';
            let note = (config && config.pendingPriceNote) ? config.pendingPriceNote : 'Preços liberados após análise';

            if (activeMode !== 'pending') {
                return;
            }

            scope.querySelectorAll('.b2b-login-to-see-price').forEach(function (priceMarker) {
                priceMarker.classList.add('b2b-price-pending');
                priceMarker.setAttribute('data-b2b-pending-price', '1');
                priceMarker.setAttribute('aria-label', label + '. ' + note);
                priceMarker.innerHTML = '<span class="price-label">' + escapeHtml(label) + '</span>'
                    + '<span class="price-note">' + escapeHtml(note) + '</span>';
            });
        }

        function getProductIdFromNode(node)
        {
            let root;
            let fromDataset;
            let productInput;

            if (!node) {
                return null;
            }

            root = node.closest('[data-product-id], .item-product, .product-item, li, .product-info-main, .product-add-form') || node.parentElement;
            fromDataset = root && root.getAttribute ? parseInt(root.getAttribute('data-product-id'), 10) : 0;
            if (fromDataset) {
                return String(fromDataset);
            }

            productInput = root ? root.querySelector('input[name="product"]') : null;
            if (!productInput && document.getElementById('product_addtocart_form')) {
                productInput = document.querySelector('#product_addtocart_form input[name="product"]');
            }

            return productInput && productInput.value ? String(parseInt(productInput.value, 10)) : null;
        }

        function collectPriceTargets()
        {
            let targetsByProductId = {};

            document.querySelectorAll('.b2b-login-to-see-price').forEach(function (priceMarker) {
                let productId = getProductIdFromNode(priceMarker);

                if (!productId) {
                    return;
                }

                if (!targetsByProductId[productId]) {
                    targetsByProductId[productId] = [];
                }

                targetsByProductId[productId].push(priceMarker);
            });

            return targetsByProductId;
        }

        function replacePriceTarget(priceMarker, html)
        {
            if (!priceMarker || !html) {
                return;
            }

            if (priceMarker.parentNode) {
                priceMarker.outerHTML = html;
            }
        }

        function hydrateHiddenPrices()
        {
            let targetsByProductId = collectPriceTargets();
            let productIds = Object.keys(targetsByProductId);

            if (!productIds.length || typeof window.fetch !== 'function') {
                return Promise.resolve(false);
            }

            return window.fetch('/b2b/ajax/customerPrices?product_ids=' + encodeURIComponent(productIds.join(',')), {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function (response) {
                return response.ok ? response.json() : null;
            }).then(function (payload) {
                let hydratedAny = false;

                if (!payload || !payload.success || !payload.allowed || !payload.items) {
                    return false;
                }

                Object.keys(payload.items).forEach(function (productId) {
                    let item = payload.items[productId];

                    if (!item || !item.html || !targetsByProductId[productId]) {
                        return;
                    }

                    targetsByProductId[productId].forEach(function (priceMarker) {
                        replacePriceTarget(priceMarker, item.html);
                        hydratedAny = true;
                    });
                });

                return hydratedAny;
            }).catch(function () {
                return false;
            });
        }

        function syncPriceBlocksAfterLogin()
        {
            if (priceSyncStarted || isRestricted || !hasHiddenPriceMarkers()) {
                return;
            }

            priceSyncStarted = true;

            try {
                customerData.invalidate(['customer', 'cart']);
                customerData.reload(['customer', 'cart'], true);
            } catch (error) {
                // ignore customer-data refresh errors and continue with AJAX hydration
            }

            hydrateHiddenPrices().then(function (hydratedAny) {
                if (!hydratedAny) {
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 150);
                }
            });
        }

        function isModalOpen()
        {
            return overlay && overlay.classList.contains('active');
        }

        function getFocusableElements()
        {
            if (!dialog) {
                return [];
            }

            let focusables = Array.prototype.slice.call(
                dialog.querySelectorAll(
                    'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
                )
            );

            return focusables.filter(function (el) {
                return isElementVisible(el);
            });
        }

        function openModal(triggerEl)
        {
            if (!overlay || activeMode !== 'guest' || isModalOpen()) {
                return;
            }
            lastActiveElement = document.activeElement;
            lastTriggerButton = triggerEl || lastActiveElement;
            if (lastTriggerButton && typeof lastTriggerButton.setAttribute === 'function') {
                lastTriggerButton.setAttribute('aria-expanded', 'true');
            }
            overlay.hidden = false;
            overlay.removeAttribute('inert');
            overlay.classList.add('active');
            overlay.setAttribute('aria-hidden', 'false');

            if (previousBodyOverflow === null) {
                previousBodyOverflow = document.body.style.overflow;
            }
            document.body.style.overflow = 'hidden';

            window.setTimeout(function () {
                let focusables = getFocusableElements();
                if (focusables.length) {
                    focusables[0].focus();
                } else if (dialog) {
                    dialog.focus();
                }
            }, 0);
        }

        function closeModal()
        {
            if (!overlay) {
                return;
            }
            overlay.classList.remove('active');
            overlay.setAttribute('aria-hidden', 'true');
            overlay.setAttribute('inert', '');
            overlay.hidden = true;

            document.body.style.overflow = previousBodyOverflow !== null ? previousBodyOverflow : '';
            previousBodyOverflow = null;

            if (lastTriggerButton && typeof lastTriggerButton.setAttribute === 'function') {
                lastTriggerButton.setAttribute('aria-expanded', 'false');
            }
            lastTriggerButton = null;

            if (lastActiveElement && typeof lastActiveElement.focus === 'function') {
                lastActiveElement.focus();
            }
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                closeModal();
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    closeModal();
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            if (!isModalOpen()) {
                return;
            }

            if (e.key === 'Escape') {
                closeModal();
                return;
            }

            if (e.key !== 'Tab') {
                return;
            }

            let focusables = getFocusableElements();
            if (!focusables.length) {
                e.preventDefault();
                return;
            }

            let first = focusables[0];
            let last = focusables[focusables.length - 1];
            let isShiftPressed = e.shiftKey;
            let currentActiveElement = document.activeElement;

            if (isShiftPressed) {
                if (currentActiveElement === first) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                if (currentActiveElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });

        function wireSsrGuestButton(btn)
        {
            if (!btn || btn.getAttribute('data-b2b-wired') === '1' || activeMode !== 'guest') {
                return;
            }

            btn.setAttribute('data-b2b-wired', '1');
            btn.setAttribute('aria-haspopup', 'dialog');
            btn.setAttribute('aria-controls', 'b2b-login-modal');
            btn.addEventListener('click', function (e) {
                openModal(e.currentTarget);
            });
        }

        function replaceAddToCartButtons()
        {
            // CRITICAL: If customer-data confirms user is logged in, don't replace buttons
            if (!isRestricted) {
                return;
            }

            let isGuestMode = (activeMode === 'guest');
            let isPendingMode = (activeMode === 'pending');
            let isDisabledMode = !isGuestMode;

            // Add the appropriate body class
            document.body.classList.add(bodyClass);
            document.body.classList.add('b2b-restricted-mode');
            syncPendingPriceMarkers();

            let iconSvg = isGuestMode ? PDP_ICON_SVG : PENDING_ICON_SVG;

            // PDP (product detail page)
            let productAddForm = document.querySelector('.product-add-form');
            if (productAddForm) {
                let boxToCart = productAddForm.querySelector('.box-tocart');
                let qtyField = productAddForm.querySelector('.box-tocart .field.qty');
                let instantPurchase = productAddForm.querySelector('#instant-purchase');
                let addToCartBtn = productAddForm.querySelector('button.tocart, button#product-addtocart-button');
                let ssrGuestBtn = productAddForm.querySelector('.b2b-login-to-buy-btn[data-b2b-ssr="guest"]');

                if (ssrGuestBtn && isGuestMode) {
                    if (boxToCart) {
                        boxToCart.classList.add('b2b-login-to-buy-mode');
                    }
                    wireSsrGuestButton(ssrGuestBtn);
                } else if (boxToCart) {
                    boxToCart.classList.add('b2b-login-to-buy-mode');
                }

                if (qtyField && !ssrGuestBtn) {
                    qtyField.setAttribute('data-b2b-original-hidden', '1');
                    qtyField.style.display = 'none';
                }

                if (instantPurchase) {
                    instantPurchase.setAttribute('data-b2b-original-hidden', '1');
                    instantPurchase.style.display = 'none';
                }

                if (!ssrGuestBtn && addToCartBtn && !productAddForm.querySelector('.b2b-login-to-buy-btn')) {
                    addToCartBtn.setAttribute('data-b2b-original-hidden', '1');
                    addToCartBtn.style.display = 'none';

                    let pdpBtn = createLoginButton({
                        html: iconSvg + ' ' + ((config && config.pdpButtonText) ? config.pdpButtonText : 'Entrar para Comprar'),
                        disabled: isDisabledMode
                    });
                    pdpBtn.setAttribute('data-b2b-injected', '1');

                    if (isGuestMode) {
                        pdpBtn.setAttribute('aria-haspopup', 'dialog');
                        pdpBtn.setAttribute('aria-controls', 'b2b-login-modal');
                        pdpBtn.addEventListener('click', function (e) {
                            openModal(e.currentTarget);
                        });
                    }

                    addToCartBtn.parentNode.insertBefore(pdpBtn, addToCartBtn.nextSibling);
                }
            }

            // Product listings (category, search, widgets)
            document.querySelectorAll('.product-item-actions .actions-primary, .product-info-cart .actions-primary').forEach(function (actionsContainer) {
                let addBtn = actionsContainer.querySelector('button.tocart, form button.tocart');
                if (addBtn && !actionsContainer.querySelector('.b2b-login-to-buy-btn')) {
                    addBtn.setAttribute('data-b2b-original-hidden', '1');
                    addBtn.style.display = 'none';

                    let listingBtn = createLoginButton({
                        text: (config && config.listingButtonText) ? config.listingButtonText : 'Entrar para Comprar',
                        variantClass: 'b2b--listing',
                        disabled: isDisabledMode
                    });
                    listingBtn.setAttribute('data-b2b-injected', '1');

                    if (isGuestMode) {
                        listingBtn.setAttribute('aria-haspopup', 'dialog');
                        listingBtn.setAttribute('aria-controls', 'b2b-login-modal');
                        listingBtn.addEventListener('click', function (e) {
                            openModal(e.currentTarget);
                        });
                    }

                    actionsContainer.appendChild(listingBtn);
                }
            });
        }

        /**
         * Check customer-data and update restriction state.
         * If customer is logged in, restore original buttons.
         */
        function checkAndUpdateState()
        {
            if (serverMode === 'guest' && isCustomerLoggedIn()) {
                // Customer is actually logged in - FPC served a stale guest page
                isRestricted = false;
                restoreOriginalButtons();
                syncPriceBlocksAfterLogin();

                // Disconnect the MutationObserver to stop replacing buttons
                if (observerInstance) {
                    observerInstance.disconnect();
                    observerInstance = null;
                }
            }
        }

        function bindCustomerDataSubscribe()
        {
            if (serverMode !== 'guest') {
                return;
            }

            try {
                customerData.get('customer').subscribe(function (customer) {
                    if (isCustomerDataLoggedIn(customer)) {
                        isRestricted = false;
                        restoreOriginalButtons();
                        syncPriceBlocksAfterLogin();
                        if (observerInstance) {
                            observerInstance.disconnect();
                            observerInstance = null;
                        }
                    }
                });
            } catch (e) {
                // ignore
            }
        }

        // Show pending banner if in pending mode
        if (serverMode === 'pending' && pendingBanner) {
            pendingBanner.hidden = false;
        }

        checkAndUpdateState();
        bindCustomerDataSubscribe();

        if (!isRestricted) {
            return;
        }

        // Initial run - replace buttons (may be reverted by customer-data check)
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                replaceAddToCartButtons();
                window.setTimeout(checkAndUpdateState, 100);
            });
        } else {
            replaceAddToCartButtons();
            window.setTimeout(checkAndUpdateState, 100);
        }

        // Re-run with throttle on DOM changes
        let scheduled = false;
        function scheduleReplace()
        {
            if (scheduled || !isRestricted) {
                return;
            }
            scheduled = true;
            window.setTimeout(function () {
                scheduled = false;
                if (!isRestricted) {
                    return;
                }
                // Desconectar durante nossas próprias mutações DOM para evitar disparo
                // recursivo: cada botão injetado por replaceAddToCartButtons() causaria
                // uma nova mutação que re-acionaria este observer indefinidamente.
                if (observerInstance) {
                    observerInstance.disconnect();
                }
                replaceAddToCartButtons();
                syncPendingPriceMarkers();
                // Reconectar para capturar conteúdo carregado via AJAX (abas de produto)
                if (isRestricted && observerInstance) {
                    observerInstance.observe(document.body, {childList: true, subtree: true});
                }
            }, 120);
        }

        observerInstance = new MutationObserver(function (mutations) {
            for (let i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes && mutations[i].addedNodes.length) {
                    scheduleReplace();
                    break;
                }
            }
        });

        observerInstance.observe(document.body, {childList: true, subtree: true});
    }

    return init;
});
