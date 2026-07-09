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

    return function (config, element) {
        var options = config || {};
        var $modal = $(element);
        var personTypeSelector = options.personTypeSelector || '#person_type';
        var personTypeValue = options.personTypeValue || 'pj';
        var storageKey = options.storageKey || 'b2b_pj_modal_shown';
        var $personType = $(personTypeSelector).first();

        if (!$modal.length || !$personType.length) {
            return;
        }

        function closeModal()
        {
            $modal.removeClass('active').attr('aria-hidden', 'true');
        }

        function openModal()
        {
            $modal.addClass('active').attr('aria-hidden', 'false');
        }

        $personType.on('change', function () {
            if ($personType.val() !== personTypeValue || readStorage(storageKey)) {
                return;
            }

            openModal();
            writeStorage(storageKey);
        });

        $modal.on('click', '.b2b-suggestion-close, .b2b-continue-btn', function () {
            closeModal();
        });

        $modal.on('click', function (event) {
            if (event.target === $modal.get(0)) {
                closeModal();
            }
        });

        $(document).off('keyup.b2bSuggestionModal').on('keyup.b2bSuggestionModal', function (event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    };
});
