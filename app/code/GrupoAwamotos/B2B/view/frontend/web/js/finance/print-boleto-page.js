/**
 * Comportamento da pagina de impressao do boleto.
 * Remove onclick inline e usa binding modular via data-mage-init.
 * Suporta ?autoprint=1 (aberto a partir do modal) para disparar a impressao
 * numa janela dedicada — mais confiavel que iframe.contentWindow.print().
 */
define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';


    /**
     * @returns {boolean}
     */
    function wantsAutoprint() {
        try {
            return new URLSearchParams(window.location.search).get('autoprint') === '1';
        } catch (e) {
            return /[?&]autoprint=1(?:&|$)/.test(window.location.search || '');
        }
    }

    /**
     * Carregado dentro do iframe do modal (?embed=1).
     *
     * @returns {boolean}
     */
    function isEmbedMode() {
        try {
            return new URLSearchParams(window.location.search).get('embed') === '1';
        } catch (e) {
            return /[?&]embed=1(?:&|$)/.test(window.location.search || '');
        }
    }

    /**
     * Cursor/Electron: dialogo de impressao sem preview — nao disparar window.print.
     *
     * @returns {boolean}
     */
    function supportsPrintPreview() {
        if (window.cursorBrowser) {
            return false;
        }

        var ua = navigator.userAgent || '';

        return !(/Electron/i.test(ua) || /Cursor\/[\d.]+/i.test(ua));
    }

    /**
     * Aviso visivel quando o ambiente nao tem preview de impressao (Cursor).
     *
     * @param {jQuery} $root
     */
    function showNoPreviewNotice($root) {
        var $existing = $root.find('.b2b-print-boleto__no-preview');

        if ($existing.length) {
            $existing[0].scrollIntoView({block: 'nearest'});

            return;
        }

        var $notice = $(
            '<p class="b2b-print-boleto__no-preview" role="status">' +
                $t('A visualização de impressão não está disponível neste navegador. Abra esta página no Chrome ou Edge para imprimir o boleto.') +
            '</p>'
        );

        $root.find('.b2b-print-boleto__toolbar').after($notice);
        $notice[0].scrollIntoView({block: 'nearest'});
    }

    /**
     * @param {string} source
     * @param {jQuery} $root
     */
    function triggerPrint(source, $root) {

        if (!supportsPrintPreview()) {
            if ($root && $root.length) {
                showNoPreviewNotice($root);
            }

            return;
        }

        window.print();
    }

    return function (config, element) {
        var $root = $(element);

        // No iframe do modal: esconde toolbar (Imprimir fica no rodape do modal).
        if (isEmbedMode() || window.self !== window.top) {
            $root.addClass('is-embed');
            document.body.classList.add('b2b-print-boleto-embed');
        }

        $root.off('click.b2bPrintPage', '[data-action="print-boleto"]');
        $root.on('click.b2bPrintPage', '[data-action="print-boleto"]', function (event) {
            event.preventDefault();
            triggerPrint('button', $root);
        });

        // Em embed nunca autoprint — o modal controla a impressao.
        if (isEmbedMode()) {
            return;
        }

        if (wantsAutoprint() && supportsPrintPreview()) {
            // Aguarda paint do boleto antes de abrir o dialogo (Chrome/desktop).
            window.setTimeout(function () {
                triggerPrint('autoprint', $root);
            }, 500);
        } else if (wantsAutoprint()) {
            // Pagina dedicada aberta pelo modal no Cursor: avisar sem disparar print.
            if (window.self === window.top) {
                showNoPreviewNotice($root);
            }
        }
    };
});
