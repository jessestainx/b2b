define([
    'jquery',
    'ko',
    'mage/translate',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/action/redirect-on-success'
], function ($, ko, $t, Component, fullScreenLoader, additionalValidators, redirectOnSuccessAction) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'GrupoAwamotos_OfflinePayment/payment/acombinar'
        },

        isPlaceOrderInProgress: ko.observable(false),

        /**
         * @returns {string}
         */
        getCode: function () {
            return 'acombinar';
        },

        /**
         * @returns {boolean}
         */
        isActive: function () {
            return true;
        },

        /**
         * @returns {string}
         */
        getInstructions: function () {
            return window.checkoutConfig.payment.instructions ?
                window.checkoutConfig.payment.instructions[this.getCode()] :
                $t('O pagamento será combinado diretamente com nossa equipe.');
        },

        /**
         * @returns {string}
         */
        getTitle: function () {
            return $t('A Combinar');
        },

        /**
         * @returns {boolean}
         */
        isPlaceOrderActionAllowed: function () {
            return this._super() && !this.isPlaceOrderInProgress();
        },

        /**
         * Retorna deferred para o OPC aguardar conclusão (evita loader/botão travados).
         *
         * @param {Object|null} data
         * @param {Event|null} event
         * @returns {jQuery.Deferred|Promise}
         */
        placeOrder: function (data, event) {
            var self = this;
            var rejected = $.Deferred().reject().promise();

            if (event) {
                event.preventDefault();
            }

            if (!this.isPlaceOrderActionAllowed()) {
                return rejected;
            }

            if (!this.validate() || !additionalValidators.validate()) {
                return rejected;
            }

            this.isPlaceOrderActionAllowed(false);
            this.isPlaceOrderInProgress(true);
            fullScreenLoader.startLoader();

            var willRedirect = false;

            return this.getPlaceOrderDeferredObject()
                .done(function () {
                    self.afterPlaceOrder();

                    if (self.redirectAfterPlaceOrder) {
                        willRedirect = true;
                        redirectOnSuccessAction.execute();
                    }
                })
                .always(function () {
                    self.isPlaceOrderActionAllowed(true);
                    self.isPlaceOrderInProgress(false);

                    if (!willRedirect) {
                        fullScreenLoader.stopLoader();
                    }
                });
        }
    });
});
