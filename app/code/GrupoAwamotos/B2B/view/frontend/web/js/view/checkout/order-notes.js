/**
 * Order Notes Component for Checkout
 * P2-4.2: Campo para observações do pedido
 *
 * @module GrupoAwamotos_B2B/js/view/checkout/order-notes
 */
define([
    'uiComponent',
    'ko',
    'Magento_Customer/js/model/customer',
    'GrupoAwamotos_B2B/js/model/checkout/order-notes-storage',
    'GrupoAwamotos_B2B/js/model/checkout/b2b-config',
    'mage/translate'
], function (Component, ko, customer, orderNotesStorage, b2bConfig, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'GrupoAwamotos_B2B/checkout/order-notes',
            orderNotes: '',
            isVisible: true
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();

            this.orderNotes = orderNotesStorage.orderNotesObservable;
            this.notesLength = ko.pureComputed(function () {
                return (this.orderNotes() || '').length;
            }, this);

            this.isVisible = ko.computed(function () {
                return customer.isLoggedIn() && b2bConfig.isEnabled(b2bConfig.getSection('orderNotes').enabled);
            }, this);

            return this;
        },

        /**
         * Get component label
         *
         * @returns {string}
         */
        getLabel: function () {
            return $t('Observações do pedido');
        },

        /**
         * @returns {string}
         */
        getOptionalLabel: function () {
            return $t('Opcional');
        },

        /**
         * Get placeholder text
         *
         * @returns {string}
         */
        getPlaceholder: function () {
            return $t('Ex.: referência de doca, contato no recebimento, janela de entrega ou instruções fiscais.');
        },

        /**
         * @returns {string}
         */
        getHelperText: function () {
            return $t('Use este campo para orientar entrega, conferência ou identificação interna do pedido. Evite incluir dados sensíveis.');
        },

        /**
         * @returns {string}
         */
        getFootnoteText: function () {
            return $t('Essas observações acompanham o pedido para análise comercial e operacional.');
        },

        /**
         * Get max length
         *
         * @returns {number}
         */
        getMaxLength: function () {
            return 500;
        },

        /**
         * @returns {string}
         */
        getFieldDescriptionId: function () {
            return 'b2b-order-notes-description';
        },

        /**
         * @returns {string}
         */
        getCounterId: function () {
            return 'b2b-order-notes-counter';
        },

        /**
         * @returns {string}
         */
        getAriaDescribedBy: function () {
            return this.getFieldDescriptionId() + ' ' + this.getCounterId();
        },

        /**
         * @returns {string}
         */
        getCounterText: function () {
            return $t('%1/%2 caracteres')
                .replace('%1', this.notesLength())
                .replace('%2', this.getMaxLength());
        }
    });
});