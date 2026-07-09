/**
 * Persiste frete na quote ao selecionar transportadora (OPC não salvava até place order).
 */
define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Customer/js/model/customer',
    'Rokanthemes_OnePageCheckout/js/action/set-shipping-information'
], function ($, quote, shippingService, selectShippingMethodAction, customer, setShippingInformationAction) {
    'use strict';

    var saveTimer = null;

    /**
     * @returns {void}
     */
    function ensureShippingMethodFromRates() {
        var shippingMethod = quote.shippingMethod();
        var rates;
        var validRates;

        if (shippingMethod && shippingMethod.carrier_code && shippingMethod.method_code) {
            return;
        }

        rates = shippingService.getShippingRates()();
        validRates = Array.isArray(rates) ? rates.filter(function (rate) {
            return rate && rate.carrier_code && rate.method_code && !rate.error_message;
        }) : [];

        if (validRates.length) {
            selectShippingMethodAction(validRates[0]);
        }
    }

    return function (ShippingComponent) {
        return ShippingComponent.extend({
            /** @inheritdoc */
            validateShippingInformation: function () {
                ensureShippingMethodFromRates();

                return this._super();
            },

            /** @inheritdoc */
            selectShippingMethod: function (shippingMethod) {
                var result = this._super(shippingMethod);

                if (!customer.isLoggedIn() || !shippingMethod) {
                    return result;
                }

                window.clearTimeout(saveTimer);
                saveTimer = window.setTimeout(function () {
                    setShippingInformationAction().fail(function () {
                        // errorProcessor no processor default
                    });
                }, 600);

                return result;
            }
        });
    };
});
