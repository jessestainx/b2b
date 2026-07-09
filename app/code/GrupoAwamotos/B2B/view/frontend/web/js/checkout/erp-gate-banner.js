/**
 * Limpa banners residuais do gate ERP desativado (cache/JS antigo).
 *
 * @module GrupoAwamotos_B2B/js/checkout/erp-gate-banner
 */
define(['jquery'], function ($) {
    'use strict';

    var GATE_BANNER_SELECTOR = '.awa-b2b-checkout-erp-gate[data-awa-component="b2b-erp-gate-banner"]';
    var PLACE_ORDER_SELECTOR = [
        '#opc-sidebar .action.primary.checkout',
        '.actions-toolbar .action.checkout',
        '.actions-toolbar .btn-placeorder',
        'button[data-role="review-save"]',
        '.payment-method-content .action.primary.checkout',
        '.checkout-methods-items .action.primary.checkout',
        '.cart-summary .action.primary.checkout'
    ].join(', ');

    function clearStaleGate()
    {
        if (window.__awaB2bErpGateCleaned) {
            return;
        }

        window.__awaB2bErpGateCleaned = true;
        if (window.__awaB2bErpGateObserver) {
            window.__awaB2bErpGateObserver.disconnect();
            window.__awaB2bErpGateObserver = null;
        }

        $(document.body).removeClass('awa-b2b-checkout-erp-blocked');
        $(GATE_BANNER_SELECTOR).remove();
        $(PLACE_ORDER_SELECTOR).prop('disabled', false).removeAttr('aria-disabled');

        if (window.checkoutConfig) {
            window.checkoutConfig.b2bCheckoutBlocked = false;
            delete window.checkoutConfig.b2bCheckoutBlockMessage;
        }
    }

    function isRelevantPage()
    {
        if (!document.body) {
            return false;
        }

        return document.body.classList.contains('checkout-cart-index') ||
            document.body.classList.contains('checkout-index-index') ||
            document.body.classList.contains('rokanthemes-onepagecheckout') ||
            document.body.classList.contains('onepagecheckout-index-index');
    }

    return function () {
        if (!isRelevantPage()) {
            return;
        }

        var runCleanup = function () {
            clearStaleGate();
        };

        if (window.requestIdleCallback) {
            window.requestIdleCallback(runCleanup, {timeout: 500});
        } else {
            window.requestAnimationFrame(runCleanup);
        }
    };
});
