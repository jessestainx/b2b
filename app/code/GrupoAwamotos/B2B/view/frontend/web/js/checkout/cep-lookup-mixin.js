/**
 * AWA Motos B2B — CEP autocomplete no checkout.
 * Ao sair do campo CEP (postcode), consulta ViaCEP e preenche
 * logradouro (street[0]), bairro (street[3]), cidade e estado.
 */
define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote'
], function ($, wrapper, quote) {
    'use strict';

    var CEP_LOOKUP_URL = window.BASE_URL + 'b2b/ajax/ceplookup';

    function formatCep(val)
    {
        var v = String(val).replace(/\D/g, '').substring(0, 8);
        return v.length > 5 ? v.substring(0, 5) + '-' + v.substring(5) : v;
    }

    function lookupCep(cep, onSuccess)
    {
        var clean = String(cep).replace(/\D/g, '');
        if (clean.length !== 8) {
            return;
        }

        $.getJSON(CEP_LOOKUP_URL, { cep: clean }, function (data) {
            if (data.success && data.logradouro) {
                onSuccess(data);
            }
        }).fail(function () {
            // Silencia falhas — ViaCEP pode estar indisponível
        });
    }

    /**
     * Preenche campos de endereço num formulário jQuery identificado pelo seletor.
     * Compatível com shipping e billing address forms no checkout Magento 2.
     */
    function fillAddressFields($form, data)
    {
        // Street 0 = Logradouro
        $form.find('input[name="street[0]"]').val(data.logradouro || '').trigger('change');
        // Street 3 = Bairro (quando street_lines = 4)
        $form.find('input[name="street[3]"]').val(data.bairro || '').trigger('change');
        // Cidade
        $form.find('input[name="city"]').val(data.localidade || '').trigger('change');
        // UF — dispara change para que o componente React/KO atualize a region
        var $region = $form.find('select[name="region_id"]');
        if ($region.length && data.uf) {
            $region.find('option').each(function () {
                if ($(this).text().trim() === data.uf || $(this).val() === data.uf) {
                    $region.val($(this).val()).trigger('change');
                    return false;
                }
            });
        }
    }

    return function (target) {
        return wrapper.wrap(target, function (original) {
            var result = original.apply(this, Array.prototype.slice.call(arguments, 1));

            // Máscara e lookup no campo postcode do checkout
            $(document).on('input', 'input[name="postcode"]', function () {
                $(this).val(formatCep($(this).val()));
            });

            $(document).on('blur', 'input[name="postcode"]', function () {
                var cep = $(this).val();
                var $form = $(this).closest('form, fieldset, .fieldset, [data-role="email-with-possible-login"]').first();
                if (!$form.length) {
                    $form = $(this).parent().parent();
                }

                lookupCep(cep, function (data) {
                    fillAddressFields($form, data);
                });
            });

            return result;
        });
    };
});
