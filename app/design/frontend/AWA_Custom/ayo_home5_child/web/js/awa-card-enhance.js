/**
 * AWA Motos — awa-card-enhance.js
 *
 * Re-inicializa awa-qty-control em slides clonados por carrosseis (scroll-snap/Swiper).
 * Carouseis clonam nos do DOM — o guard data-awa-qty-bound no clone impede
 * a re-binding automatica do Magento widget framework, deixando os botoes +/- mudos.
 *
 * Fix: MutationObserver nos containers de carousel que:
 *   1. Detecta novos slides adicionados (clones incluidos)
 *   2. Remove o guard do clone e re-inicializa via data-mage-init
 *   3. Desconecta tudo no pagehide para evitar memory leak
 */
define(['jquery', 'mage/apply/main'], function ($, mageApply) {
    'use strict';

    let CAROUSEL_SELECTORS = [
        '.awa-carousel__track',
        '.swiper-wrapper',
        '.slick-list'
    ].join(', ');

    let QTY_COMPONENT_SELECTOR = '[data-mage-init*="awa-qty-control"]';

    function reinitQtyControls(container) {
        container.querySelectorAll(QTY_COMPONENT_SELECTOR).forEach(function (el) {
            if (el.getAttribute('data-awa-qty-bound') === 'true') {
                el.removeAttribute('data-awa-qty-bound');
            }
        });
        if (typeof mageApply === 'function') {
            try { mageApply(); } catch (e) {}
        }
    }

    function init() {
        let carouselEls = document.querySelectorAll(CAROUSEL_SELECTORS);
        if (!carouselEls.length) { return; }

        let observers = [];

        carouselEls.forEach(function (el) {
            let obs = new MutationObserver(function (mutations) {
                let hasAdded = mutations.some(function (m) { return m.addedNodes.length > 0; });
                if (!hasAdded) { return; }
                if (window.requestIdleCallback) {
                    window.requestIdleCallback(function () { reinitQtyControls(el); }, { timeout: 300 });
                } else {
                    setTimeout(function () { reinitQtyControls(el); }, 50);
                }
            });
            obs.observe(el, { childList: true });
            observers.push(obs);
        });

        window.addEventListener('pagehide', function () {
            observers.forEach(function (o) { o.disconnect(); });
        }, { once: true });
    }

    $(document).ready(function () {
        if (window.requestIdleCallback) {
            window.requestIdleCallback(init, { timeout: 2000 });
        } else {
            setTimeout(init, 500);
        }
    });
});
