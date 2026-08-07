/**
 * Carrinho: aguarda customer-data defer antes de resolver endereço de estimativa.
 * Evita TypeError em customer-data.js (storage.set) quando checkout-data roda cedo demais.
 *
 * Também força estimate BR só com CEP: oculta país/UF/cidade no fieldset.
 * (region customEntry reaparece se só region_id.visible=false no jsLayout.)
 *
 * IMPORTANTE: _super é injetado pelo wrapper.js apenas durante a execução síncrona.
 * Deve ser capturado ANTES de qualquer chamada assíncrona (Promise/setTimeout).
 */
define([
    'js/awa-customer-sections-gate'
], function (whenCustomerSectionsReady) {
    'use strict';

    var HIDDEN_ESTIMATE_FIELDS = {
        country_id: true,
        region_id: true,
        region: true,
        city: true
    };

    /**
     * @param {Object} fieldset
     */
    function hideBrazilOnlyEstimateFields(fieldset) {
        var elems;

        if (!fieldset || typeof fieldset.elems !== 'function') {
            return;
        }

        elems = fieldset.elems();

        if (!elems || !elems.length) {
            return;
        }

        elems.forEach(function (field) {
            if (!field || !HIDDEN_ESTIMATE_FIELDS[field.index]) {
                return;
            }

            if (typeof field.visible === 'function') {
                field.visible(false);
            }

            if (field.index === 'country_id' && typeof field.value === 'function' && !field.value()) {
                field.value('BR');
            }
        });
    }

    return function (Component) {
        return Component.extend({
            /**
             * @inheritdoc
             */
            initialize: function () {
                if (!document.getElementById('awa-customer-sections-defer-json')) {
                    return this._super();
                }

                var self = this;
                // Captura _super enquanto ainda está disponível (síncrono).
                // wrapper.js deleta this._super logo após o return desta função.
                var superInitialize = this._super;

                whenCustomerSectionsReady(function () {
                    superInitialize.call(self);
                });

                return this;
            },

            /**
             * @inheritdoc
             */
            initElement: function (element) {
                this._super(element);

                if (element && element.index === 'address-fieldsets') {
                    hideBrazilOnlyEstimateFields(element);

                    if (typeof element.elems.subscribe === 'function') {
                        element.elems.subscribe(function () {
                            hideBrazilOnlyEstimateFields(element);
                        });
                    }
                }

                return this;
            }
        });
    };
});
