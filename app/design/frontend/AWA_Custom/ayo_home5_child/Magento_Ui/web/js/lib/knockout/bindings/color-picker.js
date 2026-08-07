/**
 * Storefront stub for Magento_Ui colorPicker KO binding.
 *
 * Admin-oriented spectrum/tinycolor must not join the customer-data/minicart
 * Knockout bootstrap on the storefront (home, PLP, PDP). No color pickers
 * exist in theme templates; real binding remains available in adminhtml.
 */
define([
    'ko',
    'Magento_Ui/js/lib/knockout/template/renderer'
], function (ko, renderer) {
    'use strict';

    ko.bindingHandlers.colorPicker = {
        init: function () {
            return { controlsDescendantBindings: false };
        },
        update: function () {}
    };

    renderer.addAttribute('colorPicker');

    return ko.bindingHandlers.colorPicker;
});
