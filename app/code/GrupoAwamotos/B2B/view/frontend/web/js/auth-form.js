define([
    'jquery',
    'mage/validation'
], function ($) {
    'use strict';

    function maskCnpj(value)
    {
        let digits = String(value || '').replace(/\D/g, '');

        if (!digits) {
            return '';
        }

        if (digits.length > 14) {
            digits = digits.substring(0, 14);
        }

        digits = digits.replace(/^(\d{2})(\d)/, '$1.$2');
        digits = digits.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
        digits = digits.replace(/\.(\d{3})(\d)/, '.$1/$2');
        digits = digits.replace(/(\d{4})(\d)/, '$1-$2');

        return digits;
    }

    return function (config, element) {
        let options = config || {};
        var $form = $(element);
        var $username = $(options.usernameSelector || '');
        var $cnpjOrEmailField = $(options.cnpjOrEmailMaskSelector || '');
        var $passwordToggles = $form.find(options.passwordToggleSelector || '[data-password-toggle]');
        let loadingLabel = options.loadingLabel || 'Processando...';
        let passwordShowText = options.passwordShowText || 'Mostrar';
        let passwordHideText = options.passwordHideText || 'Ocultar';
        let passwordShowAriaLabel = options.passwordShowAriaLabel || 'Mostrar senha';
        let passwordHideAriaLabel = options.passwordHideAriaLabel || 'Ocultar senha';

        if (!$form.length) {
            return;
        }

        function schedule(callback)
        {
            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(callback);
                return;
            }

            window.setTimeout(callback, 0);
        }

        function syncFieldAriaInvalidState()
        {
            $form.find('input, select, textarea').each(function () {
                var $field = $(this);
                let hasError = $field.hasClass('mage-error');

                $field.attr('aria-invalid', hasError ? 'true' : 'false');
            });
        }

        function scrollToField($field)
        {
            let topOffset;
            let prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (!$field.length) {
                return;
            }

            topOffset = Math.max(0, ($field.offset().top || 0) - 24);

            if (prefersReducedMotion) {
                window.scrollTo(0, topOffset);
                return;
            }

            $('html, body').stop(true).animate({ scrollTop: topOffset }, 220);
        }

        function focusFirstInvalidField()
        {
            var $firstInvalid = $form.find('input.mage-error, select.mage-error, textarea.mage-error').filter(':visible').first();

            if (!$firstInvalid.length) {
                return;
            }

            scrollToField($firstInvalid);

            window.setTimeout(function () {
                $firstInvalid.trigger('focus');
            }, 40);
        }

        function syncUsernameInputMode()
        {
            let current;
            let digits;
            let nextMode;

            if (!$cnpjOrEmailField.length) {
                return;
            }

            current = String($cnpjOrEmailField.val() || '');

            if (current.indexOf('@') !== -1 || /[a-z]/i.test(current)) {
                nextMode = 'email';
            } else {
                digits = current.replace(/\D/g, '');
                nextMode = digits.length > 0 ? 'numeric' : 'email';
            }

            $cnpjOrEmailField.attr('inputmode', nextMode);
        }

        function applyCnpjOrEmailMask()
        {
            let current;

            if (!$cnpjOrEmailField.length) {
                return;
            }

            current = String($cnpjOrEmailField.val() || '');
            syncUsernameInputMode();

            // If user is typing email, don't force a mask.
            if (current.indexOf('@') !== -1 || /[a-z]/i.test(current)) {
                return;
            }

            $cnpjOrEmailField.val(maskCnpj(current));
        }

        function updatePasswordToggleButton($toggle, isVisible)
        {
            let nextLabel = isVisible ? passwordHideAriaLabel : passwordShowAriaLabel;
            let nextText = isVisible ? passwordHideText : passwordShowText;
            var $textTarget = $toggle.find('[data-password-toggle-label]');

            $toggle.attr('aria-pressed', isVisible ? 'true' : 'false');
            $toggle.attr('aria-label', nextLabel);
            $toggle.attr('title', nextLabel);

            if ($textTarget.length) {
                $textTarget.text(nextText);
            }
        }

        function initPasswordToggles()
        {
            $passwordToggles.each(function () {
                var $toggle = $(this);
                let targetSelector = $toggle.attr('data-target');
                var $target = targetSelector ? $form.find(targetSelector) : $();

                if (!$target.length) {
                    return;
                }

                updatePasswordToggleButton($toggle, $target.attr('type') === 'text');

                $toggle.on('click', function (event) {
                    let showPassword;

                    event.preventDefault();
                    showPassword = $target.attr('type') !== 'text';
                    $target.attr('type', showPassword ? 'text' : 'password');
                    updatePasswordToggleButton($toggle, showPassword);
                    $target.trigger('focus');
                });
            });
        }

        function setSubmitLoadingState($button)
        {
            var $label = $button.find('span').first();
            let originalLabel;
            var $overlay;

            if (!$button.length) {
                return;
            }

            originalLabel = $button.data('original-label');
            if (!originalLabel) {
                $button.data('original-label', $label.text());
            }

            $button.addClass('is-loading').prop('disabled', true).attr('aria-busy', 'true');

            if ($label.length) {
                $label.text(loadingLabel);
            }

            // Show a full-page overlay so the loading state is visually
            // prominent and detectable throughout the server round-trip.
            if (!$('#b2b-auth-loading-overlay').length) {
                $overlay = $('<div>', {
                    id: 'b2b-auth-loading-overlay',
                    role: 'status',
                    'aria-label': loadingLabel,
                    'aria-live': 'assertive'
                }).append(
                    $('<div>', { 'class': 'b2b-auth-spinner', 'aria-hidden': 'true' })
                ).append(
                    $('<span>', { 'class': 'b2b-auth-loading-label' }).text(loadingLabel)
                );
                $('body').append($overlay);
            }
        }

        if ($username.length) {
            $username.on('blur', function () {
                let value = String($(this).val() || '');

                if (value.indexOf('@') !== -1) {
                    $(this).val($.trim(value).toLowerCase());
                    return;
                }

                $(this).val($.trim(value));
            });
        }

        if ($cnpjOrEmailField.length) {
            $cnpjOrEmailField.on('input blur', function () {
                applyCnpjOrEmailMask();
            });

            syncUsernameInputMode();
            applyCnpjOrEmailMask();
        }

        $form.on('input blur change', 'input, select, textarea', function () {
            schedule(syncFieldAriaInvalidState);
        });

        $form.on('submit', function (event) {
            var $submitButton = $form.find('button[type="submit"]').first();

            if ($form.data('isSubmitting')) {
                event.preventDefault();
                return false;
            }

            if (typeof $form.validation === 'function' && !$form.validation('isValid')) {
                schedule(function () {
                    syncFieldAriaInvalidState();
                    focusFirstInvalidField();
                });
                return true;
            }

            $form.data('isSubmitting', true).addClass('is-submitting');
            setSubmitLoadingState($submitButton);

            return true;
        });

        initPasswordToggles();
        syncFieldAriaInvalidState();
    };
});
