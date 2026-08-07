/**
 * Storefront stub for Magento_Bundle/js/validation mixin.
 *
 * Bundle option validators are only needed on bundle PDPs. The core mixin is
 * registered globally on mage/validation and otherwise joins every form boot
 * (newsletter, search, login-to-cart) on the home/PLP storefront.
 *
 * @return {Function}
 */
define([], function () {
    'use strict';

    return function (target) {
        return target;
    };
});
