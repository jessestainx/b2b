/**
 * superdeals-init.js — AWA override (scroll-snap via AWA_SHELF_CAROUSEL).
 */
define([
    'jquery'
], function ($) {
    'use strict';

    function scanShelf($scope) {
        var shelfRuntime = window.AWA_SHELF_CAROUSEL;

        if (!shelfRuntime || typeof shelfRuntime.scan !== 'function') {
            return false;
        }

        shelfRuntime.scan($scope[0]);

        if (typeof shelfRuntime.scheduleEqualize === 'function') {
            shelfRuntime.scheduleEqualize($scope[0]);
        }

        return true;
    }

    function scheduleShelfRetry($scope) {
        if ($scope.data('awaSuperdealsShelfRetry')) {
            return;
        }

        $scope.data('awaSuperdealsShelfRetry', 1);

        function retry() {
            if (scanShelf($scope)) {
                $scope.removeData('awaSuperdealsShelfRetry');
            }
        }

        document.addEventListener('awa-bootstrap-ready', retry, { once: true });
        document.addEventListener('awa:carousel-runtime-ready', retry, { once: true });
        window.setTimeout(retry, 250);
        window.setTimeout(retry, 1200);
    }

    return function (config, element) {
        var $scope = $(element);
        var countdownSelector = config.countdownSelector || '.super-deal-countdown';
        var labels = config.labels || {};
        var countdownConfig = config.countdown || {};

        $scope.addClass('awa-shelf awa-shelf--carousel awa-shelf--has-countdown');

        if (!scanShelf($scope)) {
            scheduleShelfRetry($scope);
        }

        var $countdownEls = $scope.find(countdownSelector);

        if ($countdownEls.length > 0) {
            require(['rokanthemes/timecircles'], function () {
                $countdownEls.each(function () {
                    var $countdown = $(this);

                    if ($countdown.data('awaSuperdealsCountdownInit') || typeof $countdown.TimeCircles !== 'function') {
                        return;
                    }

                    $countdown.data('awaSuperdealsCountdownInit', 1);
                    $countdown.TimeCircles({
                        fg_width: parseFloat(countdownConfig.fg_width) || 0.01,
                        bg_width: parseFloat(countdownConfig.bg_width) || 1.2,
                        text_size: parseFloat(countdownConfig.text_size) || 0.07,
                        circle_bg_color: countdownConfig.circle_bg_color || '#ffffff',
                        time: {
                            Days: { show: true, text: labels.days || 'Days', color: '#f9bc02' },
                            Hours: { show: true, text: labels.hours || 'Hours', color: '#f9bc02' },
                            Minutes: { show: true, text: labels.minutes || 'Mins', color: '#f9bc02' },
                            Seconds: { show: true, text: labels.seconds || 'Secs', color: '#f9bc02' }
                        }
                    });
                });
            });
        }
    };
});
