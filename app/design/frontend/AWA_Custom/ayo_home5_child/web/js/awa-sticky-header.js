/**
 * AWA Motos - Sticky Header on Scroll
 *
 * Sprint 3 (UX): header sticky ao rolar pagina.
 * Adiciona classe is-sticky quando scroll > threshold.
 */
(function (w, d) {
    'use strict';
    if (w.__awaStickyHeaderInit) { return; }
    w.__awaStickyHeaderInit = true;

    var THRESHOLD = 200; // pixels
    var REDUCED_THRESHOLD = 80; // quando reduced motion

    function init() {
        var header = d.querySelector('.awa-site-header') || d.querySelector('header.page-header');
        if (!header) return;

        // Check for reduced motion preference
        var prefersReduced = w.matchMedia && w.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var threshold = prefersReduced ? REDUCED_THRESHOLD : THRESHOLD;

        var lastScroll = 0;
        var ticking = false;

        function onScroll() {
            var scrollY = w.pageYOffset || d.documentElement.scrollTop || 0;
            var shouldStick = scrollY > threshold;

            if (shouldStick !== header.classList.contains('is-sticky')) {
                if (shouldStick) {
                    header.classList.add('is-sticky');
                } else {
                    header.classList.remove('is-sticky');
                }
            }

            // Hide/show on scroll direction (apenas se is-sticky)
            if (shouldStick) {
                if (scrollY > lastScroll && scrollY > 300) {
                    header.classList.add('is-hidden');
                } else {
                    header.classList.remove('is-hidden');
                }
            }

            lastScroll = scrollY;
            ticking = false;
        }

        function requestTick() {
            if (!ticking) {
                w.requestAnimationFrame(onScroll);
                ticking = true;
            }
        }

        w.addEventListener('scroll', requestTick, { passive: true });
        onScroll();
    }

    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})(window, document);
