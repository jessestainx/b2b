/**
 * AWA Compare — Comparador de Produtos Flutuante (2026-03-19)
 *
 * Até 3 produtos selecionados no PLP.
 * Barra flutuante no bottom mostra os selecionados.
 * Modal abre comparação side-by-side via REST API.
 *
 * Persistência: localStorage 'awa_compare_items' (array de {id, sku, name, img, url})
 * Inicializado via data-mage-init em list.phtml ou via x-magento-init global.
 */
define(['jquery', 'mage/url'], function ($, urlBuilder) {
    'use strict';

    let STORAGE_KEY = 'awa_compare_items';
    let MAX_ITEMS   = 3;
    let BAR_ID      = 'awa-compare-bar';
    let MODAL_ID    = 'awa-compare-modal';
    let isInitialized = false;

    function shouldDisableCompareUi() {
        let body = document.body;
        if (!body) {
            return false;
        }

        return body.classList.contains('cms-index-index') ||
            body.classList.contains('cms-home') ||
            body.classList.contains('cms-homepage_ayo_home5') ||
            body.classList.contains('checkout-index-index') ||
            body.classList.contains('onepagecheckout-index-index') ||
            body.classList.contains('b2b-register-index') ||
            body.classList.contains('b2b-auth-shell') ||
            body.classList.contains('b2b-account-login') ||
            body.classList.contains('b2b-account-forgotpassword') ||
            body.classList.contains('b2b-account-claim') ||
            body.classList.contains('customer-account-login') ||
            body.classList.contains('customer-account-create') ||
            body.classList.contains('catalogsearch-result-index') ||
            body.classList.contains('catalogsearch-advanced-result') ||
            body.classList.contains('catalogsearch-advanced-index');
    }

    function removeCompareUiArtifacts() {
        let bar = document.getElementById(BAR_ID);
        let modal = document.getElementById(MODAL_ID);

        if (bar && bar.parentNode) {
            bar.parentNode.removeChild(bar);
        }

        if (modal && modal.parentNode) {
            modal.parentNode.removeChild(modal);
        }
    }

    /* ---- Attributes to compare ---- */
    let COMPARE_ATTRS = [
        { code: 'price',            label: 'Preço',           type: 'price' },
        { code: 'special_price',    label: 'Preço Promo',     type: 'price' },
        { code: 'sku',              label: 'SKU',             type: 'text' },
        { code: 'weight',           label: 'Peso (kg)',       type: 'text' },
        { code: 'product_brand',    label: 'Marca',           type: 'text' },
        { code: 'manufacturer',     label: 'Fabricante',      type: 'text' },
        { code: 'marca_moto',       label: 'Marca da Moto',   type: 'text' },
        { code: 'modelo_moto',      label: 'Modelo da Moto',  type: 'text' },
        { code: 'ano_moto',         label: 'Ano da Moto',     type: 'text' },
        { code: 'short_description',label: 'Descrição',       type: 'html' },
    ];

    /* ---- Storage ---- */

    function getItems() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
        } catch (e) { return []; }
    }

    function saveItems(items) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
        } catch (e) {}
    }

    function hasItem(id) {
        return getItems().some(function (i) { return String(i.id) === String(id); });
    }

    function addItem(item) {
        let items = getItems();
        if (items.length >= MAX_ITEMS) { return false; }
        if (hasItem(item.id)) { return false; }
        items.push(item);
        saveItems(items);
        return true;
    }

    function removeItem(id) {
        saveItems(getItems().filter(function (i) { return String(i.id) !== String(id); }));
    }

    /* ---- Floating bar ---- */

    function getBar() {
        let bar = document.getElementById(BAR_ID);
        if (!bar) {
            bar = document.createElement('div');
            bar.id = BAR_ID;
            bar.className = 'awa-compare-bar';
            bar.setAttribute('aria-live', 'polite');
            bar.innerHTML =
                '<div class="awa-compare-bar__inner">' +
                '<span class="awa-compare-bar__label">Comparar:</span>' +
                '<div class="awa-compare-bar__slots" id="awa-compare-slots"></div>' +
                '<button type="button" class="awa-compare-bar__cta" id="awa-compare-go" disabled>' +
                'Comparar agora</button>' +
                '<button type="button" class="awa-compare-bar__clear" id="awa-compare-clear" ' +
                'aria-label="Limpar comparação">&#x2715;</button>' +
                '</div>';
            document.body.appendChild(bar);

            document.getElementById('awa-compare-go').addEventListener('click', openModal);
            document.getElementById('awa-compare-clear').addEventListener('click', function () {
                saveItems([]);
                renderBar();
                refreshAllButtons();
            });
        }
        return bar;
    }

    function renderBar() {
        let items = getItems();
        let bar = getBar();
        let slots = document.getElementById('awa-compare-slots');
        let goBtn = document.getElementById('awa-compare-go');

        if (items.length === 0) {
            bar.classList.remove('is-visible');
            return;
        }

        bar.classList.add('is-visible');
        goBtn.disabled = items.length < 2;

        slots.innerHTML = items.map(function (item) {
            return '<div class="awa-compare-bar__slot" data-id="' + escHtml(String(item.id)) + '">' +
                '<img src="' + escHtml(item.img) + '" alt="" loading="lazy"/>' +
                '<span class="awa-compare-bar__slot-name">' + escHtml(item.name) + '</span>' +
                '<button type="button" class="awa-compare-bar__slot-remove" ' +
                'data-compare-remove="' + escHtml(String(item.id)) + '" ' +
                'aria-label="Remover ' + escHtml(item.name) + ' da comparação">&#x2715;</button>' +
                '</div>';
        }).join('');

        /* Placeholder slots */
        for (let i = items.length; i < MAX_ITEMS; i++) {
            slots.innerHTML += '<div class="awa-compare-bar__slot awa-compare-bar__slot--empty">' +
                '<span>+</span>' +
                '</div>';
        }

        /* Remove delegated click */
        slots.querySelectorAll('[data-compare-remove]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                removeItem(btn.dataset.compareRemove);
                renderBar();
                refreshAllButtons();
            });
        });
    }

    /* ---- PLP buttons ---- */

    function refreshAllButtons() {
        let items = getItems();
        let count = items.length;
        document.querySelectorAll('[data-compare-add]').forEach(function (btn) {
            let id = String(btn.dataset.compareAdd);
            let inList = hasItem(id);
            btn.classList.toggle('is-active', inList);
            btn.setAttribute('aria-pressed', String(inList));
            btn.disabled = !inList && count >= MAX_ITEMS;
            btn.title = inList ? 'Remover da comparação' :
                (count >= MAX_ITEMS ? 'Máximo de ' + MAX_ITEMS + ' produtos' : 'Adicionar à comparação');
        });
    }

    function bindPlpButton(btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            let id   = String(btn.dataset.compareAdd);
            let name = btn.dataset.compareName  || '';
            let sku  = btn.dataset.compareSku   || '';
            let img  = btn.dataset.compareImg   || '';
            let url  = btn.dataset.compareUrl   || '';

            if (hasItem(id)) {
                removeItem(id);
            } else {
                let ok = addItem({ id: id, sku: sku, name: name, img: img, url: url });
                if (!ok) { return; }
            }
            renderBar();
            refreshAllButtons();
        });
    }

    /* ---- REST Product fetch ---- */

    function fetchProduct(sku) {
        let apiUrl = urlBuilder.build('rest/V1/products/' + encodeURIComponent(sku) +
            '?fields=sku,name,price,special_price,weight,custom_attributes,media_gallery_entries');
        return $.ajax({ url: apiUrl, type: 'GET', dataType: 'json' });
    }

    function attrVal(product, code) {
        if (product[code] !== undefined && product[code] !== null) {
            return String(product[code]);
        }
        if (product.custom_attributes) {
            let found = null;
            product.custom_attributes.forEach(function (a) {
                if (a.attribute_code === code) { found = a.value; }
            });
            if (found !== null) { return String(found); }
        }
        return null;
    }

    /* ---- Modal ---- */

    function openModal() {
        let items = getItems();
        if (items.length < 2) { return; }

        let existing = document.getElementById(MODAL_ID);
        if (existing) { existing.parentNode.removeChild(existing); }

        let modal = document.createElement('div');
        modal.id = MODAL_ID;
        modal.className = 'awa-compare-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', 'Comparação de produtos');
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML =
            '<div class="awa-compare-modal__backdrop" data-compare-close></div>' +
            '<div class="awa-compare-modal__dialog">' +
            '<header class="awa-compare-modal__header">' +
            '<h2 class="awa-compare-modal__title">Comparar Produtos</h2>' +
            '<button type="button" class="awa-compare-modal__close" data-compare-close ' +
            'aria-label="Fechar">&#x2715;</button>' +
            '</header>' +
            '<div class="awa-compare-modal__body">' +
            '<div class="awa-compare-modal__loading">' +
            '<svg class="awa-compare-spinner" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10"/></svg>' +
            'Carregando...</div>' +
            '</div>' +
            '</div>';
        document.body.appendChild(modal);

        /* Close handlers */
        modal.querySelectorAll('[data-compare-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });
        document.addEventListener('keydown', handleModalKey);

        /* Show */
        requestAnimationFrame(function () {
            modal.setAttribute('aria-hidden', 'false');
            modal.classList.add('is-open');
        });

        /* Load data */
        let promises = items.map(function (item) { return fetchProduct(item.sku); });
        Promise.all(promises.map(function (p) { return p.catch(function () { return null; }); }))
            .then(function (products) { renderCompareTable(modal, items, products); });
    }

    function closeModal() {
        let modal = document.getElementById(MODAL_ID);
        if (!modal) { return; }
        modal.setAttribute('aria-hidden', 'true');
        modal.classList.remove('is-open');
        document.removeEventListener('keydown', handleModalKey);
        setTimeout(function () {
            if (modal.parentNode) { modal.parentNode.removeChild(modal); }
        }, 250);
    }

    function handleModalKey(e) {
        if (e.key === 'Escape') { closeModal(); }
    }

    function renderCompareTable(modal, items, products) {
        let cols = items.length;
        let body = modal.querySelector('.awa-compare-modal__body');

        /* Header row: product images + names */
        let headerCells = items.map(function (item, i) {
            let p = products[i];
            let img = p && p.media_gallery_entries && p.media_gallery_entries[0]
                ? '/pub/media/catalog/product' + p.media_gallery_entries[0].file
                : item.img;
            return '<th class="awa-ct-prod">' +
                '<a href="' + escHtml(item.url) + '">' +
                '<img src="' + escHtml(img) + '" alt="' + escHtml(item.name) + '" loading="lazy"/>' +
                '<span class="awa-ct-prod__name">' + escHtml(item.name) + '</span>' +
                '</a>' +
                '</th>';
        }).join('');

        /* Attribute rows */
        let attrRows = COMPARE_ATTRS.map(function (attr) {
            let cells = products.map(function (p) {
                let val = p ? attrVal(p, attr.code) : null;
                if (val === null || val === '' || val === '0' || val === 'null') {
                    return '<td class="awa-ct-empty">—</td>';
                }
                if (attr.type === 'price') {
                    let num = parseFloat(val);
                    let formatted = 'R$ ' + num.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                    return '<td class="awa-ct-price">' + formatted + '</td>';
                }
                if (attr.type === 'html') {
                    return '<td class="awa-ct-html">' + val.replace(/<[^>]+>/g, '').substring(0, 120) + '…</td>';
                }
                return '<td>' + escHtml(val) + '</td>';
            }).join('');

            return '<tr><th class="awa-ct-attr">' + escHtml(attr.label) + '</th>' + cells + '</tr>';
        }).join('');

        body.innerHTML =
            '<div class="awa-compare-table-wrap">' +
            '<table class="awa-compare-table">' +
            '<thead><tr>' +
            '<th class="awa-ct-attr-head"></th>' +
            headerCells +
            '</tr></thead>' +
            '<tbody>' + attrRows + '</tbody>' +
            '</table>' +
            '</div>';
    }

    /* ---- Helpers ---- */

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ---- Init ---- */

    function init() {
        if (isInitialized) {
            return;
        }

        if (!document.body) {
            document.addEventListener('DOMContentLoaded', init, { once: true });
            return;
        }

        isInitialized = true;

        if (shouldDisableCompareUi()) {
            removeCompareUiArtifacts();
            return;
        }

        /* Bind all existing compare buttons */
        document.querySelectorAll('[data-compare-add]').forEach(function (btn) {
            if (!btn._awaCompareBound) {
                btn._awaCompareBound = true;
                bindPlpButton(btn);
            }
        });

        /* MutationObserver for AJAX-loaded products (infinite scroll etc.) */
        let compareRoot = document.querySelector(
            '.column.main, .products-grid, .product-items, #maincontent'
        );

        if (compareRoot) {
            let observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    m.addedNodes.forEach(function (node) {
                        if (node.nodeType !== 1) { return; }
                        if (node.matches && node.matches('[data-compare-add]')) {
                            if (!node._awaCompareBound) {
                                node._awaCompareBound = true;
                                bindPlpButton(node);
                            }
                        }
                        node.querySelectorAll('[data-compare-add]').forEach(function (btn) {
                            if (!btn._awaCompareBound) {
                                btn._awaCompareBound = true;
                                bindPlpButton(btn);
                            }
                        });
                    });
                });
            });
            observer.observe(compareRoot, { childList: true, subtree: true });
            // Disconnect on page unload to prevent memory leak (B5)
            window.addEventListener('pagehide', function () { observer.disconnect(); }, { once: true });
        }

        /* Render bar for items already in localStorage */
        renderBar();
        refreshAllButtons();
    }

    function initOnDomReady() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init, { once: true });
        } else {
            init();
        }
    }

    let api = {
        add: addItem,
        remove: removeItem,
        open: openModal,
        init: init
    };

    function initializer() {
        initOnDomReady();
        return api;
    }

    initializer.add = addItem;
    initializer.remove = removeItem;
    initializer.open = openModal;
    initializer.init = initOnDomReady;

    initOnDomReady();

    return initializer;
});
