/**
 * Social Proof PDP — carrega badges de prova social via AJAX.
 *
 * Permite que a PDP seja totalmente cacheável no FPC/Varnish (~30ms TTFB)
 * enquanto os badges continuam exibindo dados reais com cache de 10 minutos.
 *
 * Conformidade com CDC Art. 37 — somente dados reais, sem simulação.
 */
define(['jquery'], function ($) {
    'use strict';

    return function (config) {
        const container = document.getElementById('awa-social-proof-pdp');
        if (!container) {
            return;
        }

        const productId = parseInt(container.getAttribute('data-product-id'), 10);
        if (!productId) {
            return;
        }

        const endpoint = config.baseUrl + 'socialproof/product/data?product_id=' + productId;

        fetch(endpoint, { credentials: 'omit' })
            .then(function (res) {
                return res.json(); })
            .then(function (data) {
                /**
                 * Build badges via DOM API to avoid XSS from concatenated HTML.
                 * Numeric values from the API are safe, but label attributes from
                 * data-* are server-rendered strings — DOM textContent handles escaping.
                 */
                function makeBadge(extraClass, ariaLabel, iconClass, contentEl)
                {
                    const div = document.createElement('div');
                    div.className = 'social-proof-badge ' + extraClass;
                    div.setAttribute('role', 'note');
                    div.setAttribute('aria-label', ariaLabel);
                    const icon = document.createElement('i');
                    icon.className = 'fa ' + iconClass;
                    icon.setAttribute('aria-hidden', 'true');
                    div.appendChild(icon);
                    div.appendChild(contentEl);
                    return div;
                }

                function makeSpan(strongText, suffixText)
                {
                    const span = document.createElement('span');
                    span.className = 'badge-text';
                    const strong = document.createElement('strong');
                    strong.textContent = strongText;
                    span.appendChild(strong);
                    if (suffixText) {
                        span.appendChild(document.createTextNode(' ' + suffixText));
                    }
                    return span;
                }

                const fragment = document.createDocumentFragment();

                if (data.views_today > 0) {
                    fragment.appendChild(makeBadge(
                        'views-badge',
                        'Visualizações do produto',
                        'fa-eye',
                        makeSpan(String(data.views_today), container.getAttribute('data-label-views'))
                    ));
                }

                if (data.low_stock) {
                    const lowStockText = container.getAttribute('data-label-low-stock-pre')
                        + ' ' + String(data.qty) + ' '
                        + container.getAttribute('data-label-low-stock-suf');
                    fragment.appendChild(makeBadge(
                        'low-stock-badge urgency',
                        'Alerta de baixo estoque',
                        'fa-exclamation-triangle',
                        makeSpan(lowStockText, container.getAttribute('data-label-stock-msg'))
                    ));
                }

                if (data.is_best_seller) {
                    fragment.appendChild(makeBadge(
                        'bestseller-badge',
                        'Produto mais vendido',
                        'fa-star',
                        makeSpan(container.getAttribute('data-label-bestseller'), null)
                    ));
                }

                if (fragment.childNodes.length > 0) {
                    container.innerHTML = ''; // safe — clearing own container before appending
                    container.appendChild(fragment);
                    container.classList.add('awa-sp-pdp');
                }
            })
            .catch(function () {
                // silently fail — social proof is non-critical
            });
    };
});
