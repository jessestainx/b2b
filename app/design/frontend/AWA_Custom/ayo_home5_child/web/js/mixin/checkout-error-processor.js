/**
 * Checkout error-processor — mensagens PT-BR e fallback legível.
 */
define([
    'mage/url',
    'Magento_Ui/js/model/messageList',
    'mage/translate'
], function (url, globalMessageList, $t) {
    'use strict';

    var GENERIC_KEY = 'Something went wrong with your request. Please try again later.';
    var GENERIC_PT = $t('Não foi possível concluir esta etapa. Verifique os campos e tente novamente.');

    /**
     * @param {Object|null} response
     * @returns {string}
     */
    function resolveMessage(response) {
        var status = response && response.status ? response.status : 0;
        var parsed = null;

        if (!window.navigator.onLine || status === 0) {
            return $t('Sem conexão com a internet. Verifique sua rede e tente novamente.');
        }

        if (status === 429) {
            return $t('Muitas atualizações em sequência. Aguarde 5 segundos e tente de novo.');
        }

        if (status === 403) {
            return $t('Você não tem permissão para concluir esta etapa. Atualize a página ou entre novamente.');
        }

        if (status >= 500) {
            return $t('O servidor está temporariamente indisponível. Tente novamente em instantes.');
        }

        try {
            parsed = JSON.parse(response.responseText);
        } catch (exception) {
            parsed = null;
        }

        if (parsed && parsed.message && parsed.message !== GENERIC_KEY) {
            return parsed.message;
        }

        return GENERIC_PT;
    }

    return function (errorProcessor) {
        errorProcessor.process = function (response, messageContainer) {
            messageContainer = messageContainer || globalMessageList;

            if (response && response.status === 401) {
                errorProcessor.redirectTo(url.build('b2b/account/login/'));
                return;
            }

            messageContainer.addErrorMessage({
                message: resolveMessage(response)
            });
        };

        return errorProcessor;
    };
});
