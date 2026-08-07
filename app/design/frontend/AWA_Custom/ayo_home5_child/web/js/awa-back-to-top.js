/**
 * AWA Motos — Back to Top Button
 * RequireJS widget: mostra/esconde botão e scroll suave ao topo.
 */
define([], function () {
    'use strict';

    return function (config, element) {
        if (!element) {
            return;
        }

        const threshold = config.threshold || 600;
        let shown = false;

        function showButton() {
            element.hidden = false;
            element.removeAttribute('hidden');
            element.setAttribute('aria-hidden', 'false');
            element.classList.add('is-visible');
            shown = true;
        }

        function hideButton() {
            element.hidden = true;
            element.setAttribute('hidden', '');
            element.setAttribute('aria-hidden', 'true');
            element.classList.remove('is-visible');
            shown = false;
        }

        function toggle() {
            const y = window.pageYOffset || document.documentElement.scrollTop;

            if (y > threshold && !shown) {
                showButton();
            } else if (y <= threshold && shown) {
                hideButton();
            }
        }

        // Keep only the new AWA button active; hide legacy fixed-right scroll-top item.
        document.querySelectorAll('.fixed-right .scroll-top, .fixed-right-ul .scroll-top').forEach((legacyNode) => {
            legacyNode.style.display = 'none';
        });

        hideButton();

        window.addEventListener('scroll', toggle, {passive: true});

        element.addEventListener('click', () => {
            const prefersReducedMotion = window.matchMedia &&
                window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            window.scrollTo({top: 0, behavior: prefersReducedMotion ? 'auto' : 'smooth'});
        });

        toggle();
    };
});
