define([
    'jquery',
    'mage/cookies'
], function ($) {
    'use strict';

    return function (config, element) {
        var $root = $(element);
        let addBySkuUrl = String(config.addBySkuUrl || '');
        let filterUrl = String(config.filterUrl || '');
        let opportunityUrl = String(config.opportunityUrl || '');
        let currentFilterPage = 1;

        if (!$root.length || !addBySkuUrl || !filterUrl || !opportunityUrl) {
            return;
        }

        let oppLabels = {
            monthly: 'Mensal',
            quarterly_not_bought: 'Trim. Nao Comprou',
            quarterly_bought: 'Trim. Comprou',
            expansion_monthly: 'Expansao Mensal',
            expansion_quarterly: 'Expansao Trim.',
            irregular: 'Irregular',
            churn: 'Churn',
            cross_sell: 'Cross-sell'
        };

        function getFormKey()
        {
            return $.mage && $.mage.cookies ? $.mage.cookies.get('form_key') : '';
        }

        function formatPrice(value)
        {
            return 'R$ ' + Number(value || 0).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatDate(dateStr)
        {
            if (!dateStr) {
                return '-';
            }
            try {
                let d = new Date(dateStr);
                if (isNaN(d.getTime())) {
                    return String(dateStr).substring(0, 10);
                }
                return d.toLocaleDateString('pt-BR');
            } catch (e) {
                return String(dateStr).substring(0, 10);
            }
        }

        function escapeHtml(str)
        {
            if (!str) {
                return '';
            }
            let div = document.createElement('div');
            div.appendChild(document.createTextNode(String(str)));
            return div.innerHTML;
        }

        function addToCart(sku, qty)
        {
            return new Promise(function (resolve, reject) {
                $.ajax({
                    url: addBySkuUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        sku: sku,
                        qty: qty,
                        form_key: getFormKey()
                    }
                }).done(function (response) {
                    if (response && response.success) {
                        resolve(response);
                        return;
                    }
                    reject(response && response.message ? response.message : 'Error');
                }).fail(function () {
                    reject('Network error');
                });
            });
        }

        function renderPagination(currentPage, totalPages)
        {
            if (totalPages <= 1) {
                return;
            }

            var $pag = $('#erp-filter-pagination');
            let html = '';

            if (currentPage > 1) {
                html += '<button class="erp-btn erp-btn-secondary erp-btn-small erp-pag-btn" data-page="' + (currentPage - 1) + '">Anterior</button>';
            }

            html += '<span class="erp-pag-info">Pagina ' + currentPage + ' de ' + totalPages + '</span>';

            if (currentPage < totalPages) {
                html += '<button class="erp-btn erp-btn-secondary erp-btn-small erp-pag-btn" data-page="' + (currentPage + 1) + '">Proxima</button>';
            }

            $pag.html(html);
            $pag.find('.erp-pag-btn').on('click', function () {
                currentFilterPage = parseInt($(this).data('page'), 10);
                loadFilteredHistory();
            });
        }

        function renderFilteredItems(items)
        {
            var $list = $('#erp-filter-results-list');
            $list.empty();

            items.forEach(function (item) {
                let sku = item.codigo_material || item.sku || '';
                let name = (item.magento && item.magento.name) ? item.magento.name : (item.descricao || item.name || sku);
                let price = item.avg_price || (item.magento && item.magento.final_price) || 0;
                let orderCount = item.order_count || item.vezes_comprado || 0;
                let totalQty = item.total_qty || item.quantidade_total || 0;
                let daysSince = item.days_since_last || '';
                let lastDate = item.last_order_date || item.ultima_compra || '';
                let hasUrl = item.magento && (item.magento.product_url || item.magento.url_key);
                let url = (item.magento && item.magento.product_url) ? item.magento.product_url : (hasUrl ? '/' + item.magento.url_key + '.html' : '#');
                let inStore = item.available_in_store;

                let html = '<div class="erp-fh-item' + (!inStore ? ' erp-fh-item-unavailable' : '') + '">';
                html += '<div class="erp-fh-item-check">';
                if (inStore) {
                    html += '<input type="checkbox" class="erp-fh-item-select" value="' + escapeHtml(sku) + '">';
                }
                html += '</div>';
                html += '<div class="erp-fh-item-info">';
                html += '<div class="erp-fh-item-main">';
                html += '<span class="erp-item-sku">' + escapeHtml(sku) + '</span>';
                if (item.opportunity_type && oppLabels[item.opportunity_type]) {
                    html += '<span class="erp-opp-badge erp-opp-' + item.opportunity_type.replace(/_/g, '-') + '">'
                        + escapeHtml(oppLabels[item.opportunity_type]) + '</span>';
                }
                html += '<a href="' + escapeHtml(url) + '" class="erp-item-name">' + escapeHtml(name) + '</a>';
                if (!inStore) {
                    html += '<span class="erp-fh-unavailable-badge">Indisponivel na loja</span>';
                }
                html += '</div>';
                html += '<div class="erp-fh-item-meta">';
                html += '<span class="erp-meta-item"><strong>' + orderCount + '</strong> pedidos</span>';
                html += '<span class="erp-meta-item"><strong>' + totalQty + '</strong> unidades</span>';
                if (daysSince) {
                    html += '<span class="erp-meta-item">Ha <strong>' + daysSince + '</strong> dias</span>';
                }
                if (lastDate) {
                    html += '<span class="erp-meta-item">Ultima: ' + formatDate(lastDate) + '</span>';
                }
                html += '</div>';
                html += '</div>';
                html += '<div class="erp-fh-item-price">' + formatPrice(parseFloat(price)) + '</div>';
                if (inStore) {
                    html += '<div class="erp-fh-item-qty">';
                    html += '<input type="number" class="erp-fh-qty-input" value="1" min="1" max="999">';
                    html += '</div>';
                    html += '<div class="erp-fh-item-action">';
                    html += '<button type="button" class="erp-btn erp-btn-outline erp-btn-small erp-fh-add-one" data-sku="' + escapeHtml(sku) + '">Adicionar</button>';
                    html += '</div>';
                } else {
                    html += '<div class="erp-fh-item-qty"></div>';
                    html += '<div class="erp-fh-item-action"></div>';
                }
                html += '</div>';

                $list.append(html);
            });

            $list.find('.erp-fh-add-one').on('click', function () {
                var $btn = $(this);
                let sku = $btn.data('sku');
                let qty = parseInt($btn.closest('.erp-fh-item').find('.erp-fh-qty-input').val(), 10) || 1;

                $btn.prop('disabled', true).text('...');
                addToCart(sku, qty).then(function () {
                    $btn.text('OK');
                    window.setTimeout(function () {
                        $btn.prop('disabled', false).text('Adicionar');
                    }, 2000);
                }).catch(function () {
                    $btn.prop('disabled', false).text('Adicionar');
                    window.alert('Erro ao adicionar.');
                });
            });

            $list.on('change', '.erp-fh-item-select', function () {
                let hasChecked = $list.find('.erp-fh-item-select:checked').length > 0;
                $('#erp-add-filtered-to-cart').toggle(hasChecked);
            });
        }

        function loadFilteredHistory()
        {
            let freqVal = String($('#erp-filter-freq').val() || '0-0').split('-');
            let oppType = $('#erp-filter-opportunity').val();
            let params = {
                period_days: $('#erp-filter-period').val(),
                min_freq: freqVal[0],
                max_freq: freqVal[1],
                min_price: $('#erp-filter-min-price').val() || 0,
                max_price: $('#erp-filter-max-price').val() || 0,
                sort_by: $('#erp-filter-sort').val(),
                sort_dir: $('#erp-filter-sort-dir').val(),
                page: currentFilterPage,
                limit: 20
            };

            let requestUrl = filterUrl;
            if (oppType) {
                requestUrl = opportunityUrl;
                params.opportunity_type = oppType;
            }

            $('#erp-filter-loading').show();
            $('#erp-filter-results').show();
            $('#erp-filter-empty').hide();
            $('#erp-filter-results-list').empty();
            $('#erp-filter-pagination').empty();

            $.ajax({
                url: requestUrl,
                type: 'GET',
                data: params,
                dataType: 'json'
            }).done(function (response) {
                $('#erp-filter-loading').hide();

                if (!response.success) {
                    $('#erp-filter-empty').show().text(response.message || 'Erro ao consultar.');
                    $('#erp-filter-results').hide();
                    return;
                }

                if (!response.items || !response.items.length) {
                    $('#erp-filter-empty').show();
                    $('#erp-filter-results').hide();
                    return;
                }

                $('#erp-results-count').text(response.total_count + ' produtos encontrados');
                $('#erp-add-filtered-to-cart').toggle(response.items.length > 0);
                renderFilteredItems(response.items);
                renderPagination(response.page, response.total_pages);
            }).fail(function () {
                $('#erp-filter-loading').hide();
                $('#erp-filter-empty').show().text('Erro de conexao. Tente novamente.');
            });
        }

        $('#erp-filter-toggle').on('click', function () {
            var $body = $('#erp-filter-body');
            var $icon = $('#erp-toggle-icon');
            let isVisible = $body.is(':visible');
            $body.slideToggle(200);
            $icon.text(isVisible ? '▶' : '▼');
        });

        $('#erp-btn-filter').on('click', function () {
            currentFilterPage = 1;
            loadFilteredHistory();
        });

        $('#erp-btn-clear-filter').on('click', function () {
            $('#erp-filter-opportunity').val('');
            $('#erp-filter-period').val('365');
            $('#erp-filter-freq').val('0-0');
            $('#erp-filter-min-price').val('');
            $('#erp-filter-max-price').val('');
            $('#erp-filter-sort').val('days_since_last');
            $('#erp-filter-sort-dir').val('ASC');
            currentFilterPage = 1;
            $('#erp-filter-results').hide();
            $('#erp-filter-empty').hide();
        });

        $('#erp-add-filtered-to-cart').on('click', function () {
            var $btn = $(this);
            let items = [];

            $('#erp-filter-results-list .erp-fh-item-select:checked').each(function () {
                var $row = $(this).closest('.erp-fh-item');
                items.push({
                    sku: $(this).val(),
                    qty: parseInt($row.find('.erp-fh-qty-input').val(), 10) || 1
                });
            });

            if (!items.length) {
                window.alert('Selecione pelo menos um produto.');
                return;
            }

            $btn.prop('disabled', true).text('Adicionando...');

            Promise.all(items.map(function (item) {
                return addToCart(item.sku, item.qty);
            })).then(function () {
                $btn.text('Adicionado');
                window.setTimeout(function () {
                    $btn.prop('disabled', false).text('Adicionar Selecionados');
                }, 2000);
            }).catch(function () {
                window.alert('Erro ao adicionar produtos. Tente novamente.');
                $btn.prop('disabled', false).text('Adicionar Selecionados');
            });
        });
    };
});
