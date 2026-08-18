define(['jquery'], function ($) {
    'use strict';

    function readStorage(storageKey)
    {
        try {
            return window.sessionStorage.getItem(storageKey) === '1';
        } catch (error) {
            return false;
        }
    }

    function writeStorage(storageKey)
    {
        try {
            window.sessionStorage.setItem(storageKey, '1');
        } catch (error) {
            // Session storage may be unavailable in restricted browsers.
        }
    }

    function initSuggestionModal(config, element)
    {
        var options = config || {};
        var $modal = $(element);
        var personTypeSelector = options.personTypeSelector || '#person_type';
        var personTypeValue = options.personTypeValue || 'pj';
        var storageKey = options.storageKey || 'b2b_pj_modal_shown';
        var $personType = $(personTypeSelector).first();
        var lastFocused = null;

        if (!$modal.length || !$personType.length) {
            return;
        }

        function closeModal()
        {
            $modal.removeClass('active').attr('aria-hidden', 'true');
            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
        }

        function openModal()
        {
            lastFocused = document.activeElement;
            $modal.addClass('active').attr('aria-hidden', 'false');
            var $close = $modal.find('.b2b-suggestion-close').first();
            if ($close.length) {
                $close.trigger('focus');
            }
        }

        function shouldOpenModal()
        {
            return $personType.val() === personTypeValue && !readStorage(storageKey);
        }

        function handlePersonTypeChange()
        {
            if (!shouldOpenModal()) {
                return;
            }

            openModal();
            writeStorage(storageKey);
        }

        function handleEscape(event)
        {
            if (event.key !== 'Escape') {
                return;
            }

            closeModal();
        }

        $personType.on('change', handlePersonTypeChange);

        $modal.on('click', '.b2b-suggestion-close, .b2b-continue-btn', function () {
            closeModal();
        });

        $modal.on('click', function (event) {
            if (event.target === $modal.get(0)) {
                closeModal();
            }
        });

        $(document).off('keyup.b2bSuggestionModal').on('keyup.b2bSuggestionModal', handleEscape);
    }

    return initSuggestionModal;
});
