define([], function () {
    'use strict';

    let BLOCKED_GRID_PROPS = [
        'grid-template-columns',
        'grid-template-rows',
        'grid-template-areas',
        'grid-template',
        'gap',
        'grid-column'
    ];
    let HEADER_ROW_SELECTOR = '.header .awa-main-header__inner[data-awa-header-row], .header .wp-header[data-awa-header-row]';
    let HEADER_TOP_SEARCH_SELECTOR = '.header .awa-main-header__inner[data-awa-header-row] > .top-search, .header .wp-header[data-awa-header-row] > .top-search';
    let HEADER_MINICART_SELECTOR = '.header [data-awa-header-minicart-shell="true"], .header .awa-header-minicart[data-awa-header-cart="true"]';

    function interceptSetProperty(element, overrides, blocked) {
        let original = element.style.setProperty.bind(element.style);

        element.style.setProperty = function (property, value, priority) {
            if (overrides[property]) {
                original(property, overrides[property].value, overrides[property].priority);
                return;
            }

            if (blocked.indexOf(property) !== -1) {
                return;
            }

            original(property, value, priority);
        };

        return original;
    }

    function blockSetAttribute(element) {
        let original = element.setAttribute.bind(element);

        element.setAttribute = function (name, value) {
            if (name === 'style') {
                return;
            }

            original(name, value);
        };
    }

    function applyInitial(originalSetProperty, styles) {
        Object.keys(styles).forEach(function (property) {
            originalSetProperty(property, styles[property].value, styles[property].priority);
        });
    }

    function install() {
        if (!document.body.classList.contains('checkout-cart-index')) {
            return true;
        }

        if (window.innerWidth > 767) {
            return true;
        }

        let wrapper = document.querySelector(HEADER_ROW_SELECTOR);
        let topSearch = document.querySelector(HEADER_TOP_SEARCH_SELECTOR);
        let miniCart = document.querySelector(HEADER_MINICART_SELECTOR);

        if (!wrapper || !topSearch) {
            return false;
        }

        if (!wrapper.hasAttribute('data-awa-cart-header-patched')) {
            wrapper.removeAttribute('style');

            let wrapperOverrides = {
                'display': {value: 'grid', priority: 'important'},
                'grid-template-columns': {value: 'minmax(0, 1fr)', priority: 'important'},
                'grid-template-areas': {value: '"brand" "search"', priority: 'important'},
                'row-gap': {value: '12px', priority: 'important'},
                'justify-items': {value: 'center', priority: 'important'},
                'padding-inline': {value: '18px', priority: 'important'}
            };

            let originalWrapperSet = interceptSetProperty(wrapper, wrapperOverrides, BLOCKED_GRID_PROPS);

            blockSetAttribute(wrapper);
            applyInitial(originalWrapperSet, {
                'display': {value: 'grid', priority: 'important'},
                'grid-template-columns': {value: 'minmax(0, 1fr)', priority: 'important'},
                'grid-template-areas': {value: '"brand" "search"', priority: 'important'},
                'align-items': {value: 'center', priority: 'important'},
                'justify-items': {value: 'center', priority: 'important'},
                'row-gap': {value: '12px', priority: 'important'},
                'padding-inline': {value: '18px', priority: 'important'}
            });

            wrapper.setAttribute('data-awa-cart-header-patched', 'true');
        }

        if (!topSearch.hasAttribute('data-awa-cart-top-search-patched')) {
            topSearch.removeAttribute('style');

            let topSearchOverrides = {
                'display': {value: 'block', priority: 'important'},
                'grid-column': {value: '1 / -1', priority: 'important'},
                'grid-area': {value: 'search', priority: 'important'},
                'max-width': {value: '360px', priority: 'important'},
                'width': {value: '100%', priority: 'important'},
                'min-width': {value: '0', priority: 'important'},
                'margin': {value: '0 auto', priority: 'important'}
            };

            let originalTopSearchSet = interceptSetProperty(topSearch, topSearchOverrides, BLOCKED_GRID_PROPS);

            blockSetAttribute(topSearch);
            applyInitial(originalTopSearchSet, {
                'display': {value: 'block', priority: 'important'},
                'grid-area': {value: 'search', priority: 'important'},
                'grid-column': {value: '1 / -1', priority: 'important'},
                'width': {value: '100%', priority: 'important'},
                'max-width': {value: '360px', priority: 'important'},
                'min-width': {value: '0', priority: 'important'},
                'margin': {value: '0 auto', priority: 'important'},
                'position': {value: 'relative', priority: 'important'}
            });

            topSearch.setAttribute('data-awa-cart-top-search-patched', 'true');
        }

        if (miniCart && !miniCart.hasAttribute('data-awa-cart-minicart-patched')) {
            miniCart.removeAttribute('style');

            let miniCartOverrides = {
                'display': {value: 'none', priority: 'important'},
                'width': {value: '0', priority: 'important'},
                'min-width': {value: '0', priority: 'important'},
                'max-width': {value: '0', priority: 'important'},
                'margin': {value: '0', priority: 'important'}
            };

            let originalMiniCartSet = interceptSetProperty(miniCart, miniCartOverrides, BLOCKED_GRID_PROPS);

            blockSetAttribute(miniCart);
            applyInitial(originalMiniCartSet, {
                'display': {value: 'none', priority: 'important'},
                'width': {value: '0', priority: 'important'},
                'min-width': {value: '0', priority: 'important'},
                'max-width': {value: '0', priority: 'important'},
                'height': {value: '0', priority: 'important'},
                'min-height': {value: '0', priority: 'important'},
                'overflow': {value: 'hidden', priority: 'important'},
                'margin': {value: '0', priority: 'important'}
            });

            miniCart.setAttribute('data-awa-cart-minicart-patched', 'true');
        }

        return true;
    }

    return function () {
        if (!document.body.classList.contains('checkout-cart-index')) {
            return;
        }

        let attempts = 0;
        const MAX_ATTEMPTS = 24;

        function tick() {
            attempts += 1;

            if (install() || attempts >= MAX_ATTEMPTS) {
                return;
            }

            window.setTimeout(tick, 100);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', tick, {once: true});
            return;
        }

        tick();
    };
});
