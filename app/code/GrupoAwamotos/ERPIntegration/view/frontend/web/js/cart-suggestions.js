define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'mage/cookies'
], function ($, customerData) {
    'use strict';

    return function (config, element) {
        var $root = $(element);
        let addBySkuUrl = config.addBySkuUrl || '';
        let reloadDelay = Number(config.reloadDelay || 1200);
        let mode = config.mode || 'main';

        if (!$root.length || !addBySkuUrl) {
            return;
        }

        function getFormKey()
        {
            return $.mage && $.mage.cookies ? $.mage.cookies.get('form_key') : null;
        }

        function reloadCartData()
        {
            customerData.reload(['cart'], true);
        }

        function addBySku($button, sku, qty)
        {
            return $.ajax({
                url: addBySkuUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    sku: sku,
                    qty: qty,
                    form_key: getFormKey()
                }
            }).done(function (response) {
                if (!response || !response.success) {
                    let message = response && response.message ? response.message : 'Erro ao adicionar produto';
                    window.alert(message);
                    return;
                }

                reloadCartData();

                if (mode === 'sidebar') {
                    $button.text('OK').addClass('added');
                } else {
                    $button.addClass('erp-added');
                    $button.find('.btn-text').text('OK').show();
                    $button.find('.btn-loading').hide();
                }

                window.setTimeout(function () {
                    window.location.reload();
                }, reloadDelay);
            }).fail(function () {
                window.alert('Erro de conexao. Tente novamente.');
            });
        }

        $root.on('click', '.erp-widget-add-btn', function () {
            var $button = $(this);
            let sku = String($button.data('sku') || '');
            let qty = Number($button.data('qty') || 1);

            if (!sku) {
                return;
            }

            $button.prop('disabled', true);
            $button.find('.btn-text').hide();
            $button.find('.btn-loading').show();

            addBySku($button, sku, qty).always(function () {
                if (!$button.hasClass('erp-added')) {
                    $button.prop('disabled', false);
                    $button.find('.btn-text').show();
                    $button.find('.btn-loading').hide();
                }
            });
        });

        $root.on('click', '.erp-sidebar-add', function () {
            var $button = $(this);
            let sku = String($button.data('sku') || '');
            let qty = Number($button.data('qty') || 1);

            if (!sku) {
                return;
            }

            $button.prop('disabled', true).text('...');

            addBySku($button, sku, qty).always(function () {
                if (!$button.hasClass('added')) {
                    $button.prop('disabled', false).text('+');
                }
            });
        });
    };
});
