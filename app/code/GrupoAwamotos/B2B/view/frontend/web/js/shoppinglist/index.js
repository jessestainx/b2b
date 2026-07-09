define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var $root = $(element);
        var $createButtons = $root.find('#btn-create-list, [data-trigger-create-list]');
        var $cancelButton = $root.find('#btn-cancel-create');
        var $createForm = $root.find('#create-list-form');
        var $nameInput = $root.find('#list-name');

        // ── Create form ──────────────────────────────────────────────────────
        function showCreateForm()
        {
            $createForm.stop(true, true).slideDown(200);
            $createButtons.hide().attr('aria-expanded', 'true');
            $nameInput.trigger('focus');
        }

        function hideCreateForm()
        {
            $createForm.stop(true, true).slideUp(200);
            $createButtons.show().attr('aria-expanded', 'false');
            $createButtons.first().trigger('focus');
        }

        $createButtons.on('click', showCreateForm);
        $cancelButton.on('click', hideCreateForm);

        // ── Delete confirmation via native <dialog> ──────────────────────────
        var dialog = document.getElementById('b2b-delete-list-dialog');
        var dialogConfirmBtn = document.getElementById('b2b-delete-dialog-confirm');
        var dialogCancelBtn = document.getElementById('b2b-delete-dialog-cancel');

        if (!dialog || typeof dialog.showModal !== 'function') {
            // Fallback para browsers que não suportam <dialog> (Safari < 15.4)
            $root.on('click', '.action--trigger-delete-dialog', function (event) {
                var deleteUrl = $(this).data('delete-url');
                if (deleteUrl && window.confirm($(this).data('list-name')
                    ? 'Excluir a lista "' + $(this).data('list-name') + '"? Esta ação não pode ser desfeita.'
                    : 'Excluir esta lista? Esta ação não pode ser desfeita.')) {
                    window.location.href = deleteUrl;
                }
            });
            return;
        }

        // Mantém referência ao botão que abriu o dialog para devolver foco ao fechar
        var openerButton = null;

        $root.on('click', '.action--trigger-delete-dialog', function () {
            var deleteUrl = $(this).data('delete-url');
            openerButton = this;

            // Aponta o link de confirmação para a URL de exclusão correta
            dialogConfirmBtn.href = deleteUrl;

            dialog.showModal();
            // Foco vai para o botão Cancelar (ação segura por padrão — WCAG 3.3.4)
            dialogCancelBtn.focus();
        });

        dialogCancelBtn.addEventListener('click', function () {
            dialog.close('cancel');
        });

        dialog.addEventListener('close', function () {
            // Devolve foco ao elemento que abriu o dialog
            if (openerButton) {
                openerButton.focus();
                openerButton = null;
            }
        });

        // Fecha ao clicar no backdrop (fora do conteúdo do dialog)
        dialog.addEventListener('click', function (event) {
            var rect = dialog.getBoundingClientRect();
            var clickedBackdrop = (
                event.clientX < rect.left ||
                event.clientX > rect.right ||
                event.clientY < rect.top ||
                event.clientY > rect.bottom
            );
            if (clickedBackdrop) {
                dialog.close('cancel');
            }
        });
    };
});
