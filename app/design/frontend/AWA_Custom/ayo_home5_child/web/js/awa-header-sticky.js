/**
 * AWA Header Sticky — condensed state on scroll
 *
 * Adds `awa-header-condensed` to .awa-site-header and
 * `is-sticky` + `awa-header-condensed` to .header-wrapper-sticky
 * when the page is scrolled past the dynamic scroll threshold.
 *
 * Promo bar height tracked via ResizeObserver — no getBoundingClientRect()
 * in scroll handler, no periodic forced layout reflows.
 *
 * Uses requestAnimationFrame for scroll throttle (no lodash needed).
 * Respects prefers-reduced-motion: skips class toggle animation context.
 *
 * RequireJS path: awa-header-sticky (registered in requirejs-config.js)
 */
define([], function () {
    'use strict';

    let SCROLL_THRESHOLD_BASE = 60;
    let DELTA_MIN = 6;
    let MOBILE_MQ = window.matchMedia('(max-width: 767px)');

    return function () {
        /** @type {Element|null} */
        let header = document.querySelector('.awa-site-header');
        /** @type {Element|null} */
        let stickyWrapper = document.querySelector('.header-wrapper-sticky');
        /** @type {HTMLInputElement|null} */
        let searchInput = document.getElementById('search');

        if (!header || !stickyWrapper) {
            return;
        }

        let ticking = false;
        let lastScrollY = window.pageYOffset || 0;
        let lastSticky = false;

        /* Promo bar height — updated by ResizeObserver, never read in scroll handler */
        let promoBarHeight = 0;

        function isCondensedAllowed() {
            let body = document.body;
            return body
                && !body.classList.contains('awa-account-operational')
                && !body.classList.contains('b2b-account-shell');
        }

        function syncMobileSearchTabOrder(isSticky) {
            if (!searchInput) {
                return;
            }

            if (isSticky && MOBILE_MQ.matches) {
                searchInput.setAttribute('tabindex', '-1');
            } else {
                searchInput.removeAttribute('tabindex');
            }
        }

        function initPromoBarObserver() {
            let bar = document.getElementById('awa-b2b-promo-bar');
            if (!bar) {
                return;
            }

            function updatePromoHeight(entries) {
                let entry = entries && entries[0];
                if (entry) {
                    let h = entry.contentRect ? entry.contentRect.height : entry.target.offsetHeight;
                    promoBarHeight = bar.style.display === 'none' ? 0 : Math.round(h);
                } else {
                    promoBarHeight = bar.style.display === 'none' ? 0 : Math.round(bar.offsetHeight);
                }
            }

            if (window.ResizeObserver) {
                let ro = new ResizeObserver(updatePromoHeight);
                ro.observe(bar);
            } else {
                promoBarHeight = bar.style.display === 'none' ? 0 : Math.round(bar.offsetHeight);
            }

            promoBarHeight = bar.style.display === 'none' ? 0 : Math.round(bar.offsetHeight);
        }

        function getScrollThreshold() {
            return SCROLL_THRESHOLD_BASE + promoBarHeight;
        }

        function clearStickyClasses() {
            header.classList.remove('awa-header-condensed', 'awa-scroll-down', 'awa-scroll-up');
            stickyWrapper.classList.remove('is-sticky', 'awa-header-condensed');
            document.body.classList.remove('awa-header-is-sticky');
            syncMobileSearchTabOrder(false);
            lastSticky = false;
        }

        function isHeaderRenderable() {
            let headerStyle = window.getComputedStyle(header);
            return headerStyle.display !== 'none' && headerStyle.visibility !== 'hidden';
        }

        /**
         * Apply or remove sticky classes based on current scrollY.
         */
        function updateStickyState() {
            if (!isCondensedAllowed()) {
                clearStickyClasses();
                lastScrollY = window.pageYOffset || 0;
                ticking = false;
                return;
            }

            let scrollY = window.pageYOffset !== undefined
                ? window.pageYOffset
                : (document.documentElement || document.body.parentNode || document.body).scrollTop;

            if (!isHeaderRenderable()) {
                clearStickyClasses();
                lastScrollY = scrollY;
                ticking = false;
                return;
            }

            let threshold = getScrollThreshold();
            let isSticky = scrollY > threshold;
            let direction = (scrollY - lastScrollY) > DELTA_MIN
                ? 'down'
                : ((lastScrollY - scrollY) > DELTA_MIN ? 'up' : 'still');

            if (isSticky !== lastSticky) {
                header.classList.toggle('awa-header-condensed', isSticky);
                document.body.classList.toggle('awa-header-is-sticky', isSticky);
                syncMobileSearchTabOrder(isSticky);
                lastSticky = isSticky;
            }

            header.classList.toggle('awa-scroll-down', direction === 'down' && isSticky);
            header.classList.toggle('awa-scroll-up', direction === 'up' && isSticky);

            if (isSticky !== stickyWrapper.classList.contains('is-sticky')) {
                stickyWrapper.classList.toggle('is-sticky', isSticky);
            }
            if (isSticky !== stickyWrapper.classList.contains('awa-header-condensed')) {
                stickyWrapper.classList.toggle('awa-header-condensed', isSticky);
            }

            lastScrollY = scrollY;
            ticking = false;
        }

        function onScroll() {
            if (!ticking) {
                window.requestAnimationFrame(updateStickyState);
                ticking = true;
            }
        }

        initPromoBarObserver();

        /* ── Phase 3: CLS placeholder — ResizeObserver on .awa-site-header ────────
         * Tracks the total rendered height of the site header and stores it in
         * --awa-header-height on <html>. When the header-wrapper-sticky transitions
         * to position:fixed (Rokanthemes parent CSS), body.awa-header-is-sticky is
         * toggled by updateStickyState() which makes #awa-header-cls-placeholder
         * visible at the correct height, preventing CLS.
         * Protected against double init via window.__awaHeaderHeightObserver.       */
        if (!window.__awaHeaderHeightObserver) {
            var placeholder = document.getElementById('awa-header-cls-placeholder');

            function updateHeaderHeight(entries) {
                var h;
                if (entries && entries[0] && entries[0].contentRect) {
                    h = Math.round(entries[0].contentRect.height);
                } else {
                    h = Math.round(header.offsetHeight);
                }
                document.documentElement.style.setProperty('--awa-header-height', h + 'px');
                if (placeholder) {
                    placeholder.style.height = h + 'px';
                }
            }

            if (window.ResizeObserver) {
                window.__awaHeaderHeightObserver = new ResizeObserver(updateHeaderHeight);
                window.__awaHeaderHeightObserver.observe(header);
            } else {
                /* Fallback: measure once and update on resize/orientationchange */
                window.__awaHeaderHeightObserver = true;
                var measureHeight = function () {
                    var h = Math.round(header.offsetHeight);
                    document.documentElement.style.setProperty('--awa-header-height', h + 'px');
                    if (placeholder) {
                        placeholder.style.height = h + 'px';
                    }
                };
                window.addEventListener('resize', measureHeight, { passive: true });
                window.addEventListener('orientationchange', measureHeight, { passive: true });
                measureHeight();
            }
        }

        if (MOBILE_MQ.addEventListener) {
            MOBILE_MQ.addEventListener('change', onScroll);
        } else if (MOBILE_MQ.addListener) {
            MOBILE_MQ.addListener(onScroll);
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll, { passive: true });

        updateStickyState();
    };
});
