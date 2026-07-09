/**
 * AWA Motos - PLP Infinite Scroll handler
 *
 * Sprint 3 (UX moderno): carrega proxima pagina automaticamente ao rolar.
 * Mantem URL atualizada com ?p=N para compartilhamento.
 */
(function (w, d) {
    'use strict';
    if (w.__awaPlpInfiniteInit) { return; }
    w.__awaPlpInfiniteInit = true;

    var CONTAINER = '.products.wrapper.grid.products-grid';
    var TOOLBAR_NEXT = '.pages-items-next a';
    var TOOLBAR_PAGE = '.pages li a';

    function $(s, r) { return (r || d).querySelector(s); }

    function init() {
        var container = $(CONTAINER);
        var trigger = d.querySelector('[data-awa-infinite-trigger]');
        var loader = d.querySelector('[data-awa-infinite-loader]');
        if (!container || !trigger) return;

        var currentPage = parseInt(trigger.parentNode.dataset.currentPage || '1', 10);
        var lastPage = parseInt(trigger.parentNode.dataset.lastPage || '1', 10);
        if (currentPage >= lastPage) return;

        var loading = false;
        var intersectionObserver;

        function loadNext() {
            if (loading) return;
            loading = true;
            if (loader) loader.hidden = false;

            var nextPage = currentPage + 1;
            var url = new URL(w.location.href);
            url.searchParams.set('p', nextPage);

            fetch(url.href, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var wrapper = d.createElement('div');
                    wrapper.innerHTML = html;

                    // Pegar grid de produtos
                    var newGrid = wrapper.querySelector(CONTAINER);
                    if (!newGrid) throw new Error('Grid nao encontrado');

                    // Mover itens para o grid atual
                    var items = newGrid.children;
                    while (items.length) {
                        container.appendChild(items[0]);
                    }

                    currentPage = nextPage;
                    loading = false;
                    if (loader) loader.hidden = true;

                    // Atualizar URL sem recarregar
                    w.history.replaceState({}, '', url.href);

                    // Re-bind quick view nos novos items
                    if (typeof w.__awaPlpQuickViewInit !== 'undefined') {
                        // Trigger re-bind
                        d.dispatchEvent(new CustomEvent('awa-plp-infinite-scroll:loaded'));
                    }

                    // Se atingiu a ultima pagina, parar
                    if (currentPage >= lastPage && intersectionObserver) {
                        intersectionObserver.disconnect();
                        if (trigger) trigger.remove();
                    }
                })
                .catch(function (e) {
                    loading = false;
                    if (loader) loader.hidden = true;
                    console.warn('[AWA] Erro ao carregar proxima pagina', e);
                });
        }

        if (typeof IntersectionObserver !== 'undefined') {
            intersectionObserver = new IntersectionObserver(function (entries) {
                for (var i = 0; i < entries.length; i++) {
                    if (entries[i].isIntersecting) {
                        loadNext();
                    }
                }
            }, { rootMargin: '600px 0px' });
            intersectionObserver.observe(trigger);
        }
    }

    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})(window, document);
