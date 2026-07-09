/**
 * Abre a pagina de impressao do boleto dentro de um modal (iframe), evitando
 * navegacao para nova aba. O boleto e carregado no iframe (mesma pagina ja
 * "limpa" usada na impressao direta) e o botao "Imprimir" do modal aciona
 * a impressao apenas do conteudo do iframe.
 */
define([
    'jquery',
    'mage/translate',
    'Magento_Ui/js/modal/modal'
], function ($, $t, modal) {
    'use strict';

    /**
     * Referencia ao popup atualmente aberto (evita 2+ modais empilhados
     * se o usuario clicar em "Imprimir" mais de uma vez).
     */
    var activePopup = null;

    /**
     * Evita registrar binding global mais de uma vez.
     */
    var globalBindingApplied = false;

    /**
     * Tempo maximo (ms) esperando o iframe carregar antes de mostrar um
     * aviso de erro com opcao de abrir em nova aba (evita ficar preso em
     * "Carregando boleto..." para sempre caso algo impeca o load).
     */
    var LOAD_TIMEOUT_MS = 15000;

    /**
     * @param {string} url
     */
    function openPrintModal(url) {
        if (activePopup) {
            activePopup.closeModal();
            activePopup = null;
        }

        var $content = $(
            '<div class="b2b-finance-print-modal">' +
                '<div class="b2b-finance-print-modal__loading">' + $t('Carregando boleto…') + '</div>' +
                '<iframe class="b2b-finance-print-modal__frame" title="' + $t('Boleto para impressão') + '"></iframe>' +
            '</div>'
        );
        var $frame = $content.find('.b2b-finance-print-modal__frame');
        var $loading = $content.find('.b2b-finance-print-modal__loading');
        var timeoutId;

        var popup = modal({
            title: $t('Imprimir boleto'),
            modalClass: 'b2b-finance-print-modal-wrap',
            responsive: true,
            innerScroll: false,
            buttons: [
                {
                    text: $t('Fechar'),
                    class: 'action-secondary',
                    click: function () {
                        this.closeModal();
                    }
                },
                {
                    text: $t('Imprimir'),
                    class: 'action-primary',
                    click: function () {
                        var win = $frame[0] && $frame[0].contentWindow;

                        if (win) {
                            win.focus();
                            win.print();
                        }
                    }
                }
            ],
            closed: function () {
                clearTimeout(timeoutId);
                $frame.attr('src', 'about:blank');
                $content.remove();
                activePopup = null;
            }
        }, $content);

        activePopup = popup;

        $frame.on('load', function () {
            clearTimeout(timeoutId);
            $loading.hide();
        });
        $frame.attr('src', url);

        timeoutId = setTimeout(function () {
            $loading.html(
                '<p>' + $t('Isso está demorando mais que o esperado.') + '</p>' +
                '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' +
                    $t('Abrir em nova aba') +
                '</a>'
            );
        }, LOAD_TIMEOUT_MS);

        if (popup && typeof popup.openModal === 'function') {
            popup.openModal();
            return;
        }

        // Fallback para temas/plugins que exponham apenas API jQuery modal.
        if (typeof $content.modal === 'function') {
            $content.modal('openModal');
        }
    }

    function bindClickHandler() {
        var $scope = $(document);

        $scope.off('click.b2bPrintModal', '[data-print-boleto-url]');
        $scope.on('click.b2bPrintModal', '[data-print-boleto-url]', function (event) {
            var url = $(event.currentTarget).attr('data-print-boleto-url');

            event.preventDefault();

            if (!url) {
                return;
            }

            try {
                openPrintModal(url);
            } catch (error) {
                window.location.href = url;
            }
        });
    }

    /**
     * O binding e feito SEMPRE em document (delegado), uma unica vez, mesmo que
     * este modulo seja inicializado mais de uma vez na pagina (ex.: mais de um
     * data-mage-init apontando pra ele). Bindar em escopos diferentes (ex.: no
     * container da tabela E em document) faz o mesmo clique disparar 2+ handlers
     * via bubbling, o que abre e fecha o modal quase instantaneamente (o
     * singleton "activePopup" fecha o popup recem-aberto ao ver a 2a chamada) --
     * bug real que causava "nao abre o modal" para o usuario.
     */
    return function () {
        if (!globalBindingApplied) {
            bindClickHandler();
            globalBindingApplied = true;
        }
    };
});
