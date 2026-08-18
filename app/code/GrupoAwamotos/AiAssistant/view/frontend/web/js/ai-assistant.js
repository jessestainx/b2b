/**
 * AWA AI Assistant — Knockout/UIComponent
 * Magento 2 storefront chat widget (RequireJS + Knockout + uiComponent)
 */
define([
    'ko',
    'uiComponent',
    'jquery',
    'GrupoAwamotos_AiAssistant/js/guided-coach'
], function (ko, Component, $, guidedCoach) {
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
                this.messages.push({ role: 'assistant', content: this.welcomeMessage, products: [] });
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

        sendMessage: function () {
            var text = (this.inputText() || '').trim();
            if (!text || this.isLoading()) {
                return;
            }

            this.errorMsg('');
            this.messages.push({ role: 'user', content: text, products: [] });
            this.inputText('');
            this.isLoading(true);
            this._scrollToBottom();

            var self    = this;
            var payload = JSON.stringify({
                message: text,
                channel: this.channel,
                history: this._history
            });

            $.ajax({
                url:         this.endpoint,
                type:        'POST',
                contentType: 'application/json',
                data:        payload,
                dataType:    'json',
                timeout:     60000,
                success: function (response) {
                    self.isLoading(false);
                    if (response && response.history) {
                        self._history = response.history;
                    }
                    if (response && response.reply) {
                        var products = self._normalizeProducts(response.products);
                        self.messages.push({
                            role: 'assistant',
                            content: response.reply,
                            products: products
                        });
                    } else if (response && response.error) {
                        self.errorMsg(response.error);
                        self.messages.push({ role: 'assistant', content: response.error, products: [] });
                    }
                    self._scrollToBottom();
                },
                error: function () {
                    self.isLoading(false);
                    var msg = 'Não foi possível conectar ao assistente. Tente novamente.';
                    self.errorMsg(msg);
                    self.messages.push({ role: 'assistant', content: msg, products: [] });
                    self._scrollToBottom();
                }
            });
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
