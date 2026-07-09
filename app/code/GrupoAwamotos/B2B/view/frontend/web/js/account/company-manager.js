define([
    'jquery',
    'mage/translate',
    'mage/cookies',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/confirm'
], function ($, $t, _cookies, alertModal, confirmModal) {
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

    function requestAction(url, payload, onSuccess, onFail)
    {
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: payload
        }).done(function (response) {
            if (response && response.message) {
                showAlert(response.message);
            }

            if (response && response.success) {
                onSuccess();
                return;
            }

            if (typeof onFail === 'function') {
                onFail(response || {});
            }
        }).fail(function () {
            if (typeof onFail === 'function') {
                onFail({});
            }
        });
    }

    return function (config, element) {
        var $root = $(element);
        var messages = config.messages || {};
        var manageUrl = config.manageUrl || '';

        var $addForm = $root.find('#add-user-form');
        var $emailInput = $root.find('#new-user-email');
        var $roleInput = $root.find('#new-user-role');

        if (!manageUrl) {
            return;
        }

        $root.on('click', '[data-action="toggle-add-user"]', function () {
            $addForm.toggleClass('hidden');
            if (!$addForm.hasClass('hidden')) {
                $emailInput.trigger('focus');
            }
        });

        $root.on('click', '[data-action="add-user"]', function () {
            var email = $.trim($emailInput.val());
            var role = $roleInput.val() || 'buyer';

            if (!email) {
                showAlert(messages.emailRequired || $t('Informe o e-mail.'));
                return;
            }

            requestAction(
                manageUrl,
                {
                    action: 'add',
                    customer_id: 0,
                    email: email,
                    role: role,
                    form_key: getFormKey()
                },
                function () {
                    window.location.reload();
                },
                function () {
                    showAlert(messages.requestError || $t('Erro. Tente novamente.'));
                }
            );
        });

        $root.on('change', '.js-user-role', function () {
            var $select = $(this);
            var customerId = parseInt($select.data('customer-id'), 10) || 0;
            var role = $select.val() || 'buyer';

            if (customerId <= 0) {
                return;
            }

            requestAction(
                manageUrl,
                {
                    action: 'update_role',
                    customer_id: customerId,
                    role: role,
                    form_key: getFormKey()
                },
                function () {
                    window.location.reload();
                },
                function () {
                    showAlert(messages.requestError || $t('Erro. Tente novamente.'));
                }
            );
        });

        $root.on('click', '[data-action="remove-user"]', function () {
            var customerId = parseInt($(this).data('customer-id'), 10) || 0;

            if (customerId <= 0) {
                return;
            }

            confirmModal({
                title: $t('Confirmar'),
                content: messages.confirmRemove || $t('Remover este usuário da empresa?'),
                actions: {
                    confirm: function () {
                        requestAction(
                            manageUrl,
                            {
                                action: 'remove',
                                customer_id: customerId,
                                form_key: getFormKey()
                            },
                            function () {
                                window.location.reload();
                            },
                            function () {
                                showAlert(messages.requestError || $t('Erro. Tente novamente.'));
                            }
                        );
                    }
                }
            });
        });
    };
});
