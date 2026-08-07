/**
 * Billing Address Company Auto-fill Mixin
 * P2-4.1: Preenche company e vat_id da billing address com dados B2B
 *
 * @module GrupoAwamotos_B2B/js/model/checkout/company-autofill
 */
define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'mage/translate'
], function ($, wrapper, quote, $t) {
    'use strict';

    /**
     * Ensure an aria-live region exists for silent UX feedback.
     *
     * @returns {HTMLElement|null}
     */
    function ensureAutofillStatusRegion()
    {
        if (typeof document === 'undefined') {
            return null;
        }

        var region = document.getElementById('b2b-company-autofill-status');

        if (!region) {
            region = document.createElement('div');
            region.id = 'b2b-company-autofill-status';
            region.className = 'awa-sr-only b2b-autofill-status';
            region.setAttribute('role', 'status');
            region.setAttribute('aria-live', 'polite');
            region.setAttribute('aria-atomic', 'true');
            document.body.appendChild(region);
        }

        return region;
    }

    /**
     * Announces a small status message for screen readers.
     *
     * @param {string} message
     */
    function announceAutofill(message)
    {
        var region = ensureAutofillStatusRegion();

        if (!region) {
            return;
        }

        region.textContent = '';

        window.setTimeout(function () {
            region.textContent = message;
        }, 16);
    }

    return function (setBillingAddressAction) {
        if (typeof setBillingAddressAction !== 'function') {
            return setBillingAddressAction;
        }

        return wrapper.wrap(setBillingAddressAction, function (originalAction) {
            var args = Array.prototype.slice.call(arguments, 1);
            var companyData = (window.checkoutConfig || {}).b2bCompanyData || null;
            var hasAutofilledAnyField = false;

            if (companyData) {
                var billingAddress = quote.billingAddress();

                if (billingAddress) {
                    if (!billingAddress.company && companyData.company) {
                        billingAddress.company = companyData.company;
                        hasAutofilledAnyField = true;
                    }

                    if (!billingAddress.vatId && companyData.vatId) {
                        billingAddress.vatId = companyData.vatId;
                        hasAutofilledAnyField = true;
                    }

                    if (hasAutofilledAnyField) {
                        announceAutofill($t('Dados da empresa preenchidos automaticamente na finalização do pedido.'));
                    }
                }
            }

            return originalAction.apply(this, args);
        });
    };
});
