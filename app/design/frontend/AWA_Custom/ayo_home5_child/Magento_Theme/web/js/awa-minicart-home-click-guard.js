/**
 * Home minicart click guard (sync external) — intercepta .showcart antes da navegacao.
 */
(function (w, d) {
    'use strict';
    if (w.__awaHomeMinicartClickGuard) {
        return;
    }
    w.__awaHomeMinicartClickGuard = true;

    var openPending = false;
    var warmPending = false;

    function isDropdownReady() {
        var jq = w.jQuery;
        if (!jq || !jq.fn || typeof jq.fn.dropdownDialog !== 'function') {
            return false;
        }
        var $panel = jq('[data-block="minicart"] [data-role="dropdownDialog"]');
        return !!($panel.length && $panel.data('mageDropdownDialog'));
    }

    function tryOpenDropdown() {
        var jq = w.jQuery;
        if (!jq) {
            return false;
        }
        var $panel = jq('[data-block="minicart"] [data-role="dropdownDialog"]');
        if ($panel.length && $panel.data('mageDropdownDialog')) {
            $panel.dropdownDialog('open');
            openPending = false;
            if (typeof w.require === 'function') {
                w.require(['js/awa-minicart-position'], function (minicartPosition) {
                    if (minicartPosition && typeof minicartPosition.scheduleCenterOpenMinicartPanel === 'function') {
                        minicartPosition.scheduleCenterOpenMinicartPanel($panel.get(0));
                    }
                });
            }
            return true;
        }
        return false;
    }

    function openDropdownWithRetry(maxAttempts) {
        var attempts = 0;

        if (openPending) {
            return;
        }

        openPending = true;

        function attemptOpen() {
            attempts += 1;
            if (tryOpenDropdown()) {
                return;
            }
            if (attempts < maxAttempts) {
                w.setTimeout(attemptOpen, 75);
                return;
            }

            openPending = false;
        }

        attemptOpen();
    }

    function scheduleOpen(ready) {
        if (!ready || typeof ready.then !== 'function') {
            return;
        }
        ready.then(function () {
            openDropdownWithRetry(24);
        }, function () {
            openDropdownWithRetry(8);
        });
    }

    function pollForReadyAndOpen() {
        var polls = 0;
        var iv = w.setInterval(function () {
            polls += 1;
            var ready = w.__awaMinicartUiReady;
            if (ready && typeof ready.then === 'function') {
                w.clearInterval(iv);
                scheduleOpen(ready);
                return;
            }
            if (polls > 120) {
                w.clearInterval(iv);
            }
        }, 50);
    }

    function forceMinicartOpenShell() {
        var shell = d.querySelector('.awa-header-minicart');
        var wrap = d.querySelector('[data-block="minicart"], .minicart-wrapper');
        var panel = d.querySelector('[data-block="minicart"] .block-minicart, .block-minicart');
        if (shell) {
            shell.classList.add('awa-header-minicart--expanded');
        }
        if (wrap) {
            wrap.classList.add('active', 'is-open', 'show');
            wrap.setAttribute('data-awa-minicart-dropdown', '1');
        }
        if (panel) {
            panel.classList.add('_active', 'active', 'is-open');
            panel.setAttribute('aria-hidden', 'false');
        }
        d.documentElement.classList.add('awa-minicart-overlay-active');
        if (typeof w.require === 'function') {
            w.require(['js/awa-minicart-position'], function (minicartPosition) {
                if (minicartPosition && typeof minicartPosition.scheduleCenterOpenMinicartPanel === 'function') {
                    minicartPosition.scheduleCenterOpenMinicartPanel(panel || d.querySelector('.block-minicart'));
                }
            });
        }
    }

    function onMinicartClick(event) {
        var target = event && event.target;
        var trigger = null;
        if (!target || typeof target.closest !== 'function') {
            return;
        }
        if (target.closest('[data-role="awa-vertical-menu-trigger"], .title-category-dropdown, .awa-header-categories.menu_left_home1')) {
            return;
        }
        trigger = target.closest('[data-block="minicart"] .showcart, [data-block="minicart"] .action.showcart, .minicart-wrapper .showcart, .minicart-wrapper .action.showcart, .header .showcart, .header .action.showcart');
        if (!trigger) {
            return;
        }

        var wrapNow = d.querySelector('[data-block="minicart"], .minicart-wrapper');
        var alreadyOpen = !!(wrapNow && (wrapNow.classList.contains('active') || wrapNow.classList.contains('is-open') || wrapNow.classList.contains('show')));
        var hasUiReadyPromise = !!(w.__awaMinicartUiReady && typeof w.__awaMinicartUiReady.then === 'function');
        var uiInit = !!w.__awaMinicartUiInit;

        if (isDropdownReady()) {
            // Magento toggles; only recenter when opening.
            if (!alreadyOpen) {
                forceMinicartOpenShell();
            } else {
                var shell = d.querySelector('.awa-header-minicart');
                if (shell) {
                    shell.classList.remove('awa-header-minicart--expanded');
                }
                d.documentElement.classList.remove('awa-minicart-overlay-active');
            }
            return;
        }

        if (uiInit || hasUiReadyPromise) {
            // UI já hidratada: evitar forçar classes manuais no shell e delegar a abertura
            // para o fluxo oficial (dropdownDialog/openAfterInit), prevenindo estado visual
            // divergente no header/minicart.
            event.preventDefault();
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }
            if (typeof w.__awaHomeMinicartBoot === 'function') {
                w.__awaHomeMinicartBoot({ openAfterInit: true });
            }
            if (hasUiReadyPromise) {
                scheduleOpen(w.__awaMinicartUiReady);
            } else {
                pollForReadyAndOpen();
            }
            return;
        }

        // Cold path: block navigation and force open shell immediately.
        forceMinicartOpenShell();
        event.preventDefault();
        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }
        w.__awaMinicartHomeBootPending = true;
        w.__awaMinicartOpenAfterInit = true;
        if (typeof w.__awaHomeMinicartBoot === 'function') {
            w.__awaHomeMinicartBoot({ openAfterInit: true });
        }
        if (w.__awaMinicartUiReady && typeof w.__awaMinicartUiReady.then === 'function') {
            scheduleOpen(w.__awaMinicartUiReady);
        } else {
            pollForReadyAndOpen();
        }
    }

    d.addEventListener('click', onMinicartClick, true);

    // Warm: só marca pending + preload position AMD. NÃO boot KO no hover
    // (GTmetrix/TBT — hidratação completa só no clique via onMinicartClick).
    function onMinicartWarm(event) {
        var target = event && event.target;
        if (warmPending || w.__awaMinicartUiInit) {
            return;
        }
        if (!target || typeof target.closest !== 'function') {
            return;
        }
        if (!target.closest('[data-block="minicart"], .awa-header-minicart, .minicart-wrapper .showcart, .action.showcart')) {
            return;
        }
        warmPending = true;
        w.__awaMinicartHomeWarmPending = true;
        if (typeof w.require === 'function') {
            w.require(['js/awa-minicart-position'], function () { /* warm AMD cache */ });
        }
        // Não chamar __awaHomeMinicartBoot aqui — evita waterfall KO sem clique.
    }
    d.addEventListener('mouseover', onMinicartWarm, true);
    d.addEventListener('pointerdown', onMinicartWarm, true);

    function bindWarmToShowcart() {
        var nodes = d.querySelectorAll(
            '[data-block="minicart"] .showcart, .minicart-wrapper .action.showcart, .awa-header-minicart .showcart'
        );
        var i;
        var el;

        for (i = 0; i < nodes.length; i += 1) {
            el = nodes[i];
            if (el.getAttribute('data-awa-minicart-warm') === '1') {
                continue;
            }
            el.setAttribute('data-awa-minicart-warm', '1');
            el.addEventListener('mouseenter', onMinicartWarm, { passive: true });
            el.addEventListener('pointerenter', onMinicartWarm, { passive: true });
            el.addEventListener('focus', onMinicartWarm, { passive: true });
        }
    }
    [0, 300, 800, 1500, 3000].forEach(function (t) {
        w.setTimeout(bindWarmToShowcart, t);
    });

    // Keep onMinicartClick permanently: after uiInit it only schedules center
    // (no preventDefault). Removing it caused the 44px uncentered flash once warm.
    function removeWarmOnly() {
        if (w.__awaMinicartUiInit || warmPending) {
            d.removeEventListener('mouseover', onMinicartWarm, true);
            d.removeEventListener('pointerdown', onMinicartWarm, true);
        }
    }
    [2000, 5000, 10000].forEach(function (t) {
        w.setTimeout(removeWarmOnly, t);
    });
}(window, document));
