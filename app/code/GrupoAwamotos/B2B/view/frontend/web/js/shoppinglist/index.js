define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var $root = $(element);
        var $createButtons = $root.find('#btn-create-list, [data-trigger-create-list]');
        var $cancelButton = $root.find('#btn-cancel-create');
        var $createForm = $root.find('#create-list-form');
        var $nameInput = $root.find('#list-name');
        var $liveRegion = $root.find('[data-role="shoppinglist-index-live"]');
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var motionMs = reduceMotion ? 0 : 200;

        function announce(message)
        {
            if (!$liveRegion.length || !message) {
                return;
            }

            $liveRegion.text('');
            window.setTimeout(function () {
                $liveRegion.text(message);
            }, 30);
        }

        // ── Create form ──────────────────────────────────────────────────────
        function showCreateForm()
        {
            if (!$createForm.length || !$createForm.prop('hidden')) {
                return;
            }

            $createForm.prop('hidden', false).removeAttr('hidden');
            $createForm.stop(true, true).slideDown(motionMs);
            $createButtons.hide();
            setCreateButtonsExpanded(true);
            $nameInput.trigger('focus');
            announce('Formulário de criação de lista aberto.');
        }

        function hideCreateForm()
        {
            if (!$createForm.length || $createForm.prop('hidden')) {
                return;
            }

            $createForm.stop(true, true).slideUp(motionMs, function () {
                $createForm.prop('hidden', true).attr('hidden', 'hidden');
            });
            $createButtons.show();
            setCreateButtonsExpanded(false);
            $createButtons.first().trigger('focus');
            announce('Formulário de criação de lista fechado.');
        }

        $createButtons.on('click', showCreateForm);
        $cancelButton.on('click', hideCreateForm);

        // ── Delete confirmation via native <dialog> ──────────────────────────
        var dialog = document.getElementById('b2b-delete-list-dialog');
        var dialogConfirmBtn = document.getElementById('b2b-delete-dialog-confirm');
        var dialogCancelBtn = document.getElementById('b2b-delete-dialog-cancel');
        var dialogDesc = document.getElementById('b2b-delete-dialog-desc');
        var defaultDialogDesc = dialogDesc ? dialogDesc.textContent : '';
        var pendingDeleteFormId = '';
        var pendingDeleteUrl = '';
        var openerButton = null;

        function setCreateButtonsExpanded(expanded)
        {
            $createButtons.attr('aria-expanded', expanded ? 'true' : 'false');
        }

        function submitDeleteForm(formId)
        {
            var form = formId ? document.getElementById(formId) : null;

            if (!form) {
                return;
            }

            form.submit();
        }

        function getConfirmHref()
        {
            var href = dialogConfirmBtn.getAttribute('href');

            if (!href || href === '#') {
                return '';
            }

            return href;
        }

        function resolveDeleteAction()
        {
            var href = getConfirmHref();

            if (pendingDeleteFormId) {
                announce('Exclusão confirmada. Enviando solicitação.');
                submitDeleteForm(pendingDeleteFormId);
                return true;
            }

            if (pendingDeleteUrl) {
                window.location.href = pendingDeleteUrl;
                return true;
            }

            if (href) {
                window.location.href = href;
                return true;
            }

            return false;
        }

        function getDeleteDialogMessage(listName)
        {
            if (!listName) {
                return defaultDialogDesc;
            }

            return 'Os itens salvos em "' + listName + '" serão removidos permanentemente. Esta ação não pode ser desfeita.';
        }

        function openDeleteDialog(deleteFormId, deleteUrl, listName, opener)
        {
            openerButton = opener;
            pendingDeleteFormId = deleteFormId ? String(deleteFormId) : '';
            pendingDeleteUrl = deleteUrl ? String(deleteUrl) : '';

            if (dialogDesc) {
                dialogDesc.textContent = getDeleteDialogMessage(listName);
            }

            dialog.showModal();
            // Foco vai para o botão Cancelar (ação segura por padrão — WCAG 3.3.4)
            dialogCancelBtn.focus();
            announce(listName ? 'Confirmar exclusão da lista ' + listName + '.' : 'Confirmar exclusão da lista.');
        }

        if (!dialog || typeof dialog.showModal !== 'function' || !dialogConfirmBtn || !dialogCancelBtn) {
            // Fallback para browsers que não suportam <dialog> (Safari < 15.4)
            $root.on('click', '.action--trigger-delete-dialog', function (event) {
                var deleteFormId = $(this).data('delete-form');
                if (deleteFormId && window.confirm($(this).data('list-name')
                    ? 'Excluir a lista "' + $(this).data('list-name') + '"? Esta ação não pode ser desfeita.'
                    : 'Excluir esta lista? Esta ação não pode ser desfeita.')) {
                    announce('Exclusão confirmada. Enviando solicitação.');
                    submitDeleteForm(String(deleteFormId));
                }
            });
            return;
        }

        $root.on('click', '.action--trigger-delete-dialog', function () {
            var $trigger = $(this);
            openDeleteDialog(
                $trigger.data('delete-form'),
                $trigger.data('delete-url'),
                $trigger.data('list-name'),
                this
            );
        });

        dialogCancelBtn.addEventListener('click', function () {
            dialog.close('cancel');
            announce('Exclusão cancelada.');
        });

        dialogConfirmBtn.addEventListener('click', function (event) {
            event.preventDefault();

            if (resolveDeleteAction()) {
                return;
            }

            dialog.close('cancel');
        });

        dialog.addEventListener('close', function () {
            pendingDeleteFormId = '';
            pendingDeleteUrl = '';
            if (dialogDesc) {
                dialogDesc.textContent = defaultDialogDesc;
            }
            // Devolve foco ao elemento que abriu o dialog
            if (openerButton && typeof openerButton.focus === 'function') {
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
