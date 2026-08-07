/**
 * Carrinho — abre estimativa de frete quando ainda não há CEP (menos um clique B2B).
 */
define(['jquery', 'domReady!'], function ($) {
    'use strict';

    var POSTCODE_SELECTORS = [
        '#block-summary input[name="postcode"]',
        '#cart-shipping-zip input[name="postcode"]',
        'input[name="estimate_postcode"]'
    ];

    /**
     * @returns {string}
     */
    function getPostcodeValue() {
        var i;
        var el;
        var val;

        for (i = 0; i < POSTCODE_SELECTORS.length; i++) {
            el = document.querySelector(POSTCODE_SELECTORS[i]);

            if (!el) {
                continue;
            }

            val = String(el.value || '').trim();

            if (val) {
                return val;
            }
        }

        return '';
    }

    /**
     * Abre bloco colapsável sem collapsible('activate').
     * O widget Magento dispara dimensionsChanged → scrollIntoView no .title,
     * empurrando o carrinho ~1200px no mobile e escondendo título/resumo.
     */
    function activateCollapsibleBlock(selector) {
        var $block = $(selector);
        var $trigger;
        var $content;

        if (!$block.length || $block.hasClass('active')) {
            return;
        }

        $trigger = $block.find('[data-role="trigger"]').first();
        $content = $block.find('[data-role="content"]').first();

        $block.addClass('active');
        $block.attr('aria-expanded', 'true');

        if ($trigger.length) {
            $trigger.attr('aria-expanded', 'true');
        }

        /* title usa data-role=title (não trigger) no carrinho Magento */
        $block.find('[data-role="title"]').attr({
            'aria-expanded': 'true',
            'aria-selected': 'true'
        });

        if ($content.length) {
            $content.show().attr('aria-hidden', 'false');
        }
    }

    function activateShippingBlock() {
        activateCollapsibleBlock('#block-shipping');
    }

    /**
     * @param {number} retries
     */
    function tryOpenShipping(retries) {
        if (!document.body.classList.contains('checkout-cart-index')) {
            return;
        }

        if (getPostcodeValue()) {
            return;
        }

        if (!$('#block-shipping').length) {
            if (retries > 0) {
                window.setTimeout(function () {
                    tryOpenShipping(retries - 1);
                }, 400);
            }

            return;
        }

        activateShippingBlock();
    }

    /**
     * Observa DOM em vez de polling (menos timers na main thread).
     */
    function watchShippingBlock() {
        if (!document.body.classList.contains('checkout-cart-index') || getPostcodeValue()) {
            return;
        }

        if (document.getElementById('block-shipping')) {
            tryOpenShipping(0);
            return;
        }

        var observer = new MutationObserver(function () {
            if (document.getElementById('block-shipping')) {
                observer.disconnect();
                tryOpenShipping(0);
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        window.setTimeout(function () {
            observer.disconnect();
        }, 8000);
    }

    return function () {
        watchShippingBlock();

        $(document).on('contentUpdated.awaCartSummaryPolish', function () {
            window.setTimeout(function () {
                tryOpenShipping(0);
            }, 150);
        });
    };
});
