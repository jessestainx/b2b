/**
 * Inicializador de pagina (x-magento-init) que renderiza o codigo de barras do boleto
 * usando o renderizador ITF puro (sem biblioteca externa).
 */
define([
    'GrupoAwamotos_B2B/js/finance/itf-barcode'
], function (itfBarcode) {
    'use strict';

    return function (config, element) {
        var barcode = element.getAttribute('data-barcode');

        if (barcode) {
            itfBarcode.render(barcode, element);
        }
    };
});
