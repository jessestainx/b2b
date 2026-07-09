/**
 * Home: carrega awa-minicart-ui-bootstrap após require real + customer-data defer.
 */
(function (w, d) {
    'use strict';

    var started = false;
    var intentEvents = ['pointerdown', 'touchstart', 'click', 'keydown'];
    var intentSelector = '[data-block="minicart"] .showcart, [data-awa-header-minicart-shell="true"], .awa-header-minicart';
    var requireWaitAttempts = 0;
    var openAfterInitPending = false;

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

    function start(openAfterInit) {
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

        start(true);
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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addIntentListeners, { once: true });
    } else {
        addIntentListeners();
    }

    // Expõe start() para o guard síncrono em awa-minicart-ui-defer.phtml
    w.__awaHomeMinicartBoot = function (options) {
        start(!!(options && options.openAfterInit));
    };

    // O guard inline pode ter disparado antes deste defer carregar
    if (w.__awaMinicartHomeBootPending) {
        start(true);
    }
}(window, document));
