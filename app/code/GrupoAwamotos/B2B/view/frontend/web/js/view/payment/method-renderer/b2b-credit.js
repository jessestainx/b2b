/**
 * B2B Credit Payment Method Renderer
 * Includes client-side credit validation against order grand total
 * P2-4.4: Loading states for place order
 */
define([
    'ko',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils',
    'Magento_Checkout/js/model/full-screen-loader'
], function (ko, Component, quote, priceUtils, fullScreenLoader) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'GrupoAwamotos_B2B/payment/b2b_credit'
        },

        selectedPaymentTerm: ko.observable(''),
        isPlaceOrderInProgress: ko.observable(false),

        getCode: function () {
            return 'b2b_credit';
        },

        isActive: function () {
            return true;
        },

        getMethodConfig: function () {
            var checkoutConfig = window.checkoutConfig || {};
            var paymentConfig = checkoutConfig.payment || {};

            return paymentConfig.b2b_credit || null;
        },

        getTitle: function () {
            var methodConfig = this.getMethodConfig();

            return methodConfig && methodConfig.title
                ? methodConfig.title
                : 'Crédito B2B (Faturamento)';
        },

        getCreditInfo: function () {
            var methodConfig = this.getMethodConfig();

            return methodConfig && methodConfig.credit_info
                ? methodConfig.credit_info
                : null;
        },

        /**
         * Get payment terms list from checkout config
         */
        getPaymentTerms: function () {
            var methodConfig = this.getMethodConfig();

            return methodConfig && methodConfig.payment_terms
                ? methodConfig.payment_terms
                : [];
        },

        /**
         * Whether multiple payment terms are available
         */
        hasMultipleTerms: function () {
            return this.getPaymentTerms().length > 1;
        },

        /**
         * Initialize: auto-select first term + credit validation observables
         */
        initialize: function () {
            this._super();
            var self = this;
            var terms = this.getPaymentTerms();

            if (terms.length === 1) {
                this.selectedPaymentTerm(terms[0].value);
            } else if (terms.length > 1 && !this.selectedPaymentTerm()) {
                this.selectedPaymentTerm(terms[0].value);
            }

            // Available credit from config (static at page load)
            var creditInfo = this.getCreditInfo();
            var availableCredit = creditInfo ? parseFloat(creditInfo.available) || 0 : 0;

            /**
             * Grand total reativo — atualiza quando carrinho muda
             */
            this.grandTotal = ko.computed(function () {
                var totals = quote.totals();

                return totals ? parseFloat(totals.grand_total) || 0 : 0;
            });

            /**
             * Crédito suficiente para o pedido?
             */
            this.isCreditSufficient = ko.computed(function () {
                return availableCredit >= self.grandTotal();
            });

            /**
             * Crédito restante após o pedido (pode ser negativo se insuficiente)
             */
            this.remainingCredit = ko.computed(function () {
                return Math.max(0, availableCredit - self.grandTotal());
            });

            /**
             * Crédito restante formatado como moeda
             */
            this.remainingCreditFormatted = ko.computed(function () {
                var format = (window.checkoutConfig || {}).priceFormat || {};

                return priceUtils.formatPrice(self.remainingCredit(), format);
            });

            /**
             * Valor que excede o crédito disponível
             */
            this.creditDeficit = ko.computed(function () {
                var deficit = self.grandTotal() - availableCredit;

                return deficit > 0 ? deficit : 0;
            });

            /**
             * Déficit formatado como moeda
             */
            this.creditDeficitFormatted = ko.computed(function () {
                var format = (window.checkoutConfig || {}).priceFormat || {};

                return priceUtils.formatPrice(self.creditDeficit(), format);
            });

            return this;
        },

        /**
         * Override isPlaceOrderActionAllowed to block when credit insufficient or in progress
         */
        isPlaceOrderActionAllowed: function () {
            return this._super() && this.isCreditSufficient() && !this.isPlaceOrderInProgress();
        },

        /**
         * Override placeOrder with loading state management
         */
        placeOrder: function (data, event) {
            var self = this;
            var allowed;
            var result;

            if (event) {
                event.preventDefault();
            }

            allowed = this.isPlaceOrderActionAllowed();

            if (!allowed) {
                return false;
            }

            this.isPlaceOrderInProgress(true);
            fullScreenLoader.startLoader();

            // Delegate to parent placeOrder — it returns a deferred/promise
            result = this._super(data, event);

            // Handle both jQuery deferred and native Promise
            if (result && typeof result.always === 'function') {
                result.always(function () {
                    self.isPlaceOrderInProgress(false);
                    fullScreenLoader.stopLoader();
                });
            } else if (result && typeof result.finally === 'function') {
                result.finally(function () {
                    self.isPlaceOrderInProgress(false);
                    fullScreenLoader.stopLoader();
                });
            } else {
                self.isPlaceOrderInProgress(false);
                fullScreenLoader.stopLoader();
            }

            return result;
        },

        /**
         * Override getData to include selected payment term
         */
        getData: function () {
            return {
                'method': this.item.method,
                'additional_data': {
                    'payment_term': this.selectedPaymentTerm()
                }
            };
        }
    });
});
