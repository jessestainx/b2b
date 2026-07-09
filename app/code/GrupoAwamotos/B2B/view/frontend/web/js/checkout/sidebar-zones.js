/**
 * Agrupa blocos extras da sidebar OPC em seção colapsável.
 *
 * @module GrupoAwamotos_B2B/js/checkout/sidebar-zones
 */
define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    var SIDEBAR_SELECTOR = '#opc-sidebar, .opc-sidebar';
    var SUMMARY_SELECTOR = '.opc-block-summary';
    var EXTRAS_WRAPPER_CLASS = 'awa-opc-sidebar__extras';
    var EXTRAS_ZONE_CLASS = 'awa-opc-sidebar__zone--extras';
    var ZONED_ATTR = 'data-awa-opc-sidebar-zoned';

    /**
     * Seletores de blocos extras (após resumo/totais).
     *
     * @type {string[]}
     */
    var EXTRA_BLOCK_SELECTORS = [
        '.payment-option.discount',
        '[data-bind*="rokanthemes_opc_order_comment"]',
        '.field[name*="subscribe"]',
        '.gift-message',
        '[data-role="checkout-agreements"]',
        '.checkout-agreements',
        '.opc-block-agreements',
        '.whatsapp-opt-in',
        '[class*="whatsapp"]'
    ];

    function isCheckoutPage()
    {
        if (!document.body) {
            return false;
        }

        return document.body.classList.contains('checkout-index-index') ||
            document.body.classList.contains('rokanthemes-onepagecheckout') ||
            document.body.classList.contains('onepagecheckout-index-index');
    }

    /**
     * @param {jQuery} $sidebar
     * @returns {jQuery}
     */
    function findSummary($sidebar)
    {
        return $sidebar.find(SUMMARY_SELECTOR).first();
    }

    /**
     * @param {jQuery} $summary
     * @returns {jQuery}
     */
    function findTotalsAnchor($summary)
    {
        var $totals = $summary.find('.table-totals, .opc-block-summary-totals').last();

        if ($totals.length) {
            return $totals;
        }

        return $summary.find('.minicart-items').last();
    }

    /**
     * @param {jQuery} $sidebar
     * @param {jQuery} $summary
     * @returns {jQuery[]}
     */
    function collectExtraBlocks($sidebar, $summary)
    {
        var $blocks = $();
        var seen = {};

        EXTRA_BLOCK_SELECTORS.forEach(function (selector) {
            $sidebar.find(selector).each(function () {
                var el = this;
                var key = el.getAttribute('data-bind') || el.className || selector;

                if (seen[key] || $.contains($summary[0], el)) {
                    return;
                }

                if ($(el).closest('.' + EXTRAS_WRAPPER_CLASS).length) {
                    return;
                }

                seen[key] = true;
                $blocks = $blocks.add(el);
            });
        });

        // Fallback: siblings after summary inside sidebar (exceto place-order toolbar)
        if (!$blocks.length) {
            $summary.nextAll().each(function () {
                var $el = $(this);

                if ($el.find('.action.primary.checkout, .btn-placeorder').length) {
                    return;
                }

                if ($el.hasClass('actions-toolbar') || $el.is('[data-role="opc-summary-sticky"]')) {
                    return;
                }

                $blocks = $blocks.add(this);
            });
        }

        return $blocks.toArray().map(function (el) {
            return $(el);
        });
    }

    /**
     * @param {jQuery} $sidebar
     */
    function applyZones($sidebar)
    {
        var $summary = findSummary($sidebar);

        if (!$summary.length) {
            $sidebar.removeAttr(ZONED_ATTR);
            return;
        }

        var extraBlocks = collectExtraBlocks($sidebar, $summary);
        var $existingExtras = $sidebar.find('.' + EXTRAS_WRAPPER_CLASS).first();

        if ($sidebar.attr(ZONED_ATTR) === '1') {
            if ($existingExtras.length && extraBlocks.length) {
                return;
            }

            if (!$existingExtras.length && !extraBlocks.length) {
                return;
            }

            $existingExtras.remove();
            $sidebar.removeAttr(ZONED_ATTR);
        }

        $summary.addClass('awa-opc-sidebar__zone--summary');

        if (!extraBlocks.length) {
            $sidebar.attr(ZONED_ATTR, '1');
            return;
        }

        var $details = $(
            '<details class="' + EXTRAS_WRAPPER_CLASS + ' ' + EXTRAS_ZONE_CLASS + '">' +
            '<summary class="awa-opc-sidebar__extras-summary">' +
            $t('Opções adicionais') +
            '</summary>' +
            '<div class="awa-opc-sidebar__extras-body"></div>' +
            '</details>'
        );
        var $body = $details.find('.awa-opc-sidebar__extras-body');
        var $anchor = findTotalsAnchor($summary);

        extraBlocks.forEach(function ($block) {
            $body.append($block);
        });

        if ($anchor.length) {
            $anchor.after($details);
        } else {
            $summary.after($details);
        }

        $sidebar.attr(ZONED_ATTR, '1');
    }

    function sync()
    {
        $(SIDEBAR_SELECTOR).each(function () {
            applyZones($(this));
        });
    }

    return function () {
        if (!isCheckoutPage()) {
            return;
        }

        sync();

        if (window.MutationObserver && !window.__awaB2bSidebarZonesObserver) {
            var target = document.querySelector('.checkout-container, #checkout, .page-main') || document.body;

            window.__awaB2bSidebarZonesObserver = new window.MutationObserver(function () {
                window.requestAnimationFrame(sync);
            });
            window.__awaB2bSidebarZonesObserver.observe(target, {
                childList: true,
                subtree: true
            });
        }
    };
});
