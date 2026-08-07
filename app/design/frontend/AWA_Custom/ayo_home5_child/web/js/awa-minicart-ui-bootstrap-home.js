/**
 * Home: carrega awa-minicart-ui-bootstrap após require real + customer-data defer.
 */
(function (w, d) {
    'use strict';

    var started = false;
    var warmed = false;
    var intentEvents = ['pointerdown', 'touchstart', 'click', 'keydown'];
    var warmEvents = ['mouseover', 'pointerdown', 'focusin'];
    var intentSelector = '[data-block="minicart"] .showcart, [data-awa-header-minicart-shell="true"], .awa-header-minicart';
    var requireWaitAttempts = 0;
    var openAfterInitPending = false;

    function preloadPositionModule() {
        if (typeof w.require !== 'function') {
            return;
        }

        w.require(['js/awa-minicart-position'], function () {
            /* warm AMD cache */
        });
    }

    /**
     * Home PSI keeps KO deferred until cart intent, but SSR counter stays "0/empty".
     * Sync the badge from Magento customer-data without booting the full minicart UI.
     */
    function syncBadgeFromCustomerData() {
        function applyCart(cart) {
            var counter;
            var total;
            var count;

            if (w.__awaMinicartUiInit) {
                return;
            }

            cart = cart || {};
            count = parseInt(cart.summary_count, 10);
            if (isNaN(count) || count < 0) {
                count = 0;
            }

            counter = d.querySelector('[data-block="minicart"] .counter.qty');
            total = d.querySelector('[data-block="minicart"] .total-mini-cart-item');
            if (!counter) {
                return;
            }

            if (count > 0) {
                counter.classList.remove('empty');
                if (total && !total.querySelector('[data-bind]')) {
                    total.textContent = String(count);
                }
            } else {
                counter.classList.add('empty');
                if (total && !total.querySelector('[data-bind]')) {
                    total.textContent = '0';
                }
            }
        }

        function bind() {
            if (typeof w.require !== 'function') {
                return false;
            }

            w.require(['Magento_Customer/js/customer-data'], function (customerData) {
                var cart = customerData.get('cart');
                applyCart(cart());
                if (cart && typeof cart.subscribe === 'function') {
                    cart.subscribe(applyCart);
                }
            });
            return true;
        }

        if (bind()) {
            return;
        }

        if (typeof w.awaRunWhenRequire === 'function') {
            w.awaRunWhenRequire(bind, { key: 'minicart-home-badge-sync' });
            return;
        }

        w.setTimeout(function () {
            bind();
        }, 100);
    }

    function parsePayload() {
        var node = document.getElementById('awa-minicart-ui-json');
        if (!node || !node.textContent) {
            return null;
        }

        try {
            return JSON.parse(node.textContent);
        } catch (e) {
            if (w.console && w.console.error) {
                w.console.error('AWA minicart UI payload parse failed', e);
            }
            return null;
        }
    }

    function boot(openAfterInit) {
        var payload = parsePayload();
        if (!payload) {
            return;
        }

        w.require(['js/awa-minicart-ui-bootstrap'], function (bootstrapMinicartUi) {
            function start() {
                bootstrapMinicartUi(payload, {
                    skipCustomerGate: true,
                    launchDelayMs: 0,
                    openAfterInit: !!(openAfterInit || openAfterInitPending || w.__awaMinicartOpenAfterInit)
                });
                openAfterInitPending = false;
            }

            start();
        });
    }

    function start(openAfterInit, reason) {
        if (openAfterInit) {
            openAfterInitPending = true;
            w.__awaMinicartOpenAfterInit = true;
        }

        if (started) {
            if (openAfterInit) {
                boot(true);
            }
            return;
        }

        started = true;
        removeIntentListeners();
        removeWarmListeners();
        preloadPositionModule();

        function runBoot() {
            if (typeof w.require === 'function') {
                boot(openAfterInit);
                return;
            }

            if (typeof w.awaRunWhenRequire === 'function') {
                w.awaRunWhenRequire(function () {
                    boot(openAfterInit);
                }, { key: 'minicart-ui-bootstrap-home' });
                return;
            }

            if (typeof w.awaWhenRequire === 'function') {
                w.awaWhenRequire(function () {
                    boot(openAfterInit);
                }, { key: 'minicart-ui-bootstrap-home' });
                return;
            }

            requireWaitAttempts += 1;
            if (requireWaitAttempts <= 120) {
                w.setTimeout(runBoot, 50);
                return;
            }

            started = false;
            addIntentListeners();
        }

        runBoot();
    }

    function isCartIntent(event) {
        var target = event && event.target;

        if (!target || typeof target.closest !== 'function') {
            return false;
        }

        if (event.type === 'keydown') {
            if (!target || typeof target.closest !== 'function' || !target.closest(intentSelector)) {
                return false;
            }
            if (event.key !== 'Enter' && event.key !== ' ') {
                return false;
            }
        }

        if (event.type !== 'keydown' && target && typeof target.closest === 'function') {
            var closestInteractive = target.closest('a, button, [role="button"], [data-action], .action');
            if (!closestInteractive || !closestInteractive.closest(intentSelector)) {
                return false;
            }
        }

        if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
            return false;
        }

        return !!target.closest(intentSelector);
    }

    function onIntent(event) {
        var isIntent = isCartIntent(event);

        if (!isIntent) {
            return;
        }

        start(true, 'onIntent:' + (event && event.type));
    }

    function onWarm(event) {
        var target = event && event.target;

        if (warmed || started) {
            return;
        }

        if (!target || typeof target.closest !== 'function') {
            return;
        }

        if (!target.closest(intentSelector)) {
            return;
        }

        warmed = true;
        preloadPositionModule();
        // NÃO chamar start() no warm: hover/mouseover hidratava KO+jquery-ui sem clique (TBT).
        // Boot completo só em onIntent / __awaHomeMinicartBoot.
    }

    function addIntentListeners() {
        var i;

        for (i = 0; i < intentEvents.length; i += 1) {
            d.addEventListener(intentEvents[i], onIntent, {
                capture: true,
                passive: intentEvents[i] !== 'keydown'
            });
        }
    }

    function removeIntentListeners() {
        var i;

        for (i = 0; i < intentEvents.length; i += 1) {
            d.removeEventListener(intentEvents[i], onIntent, true);
        }
    }

    function addWarmListeners() {
        var i;

        for (i = 0; i < warmEvents.length; i += 1) {
            d.addEventListener(warmEvents[i], onWarm, { capture: true, passive: true });
        }
    }

    function removeWarmListeners() {
        var i;

        for (i = 0; i < warmEvents.length; i += 1) {
            d.removeEventListener(warmEvents[i], onWarm, true);
        }
    }

    /* Sem idle warm automático: GTmetrix/Lighthouse sem interação ainda assim
       disparava KO+jquery-ui+templates do minicart (~13s) e inflava TBT.
       Warm só em hover/focus no ícone; open no clique (guard + onIntent). */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            addIntentListeners();
            addWarmListeners();
            syncBadgeFromCustomerData();
        }, { once: true });
    } else {
        addIntentListeners();
        addWarmListeners();
        syncBadgeFromCustomerData();
    }

    // Expõe start() para o guard síncrono em awa-minicart-ui-defer.phtml
    w.__awaHomeMinicartBoot = function (options) {
        start(!!(options && options.openAfterInit), 'api:__awaHomeMinicartBoot');
    };

    // Pending warm NÃO deve bootar KO — só clique/API com open.
    if (w.__awaMinicartHomeBootPending) {
        start(true, 'pending:boot');
    }
}(window, document));
