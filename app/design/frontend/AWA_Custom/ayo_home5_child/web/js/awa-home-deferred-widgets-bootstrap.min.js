/**
 * Home: adia catalogAddToCart, superdeals, storage-manager e invalidation-processor.
 */
define([], function () {
    'use strict';

    var started = false;

    function initCatalogAddToCart() {
        require(['jquery', 'catalogAddToCart'], function ($) {
            $('[data-role="tocart-form"]').each(function () {
                var $form = $(this);
                if ($form.data('catalogAddToCart')) {
                    return;
                }
                $form.catalogAddToCart({});
            });
        });
    }

    function initSuperdeals() {
        if (window.__awaShelfCarouselInit || window.AWA_SHELF_CAROUSEL) {
            return;
        }
        var node = document.getElementById('awa-home-superdeals-init-json');
        if (!node || !node.textContent) {
            return;
        }

        var payload;
        try {
            payload = JSON.parse(node.textContent);
        } catch (e) {
            return;
        }

        if (!payload || !payload.config) {
            return;
        }

        require(['js/superdeals-init'], function (superdealsInit) {
            var selector = payload.selector || '.hot-deal-tab-slider-customcss';
            document.querySelectorAll(selector).forEach(function (element) {
                if (element.getAttribute('data-awa-superdeals-init') === '1') {
                    return;
                }
                element.setAttribute('data-awa-superdeals-init', '1');
                superdealsInit(payload.config, element);
            });
        });
    }

    function initStorageManager() {
        if (window.__awaStorageManagerInit) {
            return;
        }

        var node = document.getElementById('awa-frontend-storage-init-json');
        if (!node || !node.textContent) {
            return;
        }

        var payload;
        try {
            payload = JSON.parse(node.textContent);
        } catch (e) {
            return;
        }

        if (!payload || !payload.component) {
            return;
        }

        require(['Magento_Ui/js/core/app'], function (uiApp) {
            window.__awaStorageManagerInit = true;
            uiApp({
                components: {
                    'storage-manager': {
                        component: payload.component,
                        appendTo: payload.appendTo || '',
                        storagesConfiguration: payload.storagesConfiguration || {}
                    }
                }
            });
        });
    }

    function initInvalidationProcessor() {
        if (window.__awaInvalidationProcessorInit) {
            return;
        }

        var node = document.getElementById('awa-customer-invalidation-init-json');
        if (!node || !node.textContent) {
            return;
        }

        var payload;
        try {
            payload = JSON.parse(node.textContent);
        } catch (e) {
            return;
        }

        if (!payload || !payload.invalidationRules) {
            return;
        }

        window.__awaInvalidationProcessorInit = true;
        require(['Magento_Customer/js/invalidation-processor'], function (invalidationProcessor) {
            invalidationProcessor({
                invalidationRules: payload.invalidationRules
            });
        });
    }

    function initQuickview() {
        if (window.__awaQuickviewHomeInit) {
            return;
        }

        window.__awaQuickviewHomeInit = true;

        require([
            'jquery',
            'productQuickview',
            'quickview/cloudzoom',
            'swiper'
        ], function ($) {
            $('.quickview-product [data-role=quickview-button]').each(function () {
                var $button = $(this);
                if ($button.data('mageProductQuickview')) {
                    return;
                }
                $button.productQuickview({});
            });
        });
    }

    function runIdleSafe() {
        if (started) {
            return;
        }
        started = true;
        // Idle: só superdeals se existir. NÃO puxa storage-manager / invalidation /
        // catalogAddToCart (cascade Magento_Catalog + section compare/wishlist).
        initSuperdeals();
        if (document.getElementById('awa-frontend-storage-init-json')) {
            initStorageManager();
        }
        if (document.getElementById('awa-customer-invalidation-init-json')) {
            require(['js/awa-customer-sections-gate'], function (whenCustomerSectionsReady) {
                whenCustomerSectionsReady(initInvalidationProcessor);
            });
        }
    }

    return function bootstrapHomeDeferredWidgets() {
        var cartIntentSelectors = [
            '[data-role="tocart-form"] .tocart',
            '[data-role="tocart-form"] button[type="submit"]',
            '.action.tocart'
        ];
        var shelfIntentSelectors = [
            '.hot-deal-tab-slider-customcss',
            '.rokan-bestseller',
            '.rokan-newproduct'
        ];

        function onQuickviewIntent(evt) {
            var target = evt && evt.target;
            if (!target || !target.closest) {
                return;
            }
            if (target.closest('.quickview-link, [data-role="quickview-button"]')) {
                initQuickview();
            }
        }

        function onCartIntent(evt) {
            var target = evt && evt.target;
            if (!target || !target.closest) {
                return;
            }
            if (cartIntentSelectors.some(function (sel) { return target.closest(sel); })) {
                initCatalogAddToCart();
            }
        }

        function onShelfIntent(evt) {
            var target = evt && evt.target;
            if (!target || !target.closest) {
                return;
            }
            if (shelfIntentSelectors.some(function (sel) { return target.closest(sel); })) {
                runIdleSafe();
            }
        }

        ['pointerdown', 'touchstart', 'keydown'].forEach(function (evtName) {
            window.addEventListener(evtName, onQuickviewIntent, { passive: true });
            window.addEventListener(evtName, onCartIntent, { passive: true });
            window.addEventListener(evtName, onShelfIntent, { passive: true });
        });

        var fallbackDelay = window.matchMedia('(max-width: 767px)').matches ? 6500 : 4000;

        if (typeof window.requestIdleCallback === 'function' && fallbackDelay <= 4000) {
            window.requestIdleCallback(runIdleSafe, { timeout: fallbackDelay });
        } else {
            window.setTimeout(runIdleSafe, fallbackDelay);
        }
    };
});
