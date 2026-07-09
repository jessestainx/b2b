/**
 * Comportamento da pagina de impressao do boleto.
 * Remove onclick inline e usa binding modular via data-mage-init.
 */
define([
    'jquery'
], function ($) {
    'use strict';

    return function (config, element) {
        var $root = $(element);

        $root.off('click.b2bPrintPage', '[data-action="print-boleto"]');
        $root.on('click.b2bPrintPage', '[data-action="print-boleto"]', function (event) {
            event.preventDefault();
            window.print();
        });
    };
});
