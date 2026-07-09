/**
 * GrupoAwamotos B2B - Header Link Injection
 * Adds B2B registration link to header for guests
 */
define([
    'jquery',
    'mage/cookies'
], function ($) {
    'use strict';

    return function (config) {
        let isLoggedIn = $.mage.cookies.get('customer_logged_in') === '1';

        // Only show for guests
        if (isLoggedIn) {
            return;
        }

        var $targetContainer = $('.top-bar-right .top-info, .top-account, .header.links');

        if ($targetContainer.length === 0) {
            // Fallback: add to header content
            $targetContainer = $('.header-content .top-header');
        }

        if ($targetContainer.length > 0) {
            let b2bLink = $('<a>', {
                href: config.registerUrl,
                'class': 'b2b-header-link',
                title: config.title,
                text: config.label
            });

            // Prepend the link
            $targetContainer.first().prepend(b2bLink);
        }
    };
});
