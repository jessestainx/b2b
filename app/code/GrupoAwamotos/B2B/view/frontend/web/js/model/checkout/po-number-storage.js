/**
 * PO Number Storage Model
 * Stores PO number value for checkout payment submission
 *
 * @module GrupoAwamotos_B2B/js/model/checkout/po-number-storage
 */
define([
    'ko',
    'GrupoAwamotos_B2B/js/model/checkout/b2b-config',
    'mage/translate'
], function (ko, b2bConfig, $t) {
    'use strict';

    var poNumber = ko.observable('');
    var errorMessage = ko.observable('');
    var PO_PATTERN = /^[a-zA-Z0-9\s\-\/\.]+$/;
    var DEFAULT_MAX_LENGTH = 50;

    /**
     * @returns {{enabled: boolean, required: boolean, maxLength: number, errors: Object}}
     */
    function getPoConfig()
    {
        return b2bConfig.getSection('poNumber');
    }

    /**
     * @returns {number}
     */
    function getMaxLength()
    {
        var poConfig = getPoConfig();

        return poConfig.maxLength || DEFAULT_MAX_LENGTH;
    }

    return {
        /**
         * Get current PO number
         * @returns {string}
         */
        getPoNumber: function () {
            return poNumber();
        },

        /**
         * Set PO number
         * @param {string} value
         */
        setPoNumber: function (value) {
            poNumber(value);
        },

        /**
         * Observable for PO number
         * @returns {ko.observable}
         */
        poNumberObservable: poNumber,

        /**
         * Inline validation error for screen readers and field feedback
         * @returns {ko.observable}
         */
        errorMessageObservable: errorMessage,

        /**
         * @returns {number}
         */
        getMaxLength: getMaxLength,

        /**
         * Validate PO number against B2B checkout rules
         *
         * @returns {{valid: boolean, message: string}}
         */
        validate: function () {
            var value = (poNumber() || '').trim();
            var poConfig = getPoConfig();
            var errors = poConfig.errors || {};
            var maxLength = getMaxLength();

            if (!b2bConfig.isEnabled(poConfig.enabled)) {
                errorMessage('');

                return { valid: true, message: '' };
            }

            if (b2bConfig.isEnabled(poConfig.required) && !value) {
                var requiredMsg = errors.required || $t('Número de pedido é obrigatório.');

                errorMessage(requiredMsg);

                return { valid: false, message: requiredMsg };
            }

            if (!value) {
                errorMessage('');

                return { valid: true, message: '' };
            }

            if (value.length > maxLength) {
                var lengthMsg = errors.max_length ||
                    $t('Número de pedido não pode exceder %1 caracteres.').replace('%1', String(maxLength));

                errorMessage(lengthMsg);

                return { valid: false, message: lengthMsg };
            }

            if (!PO_PATTERN.test(value)) {
                var charsMsg = errors.invalid_chars ||
                    $t('Número de pedido contém caracteres inválidos.');

                errorMessage(charsMsg);

                return { valid: false, message: charsMsg };
            }

            errorMessage('');

            return { valid: true, message: '' };
        },

        /**
         * Clear PO number
         */
        clear: function () {
            poNumber('');
            errorMessage('');
        },

        /**
         * Check if PO number is set
         * @returns {boolean}
         */
        hasPoNumber: function () {
            return poNumber() !== '' && poNumber() !== null;
        }
    };
});
