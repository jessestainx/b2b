define([
    'jquery',
    'mage/cookies',
    'Magento_Ui/js/modal/alert'
], function ($, _cookies, alertModal) {
    'use strict';

    function showAlert(message)
    {
        alertModal({
            title: 'B2B',
            content: message
        });
    }

    function getFormKey()
    {
        if (window.FORM_KEY) {
            return window.FORM_KEY;
        }

        if ($.mage && $.mage.cookies) {
            return $.mage.cookies.get('form_key') || '';
        }

        return '';
    }

    return function (config, element) {
        var $root = $(element);
        var messages = config.messages || {};
        var debounceTimer;

        $root.on('change', '[data-role="recurring-toggle"]', function () {
            var $options = $root.find('#recurring-options');
            if (this.checked) {
                $options.css('display', 'flex');
                return;
            }

            $options.hide();
        });

        $root.on('change', '.qty-input', function () {
            var $input = $(this);
            var itemId = parseInt($input.data('item-id'), 10) || 0;
            var updateUrl = $input.data('update-url') || '';
            var qty = parseInt($input.val(), 10) || 1;

            if (!itemId || !updateUrl) {
                return;
            }

            qty = Math.max(1, qty);
            $input.val(qty);

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                $.ajax({
                    url: updateUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        item_id: itemId,
                        qty: qty,
                        form_key: getFormKey()
                    },
                    showLoader: true
                }).done(function () {
                    window.location.reload();
                }).fail(function () {
                    showAlert(messages.qtyUpdateError || 'Erro ao atualizar quantidade.');
                });
            }, 450);
        });

        $root.on('click', '[data-confirm]', function (event) {
            var message = $(this).data('confirm');

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    };
});
