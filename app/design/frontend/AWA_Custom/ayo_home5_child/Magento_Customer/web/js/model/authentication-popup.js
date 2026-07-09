/**
 * AWA override — modal de autenticação Magento + trigger B2B/AjaxSuite.
 */
define([
    'jquery',
    'Magento_Ui/js/modal/modal'
], function ($, modal) {
    'use strict';

    return {
        modalWindow: null,

        /**
         * @param {HTMLElement} element
         */
        createPopUp: function (element) {
            var options = {
                type: 'popup',
                modalClass: 'popup-authentication',
                focus: '[name=username]',
                responsive: true,
                innerScroll: true,
                trigger: '.proceed-to-checkout, .trigger-auth-popup',
                buttons: []
            };

            this.modalWindow = element;
            this.modalWindow.removeAttribute('hidden');
            this.modalWindow.style.removeProperty('display');
            modal(options, $(this.modalWindow));
        },

        showModal: function () {
            if (!this.modalWindow) {
                return;
            }

            var shell = document.getElementById('authenticationPopup');

            if (shell) {
                shell.removeAttribute('hidden');
                shell.removeAttribute('aria-hidden');
            }

            this.modalWindow.removeAttribute('hidden');
            this.modalWindow.style.removeProperty('display');
            this.modalWindow.style.removeProperty('visibility');
            $(this.modalWindow).modal('openModal').trigger('contentUpdated');
        }
    };
});
