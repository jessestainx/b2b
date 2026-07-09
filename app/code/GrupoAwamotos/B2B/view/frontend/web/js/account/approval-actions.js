define([
    'jquery',
    'mage/translate',
    'mage/cookies',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/modal'
], function ($, $t, _cookies, alertModal, modal) {
    'use strict';

    function showAlert(message)
    {
        alertModal({
            title: $t('B2B'),
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

    function createRejectModal(onConfirm)
    {
        var $modal = $('<div class="b2b-approval-reject-modal" role="dialog" aria-modal="true"></div>');
        var $form = $('<form class="b2b-approval-reject-modal__form"></form>');
        var $label = $('<label class="b2b-approval-reject-modal__label"></label>')
            .text($t('Motivo da rejeição'));
        var $textarea = $('<textarea class="b2b-approval-reject-modal__textarea" required minlength="10" rows="4"></textarea>')
            .attr('aria-label', $t('Motivo da rejeição'));
        var $hint = $('<p class="b2b-approval-reject-modal__hint"></p>')
            .text($t('Informe ao menos 10 caracteres.'));

        $form.append($label, $textarea, $hint);
        $modal.append($form);

        var popup = modal({
            title: $t('Rejeitar pedido'),
            modalClass: 'b2b-approval-reject-modal-wrap',
            focus: '[data-role="reject-reason"]',
            buttons: [{
                text: $t('Cancelar'),
                class: 'action-secondary',
                click: function () {
                    this.closeModal(true);
                }
            }, {
                text: $t('Confirmar rejeição'),
                class: 'action-primary',
                click: function () {
                    var reason = $.trim($textarea.val());
                    if (reason.length < 10) {
                        $textarea.trigger('focus');
                        return;
                    }
                    this.closeModal(true);
                    onConfirm(reason);
                }
            }]
        }, $modal);

        $textarea.attr('data-role', 'reject-reason');
        popup.openModal();

        $form.on('submit', function (event) {
            event.preventDefault();
            popup.modal.find('.action-primary').trigger('click');
        });
    }

    return function (config, element) {
        var $root = $(element);
        var actionUrl = config.actionUrl || '';
        var messages = config.messages || {};

        if (!actionUrl) {
            return;
        }

        $root.on('click', '[data-action="approve"], [data-action="reject"]', function () {
            var $button = $(this);
            var approvalId = parseInt($button.data('approval-id'), 10) || 0;
            var action = $button.data('action') || '';

            if (!approvalId || !action) {
                return;
            }

            var submitAction = function (comment) {
                $button.prop('disabled', true).addClass('is-loading');

                $.ajax({
                    url: actionUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        approval_id: approvalId,
                        action: action,
                        comment: comment,
                        reason: comment,
                        form_key: getFormKey()
                    }
                }).done(function (response) {
                    if (response && response.message) {
                        showAlert(response.message);
                    }

                    if (response && response.success) {
                        window.location.reload();
                    }
                }).fail(function () {
                    showAlert(messages.processError || $t('Erro ao processar. Tente novamente.'));
                }).always(function () {
                    $button.prop('disabled', false).removeClass('is-loading');
                });
            };

            if (action === 'reject') {
                createRejectModal(submitAction);
                return;
            }

            submitAction('');
        });
    };
});
