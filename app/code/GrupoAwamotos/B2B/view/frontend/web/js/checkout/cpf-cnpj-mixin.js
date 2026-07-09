/**
 * AWA Motos B2B — Máscara CPF/CNPJ no campo vat_id do checkout.
 * Auto-detecta: 11 dígitos = CPF (000.000.000-00), 14 dígitos = CNPJ (00.000.000/0000-00).
 */
define(['jquery', 'mage/utils/wrapper'], function ($, wrapper) {
    'use strict';

    function maskCpfCnpj(value)
    {
        var v = String(value).replace(/\D/g, '').substring(0, 14);
        if (v.length <= 11) {
            // CPF: 000.000.000-00
            v = v.replace(/^(\d{3})(\d)/, '$1.$2');
            v = v.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
            v = v.replace(/\.(\d{3})(\d)/, '.$1-$2');
        } else {
            // CNPJ: 00.000.000/0000-00
            v = v.replace(/^(\d{2})(\d)/, '$1.$2');
            v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
            v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
            v = v.replace(/(\d{4})(\d)/, '$1-$2');
        }
        return v;
    }

    return function (target) {
        return wrapper.wrap(target, function (original) {
            var result = original.apply(this, Array.prototype.slice.call(arguments, 1));

            // Aplica máscara ao campo vat_id após renderização
            $(document).on('input', 'input[name="vat_id"]', function () {
                var masked = maskCpfCnpj($(this).val());
                $(this).val(masked);
            });

            return result;
        });
    };
});
