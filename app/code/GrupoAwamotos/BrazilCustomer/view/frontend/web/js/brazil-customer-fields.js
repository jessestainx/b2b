/**
 * JavaScript para campos brasileiros de cliente
 * Alterna entre PF/PJ e aplica máscaras
 */
define([
    'jquery',
    'mage/validation'
], function ($) {
    'use strict';

    return function () {
        $(document).ready(function () {
            var $personType = $('#person_type');
            var $pfFields = $('.person-fisica-fields');
            var $pjFields = $('.person-juridica-fields');
            var $cpfField = $('#cpf');
            var $cnpjField = $('#cnpj');

            // Toggle entre PF e PJ
            function togglePersonType()
            {
                let type = $personType.val();

                if (type === 'pf') {
                    $pfFields.show();
                    $pjFields.hide();

                    // Ajusta validação
                    $cpfField.addClass('required-entry').attr('data-validate', '{required:true, "validate-cpf":true}');
                    $cnpjField.removeClass('required-entry').removeAttr('data-validate');
                    $('#company_name').removeClass('required-entry');
                } else {
                    $pfFields.hide();
                    $pjFields.show();

                    // Ajusta validação
                    $cpfField.removeClass('required-entry').removeAttr('data-validate');
                    $cnpjField.addClass('required-entry').attr('data-validate', '{required:true, "validate-cnpj":true}');
                    $('#company_name').addClass('required-entry');
                }
            }

            // Aplica máscara de CPF
            function maskCpf(value)
            {
                value = value.replace(/\D/g, '');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                return value;
            }

            // Aplica máscara de CNPJ
            function maskCnpj(value)
            {
                value = value.replace(/\D/g, '');
                value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
                return value;
            }

            // Validação de CPF
            $.validator.addMethod(
                'validate-cpf',
                function (value) {
                    if (!value) {
                        return true;
                    }

                    let cpf = value.replace(/\D/g, '');

                    if (cpf.length !== 11) {
                        return false;
                    }
                    if (/^(\d)\1{10}$/.test(cpf)) {
                        return false;
                    }

                    // Calcula primeiro dígito verificador
                    let sum = 0;
                    for (let i = 0; i < 9; i++) {
                        sum += parseInt(cpf.charAt(i)) * (10 - i);
                    }
                    let remainder = sum % 11;
                    let digit1 = (remainder < 2) ? 0 : 11 - remainder;

                    if (parseInt(cpf.charAt(9)) !== digit1) {
                        return false;
                    }

                    // Calcula segundo dígito verificador
                    sum = 0;
                    for (let i = 0; i < 10; i++) {
                        sum += parseInt(cpf.charAt(i)) * (11 - i);
                    }
                    remainder = sum % 11;
                    let digit2 = (remainder < 2) ? 0 : 11 - remainder;

                    return parseInt(cpf.charAt(10)) === digit2;
                },
                $.mage.__('CPF inválido. Verifique os números digitados.')
            );

            // Validação de CNPJ
            $.validator.addMethod(
                'validate-cnpj',
                function (value) {
                    if (!value) {
                        return true;
                    }

                    let cnpj = value.replace(/\D/g, '');

                    if (cnpj.length !== 14) {
                        return false;
                    }
                    if (/^(\d)\1{13}$/.test(cnpj)) {
                        return false;
                    }

                    // Calcula primeiro dígito verificador
                    let weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
                    let sum = 0;
                    for (let i = 0; i < 12; i++) {
                        sum += parseInt(cnpj.charAt(i)) * weights1[i];
                    }
                    let remainder = sum % 11;
                    let digit1 = (remainder < 2) ? 0 : 11 - remainder;

                    if (parseInt(cnpj.charAt(12)) !== digit1) {
                        return false;
                    }

                    // Calcula segundo dígito verificador
                    let weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
                    sum = 0;
                    for (let i = 0; i < 13; i++) {
                        sum += parseInt(cnpj.charAt(i)) * weights2[i];
                    }
                    remainder = sum % 11;
                    let digit2 = (remainder < 2) ? 0 : 11 - remainder;

                    return parseInt(cnpj.charAt(13)) === digit2;
                },
                $.mage.__('CNPJ inválido. Verifique os números digitados.')
            );

            // Event listeners
            $personType.on('change', togglePersonType);

            $cpfField.on('input', function () {
                this.value = maskCpf(this.value);
            });

            $cnpjField.on('input', function () {
                this.value = maskCnpj(this.value);
            });

            // Inicialização
            togglePersonType();
        });
    };
});
