/**
 * AWA stub — substitui bxSlider legado (quickview/bxslider, rokanthemes/bxslider).
 */
define(['jquery'], function ($) {
    'use strict';

    if (!$.fn.bxSlider) {
        $.fn.bxSlider = function () {
            return this;
        };
    }

    return $.fn.bxSlider;
});
