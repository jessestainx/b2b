/**
 * Attach B2B checkout extension attributes to place-order payloads.
 * Hardening: prevent concurrent submissions, validate PO, inline errors near CTA.
 */
define([
    'jquery',
    'mage/utils/wrapper',
    'mage/translate',
    'GrupoAwamotos_B2B/js/model/checkout/po-number-storage',
    'GrupoAwamotos_B2B/js/model/checkout/order-notes-storage'
], function ($, wrapper, $t, poNumberStorage, orderNotesStorage) {
    'use strict';

    var placeOrderInProgress = false;
    var INLINE_ERROR_SELECTOR = '.awa-b2b-place-order-error, #awa-place-order-inline-error';
    var INLINE_ERROR_ROLE = 'b2b-place-order-error';

    var PLACE_ORDER_SELECTOR = [
        '#opc-sidebar .action.primary.checkout',
        '.actions-toolbar .action.checkout',
        '.actions-toolbar .btn-placeorder',
        'button[data-role="review-save"]',
        '.payment-method-content .action.primary.checkout'
    ].join(', ');

    var TOOLBAR_SELECTOR = [
        '#opc-sidebar .actions-toolbar',
        '.opc-sidebar .actions-toolbar',
        '.checkout-methods-items'
    ].join(', ');

    /**
     * @param {boolean} busy
     */
    function setPlaceOrderBusy(busy)
    {
        $(PLACE_ORDER_SELECTOR).each(function () {
            var $btn = $(this);

            if (busy) {
                $btn.attr('aria-busy', 'true');
            } else {
                $btn.removeAttr('aria-busy');
            }
        });
    }

    /**
     * @returns {jQuery}
     */
    function findToolbar()
    {
        var $toolbar = $(TOOLBAR_SELECTOR).filter(function () {
            return $(this).find(PLACE_ORDER_SELECTOR).length > 0;
        }).first();

        if ($toolbar.length) {
            return $toolbar;
        }

        return $(TOOLBAR_SELECTOR).first();
    }

    /**
     * @returns {jQuery}
     */
    function ensureInlineErrorRegion()
    {
        var $toolbar = findToolbar();

        if (!$toolbar.length) {
            return $();
        }

        var $existing = $('#awa-place-order-inline-error')
            .add($toolbar.siblings(INLINE_ERROR_SELECTOR))
            .add($toolbar.children(INLINE_ERROR_SELECTOR));

        if ($existing.length) {
            return $existing.first();
        }

        var $region = $(
            '<div id="awa-place-order-inline-error" class="awa-b2b-place-order-error message message-error error" ' +
            'role="alert" aria-live="assertive" data-role="' + INLINE_ERROR_ROLE + '" hidden></div>'
        );

        $toolbar.before($region);

        return $region;
    }

    /**
     * @param {string} message
     */
    function showInlineError(message)
    {
        var text = $.trim(message || '');

        if (!text) {
            return;
        }

        var $region = ensureInlineErrorRegion();

        if (!$region.length) {
            return;
        }

        $region
            .removeAttr('hidden')
            .html('<div>' + $('<span/>').text(text).html() + '</div>');

        if ($region[0] && typeof $region[0].scrollIntoView === 'function') {
            $region[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function clearInlineError()
    {
        $(INLINE_ERROR_SELECTOR).attr('hidden', 'hidden').empty();
    }

    /**
     * @param {Object} paymentData
     * @returns {Object}
     */
    function ensureExtensionAttributes(paymentData)
    {
        if (!paymentData || typeof paymentData !== 'object') {
            return paymentData;
        }

        if (!paymentData.extension_attributes || typeof paymentData.extension_attributes !== 'object') {
            paymentData.extension_attributes = {};
        }

        return paymentData;
    }

    /**
     * Resolve payment data for both standard checkout and custom wrappers.
     *
     * @param {Array} args
     * @returns {Object|null}
     */
    function resolvePaymentData(args)
    {
        if (args[0] && typeof args[0] === 'object' && !args[0].hasMessages) {
            return ensureExtensionAttributes(args[0]);
        }

        if (args[1] && typeof args[1] === 'object' && !args[1].hasMessages) {
            return ensureExtensionAttributes(args[1]);
        }

        return null;
    }

    /**
     * @param {Object|null} messageContainer
     * @param {string} message
     */
    function pushErrorMessage(messageContainer, message)
    {
        showInlineError(message);

        if (messageContainer && typeof messageContainer.addErrorMessage === 'function') {
            messageContainer.addErrorMessage({
                message: message
            });
        }
    }

    /**
     * @param {*} response
     * @returns {string}
     */
    function extractErrorMessage(response)
    {
        var fallback = $t('Não foi possível finalizar o pedido. Revise os dados e tente novamente.');

        if (!response) {
            return fallback;
        }

        try {
            var parsed = JSON.parse(response.responseText || '{}');

            if (parsed && parsed.message) {
                return parsed.message;
            }
        } catch (e) {
            // fall through
        }

        return fallback;
    }

    /**
     * @param {*} result
     * @param {Function} done
     */
    function bindPlaceOrderCompletion(result, done)
    {
        if (result && typeof result.always === 'function') {
            result.always(done);
        } else if (result && typeof result.finally === 'function') {
            result.finally(done);
        } else {
            done();
        }
    }

    /**
     * @param {*} result
     */
    function bindPlaceOrderFailure(result)
    {
        if (!result || typeof result.fail !== 'function') {
            return;
        }

        result.fail(function (response) {
            showInlineError(extractErrorMessage(response));
        });
    }

    return function (placeOrderAction) {
        if (typeof placeOrderAction !== 'function') {
            return placeOrderAction;
        }

        return wrapper.wrap(placeOrderAction, function (originalAction) {
            var args = Array.prototype.slice.call(arguments, 1);
            var paymentData = resolvePaymentData(args);
            var messageContainer = args[1] && args[1].hasMessages ? args[1] : args[0];
            var poNumber = (poNumberStorage.getPoNumber() || '').trim();
            var orderNotes = (orderNotesStorage.getOrderNotes() || '').trim();
            var poValidation = poNumberStorage.validate();

            if (placeOrderInProgress) {
                return $.Deferred().reject().promise();
            }

            if (!poValidation.valid) {
                pushErrorMessage(messageContainer, poValidation.message);

                return $.Deferred().reject().promise();
            }

            if (paymentData) {
                if (poNumber) {
                    paymentData.extension_attributes.b2b_po_number = poNumber;
                }

                if (orderNotes) {
                    paymentData.extension_attributes.b2b_order_notes = orderNotes;
                }
            }

            clearInlineError();
            placeOrderInProgress = true;
            setPlaceOrderBusy(true);

            var result = originalAction.apply(this, args);

            bindPlaceOrderFailure(result);
            bindPlaceOrderCompletion(result, function () {
                placeOrderInProgress = false;
                setPlaceOrderBusy(false);
            });

            return result;
        });
    };
});
