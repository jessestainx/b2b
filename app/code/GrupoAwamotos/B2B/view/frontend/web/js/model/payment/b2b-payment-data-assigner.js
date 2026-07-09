/**
 * Payment Information Mixin unificado
 * Adiciona PO Number e Order Notes aos atributos de extensão antes do envio
 *
 * @module GrupoAwamotos_B2B/js/model/payment/b2b-payment-data-assigner
 */
define([
    'jquery',
    'mage/utils/wrapper',
    'GrupoAwamotos_B2B/js/model/checkout/po-number-storage',
    'GrupoAwamotos_B2B/js/model/checkout/order-notes-storage'
], function ($, wrapper, poNumberStorage, orderNotesStorage) {
    'use strict';

    return function (paymentInformationHandler) {
        if (typeof paymentInformationHandler !== 'function') {
            return paymentInformationHandler;
        }

        return wrapper.wrap(paymentInformationHandler, function (originalAction) {
            var args = Array.prototype.slice.call(arguments, 1);
            var paymentData = args[1];

            if (paymentData && typeof paymentData === 'object') {
                var poNumber = (poNumberStorage.getPoNumber() || '').trim();
                var orderNotes = (orderNotesStorage.getOrderNotes() || '').trim();

                if (poNumber || orderNotes) {
                    if (!paymentData.extension_attributes || typeof paymentData.extension_attributes !== 'object') {
                        paymentData.extension_attributes = {};
                    }

                    if (poNumber) {
                        paymentData.extension_attributes.b2b_po_number = poNumber;
                    }
                    if (orderNotes) {
                        paymentData.extension_attributes.b2b_order_notes = orderNotes;
                    }
                }
            }

            return originalAction.apply(this, args);
        });
    };
});
