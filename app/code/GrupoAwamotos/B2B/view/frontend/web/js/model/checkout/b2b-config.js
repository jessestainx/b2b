/**
 * Normaliza window.checkoutConfig.b2bCheckout (evita arrays de merge_recursive).
 *
 * @module GrupoAwamotos_B2B/js/model/checkout/b2b-config
 */
define([], function () {
    'use strict';

    /**
     * @param {*} value
     * @returns {*}
     */
    function normalizeValue(value)
    {
        if (Array.isArray(value)) {
            return value.length ? value[value.length - 1] : null;
        }

        return value;
    }

    /**
     * @returns {Object}
     */
    function getCheckoutConfig()
    {
        return window.checkoutConfig || {};
    }

    /**
     * @returns {Object}
     */
    function getB2bCheckout()
    {
        return getCheckoutConfig().b2bCheckout || {};
    }

    /**
     * @param {string} section
     * @returns {Object}
     */
    function getSection(section)
    {
        var block = getB2bCheckout()[section] || {};
        var normalized = {};

        Object.keys(block).forEach(function (key) {
            normalized[key] = normalizeValue(block[key]);
        });

        return normalized;
    }

    /**
     * @param {*} value
     * @returns {boolean}
     */
    function isEnabled(value)
    {
        var normalized = normalizeValue(value);

        return normalized === true || normalized === 1;
    }

    return {
        normalizeValue: normalizeValue,
        getCheckoutConfig: getCheckoutConfig,
        getB2bCheckout: getB2bCheckout,
        getSection: getSection,
        isEnabled: isEnabled
    };
});
