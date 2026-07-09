define([
    'awa-header-sticky',
    'awa-header-nav-runtime',
    'awa-header-customer-runtime'
], function (
    initStickyHeader,
    initHeaderNavRuntime,
    initHeaderCustomerRuntime
) {
    'use strict';

    function initPromoBarDismiss() {
        if (window.__awaPromoDismissInit) {
            return;
        }

        var bar = document.getElementById('awa-b2b-promo-bar');
        var btn = document.getElementById('awa-b2b-promo-close');
        if (!bar || !btn) {
            return;
        }

        window.__awaPromoDismissInit = true;

        try {
            if (localStorage.getItem('awa_b2b_promo_dismissed') === '1'
                || sessionStorage.getItem('awa_b2b_promo_dismissed_session') === '1') {
                bar.style.display = 'none';
                bar.setAttribute('aria-hidden', 'true');
                return;
            }
        } catch (storageReadError) {
            console.warn('[AWA] promo bar dismiss: localStorage read failed', storageReadError);
        }

        var dismissing = false;
        var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function hideBar() {
            bar.style.display = 'none';
            bar.setAttribute('aria-hidden', 'true');
            try {
                sessionStorage.setItem('awa_b2b_promo_dismissed_session', '1');
                localStorage.setItem('awa_b2b_promo_dismissed', '1');
            } catch (storageError) {
                console.warn('[AWA] promo bar dismiss: localStorage unavailable', storageError);
            }
        }

        btn.addEventListener('click', function () {
            if (dismissing || bar.getAttribute('aria-hidden') === 'true') {
                return;
            }
            dismissing = true;
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');

            if (reducedMotion) {
                hideBar();
                return;
            }

            bar.classList.add('is-dismissing');
            window.setTimeout(hideBar, 320);
        });
    }

    /**
     * Manages aria-expanded state on .awa-header-cart-fallback when the
     * minicart panel opens or closes. Also handles ESC key and click-outside.
     *
     * Phase 2: Cart ARIA States (WCAG 4.1.2)
     */
    function initCartAria() {
        if (window.__awaCartAriaInit) {
            return;
        }

        var shell = document.querySelector('[data-awa-header-minicart-shell]');
        var fallback = document.querySelector('[data-awa-header-minicart-fallback]');
        var panel = document.getElementById('awa-minicart-panel');

        if (!shell || !fallback) {
            return;
        }

        window.__awaCartAriaInit = true;

        /**
         * Sync aria-expanded on the fallback link to match the shell's
         * data-awa-minicart-expanded attribute (set by Rokanthemes minicart JS).
         */
        function syncAriaExpanded() {
            var expanded = shell.getAttribute('data-awa-minicart-expanded') === '1';
            fallback.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }

        // Observe data-awa-minicart-expanded changes on the shell element.
        if (window.MutationObserver) {
            var mo = new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    if (m.attributeName === 'data-awa-minicart-expanded') {
                        syncAriaExpanded();
                    }
                });
            });
            mo.observe(shell, { attributes: true, attributeFilter: ['data-awa-minicart-expanded'] });
        }

        // Initial sync.
        syncAriaExpanded();

        /**
         * Close minicart and restore focus.
         * Mirrors the pattern used by the Rokanthemes minicart JS.
         */
        function closeMinicart() {
            shell.setAttribute('data-awa-minicart-expanded', '0');
            syncAriaExpanded();
            fallback.focus();
        }

        // ESC key: close minicart when panel is open and focus is inside it.
        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Escape' || e.keyCode === 27) &&
                    shell.getAttribute('data-awa-minicart-expanded') === '1') {
                closeMinicart();
            }
        });

        // Click-outside: close when clicking outside the minicart shell.
        document.addEventListener('click', function (e) {
            if (shell.getAttribute('data-awa-minicart-expanded') === '1' &&
                    !shell.contains(e.target)) {
                closeMinicart();
            }
        });
    }

    return function bootstrapHeaderRuntime() {
        if (window.__awaHeaderRuntimeBootstrapInit) {
            return;
        }

        window.__awaHeaderRuntimeBootstrapInit = true;

        if (typeof initStickyHeader === 'function') {
            initStickyHeader();
        }
        if (typeof initHeaderNavRuntime === 'function') {
            initHeaderNavRuntime();
        }
        if (typeof initHeaderCustomerRuntime === 'function') {
            initHeaderCustomerRuntime();
        }

        initPromoBarDismiss();
        initCartAria();
    };
});
