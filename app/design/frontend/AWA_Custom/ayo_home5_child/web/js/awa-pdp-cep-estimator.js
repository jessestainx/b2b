/**
 * AWA Motos - PDP CEP / Shipping Estimator widget
 *
 * Quick win #1 (Sprint 2): calcula frete e prazo via AJAX sem reload de pagina.
 *
 * Comportamento:
 *  - Mascara o CEP (99999-999) automaticamente
 *  - Submit ao clicar no botao ou ao pressionar Enter
 *  - Estados: idle -> loading -> success/error
 *  - Acessivel: aria-live="polite" na area de resultados, foco gerenciado
 *
 * Endpoint esperado: GET /awa/cep/estimate?productId=X&cep=Y
 *   Resposta JSON: { ok: bool, options: [{ carrier, method, price, eta }], error?: string }
 */
define(['jquery', 'Magento_Ui/js/modal/alert'], function ($, alert) {
    'use strict';

    var SEL_INPUT = '[data-awa-cep-input]';
    var SEL_SUBMIT = '[data-awa-cep-submit]';
    var SEL_RESULTS = '[data-awa-cep-results]';

    function onlyDigits(s) { return String(s || '').replace(/\D+/g, ''); }
    function formatCep(cep) {
        var d = onlyDigits(cep).slice(0, 8);
        if (d.length > 5) return d.slice(0, 5) + '-' + d.slice(5);
        return d;
    }
    function isValidCep(cep) { return onlyDigits(cep).length === 8; }

    function renderResults($root, payload) {
        var $box = $root.find(SEL_RESULTS);
        var html;
        if (payload.ok && payload.options && payload.options.length) {
            html = '<ul class="awa-pdp-cep-estimator__list">';
            payload.options.forEach(function (o) {
                html += '<li class="awa-pdp-cep-estimator__option">'
                      +    '<span class="awa-pdp-cep-estimator__carrier">' + escape(o.carrier || '') + '</span>'
                      +    '<span class="awa-pdp-cep-estimator__method">' + escape(o.method || '') + '</span>'
                      +    '<span class="awa-pdp-cep-estimator__price">' + escape(o.price || '') + '</span>'
                      +    '<span class="awa-pdp-cep-estimator__eta">' + escape(o.eta || '') + '</span>'
                      + '</li>';
            });
            html += '</ul>';
        } else {
            html = '<p class="awa-pdp-cep-estimator__error">'
                 + escape(payload.error || 'Nenhuma opcao de entrega disponivel para este CEP.')
                 + '</p>';
        }
        $box.html(html).prop('hidden', false);
    }

    function escape(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    return function (config, el) {
        var $root = $(el);
        var endpoint = config.endpoint || $root.data('endpoint') || '/awa/cep/estimate';
        var $input = $root.find(SEL_INPUT);
        var $submit = $root.find(SEL_SUBMIT);
        var $spinner = $submit.find('.awa-pdp-cep-estimator__spinner');

        // Mascara CEP em tempo real
        $input.on('input', function () {
            var pos = this.selectionStart;
            var formatted = formatCep(this.value);
            if (formatted !== this.value) {
                this.value = formatted;
                try { this.setSelectionRange(pos, pos); } catch (e) {}
            }
        });

        function setLoading(loading) {
            $submit.prop('disabled', loading);
            $input.prop('disabled', loading);
            $spinner.prop('hidden', !loading);
            $submit.attr('aria-busy', loading ? 'true' : 'false');
        }

        function submit() {
            var raw = $input.val();
            if (!isValidCep(raw)) {
                $input.focus().attr('aria-invalid', 'true');
                return;
            }
            $input.removeAttr('aria-invalid');
            setLoading(true);
            var url = endpoint
                + (endpoint.indexOf('?') >= 0 ? '&' : '?')
                + 'product_id=' + encodeURIComponent(config.productId || $root.data('product-id'))
                + '&cep=' + encodeURIComponent(onlyDigits(raw));

            $.ajax({ url: url, method: 'GET', dataType: 'json', timeout: 8000 })
                .done(function (payload) {
                    renderResults($root, payload || { ok: false, error: 'Resposta invalida.' });
                })
                .fail(function (xhr, status) {
                    var msg = (status === 'timeout')
                        ? 'Tempo esgotado. Tente novamente.'
                        : 'Nao foi possivel calcular o frete agora.';
                    renderResults($root, { ok: false, error: msg });
                })
                .always(function () {
                    setLoading(false);
                });
        }

        $submit.on('click', submit);
        $input.on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submit();
            }
        });
    };
});
