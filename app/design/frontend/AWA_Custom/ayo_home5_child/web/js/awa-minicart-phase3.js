/**
 * AWA Motos — Minicart Fase 3: frete grátis dinâmico, cupom rápido, aria-expanded.
 */
define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'mage/translate',
    'domReady!'
], function ($, customerData, $t) {
    'use strict';

    /**
     * Clona blocos PHP (pedido mínimo, frete, cupom) para o slot KO após a lista de itens.
     * Usa clone (não move) para sobreviver a re-render KO quando o carrinho esvazia/enche.
     *
     * @param {HTMLElement} root
     */
    function mountAddons(root) {
        var source = document.getElementById('awa-minicart-addons-source');
        var mount = root.querySelector('#awa-minicart-addons-mount');
        var child;
        var i;

        if (!source || !mount || !source.childNodes.length) {
            return;
        }

        mount.innerHTML = '';

        for (i = 0; i < source.childNodes.length; i++) {
            child = source.childNodes[i];

            if (child.nodeType === 1 || child.nodeType === 3) {
                mount.appendChild(child.cloneNode(true));
            }
        }

        mount.dataset.mounted = '1';

        require(['mage/apply/main'], function (mageApply) {
            if (mageApply && typeof mageApply.apply === 'function') {
                mageApply.apply();
            }
        });
    }

    /**
     * Inicializa steppers de quantidade renderizados via Knockout.
     *
     * @param {HTMLElement} root
     */
    function initQtySteppers(root) {
        var steppers = root.querySelectorAll('.awa-minicart-qty-stepper:not([data-awa-qty-bound])');

        if (!steppers.length) {
            return;
        }

        require(['js/awa-qty-control', 'mage/apply/main'], function (qtyControl, mageApply) {
            steppers.forEach(function (stepper) {
                if (stepper.getAttribute('data-awa-qty-bound') === 'true') {
                    return;
                }

                qtyControl({}, stepper);
            });

            if (mageApply && typeof mageApply.apply === 'function') {
                mageApply.apply();
            }
        });
    }

    /**
     * @param {HTMLElement} root
     */
    function initMinicartPhase3(root) {
        if (!root) {
            return;
        }

        mountAddons(root);
        initQtySteppers(root);
        bindFreeShippingBar(root);
        bindCouponForm(root);
        syncAddonsVisibility(root);

        if (root.getAttribute('data-awa-phase3-bound') !== '1') {
            root.setAttribute('data-awa-phase3-bound', '1');

            if (!window.__awaMinicartPhase3CartSubscribed) {
                window.__awaMinicartPhase3CartSubscribed = true;
                customerData.get('cart').subscribe(function () {
                    var mc = document.querySelector('[data-block="minicart"]');
                    if (mc) {
                        syncAddonsVisibility(mc);
                    }
                });
            }
        }
    }

    /**
     * Exibe/oculta cupom e frete grátis conforme itens no carrinho (customer-data).
     *
     * @param {HTMLElement} root
     */
    function syncAddonsVisibility(root) {
        var cart = customerData.get('cart')();
        var hasItems = !!(cart && cart.summary_count);
        var coupon = root.querySelector('[data-role="awa-minicart-coupon"]');
        var shipping = root.querySelector('.awa-free-shipping-bar--minicart');
        var footer = root.querySelector('.awa-minicart-footer');

        if (coupon) {
            coupon.hidden = !hasItems;
        }
        if (shipping) {
            shipping.hidden = !hasItems;
        }
        /* Footer B2B (continuar + trust) permanece visível com carrinho vazio — ui-ux-pro-max */
        if (footer) {
            footer.hidden = false;
        }
    }

    /**
     * @param {HTMLElement} root
     */
    function bindFreeShippingBar(root) {
        var bar = root.querySelector('.awa-free-shipping-bar--minicart[data-awa-free-shipping-config]');

        if (!bar) {
            return;
        }

        var config;

        try {
            config = JSON.parse(bar.getAttribute('data-awa-free-shipping-config') || '{}');
        } catch (e) {
            return;
        }

        if (!config.active) {
            bar.hidden = true;
            return;
        }

        if (bar.dataset.awaShippingBound === '1') {
            return;
        }

        bar.dataset.awaShippingBound = '1';

        var messageEl = bar.querySelector('.awa-free-shipping-bar__message');
        var trackEl = bar.querySelector('.awa-free-shipping-bar__track');
        var fillEl = bar.querySelector('.awa-free-shipping-bar__fill');

        var render = function (subtotal) {
            var threshold = parseFloat(config.threshold) || 0;
            var current = parseFloat(subtotal) || 0;
            var percent = threshold > 0 ? Math.min(100, Math.round((current / threshold) * 100)) : 100;
            var reached = threshold > 0 && current >= threshold;
            var remaining = Math.max(0, threshold - current);

            bar.hidden = false;

            if (messageEl) {
                messageEl.classList.toggle('awa-free-shipping-bar__message--success', reached);
                if (reached) {
                    messageEl.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg> '
                        + $('<div/>').text(config.successMessage || $t('Parabéns! Você ganhou frete grátis.')).html();
                } else {
                    var remainingText = formatMoney(remaining);
                    var msg = (config.progressMessage || $t('Faltam %1 para frete grátis!')).replace('%1', remainingText);
                    messageEl.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 5v4h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg> '
                        + $('<div/>').text(msg).html();
                }
            }

            if (fillEl) {
                fillEl.style.width = percent + '%';
            }

            if (trackEl) {
                trackEl.setAttribute('aria-valuenow', String(percent));
            }
        };

        var cart = customerData.get('cart');

        cart.subscribe(function (data) {
            if (!data || !data.summary_count) {
                bar.hidden = true;
                return;
            }
            render(data.subtotalAmount);
        });

        render(cart().subtotalAmount);
    }

    /**
     * @param {number} amount
     * @returns {string}
     */
    function formatMoney(amount) {
        try {
            return new Intl.NumberFormat('pt-BR', {style: 'currency', currency: 'BRL'}).format(amount);
        } catch (e) {
            return 'R$ ' + amount.toFixed(2).replace('.', ',');
        }
    }

    /**
     * @param {HTMLElement} root
     */
    function bindCouponForm(root) {
        var wrapper = root.querySelector('[data-role="awa-minicart-coupon"]');

        if (!wrapper) {
            return;
        }

        var form = wrapper.querySelector('#awa-minicart-coupon-form');
        var feedback = wrapper.querySelector('.awa-minicart-coupon__feedback');
        var removeBtn = wrapper.querySelector('[data-action="remove-coupon"]');

        if (!form || form.dataset.awaCouponBound === '1') {
            return;
        }

        form.dataset.awaCouponBound = '1';

        var syncFormKey = function ($form) {
            var formKey = ($.mage && $.mage.cookies) ? $.mage.cookies.get('form_key') : '';
            if (formKey) {
                $form.find('[name="form_key"]').val(formKey);
            }
        };

        var setFeedback = function (text, isError) {
            if (!feedback) {
                return;
            }
            feedback.hidden = !text;
            feedback.textContent = text || '';
            feedback.classList.toggle('awa-minicart-coupon__feedback--error', !!isError);
            feedback.classList.toggle('awa-minicart-coupon__feedback--success', !!text && !isError);
        };

        var reloadCart = function () {
            customerData.invalidate(['cart']);
            customerData.reload(['cart'], true);
            $(document).trigger('ajax:updateCart');
        };

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var $form = $(form);
            var code = ($form.find('[name="coupon_code"]').val() || '').trim();

            if (!code) {
                setFeedback($t('Informe o código do cupom.'), true);
                return;
            }

            syncFormKey($form);
            $form.find('.awa-minicart-coupon__remove').val('0');
            setFeedback($t('Aplicando cupom…'), false);

            $.ajax({
                url: form.action,
                type: 'POST',
                data: $form.serialize(),
                showLoader: true
            }).done(function () {
                setFeedback($t('Cupom aplicado com sucesso.'), false);
                reloadCart();
            }).fail(function () {
                setFeedback($t('Cupom inválido ou expirado.'), true);
            });
        });

        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                var $form = $(form);

                syncFormKey($form);
                $form.find('.awa-minicart-coupon__remove').val('1');
                $form.find('[name="coupon_code"]').val('');
                setFeedback($t('Removendo cupom…'), false);

                $.ajax({
                    url: form.action,
                    type: 'POST',
                    data: $form.serialize(),
                    showLoader: true
                }).done(function () {
                    setFeedback($t('Cupom removido.'), false);
                    reloadCart();
                }).fail(function () {
                    setFeedback($t('Não foi possível remover o cupom.'), true);
                });
            });
        }
    }

    return function (config, element) {
        initMinicartPhase3(element);

        document.addEventListener('contentUpdated', function () {
            var root = document.querySelector('[data-block="minicart"]');
            if (root) {
                mountAddons(root);
                initQtySteppers(root);
                bindFreeShippingBar(root);
                bindCouponForm(root);
                syncAddonsVisibility(root);
            }
        });
    };
});
