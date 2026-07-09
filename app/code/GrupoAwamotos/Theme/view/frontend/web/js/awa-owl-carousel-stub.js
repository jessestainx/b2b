/**
 * AWA stub — substitui Owl Carousel 1.x legado (rokanthemes/owl).
 * Carrosséis AWA usam scroll-snap / Swiper.
 */
define(['jquery'], function ($) {
    'use strict';

    if (!$.fn.owlCarousel) {
        $.fn.owlCarousel = function () {
            return this;
        };
    }

    return $.fn.owlCarousel;
});
