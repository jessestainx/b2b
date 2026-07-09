define(['jquery'], function ($) {
    'use strict';

    function normalizeCsvValue(value, key) {
        var pricePattern = /^\d+(?:\.\d+)?-\d+(?:\.\d+)?$/;
        var categoryPattern = /^\d+$/;
        var singleValue;

        if (!value) {
            return value;
        }

        if (value.indexOf(',') === -1) {
            singleValue = $.trim(value);
            if (!singleValue) {
                return '';
            }
            if (key === 'price') {
                return pricePattern.test(singleValue) ? singleValue : '';
            }
            if (key === 'cat') {
                return categoryPattern.test(singleValue) ? singleValue : '';
            }
            return singleValue;
        }

        var chunks = value.split(',');
        var normalizedChunks = [];
        var unique = [];
        var seen = {};

        chunks.forEach(function (chunk) {
            var normalized = $.trim(chunk);
            if (!normalized) {
                return;
            }
            normalizedChunks.push(normalized);
            if (seen[normalized]) {
                return;
            }
            seen[normalized] = true;
            unique.push(normalized);
        });

        if (key === 'price' && normalizedChunks.length) {
            for (var i = normalizedChunks.length - 1; i >= 0; i--) {
                if (pricePattern.test(normalizedChunks[i])) {
                    return normalizedChunks[i];
                }
            }
            return '';
        }

        if (key === 'cat' && normalizedChunks.length) {
            for (var j = normalizedChunks.length - 1; j >= 0; j--) {
                if (categoryPattern.test(normalizedChunks[j])) {
                    return normalizedChunks[j];
                }
            }
            return '';
        }

        return unique.join(',');
    }

    function normalizeUrl(url) {
        var parsed;
        var grouped = {};
        var keys = [];
        var normalized = new URLSearchParams();

        if (!url) {
            return url;
        }

        try {
            parsed = new URL(url, window.location.origin);
        } catch (e) {
            return url;
        }

        parsed.searchParams.forEach(function (value, key) {
            if (!grouped[key]) {
                grouped[key] = [];
                keys.push(key);
            }
            grouped[key].push(value);
        });

        keys.forEach(function (key) {
            var mergedValue = grouped[key].join(',');
            var normalizedValue = normalizeCsvValue(mergedValue, key);
            if (normalizedValue !== '') {
                normalized.set(key, normalizedValue);
            }
        });

        parsed.search = normalized.toString();

        return parsed.toString();
    }

    /**
     * Disable Rokanthemes LayeredAjax runtime on PLP.
     *
     * The category page was reproducing Chrome "page unresponsive" with this
     * module active. Returning a mage-init compatible no-op keeps normal filter
     * links usable while avoiding the heavy Ajax layer.
     */
    return function (config, element) {
        var currentHref = window.location.href;
        var normalizedCurrent;

        if (element) {
            $(element).attr('data-awa-layeredajax-disabled', '1');

            $(element).find('a[href]').each(function () {
                var $link = $(this);
                var href = $link.attr('href');
                var normalizedHref = normalizeUrl(href);

                if (normalizedHref && normalizedHref !== href) {
                    $link.attr('href', normalizedHref);
                }
            });
        }

        normalizedCurrent = normalizeUrl(currentHref);
        if (normalizedCurrent && normalizedCurrent !== currentHref && window.history && typeof window.history.replaceState === 'function') {
            window.history.replaceState(
                window.history.state || {},
                document.title,
                normalizedCurrent
            );
        }
    };
});
