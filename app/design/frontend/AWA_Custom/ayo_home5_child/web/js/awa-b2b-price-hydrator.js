define([
    'Magento_Customer/js/customer-data'
], function (customerData) {
    'use strict';

    let hydratedHtmlByProductId = {};
    let inFlightProducts = {};
    let refreshScheduled = false;
    let observerStarted = false;

    function isLoggedIn(customer) {
        if (!customer || typeof customer !== 'object') {
            return false;
        }

        return !!(
            customer.firstname
            || customer.fullname
            || customer.email
            || customer.id
            || customer.entity_id
            || customer.websiteId !== undefined
        );
    }

    function getCustomerPayload() {
        try {
            return customerData.get('customer')();
        } catch (e) {
            return {};
        }
    }

    function resolveProductId(node) {
        let root;
        let fromDataset;
        let productInput;
        let quickviewTrigger;

        root = node.closest('[data-product-id], .item-product, .product-item, li, .product-info-main, .product-add-form') || node.parentElement;

        fromDataset = root && root.getAttribute ? parseInt(root.getAttribute('data-product-id'), 10) : 0;
        if (fromDataset) {
            return String(fromDataset);
        }

        productInput = root ? root.querySelector('input[name="product"]') : null;
        if (productInput && productInput.value) {
            return String(parseInt(productInput.value, 10));
        }

        quickviewTrigger = root ? root.querySelector('[data-role="quickview-button"][data-id]') : null;
        if (quickviewTrigger) {
            return String(parseInt(quickviewTrigger.getAttribute('data-id'), 10));
        }

        return null;
    }

    function collectTargets() {
        let targets = {};

        document.querySelectorAll('.b2b-login-to-see-price').forEach(function (priceMarker) {
            let productId = resolveProductId(priceMarker);

            if (!productId) {
                return;
            }

            if (hydratedHtmlByProductId[productId]) {
                priceMarker.outerHTML = hydratedHtmlByProductId[productId];
                return;
            }

            if (!targets[productId]) {
                targets[productId] = [];
            }

            targets[productId].push(priceMarker);
        });

        return targets;
    }

    function replaceTargets(targets, payloadItems) {
        Object.keys(payloadItems).forEach(function (productId) {
            let item = payloadItems[productId];

            if (!item || !item.html || !targets[productId]) {
                return;
            }

            hydratedHtmlByProductId[productId] = item.html;

            targets[productId].forEach(function (marker) {
                marker.outerHTML = item.html;
            });
        });
    }

    function releaseInFlight(productIds) {
        productIds.forEach(function (productId) {
            delete inFlightProducts[productId];
        });
    }

    function hydratePrices() {
        let targets;
        let productIds;

        if (!isLoggedIn(getCustomerPayload()) || typeof window.fetch !== 'function') {
            return;
        }

        targets = collectTargets();
        productIds = Object.keys(targets).filter(function (productId) {
            return !inFlightProducts[productId];
        });

        if (!productIds.length) {
            return;
        }

        productIds.forEach(function (productId) {
            inFlightProducts[productId] = true;
        });

        window.fetch('/b2b/ajax/customerPrices?product_ids=' + encodeURIComponent(productIds.join(',')), {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return response.ok ? response.json() : null;
        }).then(function (payload) {
            if (!payload || !payload.success || !payload.allowed || !payload.items) {
                return;
            }

            replaceTargets(targets, payload.items);
        }).catch(function () {
            // no-op: placeholder remains until next refresh
        }).then(function () {
            releaseInFlight(productIds);
        });
    }

    function scheduleHydration() {
        if (refreshScheduled) {
            return;
        }

        refreshScheduled = true;
        window.setTimeout(function () {
            refreshScheduled = false;
            hydratePrices();
        }, 120);
    }

    function init() {
        let customerSection;
        let initialCustomer;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', scheduleHydration);
        } else {
            scheduleHydration();
        }

        customerSection = customerData.get('customer');
        initialCustomer = getCustomerPayload();

        // Guest nao precisa observar mutacoes da home inteira.
        if (isLoggedIn(initialCustomer)) {
            startObserver();
        }

        try {
            customerSection.subscribe(function (customer) {
                if (isLoggedIn(customer)) {
                    startObserver();
                    scheduleHydration();
                }
            });
        } catch (e) {
            // ignore subscription errors
        }
    }

    const PRODUCT_OBSERVER_ROOT =
        '.page-main, #maincontent, .column.main, .products-grid, .product-items';
    const PRODUCT_MUTATION_SEL =
        '.b2b-login-to-see-price, .product-item, .item-product, [data-product-id]';

    function resolveProductObserverRoot() {
        return document.querySelector(PRODUCT_OBSERVER_ROOT);
    }

    function mutationTouchesCatalog(mutations) {
        let i;
        let j;
        let node;

        for (i = 0; i < mutations.length; i++) {
            let added = mutations[i].addedNodes;
            if (!added || !added.length) {
                continue;
            }
            for (j = 0; j < added.length; j++) {
                node = added[j];
                if (node.nodeType !== 1) {
                    continue;
                }
                if (node.matches && node.matches(PRODUCT_MUTATION_SEL)) {
                    return true;
                }
                if (node.querySelector && node.querySelector(PRODUCT_MUTATION_SEL)) {
                    return true;
                }
            }
        }

        return false;
    }

    function startObserver() {
        let root;

        if (observerStarted) {
            return;
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', startObserver, { once: true });
            return;
        }

        root = resolveProductObserverRoot();
        if (!root) {
            return;
        }

        observerStarted = true;
        new MutationObserver(function (mutations) {
            if (!mutationTouchesCatalog(mutations)) {
                return;
            }
            scheduleHydration();
        }).observe(root, { childList: true, subtree: true });
    }

    return init;
});
