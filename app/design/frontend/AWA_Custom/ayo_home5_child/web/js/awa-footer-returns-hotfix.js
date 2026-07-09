define([], function () {
    'use strict';

    let LEGACY_PATTERN = /\/trocas-devolucoes\/?$/i;
    let CANONICAL_PATH = '/returns';

    function normalizeReturnsLinks(root) {
        let scope = root || document;
        let links = scope.querySelectorAll('a[href]');

        links.forEach(function (link) {
            let href = link.getAttribute('href') || '';

            if (!LEGACY_PATTERN.test(href)) {
                return;
            }

            // Keep protocol/host from current document and normalize to canonical route.
            link.setAttribute('href', CANONICAL_PATH);
            link.setAttribute('data-awa-returns-normalized', '1');
        });
    }

    function boot() {
        normalizeReturnsLinks(document);

        if (typeof MutationObserver === 'undefined') {
            return;
        }

        let footerRoot = document.querySelector('.page-footer, footer.footer, .footer.content');

        if (!footerRoot) {
            return;
        }

        new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node && node.nodeType === 1) {
                        normalizeReturnsLinks(node);
                    }
                });
            });
        }).observe(footerRoot, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
        return;
    }

    boot();
});
