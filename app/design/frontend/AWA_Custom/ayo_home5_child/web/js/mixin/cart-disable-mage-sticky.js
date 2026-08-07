/**
 * No-op mage/sticky no carrinho — CSS position:sticky é a fonte canônica.
 * Evita _sticky + top inline do widget Luma (bundle/theme-heavy).
 */
define(['mage/utils/wrapper'], function (wrapper) {
    'use strict';

    return function (stickyWidget) {
        if (!stickyWidget || !stickyWidget.prototype) {
            return stickyWidget;
        }

        stickyWidget.prototype._create = wrapper.wrap(
            stickyWidget.prototype._create,
            function (original) {
                if (document.body && document.body.classList.contains('checkout-cart-index')) {
                    return;
                }

                return original();
            }
        );

        stickyWidget.prototype._stick = wrapper.wrap(
            stickyWidget.prototype._stick,
            function (original) {
                if (document.body && document.body.classList.contains('checkout-cart-index')) {
                    return;
                }

                return original();
            }
        );

        return stickyWidget;
    };
});
