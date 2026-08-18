/**
 * AWA AI Assistant — Knockout/UIComponent
 * Magento 2 storefront chat widget (RequireJS + Knockout + uiComponent)
 */
define([
    'ko',
    'uiComponent',
    'jquery',
    'mage/cookies',
    'GrupoAwamotos_AiAssistant/js/guided-coach'
], function (ko, Component, $, mageCookies, guidedCoach) {
    'use strict';

    return Component.extend({

        defaults: {
            config: {}
        },

        initialize: function () {
            this._super();

            // Magento UI merges x-magento-init "config" onto the component instance.
            // this.config is often {} — never overwrite merged props from it alone.
            var cfg = this.config || {};
            this.endpoint       = this.endpoint       || cfg.endpoint       || '';
            this.title          = this.title          || cfg.title          || 'Assistente AWA';
            this.welcomeMessage = this.welcomeMessage || cfg.welcomeMessage || '';
            this.channel        = this.channel        || cfg.channel        || 'storefront';
            this.isLoggedIn     = typeof this.isLoggedIn === 'boolean'
                ? this.isLoggedIn
                : !!(cfg.isLoggedIn);
            this.isB2B          = typeof this.isB2B === 'boolean'
                ? this.isB2B
                : !!(cfg.isB2B);

            this.isOpen    = ko.observable(false);
            this.inputText = ko.observable('');
            this.isLoading = ko.observable(false);
            this.errorMsg  = ko.observable('');
            this.messages  = ko.observableArray([]);

            // LLM conversation history (role/content pairs, no UI state).
            this._history = [];

            if (this.welcomeMessage) {
                this.messages.push({
                    role: 'assistant',
                    content: this.welcomeMessage,
                    products: [],
                    confirmation: null
                });
            }

            var self = this;
            this.messages.subscribe(function () {
                self._scrollToBottom();
            });

            var coachCfg = this.guidedCoach || cfg.guidedCoach || {};
            guidedCoach.attach(this, coachCfg);
            this._bindGlobalA11yHandlers();

            return this;
        },

        toggleChat: function () {
            var willOpen = !this.isOpen();
            this.isOpen(willOpen);

            if (willOpen) {
                this._scrollToBottom();
                var el = document.getElementById('awa-ai-chat-input');
                if (el) {
                    requestAnimationFrame(function () { el.focus(); });
                }
                return;
            }

            var toggle = document.querySelector('#awa-ai-chat-root .awa-ai-chat__toggle');
            if (toggle) {
                requestAnimationFrame(function () { toggle.focus(); });
            }
        },

        _bindGlobalA11yHandlers: function () {
            var self = this;
            if (this._onDocumentKeydown) {
                return;
            }

            this._onDocumentKeydown = function (event) {
                if (!event || event.key !== 'Escape') {
                    return;
                }

                if (!self.isOpen()) {
                    return;
                }

                var root = document.getElementById('awa-ai-chat-root');
                if (!root || !root.contains(document.activeElement)) {
                    return;
                }

                self.toggleChat();
            };

            document.addEventListener('keydown', this._onDocumentKeydown);
        },

        handleKeydown: function (data, event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                this.sendMessage();
            }
            return true;
        },

        _formKey: function () {
            var fromInput = $('input[name="form_key"]').first().val() || '';
            var fromCookie = '';
            if ($.mage && $.mage.cookies && typeof $.mage.cookies.get === 'function') {
                fromCookie = $.mage.cookies.get('form_key') || '';
            } else if (mageCookies && typeof mageCookies.get === 'function') {
                fromCookie = mageCookies.get('form_key') || '';
            }
            return fromInput || fromCookie || '';
        },

        _clearConfirmation: function (msg) {
            var list = this.messages();
            var idx = list.indexOf(msg);
            if (idx === -1) {
                return;
            }
            this.messages.splice(idx, 1, {
                role: msg.role,
                content: msg.content,
                products: msg.products || [],
                confirmation: null
            });
        },

        _postJson: function (payload, onDone) {
            var key = this._formKey();
            var url = this.endpoint || '';
            if (key) {
                url += (url.indexOf('?') === -1 ? '?' : '&') + 'form_key=' + encodeURIComponent(key);
            }

            $.ajax({
                url: url,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(payload),
                dataType: 'json',
                timeout: 60000,
                headers: {
                    'X-Magento-Form-Key': key
                },
                success: function (response) {
                    onDone(null, response);
                },
                error: function (xhr) {
                    var msg = 'Não foi possível conectar ao assistente. Tente novamente.';
                    if (xhr && xhr.status === 403) {
                        msg = 'Sessão expirada. Recarregue a página e tente de novo.';
                    }
                    onDone(msg, xhr && xhr.responseJSON ? xhr.responseJSON : null);
                }
            });
        },

        sendMessage: function () {
            var text = (this.inputText() || '').trim();
            if (!text || this.isLoading()) {
                return;
            }

            this.errorMsg('');
            this.messages.push({ role: 'user', content: text, products: [], confirmation: null });
            this.inputText('');
            this.isLoading(true);
            this._scrollToBottom();

            var self = this;
            this._postJson({
                message: text,
                channel: this.channel,
                history: this._history
            }, function (err, response) {
                self.isLoading(false);
                if (err) {
                    self.errorMsg(err);
                    self.messages.push({ role: 'assistant', content: err, products: [], confirmation: null });
                    self._scrollToBottom();
                    return;
                }
                self._applyResponse(response);
            });
        },

        confirmWrite: function (msg) {
            var token = msg && msg.confirmation ? msg.confirmation.token : '';
            if (!token || this.isLoading()) {
                return;
            }
            this._clearConfirmation(msg);
            this.isLoading(true);
            this._scrollToBottom();

            var self = this;
            this._postJson({
                confirm_token: token,
                history: this._history
            }, function (err, response) {
                self.isLoading(false);
                if (err) {
                    self.errorMsg(err);
                    self.messages.push({ role: 'assistant', content: err, products: [], confirmation: null });
                    self._scrollToBottom();
                    return;
                }
                self._applyResponse(response);
            });
        },

        cancelWrite: function (msg) {
            this._clearConfirmation(msg);
            this.messages.push({
                role: 'assistant',
                content: 'Ação cancelada. Nada foi alterado.',
                products: [],
                confirmation: null
            });
            this._scrollToBottom();
        },

        _applyResponse: function (response) {
            if (response && response.history) {
                this._history = response.history;
            }
            if (response && response.reply) {
                this.messages.push({
                    role: 'assistant',
                    content: response.reply,
                    products: this._normalizeProducts(response.products),
                    confirmation: response.confirmation || null
                });
            } else if (response && response.error) {
                this.errorMsg(response.error);
                this.messages.push({
                    role: 'assistant',
                    content: response.error,
                    products: [],
                    confirmation: null
                });
            }
            this._scrollToBottom();
        },

        /**
         * Keep only product cards with usable name + url.
         * @param {*} raw
         * @returns {Array}
         */
        _normalizeProducts: function (raw) {
            if (!Array.isArray(raw)) {
                return [];
            }
            return raw.filter(function (p) {
                return p && typeof p === 'object' && !!(p.name || p.sku) && !!p.url;
            });
        },

        formatMessage: function (content) {
            if (!content) { return ''; }

            var safe = content
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');

            safe = safe.replace(
                /(https?:\/\/[^\s<>"']+)/g,
                '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
            );

            return safe.replace(/\n/g, '<br>');
        },

        _scrollToBottom: function () {
            var run = function () {
                var el = document.querySelector('.awa-ai-chat__messages');
                if (el) {
                    el.scrollTop = el.scrollHeight;
                }
            };
            requestAnimationFrame(function () {
                requestAnimationFrame(run);
            });
            setTimeout(run, 60);
            setTimeout(run, 280);
        }
    });
});
