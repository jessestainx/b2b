define([
    'jquery'
], function ($) {
    'use strict';

    return function (config, element) {
        var $root = $(element),
            modalSelector = config.modalSelector || '#create-list-modal',
            $modal = $(modalSelector);

        function showModal()
        {
            $modal.show();
        }

        function hideModal()
        {
            $modal.hide();
        }

        if (!$modal.length) {
            return;
        }

        $root.on('click.shoppingListModal', '[data-action="open-modal"]', function (event) {
            event.preventDefault();
            showModal();
        });

        $root.on('click.shoppingListModal', '[data-action="close-modal"]', function (event) {
            event.preventDefault();
            hideModal();
        });

        $root.on('submit.shoppingListModal', 'form[data-confirm]', function (event) {
            let message = $(this).data('confirm');

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });

        if (!$modal.data('shoppingListModalBound')) {
            $(document).on('click.shoppingListModal', function (event) {
                if ($modal.is(':visible') && $(event.target).is($modal)) {
                    hideModal();
                }
            });

            $(document).on('keyup.shoppingListModal', function (event) {
                if (event.key === 'Escape' && $modal.is(':visible')) {
                    hideModal();
                }
            });

            $modal.data('shoppingListModalBound', true);
        }
    };
});
