define([
    'jquery',
    'underscore'
], function ($, _) {

    var TypeAhead = function (input) {
        this.$input = $(input);
        this.storage = null;
        this.suggestKey = null;
        this.placeholderHtml = '<input value="" class="input-text mst-search-autocomplete__typeahead-overlay" type="text" />';
        this.$placeholder = $(this.placeholderHtml);
    };

    TypeAhead.prototype = {
        init: function (config) {
            this.config = _.defaults(config, this.defaults);
            this.$input.prop('style','background:transparent!important');
            if (this.$input.val().length >= this.config.minSuggestLength) {
                this.retrieveTypeaheadStorage();
            }

            this.$input.on("keyup", function (event) {
                this.keyupHandler(event)
            }.bind(this));

            this.$input.on("click focus", function () {
                this.clickHandler()
            }.bind(this));

            this.$input.on("input", function () {
                this.inputHandler()
            }.bind(this));
        },

        keyupHandler: function (event) {
            if (event.key === 'ArrowRight' || event.keyCode === 39) {
                this.completeQuery();
            }
        },

        clickHandler: function (event) {
            this.suggest();
            this.ensurePosition();
        },

        inputHandler: function () {
            this.suggest();
            this.ensurePosition();
        },

        suggest: function () {
            if (!this.$input) {
                return;
            }

            this.$placeholder.val('');
            this.$placeholder.remove();

            var inputLength = this.$input.val().length;
            var moreOrEqualsMinSuggestLength = inputLength >= this.config.minSuggestLength;
            if (!moreOrEqualsMinSuggestLength) {
                return;
            }
            var emptyStorage = !this.storage || this.storage.length === 0;
            var suggestKeyMatches = this.$input.val().indexOf(this.suggestKey) === 0;
            var moreOrEqualsMinSearchLength = inputLength >= this.config.minSearchLength;

            if (emptyStorage) {
                if (moreOrEqualsMinSuggestLength) {
                    this.retrieveTypeaheadStorage();
                }
            } else {
                if (suggestKeyMatches) {
                    if (moreOrEqualsMinSearchLength) {
                        this._doSuggest();
                        this.ensurePosition();
                    }
                } else {
                    this.retrieveTypeaheadStorage();
                }
            }
        },

        _doSuggest: function () {
            $.each(JSON.parse(this.storage.replace("/", "")), function (i, item) {
                this.$placeholder.remove();
                if (typeof item === 'string' || item instanceof String) {
                    if (item.indexOf(this.$input.val().toLowerCase()) === 0) {
                        this.$input.parent().after(this.$placeholder);
                        this.$placeholder.val(item.replace(this.$input.val().toLowerCase(), this.$input.val()));
                        return false;
                    }
                }
            }.bind(this));
        },

        completeQuery: function () {
            if (this.$placeholder.val().length >= this.config.minSearchLength) {
                this.$input.val(this.$placeholder.val());
                this.$input.trigger("input");
            }
        },

        retrieveTypeaheadStorage: function () {
            var self = this;
            var q = (this.$input.val() || '').toLowerCase();
            var delay = Math.max(0, parseInt(this.config.delay, 10) || 150);

            if (!q || q.length < (this.config.minSuggestLength || 2)) {
                return;
            }

            // AWA: debounce + dedupe — evita GETs duplicados (focus+input / digitação rápida).
            if (this._awaTypeaheadTimer) {
                clearTimeout(this._awaTypeaheadTimer);
            }
            if (this._awaTypeaheadInflight === q) {
                return;
            }

            this._awaTypeaheadTimer = setTimeout(function () {
                self._awaTypeaheadTimer = null;
                if (self._awaTypeaheadInflight === q) {
                    return;
                }
                self._awaTypeaheadInflight = q;
                $.ajax({
                    url: self.config.typeaheadUrl,
                    dataType: 'json',
                    type: 'GET',
                    data: {
                        q: q,
                        store_id: self.config.storeId
                    },
                    success: function (data) {
                        if (data !== '') {
                            self.storage = JSON.stringify(data);
                            self.suggestKey = q.substring(0, 2).toLowerCase();
                        }
                    },
                    complete: function () {
                        if (self._awaTypeaheadInflight === q) {
                            self._awaTypeaheadInflight = null;
                        }
                    }
                });
            }, delay);
        },

        ensurePosition: function () {
            var position = this.$input.position();
            var left = position.left + 1 + parseInt(this.$input.css('marginLeft'), 10);
            var top = position.top + parseInt(this.$input.css('marginTop'), 10);

            this.$placeholder
                .css('top', top)
                .css('left', left)
                .css('width', this.$input.outerWidth());
        }
    };

    return TypeAhead;
});
