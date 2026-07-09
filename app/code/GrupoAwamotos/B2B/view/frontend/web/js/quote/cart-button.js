define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var options = config || {};
        var targets = options.targets || ['.cart-summary', '.checkout-methods-items', '#cart-totals'];
        var fallbackTarget = options.fallbackTarget || '.column.main';
        var retryDelay = parseInt(options.retryDelay, 10) || 180;
        var maxAttempts = parseInt(options.maxAttempts, 10) || 20;

        var $wrapper = $(element);
        var $quoteBox = $wrapper.find('.b2b-cart-quote-box').first();

        function finalizePlacement()
        {
            $quoteBox.show();
            $wrapper.remove();
        }

        function placeInTarget()
        {
            var placed = false;
            var attempt;

            for (attempt = 0; attempt < targets.length; attempt += 1) {
                var $target = $(targets[attempt]).first();
                if (!$target.length) {
                    continue;
                }

                $target.after($quoteBox);
                placed = true;
                break;
            }

            if (placed) {
                finalizePlacement();
                return;
            }

            if (maxAttempts > 0) {
                maxAttempts -= 1;
                setTimeout(placeInTarget, retryDelay);
                return;
            }

            var $fallback = $(fallbackTarget).first();
            if ($fallback.length) {
                $fallback.append($quoteBox);
            } else {
                $wrapper.after($quoteBox);
            }

            finalizePlacement();
        }

        if (!$wrapper.length || !$quoteBox.length) {
            return;
        }

        placeInTarget();
    };
});
