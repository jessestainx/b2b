/**
 * Checkout A11y Enhancements — AWA Motos
 *
 * Loader removal is handled by Magento_Checkout/js/checkout-loader (theme override).
 *
 * select[name="billing_address_id"] has no accessible name
 * (vendor template uses <label> without for/id association).
 *
 * Only runs on OPC page (body.rokanthemes-onepagecheckout).
 */
define([], function () {
    'use strict';

    /**
     * Add aria-label to billing address select once KO renders it.
     * The vendor template (billing-address/list.html) has a <label> with
     * text "Endereço de Cobrança" but no for/id association with the select.
     */
    function fixBillingAddressSelectA11y() {
        let select = document.querySelector('select[name="billing_address_id"]');
        if (select && !select.getAttribute('aria-label')) {
            select.setAttribute('aria-label', 'Endereço de cobrança');
            return;
        }
        // KO renders this select conditionally (only when >1 address option).
        // Use MutationObserver to catch it when it appears.
        let observer = new MutationObserver(function (mutations, obs) {
            let sel = document.querySelector('select[name="billing_address_id"]');
            if (sel && !sel.getAttribute('aria-label')) {
                sel.setAttribute('aria-label', 'Endereço de cobrança');
                obs.disconnect();
            }
        });
        var observeRoot = document.getElementById('checkout') ||
            document.querySelector('.checkout-payment-method, .opc-wrapper') ||
            document.body;

        observer.observe(observeRoot, { childList: true, subtree: true });
        window.setTimeout(function () {
            observer.disconnect();
        }, 15000);
    }

    return function () {
        if (!document.body.classList.contains('rokanthemes-onepagecheckout')) {
            return;
        }

        fixBillingAddressSelectA11y();
    };
});
