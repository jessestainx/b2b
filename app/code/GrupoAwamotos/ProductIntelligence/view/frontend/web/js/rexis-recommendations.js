/**
 * Product Intelligence - Real-time Recommendations Module
 *
 * Usage:
 * require(['rexisRecommendations'], function(rexis) {
 *     rexis.load({
 *         container: '#my-recommendations',
 *         classificacao: 'Oportunidade Cross-sell',
 *         limit: 4,
 *         onSuccess: function(data) {
 *             console.log('Loaded', data.total, 'recommendations');
 *         }
 *     });
 * });
 */
define([
    'jquery',
    'mage/url',
    'mage/template',
    'mage/storage'
], function ($, urlBuilder, mageTemplate, storage) {
    'use strict';

    let defaultTemplate =
        '<% _.each(recommendations, function(item) { %>' +
        '   <div class="rexis-ajax-item" data-product-id="<%= item.product_id %>">' +
        '       <div class="rexis-ajax-image">' +
        '           <a href="<%= item.url %>">' +
        '               <img src="<%= item.image %>" alt="<%= item.name %>" loading="lazy" />' +
        '           </a>' +
        '       </div>' +
        '       <div class="rexis-ajax-details">' +
        '           <h4><a href="<%= item.url %>"><%= item.name %></a></h4>' +
        '           <div class="rexis-ajax-price"><%= item.price %></div>' +
        '           <% if (item.score >= 85) { %>' +
        '               <div class="rexis-ajax-score"><%= Math.round(item.score) %>% Match</div>' +
        '           <% } %>' +
        '           <button class="action primary rexis-ajax-addtocart" data-sku="<%= item.sku %>">' +
        '               Adicionar ao Carrinho' +
        '           </button>' +
        '       </div>' +
        '   </div>' +
        '<% }); %>';

    return {
        /**
         * Load recommendations via AJAX
         *
         * @param {Object} options
         */
        load: function (options) {
            let settings = $.extend({
                container: '#rexis-recommendations',
                classificacao: null,
                limit: 4,
                minScore: 0.7,
                template: null,
                showLoader: true,
                onSuccess: null,
                onError: null
            }, options);

            var $container = $(settings.container);
            if ($container.length === 0) {
                console.warn('Product Intelligence: Container not found -', settings.container);
                return;
            }

            // Show loader
            if (settings.showLoader) {
                $container.html('<div class="rexis-ajax-loader">🔄 Carregando recomendações...</div>');
            }

            // Build URL with params
            let url = urlBuilder.build('rexisml/ajax/getrecommendations');
            let params = {
                limit: settings.limit,
                minScore: settings.minScore
            };

            if (settings.classificacao) {
                params.classificacao = settings.classificacao;
            }

            // AJAX request
            $.ajax({
                url: url,
                type: 'GET',
                data: params,
                dataType: 'json',
                success: function (response) {
                    if (response.success && response.recommendations.length > 0) {
                        let template = settings.template || defaultTemplate;
                        let compiled = mageTemplate(template);
                        let html = compiled({
                            recommendations: response.recommendations
                        });

                        $container.html(html);

                        // Bind add to cart
                        $container.find('.rexis-ajax-addtocart').on('click', function (e) {
                            e.preventDefault();
                            var $btn = $(this);
                            let productId = $btn.closest('.rexis-ajax-item').data('product-id');
                            let formKey = $.mage && $.mage.cookies ? $.mage.cookies.get('form_key') : '';

                            $btn.prop('disabled', true);

                            $.ajax({
                                url: urlBuilder.build('checkout/cart/add/'),
                                type: 'POST',
                                dataType: 'json',
                                data: {
                                    product: productId,
                                    qty: 1,
                                    form_key: formKey
                                },
                                success: function () {
                                    $('[data-block="minicart"]').trigger('contentLoading');
                                },
                                complete: function () {
                                    $btn.prop('disabled', false);
                                }
                            });
                        });

                        if (settings.onSuccess) {
                            settings.onSuccess(response);
                        }
                    } else {
                        $container.html('<div class="rexis-ajax-empty">Nenhuma recomendação disponível no momento.</div>');
                    }
                },
                error: function (xhr, status, error) {
                    $container.html('<div class="rexis-ajax-error">Erro ao carregar recomendações.</div>');

                    if (settings.onError) {
                        settings.onError(error);
                    }

                    console.error('Product Intelligence Error:', error);
                }
            });
        },

        /**
         * Refresh recommendations in container
         */
        refresh: function (container) {
            var $container = $(container);
            let options = $container.data('rexis-options') || {};
            options.container = container;
            this.load(options);
        },

        /**
         * Track recommendation view (for analytics)
         */
        trackView: function (productId, score) {
            // Analytics tracking — silent by design
        },

        /**
         * Track recommendation click
         */
        trackClick: function (productId, score) {
            // Analytics tracking — silent by design
        }
    };
});
