/**
 * Input Mask Widget for Brazilian documents (CNPJ, CPF, etc.)
 */
define([
    'jquery',
    'jquery-ui-modules/widget'
], function ($) {
    'use strict';

    $.widget('grupoawamotos.inputMask', {
        options: {
            mask: ''
        },

        /**
         * Widget initialization
         * @private
         */
        _create: function () {
            this._bindEvents();
            this._applyMask();
        },

        /**
         * Bind input events
         * @private
         */
        _bindEvents: function () {
            let self = this;

            this.element.on('input.inputMask keyup.inputMask', function () {
                self._applyMask();
            });

            this.element.on('focus.inputMask', function () {
                self._applyMask();
            });
        },

        /**
         * Apply mask to the input value
         * @private
         */
        _applyMask: function () {
            let value = this.element.val().replace(/\D/g, ''),
                mask = this.options.mask,
                maskedValue = '';

            if (!mask || !value) {
                return;
            }

            let valueIndex = 0;
            for (let i = 0; i < mask.length && valueIndex < value.length; i++) {
                if (mask[i] === '0' || mask[i] === '9') {
                    maskedValue += value[valueIndex];
                    valueIndex++;
                } else {
                    maskedValue += mask[i];
                    if (value[valueIndex] === mask[i]) {
                        valueIndex++;
                    }
                }
            }

            this.element.val(maskedValue);
        },

        /**
         * Get unmasked value
         * @returns {string}
         */
        getUnmaskedValue: function () {
            return this.element.val().replace(/\D/g, '');
        },

        /**
         * Destroy widget
         * @private
         */
        _destroy: function () {
            this.element.off('.inputMask');
        }
    });

    return $.grupoawamotos.inputMask;
});
