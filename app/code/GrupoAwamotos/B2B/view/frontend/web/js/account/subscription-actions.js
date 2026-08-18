define([
    'jquery',
    'mage/translate',
    'Magento_Ui/js/modal/confirm'
], function ($, $t, confirmModal) {
    'use strict';

    function escapeHtml(value)
    {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildDeleteMessage(template, subscriptionName)
    {
        var name = $.trim(subscriptionName || '');
        return template.replace('%1', escapeHtml(name || $t('selecionada')));
    }

    return function (config, element) {
        var options = config || {};
        var messages = options.messages || {};
        var liveRegionSelector = options.liveRegionSelector || '[data-role="subscription-live"]';
        var $root = $(element);
        var $liveRegion = $root.find(liveRegionSelector).first();
        var deleteTitle = messages.deleteTitle || $t('Remover assinatura');
        var deleteConfirmTemplate = messages.deleteConfirm ||
            $t('Tem certeza de que deseja remover a assinatura "%1"?');
        var deleteAction = messages.deleteAction || $t('Remover');

        $root.on('click', '[data-role="delete-subscription"]', function (event) {
            var $trigger = $(this);
            var href = $trigger.attr('href');
            var targetFormId = String($trigger.data('target-form') || '');
            var $targetForm = targetFormId ? $('#' + targetFormId) : $trigger.closest('form');
            var subscriptionName = $trigger.data('subscription-name') || '';

            event.preventDefault();

            if (!$targetForm.length && !href) {
                return;
            }

            confirmModal({
                title: deleteTitle,
                content: buildDeleteMessage(deleteConfirmTemplate, subscriptionName),
                actions: {
                    confirm: function () {
                        if ($liveRegion.length) {
                            $liveRegion.text($t('Remoção da assinatura em andamento.'));
                        }
                        if ($targetForm.length) {
                            $targetForm.trigger('submit');
                            return;
                        }

                        window.location.href = href;
                    }
                },
                buttons: [{
                    text: $t('Cancelar'),
                    class: 'action secondary action-dismiss',
                    click: function (event) {
                        this.closeModal(event, false);
                    }
                }, {
                    text: deleteAction,
                    class: 'action primary action-accept',
                    click: function (event) {
                        this.closeModal(event, true);
                    }
                }]
            });
        });
    };
});
