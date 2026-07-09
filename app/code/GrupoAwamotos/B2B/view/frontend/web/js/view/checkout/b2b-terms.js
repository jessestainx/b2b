/**
 * B2B Terms and Conditions Component for Checkout
 * Exibe termos específicos para clientes B2B com validação obrigatória
 *
 * @module GrupoAwamotos_B2B/js/view/checkout/b2b-terms
 */
define([
    'uiComponent',
    'ko',
    'jquery',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/model/quote',
    'GrupoAwamotos_B2B/js/model/checkout/b2b-config',
    'mage/translate'
], function (Component, ko, $, customer, additionalValidators, quote, b2bConfig, $t) {
    'use strict';

    var validatorRegistered = false;

    function getTermsConfig()
    {
        return b2bConfig.getSection('terms');
    }

    return Component.extend({
        defaults: {
            template: 'GrupoAwamotos_B2B/checkout/b2b-terms',
            isAccepted: false,
            isVisible: true,
            checkboxText: $t('Li e aceito os termos de venda B2B'),
            termsContent: '',
            warningTitle: $t('Atenção'),
            warningContent: $t('Você deve aceitar os termos e condições para continuar.')
        },

        /**
         * Initialize component
         */
        initialize: function () {
            var self = this;
            var termsConfig = getTermsConfig();

            this._super();

            if (termsConfig.checkboxText) {
                this.checkboxText = termsConfig.checkboxText;
            }
            if (termsConfig.content) {
                this.termsContent = termsConfig.content;
            }
            if (termsConfig.warningTitle) {
                this.warningTitle = termsConfig.warningTitle;
            }
            if (termsConfig.warningContent) {
                this.warningContent = termsConfig.warningContent;
            }

            this.isAccepted = ko.observable(false);
            this.isModalOpen = ko.observable(false);
            this.inlineError = ko.observable('');
            this.isVisible = ko.computed(function () {
                var checkoutConfig = b2bConfig.getCheckoutConfig();
                var loggedIn = customer.isLoggedIn() || checkoutConfig.isCustomerLoggedIn === true;

                return loggedIn && b2bConfig.isEnabled(getTermsConfig().enabled);
            }, this);

            this.isModalOpen.subscribe(function (open) {
                if (open) {
                    self.bindModalKeyboard();
                    window.setTimeout(function () {
                        var closeBtn = document.querySelector('.b2b-terms-modal-close');

                        if (closeBtn && typeof closeBtn.focus === 'function') {
                            closeBtn.focus();
                        }
                    }, 0);
                } else {
                    self.unbindModalKeyboard();
                }
            });

            if (b2bConfig.isEnabled(termsConfig.enabled) && !validatorRegistered) {
                additionalValidators.registerValidator(this);
                validatorRegistered = true;
            }

            this.isAccepted.subscribe(function (accepted) {
                if (accepted) {
                    this.inlineError('');
                    $('.b2b-terms-container').removeClass('b2b-terms-container--error');
                }
            }, this);

            // Garante sync nativo → KO (label click / autofill antes do binding completo).
            $(document).on('change.awaB2bTerms', '#b2b-terms-checkbox', function () {
                self.isAccepted(!!this.checked);
            });

            return this;
        },

        /**
         * Validate acceptance
         *
         * @returns {boolean}
         */
        validate: function () {
            if (!this.isVisible()) {
                return true;
            }

            this.syncAcceptedFromDom();

            if (!this.isAccepted()) {
                this.showInlineBlocker();
                return false;
            }

            return true;
        },

        /**
         * Align KO state with the native checkbox (automation / partial KO binding edge cases).
         */
        syncAcceptedFromDom: function () {
            var checkbox = document.getElementById('b2b-terms-checkbox');

            if (checkbox && checkbox.checked && !this.isAccepted()) {
                this.isAccepted(true);
            }
        },

        /**
         * Inline feedback + scroll so users see why "Concluir Pedido" did not proceed.
         */
        showInlineBlocker: function () {
            var message = this.warningContent || $t('Você deve aceitar os termos e condições para continuar.');

            this.inlineError(message);
            $('.b2b-terms-container').addClass('b2b-terms-container--error');

            var container = document.querySelector('.b2b-terms-container[data-awa-component="b2b-terms"]');

            if (container && typeof container.scrollIntoView === 'function') {
                container.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            var checkbox = document.getElementById('b2b-terms-checkbox');

            if (checkbox && typeof checkbox.focus === 'function') {
                checkbox.focus({ preventScroll: true });
            }
        },

        /**
         * Escape fecha o modal de termos (a11y).
         */
        bindModalKeyboard: function () {
            var self = this;

            this._modalKeyHandler = function (event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    self.closeTermsModal();
                }
            };

            document.addEventListener('keydown', this._modalKeyHandler);
        },

        /**
         * Remove listener do modal.
         */
        unbindModalKeyboard: function () {
            if (this._modalKeyHandler) {
                document.removeEventListener('keydown', this._modalKeyHandler);
                this._modalKeyHandler = null;
            }
        },

        /**
         * Open terms modal
         */
        openTermsModal: function () {
            this.isModalOpen(true);
        },

        /**
         * Close terms modal
         */
        closeTermsModal: function () {
            this.isModalOpen(false);
        },

        /**
         * Accept terms from modal
         */
        acceptTerms: function () {
            this.isAccepted(true);
            this.closeTermsModal();
        },

        /**
         * @returns {string}
         */
        getSectionTitle: function () {
            return $t('Condições comerciais B2B');
        },

        /**
         * @returns {string}
         */
        getRequiredLabel: function () {
            return $t('Obrigatório');
        },

        /**
         * @returns {string}
         */
        getSectionDescription: function () {
            return $t('Confirme a leitura dos termos para seguir com um pedido corporativo seguro e alinhado às políticas comerciais vigentes.');
        },

        /**
         * Get checkbox label with link
         *
         * @returns {string}
         */
        getCheckboxLabel: function () {
            return this.checkboxText;
        },

        /**
         * @returns {string}
         */
        getTermsLinkLabel: function () {
            return $t('Ver termos completos');
        },

        /**
         * Get terms content HTML
         *
         * @returns {string}
         */
        getTermsContent: function () {
            return this.termsContent;
        },

        /**
         * Check if terms link should be shown
         *
         * @returns {boolean}
         */
        hasTermsContent: function () {
            return !!this.termsContent;
        },

        /**
         * @returns {string}
         */
        getAcceptedLabel: function () {
            return $t('Termos aceitos');
        },

        /**
         * @returns {string}
         */
        getModalTitle: function () {
            return $t('Termos e condições de venda B2B');
        },

        /**
         * @returns {string}
         */
        getCloseLabel: function () {
            return $t('Fechar');
        },

        /**
         * @returns {string}
         */
        getCloseButtonLabel: function () {
            return $t('Fechar');
        },

        /**
         * @returns {string}
         */
        getAcceptButtonLabel: function () {
            return $t('Li e aceito os termos');
        },

        /**
         * @returns {string}
         */
        getFieldDescriptionId: function () {
            return 'b2b-terms-description';
        }
    });
});